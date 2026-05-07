<?php
// ================================================================
// HorizonAI — NLP Engine (Multilingue FR/EN/AR/WO)
// Utilisé par IAOrchestrator avant chaque appel Claude
// ================================================================
declare(strict_types=1);

class NLPEngine {

    // Dictionnaire compétences normalisées ← utilisé par MatchingEngine
    private const SKILL_DICT = [
        'Agriculture' => ['fr'=>['agriculture','élevage','maraîchage','cultures','paysan','fermier'],'en'=>['agriculture','farming','crop','livestock','farmer'],'ar'=>['زراعة','فلاحة','مزارع'],'wo'=>['mbay']],
        'Commerce'    => ['fr'=>['commerce','vente','négoce','marchand','commerçant'],'en'=>['commerce','trade','sales','merchant','business'],'ar'=>['تجارة','بيع','تاجر'],'wo'=>['jënd-jënd']],
        'BTP'         => ['fr'=>['btp','construction','maçon','bâtiment','charpentier','menuisier','plombier','électricien'],'en'=>['construction','masonry','builder','carpenter','plumber','electrician'],'ar'=>['بناء','إنشاء','نجار'],'wo'=>[]],
        'Informatique'=> ['fr'=>['informatique','développement','code','programmation','web','numérique'],'en'=>['it','computing','programming','software','web','digital'],'ar'=>['برمجة','حاسوب'],'wo'=>[]],
        'Couture'     => ['fr'=>['couture','textile','mode','confection','broderie','tailleur'],'en'=>['sewing','textile','fashion','tailoring'],'ar'=>['خياطة','نسيج'],'wo'=>['cosaan']],
        'Transport'   => ['fr'=>['transport','chauffeur','conducteur','logistique','camion','taxi'],'en'=>['transport','driver','logistics','truck'],'ar'=>['نقل','سائق'],'wo'=>[]],
        'Santé'       => ['fr'=>['santé','infirmier','infirmière','médecin','soins','pharmacien'],'en'=>['health','nurse','doctor','medical','caregiver'],'ar'=>['صحة','ممرض','طبيب'],'wo'=>[]],
        'Enseignement'=> ['fr'=>['enseignement','professeur','enseignant','éducation','formation'],'en'=>['teaching','teacher','education','training'],'ar'=>['تعليم','مدرس'],'wo'=>['jàng']],
        'Restauration'=> ['fr'=>['restauration','cuisine','cuisinier','chef','traiteur','hôtellerie'],'en'=>['catering','cooking','chef','hospitality'],'ar'=>['طهي','مطعم','طباخ'],'wo'=>[]],
        'Artisanat'   => ['fr'=>['artisanat','poterie','bijouterie','vannerie','maroquinerie','artisan'],'en'=>['crafts','pottery','jewelry','artisan'],'ar'=>['حرف','فخار'],'wo'=>['cosaan']],
    ];

    // Toutes les langues supportées (code => label natif)
    public const SUPPORTED_LANGS = [
        'fr'  => 'Français',
        'en'  => 'English',
        'ar'  => 'العربية',
        'wo'  => 'Wolof',
        'bm'  => 'Bambara / Dioula',
        'ha'  => 'Hausa',
        'ff'  => 'Fulfuldé / Peul',
        'tzm' => 'Tamazight / Kabyle',
    ];

    // Langues avec TTS natif dans les navigateurs (Web Speech API)
    public const LANGS_WITH_NATIVE_TTS = ['fr', 'en', 'ar'];

