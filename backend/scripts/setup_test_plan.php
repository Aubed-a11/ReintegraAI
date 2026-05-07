<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

echo "=== CRÉATION DE DONNÉES DE TEST ===\n\n";

// 1. Créer un utilisateur MIGRANT test si n'existe pas
$migrant_email = 'migrant@test.ma';
$existing = DB::row("SELECT id FROM users WHERE email = ?", [$migrant_email]);

if ($existing) {
    echo "✓ Migrant test existe déjà (ID: {$existing['id']})\n";
    $migrant_id = $existing['id'];
} else {
    $migrant_id = DB::insert('users', [
        'email' => $migrant_email,
        'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
        'phone' => '+212612345678',
        'phone_hash' => password_hash('+212612345678', PASSWORD_BCRYPT),
        'first_name' => 'Test',
        'last_name' => 'Migrant',
        'role' => 'MIGRANT',
        'lang_pref' => 'fr',
    ]);
    echo "✓ Migrant test créé (ID: {$migrant_id})\n";
}

// 2. Créer ou mettre à jour son profil avec 80% de complétion
$profile = DB::row("SELECT id FROM profiles WHERE user_id = ?", [$migrant_id]);

$profile_data = [
    'pays_origine' => 'Sénégal',
    'ville_retour' => 'Dakar',
    'tranche_age' => '25-35',
    'niveau_etudes' => 'BAC+2',
    'competences' => json_encode(['Commerce', 'Gestion', 'Vente']),
    'vulnerabilites' => '{SDF,SANS_DOC}',
    'completion_pct' => 80,
];

if ($profile) {
    DB::query("UPDATE profiles SET " . implode('=?,', array_keys($profile_data)) . "=? WHERE user_id=?",
        array_merge(array_values($profile_data), [$migrant_id]));
    echo "✓ Profil migrant mis à jour (80% complet)\n";
    $profile_id = $profile['id'];
} else {
    $profile_id = DB::insert('profiles', array_merge(['user_id' => $migrant_id], $profile_data));
    echo "✓ Profil migrant créé (80% complet)\n";
}

// 3. Créer un plan de test pour ce profil
$existing_plan = DB::row("SELECT id FROM plans WHERE profile_id = ? LIMIT 1", [$profile_id]);

if ($existing_plan) {
    echo "✓ Plan test existe déjà (ID: {$existing_plan['id']})\n";
} else {
    $plan_data = [
        'profile_id' => $profile_id,
        'statut' => 'VALIDATED',
        'score_ia' => 85,
        'resume_global' => 'Plan personnalisé pour votre retour au Sénégal. Profil commerce avec 3 compétences identifiées. Score de faisabilité: 85/100.',
        'axes' => json_encode([
            'emploi' => [
                'label' => 'Emploi & Formation',
                'items' => [
                    [
                        'titre' => 'Formation commerce local',
                        'description' => 'Programme adapté au contexte sénégalais',
                        'organisme' => 'CFP Dakar',
                        'contact' => '+221 33 123 45 67',
                        'cout_estime' => '50 000 FCFA',
                        'duree' => '8 semaines',
                        'priorite' => 1,
                    ],
                ]
            ],
            'logement' => [
                'label' => 'Logement',
                'items' => [
                    [
                        'titre' => 'Accueil UNHCR Dakar',
                        'description' => 'Hébergement 14 jours, repas inclus',
                        'organisme' => 'UNHCR',
                        'contact' => '+221 33 869 50 00',
                        'cout_estime' => 'Gratuit',
                        'duree' => '2 semaines',
                        'priorite' => 1,
                    ],
                ]
            ],
            'finance' => [
                'label' => 'Soutien financier',
                'items' => [
                    [
                        'titre' => 'Aide retour OIM',
                        'description' => 'Versement sur Mobile Money',
                        'organisme' => 'OIM',
                        'contact' => 'agent@oim.sn',
                        'cout_estime' => '300 USD',
                        'duree' => 'J+1',
                        'priorite' => 1,
                    ],
                ]
            ],
            'sante' => [
                'label' => 'Santé',
                'items' => [
                    [
                        'titre' => 'Bilan médical OIM',
                        'description' => 'Pris en charge par l\'OIM',
                        'organisme' => 'Hôpital Principal',
                        'contact' => '+221 33 839 50 00',
                        'cout_estime' => 'Gratuit',
                        'duree' => 'J+3',
                        'priorite' => 1,
                    ],
                ]
            ]
        ]),
        'prochaine_etape' => 'Contacter agent OIM dans 72h pour confirmer les démarches',
        'conseils_agent' => 'Profil positif, bon potentiel d\'intégration.',
    ];

    $plan_id = DB::insert('plans', $plan_data);
    echo "✓ Plan test créé et validé (ID: {$plan_id})\n";
}

echo "\n=== DONNÉES DE CONNEXION ===\n";
echo "Email: {$migrant_email}\n";
echo "Password: password123\n";
echo "\nMaintenant tu peux :\n";
echo "1. Te connecter avec ces identifiants\n";
echo "2. Accéder à la page /plan pour voir ton plan\n";
echo "3. Tester l'export PDF\n";