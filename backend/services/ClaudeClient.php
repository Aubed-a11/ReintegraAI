<?php
declare(strict_types=1);

class ClaudeClient {
    private const PRICE_IN  = 3.00;   // USD / 1M tokens input
    private const PRICE_OUT = 15.00;  // USD / 1M tokens output
    private const MAX_RETRY = 3;
    private const RETRY_DELAYS = [1, 2, 4];

    // ── Génération plan IA ← point d'entrée depuis IAOrchestrator ──
    public static function generate_plan(array $profile, array $matched, string $lang, array $ctx = [], ?string $custom_system = null, ?string $custom_message = null): array {
        // Vérifier cache SQLite (même profil → même plan pendant 6h)
        $cache_key = self::cache_key($profile, $lang) . ($custom_system ? '_alpha' : '');
        if ($cached = self::get_cache($cache_key)) {
            return array_merge($cached, ['_from_cache' => true]);
        }

        $t0 = hrtime(true);

        $system_prompt = $custom_system ?? PromptBuilder::system($lang);
        $user_message  = $custom_message ?? PromptBuilder::plan($profile, $matched, $lang);

        // Mode démo personnalisé
        if (!CLAUDE_API_KEY) {
            $res = self::demo_plan_response($profile, $matched, $lang);
        } else {
            $res = self::call(
                $system_prompt,
                [['role'=>'user','content'=> $user_message]],
                CLAUDE_MAX_TOKENS
            );
        }
        
        $latence = (int)((hrtime(true) - $t0) / 1e6);

        if (!$res['success']) return ['success'=>false,'error'=>$res['error'],'latence_ms'=>$latence];

        $plan = self::parse_plan($res['content']);
        if (!$plan) {
            $plan = self::parse_plan(self::demo_plan_json());
            $plan['_fallback'] = true;
        }

        // Personnaliser le plan avec les données du profil
        $plan = self::personalize_plan($plan, $profile, $matched, $lang);

        $cost = self::cost($res['tokens_in'] ?? 0, $res['tokens_out'] ?? 0);

        // Stocker en cache DB
        self::set_cache($cache_key, ['success'=>true,'plan'=>$plan], 21600);

        return [
            'success'       => true,
            'plan'          => $plan,
            'tokens_in'     => $res['tokens_in']  ?? 0,
            'tokens_out'    => $res['tokens_out'] ?? 0,
            'cout_usd'      => $cost,
            'latence_ms'    => $latence,
            'model'         => CLAUDE_MODEL,
            'prompt_version'=> PromptBuilder::VERSION,
            '_from_cache'   => false,
        ];
    }

    // ── Chat assistant ← appelé par IAOrchestrator::chat() ─────────
    public static function chat(string $message, array $plan, array $profile, string $lang, array $history = []): array {
        // Mode démo personnalisé
        if (!CLAUDE_API_KEY) {
            return self::demo_chat_response($message, $plan, $profile, $lang, $history);
        }

        $messages = array_map(fn($h) => ['role'=>$h['role'],'content'=>$h['content']], $history);
        $messages[] = ['role'=>'user','content'=>$message];

        $t0 = hrtime(true);
        $res = self::call(PromptBuilder::chat_system($plan, $profile, $lang), $messages, 600);
        $latence = (int)((hrtime(true) - $t0) / 1e6);

        if (!$res['success']) return ['success'=>false,'error'=>$res['error']];
        return [
            'success'    => true,
            'message'    => trim($res['content']),
            'latence_ms' => $latence,
            'cout_usd'   => self::cost($res['tokens_in']??0, $res['tokens_out']??0),
        ];
    }

    // ── Résumé PDF ──────────────────────────────────────────────────
    public static function pdf_summary(array $plan, string $lang): array {
        $res = self::call('Tu es un rédacteur OIM officiel.', [
            ['role'=>'user','content'=> PromptBuilder::pdf_summary($plan, $lang)]
        ], 300);
        if (!$res['success']) return ['success'=>false,'error'=>$res['error']];
        return ['success'=>true,'summary'=>trim($res['content'])];
    }

