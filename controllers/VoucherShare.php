<?php
declare(strict_types=1);

/**
 * Signed public links for printed vouchers (the QR code on the voucher header).
 *
 * A link looks like https://aeroheightstravels.com/voucher.php?t=v16-<signature>. The signature is an
 * HMAC of the voucher type + id with a server-only secret, so ids can't be guessed or changed to
 * view someone else's voucher. The page lives on the main website and never links to the software.
 */
class VoucherShare {
    public const PUBLIC_BASE_URL = 'https://aeroheightstravels.com/voucher.php';

    /** v = Build Hotel Voucher, b = Only Hotel Booking sale voucher */
    private const TYPES = ['v', 'b'];
    private const SIG_LENGTH = 22; // 22 base64url chars = 132 bits

    /** Secret key kept outside the web root (~/domains/aeroheightstravels.com on live); created on first use. */
    private static function secret(): string {
        static $secret = null;
        if ($secret !== null) return $secret;

        $file = dirname(__DIR__, 3) . '/aero_voucher_share.key';
        $key = is_readable($file) ? trim((string)file_get_contents($file)) : '';
        if (strlen($key) < 32) {
            $key = bin2hex(random_bytes(32));
            if (@file_put_contents($file, $key, LOCK_EX) === false) {
                throw new RuntimeException('Voucher share key could not be created.');
            }
            @chmod($file, 0600);
        }
        return $secret = $key;
    }

    private static function sign(string $type, int $id): string {
        $raw = hash_hmac('sha256', "aero-voucher:{$type}:{$id}", self::secret(), true);
        return substr(rtrim(strtr(base64_encode($raw), '+/', '-_'), '='), 0, self::SIG_LENGTH);
    }

    public static function token(string $type, int $id): string {
        if (!in_array($type, self::TYPES, true) || $id <= 0) throw new InvalidArgumentException('Invalid voucher reference.');
        return $type . $id . '-' . self::sign($type, $id);
    }

    /** Public URL for the QR code, or '' if the key is unavailable (the QR is then simply not shown). */
    public static function url(string $type, int $id): string {
        try {
            return self::PUBLIC_BASE_URL . '?t=' . self::token($type, $id);
        } catch (Throwable $e) {
            return '';
        }
    }

    /** Returns ['type' => 'v'|'b', 'id' => int] for a valid token, otherwise null. */
    public static function verify(string $token): ?array {
        if (!preg_match('/^([vb])([1-9]\d{0,9})-([A-Za-z0-9_-]{' . self::SIG_LENGTH . '})$/', $token, $m)) return null;
        $id = (int)$m[2];
        try {
            if (!hash_equals(self::sign($m[1], $id), $m[3])) return null;
        } catch (Throwable $e) {
            return null;
        }
        return ['type' => $m[1], 'id' => $id];
    }

    /** Masks a passport number for the public page: AB1234567 -> *****4567 */
    public static function maskPassport(?string $passport): string {
        $p = trim((string)$passport);
        if ($p === '') return '';
        return strlen($p) <= 4 ? str_repeat('*', strlen($p)) : str_repeat('*', strlen($p) - 4) . substr($p, -4);
    }
}
