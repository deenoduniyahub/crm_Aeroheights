<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

/**
 * Team Login Management (admin-only).
 * Lets an admin add, edit, deactivate, or reset passwords for teammate accounts
 * without needing developer help.
 */
class UserController {

    private const ROLES = ['admin', 'full_access', 'view_only'];

    public static function getAll(): array {
        return Database::fetchAll(
            "SELECT id, username, email, full_name, role, is_active, last_login_at, created_at FROM users ORDER BY created_at ASC"
        );
    }

    public static function save(array $data): array {
        $id       = !empty($data['id']) ? (int)$data['id'] : null;
        $username = strtolower(trim($data['username'] ?? ''));
        $fullName = trim($data['full_name'] ?? '');
        $email    = strtolower(trim((string)($data['email'] ?? '')));
        $role     = trim($data['role'] ?? 'full_access');
        $password = (string)($data['password'] ?? '');
        $isActive = !empty($data['is_active']) ? 1 : 0;

        if ($username === '' || !preg_match('/^[a-z0-9_.]{3,50}$/', $username)) {
            return ['success' => false, 'message' => 'Username must be 3-50 characters: letters, numbers, dot or underscore only.'];
        }
        if (!in_array($role, self::ROLES, true)) {
            return ['success' => false, 'message' => 'Invalid role selected.'];
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address (or leave it blank).'];
        }

        $existing = Database::fetchOne("SELECT id FROM users WHERE LOWER(username) IN (?, ?) OR (email IS NOT NULL AND LOWER(email) IN (?, ?))", [$username, $email, $username, $email]);
        if ($existing && (int)$existing['id'] !== $id) {
            return ['success' => false, 'message' => 'That username or email is already used by another account.'];
        }
        $email = $email !== '' ? $email : null;

        if ($id) {
            if ($password !== '') {
                if (strlen($password) < 8) {
                    return ['success' => false, 'message' => 'New password must be at least 8 characters.'];
                }
                Database::execute(
                    "UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, is_active = ?, password_hash = ? WHERE id = ?",
                    [$username, $email, $fullName, $role, $isActive, password_hash($password, PASSWORD_DEFAULT), $id]
                );
            } else {
                Database::execute(
                    "UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, is_active = ? WHERE id = ?",
                    [$username, $email, $fullName, $role, $isActive, $id]
                );
            }
            // Force re-login everywhere for this account whenever it is edited (role/password/active change).
            Database::execute("DELETE FROM user_remember_tokens WHERE user_id = ?", [$id]);
            return ['success' => true, 'message' => 'Login account updated successfully.'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        }
        Database::execute(
            "INSERT INTO users (username, email, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, ?, ?)",
            [$username, $email, password_hash($password, PASSWORD_DEFAULT), $fullName, $role, $isActive]
        );
        return ['success' => true, 'message' => 'New login account created successfully.', 'user_id' => Database::lastInsertId()];
    }

    public static function delete(int $id, int $currentUserId): array {
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid account.'];
        }
        if ($id === $currentUserId) {
            return ['success' => false, 'message' => 'You cannot delete the account you are currently logged in with.'];
        }
        Database::execute("DELETE FROM users WHERE id = ?", [$id]);
        return ['success' => true, 'message' => 'Login account removed.'];
    }
}
