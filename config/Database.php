<?php
declare(strict_types=1);

/**
 * Database Singleton Connection & Query Helper
 * Handles secure PDO connections with prepared statements, error trapping, and transactions.
 */
class Database {
    private static ?PDO $instance = null;

    /** Connection settings come from config/env.php (git-ignored; differs between local and live). */
    private static function env(): array {
        static $env = null;
        if ($env === null) {
            $file = __DIR__ . '/env.php';
            $env = is_file($file) ? (array)require $file : [];
        }
        return $env;
    }

    private function __construct() {}
    private function __clone() {}

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                (string)(self::env()['db_host'] ?? 'localhost'),
                (int)(self::env()['db_port'] ?? 3306),
                (string)(self::env()['db_name'] ?? 'aeroheights_crm')
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, (string)(self::env()['db_user'] ?? 'root'), (string)(self::env()['db_pass'] ?? ''), $options);
            } catch (PDOException $e) {
                if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
                    header('Content-Type: application/json', true, 500);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Database connection failed: ' . $e->getMessage()
                    ]);
                } else {
                    die('<div style="font-family:Jost,Arial,sans-serif;padding:20px;color:#E0475B;background:#FDF0F2;border:1px solid #E0475B;border-radius:8px;">' .
                        '<h3 style="margin-top:0;">Database Connection Error</h3>' .
                        '<p>' . htmlspecialchars($e->getMessage()) . '</p>' .
                        '</div>');
                }
                exit;
            }
        }
        return self::$instance;
    }

    /** Non-database values from env.php (mail settings, etc.). */
    public static function config(string $key, mixed $default = null): mixed {
        return self::env()[$key] ?? $default;
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetchOne(string $sql, array $params = []): ?array {
        $res = self::query($sql, $params)->fetch();
        return $res === false ? null : $res;
    }

    public static function fetchValue(string $sql, array $params = []): mixed {
        return self::query($sql, $params)->fetchColumn();
    }

    public static function execute(string $sql, array $params = []): bool {
        self::query($sql, $params);
        return true;
    }

    public static function lastInsertId(): int {
        return (int)self::getConnection()->lastInsertId();
    }

    public static function beginTransaction(): bool {
        return self::getConnection()->beginTransaction();
    }

    public static function commit(): bool {
        return self::getConnection()->commit();
    }

    public static function rollBack(): bool {
        if (self::getConnection()->inTransaction()) {
            return self::getConnection()->rollBack();
        }
        return false;
    }
}