    // ── Appel HTTP vers Anthropic avec retry ────────────────────────
    private static function call(string $system, array $messages, int $max_tokens): array {
        // Mode démo si pas de clé API
        if (!CLAUDE_API_KEY) return self::demo_response($messages[count($messages)-1]['content'] ?? '');

        $payload = json_encode([
            'model'      => CLAUDE_MODEL,
            'max_tokens' => $max_tokens,
            'system'     => $system,
            'messages'   => $messages,
        ], JSON_UNESCAPED_UNICODE);

        $last_err = '';
        for ($i = 0; $i < self::MAX_RETRY; $i++) {
            if ($i > 0) sleep(self::RETRY_DELAYS[$i - 1]);

            $ch = curl_init(CLAUDE_API_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'x-api-key: '       . CLAUDE_API_KEY,
                    'anthropic-version: 2023-06-01',
                    'X-Request-ID: '    . uniqid('riai-', true),
                ],
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($err) { $last_err = "cURL: {$err}"; continue; }
            $data = json_decode($body, true);

            if ($code === 200) {
                $content = null;
                if (isset($data['content'][0]['text'])) {
                    $content = $data['content'][0]['text'];
                } elseif (isset($data['completion'])) {
                    $content = is_string($data['completion']) ? $data['completion'] : null;
                } elseif (isset($data['output'][0]['content'][0]['text'])) {
                    $content = $data['output'][0]['content'][0]['text'];
                }
                if ($content !== null) {
                    return [
                        'success'    => true,
                        'content'    => trim($content),
                        'tokens_in'  => $data['usage']['input_tokens']  ?? 0,
                        'tokens_out' => $data['usage']['output_tokens'] ?? 0,
                    ];
                }
            }
            if ($code === 429) { sleep(10); $last_err = 'Rate limit 429'; continue; }
            if ($code >= 500)  { $last_err = "API {$code}"; continue; }
            $last_err = $data['error']['message'] ?? "HTTP {$code}"; break;
        }
        return ['success'=>false,'error'=>$last_err];
    }

    
    private static function parse_plan(string $content): ?array {
        if (preg_match('/\{[\s\S]*\}/m', $content, $m)) $content = $m[0];
        $d = json_decode($content, true);
        if (!$d || !isset($d['axes'])) return null;
        $d['score'] = max(0, min(99, (int)($d['score'] ?? 70)));
        foreach (['emploi','logement','finance','sante'] as $axe) {
            if (!isset($d['axes'][$axe])) $d['axes'][$axe] = ['label'=>ucfirst($axe),'items'=>[]];
            foreach ($d['axes'][$axe]['items'] as &$item) {
                $item['titre']       ??= '—';
                $item['description'] ??= '';
                $item['organisme']   ??= 'OIM';
                $item['priorite']    ??= 2;
                $item['contact']     ??= null;
                $item['cout_estime'] ??= 'Gratuit';
                $item['duree']       ??= '—';
                $item['source_opportunity_id'] ??= null;
            }
        }
        $d['resume']               ??= '';
        $d['alerte_vulnerabilite'] ??= null;
        $d['prochaine_etape']      ??= '';
        $d['conseils_agent']       ??= '';
        return $d;
    }

    // ── Cache SQLite (table plan_cache) ─────────────────────────
    private static function cache_key(array $p, string $lang): string {
        return 'plan_' . hash('sha256', implode('|', [$p['pays_origine']??'', $p['tranche_age']??'', $p['niveau_etudes']??'', implode(',', $p['competences']??[]), $lang]));
    }
    private static function get_cache(string $key): ?array {
        try { $r = DB::row("SELECT value FROM plan_cache WHERE key=? AND expires_at>datetime('now')", [$key]); return $r ? json_decode($r['value'],true) : null; } catch (\Throwable) { return null; }
    }
    private static function set_cache(string $key, array $value, int $ttl): void {
        try {
            $expires = date('Y-m-d H:i:s', time() + $ttl);
            DB::query("INSERT OR REPLACE INTO plan_cache(key,value,expires_at,created_at) VALUES(?,?,?,datetime('now'))", [$key, json_encode($value), $expires]);
        } catch (\Throwable) {}
    }

    // ── Coût réel ───────────────────────────────────────────────────
    private static function cost(int $in, int $out): float {
        return round(($in * self::PRICE_IN + $out * self::PRICE_OUT) / 1_000_000, 8);
    }

    // ── Mode démo sans clé API ──────────────────────────────────────
    private static function demo_response(string $msg): array {
        $is_chat = strlen($msg) < 400;
        if ($is_chat) {
            return ['success'=>true,'content'=>"Je comprends votre question sur \"{$msg}\". En tant qu'assistant HorizonAI, je peux vous aider avec votre plan de réintégration personnalisé. Votre agent OIM est disponible pour tout détail spécifique sur les démarches à suivre. Y a-t-il autre chose que vous souhaitez savoir ?", 'tokens_in'=>120,'tokens_out'=>60];
        }
        return ['success'=>true,'content'=>self::demo_plan_json(),'tokens_in'=>850,'tokens_out'=>620];
    }

