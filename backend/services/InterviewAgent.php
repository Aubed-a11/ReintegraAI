<?php
// ================================================================
// HorizonAI — InterviewAgent
// Conduit un entretien oral structuré en 10 étapes via Claude.
// Utilisé par le kiosque OIM (sans login migrant).
// ================================================================
declare(strict_types=1);

class InterviewAgent {

    // Ordre des étapes de l'entretien
    private const ETAPES = [
        'BIENVENUE', 'IDENTITE', 'VILLE', 'FAMILLE',
        'EDUCATION', 'COMPETENCES', 'OBJECTIFS',
        'SANTE', 'CONTACT', 'RECAPITULATIF',
    ];

    // Questions de fallback offline (par étape, par langue)
    private const QUESTIONS_FALLBACK = [
        'fr' => [
            'BIENVENUE'    => "Bonjour ! Je suis votre assistant HorizonAI. Dans quelle langue souhaitez-vous que je vous parle ? Vous pouvez répondre en français, anglais, arabe ou wolof.",
            'IDENTITE'     => "Pouvez-vous me dire votre prénom et votre pays d'origine ?",
            'VILLE'        => "Dans quelle ville ou région souhaitez-vous retourner dans votre pays ?",
            'FAMILLE'      => "Quelle est votre situation familiale ? Êtes-vous célibataire, marié(e) ? Avez-vous des enfants ?",
            'EDUCATION'    => "Avez-vous été à l'école ? Si oui, jusqu'à quel niveau ? Si non, ne vous inquiétez pas, nous pouvons quand même vous aider.",
            'COMPETENCES'  => "Qu'est-ce que vous savez faire de vos mains ou de votre tête ? Par exemple : cultiver des légumes, vendre au marché, coudre, conduire, construire ?",
            'OBJECTIFS'    => "Qu'est-ce que vous voulez faire quand vous rentrez dans votre pays ? Quel est votre rêve ou votre projet ?",
            'SANTE'        => "Comment va votre santé ? Avez-vous besoin d'une aide médicale particulière ?",
            'CONTACT'      => "Avez-vous un numéro de téléphone où l'OIM peut vous contacter ? Si non, ce n'est pas obligatoire.",
            'RECAPITULATIF'=> "J'ai bien noté toutes vos informations. Je vais maintenant générer votre plan de réintégration personnalisé. Cela prend quelques instants...",
        ],
        'en' => [
            'BIENVENUE'    => "Hello! I am your HorizonAI assistant. In which language would you like to speak? You can answer in French, English, Arabic or Wolof.",
            'IDENTITE'     => "Could you tell me your first name and country of origin?",
            'VILLE'        => "Which city or region do you plan to return to in your country?",
            'FAMILLE'      => "What is your family situation? Are you single, married? Do you have children?",
            'EDUCATION'    => "Did you go to school? If yes, up to what level? If no, don't worry, we can still help you.",
            'COMPETENCES'  => "What can you do with your hands or your mind? For example: grow vegetables, sell at the market, sew, drive, build?",
            'OBJECTIFS'    => "What do you want to do when you return to your country? What is your dream or project?",
            'SANTE'        => "How is your health? Do you need any special medical assistance?",
            'CONTACT'      => "Do you have a phone number where IOM can contact you? If not, it is not required.",
            'RECAPITULATIF'=> "I have noted all your information. I will now generate your personalized reintegration plan. This will take a few moments...",
        ],
        'ar' => [
            'BIENVENUE'    => "مرحباً! أنا مساعدك HorizonAI. بأي لغة تريد أن أتحدث معك؟ يمكنك الإجابة بالفرنسية أو الإنجليزية أو العربية أو الولوف.",
            'IDENTITE'     => "هل يمكنك إخباري باسمك الأول وبلدك الأصلي؟",
            'VILLE'        => "إلى أي مدينة أو منطقة تخطط للعودة في بلدك؟",
            'FAMILLE'      => "ما هو وضعك العائلي؟ هل أنت أعزب أم متزوج؟ هل لديك أطفال؟",
            'EDUCATION'    => "هل ذهبت إلى المدرسة؟ إذا كانت الإجابة نعم، حتى أي مستوى؟ إذا لم تذهب، لا تقلق، يمكننا مساعدتك.",
            'COMPETENCES'  => "ماذا تعرف أن تفعل بيديك أو بعقلك؟ مثلاً: زراعة الخضار، البيع في السوق، الخياطة، القيادة، البناء؟",
            'OBJECTIFS'    => "ماذا تريد أن تفعل عندما تعود إلى بلدك؟ ما هو حلمك أو مشروعك؟",
            'SANTE'        => "كيف صحتك؟ هل تحتاج إلى مساعدة طبية خاصة؟",
            'CONTACT'      => "هل لديك رقم هاتف يمكن للمنظمة الدولية للهجرة الاتصال بك عليه؟ إذا لم يكن، فهذا ليس إلزامياً.",
            'RECAPITULATIF'=> "لقد سجلت جميع معلوماتك. سأقوم الآن بإنشاء خطة إعادة الإدماج الشخصية الخاصة بك. سيستغرق ذلك بضع لحظات...",
        ],
        'wo' => [
            'BIENVENUE'    => "Salaam aleekum! Maa ngi HorizonAI. Lan la ci kanam ? Français, anglais, arabe walla wolof ?",
            'IDENTITE'     => "Lan mooy sa tuur ak sa dëkk ci ngoon ?",
            'VILLE'        => "Fan la bëgg dem ci sa dëkk bi ?",
            'FAMILLE'      => "Lan mooy sa situation familiale ? Xamoom sa doomu rëy yi ?",
            'EDUCATION'    => "Daaw nga jàng ? Bu waaw, ñaata waxtu la jàngal ci ?",
            'COMPETENCES'  => "Lan nga xam def ak sa loxo ? Mbay, jënd-jënd, cosaan, kanam ?",
            'OBJECTIFS'    => "Lan la bëgg def bu dem nga ci sa dëkk ?",
            'SANTE'        => "Nanga wér ? Am nga dara bu yees ci wér yi ?",
            'CONTACT'      => "Am nga téléphone ? IOM dafay bëgg wax ak yow seppo.",
            'RECAPITULATIF'=> "Bëgg naa tëral sa dossier. Maa ngi soxor sa plan de réintégration...",
        ],
        'bm' => [
            'BIENVENUE'    => "I ni ce! Ne ye HorizonAI ye. I bɛ kuma kan jumɛn na ? Français, anglais, arabe wala bambara ?",
            'IDENTITE'     => "I tɔgɔ ye mun ye, ani i ka jamana ye mun ye ?",
            'VILLE'        => "I bɛ sɔrɔ min kɔfɛ i ka jamana kɔnɔ ?",
            'FAMILLE'      => "I ka somɔgɔw cogoya ye mun ye ? I den dɔnna wa ?",
            'EDUCATION'    => "I tun bɛ kalankɛ wa ? Ayi kɔrɔ, kana dimi, a ka se ka dɛmɛ i.",
            'COMPETENCES'  => "I bɛ se ka baara juman kɛ ? Sɔgɔsɔgɔ, jalan, sɛnɛkɛ, juguya ?",
            'OBJECTIFS'    => "I bɛ mun kɛ bɔ i bɛ segin i ka jamana kɔnɔ ?",
            'SANTE'        => "I ka kɛnɛya bɛ di ? I fɛ dɛmɛ si bɛ di ?",
            'CONTACT'      => "I bɛ telefɔni dɔ sɔrɔ wa ? OIM bɛna se ka i weele a la.",
            'RECAPITULATIF'=> "N bɛna i ka kunnafoni minɛ sisan. N bɛ i ka seginkɛlɛ seere dilan...",
        ],
        'ha' => [
            'BIENVENUE'    => "Sannu! Ni ne HorizonAI. Wace harshe kake son mu yi magana da ita ? Faransanci, Turanci, Larabci ko Hausa ?",
            'IDENTITE'     => "Yaya sunanka, kuma ƙasar da kake fitowa ita ce wace ?",
            'VILLE'        => "Wane birni ko yanki kake son komawa a ƙasarka ?",
            'FAMILLE'      => "Yaya halin iyalinka ? Kana da aure ? Kana da 'ya'ya ?",
            'EDUCATION'    => "Ka je makaranta ? Idan a'a, kada ka damu, za mu iya taimaka maka.",
            'COMPETENCES'  => "Mene ne ka san yi ? Misali: noma, sayarwa, dinki, tuki, gini ?",
            'OBJECTIFS'    => "Me kake son yi lokacin da ka koma ƙasarka ?",
            'SANTE'        => "Yaya lafiyarka ? Kana buƙatar taimako na musamman na likita ?",
            'CONTACT'      => "Kana da lambar waya da OIM za ta iya tuntuɓe ka ?",
            'RECAPITULATIF'=> "Na rubuta duk bayananku. Zan shirya shirin dawowarka yanzu...",
        ],
        'ff' => [
            'BIENVENUE'    => "Jam waali! Mi winndii HorizonAI. Ko hol goongi ngam tawde i ? Faransere, Engele, Arabe wala Fulfuldé ?",
            'IDENTITE'     => "Ko holɗo togniral maa, e ko hol leydi maa ?",
            'VILLE'        => "Wuro wanɗo kaa yiɗaa ruttude e leydi maa ?",
            'FAMILLE'      => "Ko hol haalannde maa e galle ? A woodi ɓiɓɓe ?",
            'EDUCATION'    => "Ndaarii-ɗaa e jangirde ? Si alaa, ko wayaani, min mbaawi wallude maa.",
            'COMPETENCES'  => "Ko holɗo humpitii waɗaade ? Mbaydi : lahal, suudu, ligginde, yirnaade ?",
            'OBJECTIFS'    => "Ko holɗo yiɗaa waɗaade nde ummitii-ɗaa e leydi maa ?",
            'SANTE'        => "Ko hol cellal maa ? Ina woodi ballal goɗngal keɓtungal nii mbaɗaa ?",
            'CONTACT'      => "A woodi nimero telefon e OIM waawi nodditaade maa ?",
            'RECAPITULATIF'=> "Ndonkii-mi kala haalanɗe maa. Mi ñannoo piyanaa maa hannde...",
        ],
        'tzm' => [
            'BIENVENUE'    => "Azul ! Nkk d HorizonAI. D acu n tutlayt tebɣiḍ ? Tafransist, Taglinzit, Taɛrabt neɣ Tamaziɣt ?",
            'IDENTITE'     => "Acu-t isem-ik, d acu-t tamurt-ik ?",
            'VILLE'        => "Anida tebɣiḍ ad trjedjeḍ di tamurt-ik ?",
            'FAMILLE'      => "Acu-t taɣult-ik ? Tesɛiḍ tarwa ?",
            'EDUCATION'    => "Telliḍ s tɣiwant ? Ur yelli, ur txeyyeḍ, nezmer ad k-nεawen.",
            'COMPETENCES'  => "Acu tessen ad tgaḍ ? Aɣrum, tazmart, taɣuri, adrum, ticerka ?",
            'OBJECTIFS'    => "Acu tebɣiḍ ad tgaḍ mi tremḍeḍ ɣer tmurt-ik ?",
            'SANTE'        => "Amek lḥal-ik s teɣzi ? Tesɛiḍ aḥtaj n tallalt tabibit ?",
            'CONTACT'      => "Tesɛiḍ uṭṭun n tiliɣri amedya OIM ad ak-isfed ?",
            'RECAPITULATIF'=> "Rniɣ-d kra n isallen-ik. Ad sbeddeɣ asenked-ik tura...",
        ],
    ];

