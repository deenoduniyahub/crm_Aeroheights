<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

/**
 * Minimal outgoing mail for password-reset OTPs.
 * Uses SMTP when config/env.php has smtp_host (SSL on 465, STARTTLS on 587), otherwise PHP mail().
 */
class Mailer {
    public static function send(string $to, string $subject, string $html, string $text): bool {
        $from     = (string)Database::config('mail_from', 'no-reply@aeroheightstravels.com');
        $fromName = (string)Database::config('mail_from_name', 'Aeroheights CRM');
        $boundary = 'b' . bin2hex(random_bytes(12));

        $headers = [
            'From: ' . self::encode($fromName) . " <{$from}>",
            "Reply-To: {$from}",
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        ];
        $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($text))
              . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($html))
              . "--{$boundary}--\r\n";

        if (trim((string)Database::config('smtp_host', '')) !== '') {
            return self::smtp($from, $to, self::encode($subject), $headers, $body);
        }
        return @mail($to, self::encode($subject), $body, implode("\r\n", $headers), '-f' . $from);
    }

    private static function encode(string $value): string {
        return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    private static function smtp(string $from, string $to, string $subject, array $headers, string $body): bool {
        $host = (string)Database::config('smtp_host');
        $port = (int)Database::config('smtp_port', 465);
        $user = (string)Database::config('smtp_user', '');
        $pass = (string)Database::config('smtp_pass', '');

        $fp = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . "{$host}:{$port}", $errno, $errstr, 20);
        if (!$fp) {
            error_log("Mailer: SMTP connect failed: {$errstr}");
            return false;
        }
        stream_set_timeout($fp, 20);
        $read = static function () use ($fp): string {
            $out = '';
            while (($line = fgets($fp, 515)) !== false) {
                $out .= $line;
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return $out;
        };
        $cmd = static function (string $c, array $ok) use ($fp, $read): bool {
            fwrite($fp, $c . "\r\n");
            $reply = $read();
            if (!in_array((int)substr($reply, 0, 3), $ok, true)) {
                error_log('Mailer: SMTP "' . explode(' ', $c)[0] . '" failed: ' . trim($reply));
                return false;
            }
            return true;
        };

        $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
        $ok = (int)substr($read(), 0, 3) === 220 && $cmd($ehlo, [250]);
        if ($ok && $port !== 465) {
            $ok = $cmd('STARTTLS', [220])
                && stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)
                && $cmd($ehlo, [250]);
        }
        if ($ok && $user !== '') {
            $ok = $cmd('AUTH LOGIN', [334]) && $cmd(base64_encode($user), [334]) && $cmd(base64_encode($pass), [235]);
        }
        $ok = $ok && $cmd("MAIL FROM:<{$from}>", [250]) && $cmd("RCPT TO:<{$to}>", [250, 251]) && $cmd('DATA', [354]);
        if ($ok) {
            $msg = implode("\r\n", array_merge($headers, ["To: <{$to}>", "Subject: {$subject}", 'Date: ' . date('r')])) . "\r\n\r\n" . $body;
            $msg = preg_replace('/^\./m', '..', $msg); // dot-stuffing
            $ok = $cmd($msg . "\r\n.", [250]);
        }
        @fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return $ok;
    }
}
