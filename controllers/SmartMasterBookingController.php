<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/AirTicketController.php';
require_once __DIR__ . '/TicketBookingController.php';
require_once __DIR__ . '/../services/TicketAiReader.php';

/**
 * Master Bookings -> "Smart Auto Booking".
 * The browser reads every ticket and passport with Gemini (read_booking_file, one file per request) and sends
 * the results here. prepare() turns them into one draft Master Booking per traveller:
 *   - traveller list = everyone on the tickets (an arrival and a return ticket may be separate files),
 *     plus anyone whose passport was uploaded but who is on no ticket
 *   - flight_number = the flight that lands in KSA (PAK -> KSA arrival), arrival_date = the day it lands,
 *     departure_date = the day of the first flight leaving KSA after that
 *   - passport number / gender / expiry from the matching passport, and which file + page holds it
 *   - duplicates: an existing Master Booking with the same passport and arrival date
 * The user reviews the drafts; the browser then creates them through save_booking and attaches the files.
 */
class SmartMasterBookingController {
    public const KSA_AIRPORTS = ['JED', 'MED', 'RUH', 'DMM', 'TIF', 'TUU', 'AHB', 'ELQ', 'GIZ', 'YNB', 'HAS', 'ULH', 'AJF', 'EAM', 'URY', 'WAE', 'RAE', 'TUI', 'ABT', 'BHH', 'DWD', 'AQI', 'NUM', 'RSI'];

    private static function isKsa(string $code): bool { return in_array(strtoupper($code), self::KSA_AIRPORTS, true); }

    /** "AHMED KHAN" -> "Ahmed Khan" (Master Bookings keep names in this style). */
    public static function displayName(string $name): string {
        return ucwords(strtolower(trim(preg_replace('/\s+/', ' ', $name))), " -'");
    }

