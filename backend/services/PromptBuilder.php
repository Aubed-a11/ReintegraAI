<?php
declare(strict_types=1);

class PromptBuilder {
    const VERSION = '2.1';

    // ── System prompt ← transmis à Claude comme rôle système ─────
    public static function system(string $lang = 'fr'): string {
        $ethics = match($lang) {
            'ar' => "- الحفاظ على الكرامة الإنسانية\n- لا ضرر ولا ضرار\n- سرية البيانات\n- إشراف بشري إلزامي",
            'en' => "- Preserve human dignity\n- Do no harm\n- Strict data confidentiality\n- Mandatory human oversight",
            default => "- Préserver la dignité humaine\n- Do no harm — éviter tout risque\n- Confidentialité stricte\n- Supervision humaine obligatoire",
        };
        return "Tu es HorizonAI, assistant IA du programme AVRR de l'OIM (Organisation Internationale pour les Migrations).\nTon rôle : générer des plans de réintégration structurés, personnalisés, réalistes et éthiques.\nChaque plan est validé par un agent OIM humain avant remise au migrant.\n\nPRINCIPES ÉTHIQUES:\n{$ethics}\n\nRègle absolue: réponds UNIQUEMENT en JSON valide. Langue de réponse: {$lang}.";
    }

    // ── Prompt génération plan ← injecte profil + opportunités matchées ─
    public static function plan(array $profile, array $matched_opps, string $lang = 'fr'): string {
        $profile_text = NLPEngine::profile_to_text($profile, $lang);
        $opps_text    = self::format_opps($matched_opps, $lang);

        $labels = [
            'fr'  => ['Emploi & Formation','Logement','Soutien financier','Santé'],
            'en'  => ['Employment & Training','Housing','Financial Support','Health'],
            'ar'  => ['التوظيف والتدريب','السكن','الدعم المالي','الصحة'],
            'wo'  => ['Liggéey & Jàng','Ker','Xaalis','Wér ak jam'],
            'bm'  => ['Baara & Kalan','So','Wari Dɛmɛ','Kɛnɛya'],
            'ha'  => ['Aiki & Horarwa','Gida','Tallafin Kudi','Lafiya'],
            'ff'  => ['Golle & Janngirde','Galle','Ndukkal Kalis','Cellal'],
            'tzm' => ['Amahil & Ulmad','Axxam','Tallalt n Iqriḍen','Taselkimt'],
        ][$lang] ?? ['Emploi & Formation','Logement','Soutien financier','Santé'];

        // Pour les langues sans support TTS natif, Claude répond en français + langue locale
        $lang_instruction = in_array($lang, ['bm','ha','ff','tzm'])
            ? "Réponds en FRANÇAIS pour la logique JSON, mais traduis les champs 'titre', 'description' et 'resume' en {$lang} ET en français (format: 'texte en {$lang} | French: texte français')."
            : "Langue de réponse: {$lang}.";

        return "## PROFIL MIGRANT (anonymisé)\n{$profile_text}\n\n## OPPORTUNITÉS DISPONIBLES DANS LE PAYS\n{$opps_text}\n\n## INSTRUCTIONS\nGénère un plan personnalisé en exploitant les opportunités listées. Réponds UNIQUEMENT avec ce JSON:\n{\n  \"score\": <0-100>,\n  \"resume\": \"<2-3 phrases>\",\n  \"axes\": {\n    \"emploi\":   {\"label\": \"{$labels[0]}\", \"items\": [{\"titre\":\"\",\"description\":\"\",\"organisme\":\"\",\"contact\":\"\",\"cout_estime\":\"\",\"duree\":\"\",\"priorite\":1,\"source_opportunity_id\":null}]},\n    \"logement\": {\"label\": \"{$labels[1]}\", \"items\": []},\n    \"finance\":  {\"label\": \"{$labels[2]}\", \"items\": []},\n    \"sante\":    {\"label\": \"{$labels[3]}\", \"items\": []}\n  },\n  \"alerte_vulnerabilite\": null,\n  \"prochaine_etape\": \"<action dans les 72h>\",\n  \"conseils_agent\": \"<recommandation pour l'agent OIM>\"\n}\nMax 3 items/axe. Sois spécifique et actionnable. {$lang_instruction}";
    }