    private static function demo_chat_response(string $message, array $plan, array $profile, string $lang, array $history): array {
        $pays = $profile['pays_origine'] ?? 'votre pays';
        $ville = $profile['ville_retour'] ?? 'votre ville';
        $competences = is_array($profile['competences'] ?? []) ? implode(', ', $profile['competences']) : 'vos compétences';
        
        $plan_info = '';
        if (!empty($plan['axes'])) {
            $actions = [];
            foreach ($plan['axes'] as $axe => $data) {
                if (!empty($data['items'])) {
                    $actions[] = strtolower($axe) . ' (' . count($data['items']) . ' actions)';
                }
            }
            $plan_info = 'Votre plan comprend des actions en ' . implode(', ', $actions) . '. ';
        }

        $responses = [
            "Bonjour ! Je suis votre assistant HorizonAI. {$plan_info}Pour votre retour au {$pays} ({$ville}), nous avons identifié des opportunités dans {$competences}. Votre agent OIM vous contactera bientôt pour valider les détails.",
            "Je comprends votre question. {$plan_info}Le programme AVRR de l'OIM vous accompagne dans votre réintégration au {$pays}. Vos compétences en {$competences} sont un atout majeur pour votre projet.",
            "Votre plan de réintégration est personnalisé selon votre profil. {$plan_info}N'hésitez pas à contacter votre agent OIM pour toute précision sur les démarches au {$pays}.",
            "Les opportunités identifiées correspondent à votre expérience en {$competences}. {$plan_info}L'OIM vous soutient à chaque étape de votre retour au {$pays}.",
        ];

        $response = $responses[array_rand($responses)];
        
        return [
            'success' => true,
            'message' => $response,
            'latence_ms' => rand(500, 1500),
            'cout_usd' => 0,
        ];
    }

    private static function demo_plan_response(array $profile, array $matched, string $lang): array {
        $content = self::personalized_plan_json($profile, $matched, $lang);
        return [
            'success' => true,
            'content' => $content,
            'tokens_in' => 850,
            'tokens_out' => 620,
        ];
    }

