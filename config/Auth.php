<?php
declare(strict_types=1);

require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/Database.php';

/**
 * Login Gate: per-user accounts, role-based access, and a "Remember Me"
 * persistent cookie so the team is not re-asked for the password on every visit.
 */
class Auth {
    private const REMEMBER_COOKIE = 'aero_remember';
    private const REMEMBER_DAYS   = 30;

    private const MAX_FAILED_LOGINS = 5;
    private const LOCKOUT_SECONDS   = 300;

    /** Signs in with a username OR an email address. */
    public static function attempt(string $login, string $password, bool $remember): array {
        $login = strtolower(trim($login));
        if ($login === '' || $password === '') {
            return ['success' => false, 'message' => 'Email / username and password are required.'];
        }

        Session::start();
        $fails = $_SESSION['login_fails'] ?? ['count' => 0, 'at' => 0];
        if ($fails['count'] >= self::MAX_FAILED_LOGINS && time() - $fails['at'] < self::LOCKOUT_SECONDS) {
            $wait = (int)ceil((self::LOCKOUT_SECONDS - (time() - $fails['at'])) / 60);
            return ['success' => false, 'message' => "Too many failed attempts. Please try again in {$wait} minute(s) or use Forgot Password."];
        }

        $user = self::findActiveByLogin($login);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['login_fails'] = ['count' => ($fails['count'] ?? 0) + 1, 'at' => time()];
            return ['success' => false, 'message' => 'Invalid email / username or password.'];
        }
        unset($_SESSION['login_fails']);

        self::establishSession($user);
        if ($remember) {
            self::issueRememberCookie((int)$user['id']);
        }
        Database::execute("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);

        return ['success' => true, 'message' => 'Welcome back, ' . ($user['full_name'] ?: $user['username']) . '.'];
    }

    /** Active account matching a username or an email address (case-insensitive). */
    public static function findActiveByLogin(string $login): ?array {
        $login = strtolower(trim($login));
        if ($login === '') return null;
        return Database::fetchOne(
            "SELECT * FROM users WHERE is_active = 1 AND (LOWER(username) = ? OR LOWER(email) = ?) ORDER BY id LIMIT 1",
            [$login, $login]
        );
    }

    /** Ends every "keep me signed in" device for an account (after a password change). */
    public static function revokeRememberTokens(int $userId): void {
        Database::execute("DELETE FROM user_remember_tokens WHERE user_id = ?", [$userId]);
    }

    public static function logout(): void {
        Session::start();
        if (!empty($_COOKIE[self::REMEMBER_COOKIE])) {
            [$selector] = array_pad(explode(':', (string)$_COOKIE[self::REMEMBER_COOKIE], 2), 2, '');
            if ($selector !== '') {
                Database::execute("DELETE FROM user_remember_tokens WHERE selector = ?", [$selector]);
            }
        }
        self::clearRememberCookie();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /** True if a valid session exists, or a valid remember-me cookie was used to re-establish one. */
    public static function check(): bool {
        Session::start();
        if (!empty($_SESSION['user_id'])) {
            return true;
        }
        return self::loginFromRememberCookie();
    }

    public static function user(): ?array {
        Session::start();
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        return [
            'id'        => (int)$_SESSION['user_id'],
            'username'  => $_SESSION['username'] ?? '',
            'full_name' => $_SESSION['user_name'] ?? '',
            'email'     => $_SESSION['user_email'] ?? '',
            'role'      => $_SESSION['user_role'] ?? 'view_only',
        ];
    }

    public static function role(): string {
        Session::start();
        return $_SESSION['user_role'] ?? '';
    }

    public static function isAdmin(): bool {
        return self::role() === 'admin';
    }

    /** Admins and full-access accounts may create/edit/delete records; view_only may not. */
    public static function canWrite(): bool {
        return in_array(self::role(), ['admin', 'full_access'], true);
    }

    /** Call at the top of any page/action that requires a logged-in user. */
    public static function requireLogin(bool $isApi = false): void {
        if (self::check()) {
            return;
        }
        if ($isApi) {
            header('Content-Type: application/json', true, 401);
            echo json_encode(['success' => false, 'message' => 'Your session has expired. Please log in again.']);
            exit;
        }
        header('Location: index.php?page=login');
        exit;
    }

    /** Call before any data-mutating API action; view_only accounts are rejected. */
    public static function requireWrite(): void {
        if (self::canWrite()) {
            return;
        }
        header('Content-Type: application/json', true, 403);
        echo json_encode(['success' => false, 'message' => 'Your account is view-only. Ask an admin for full access to make changes.']);
        exit;
    }

    /** Call before any admin-only page/action (e.g. managing team logins). */
    public static function requireAdmin(bool $isApi = false): void {
        if (self::isAdmin()) {
            return;
        }
        if ($isApi) {
            header('Content-Type: application/json', true, 403);
            echo json_encode(['success' => false, 'message' => 'Only the owner / admin accounts can do this.']);
            exit;
        }
        header('Location: index.php?page=operations');
        exit;
    }

    private static function establishSession(array $user): void {
        Session::start();
        session_regenerate_id(true);
        $_SESSION['user_id']    = (int)$user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['user_name']  = $user['full_name'] ?: $user['username'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['user_email'] = $user['email'] ?? '';
    }

    private static function loginFromRememberCookie(): bool {
        if (empty($_COOKIE[self::REMEMBER_COOKIE])) {
            return false;
        }
        [$selector, $validator] = array_pad(explode(':', (string)$_COOKIE[self::REMEMBER_COOKIE], 2), 2, '');
        if ($selector === '' || $validator === '') {
            self::clearRememberCookie();
            return false;
        }

        $token = Database::fetchOne("SELECT * FROM user_remember_tokens WHERE selector = ? AND expires_at > NOW()", [$selector]);
        if (!$token || !hash_equals($token['validator_hash'], hash('sha256', $validator))) {
            self::clearRememberCookie();
            return false;
        }

        $user = Database::fetchOne("SELECT * FROM users WHERE id = ? AND is_active = 1", [$token['user_id']]);
        if (!$user) {
            self::clearRememberCookie();
            return false;
        }

        // Rotate the token on each use so a stolen (already-used) cookie value stops working.
        Database::execute("DELETE FROM user_remember_tokens WHERE id = ?", [$token['id']]);
        self::establishSession($user);
        self::issueRememberCookie((int)$user['id']);
        return true;
    }

    private static function issueRememberCookie(int $userId): void {
        $selector  = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(33));
        $expires   = time() + self::REMEMBER_DAYS * 86400;

        Database::execute(
            "INSERT INTO user_remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, FROM_UNIXTIME(?))",
            [$userId, $selector, hash('sha256', $validator), $expires]
        );

        setcookie(self::REMEMBER_COOKIE, $selector . ':' . $validator, [
            'expires'  => $expires,
            'path'     => '/',
            'domain'   => '',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::REMEMBER_COOKIE] = $selector . ':' . $validator;
    }

    private static function clearRememberCookie(): void {
        if (isset($_COOKIE[self::REMEMBER_COOKIE])) {
            setcookie(self::REMEMBER_COOKIE, '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'domain'   => '',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            unset($_COOKIE[self::REMEMBER_COOKIE]);
        }
    }
}
