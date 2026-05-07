<?php
// ================================================================
// HorizonAI — Matching Engine
// Score chaque opportunité (DB) contre le profil migrant
// Appelle NLPEngine pour les scores éducation/expérience
// Résultat injecté dans PromptBuilder
// ================================================================
declare(strict_types=1);

class MatchingEngine {

    // Poids des 5 critères (total = 100)
    private const W = [
        'skill'   => 40,
        'edu'     => 20,
        'avail'   => 15,
        'vuln'    => 15,
        'cost'    => 10,
    ];

    /**
     * Point d'entrée principal — appelé par IAOrchestrator
     * Charge les opportunités depuis la BDD et les score contre le profil
     */
    public static function match_from_db(array $profile, int $max_per_axe = 3): array {
        // Récupérer les opportunités du pays depuis SQLite
        $opps = DB::rows("
            SELECT id, pays, ville, type, titre, description, organisme,
                   contact_tel, cout_estime, devise, duree_semaines,
                   tags, is_active, verifie_le
            FROM opportunities
            WHERE pays = ? AND is_active = TRUE
            ORDER BY type
            LIMIT 40
        ", [$profile['pays_origine']]);

        // Fallback: opportunités génériques si pays pas couvert
        if (empty($opps)) {
            $opps = DB::rows("SELECT * FROM opportunities WHERE is_active = TRUE LIMIT 20");
        }

        return self::match_all($opps, $profile, $max_per_axe);
    }

    /**
     * Score toutes les opportunités et groupe par type
     */
    public static function match_all(array $opps, array $profile, int $max = 3): array {
        $scored = array_map(fn($o) => array_merge($o, [
            '_score'  => self::score($o, $profile),
            '_detail' => self::explain($o, $profile),
        ]), $opps);

        // Regrouper par type → trier → top N
        $by_type = [];
        foreach ($scored as $o) $by_type[$o['type']][] = $o;
        foreach ($by_type as $type => &$list) {
            usort($list, fn($a, $b) => $b['_score'] <=> $a['_score']);
            $list = array_slice($list, 0, $max);
        }
        return $by_type;
    }

    /**
     * Score global du plan (0-99) ← stocké dans plans.score_ia
     */
    public static function plan_score(array $matched, array $profile): int {
        $edu  = NLPEngine::education_score($profile['niveau_etudes']  ?? '');
        $exp  = NLPEngine::experience_score($profile['annees_experience'] ?? '');
        $comp = count($profile['competences'] ?? []);
        $vuln = !empty($profile['vulnerabilites']) && !in_array('AUCUNE', (array)$profile['vulnerabilites']);

        $base  = min(100, ($edu * 5) + ($exp * 5) + ($comp * 5) + 30);
        $bonus = 0;
        foreach ($matched as $opps) {
            $best = $opps[0]['_score'] ?? 0;
            $bonus += $best * 0.08;
        }
        $malus = ($vuln && empty($matched['SANTE'])) ? -10 : 0;
        $final = (int)min(99, max(40, $base + $bonus + $malus));
        if ($comp >= 3 && $edu >= 5) $final = min(99, $final + 5);
        if ($comp === 0) $final = max(40, $final - 10);
        return $final;
    }

    // ── Critères ─────────────────────────────────────────────────

    private static function score(array $opp, array $profile): float {
        return round(
            self::skill_score($opp, $profile)   * self::W['skill'] / 100 +
            self::edu_score($opp, $profile)      * self::W['edu']   / 100 +
            self::avail_score($opp)              * self::W['avail'] / 100 +
            self::vuln_score($opp, $profile)     * self::W['vuln']  / 100 +
            self::cost_score($opp)               * self::W['cost']  / 100,
        1);
    }

    private static function skill_score(array $opp, array $profile): float {
        $skills = array_map('mb_strtolower', $profile['competences'] ?? []);
        if (empty($skills)) return 30.0;
        $tags_str = $opp['tags'] ?? '';
        $tags = [];
        if ($tags_str) {
            if (str_starts_with($tags_str, '{') && str_ends_with($tags_str, '}')) {
                $tags = array_map('trim', explode(',', trim($tags_str, '{}')));
            } elseif (is_array($tags_str)) {
                $tags = $tags_str;
            }
        }
        $text = mb_strtolower($opp['titre'].' '.$opp['description'].' '.implode(' ', $tags));
        $matches = array_sum(array_map(fn($s) => str_contains($text, $s) ? 1 : 0, $skills));
        return min(100.0, ($matches / count($skills)) * 80 + 20);
    }

    private static function edu_score(array $opp, array $profile): float {
        $edu = NLPEngine::education_score($profile['niveau_etudes'] ?? '');
        return match($opp['type']) {
            'FORMATION'    => 80.0,
            'MICRO_CREDIT' => 70.0,
            'LOGEMENT','SANTE' => 90.0,
            'EMPLOI'       => min(100.0, $edu * 12.0),
            default        => 70.0,
        };
    }

    private static function avail_score(array $opp): float {
        if (!$opp['is_active']) return 0.0;
        $score = 60.0;
        if (!empty($opp['verifie_le'])) {
            $days = (time() - strtotime($opp['verifie_le'])) / 86400;
            $score += $days <= 30 ? 40 : ($days <= 90 ? 25 : 10);
        } else { $score += 20; }
        return min(100.0, $score);
    }

    private static function vuln_score(array $opp, array $profile): float {
        $vulns = (array)($profile['vulnerabilites'] ?? []);
        $hasVuln = !empty($vulns) && !in_array('AUCUNE', $vulns);
        if (!$hasVuln) return 70.0;
        if ($opp['type'] === 'SANTE') return 100.0;
        if ($opp['type'] === 'LOGEMENT' && array_intersect($vulns, ['FEMME_ENCEINTE','MINEUR_NON_ACCOMPAGNE','SANTE_URGENTE'])) return 95.0;
        return 60.0;
    }

    private static function cost_score(array $opp): float {
        $c = (float)($opp['cout_estime'] ?? 0);
        if ($c <= 0)       return 100.0;
        if ($c <= 10000)   return 90.0;
        if ($c <= 50000)   return 70.0;
        if ($c <= 100000)  return 50.0;
        if ($c <= 300000)  return 30.0;
        return 15.0;
    }

    private static function explain(array $opp, array $profile): array {
        return [
            'skill'  => round(self::skill_score($opp, $profile), 1),
            'edu'    => round(self::edu_score($opp, $profile), 1),
            'avail'  => round(self::avail_score($opp), 1),
            'vuln'   => round(self::vuln_score($opp, $profile), 1),
            'cost'   => round(self::cost_score($opp), 1),
        ];
    }
}