    private static function personalized_plan_json(array $profile, array $matched, string $lang): string {
        $pays = $profile['pays_origine'] ?? 'Sénégal';
        $ville = $profile['ville_retour'] ?? 'Dakar';
        $competences = is_array($profile['competences'] ?? []) ? $profile['competences'] : ['Commerce'];
        $competence_principale = $competences[0] ?? 'Commerce';
        
        $emploi_items = [];
        $formation_items = [];
        $finance_items = [];
        $logement_items = [];
        $sante_items = [];

        // Générer des items basés sur les opportunités matchées
        if (!empty($matched['EMPLOI'])) {
            foreach (array_slice($matched['EMPLOI'], 0, 2) as $opp) {
                $emploi_items[] = [
                    'titre' => $opp['titre'],
                    'description' => $opp['description'],
                    'organisme' => $opp['organisme'],
                    'contact' => $opp['contact_tel'] ?? '+221 XX XXX XX XX',
                    'cout_estime' => $opp['cout_estime'] ? ($opp['cout_estime'] . ' ' . $opp['devise']) : 'Gratuit',
                    'duree' => $opp['duree_semaines'] ? ($opp['duree_semaines'] . ' semaines') : 'Variable',
                    'priorite' => 1,
                    'source_opportunity_id' => $opp['id'],
                ];
            }
        }

        if (!empty($matched['FORMATION'])) {
            foreach (array_slice($matched['FORMATION'], 0, 1) as $opp) {
                $formation_items[] = [
                    'titre' => $opp['titre'],
                    'description' => $opp['description'],
                    'organisme' => $opp['organisme'],
                    'contact' => $opp['contact_tel'] ?? '+221 XX XXX XX XX',
                    'cout_estime' => $opp['cout_estime'] ? ($opp['cout_estime'] . ' ' . $opp['devise']) : 'Gratuit',
                    'duree' => $opp['duree_semaines'] ? ($opp['duree_semaines'] . ' semaines') : 'Variable',
                    'priorite' => 1,
                    'source_opportunity_id' => $opp['id'],
                ];
            }
        }

        if (!empty($matched['MICRO_CREDIT'])) {
            foreach (array_slice($matched['MICRO_CREDIT'], 0, 1) as $opp) {
                $finance_items[] = [
                    'titre' => $opp['titre'],
                    'description' => $opp['description'],
                    'organisme' => $opp['organisme'],
                    'contact' => $opp['contact_tel'] ?? '+221 XX XXX XX XX',
                    'cout_estime' => $opp['cout_estime'] ? ($opp['cout_estime'] . ' ' . $opp['devise']) : 'Gratuit',
                    'duree' => $opp['duree_semaines'] ? ($opp['duree_semaines'] . ' semaines') : 'Variable',
                    'priorite' => 1,
                    'source_opportunity_id' => $opp['id'],
                ];
            }
        }

        if (!empty($matched['LOGEMENT'])) {
            foreach (array_slice($matched['LOGEMENT'], 0, 1) as $opp) {
                $logement_items[] = [
                    'titre' => $opp['titre'],
                    'description' => $opp['description'],
                    'organisme' => $opp['organisme'],
                    'contact' => $opp['contact_tel'] ?? '+221 XX XXX XX XX',
                    'cout_estime' => $opp['cout_estime'] ? ($opp['cout_estime'] . ' ' . $opp['devise']) : 'Gratuit',
                    'duree' => $opp['duree_semaines'] ? ($opp['duree_semaines'] . ' semaines') : 'Variable',
                    'priorite' => 1,
                    'source_opportunity_id' => $opp['id'],
                ];
            }
        }

        if (!empty($matched['SANTE'])) {
            foreach (array_slice($matched['SANTE'], 0, 1) as $opp) {
                $sante_items[] = [
                    'titre' => $opp['titre'],
                    'description' => $opp['description'],
                    'organisme' => $opp['organisme'],
                    'contact' => $opp['contact_tel'] ?? '+221 XX XXX XX XX',
                    'cout_estime' => $opp['cout_estime'] ? ($opp['cout_estime'] . ' ' . $opp['devise']) : 'Gratuit',
                    'duree' => $opp['duree_semaines'] ? ($opp['duree_semaines'] . ' semaines') : 'Variable',
                    'priorite' => 1,
                    'source_opportunity_id' => $opp['id'],
                ];
            }
        }

        // Items par défaut si pas d'opportunités matchées
        if (empty($emploi_items)) {
            $emploi_items[] = [
                'titre' => "Mise en relation ANPEM - {$competence_principale}",
                'description' => "3 offres d'emploi compatibles dans le secteur {$competence_principale}. Accompagnement CV et entretiens.",
                'organisme' => 'ANPEM Sénégal',
                'contact' => 'anpem.sn',
                'cout_estime' => 'Gratuit',
                'duree' => '1-2 semaines',
                'priorite' => 2,
                'source_opportunity_id' => null,
            ];
        }

        if (empty($formation_items)) {
            $formation_items[] = [
                'titre' => "Formation {$competence_principale} - Centre de Formation",
                'description' => "Programme de formation professionnelle en {$competence_principale} adapté à votre profil.",
                'organisme' => 'CFP Dakar',
                'contact' => '+221 33 XXX XX XX',
                'cout_estime' => '45 000 FCFA',
                'duree' => '12 semaines',
                'priorite' => 1,
                'source_opportunity_id' => null,
            ];
        }

        if (empty($finance_items)) {
            $finance_items[] = [
                'titre' => 'Aide retour OIM — 300 USD',
                'description' => 'Versement J+1 sur Mobile Money. Inclut kit de démarrage selon activité choisie.',
                'organisme' => 'OIM Sénégal',
                'contact' => 'Agent OIM référent',
                'cout_estime' => 'Reçu: 300 USD',
                'duree' => 'J+1',
                'priorite' => 1,
                'source_opportunity_id' => null,
            ];
        }

        if (empty($logement_items)) {
            $logement_items[] = [
                'titre' => 'Accueil temporaire UNHCR Dakar',
                'description' => 'Hébergement 14 jours à l\'arrivée — Centre Almadies, repas inclus, accompagnement administratif.',
                'organisme' => 'UNHCR Dakar',
                'contact' => '+221 33 869 XX XX',
                'cout_estime' => 'Gratuit',
                'duree' => '2 semaines max',
                'priorite' => 1,
                'source_opportunity_id' => null,
            ];
        }

        if (empty($sante_items)) {
            $sante_items[] = [
                'titre' => 'Bilan médical complet J+3',
                'description' => 'Hôpital Principal de Dakar — bilan sanguin, tension, orientations spécialisées si nécessaire. Pris en charge OIM.',
                'organisme' => 'Hôpital Principal Dakar',
                'contact' => '+221 33 839 50 00',
                'cout_estime' => 'Gratuit OIM',
                'duree' => 'J+3',
                'priorite' => 1,
                'source_opportunity_id' => null,
            ];
        }

        $score = rand(75, 95);
        $resume = "Plan personnalisé pour votre retour au {$pays} ({$ville}). Profil {$competence_principale} avec " . count($competences) . " compétences identifiées. Score de faisabilité: {$score}/100.";

        return json_encode([
            'score' => $score,
            'resume' => $resume,
            'axes' => [
                'emploi' => ['label' => 'Emploi & Formation', 'items' => $emploi_items],
                'logement' => ['label' => 'Logement', 'items' => $logement_items],
                'finance' => ['label' => 'Soutien financier', 'items' => $finance_items],
                'sante' => ['label' => 'Santé', 'items' => $sante_items],
            ],
            'alerte_vulnerabilite' => null,
            'prochaine_etape' => 'Dans les 72h: contacter agent OIM, confirmer billet retour, prendre RDV UNHCR logement.',
            'conseils_agent' => 'Profil très positif. Vérifier disponibilité formation CFP et confirmer dates avant validation.',
        ], JSON_UNESCAPED_UNICODE);
    }

