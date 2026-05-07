<?php
declare(strict_types=1);

class PlanController {

    // POST /plan/generate → IAOrchestrator (NLP→Match→Prompt→Claude→DB)
    public static function generate(): never {
        $auth = require_auth(['MIGRANT']);
        check_rate_limit("ia:{$auth['sub']}", RATE_LIMIT_IA, 3600);

        $lang   = $_GET['lang'] ?? $auth['lang'] ?? 'fr';
        $result = IAOrchestrator::generate_plan($auth['sub'], $lang);

        if (!$result['success']) {
            $code = match($result['error'] ?? '') {
                'profile_not_found'  => 404,
                'profile_incomplete' => 400,
                'plan_pending'       => 409,
                'ia_error'           => 502,
                default              => 400,
            };
            response_error($code, $result['error'], $result['message']);
        }

        response_success([
            'plan_id'    => $result['plan_id'],
            'plan'       => $result['plan'],
            'from_cache' => $result['from_cache'],
            'meta'       => $result['meta'],
        ], 'Plan généré avec succès', 201);
    }

    // GET /plan → plan du migrant connecté
    public static function get_mine(): never {
        $auth    = require_auth(['MIGRANT']);
        $profile = DB::row("SELECT id FROM profiles WHERE user_id=?", [$auth['sub']]);
        if (!$profile) response_error(404, 'no_profile', 'Créez votre profil en premier');

        $row = DB::row("
            SELECT p.*, pr.pays_origine, pr.ville_retour
            FROM plans p JOIN profiles pr ON p.profile_id = pr.id
            WHERE pr.user_id = ?
            ORDER BY p.created_at DESC LIMIT 1
        ", [$auth['sub']]);

        if (!$row) response_error(404, 'no_plan', 'Aucun plan. Utilisez POST /plan/generate');
        response_success(self::fmt($row));
    }

    // GET /plan/{id}
    public static function get_by_id(string $id): never {
        $auth = require_auth();
        $row  = DB::row("
            SELECT p.*, pr.pays_origine, pr.ville_retour, pr.tranche_age, pr.niveau_etudes, pr.competences, pr.vulnerabilites
            FROM plans p JOIN profiles pr ON p.profile_id = pr.id
            WHERE p.id = ?
        ", [$id]);
        if (!$row) response_error(404, 'not_found', 'Plan introuvable');

        // Migrant → seulement son propre plan
        if ($auth['role'] === 'MIGRANT') {
            $owner = DB::row("SELECT user_id FROM profiles WHERE id=?", [$row['profile_id']]);
            if (!$owner || $owner['user_id'] !== $auth['sub']) response_error(403, 'forbidden', 'Accès refusé');
        }
        response_success(self::fmt($row));
    }

    // GET /plans/pending → liste agent OIM
    public static function list_pending(): never {
        $auth   = require_auth(['AGENT','SUPERVISEUR','ADMIN']);
        $page   = max(1, (int)($_GET['page']  ?? 1));
        $limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $statut = in_array($_GET['statut'] ?? 'PENDING', ['PENDING','UNDER_REVIEW','VALIDATED','REJECTED','ALL'])
            ? ($_GET['statut'] ?? 'PENDING') : 'PENDING';

        $where  = $statut !== 'ALL' ? "WHERE p.statut = ?" : "WHERE 1=1";
        $params = $statut !== 'ALL' ? [$statut] : [];
        if (!empty($_GET['pays'])) { $where .= " AND pr.pays_origine=?"; $params[] = sanitize($_GET['pays']); }

        $rows  = DB::rows("SELECT p.id,p.statut,p.score_ia,p.created_at,p.validated_at,pr.pays_origine,pr.ville_retour,pr.tranche_age,pr.competences,pr.vulnerabilites FROM plans p JOIN profiles pr ON p.profile_id=pr.id {$where} ORDER BY p.created_at ASC LIMIT ? OFFSET ?", array_merge($params, [$limit, $offset]));
        $total = (int)(DB::row("SELECT COUNT(*) AS cnt FROM plans p JOIN profiles pr ON p.profile_id=pr.id {$where}", $params)['cnt'] ?? 0);

        response_success([
            'plans'      => array_map([self::class,'fmt'], $rows),
            'pagination' => ['page'=>$page,'limit'=>$limit,'total'=>$total,'pages'=>(int)ceil($total/$limit)],
        ]);
    }

    // PUT /plan/{id}/validate → agent OIM valide
    public static function validate(string $id): never {
        $auth = require_auth(['AGENT','SUPERVISEUR','ADMIN']);
        $body = get_body();
        $plan = DB::row("SELECT * FROM plans WHERE id=?", [$id]);
        if (!$plan) response_error(404, 'not_found', 'Plan introuvable');
        if (in_array($plan['statut'], ['VALIDATED','REJECTED'])) response_error(409, 'already_processed', "Plan déjà {$plan['statut']}");

        $upd = ['statut'=>'VALIDATED','agent_id'=>$auth['sub'],'validated_at'=>date('Y-m-d H:i:s'),'notes_agent'=>sanitize($body['notes_agent']??'')];
        $mods = [];
        foreach (['axe_emploi','axe_logement','axe_finance','axe_sante'] as $axe) {
            if (isset($body[$axe]) && is_array($body[$axe])) { $upd[$axe]=json_encode($body[$axe],JSON_UNESCAPED_UNICODE); $mods[$axe]=true; }
        }
        if (!empty($mods)) $upd['modifications_agent'] = json_encode($mods);

        $sets = implode(', ', array_map(fn($k) => "{$k}=?", array_keys($upd)));
        DB::query("UPDATE plans SET {$sets} WHERE id=?", array_merge(array_values($upd), [$id]));

        // Notifier le migrant
        $user = DB::row("SELECT u.id FROM users u JOIN profiles pr ON pr.user_id=u.id WHERE pr.id=?", [$plan['profile_id']]);
        if ($user) {
            DB::insert('notifications', ['user_id'=>$user['id'],'channel'=>'IN_APP','title'=>'Votre plan est validé !','body'=>'Votre plan a été validé par un agent OIM. Consultez-le maintenant.','data'=>json_encode(['plan_id'=>$id])]);
        }
        response_success(self::fmt(DB::row("SELECT * FROM plans WHERE id=?",[$id])), 'Plan validé, migrant notifié');
    }

    // PUT /plan/{id}/reject
    public static function reject(string $id): never {
        $auth = require_auth(['AGENT','SUPERVISEUR','ADMIN']);
        $body = get_body();
        if (empty($body['reason'])) response_error(400, 'reason_required', 'Motif de refus obligatoire');
        $plan = DB::row("SELECT id FROM plans WHERE id=?", [$id]);
        if (!$plan) response_error(404, 'not_found', 'Plan introuvable');
        DB::query("UPDATE plans SET statut='REJECTED', agent_id=?, notes_agent=?, rejected_reason=? WHERE id=?",
            [$auth['sub'], sanitize($body['notes_agent']??''), sanitize($body['reason']), $id]);
        response_success(null, 'Plan refusé. Le migrant peut régénérer.');
    }

    // GET /plan/{id}/pdf
    public static function export_pdf(string $id): never {
        $auth = require_auth();
        $plan = DB::row("SELECT * FROM plans WHERE id=?", [$id]);
        if (!$plan) response_error(404, 'not_found', 'Plan introuvable');

        $fmt  = self::fmt($plan);
        $lang = $_GET['lang'] ?? 'fr';

        $storageDir = __DIR__ . '/../api/storage/plans';
        if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

        $pdfPath = "{$storageDir}/plan_{$id}.pdf";
        file_put_contents($pdfPath, self::buildPlanPdf($fmt));

        $pdf_url = "/api/storage/plans/plan_{$id}.pdf";
        DB::query("UPDATE plans SET pdf_url=?, pdf_generated_at=datetime('now') WHERE id=?", [$pdf_url, $id]);
        DB::query("INSERT INTO audit_logs(user_id,action,entity_type,entity_id) VALUES(?,?,?,?)",
            [$auth['sub'], 'EXPORT_PDF', 'plans', $id]);

        response_success(['pdf_url' => $pdf_url, 'generated_at' => date('c')]);
    }

    private static function enc(string $s): string {
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace(
            ["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}", "\u{2014}", "\u{2013}", "\u{2026}"],
            ["'",        "'",        '"',        '"',        '-',        '-',        '...'],
            $s
        );
        $r = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
        return ($r !== false && $r !== '') ? $r : preg_replace('/[^\x00-\x7F\xa0-\xff]/', '?', $s) ?? $s;
    }

    // Échappe une chaîne pour les parenthèses PDF
    private static function pdfStr(string $s): string {
        return str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], self::enc($s));
    }