    // Démarrer une nouvelle session
    public static function start(string $device_id, string $lang = 'fr'): array {
        $lang = in_array($lang, ['fr','en','ar','wo']) ? $lang : 'fr';
        $first_q = self::QUESTIONS_FALLBACK[$lang]['BIENVENUE'];

        $session_id = DB::insert('interview_sessions', [
            'device_id'    => $device_id,
            'lang'         => $lang,
            'statut'       => 'IN_PROGRESS',
            'etape'        => 'BIENVENUE',
            'conversation' => json_encode([
                ['role'=>'assistant','content'=>$first_q,'ts'=>date('c')]
            ], JSON_UNESCAPED_UNICODE),
            'profile_draft'=> json_encode([], JSON_UNESCAPED_UNICODE),
            'synced'       => 1,
        ]);

        return [
            'success'    => true,
            'session_id' => $session_id,
            'question'   => $first_q,
            'etape'      => 'BIENVENUE',
            'step_num'   => 1,
            'step_total' => count(self::ETAPES),
        ];
    }

    // Traiter la réponse du migrant et retourner la prochaine question
    public static function next_step(string $session_id, string $user_message): array {
        $session = DB::row('SELECT * FROM interview_sessions WHERE id = ?', [$session_id]);
        if (!$session) return ['success'=>false,'error'=>'session_not_found','message'=>'Session introuvable'];
        if ($session['statut'] === 'COMPLETED') return ['success'=>false,'error'=>'already_completed','message'=>'Entretien terminé'];

        $lang         = $session['lang'];
        $etape        = $session['etape'];
        $conversation = json_decode($session['conversation'], true) ?? [];
        $profile      = json_decode($session['profile_draft'], true) ?? [];

        // Ajouter la réponse du migrant
        $conversation[] = ['role'=>'user','content'=>$user_message,'ts'=>date('c')];

        // Extraire données du profil depuis la réponse
        $profile = self::extract_profile_data($etape, $user_message, $profile, $lang);

        // Passer à l'étape suivante
        $next_etape = self::next_etape($etape);
        $completed  = ($next_etape === null);

        if ($completed) {
            // Fin de l'entretien : générer le plan
            return self::finalize($session_id, $session, $conversation, $profile, $lang);
        }

        // Générer la prochaine question via Claude ou fallback
        $next_question = self::get_next_question($next_etape, $profile, $conversation, $lang);
        $conversation[] = ['role'=>'assistant','content'=>$next_question,'ts'=>date('c')];

        $step_num = array_search($next_etape, self::ETAPES) + 1;

        // Persister
        DB::query(
            'UPDATE interview_sessions SET etape=?,conversation=?,profile_draft=?,updated_at=datetime(\'now\') WHERE id=?',
            [$next_etape, json_encode($conversation, JSON_UNESCAPED_UNICODE), json_encode($profile, JSON_UNESCAPED_UNICODE), $session_id]
        );

        return [
            'success'    => true,
            'question'   => $next_question,
            'etape'      => $next_etape,
            'step_num'   => $step_num,
            'step_total' => count(self::ETAPES),
            'profile_draft' => $profile,
            'completed'  => false,
        ];
    }