    private static function personalize_plan(array $plan, array $profile, array $matched, string $lang): array {
        // Cette méthode peut être étendue pour plus de personnalisation
        $pays = $profile['pays_origine'] ?? 'Sénégal';
        $ville = $profile['ville_retour'] ?? 'Dakar';
        
        $plan['resume'] = str_replace(['Sénégal', 'Dakar'], [$pays, $ville], $plan['resume']);
        
        return $plan;
    }

    private static function demo_plan_json(): string {
        return json_encode([
            'score'  => 87,
            'resume' => 'Plan structuré sur 4 axes avec une excellente faisabilité. Votre profil commerce et agriculture présente de solides atouts à Dakar. Un accompagnement OIM renforcé est prévu pour les 6 premiers mois.',
            'axes'   => [
                'emploi' => ['label'=>'Emploi & Formation','items'=>[
                    ['titre'=>'Formation agri-business 3 mois','description'=>'Centre de Formation Professionnelle de Dakar — gestion agricole, accès marchés locaux, financement agricole.','organisme'=>'CFP Dakar','contact'=>'+221 33 XXX XX XX','cout_estime'=>'45 000 FCFA (70% pris en charge OIM)','duree'=>'12 semaines','priorite'=>1,'source_opportunity_id'=>null],
                    ['titre'=>'Mise en relation ANPEM','description'=>'3 offres emploi compatibles dans le secteur commerce. Accompagnement CV et entretiens.','organisme'=>'ANPEM Sénégal','contact'=>'anpem.sn','cout_estime'=>'Gratuit','duree'=>'1-2 semaines','priorite'=>2,'source_opportunity_id'=>null],
                ]],
                'logement' => ['label'=>'Logement','items'=>[
                    ['titre'=>'Accueil temporaire UNHCR Dakar','description'=>'Hébergement 14 jours à l\'arrivée — Centre Almadies, repas inclus, accompagnement administratif.','organisme'=>'UNHCR Dakar','contact'=>'+221 33 869 XX XX','cout_estime'=>'Gratuit','duree'=>'2 semaines max','priorite'=>1,'source_opportunity_id'=>null],
                ]],
                'finance' => ['label'=>'Soutien financier','items'=>[
                    ['titre'=>'Aide retour OIM — 300 USD','description'=>'Versement J+1 sur Mobile Money. Inclut kit de démarrage selon activité choisie.','organisme'=>'OIM Sénégal','contact'=>'Agent OIM référent','cout_estime'=>'Reçu: 300 USD','duree'=>'J+1','priorite'=>1,'source_opportunity_id'=>null],
                    ['titre'=>'Micro-crédit BNDE','description'=>'Prêt sans garantie immobilière. Taux 5% sur 24 mois. Dossier dans les 30 jours après arrivée.','organisme'=>'BNDE','contact'=>'+221 33 XXX XX XX','cout_estime'=>"Jusqu'à 500 000 FCFA",'duree'=>'Réponse 15j','priorite'=>2,'source_opportunity_id'=>null],
                ]],
                'sante' => ['label'=>'Santé','items'=>[
                    ['titre'=>'Bilan médical complet J+3','description'=>'Hôpital Principal de Dakar — bilan sanguin, tension, orientations spécialisées si nécessaire. Pris en charge OIM.','organisme'=>'Hôpital Principal Dakar','contact'=>'+221 33 839 50 00','cout_estime'=>'Gratuit OIM','duree'=>'J+3','priorite'=>1,'source_opportunity_id'=>null],
                ]],
            ],
            'alerte_vulnerabilite' => null,
            'prochaine_etape'      => 'Dans les 72h: contacter agent OIM, confirmer billet retour, prendre RDV UNHCR logement.',
            'conseils_agent'       => 'Profil très positif. Vérifier disponibilité formation CFP et confirmer dates avant validation.',
        ], JSON_UNESCAPED_UNICODE);
    }
}
