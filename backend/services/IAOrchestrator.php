<?php
// ================================================================
// HorizonAI — IA Orchestrator (chef d'orchestre)
//
// Pipeline generate_plan():
//   DB (profil) → NLPEngine (normalise) → DB (opportunités)
//   → MatchingEngine (score) → PromptBuilder (prompt)
//   → ClaudeClient (Claude API) → DB (persiste plan + sessions)
//   → Notifications agents OIM
//
// Pipeline chat():
//   DB (profil + plan) → NLPEngine (détecte langue)
//   → ClaudeClient::chat() → DB (log ia_session)
// ================================================================
declare(strict_types=1);

class IAOrchestrator {

    // ── GÉNÉRATION PLAN — pipeline complet ────────────────────────
    public static function generate_plan(string $user_id, string $lang = 'fr'): array {

        // 1. Charger profil depuis SQLite
        $profile = DB::row("
            SELECT p.*, u.lang_pref
            FROM profiles p JOIN users u ON u.id = p.user_id
            WHERE p.user_id = ?
        ", [$user_id]);

        if (!$profile) return self::err('profile_not_found', 'Créez votre profil avant de générer un plan.');

        $lang = $lang ?: $profile['lang_pref'] ?: 'fr';

        // 2. Normaliser le profil via NLPEngine
        $profile = self::prepare_profile($profile, $lang);

        // Seuil completion réduit pour les profils analphabètes (moins de champs disponibles)
        $is_analphabete = NLPEngine::is_analphabete($profile['niveau_etudes'] ?? '', $profile['alphabetisation'] ?? 'OUI');
        $min_completion = $is_analphabete ? 40 : 60;
        if ((int)$profile['completion_pct'] < $min_completion) {
            return self::err('profile_incomplete', "Profil à {$profile['completion_pct']}%. Minimum {$min_completion}% requis.");
        }

        // Charger les réalités économiques du pays de retour
        $country_data = DB::row('SELECT * FROM country_profiles WHERE pays = ?', [$profile['pays_origine']]) ?? [];

        // 3. Vérifier qu'aucun plan n'est déjà en cours (SQLite)
        $existing = DB::row("
            SELECT plans.id AS id FROM plans
            JOIN profiles pr ON plans.profile_id = pr.id
            WHERE pr.user_id = ? AND plans.statut IN ('PENDING','UNDER_REVIEW') LIMIT 1
        ", [$user_id]);
        if ($existing) return self::err('plan_pending', "Un plan est déjà en attente de validation (#{$existing['id']}).");

        // 4. Récupérer et scorer les opportunités depuis SQLite (via MatchingEngine)
        $matched = MatchingEngine::match_from_db($profile, 3);
        $score_base = MatchingEngine::plan_score($matched, $profile);

        // 5. Appeler Claude API avec prompt adapté (standard ou analphabète)
        $ia = ClaudeClient::generate_plan($profile, $matched, $lang, [
            'Score prévu'   => "{$score_base}/100",
            'Langue'        => strtoupper($lang),
            'Prompt v'      => PromptBuilder::VERSION,
            'Analphabete'   => $is_analphabete ? 'OUI' : 'NON',
            'Country data'  => !empty($country_data) ? 'OK' : 'ABSENT',
        ], $is_analphabete ? PromptBuilder::system_analphabete($lang) : null,
           $is_analphabete ? PromptBuilder::plan_analphabete($profile, $matched, $country_data, $lang) : null);

        if (!$ia['success']) {
            self::log_ia_error($user_id, $ia['error'] ?? 'unknown');
            return self::err('ia_error', 'Erreur IA. Réessayez dans quelques instants.');
        }

        $plan = $ia['plan'];

        // 6. Fusionner score IA + score matching
        $diff = abs($plan['score'] - $score_base);
        $plan['score'] = $diff <= 20 ? $plan['score'] : (int)(($plan['score'] + $score_base) / 2);

        // 7. Alertes vulnérabilité
        if (empty($plan['alerte_vulnerabilite'])) {
            $plan['alerte_vulnerabilite'] = self::vuln_alert($profile, $plan);
        }

        // 8. Tout persister en SQLite (transaction atomique)
        $plan_id = DB::transaction(function() use ($profile, $plan, $ia, $user_id, $lang, $matched) {
            // Créer le plan
            $pid = DB::insert('plans', [
                'profile_id'    => $profile['id'],
                'axe_emploi'    => json_encode($plan['axes']['emploi']   ?? ['label'=>'Emploi','items'=>[]], JSON_UNESCAPED_UNICODE),
                'axe_logement'  => json_encode($plan['axes']['logement'] ?? ['label'=>'Logement','items'=>[]], JSON_UNESCAPED_UNICODE),
                'axe_finance'   => json_encode($plan['axes']['finance']  ?? ['label'=>'Finance','items'=>[]], JSON_UNESCAPED_UNICODE),
                'axe_sante'     => json_encode($plan['axes']['sante']    ?? ['label'=>'Santé','items'=>[]], JSON_UNESCAPED_UNICODE),
                'resume_global' => $plan['resume'] ?? '',
                'score_ia'      => $plan['score'],
                'model_version' => $ia['model'] ?? CLAUDE_MODEL,
                'prompt_version'=> $ia['prompt_version'] ?? PromptBuilder::VERSION,
                'tokens_input'  => $ia['tokens_in']  ?? $ia['tokens_input']  ?? 0,
                'tokens_output' => $ia['tokens_out'] ?? $ia['tokens_output'] ?? 0,
                'latence_ms'    => $ia['latence_ms'] ?? 0,
                'statut'        => 'PENDING',
            ]);

            // Logger la session IA
            DB::insert('ia_sessions', [
                'plan_id'       => $pid,
                'user_id'       => $user_id,
                'endpoint'      => '/v1/messages',
                'model'         => $ia['model'] ?? CLAUDE_MODEL,
                'tokens_input'  => $ia['tokens_in']  ?? 0,
                'tokens_output' => $ia['tokens_out'] ?? 0,
                'cout_usd'      => $ia['cout_usd'] ?? 0,
                'latence_ms'    => $ia['latence_ms'] ?? 0,
                'success'       => true,
            ]);

            // Lier opportunités utilisées
            $axe_type_map = ['emploi'=>'EMPLOI','logement'=>'LOGEMENT','finance'=>'MICRO_CREDIT','sante'=>'SANTE'];
            foreach ($plan['axes'] as $axe_k => $axe) {
                foreach ($axe['items'] ?? [] as $item) {
                    if (!empty($item['source_opportunity_id'])) {
                        try { DB::query("INSERT INTO plan_opportunities(plan_id,opportunity_id,axe,priorite) VALUES(?,?,?,?) ON CONFLICT DO NOTHING",
                            [$pid, $item['source_opportunity_id'], $axe_type_map[$axe_k]??'EMPLOI', $item['priorite']??1]); }
                        catch (\Throwable) {}
                    }
                }
            }

            // Audit log
            DB::query("INSERT INTO audit_logs(user_id,action,entity_type,entity_id,metadata) VALUES(?,?,?,?,?)",
                [$user_id, 'PLAN_GENERATED', 'plans', $pid, json_encode(['score'=>$plan['score'],'lang'=>$lang,'from_cache'=>$ia['_from_cache']??false])]);

            return $pid;
        });

        // 9. Notifier les agents OIM
        self::notify_agents($plan_id, $profile, $plan['score']);

        return [
            'success'     => true,
            'plan_id'     => $plan_id,
            'plan'        => $plan,
            'from_cache'  => $ia['_from_cache'] ?? false,
            'latence_ms'  => $ia['latence_ms']  ?? 0,
            'meta' => [
                'model'          => $ia['model'] ?? CLAUDE_MODEL,
                'prompt_version' => PromptBuilder::VERSION,
                'tokens_total'   => ($ia['tokens_in']??0) + ($ia['tokens_out']??0),
                'cout_usd'       => $ia['cout_usd'] ?? 0,
                'opps_matched'   => array_sum(array_map('count', $matched)),
            ],
        ];
    }

    // ── CHAT — pipeline complet ───────────────────────────────────
    public static function chat(string $user_id, string $message, array $history = [], ?string $lang = null): array {
        // Charger profil + plan depuis SQLite
        $profile = DB::row("SELECT * FROM profiles WHERE user_id = ?", [$user_id]);
        if (!$profile) return self::err('no_profile', 'Profil introuvable');

        $plan_row = DB::row("
            SELECT * FROM plans JOIN profiles pr ON plans.profile_id = pr.id
            WHERE pr.user_id = ? AND plans.statut IN ('VALIDATED','PENDING')
            ORDER BY plans.created_at DESC LIMIT 1
        ", [$user_id]);

        $profile = self::prepare_profile($profile, $profile['lang_pref'] ?? 'fr');
        $plan    = $plan_row ? self::fmt_plan($plan_row) : [];

        // Déterminer la langue de réponse : prioriser le paramètre explicitement envoyé
        if (!$lang) {
            $lang = NLPEngine::detect_language($message);
            if ($lang === 'fr' && $profile['lang_pref']) $lang = $profile['lang_pref'];
        }
        $lang = $lang ?: ($profile['lang_pref'] ?? 'fr');

        // Appeler Claude API
        $res = ClaudeClient::chat($message, $plan, $profile, $lang, $history);

        if (!$res['success']) return self::err('chat_error', 'Assistant indisponible. Réessayez.');

        // Logger dans ia_sessions
        try { DB::insert('ia_sessions', [
            'user_id'       => $user_id,
            'endpoint'      => '/chat',
            'model'         => CLAUDE_MODEL,
            'tokens_input'  => $res['tokens_in']  ?? 0,
            'tokens_output' => $res['tokens_out'] ?? 0,
            'cout_usd'      => $res['cout_usd']   ?? 0,
            'latence_ms'    => $res['latence_ms']  ?? 0,
            'success'       => true,
        ]); } catch (\Throwable) {}

        return ['success' => true, 'message' => $res['message'], 'lang' => $lang, 'latence_ms' => $res['latence_ms'] ?? 0];
    }

    // ── Helpers internes ────────────────────────────────────────────
    private static function prepare_profile(array $p, string $lang): array {
        foreach (['competences','langues'] as $f) {
            if (isset($p[$f]) && is_string($p[$f])) $p[$f] = json_decode($p[$f], true) ?? [];
        }
        // NLPEngine normalise les compétences formelles
        if (!empty($p['competences'])) $p['competences'] = NLPEngine::normalize_skills($p['competences'], $lang);
        // Enrichir avec compétences informelles si présentes
        if (!empty($p['competences_informelles'])) {
            $inf = NLPEngine::normalize_competences_informelles($p['competences_informelles']);
            $p['competences'] = array_values(array_unique(array_merge($p['competences'] ?? [], $inf)));
        }
        if (isset($p['vulnerabilites']) && is_string($p['vulnerabilites'])) {
            $p['vulnerabilites'] = array_values(array_filter(explode(',', trim($p['vulnerabilites'], '{}'))));
        }
        return $p;
    }

    private static function vuln_alert(array $p, array $plan): ?string {
        $vulns = (array)($p['vulnerabilites'] ?? []);
        if (empty($vulns) || in_array('AUCUNE', $vulns)) return null;
        $alerts = [];
        if (in_array('SANTE_URGENTE', $vulns))        $alerts[] = 'Urgence médicale — Prise en charge immédiate requise à l\'arrivée.';
        if (in_array('FEMME_ENCEINTE', $vulns))       $alerts[] = 'Suivi maternel — Consultation gynécologique dans les 48h.';
        if (in_array('MINEUR_NON_ACCOMPAGNE', $vulns)) $alerts[] = 'Mineur — Service protection enfance OIM obligatoire.';
        if (in_array('VICTIME_TRAITE', $vulns))       $alerts[] = 'Situation sensible — Protocole protection spécifique.';
        return !empty($alerts) ? implode(' | ', $alerts) : null;
    }

    private static function notify_agents(string $plan_id, array $profile, int $score): void {
        $agents = DB::rows("SELECT id FROM users WHERE role IN ('AGENT','SUPERVISEUR') AND is_active=1");
        $urgency = $score < 60 ? 'Urgent' : ($score >= 85 ? 'Priorité normale' : 'Attention');
        $msg = "{$urgency} — Nouveau plan ({$profile['pays_origine']} · Score:{$score}/100)";
        foreach ($agents as $a) {
            try { DB::insert('notifications', ['user_id'=>$a['id'],'channel'=>'IN_APP','title'=>'Nouveau plan à valider','body'=>$msg,'data'=>json_encode(['plan_id'=>$plan_id,'score'=>$score])]); }
            catch (\Throwable) {}
        }
    }

    private static function log_ia_error(string $uid, string $err): void {
        try { DB::insert('ia_sessions', ['user_id'=>$uid,'endpoint'=>'/v1/messages','model'=>CLAUDE_MODEL,'success'=>false,'error_msg'=>substr($err,0,500)]); } catch (\Throwable) {}
    }

    private static function fmt_plan(array $r): array {
        foreach (['axe_emploi','axe_logement','axe_finance','axe_sante'] as $f) {
            if (isset($r[$f]) && is_string($r[$f])) $r[$f] = json_decode($r[$f], true) ?? [];
        }
        return ['resume'=>$r['resume_global']??'','score'=>$r['score_ia']??0,'axes'=>['emploi'=>$r['axe_emploi']??[],'logement'=>$r['axe_logement']??[],'finance'=>$r['axe_finance']??[],'sante'=>$r['axe_sante']??[]]];
    }

    private static function err(string $code, string $msg): array {
        return ['success'=>false,'error'=>$code,'message'=>$msg];
    }
}
