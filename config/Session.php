<?php
declare(strict_types=1);

/**
 * Session Security, CSRF Tokens & Financial Lock State Manager
 */
class Session {
    private const CSRF_TOKEN_KEY = '_csrf_token';
    private const FINANCIAL_LOCK_KEY = 'financial_unlocked_at';
    private const PIN_TIMEOUT_SECONDS = 1800; // 30-minute session inactivity timeout

    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            $cookieParams = [
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax'
            ];
            session_set_cookie_params($cookieParams);
            session_start();
        }

        if (!isset($_SESSION[self::CSRF_TOKEN_KEY])) {
            $_SESSION[self::CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
        }
    }

    public static function getCsrfToken(): string {
        self::start();
        return $_SESSION[self::CSRF_TOKEN_KEY] ?? '';
    }

    public static function validateCsrfToken(?string $token): bool {
        self::start();
        if (empty($token) || empty($_SESSION[self::CSRF_TOKEN_KEY])) {
            return false;
        }
        return hash_equals($_SESSION[self::CSRF_TOKEN_KEY], $token);
    }

    public static function isFinancialUnlocked(): bool {
        self::start();
        if (!isset($_SESSION[self::FINANCIAL_LOCK_KEY])) {
            return false;
        }
        if (time() - (int)$_SESSION[self::FINANCIAL_LOCK_KEY] > self::PIN_TIMEOUT_SECONDS) {
            self::lockFinancial();
            return false;
        }
        $_SESSION[self::FINANCIAL_LOCK_KEY] = time(); // Auto-renew activity window
        return true;
    }

    public static function unlockFinancial(): void {
        self::start();
        $_SESSION[self::FINANCIAL_LOCK_KEY] = time();
    }

    public static function lockFinancial(): void {
        self::start();
        unset($_SESSION[self::FINANCIAL_LOCK_KEY]);
    }


    public static function getActor(): string {
        self::start();
        $actor = $_SESSION['user_name'] ?? $_SESSION['username'] ?? $_SESSION['user_id'] ?? 'system';
        $actor = trim((string)$actor);
        return $actor !== '' ? substr($actor, 0, 100) : 'system';
    }

    public static function setFlash(string $type, string $message): void {
        self::start();
        $_SESSION['_flash'] = [
            'type'    => $type, // 'success', 'error', 'info', 'warning'
            'message' => $message
        ];
    }

    public static function getFlash(): ?array {
        self::start();
        if (isset($_SESSION['_flash'])) {
            $flash = $_SESSION['_flash'];
            unset($_SESSION['_flash']);
            return $flash;
        }
        return null;
    }
}