    /** "SV733" -> "SV-733" (the format used in Master Bookings). */
    public static function displayFlight(string $flight): string {
        $f = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $flight));
        return preg_match('/^([A-Z0-9]{2})(\d{1,4}[A-Z]?)$/', $f, $m) ? $m[1] . '-' . $m[2] : $f;
    }

    /** Under 15 days "SHORT STAY", over 29 days "LONG STAY", otherwise "21 Days". */
    public static function stayLabel(int $days): string {
        if ($days < 15) return 'SHORT STAY';
        if ($days > 29) return 'LONG STAY';
        return $days . ' Days';
    }

    private static function nameKey(string $name): string {
        $w = preg_split('/[^A-Z]+/', strtoupper($name), -1, PREG_SPLIT_NO_EMPTY);
        sort($w);
        return implode(' ', $w);
    }

    /**
     * Arrival (into KSA) and exit (out of KSA) from a traveller's flights.
     * Returns [arrivalSegment|null, exitSegment|null].
     */
    public static function ksaLegs(array $segments): array {
        usort($segments, static fn($a, $b) => strcmp(($a['dep_date'] ?? '') . ($a['dep_time'] ?? ''), ($b['dep_date'] ?? '') . ($b['dep_time'] ?? '')));
        $arrival = null;
        $exit = null;
        foreach ($segments as $n => $s) {
            if (!$arrival && self::isKsa($s['to']) && !self::isKsa($s['from'])) { $arrival = $s; continue; }
            if (self::isKsa($s['from']) && !self::isKsa($s['to'])) {
                $after = !$arrival || strcmp($s['dep_date'] . $s['dep_time'], $arrival['dep_date'] . $arrival['dep_time']) >= 0;
                if ($after && !$exit) $exit = $s;
            }
        }
        return [$arrival, $exit];
    }

    /**
     * $data = ['tickets' => [['file' => n, 'name' => filename, 'ticket' => {...}], ...],
     *          'passports' => [['file' => n, 'name' => filename, 'passports' => [...]], ...]]
     */
    public static function prepare(array $data): array {
        // 1) Everyone on the tickets, merged across files (same person on the arrival and the return ticket).
        $people = [];
        $pnrs = [];
        foreach ((array)($data['tickets'] ?? []) as $t) {
            $ticket = AirTicketController::normalize(TicketAiReader::clean('ticket', (array)($t['ticket'] ?? [])));
            $file = (int)($t['file'] ?? 0);
            if ($ticket['pnr'] !== '') $pnrs[$file] = $ticket['pnr'];
            foreach ($ticket['passengers'] as $p) {
                $k = self::nameKey($p['name']);
                $people[$k] ??= ['name' => $p['name'], 'title' => $p['title'], 'type' => $p['type'], 'gender' => $p['gender'], 'passport' => $p['passport'], 'segments' => [], 'files' => []];
                foreach (['title', 'gender', 'passport'] as $f) if ($people[$k][$f] === '' && $p[$f] !== '') $people[$k][$f] = $p[$f];
                foreach ($ticket['segments'] as $s) $people[$k]['segments'][$s['flight'] . '|' . $s['dep_date']] = $s + ['file' => $file];
                $people[$k]['files'][$file] = true;
            }
        }
        $people = array_values($people);

        // 2) Passports (with the file and page they came from), matched to the travellers.
        $passports = [];
        foreach ((array)($data['passports'] ?? []) as $pf) {
            foreach (TicketAiReader::clean('passport', (array)($pf['passports'] ?? [])) as $pp) {
                $passports[] = $pp + ['file' => (int)($pf['file'] ?? 0)];
            }
        }
        $m = TicketBookingController::matchPassports($people, $passports);
        $passports = $m['passports'];

        $rows = [];
        foreach ($people as $i => $p) {
            $pair = $m['pairs'][$i] ?? null;
            $pp = $pair ? $passports[$pair['passport']] : null;
            [$arr, $exit] = self::ksaLegs(array_values($p['segments']));
            $rows[] = self::row($p['name'], $p, $pp, $pair['how'] ?? null, $arr, $exit, $pnrs);
        }
        // Passports of people who are on none of the tickets still become bookings (without flight details).
        foreach ($m['unmatched_passports'] as $j) {
            $pp = $passports[$j];
            $rows[] = self::row(trim($pp['given_names'] . ' ' . $pp['surname']), ['type' => 'Adult', 'gender' => $pp['gender'], 'passport' => '', 'files' => []], $pp, 'no_ticket', null, null, $pnrs);
        }

        self::markDuplicates($rows);
        $summary = [
            'travellers' => count($rows),
            'with_passport' => count(array_filter($rows, static fn($r) => $r['passport_number'] !== '')),
            'duplicates' => count(array_filter($rows, static fn($r) => $r['duplicate'])),
            'pnrs' => array_values(array_unique(array_values($pnrs))),
        ];
        return ['success' => true, 'rows' => $rows, 'summary' => $summary];
    }

    private static function row(string $ticketName, array $p, ?array $pp, ?string $how, ?array $arr, ?array $exit, array $pnrs): array {
        $passportName = $pp ? trim($pp['given_names'] . ' ' . $pp['surname']) : '';
        $arrivalDate = $arr ? ($arr['arr_date'] ?: $arr['dep_date']) : '';
        $departureDate = $exit['dep_date'] ?? '';
        $stay = '';
        if ($arrivalDate && $departureDate && $departureDate > $arrivalDate) {
            $stay = self::stayLabel((new DateTimeImmutable($arrivalDate))->diff(new DateTimeImmutable($departureDate))->days);
        }
        $issues = [];
        if (!$pp) $issues[] = 'No passport uploaded for this traveller';
        elseif ($how === 'partial') $issues[] = 'Passport name only partly matches the ticket';
        elseif ($how === 'elimination') $issues[] = 'Passport given by elimination — names differ';
        if ($how === 'no_ticket') $issues[] = 'Not on any uploaded ticket — add flight details';
        if ($how !== 'no_ticket' && !$arr) $issues[] = 'No flight into Saudi Arabia found on the ticket';
        $past = date('Y-m-d', strtotime('-7 days'));
        foreach (['Arrival' => $arrivalDate, 'Departure' => $departureDate] as $label => $d) {
            if ($d !== '' && $d < $past) $issues[] = $label . ' date ' . $d . ' is in the past — check the year';
        }
        if ($pp && $pp['expiry'] !== '') {
            $limit = (new DateTimeImmutable($arrivalDate ?: 'today'))->modify('+6 months')->format('Y-m-d');
            if ($pp['expiry'] < ($arrivalDate ?: date('Y-m-d'))) $issues[] = 'Passport EXPIRED on ' . $pp['expiry'];
            elseif ($pp['expiry'] < $limit) $issues[] = 'Passport expires ' . $pp['expiry'] . ' (less than 6 months after arrival)';
        }
        $ticketFile = $arr['file'] ?? (array_key_first($p['files'] ?? []) ?? null);
        $returnFile = isset($exit['file']) && $exit['file'] !== $ticketFile ? $exit['file'] : null;

        return [
            'passenger_name' => self::displayName($passportName ?: $ticketName),
            'ticket_name' => $ticketName,
            'passport_number' => $pp['passport_no'] ?? strtoupper((string)($p['passport'] ?? '')),
            'gender' => ($pp['gender'] ?? '') ?: ($p['gender'] ?? ''),
            'type' => $p['type'] ?? 'Adult',
            'dob' => $pp['dob'] ?? '',
            'expiry' => $pp['expiry'] ?? '',
            'nationality' => $pp['nationality'] ?? '',
            'flight_number' => $arr ? self::displayFlight($arr['flight']) : '',
            'arrival_route' => $arr ? $arr['from'] . '-' . $arr['to'] : '',
            'arrival_date' => $arrivalDate,
            'return_flight' => $exit ? self::displayFlight($exit['flight']) : '',
            'departure_date' => $departureDate,
            'stay_days' => $stay,
            'pnr' => $ticketFile !== null ? ($pnrs[$ticketFile] ?? '') : '',
            'ticket_file' => $ticketFile,
            'return_file' => $returnFile,
            'passport_file' => $pp['file'] ?? null,
            'passport_page' => $pp['page'] ?? 1,
            'match' => $how ?? 'none',
            'issues' => $issues,
            'duplicate' => null,
            'earlier' => [],
        ];
    }

    /** Same passport + same arrival date already in Master Bookings = duplicate; other bookings are just listed. */
    private static function markDuplicates(array &$rows): void {
        $numbers = array_values(array_unique(array_filter(array_column($rows, 'passport_number'))));
        if (!$numbers) return;
        $existing = Database::fetchAll(
            "SELECT id, booking_code, passport_number, arrival_date FROM master_bookings
             WHERE deleted_at IS NULL AND UPPER(passport_number) IN (" . implode(',', array_fill(0, count($numbers), '?')) . ") ORDER BY id DESC",
            $numbers
        );
        foreach ($rows as &$r) {
            foreach ($existing as $e) {
                if (strtoupper((string)$e['passport_number']) !== $r['passport_number']) continue;
                if (!$r['arrival_date'] || !$e['arrival_date'] || $e['arrival_date'] === $r['arrival_date']) {
                    $r['duplicate'] ??= ['id' => (int)$e['id'], 'code' => $e['booking_code'], 'arrival_date' => $e['arrival_date']];
                } else {
                    $r['earlier'][] = ['code' => $e['booking_code'], 'arrival_date' => $e['arrival_date']];
                }
            }
        }
    }
}
