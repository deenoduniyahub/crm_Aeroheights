<?php
declare(strict_types=1);

/**
 * CLI only: creates (or resets) the owner / admin login.
 *   php setup/create_owner.php <email> <username> "<full name>"
 * The password is read from the AERO_OWNER_PASSWORD environment variable so it never lands in shell history.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/Database.php';

[$_, $email, $username, $fullName] = array_pad($argv, 4, '');
$password = (string)getenv('AERO_OWNER_PASSWORD');
$email = strtolower(trim($email));
$username = strtolower(trim($username));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-z0-9_.]{3,50}$/', $username) || strlen($password) < 8) {
    fwrite(STDERR, "Usage: AERO_OWNER_PASSWORD='...' php setup/create_owner.php <email> <username> \"<full name>\"\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$existing = Database::fetchOne("SELECT id FROM users WHERE LOWER(email) = ? OR LOWER(username) = ?", [$email, $username]);
if ($existing) {
    Database::execute("UPDATE users SET email = ?, username = ?, full_name = ?, password_hash = ?, role = 'admin', is_active = 1 WHERE id = ?", [$email, $username, $fullName ?: null, $hash, $existing['id']]);
    echo "Owner account #{$existing['id']} updated.\n";
} else {
    Database::execute("INSERT INTO users (username, email, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, 'admin', 1)", [$username, $email, $hash, $fullName ?: null]);
    echo "Owner account #" . Database::lastInsertId() . " created.\n";
}
