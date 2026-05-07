<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$adminEmail = 'admin@reintegraai.ma';
$adminPassword = 'admin123';

// Check if admin already exists
$existing = DB::row("SELECT id FROM users WHERE email = ?", [$adminEmail]);
if ($existing) {
    echo "Admin user already exists.\n";
    exit(0);
}

// Create admin user
$passwordHash = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
$phoneHash = password_hash('+212600000000', PASSWORD_BCRYPT, ['cost' => 12]);

$userId = DB::insert('users', [
    'email' => $adminEmail,
    'password_hash' => $passwordHash,
    'phone' => '+212600000000',
    'phone_hash' => $phoneHash,
    'first_name' => 'Admin',
    'last_name' => 'OIM',
    'role' => 'ADMIN',
    'lang_pref' => 'fr',
]);

echo "Admin user created successfully!\n";
echo "Email: {$adminEmail}\n";
echo "Password: {$adminPassword}\n";
echo "User ID: {$userId}\n";