    // Textes traduits ← utilisés dans les prompts et réponses
    private const LABELS = [
        'fr'  => ['greeting'=>'Bonjour ! Voici votre plan de retour personnalisé.','score_good'=>'Excellente faisabilité ({score}/100).','axe_emploi'=>'Emploi & Formation','axe_logement'=>'Logement','axe_finance'=>'Soutien financier','axe_sante'=>'Santé'],
        'en'  => ['greeting'=>'Hello! Here is your personalized return plan.','score_good'=>'Excellent feasibility ({score}/100).','axe_emploi'=>'Employment & Training','axe_logement'=>'Housing','axe_finance'=>'Financial Support','axe_sante'=>'Health'],
        'ar'  => ['greeting'=>'مرحباً! إليك خطة العودة.','score_good'=>'جدوى ممتازة ({score}/100).','axe_emploi'=>'التوظيف والتدريب','axe_logement'=>'السكن','axe_finance'=>'الدعم المالي','axe_sante'=>'الصحة'],
        'wo'  => ['greeting'=>'Mangi dem jëf! Dëkk bi ci kanam.','score_good'=>'Mbir bi dafa baax ({score}/100).','axe_emploi'=>'Liggéey & Jàng','axe_logement'=>'Ker','axe_finance'=>'Xaalis','axe_sante'=>'Wér ak jam'],
        'bm'  => ['greeting'=>'I ni ce! Nin ye i ka kɛnɛya lajɛlen ye.','score_good'=>'Cogoya ka ɲɛ ({score}/100).','axe_emploi'=>'Baara & Kalan','axe_logement'=>'So','axe_finance'=>'Wari Dɛmɛ','axe_sante'=>'Kɛnɛya'],
        'ha'  => ['greeting'=>'Sannu! Ga tsarin dawowar ku na musamman.','score_good'=>'Yiwuwar aiwatarwa yana da kyau ({score}/100).','axe_emploi'=>'Aiki & Horarwa','axe_logement'=>'Gida','axe_finance'=>'Tallafin Kudi','axe_sante'=>'Lafiya'],
        'ff'  => ['greeting'=>'Jam waali! Ndee woni piyanaa maa.','score_good'=>'Piyanaa ngaa weli ({score}/100).','axe_emploi'=>'Golle & Janngirde','axe_logement'=>'Galle','axe_finance'=>'Ndukkal Kalis','axe_sante'=>'Cellal'],
        'tzm' => ['greeting'=>'Azul! Aya d-asenked-nnwen i twuri.','score_good'=>'Afud yelha ({score}/100).','axe_emploi'=>'Amahil & Ulmad','axe_logement'=>'Axxam','axe_finance'=>'Tallalt n Iqriḍen','axe_sante'=>'Taselkimt'],
    ];