    // Ajoute des lignes de texte au stream PDF, retourne [stream, y_restant]
    private static function addLines(string &$stream, string $text, float $x, float &$y,
                                      float $lineH, float $yMin, float $fontSize): bool {
        $wrapped = wordwrap($text, 95, "\n", true);
        foreach (explode("\n", $wrapped) as $line) {
            if ($y < $yMin) return false; // page pleine
            $escaped = self::pdfStr($line);
            $stream .= "BT /F1 {$fontSize} Tf {$x} {$y} Td ({$escaped}) Tj ET\n";
            $y -= $lineH;
        }
        return true;
    }

    private static function buildPlanPdf(array $plan): string {
        $W = 595; $H = 842;
        $margin = 50; $yMin = 60;

        $pages   = [];
        $stream  = '';
        $y       = $H - $margin;

        $newPage = function() use (&$pages, &$stream, &$y, $H, $margin) {
            if ($stream !== '') $pages[] = $stream;
            $stream = '';
            $y = $H - $margin;
        };

        $ensureSpace = function(float $needed) use (&$y, $yMin, &$newPage) {
            if ($y - $needed < $yMin) $newPage();
        };

        // ── En-tête ──────────────────────────────────────────────
        $stream .= "BT /F2 18 Tf {$margin} {$y} Td (Plan de reintegration OIM) Tj ET\n";
        $y -= 24;
        $pays  = self::enc($plan['pays_origine'] ?? '');
        $ville = self::enc($plan['ville_retour'] ?? '');
        $score = $plan['score_ia'] ?? 0;
        $statut = $plan['statut'] ?? 'PENDING';
        $date  = isset($plan['created_at']) ? date('d/m/Y', strtotime($plan['created_at'])) : date('d/m/Y');
        $stream .= "BT /F1 10 Tf {$margin} {$y} Td (Destination : {$pays} - {$ville}   |   Score : {$score}/100   |   Statut : {$statut}   |   Date : {$date}) Tj ET\n";
        $y -= 8;
        // Ligne séparatrice
        $stream .= "{$margin} {$y} m " . ($W - $margin) . " {$y} l S\n";
        $y -= 16;

        // ── Résumé ────────────────────────────────────────────────
        if (!empty($plan['resume_global'])) {
            $stream .= "BT /F2 12 Tf {$margin} {$y} Td (RESUME) Tj ET\n";
            $y -= 16;
            self::addLines($stream, $plan['resume_global'], $margin, $y, 14, $yMin, 10);
            $y -= 10;
        }

        // ── 4 Axes ────────────────────────────────────────────────
        $axeConfig = [
            'axe_emploi'   => 'AXE 1 - EMPLOI & FORMATION',
            'axe_logement' => 'AXE 2 - LOGEMENT',
            'axe_finance'  => 'AXE 3 - SOUTIEN FINANCIER',
            'axe_sante'    => 'AXE 4 - SANTE',
        ];

        foreach ($axeConfig as $key => $title) {
            $axe   = $plan[$key] ?? [];
            $items = is_array($axe) ? ($axe['items'] ?? $axe) : [];
            if (!is_array($items)) $items = [];

            $ensureSpace(60);
            $stream .= "BT /F2 12 Tf {$margin} {$y} Td (" . self::pdfStr($title) . ") Tj ET\n";
            $y -= 6;
            $stream .= "{$margin} {$y} m " . ($W - $margin) . " {$y} l S\n";
            $y -= 14;

            if (empty($items)) {
                $stream .= "BT /F1 9 Tf {$margin} {$y} Td (Aucune recommandation.) Tj ET\n";
                $y -= 14;
            } else {
                foreach ($items as $i => $item) {
                    $ensureSpace(40);
                    $num   = $i + 1;
                    $titre = self::pdfStr(($item['titre'] ?? ''));
                    $org   = self::pdfStr(($item['organisme'] ?? ''));
                    $cout  = self::pdfStr(($item['cout_estime'] ?? 'Gratuit'));
                    $duree = self::pdfStr(($item['duree'] ?? ''));
                    $desc  = $item['description'] ?? '';

                    $stream .= "BT /F2 10 Tf " . ($margin+8) . " {$y} Td ({$num}. {$titre}) Tj ET\n";
                    $y -= 13;
                    if ($org)   { $stream .= "BT /F1 9 Tf " . ($margin+16) . " {$y} Td (Organisme : {$org}) Tj ET\n"; $y -= 12; }
                    if ($cout)  { $stream .= "BT /F1 9 Tf " . ($margin+16) . " {$y} Td (Cout : {$cout}   Duree : {$duree}) Tj ET\n"; $y -= 12; }
                    if ($desc)  {
                        self::addLines($stream, $desc, $margin+16, $y, 12, $yMin, 9);
                    }
                    $y -= 4;
                }
            }
            $y -= 10;
        }

        // ── Pied de page ──────────────────────────────────────────
        $ensureSpace(30);
        $stream .= "{$margin} " . ($yMin+10) . " m " . ($W - $margin) . " " . ($yMin+10) . " l S\n";
        $stream .= "BT /F1 8 Tf {$margin} " . ($yMin) . " Td (Document genere par HorizonAI - Programme AVRR OIM) Tj ET\n";

        $pages[] = $stream;

        // ── Assemblage PDF multi-pages ─────────────────────────────
        return self::assemblePdf($pages, $W, $H);
    }