    // Finaliser l'entretien et déclencher la génération de plan
    private static function finalize(string $session_id, array $session, array $conversation, array $profile, string $lang): array {
        $closing = self::QUESTIONS_FALLBACK[$lang]['RECAPITULATIF'];
        $conversation[] = ['role'=>'assistant','content'=>$closing,'ts'=>date('c')];

        // Calculer date RDV (J+2 ouvrable)
        $rdv = date('Y-m-d', strtotime('+2 weekdays'));
        $rdv_lieu = 'Bureau OIM — sur rendez-vous';

        DB::query(
            'UPDATE interview_sessions SET statut=?,etape=?,conversation=?,profile_draft=?,rdv_date=?,rdv_lieu=?,updated_at=datetime(\'now\') WHERE id=?',
            ['COMPLETED', 'RECAPITULATIF', json_encode($conversation, JSON_UNESCAPED_UNICODE), json_encode($profile, JSON_UNESCAPED_UNICODE), $rdv, $rdv_lieu, $session_id]
        );

        // Notifier les agents OIM
        $agents = DB::rows("SELECT id FROM users WHERE role IN ('AGENT','SUPERVISEUR') AND is_active=1");
        $pays = $profile['pays_origine'] ?? 'N/A';
        foreach ($agents as $a) {
            try {
                DB::insert('notifications', [
                    'user_id' => $a['id'],
                    'channel' => 'IN_APP',
                    'title'   => 'Nouvel entretien kiosque',
                    'body'    => "Entretien oral terminé — Migrant ({$pays}) — RDV {$rdv}",
                    'data'    => json_encode(['session_id'=>$session_id,'rdv_date'=>$rdv]),
                ]);
            } catch (\Throwable) {}
        }

        return [
            'success'    => true,
            'completed'  => true,
            'question'   => $closing,
            'etape'      => 'RECAPITULATIF',
            'step_num'   => count(self::ETAPES),
            'step_total' => count(self::ETAPES),
            'profile_draft' => $profile,
            'rdv_date'   => $rdv,
            'rdv_lieu'   => $rdv_lieu,
        ];
    }

