<?php
// ================================================================
// HorizonAI — Interview Routes (Kiosque OIM)
// Auth: X-Device-Token header (pas de JWT migrant)
// ================================================================
declare(strict_types=1);

class InterviewController {

    // Valider le device token depuis le header
    private static function require_device(): string {
        $token = $_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '';
        if (empty($token)) response_error(401, 'missing_device_token', 'X-Device-Token requis');

        // Token spécial pour dev/démo
        if ($token === 'DEMO_KIOSK_TOKEN') return 'demo-device';

        $device = DB::row('SELECT id FROM kiosk_devices WHERE device_token=? AND is_active=1', [$token]);
        if (!$device) {
            // Auto-enregistrer en dev
            if (APP_ENV !== 'production') {
                $device_id = DB::insert('kiosk_devices', ['device_token'=>$token,'nom'=>'Kiosque auto','lieu'=>'OIM']);
                return $device_id;
            }
            response_error(403, 'invalid_device', 'Appareil non reconnu');
        }
        // Mise à jour last_ping
        DB::query("UPDATE kiosk_devices SET last_ping=datetime('now') WHERE id=?", [$device['id']]);
        return $device['id'];
    }

    // POST /api/interview/start
    public static function start(): never {
        $device_id = self::require_device();
        $body = get_body();
        $lang = in_array($body['lang'] ?? 'fr', ['fr','en','ar','wo']) ? ($body['lang'] ?? 'fr') : 'fr';

        check_rate_limit("interview_start:{$device_id}", 20, 3600);

        $result = InterviewAgent::start($device_id, $lang);
        response_success($result, 'Entretien démarré', 201);
    }

    // POST /api/interview/{id}/step
    public static function step(string $session_id): never {
        $device_id = self::require_device();
        $body = get_body();

        if (empty($body['message'])) response_error(400, 'missing_message', '"message" requis');

        check_rate_limit("interview_step:{$device_id}", 120, 3600);

        // Vérifier que la session appartient au device
        $session = DB::row('SELECT device_id FROM interview_sessions WHERE id=?', [$session_id]);
        if (!$session) response_error(404, 'not_found', 'Session introuvable');
        if ($session['device_id'] !== $device_id && $device_id !== 'demo-device') {
            response_error(403, 'forbidden', 'Session non accessible depuis cet appareil');
        }

        $result = InterviewAgent::next_step($session_id, trim($body['message']));

        if (!$result['success']) response_error(422, $result['error'], $result['message']);
        response_success($result);
    }

    // GET /api/interview/{id}
    public static function get(string $session_id): never {
        self::require_device();
        $session = InterviewAgent::get($session_id);
        if (!$session) response_error(404, 'not_found', 'Session introuvable');
        response_success($session);
    }

    // POST /api/interview/{id}/abandon
    public static function abandon(string $session_id): never {
        self::require_device();
        InterviewAgent::abandon($session_id);
        response_success(null, 'Session abandonnée');
    }

    // POST /api/interview/sync  (sessions offline)
    public static function sync(): never {
        self::require_device();
        $body = get_body();
        if (empty($body['sessions']) || !is_array($body['sessions'])) {
            response_error(400, 'missing_sessions', '"sessions" array requis');
        }
        $result = InterviewAgent::sync_offline($body['sessions']);
        response_success($result, "Sync: {$result['synced']} OK, {$result['failed']} erreurs");
    }

    // GET /api/interview/{id}/rdv
    public static function rdv(string $session_id): never {
        self::require_device();
        $session = DB::row('SELECT rdv_date,rdv_lieu,statut,lang FROM interview_sessions WHERE id=?', [$session_id]);
        if (!$session) response_error(404, 'not_found', 'Session introuvable');

        $lang = $session['lang'] ?? 'fr';
        $rdv_messages = [
            'fr' => "Votre rendez-vous avec un agent OIM est fixé au {$session['rdv_date']}. Lieu : {$session['rdv_lieu']}. Merci pour votre confiance.",
            'en' => "Your appointment with an IOM agent is scheduled for {$session['rdv_date']}. Location: {$session['rdv_lieu']}. Thank you for your trust.",
            'ar' => "موعدك مع وكيل المنظمة الدولية للهجرة محدد في {$session['rdv_date']}. المكان: {$session['rdv_lieu']}.",
            'wo' => "Sa rendez-vous ak agent OIM dafa am ci {$session['rdv_date']}. Nokk bi: {$session['rdv_lieu']}.",
        ];

        response_success([
            'rdv_date'  => $session['rdv_date'],
            'rdv_lieu'  => $session['rdv_lieu'],
            'statut'    => $session['statut'],
            'message'   => $rdv_messages[$lang] ?? $rdv_messages['fr'],
        ]);
    }
}
