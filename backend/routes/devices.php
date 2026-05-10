<?php
// ================================================================
// HorizonAI — Device Controller (Gestion bornes kiosque)
// Chaque borne physique a un token unique.
// Permet le suivi en temps réel (ping, sessions, statut online).
// ================================================================
declare(strict_types=1);

class DeviceController {

    // ── Lister toutes les bornes ────────────────────────────────
    public static function list(): never {
        require_auth(['ADMIN','SUPERVISEUR']);
        $devices = DB::rows("
            SELECT d.*,
                COUNT(DISTINCT s.id)                                          AS sessions_total,
                SUM(CASE WHEN s.statut='COMPLETED' THEN 1 ELSE 0 END)        AS sessions_completed,
                SUM(CASE WHEN s.statut='IN_PROGRESS' THEN 1 ELSE 0 END)      AS sessions_active,
                MAX(s.created_at)                                             AS last_session_at
            FROM kiosk_devices d
            LEFT JOIN interview_sessions s ON s.device_id = d.id
            GROUP BY d.id
            ORDER BY d.last_ping DESC
        ");
        foreach ($devices as &$d) {
            $d['is_online'] = !empty($d['last_ping'])
                && strtotime($d['last_ping']) > (time() - 120); // online si ping < 2 min
        }
        response_success($devices);
    }

    // ── Créer une nouvelle borne ────────────────────────────────
    public static function create(): never {
        require_auth(['ADMIN']);
        $body  = get_body();
        $token = 'hzn_' . bin2hex(random_bytes(20)); // token unique préfixé hzn_

        $id = DB::insert('kiosk_devices', [
            'device_token' => $token,
            'nom'          => sanitize($body['nom']  ?? 'Kiosque OIM'),
            'lieu'         => sanitize($body['lieu'] ?? ''),
            'is_active'    => 1,
        ]);
        DB::query("INSERT INTO audit_logs(action,entity_type,entity_id,metadata) VALUES(?,?,?,?)",
            ['DEVICE_CREATED','kiosk_devices',$id, json_encode(['nom'=>$body['nom']??'','lieu'=>$body['lieu']??''])]);

        $device = DB::row('SELECT * FROM kiosk_devices WHERE id=?', [$id]);
        response_success($device, 'Borne enregistrée. Conservez le token en lieu sûr.', 201);
    }

    // ── Détail d'une borne + ses sessions ──────────────────────
    public static function get(string $id): never {
        require_auth(['ADMIN','SUPERVISEUR']);
        $device = DB::row("
            SELECT d.*,
                COUNT(s.id)          AS sessions_total,
                MAX(s.created_at)    AS last_session_at,
                SUM(CASE WHEN s.statut='COMPLETED' THEN 1 ELSE 0 END) AS sessions_completed
            FROM kiosk_devices d
            LEFT JOIN interview_sessions s ON s.device_id=d.id
            WHERE d.id=?
            GROUP BY d.id
        ", [$id]);
        if (!$device) response_error(404, 'not_found', 'Borne introuvable');
        $device['is_online'] = !empty($device['last_ping'])
            && strtotime($device['last_ping']) > (time() - 120);

        $sessions = DB::rows("
            SELECT id, lang, statut, etape, rdv_date, synced, created_at
            FROM interview_sessions
            WHERE device_id=?
            ORDER BY created_at DESC LIMIT 50
        ", [$id]);
        response_success(['device'=>$device,'sessions'=>$sessions]);
    }

    // ── Mettre à jour nom / lieu / statut ───────────────────────
    public static function update(string $id): never {
        require_auth(['ADMIN']);
        $body = get_body(); $sets = []; $params = [];
        if (isset($body['nom']))       { $sets[]="nom=?";       $params[]=sanitize($body['nom']); }
        if (isset($body['lieu']))      { $sets[]="lieu=?";      $params[]=sanitize($body['lieu']); }
        if (isset($body['is_active'])) { $sets[]="is_active=?"; $params[]=(int)(bool)$body['is_active']; }
        if (empty($sets)) response_error(400,'no_changes','Rien à mettre à jour');
        $params[] = $id;
        DB::query("UPDATE kiosk_devices SET ".implode(',',$sets)." WHERE id=?", $params);
        response_success(DB::row('SELECT * FROM kiosk_devices WHERE id=?',[$id]),'Borne mise à jour');
    }

    // ── Désactiver une borne ────────────────────────────────────
    public static function delete(string $id): never {
        require_auth(['ADMIN']);
        DB::query("UPDATE kiosk_devices SET is_active=0 WHERE id=?",[$id]);
        DB::query("INSERT INTO audit_logs(action,entity_type,entity_id) VALUES(?,?,?)",
            ['DEVICE_DISABLED','kiosk_devices',$id]);
        response_success(null,'Borne désactivée');
    }

    // ── Ping heartbeat ← appelé automatiquement depuis la borne ─
    // Auth: X-Device-Token header
    public static function ping(): never {
        $token = $_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '';
        if (empty($token)) response_error(401,'missing_token','X-Device-Token requis');

        if ($token === 'DEMO_KIOSK_TOKEN') {
            response_success(['status'=>'ok','mode'=>'demo','ts'=>date('c')]);
        }

        $device = DB::row('SELECT id,nom,lieu FROM kiosk_devices WHERE device_token=? AND is_active=1', [$token]);

        if (!$device) {
            if (APP_ENV !== 'production') {
                // Auto-enregistrer en développement
                $id = DB::insert('kiosk_devices',['device_token'=>$token,'nom'=>'Kiosque Auto','lieu'=>'OIM','is_active'=>1]);
                DB::query("UPDATE kiosk_devices SET last_ping=datetime('now') WHERE id=?",[$id]);
                response_success(['status'=>'ok','mode'=>'auto_registered','ts'=>date('c')]);
            }
            response_error(403,'unknown_device','Borne non reconnue. Enregistrez-la depuis l\'admin.');
        }

        DB::query("UPDATE kiosk_devices SET last_ping=datetime('now') WHERE id=?",[$device['id']]);
        response_success([
            'status' => 'ok',
            'device' => ['id'=>$device['id'],'nom'=>$device['nom'],'lieu'=>$device['lieu']],
            'ts'     => date('c'),
        ]);
    }

    // ── Réinitialiser le token d'une borne ─────────────────────
    public static function rotate_token(string $id): never {
        require_auth(['ADMIN']);
        $device = DB::row('SELECT id FROM kiosk_devices WHERE id=?',[$id]);
        if (!$device) response_error(404,'not_found','Borne introuvable');
        $new_token = 'hzn_' . bin2hex(random_bytes(20));
        DB::query("UPDATE kiosk_devices SET device_token=? WHERE id=?",[$new_token,$id]);
        response_success(['device_token'=>$new_token],'Token régénéré. Mettez à jour la configuration de la borne.');
    }

    // ── Stats globales kiosque ──────────────────────────────────
    public static function stats(): never {
        require_auth(['ADMIN','SUPERVISEUR']);
        $global = DB::row("
            SELECT
                COUNT(DISTINCT d.id)  AS bornes_total,
                SUM(d.is_active)      AS bornes_actives,
                COUNT(s.id)           AS sessions_total,
                SUM(CASE WHEN s.statut='COMPLETED' THEN 1 ELSE 0 END) AS sessions_completed,
                SUM(CASE WHEN s.synced=0 THEN 1 ELSE 0 END)           AS sessions_pending_sync
            FROM kiosk_devices d
            LEFT JOIN interview_sessions s ON s.device_id=d.id
        ");
        $by_lang = DB::rows("
            SELECT lang, COUNT(*) AS total
            FROM interview_sessions
            GROUP BY lang ORDER BY total DESC
        ");
        $online_count = (int)(DB::row("
            SELECT COUNT(*) AS n FROM kiosk_devices
            WHERE is_active=1 AND last_ping > datetime('now','-2 minutes')
        ")['n'] ?? 0);

        response_success([
            'global'       => array_merge($global, ['bornes_online'=>$online_count]),
            'by_lang'      => $by_lang,
        ]);
    }
}
