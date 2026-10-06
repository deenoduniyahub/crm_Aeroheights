<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Session.php';
require_once __DIR__ . '/AirTicketController.php';
require_once __DIR__ . '/../services/TicketAiReader.php';

/**
 * Ticket Booking (Tickets -> Ticket Booking).
 * "Smartly book ticket": the original ticket and the travellers' passports are uploaded together and read by
 * Gemini in parallel. The ticket record (passengers, PNR, flights) is created in air_tickets exactly like the
 * Tickets tab does, each passenger gets the passport number from their own passport, and the row is flagged
 * is_booking = 1 so it also lists here with client, supplier and the PKR buy/sell rates entered by the team.
 */
class TicketBookingController {
    private const MAX_BYTES = 10485760;
    private const MAX_PASSPORT_FILES = 20;
    private const ALLOWED_MIME = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private static function actor(): string { return Session::getActor(); }

    private static function money(mixed $v): float {
        $v = str_replace([',', ' '], '', (string)$v);
        return max(0, round((float)(is_numeric($v) ? $v : 0), 2));
    }

    /** YYYY-MM-DD from the form; today when blank or invalid. */
    private static function bookingDate(mixed $v): string {
        $v = trim((string)$v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && checkdate((int)substr($v, 5, 2), (int)substr($v, 8, 2), (int)substr($v, 0, 4)) ? $v : date('Y-m-d');
    }

    private static function idOrNull(mixed $v): ?int {
        $v = (int)$v;
        return $v > 0 ? $v : null;
    }

    /** $_FILES['passports'] (multiple) as a flat list of single-file entries. */
    private static function fileList(array $f): array {
        if (!isset($f['name'])) return [];
        if (!is_array($f['name'])) return [$f];
        $out = [];
        foreach (array_keys($f['name']) as $i) {
            $out[] = ['name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        }
        return array_values(array_filter($out, static fn($x) => (int)$x['error'] !== UPLOAD_ERR_NO_FILE));
    }

    private static function checkFile(array $file, string $label): array {
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['error' => $label . ': ' . match ((int)$file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'file exceeds the server upload size limit.',
                UPLOAD_ERR_PARTIAL => 'the upload was interrupted. Please try again.',
                default => 'upload failed.',
            }];
        }
        if ((int)$file['size'] <= 0) return ['error' => $label . ': the file is empty.'];
        if ((int)$file['size'] > self::MAX_BYTES) return ['error' => $label . ': file size exceeds the 10 MB limit.'];
        if (!is_uploaded_file((string)$file['tmp_name'])) return ['error' => $label . ': invalid upload source.'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']) ?: '';
        if (!isset(self::ALLOWED_MIME[$mime])) return ['error' => $label . ': only PDF, JPG, PNG or WEBP files are supported.'];
        return ['mime' => $mime, 'ext' => self::ALLOWED_MIME[$mime], 'name' => mb_substr(basename((string)$file['name']), 0, 200)];
    }

    /**
     * Creates (or, with id, refreshes) a ticket booking from the uploaded original ticket and passports.
     * New bookings need the ticket; on an existing booking either a new ticket, more passports, or both.
     */
    public static function smartBook(array $post, array $files): array {
        $id = (int)($post['id'] ?? 0);
        $existing = $id > 0 ? AirTicketController::getById($id) : null;
        if ($id > 0 && !$existing) return ['success' => false, 'message' => 'Ticket booking not found.'];

        $ticketFile = $files['ticket'] ?? [];
        $hasTicket = (int)($ticketFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $passportFiles = self::fileList($files['passports'] ?? []);
        if (!$existing && !$hasTicket) return ['success' => false, 'message' => 'Please upload the original ticket.'];
        if (!$hasTicket && !$passportFiles) return ['success' => false, 'message' => 'Please upload the ticket or at least one passport.'];
        if (count($passportFiles) > self::MAX_PASSPORT_FILES) return ['success' => false, 'message' => 'Upload at most ' . self::MAX_PASSPORT_FILES . ' passport files at a time.'];

        $jobs = [];
        if ($hasTicket) {
            $check = self::checkFile($ticketFile, 'Ticket');
            if (isset($check['error'])) return ['success' => false, 'message' => $check['error']];
            $jobs[] = ['kind' => 'ticket', 'bytes' => (string)file_get_contents((string)$ticketFile['tmp_name']), 'mime' => $check['mime']];
        }
        $passportChecks = [];
        foreach ($passportFiles as $n => $pf) {
            $check = self::checkFile($pf, 'Passport ' . ($n + 1) . ' (' . basename((string)$pf['name']) . ')');
            if (isset($check['error'])) return ['success' => false, 'message' => $check['error']];
            $passportChecks[$n] = $check;
            $jobs[] = ['kind' => 'passport', 'bytes' => (string)file_get_contents((string)$pf['tmp_name']), 'mime' => $check['mime']];
        }

        $results = self::clientReads($post, $hasTicket, count($passportFiles))
            // Not read yet: ticket and every passport are read here at the same time, spread over the Gemini keys.
            ?? TicketAiReader::readMany($jobs);
        $warnings = [];
        if ($hasTicket) {
            $ticketRead = array_shift($results);
            if (!$ticketRead['success']) return ['success' => false, 'message' => 'Could not read the ticket: ' . $ticketRead['message']];
            $ticket = $ticketRead['ticket'];
            if (!$ticket['passengers']) return ['success' => false, 'message' => 'No passengers were found on the ticket. Please check the file.'];
            if (!$ticket['segments']) return ['success' => false, 'message' => 'No flights were found on the ticket. Please check the file.'];
            if ($existing) $ticket['family_head'] = $existing['data']['family_head'];
        } else {
            $ticket = $existing['data'];
        }

        // Passports already on file (earlier uploads) take part in the matching too.
        $storedFiles = $existing ? (json_decode((string)($existing['passport_files'] ?? ''), true) ?: []) : [];
        $newEntries = [];
        foreach ($passportFiles as $n => $pf) {
            $read = $results[$n];
            if (!$read['success']) $warnings[] = 'Passport file "' . $passportChecks[$n]['name'] . '" could not be read: ' . $read['message'];
            elseif (!$read['passports']) $warnings[] = 'No passport data page was found in "' . $passportChecks[$n]['name'] . '".';
            $newEntries[$n] = ['name' => $passportChecks[$n]['name'], 'mime' => $passportChecks[$n]['mime'], 'passports' => $read['success'] ? $read['passports'] : []];
        }
        $allPassports = [];
        foreach (array_merge($storedFiles, array_values($newEntries)) as $entry) {
            foreach ((array)($entry['passports'] ?? []) as $p) $allPassports[] = $p;
        }
        if ($allPassports) {
            [$ticket['passengers'], $matchWarnings] = self::applyPassports($ticket['passengers'], $allPassports);
            $warnings = array_merge($warnings, $matchWarnings);
        }

        // Save through the Tickets engine so the record is identical to one made in the Tickets tab.
        $saved = AirTicketController::save(['id' => $id] + $ticket, $hasTicket ? $ticketFile : []);
        if (!$saved['success']) return $saved;
        $id = (int)$saved['id'];

        $storedNow = [];
        try {
            foreach ($passportFiles as $n => $pf) {
                $path = self::storePassport($id, $pf, $passportChecks[$n]['ext']);
                $storedNow[] = $path;
                $storedFiles[] = ['path' => $path] + $newEntries[$n];
            }
            $set = ['is_booking = 1', 'passport_files = ?', 'ai_notes = ?'];
            $params = [$storedFiles ? json_encode($storedFiles, JSON_UNESCAPED_UNICODE) : null, $warnings ? mb_substr(implode("\n", $warnings), 0, 1000) : null];
            if (!$existing) {
                $set = array_merge($set, ['booking_date = ?', 'agent_id = ?', 'vendor_id = ?', 'buy_pkr = ?', 'sell_pkr = ?', 'booking_remarks = ?']);
                $params = array_merge($params, [
                    self::bookingDate($post['booking_date'] ?? ''),
                    self::idOrNull($post['agent_id'] ?? 0), self::idOrNull($post['vendor_id'] ?? 0),
                    self::money($post['buy_pkr'] ?? 0), self::money($post['sell_pkr'] ?? 0),
                    mb_substr(trim((string)($post['booking_remarks'] ?? '')), 0, 500) ?: null,
                ]);
            }
            $params[] = $id;
            Database::execute("UPDATE air_tickets SET " . implode(', ', $set) . " WHERE id = ?", $params);
        } catch (Throwable $e) {
            foreach ($storedNow as $p) @unlink(__DIR__ . '/../' . $p);
            return ['success' => false, 'message' => 'Ticket saved, but the passports could not be stored: ' . $e->getMessage(), 'id' => $id];
        }

        $paxWithPassport = count(array_filter($ticket['passengers'], static fn($p) => ($p['passport'] ?? '') !== ''));
        return [
            'success' => true,
            'id' => $id,
            'message' => ($existing ? 'Ticket booking updated' : 'Ticket booking created') . ': PNR ' . ($ticket['pnr'] ?: '—') . ', '
                . count($ticket['passengers']) . ' traveller(s), ' . $paxWithPassport . ' with passport number.',
            'warnings' => $warnings,
        ];
    }

    /**
     * The browser reads each file in its own short request (read_booking_file) to stay under the host's gateway
     * time limit, then sends the results here with the files as `reads`:
     *   {"ticket": {...} | null, "passports": [{"success": bool, "passports": [...], "message": ""}, ...]}
     * Returned in the same shape as TicketAiReader::readMany(), or null when not sent.
     */
    private static function clientReads(array $post, bool $hasTicket, int $passportCount): ?array {
        $reads = json_decode((string)($post['reads'] ?? ''), true);
        if (!is_array($reads)) return null;
        $out = [];
        if ($hasTicket) {
            if (!is_array($reads['ticket'] ?? null)) return null;
            $out[] = ['success' => true, 'ticket' => TicketAiReader::clean('ticket', $reads['ticket'])];
        }
        $list = array_values((array)($reads['passports'] ?? []));
        if (count($list) !== $passportCount) return null;
        foreach ($list as $r) {
            $out[] = !empty($r['success'])
                ? ['success' => true, 'passports' => TicketAiReader::clean('passport', (array)($r['passports'] ?? []))]
                : ['success' => false, 'message' => mb_substr((string)($r['message'] ?? 'not read'), 0, 200)];
        }
        return $out;
    }

    private static function storePassport(int $id, array $file, string $ext): string {
        $dir = __DIR__ . '/../storage/ticket_files/' . $id;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Unable to create ticket storage.');
        $name = 'passport_' . bin2hex(random_bytes(12)) . '.' . $ext;
        if (!move_uploaded_file((string)$file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Unable to store the uploaded passport.');
        return 'storage/ticket_files/' . $id . '/' . $name;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Passport <-> passenger matching
    // ---------------------------------------------------------------------------------------------------------

    private static function nameTokens(string $name): array {
        $name = strtoupper($name);
        $name = preg_replace('/\b(MR|MRS|MS|MISS|MSTR|MASTER|INF|CHD|FNU|LNU)\b/', ' ', $name);
        $tokens = preg_split('/[^A-Z]+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_map([self::class, 'canonToken'], $tokens));
    }

    /** Folds common spelling variants (MOHAMMAD/MUHAMMAD/MOHD, OO/U, EE/I) so they compare equal. */
    private static function canonToken(string $t): string {
        if (preg_match('/^(M[OU]H?A?M+[AE]D|MOHD|MD)$/', $t)) return 'MUHAMMAD';
        return str_replace(['OO', 'EE', 'PH', 'KH', 'Q'], ['U', 'I', 'F', 'K', 'K'], $t);
    }

    private static function tokenEq(string $a, string $b): bool {
        if ($a === $b) return true;
        return min(strlen($a), strlen($b)) >= 4 && levenshtein($a, $b) <= 1;
    }

    /** 0..1: how much of two names agree (word order and common spelling variants ignored). */
    public static function nameSimilarity(string $a, string $b): float {
        return self::nameScore(self::nameTokens($a), self::nameTokens($b));
    }

    private static function nameScore(array $a, array $b): float {
        if (!$a || !$b) return 0.0;
        $used = [];
        $hits = 0;
        foreach ($a as $ta) {
            foreach ($b as $j => $tb) {
                if (!isset($used[$j]) && self::tokenEq($ta, $tb)) { $used[$j] = true; $hits++; break; }
            }
        }
        return $hits / max(count($a), count($b));
    }

    /**
     * Pairs travellers with passports: by name (or by a passport number the ticket already shows), best matches
     * first; a last single traveller and passport left over are paired by elimination. The same passport
     * uploaded twice counts once. Extra keys on a passport (e.g. file / page) are kept.
     * Returns ['passports' => unique list, 'pairs' => [paxIndex => ['passport' => j, 'how' => exact|partial|elimination]],
     *          'unmatched_passports' => [j...], 'unmatched_pax' => [i...]].
     */
    public static function matchPassports(array $passengers, array $passports): array {
        $unique = [];
        foreach ($passports as $p) {
            $key = $p['passport_no'] !== '' ? $p['passport_no'] : $p['given_names'] . '|' . $p['surname'];
            $unique[$key] = $p;
        }
        $passports = array_values($unique);

        $scores = [];
        foreach ($passengers as $i => $pax) {
            $paxTokens = self::nameTokens($pax['name']);
            foreach ($passports as $j => $pp) {
                $score = self::nameScore($paxTokens, self::nameTokens($pp['given_names'] . ' ' . $pp['surname']));
                if ($pp['passport_no'] !== '' && strtoupper((string)($pax['passport'] ?? '')) === $pp['passport_no']) $score += 1;
                if ($score >= 0.5) $scores[] = [$score, $i, $j];
            }
        }
        usort($scores, static fn($x, $y) => $y[0] <=> $x[0]);

        $pairs = [];
        $ppDone = [];
        foreach ($scores as [$score, $i, $j]) {
            if (isset($pairs[$i]) || isset($ppDone[$j])) continue;
            $pairs[$i] = ['passport' => $j, 'how' => $score < 0.99 ? 'partial' : 'exact'];
            $ppDone[$j] = true;
        }
        $leftPax = array_values(array_diff(array_keys($passengers), array_keys($pairs)));
        $leftPp = array_values(array_diff(array_keys($passports), array_keys($ppDone)));
        if (count($leftPax) === 1 && count($leftPp) === 1 && $passports[$leftPp[0]]['passport_no'] !== '') {
            $pairs[$leftPax[0]] = ['passport' => $leftPp[0], 'how' => 'elimination'];
            $leftPax = $leftPp = [];
        }
        return ['passports' => $passports, 'pairs' => $pairs, 'unmatched_passports' => $leftPp, 'unmatched_pax' => $leftPax];
    }

    /**
     * Gives each passenger the passport number (and gender) of their matching passport.
     * Returns [passengers, warnings].
     */
    public static function applyPassports(array $passengers, array $passports): array {
        $m = self::matchPassports($passengers, $passports);
        $passports = $m['passports'];
        $label = static fn(array $pp): string => ($pp['passport_no'] ?: '?') . ' (' . trim($pp['given_names'] . ' ' . $pp['surname']) . ')';
        $warnings = [];
        foreach ($m['pairs'] as $i => ['passport' => $j, 'how' => $how]) {
            self::applyGender($passengers[$i], $passports[$j]);
            if ($passports[$j]['passport_no'] === '') continue;
            $passengers[$i]['passport'] = $passports[$j]['passport_no'];
            // Only part of the name agrees (e.g. the same surname in one family) — worth a second look.
            if ($how === 'partial') $warnings[] = 'Passport ' . $label($passports[$j]) . ' was matched to ' . $passengers[$i]['name'] . ' by a partial name match — please check.';
            if ($how === 'elimination') $warnings[] = 'Passport ' . $label($passports[$j]) . ' was given to ' . $passengers[$i]['name'] . ' because the names differ — please check.';
        }
        foreach ($m['unmatched_passports'] as $j) $warnings[] = 'Passport ' . $label($passports[$j]) . ' does not match any traveller on the ticket.';
        foreach ($m['unmatched_pax'] as $i) {
            if (($passengers[$i]['passport'] ?? '') === '') $warnings[] = 'No passport found for ' . $passengers[$i]['name'] . '.';
        }
        return [$passengers, $warnings];
    }

    /** Gender from the passport (used for the Family Head); a blank title becomes MR / MSTR / MISS. */
    public static function applyGender(array &$pax, array $passport): void {
        $g = $passport['gender'] ?? '';
        if (!in_array($g, ['M', 'F'], true)) return;
        $pax['gender'] = $g;
        if (($pax['title'] ?? '') !== '') return;
        $adult = ($pax['type'] ?? 'Adult') === 'Adult';
        if ($g === 'M') $pax['title'] = $adult ? 'MR' : 'MSTR';
        elseif (!$adult) $pax['title'] = 'MISS';
    }

    // ---------------------------------------------------------------------------------------------------------
    // Listing, editing, files
    // ---------------------------------------------------------------------------------------------------------

    public static function getAll(array $filters = []): array {
        $sql = "SELECT t.id, t.ticket_no, t.pnr, t.airline_code, t.airline_name, t.family_head, t.pax_count, t.route, t.dep_date, t.ret_date,
                       t.status, COALESCE(t.booking_date, DATE(t.created_at)) AS booking_date, t.agent_id, t.vendor_id, t.buy_pkr, t.sell_pkr, t.booking_remarks, t.original_path, t.passport_files,
                       t.ai_notes, t.payload, t.created_by, t.created_at, a.name AS agent_name, v.name AS vendor_name
                FROM air_tickets t
                LEFT JOIN agents a ON a.id = t.agent_id
                LEFT JOIN vendors v ON v.id = t.vendor_id
                WHERE t.deleted_at IS NULL AND t.is_booking = 1";
        $params = [];
        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= " AND (t.ticket_no LIKE ? OR t.pnr LIKE ? OR t.route LIKE ? OR t.airline_name LIKE ? OR t.payload LIKE ? OR a.name LIKE ?)";
            $params = array_merge($params, array_fill(0, 6, '%' . $search . '%'));
        }
        if (!empty($filters['agent_id'])) { $sql .= " AND t.agent_id = ?"; $params[] = (int)$filters['agent_id']; }
        if (!empty($filters['vendor_id'])) { $sql .= " AND t.vendor_id = ?"; $params[] = (int)$filters['vendor_id']; }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $k => $op) {
            $d = (string)($filters[$k] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { $sql .= " AND t.dep_date {$op} ?"; $params[] = $d; }
        }
        foreach (['booked_from' => '>=', 'booked_to' => '<='] as $k => $op) {
            $d = (string)($filters[$k] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { $sql .= " AND COALESCE(t.booking_date, DATE(t.created_at)) {$op} ?"; $params[] = $d; }
        }
        $rows = Database::fetchAll($sql . " ORDER BY COALESCE(t.booking_date, DATE(t.created_at)) DESC, t.id DESC", $params);
        foreach ($rows as &$r) {
            $data = AirTicketController::normalize(json_decode((string)$r['payload'], true) ?: []);
            $r['passengers'] = $data['passengers'];
            $r['family_head'] = $data['family_head']; // re-checked with the male-adult rule
            $r['flights'] = array_values(array_unique(array_filter(array_column($data['segments'], 'flight'))));
            $r['passport_list'] = json_decode((string)($r['passport_files'] ?? ''), true) ?: [];
            unset($r['payload'], $r['passport_files']);
        }
        return $rows;
    }

    /** Client, supplier, PKR rates and remarks (ticket details are edited in the Tickets form). */
    public static function update(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if (!AirTicketController::getById($id)) return ['success' => false, 'message' => 'Ticket booking not found.'];
        Database::execute(
            "UPDATE air_tickets SET is_booking = 1, booking_date = ?, agent_id = ?, vendor_id = ?, buy_pkr = ?, sell_pkr = ?, booking_remarks = ?, updated_by = ?, updated_at = NOW() WHERE id = ?",
            [
                self::bookingDate($data['booking_date'] ?? ''),
                self::idOrNull($data['agent_id'] ?? 0), self::idOrNull($data['vendor_id'] ?? 0),
                self::money($data['buy_pkr'] ?? 0), self::money($data['sell_pkr'] ?? 0),
                mb_substr(trim((string)($data['booking_remarks'] ?? '')), 0, 500) ?: null, self::actor(), $id,
            ]
        );
        return ['success' => true, 'message' => 'Ticket booking saved.'];
    }

    /** Streams one stored passport file (index into passport_files). */
    public static function streamPassport(int $id, int $index, bool $download): void {
        $row = Database::fetchOne("SELECT passport_files FROM air_tickets WHERE id = ? AND deleted_at IS NULL", [$id]);
        $list = $row ? (json_decode((string)($row['passport_files'] ?? ''), true) ?: []) : [];
        $entry = $list[$index] ?? null;
        $path = $entry ? __DIR__ . '/../' . ltrim((string)$entry['path'], '/') : '';
        if (!$path || !str_starts_with((string)$entry['path'], 'storage/ticket_files/' . $id . '/') || !is_file($path)) {
            http_response_code(404);
            exit('Passport file not found.');
        }
        $name = str_replace(['"', "\r", "\n"], '', (string)($entry['name'] ?? '')) ?: basename($path);
        header('Content-Type: ' . ((string)($entry['mime'] ?? '') ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public static function exportCsv(array $filters): void {
        $rows = self::getAll($filters);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ticket_bookings_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Booking', 'Booking Date', 'Travellers', 'Passport Numbers', 'PNR', 'Airline', 'Route', 'Flight No.', 'Departure', 'Return', 'Client', 'Supplier', 'Buy (PKR)', 'Sell (PKR)', 'Profit (PKR)', 'Remarks']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['ticket_no'], $r['booking_date'],
                implode(', ', array_column($r['passengers'], 'name')),
                implode(', ', array_map(static fn($p) => $p['passport'] ?: '-', $r['passengers'])),
                $r['pnr'], $r['airline_name'], $r['route'], implode(' / ', $r['flights']), $r['dep_date'], $r['ret_date'],
                $r['agent_name'], $r['vendor_name'], $r['buy_pkr'], $r['sell_pkr'], round((float)$r['sell_pkr'] - (float)$r['buy_pkr'], 2), $r['booking_remarks'],
            ]);
        }
        fclose($out);
        exit;
    }
}