    // Générer la prochaine question (Claude ou fallback)
    private static function get_next_question(string $etape, array $profile, array $conversation, string $lang): string {
        if (!CLAUDE_API_KEY) {
            return self::QUESTIONS_FALLBACK[$lang][$etape] ?? self::QUESTIONS_FALLBACK['fr'][$etape] ?? "Continuez...";
        }

        $history_txt = implode("\n", array_map(
            fn($m) => ($m['role'] === 'user' ? 'Migrant: ' : 'Assistant: ') . $m['content'],
            array_slice($conversation, -6)
        ));

        $profile_txt = json_encode(array_filter($profile), JSON_UNESCAPED_UNICODE);

        $system = "Tu es HorizonAI, assistant OIM pour un entretien de réintégration.\nRègle absolue: pose UNE seule question courte, simple, bienveillante.\nLangue: {$lang}. Réponse: texte brut uniquement, PAS de JSON.\nContexte: migrant possiblement analphabète — langage simple, concret.";

        $prompt = "Étape suivante: {$etape}\nProfil construit jusqu'ici: {$profile_txt}\nHistorique:\n{$history_txt}\n\nPose la question pour l'étape {$etape} de manière naturelle et bienveillante.";

        try {
            $payload = json_encode([
                'model'      => CLAUDE_MODEL,
                'max_tokens' => 150,
                'system'     => $system,
                'messages'   => [['role'=>'user','content'=>$prompt]],
            ], JSON_UNESCAPED_UNICODE);

            $ch = curl_init(CLAUDE_API_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'x-api-key: ' . CLAUDE_API_KEY,
                    'anthropic-version: 2023-06-01',
                ],
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 200) {
                $d = json_decode($body, true);
                $q = trim($d['content'][0]['text'] ?? '');
                if (!empty($q)) return $q;
            }
        } catch (\Throwable) {}