    private static function assemblePdf(array $pages, int $W, int $H): string {
        $pageCount = count($pages);
        $objects   = [];

        // obj 1 : Catalog
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        // obj 2 : Pages (placeholder)
        $kidRefs   = implode(' ', array_map(fn($i) => (3 + $i * 2) . " 0 R", range(0, $pageCount - 1)));
        $objects[] = "<< /Type /Pages /Kids [{$kidRefs}] /Count {$pageCount} /MediaBox [0 0 {$W} {$H}] >>";

        // Polices (obj fixes à la fin)
        $fontObjBase = 3 + $pageCount * 2;
        $fontResources = "/Font << /F1 {$fontObjBase} 0 R /F2 " . ($fontObjBase + 1) . " 0 R >>";

        foreach ($pages as $i => $pageStream) {
            $contentObjIdx = 3 + $i * 2;       // page obj
            $streamObjIdx  = $contentObjIdx + 1; // stream obj
            $streamLen = strlen($pageStream);

            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$W} {$H}] /Resources << {$fontResources} >> /Contents {$streamObjIdx} 0 R >>";
            $objects[] = "<< /Length {$streamLen} >>\nstream\n{$pageStream}\nendstream";
        }

        // Polices
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        // Construire le PDF
        $pdf      = "%PDF-1.4\n";
        $offsets  = [];
        foreach ($objects as $idx => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($idx + 1) . " 0 obj\n{$body}\nendobj\n";
        }

        $xrefStart = strlen($pdf);
        $n = count($objects) + 1;
        $xref = "xref\n0 {$n}\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $xref .= sprintf("%010d 00000 n \n", $off);
        }
        $pdf .= $xref;
        $pdf .= "trailer\n<< /Size {$n} /Root 1 0 R >>\nstartxref\n{$xrefStart}\n%%EOF";

        return $pdf;
    }

    public static function fmt(array $p): array {
        foreach (['axe_emploi','axe_logement','axe_finance','axe_sante','modifications_agent'] as $f) {
            if (isset($p[$f]) && is_string($p[$f])) $p[$f] = json_decode($p[$f], true) ?? [];
        }
        foreach (['competences','langues'] as $f) {
            if (isset($p[$f]) && is_string($p[$f])) $p[$f] = json_decode($p[$f], true) ?? [];
        }
        if (isset($p['vulnerabilites']) && is_string($p['vulnerabilites'])) {
            $p['vulnerabilites'] = array_values(array_filter(explode(',', trim($p['vulnerabilites'], '{}'))));
        }
        return $p;
    }
}