    // ── System prompt spécial analphabète ───────────────────────────
    public static function system_analphabete(string $lang = 'fr'): string {
        return self::system($lang) . "\n\n## CONTEXTE MIGRANT ANALPHABÈTE OU SANS QUALIFICATION\nCe migrant n'a pas ou peu de formation formelle. Son plan DOIT impérativement :\n- PHASE 1 (0-3 mois) : intégration IMMÉDIATE dans l'économie informelle locale (secteurs sans prérequis de diplôme, micro-crédit accessible sans garantie, réseaux communautaires et tontines).\n- PHASE 2 (3-12 mois) : alphabétisation fonctionnelle ET formation pratique courte (structures gratuites locales fournies, suivi OIM mensuel).\n- ZÉRO jargon administratif — langage simple, concret, actionnable.\n- Priorité aux ressources disponibles dans la ville de retour du migrant.\n- Valoriser toujours les savoir-faire informels décrits par le migrant.\nRègle absolue: réponds UNIQUEMENT en JSON valide. Langue de réponse: {$lang}.";
    }

    // ── Prompt plan analphabète ← injecte réalités économiques pays ─
    public static function plan_analphabete(array $profile, array $matched_opps, array $country_data, string $lang = 'fr'): string {
        $profile_text = NLPEngine::profile_to_text($profile, $lang);
        $opps_text    = self::format_opps($matched_opps, $lang);

        $secteurs = json_decode($country_data['secteurs_porteurs'] ?? '[]', true) ?? [];
        $micro    = json_decode($country_data['micro_finance']     ?? '[]', true) ?? [];
        $alpha    = json_decode($country_data['structures_alpha']  ?? '[]', true) ?? [];
        $infopps  = json_decode($country_data['opportunites_informelles'] ?? '[]', true) ?? [];
        $oim      = json_decode($country_data['ressources_oim']   ?? '{}', true) ?? [];

        $secteurs_txt = implode(', ', array_column(array_filter($secteurs, fn($s) => $s['sans_qualification'] ?? false), 'secteur')) ?: 'Activités informelles variées';
        $micro_txt    = implode(' | ', array_map(fn($m) => "{$m['nom']} ({$m['type']}, plafond ".number_format($m['plafond'])." FCFA)", $micro)) ?: 'Pas de données';
        $alpha_txt    = implode(' | ', array_map(fn($a) => "{$a['nom']} — {$a['zones']}", $alpha)) ?: 'OIM locale';
        $infopps_txt  = implode(' | ', array_map(fn($o) => $o['titre'], $infopps)) ?: 'Commerce informel, agriculture';
        $oim_txt      = !empty($oim) ? "{$oim['bureau']} — {$oim['tel']}" : 'OIM locale';

        $labels = [
            'fr' => ['Emploi & Formation','Logement','Soutien financier','Santé'],
            'en' => ['Employment & Training','Housing','Financial Support','Health'],
            'ar' => ['التوظيف والتدريب','السكن','الدعم المالي','الصحة'],
            'wo' => ['Liggéey & Jàng','Ker','Xaalis','Wér ak jam'],
        ][$lang] ?? ['Emploi & Formation','Logement','Soutien financier','Santé'];

        return "## PROFIL MIGRANT (anonymisé)\n{$profile_text}\n\n## RÉALITÉS ÉCONOMIQUES DU PAYS DE RETOUR ({$profile['pays_origine']})\n- Taux d'économie informelle : {$country_data['taux_informalite']}%\n- Secteurs porteurs SANS qualification requise : {$secteurs_txt}\n- Micro-finance accessible : {$micro_txt}\n- Activités informelles recommandées : {$infopps_txt}\n- Structures d'alphabétisation gratuites : {$alpha_txt}\n- Bureau OIM local : {$oim_txt}\n\n## OPPORTUNITÉS FORMELLES DISPONIBLES\n{$opps_text}\n\n## INSTRUCTIONS — PLAN EN 2 PHASES (migrant analphabète)\nGénère un plan structuré en 2 phases. Réponds UNIQUEMENT avec ce JSON:\n{\n  \"score\": <30-70>,\n  \"resume\": \"<2-3 phrases simples en {$lang}, sans jargon>\",\n  \"axes\": {\n    \"emploi\":   {\"label\": \"{$labels[0]}\", \"items\": [\n      {\"titre\":\"PHASE 1 — Activité immédiate\",\"description\":\"\",\"organisme\":\"\",\"contact\":\"\",\"cout_estime\":\"0\",\"duree\":\"0-3 mois\",\"priorite\":1,\"source_opportunity_id\":null},\n      {\"titre\":\"PHASE 2 — Formation pratique\",\"description\":\"\",\"organisme\":\"\",\"contact\":\"\",\"cout_estime\":\"\",\"duree\":\"3-12 mois\",\"priorite\":2,\"source_opportunity_id\":null}\n    ]},\n    \"logement\": {\"label\": \"{$labels[1]}\", \"items\": []},\n    \"finance\":  {\"label\": \"{$labels[2]}\", \"items\": [{\"titre\":\"Micro-crédit sans garantie\",\"description\":\"Mentionner organisme local\",\"organisme\":\"\",\"contact\":\"\",\"cout_estime\":\"0\",\"duree\":\"12-24 mois\",\"priorite\":1,\"source_opportunity_id\":null}]},\n    \"sante\":    {\"label\": \"{$labels[3]}\", \"items\": []}\n  },\n  \"alerte_vulnerabilite\": null,\n  \"prochaine_etape\": \"<action concrète dans les 72h, formulée simplement>\",\n  \"conseils_agent\": \"<recommandation pour l'agent OIM, mentionner alphabétisation>\"\n}\nLangue: {$lang}. Score entre 30 et 70 (réaliste pour profil sans qualification).";
    }