        return self::QUESTIONS_FALLBACK[$lang][$etape] ?? self::QUESTIONS_FALLBACK['fr'][$etape] ?? "Continuez...";
    }

    // Extraire les données du profil depuis la réponse vocale
    private static function extract_profile_data(string $etape, string $msg, array $profile, string $lang): array {
        $t = mb_strtolower(trim($msg));

        switch ($etape) {
            case 'BIENVENUE':
                if (str_contains($t,'english')||str_contains($t,'anglais')||str_contains($t,'en ')) $profile['lang'] = 'en';
                elseif (preg_match('/عرب|arabe|arabic/u', $t)) $profile['lang'] = 'ar';
                elseif (str_contains($t,'wolof')||str_contains($t,'wo')) $profile['lang'] = 'wo';
                else $profile['lang'] = 'fr';
                break;

            case 'IDENTITE':
                $profile['reponse_identite'] = $msg;
                // Essayer d'extraire prénom
                $words = explode(' ', trim($msg));
                if (count($words) >= 1) $profile['prenom_probable'] = ucfirst(mb_strtolower($words[0]));
                // Détecter pays parmi les 12 pays supportés
                $pays_list = ['Sénégal','Côte d\'Ivoire','Niger','Mali','Guinée','Burkina Faso','Cameroun','Togo','Bénin','Mauritanie','Maroc','Algérie'];
                foreach ($pays_list as $pays) {
                    if (str_contains($t, mb_strtolower($pays)) || str_contains($t, mb_strtolower(preg_replace('/[éèê]/u','e',$pays)))) {
                        $profile['pays_origine'] = $pays; break;
                    }
                }
                break;

            case 'VILLE':
                $profile['ville_retour'] = ucfirst(trim($msg));
                break;

            case 'FAMILLE':
                $profile['situation_familiale'] = 'Célibataire';
                if (str_contains($t,'mari')||str_contains($t,'épous')||str_contains($t,'femme')||str_contains($t,'married')) $profile['situation_familiale'] = 'Marié(e)';
                preg_match('/(\d+)\s*(enfant|child|kid)/u', $t, $m);
                if ($m) $profile['enfants'] = (int)$m[1];
                elseif (str_contains($t,'enfant')||str_contains($t,'child')) $profile['enfants'] = 1;
                break;

            case 'EDUCATION':
                $profile['competences_informelles_edu'] = $msg;
                if (str_contains($t,'jamais')||str_contains($t,'non')||str_contains($t,'never')||str_contains($t,'pas')&&str_contains($t,'école')) {
                    $profile['niveau_etudes'] = 'Sans diplôme';
                    $profile['alphabetisation'] = 'NON';
                } elseif (str_contains($t,'prima')||str_contains($t,'elemen')) {
                    $profile['niveau_etudes'] = 'Primaire'; $profile['alphabetisation'] = 'PARTIEL';
                } elseif (str_contains($t,'collège')||str_contains($t,'college')||str_contains($t,'moyen')) {
                    $profile['niveau_etudes'] = 'Collège'; $profile['alphabetisation'] = 'OUI';
                } elseif (str_contains($t,'lycée')||str_contains($t,'bac')) {
                    $profile['niveau_etudes'] = 'Bac'; $profile['alphabetisation'] = 'OUI';
                } elseif (str_contains($t,'univers')||str_contains($t,'univer')||str_contains($t,'licence')) {
                    $profile['niveau_etudes'] = 'Bac +3'; $profile['alphabetisation'] = 'OUI';
                } else {
                    $profile['niveau_etudes'] = 'Sans diplôme'; $profile['alphabetisation'] = 'PARTIEL';
                }
                break;

            case 'COMPETENCES':
                $profile['competences_informelles'] = $msg;
                $profile['objectifs_informels'] = $msg;
                break;

            case 'OBJECTIFS':
                $profile['objectifs'] = $msg;
                break;

            case 'SANTE':
                $profile['sante'] = $msg;
                if (str_contains($t,'aveugle')||str_contains($t,'blind')||str_contains($t,'voir')) $profile['vulnerabilites'][] = 'HANDICAP';
                if (str_contains($t,'malad')||str_contains($t,'sick')||str_contains($t,'chroni')) $profile['vulnerabilites'][] = 'SANTE_CHRONIQUE';
                if (str_contains($t,'enceint')||str_contains($t,'pregnant')) $profile['vulnerabilites'][] = 'FEMME_ENCEINTE';
                if (str_contains($t,'urgent')||str_contains($t,'grave')||str_contains($t,'hospit')) $profile['vulnerabilites'][] = 'SANTE_URGENTE';
                if (empty($profile['vulnerabilites'])) $profile['vulnerabilites'][] = 'AUCUNE';
                break;

            case 'CONTACT':
                preg_match('/[\+\d][\d\s\-]{7,}/', $msg, $m);
                if ($m) $profile['telephone'] = preg_replace('/\s+/','',$m[0]);
                break;
        }

        return $profile;
    }

    // Retourner l'étape suivante (null si fin)
    private static function next_etape(string $current): ?string {
        $idx = array_search($current, self::ETAPES);
        if ($idx === false) return self::ETAPES[0];
        $next = $idx + 1;
        return isset(self::ETAPES[$next]) ? self::ETAPES[$next] : null;
    }

    // Récupérer une session
    public static function get(string $session_id): ?array {
        $s = DB::row('SELECT * FROM interview_sessions WHERE id = ?', [$session_id]);
        if (!$s) return null;
        $s['conversation']  = json_decode($s['conversation'],  true) ?? [];
        $s['profile_draft'] = json_decode($s['profile_draft'], true) ?? [];
        return $s;
    }

    // Abandonner une session
    public static function abandon(string $session_id): void {
        DB::query("UPDATE interview_sessions SET statut='ABANDONED',updated_at=datetime('now') WHERE id=?", [$session_id]);
    }

    // Sync des sessions offline (batch)
    public static function sync_offline(array $sessions): array {
        $synced = 0; $failed = 0;
        foreach ($sessions as $s) {
            try {
                DB::query(
                    'INSERT OR REPLACE INTO interview_sessions (id,device_id,lang,statut,etape,conversation,profile_draft,rdv_date,rdv_lieu,synced,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,1,?,datetime(\'now\'))',
                    [$s['id']??DB::uuid(), $s['device_id']??'unknown', $s['lang']??'fr', $s['statut']??'COMPLETED',
                     $s['etape']??'RECAPITULATIF', json_encode($s['conversation']??[]), json_encode($s['profile_draft']??[]),
                     $s['rdv_date']??null, $s['rdv_lieu']??null, $s['created_at']??date('Y-m-d H:i:s')]
                );
                $synced++;
            } catch (\Throwable) { $failed++; }
        }
        return ['synced'=>$synced,'failed'=>$failed];
    }
}
