<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

echo "=== TEST DATA VERIFICATION ===\n\n";

// Check users
$users = DB::rows("SELECT id, email, role, first_name FROM users LIMIT 10");
echo "Users (" . count($users) . "):\n";
foreach ($users as $u) {
    echo "  - {$u['first_name']} ({$u['role']}) {$u['email']}\n";
}

// Check profiles
$profiles = DB::rows("SELECT id, user_id, completion_pct, pays_origine FROM profiles LIMIT 10");
echo "\nProfiles (" . count($profiles) . "):\n";
foreach ($profiles as $p) {
    echo "  - User {$p['user_id']}: {$p['completion_pct']}% ({$p['pays_origine']})\n";
}

// Check plans
$plans = DB::rows("SELECT id, profile_id, statut, score_ia FROM plans LIMIT 10");
echo "\nPlans (" . count($plans) . "):\n";
foreach ($plans as $p) {
    echo "  - Plan {$p['id']}: Profile {$p['profile_id']} ({$p['statut']}) Score {$p['score_ia']}\n";
}

// Check if admin exists
$admin = DB::row("SELECT id, email, role FROM users WHERE role = 'ADMIN' LIMIT 1");
if ($admin) {
    echo "\nAdmin user: {$admin['email']} (ID: {$admin['id']})\n";
} else {
    echo "\nNo admin user found.\n";
}

// Check for MIGRANT with complete profile and plan
$migrant_with_plan = DB::row("
    SELECT u.id, u.email, u.first_name, p.id as profile_id, p.completion_pct, 
           pl.id as plan_id, pl.statut
    FROM users u
    LEFT JOIN profiles p ON u.id = p.user_id
    LEFT JOIN plans pl ON p.id = pl.profile_id
    WHERE u.role = 'MIGRANT' AND p.completion_pct >= 60 AND pl.id IS NOT NULL
    LIMIT 1
");

if ($migrant_with_plan) {
    echo "\nMigrant with complete profile and plan:\n";
    echo "  - User: {$migrant_with_plan['first_name']} ({$migrant_with_plan['email']})\n";
    echo "  - Profile: {$migrant_with_plan['completion_pct']}% complete\n";
    echo "  - Plan: {$migrant_with_plan['plan_id']} ({$migrant_with_plan['statut']})\n";
} else {
    echo "\nNo complete test data found (migrant with 60%+ profile and a plan).\n";
}

echo "\n=== END ===\n";