    // Détecter langue depuis texte libre (8 langues)
    public static function detect_language(string $text): string {
        // Arabe : détection par plage Unicode
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text)) return 'ar';
        // Tifinagh : Tamazight
        if (preg_match('/[\x{2D30}-\x{2D7F}]/u', $text)) return 'tzm';

        $t = mb_strtolower($text);
        $scores = [
            'fr'  => count(array_filter(['je','suis','les','des','est','pas','avec','pour','dans','sur','moi','mon','votre'], fn($w) => str_contains($t, " {$w} "))),
            'en'  => count(array_filter(['i','the','is','are','you','this','with','from','for','not','my','have'], fn($w) => str_contains($t, " {$w} "))),
            'wo'  => count(array_filter(['dafa','suma','bi','la','ci','bëgg','dem','jëf','xam','nekk','maa','nga'], fn($w) => str_contains($t, $w))),
            'bm'  => count(array_filter(['bɛ','ka','ye','tun','kɔ','fɛ','mun','ani','dɔ','minɛ','kɛ','sɔrɔ'], fn($w) => str_contains($t, $w))),
            'ha'  => count(array_filter(['ne','da','ya','zan','kana','wace','yaya','inda','amma','don','kuma'], fn($w) => str_contains($t, " {$w} "))),
            'ff'  => count(array_filter(['mi','ko','hol','ɗo','nde','waali','jam','ngol','maa','leydi','galle'], fn($w) => str_contains($t, $w))),
            'tzm' => count(array_filter(['azul','acu','nkk','tebɣiḍ','nesɛiḍ','tamurt','axxam','ad','ur','d '], fn($w) => str_contains($t, $w))),
        ];
        arsort($scores);
        $best = array_key_first($scores);
        return ($scores[$best] > 0) ? $best : 'fr';
    }

    // Normaliser compétences ← appelé par IAOrchestrator avant matching
    public static function normalize_skills(mixed $input, string $lang = 'fr'): array {
        $texts = is_array($input) ? $input : array_map('trim', preg_split('/[,;\/\n]+/', (string)$input));
        $result = [];
        foreach ($texts as $text) {
            $tl = mb_strtolower(trim((string)$text));
            if (empty($tl)) continue;
            $matched = false;
            foreach (self::SKILL_DICT as $canonical => $langs) {
                $synonyms = array_merge(...array_values($langs));
                foreach ($synonyms as $syn) {
                    if (str_contains($tl, mb_strtolower($syn)) || (strlen($syn) >= 4 && similar_text($tl, mb_strtolower($syn)) / max(strlen($tl),1) > 0.75)) {
                        $result[] = $canonical; $matched = true; break 2;
                    }
                }
            }
            if (!$matched && mb_strlen($text) >= 3) $result[] = ucfirst(mb_strtolower($text));
        }
        return array_values(array_unique($result));
    }

    // Score éducation (1-9) ← utilisé par MatchingEngine::education_score()
    public static function education_score(string $level): int {
        $map = ['Sans diplôme'=>1,'Primaire'=>2,'Collège'=>3,'Bac'=>5,'Bac +2'=>6,'Bac +3'=>7,'Bac +5'=>8,'Doctorat'=>9,'No diploma'=>1,'Primary'=>2,'Bachelor'=>7,'Master'=>8,'PhD'=>9];
        foreach ($map as $pattern => $score) {
            if (stripos($level, $pattern) !== false) return $score;
        }
        return 3;
    }

    // Score expérience (1-5) ← utilisé par MatchingEngine::plan_score()
    public static function experience_score(string $exp): int {
        preg_match('/(\d+)/', $exp, $m);
        $y = (int)($m[1] ?? 0);
        if ($y >= 10) return 5; if ($y >= 5) return 4; if ($y >= 2) return 3; if ($y >= 1) return 2; return 1;
    }

    // Traduire un label ← utilisé par PromptBuilder et ClaudeClient
    public static function t(string $key, string $lang, array $vars = []): string {
        $str = (self::LABELS[$lang] ?? self::LABELS['fr'])[$key] ?? $key;
        foreach ($vars as $k => $v) $str = str_replace("{{$k}}", (string)$v, $str);
        return $str;
    }

    // Profil → texte lisible pour le prompt IA ← utilisé par PromptBuilder
    public static function profile_to_text(array $p, string $lang = 'fr'): string {
        $comps = implode(', ', is_array($p['competences']) ? $p['competences'] : []) ?: 'non précisées';
        $vulns = implode(', ', is_array($p['vulnerabilites']) ? array_filter($p['vulnerabilites']) : []) ?: ($lang === 'ar' ? 'لا شيء' : 'Aucune');
        $alpha = $p['alphabetisation'] ?? 'OUI';
        $alpha_label = match($alpha) { 'NON' => 'Analphabète (ne sait ni lire ni écrire)', 'PARTIEL' => 'Alphabétisation partielle', default => 'Alphabétisé' };
        $infop = !empty($p['competences_informelles']) ? "\nSavoir-faire décrit : {$p['competences_informelles']}" : '';
        return match($lang) {
            'ar' => "البلد: {$p['pays_origine']} — {$p['ville_retour']}\nالعمر: {$p['tranche_age']}\nالتعليم: {$p['niveau_etudes']} ({$alpha_label})\nالمهارات: {$comps}{$infop}\nالخبرة: {$p['annees_experience']}\nالهشاشة: {$vulns}",
            default => "Retour: {$p['pays_origine']} — {$p['ville_retour']}\nÂge: {$p['tranche_age']}\nFormation: {$p['niveau_etudes']} ({$alpha_label}){$infop}\nCompétences: {$comps}\nExpérience: {$p['annees_experience']}\nVulnérabilités: {$vulns}",
        };
    }

    // Détecter si un profil est analphabète
    public static function is_analphabete(string $niveau_etudes, string $alphabetisation): bool {
        if (in_array($alphabetisation, ['NON', 'PARTIEL'], true)) return true;
        $patterns = ['sans diplôme','aucun','no diploma','jamais','analphabète','illettré'];
        $niv = mb_strtolower($niveau_etudes);
        foreach ($patterns as $p) {
            if (str_contains($niv, $p)) return true;
        }
        return false;
    }

    // Extraire compétences depuis description libre ("je sais élever des poulets")
    public static function normalize_competences_informelles(string $text): array {
        if (empty(trim($text))) return [];
        $t = mb_strtolower($text);
        $found = [];
        foreach (self::SKILL_DICT as $canonical => $langs) {
            foreach (array_merge(...array_values($langs)) as $syn) {
                if (mb_strlen($syn) >= 3 && str_contains($t, mb_strtolower($syn))) {
                    $found[] = $canonical;
                    break;
                }
            }
        }
        // Extraire mots-clés informels non couverts par SKILL_DICT
        $keywords = ['jardinage','maraîchage','pêche','élevage','couture','broderie',
                     'menuiserie','plomberie','électricité','vente','commerce','cuisine',
                     'boulangerie','mécanique','bâtiment','maçonnerie','peinture','soudure',
                     'coiffure','agriculture','récolte','transport','conduite'];
        foreach ($keywords as $kw) {
            if (str_contains($t, $kw) && !in_array($kw, $found)) $found[] = ucfirst($kw);
        }
        return array_values(array_unique($found)) ?: ['Activités informelles'];
    }
}
