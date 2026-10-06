<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Session.php';
require_once __DIR__ . '/../config/Auth.php';
require_once __DIR__ . '/../services/Mailer.php';

/**
 * Own-account management: email-OTP password reset (forgot password, or a planned change),
 * profile details, and the email + password lock in front of the ledgers and Buy / Sell reports.
 */
class AccountController {
    private const OTP_MINUTES      = 10;
    private const OTP_MAX_ATTEMPTS = 5;
    private const OTP_RESEND_SECS  = 60;

    /**
     * Emails a 6-digit code to the account's address. The reply never says whether the
     * account exists, so the form can't be used to discover emails or usernames.
     */
    public static function requestOtp(string $login): array {
        $generic = ['success' => true, 'message' => 'If that account exists, a 6-digit code has been sent to its email address. It is valid for ' . self::OTP_MINUTES . ' minutes.'];
        $user = Auth::findActiveByLogin($login);
        if (!$user || !filter_var((string)($user['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            return $generic;
        }

        $recent = Database::fetchValue(
            "SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > (NOW() - INTERVAL ? SECOND)",
            [$user['id'], self::OTP_RESEND_SECS]
        );
        if ((int)$recent > 0) {
            return ['success' => false, 'message' => 'A code was just sent. Please wait a minute before asking for another one.'];
        }

        $code = (string)random_int(100000, 999999);
        Database::execute("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL", [$user['id']]);
        Database::execute(
            "INSERT INTO password_resets (user_id, code_hash, expires_at, request_ip) VALUES (?, ?, NOW() + INTERVAL ? MINUTE, ?)",
            [$user['id'], password_hash($code, PASSWORD_DEFAULT), self::OTP_MINUTES, substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]
        );

        $name = htmlspecialchars((string)($user['full_name'] ?: $user['username']));
        $html = '<div style="font-family:Jost,Arial,sans-serif;max-width:480px;margin:auto;border:1px solid #dce5ed;border-radius:12px;overflow:hidden">'
              . '<div style="background:#26206f;color:#fff;padding:16px 20px;font-weight:bold;font-size:16px">Aeroheights Travels &amp; Tours &middot; CRM</div>'
              . '<div style="padding:20px;color:#161a35;font-size:14px;line-height:1.5">'
              . "<p>Assalam o Alaikum {$name},</p><p>Use this code to set a new password for your CRM account:</p>"
              . '<p style="font-size:30px;font-weight:bold;letter-spacing:8px;color:#2183DF;text-align:center;margin:18px 0">' . $code . '</p>'
              . '<p>The code expires in ' . self::OTP_MINUTES . ' minutes. If you did not ask for it, ignore this email &mdash; your password stays the same.</p>'
              . '</div><div style="background:#f6f8fc;color:#8A90A8;font-size:11px;padding:10px 20px">crm.aeroheightstravels.com &middot; +92 303 5137777</div></div>';
        $text = "Aeroheights CRM password reset code: {$code}\nIt expires in " . self::OTP_MINUTES . " minutes. If you did not ask for it, ignore this email.";

        if (!Mailer::send((string)$user['email'], 'Your Aeroheights CRM code: ' . $code, $html, $text)) {
            Database::execute("DELETE FROM password_resets WHERE id = ?", [Database::lastInsertId()]);
            return ['success' => false, 'message' => 'The code could not be emailed right now. Please try again in a few minutes.'];
        }
        return $generic;
    }

    /** Checks the emailed code and sets the new password; signs every device out of that account. */
    public static function resetWithOtp(string $login, string $code, string $newPassword, string $confirm): array {
        $code = preg_replace('/\D+/', '', $code);
        if (strlen($code) !== 6) return ['success' => false, 'message' => 'Enter the 6-digit code from the email.'];
        $check = self::validateNewPassword($newPassword, $confirm);
        if ($check) return ['success' => false, 'message' => $check];

        $user = Auth::findActiveByLogin($login);
        $reset = $user ? Database::fetchOne(
            "SELECT * FROM password_resets WHERE user_id = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1",
            [$user['id']]
        ) : null;
        if (!$reset) return ['success' => false, 'message' => 'This code has expired or is not valid. Please request a new one.'];

        if ((int)$reset['attempts'] >= self::OTP_MAX_ATTEMPTS) {
            Database::execute("UPDATE password_resets SET used_at = NOW() WHERE id = ?", [$reset['id']]);
            return ['success' => false, 'message' => 'Too many wrong codes. Please request a new one.'];
        }
        if (!password_verify($code, $reset['code_hash'])) {
            Database::execute("UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?", [$reset['id']]);
            return ['success' => false, 'message' => 'Incorrect code. Please check the email and try again.'];
        }

        Database::execute("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
        Database::execute("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL", [$user['id']]);
        Auth::revokeRememberTokens((int)$user['id']);
        Session::lockFinancial();
        return ['success' => true, 'message' => 'Password updated. Please sign in with your new password.'];
    }

    /** Signed-in user updates their name / email / username (current password required). */
    public static function updateProfile(int $userId, array $data): array {
        $user = Database::fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        if (!$user) return ['success' => false, 'message' => 'Account not found.'];
        if (!password_verify((string)($data['current_password'] ?? ''), $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }

        $fullName = trim((string)($data['full_name'] ?? ''));
        $username = strtolower(trim((string)($data['username'] ?? '')));
        $email    = strtolower(trim((string)($data['email'] ?? '')));
        if (!preg_match('/^[a-z0-9_.]{3,50}$/', $username)) {
            return ['success' => false, 'message' => 'Username must be 3-50 characters: letters, numbers, dot or underscore only.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address. Password-reset codes are sent there.'];
        }
        $taken = Database::fetchOne("SELECT id FROM users WHERE (LOWER(username) = ? OR LOWER(email) = ? OR LOWER(username) = ? OR LOWER(email) = ?) AND id <> ?", [$username, $username, $email, $email, $userId]);
        if ($taken) return ['success' => false, 'message' => 'That username or email is already used by another account.'];

        Database::execute("UPDATE users SET full_name = ?, username = ?, email = ? WHERE id = ?", [$fullName ?: null, $username, $email, $userId]);
        $_SESSION['username']   = $username;
        $_SESSION['user_name']  = $fullName ?: $username;
        $_SESSION['user_email'] = $email;
        return ['success' => true, 'message' => 'Account details saved.'];
    }

    /** Email + password of an admin (owner) account unlocks the ledgers and Buy / Sell reports. */
    public static function unlockFinancial(string $login, string $password): array {
        $user = Auth::findActiveByLogin($login);
        if (!$user || $user['role'] !== 'admin' || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid owner email or password. Access denied.'];
        }
        Session::unlockFinancial();
        return ['success' => true, 'message' => 'Financial access unlocked.'];
    }

    private static function validateNewPassword(string $password, string $confirm): ?string {
        if (strlen($password) < 8) return 'New password must be at least 8 characters.';
        if ($password !== $confirm) return 'The two new passwords do not match.';
        return null;
    }
}