    // ── Prompt chat ← transmet historique + contexte plan + profil ───
    public static function chat_system(array $plan, array $profile, string $lang = 'fr'): string {
        $plan_txt = isset($plan['axes'])
            ? implode(' | ', array_map(fn($k,$a) => strtoupper($k).': '.implode(', ', array_column($a['items']??[], 'titre')), array_keys($plan['axes']), $plan['axes']))
            : ($plan['resume_global'] ?? '');
        return "Tu es l'assistant HorizonAI pour un migrant du programme AVRR.\nLangue: {$lang}. Sois chaleureux et précis. N'invente jamais.\n\nPLAN DU MIGRANT:\n{$plan_txt}\n\nPROFIL:\n".NLPEngine::profile_to_text($profile, $lang);
    }

    // ── Prompt résumé PDF ────────────────────────────────────────────
   public static function pdf_summary(array $plan, string $lang): string {
    $axes_txt = '';
    foreach ($plan['axes'] ?? [] as $k => $a) {
        $axes_txt .= strtoupper($k).': '.implode(', ', array_column($a['items'] ?? [], 'titre'))."\n";
    }
    $score = $plan['score'] ?? $plan['score_ia'] ?? 0;
    return "Rédige un résumé officiel OIM (max 120 mots, langue {$lang}, ton neutre, pas de bullet points):\n{$axes_txt}Score: {$score}/100\n".($plan['resume'] ?? $plan['resume_global'] ?? '');
}

    // ── Format opportunités pour le prompt ──────────────────────────
    private static function format_opps(array $by_type, string $lang): string {
        if (empty($by_type)) return ($lang === 'fr' ? 'Aucune opportunité spécifique. Générer recommandations OIM générales.' : 'No specific opportunities. Generate general OIM recommendations.');
        $lines = [];
        foreach ($by_type as $type => $opps) {
            $lines[] = "\n### {$type}";
            foreach ($opps as $i => $o) {
                $cout  = $o['cout_estime'] ? "{$o['cout_estime']} {$o['devise']}" : 'Gratuit';
                $duree = $o['duree_semaines'] ? "{$o['duree_semaines']}sem" : '-';
                $tags = '';
                if (!empty($o['tags'])) {
                    $tags_str = $o['tags'];
                    if (str_starts_with($tags_str, '{') && str_ends_with($tags_str, '}')) {
                        $t = array_map('trim', explode(',', trim($tags_str, '{}')));
                        $tags = implode(', ', $t);
                    } elseif (is_array($tags_str)) {
                        $tags = implode(', ', $tags_str);
                    }
                }
                $site = $o['contact_web'] ?? '-';
                $lines[] = sprintf("%d. [score:%s%%] %s (%s) | %s | %s | %s | site:%s | tél:%s | tags:%s",
                    $i+1, $o['_score']??0, $o['titre'], $type,
                    $o['description'], $o['organisme']??'-',
                    $cout, $site, $o['contact_tel']??'-', $tags ?: '-');
            }
        }
        return implode("\n", $lines);
    }
}
