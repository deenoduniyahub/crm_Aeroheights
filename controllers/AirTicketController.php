<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Session.php';

/**
 * Customized Air Tickets.
 * The original airline / portal ticket (PDF or image) is read in the browser (assets/js/ticket_reader.js),
 * reviewed by the user, then saved here together with the original file. The branded ticket is rendered
 * from `payload` by views/tickets/print.php. Prices are never stored or shown.
 */
class AirTicketController {
    private const MAX_BYTES = 10485760;
    private const ALLOWED_MIME = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public const AIRLINES = [
        'SV' => 'Saudia', 'PK' => 'PIA', 'PA' => 'Airblue', 'PF' => 'AirSial', 'FZ' => 'flydubai', '9P' => 'Fly Jinnah',
        'ER' => 'Serene Air', 'XY' => 'flynas', 'F3' => 'flyadeal', 'OV' => 'SalamAir', 'G9' => 'Air Arabia', '3L' => 'Air Arabia Abu Dhabi',
        'E5' => 'Air Arabia Egypt', 'EK' => 'Emirates', 'QR' => 'Qatar Airways', 'EY' => 'Etihad Airways', 'GF' => 'Gulf Air',
        'WY' => 'Oman Air', 'KU' => 'Kuwait Airways', 'J9' => 'Jazeera Airways', 'TK' => 'Turkish Airlines', 'PC' => 'Pegasus Airlines',
        'MS' => 'EgyptAir', 'RJ' => 'Royal Jordanian', 'ME' => 'Middle East Airlines', 'IA' => 'Iraqi Airways', 'UL' => 'SriLankan Airlines',
        'BG' => 'Biman Bangladesh', 'TG' => 'Thai Airways', 'MH' => 'Malaysia Airlines', 'SQ' => 'Singapore Airlines', 'BA' => 'British Airways',
        'VS' => 'Virgin Atlantic', 'LH' => 'Lufthansa', 'AF' => 'Air France', 'KL' => 'KLM', 'LX' => 'Swiss', 'OS' => 'Austrian Airlines',
        'AZ' => 'ITA Airways', 'ET' => 'Ethiopian Airlines', 'KQ' => 'Kenya Airways', 'AI' => 'Air India', '6E' => 'IndiGo',
        'MU' => 'China Eastern', 'CZ' => 'China Southern', 'CA' => 'Air China', 'NE' => 'Nesma Airlines',
        'RQ' => 'Kam Air', 'FG' => 'Ariana Afghan', 'HY' => 'Uzbekistan Airways', 'KC' => 'Air Astana', 'AT' => 'Royal Air Maroc', 'TU' => 'Tunisair',
    ];

    public const AIRPORT_CITIES = [
        'LHE' => 'Lahore', 'KHI' => 'Karachi', 'ISB' => 'Islamabad', 'PEW' => 'Peshawar', 'MUX' => 'Multan', 'SKT' => 'Sialkot',
        'LYP' => 'Faisalabad', 'UET' => 'Quetta', 'GWD' => 'Gwadar', 'RYK' => 'Rahim Yar Khan', 'SKZ' => 'Sukkur', 'DEA' => 'D.G. Khan',
        'BHV' => 'Bahawalpur', 'JED' => 'Jeddah', 'MED' => 'Madinah', 'RUH' => 'Riyadh', 'DMM' => 'Dammam', 'TIF' => 'Taif',
        'TUU' => 'Tabuk', 'AHB' => 'Abha', 'ELQ' => 'Qassim', 'GIZ' => 'Jazan', 'YNB' => 'Yanbu', 'HAS' => 'Hail', 'ULH' => 'AlUla',
        'DXB' => 'Dubai', 'DWC' => 'Dubai (DWC)', 'SHJ' => 'Sharjah', 'AUH' => 'Abu Dhabi', 'RKT' => 'Ras Al Khaimah', 'MCT' => 'Muscat',
        'SLL' => 'Salalah', 'DOH' => 'Doha', 'BAH' => 'Bahrain', 'KWI' => 'Kuwait', 'IST' => 'Istanbul', 'SAW' => 'Istanbul (SAW)',
        'CAI' => 'Cairo', 'AMM' => 'Amman', 'BEY' => 'Beirut', 'BGW' => 'Baghdad', 'NJF' => 'Najaf', 'KBL' => 'Kabul', 'DEL' => 'Delhi',
        'BOM' => 'Mumbai', 'DAC' => 'Dhaka', 'CMB' => 'Colombo', 'KUL' => 'Kuala Lumpur', 'BKK' => 'Bangkok', 'SIN' => 'Singapore',
        'CGK' => 'Jakarta', 'TAS' => 'Tashkent', 'ALA' => 'Almaty', 'LHR' => 'London', 'LGW' => 'London (Gatwick)', 'MAN' => 'Manchester',
        'BHX' => 'Birmingham', 'GLA' => 'Glasgow', 'MXP' => 'Milan', 'FCO' => 'Rome', 'BCN' => 'Barcelona', 'CDG' => 'Paris',
        'FRA' => 'Frankfurt', 'MUC' => 'Munich', 'OSL' => 'Oslo', 'CPH' => 'Copenhagen', 'ARN' => 'Stockholm', 'BRU' => 'Brussels',
        'ZRH' => 'Zurich', 'VIE' => 'Vienna', 'ATH' => 'Athens', 'JFK' => 'New York', 'IAD' => 'Washington', 'ORD' => 'Chicago', 'YYZ' => 'Toronto',
    ];

    public const STATUSES = ['Confirmed', 'Partially Confirmed', 'On Request', 'On Hold', 'Cancelled'];
    public const PAX_TYPES = ['Adult', 'Child', 'Infant'];
    public const TITLES = ['MR', 'MRS', 'MS', 'MISS', 'MSTR', 'INF'];

    private static function actor(): string { return Session::getActor(); }

    private static function str($value, int $max = 255): string {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($value ?? ''))), 0, $max);
    }

    private static function date($value): string {
        $value = trim((string)($value ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : '';
    }

    private static function time($value): string {
        $value = trim((string)($value ?? ''));
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : '';
    }

    /** Logo file for an airline code (assets/img/airlines/<CODE>.svg|png|webp|jpg), or '' when we only have the name. */
    public static function logoFor(string $code): string {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code));
        if ($code === '') return '';
        foreach (['svg', 'png', 'webp', 'jpg'] as $ext) {
            $rel = 'assets/img/airlines/' . $code . '.' . $ext;
            if (is_file(__DIR__ . '/../' . $rel)) return $rel;
        }
        return '';
    }

    public static function cityFor(string $code): string {
        return self::AIRPORT_CITIES[strtoupper($code)] ?? '';
    }

    /** Canonical ticket shape shared by the form, the DB payload and the print view. */
    public static function normalize(array $d): array {
        $passengers = [];
        foreach ((array)($d['passengers'] ?? []) as $p) {
            if (!is_array($p)) continue;
            $name = strtoupper(self::str($p['name'] ?? '', 120));
            if ($name === '') continue;
            $title = strtoupper(self::str($p['title'] ?? '', 6));
            $type = self::str($p['type'] ?? 'Adult', 10);
            $title = in_array($title, self::TITLES, true) ? $title : '';
            // Gender: from the passport scan when known, otherwise from the title.
            $gender = strtoupper(self::str($p['gender'] ?? '', 1));
            if (!in_array($gender, ['M', 'F'], true)) $gender = match ($title) { 'MR', 'MSTR' => 'M', 'MRS', 'MS', 'MISS' => 'F', default => '' };
            $passengers[] = [
                'title' => $title,
                'name' => $name,
                'type' => in_array($type, self::PAX_TYPES, true) ? $type : 'Adult',
                'gender' => $gender,
                'passport' => strtoupper(self::str($p['passport'] ?? '', 20)),
                'ticket_no' => preg_replace('/[^0-9A-Z\-]/', '', strtoupper(self::str($p['ticket_no'] ?? '', 20))),
            ];
        }

        $segments = [];
        foreach ((array)($d['segments'] ?? []) as $s) {
            if (!is_array($s)) continue;
            $from = strtoupper(preg_replace('/[^A-Z]/i', '', self::str($s['from'] ?? '', 3)));
            $to = strtoupper(preg_replace('/[^A-Z]/i', '', self::str($s['to'] ?? '', 3)));
            $flight = strtoupper(preg_replace('/[^A-Z0-9]/i', '', self::str($s['flight'] ?? '', 10)));
            if ($flight === '' && $from === '' && $to === '') continue;
            $segments[] = [
                'flight' => $flight,
                'from' => $from,
                'to' => $to,
                'from_city' => self::str($s['from_city'] ?? '', 60) ?: self::cityFor($from),
                'to_city' => self::str($s['to_city'] ?? '', 60) ?: self::cityFor($to),
                'dep_date' => self::date($s['dep_date'] ?? ''),
                'dep_time' => self::time($s['dep_time'] ?? ''),
                'arr_date' => self::date($s['arr_date'] ?? ''),
                'arr_time' => self::time($s['arr_time'] ?? ''),
                'from_terminal' => self::str($s['from_terminal'] ?? '', 20),
                'to_terminal' => self::str($s['to_terminal'] ?? '', 20),
                'baggage' => self::str($s['baggage'] ?? '', 40),
                'duration' => self::str($s['duration'] ?? '', 20),
            ];
        }
        usort($segments, static fn($a, $b) => strcmp($a['dep_date'] . $a['dep_time'], $b['dep_date'] . $b['dep_time']));

        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', self::str($d['airline_code'] ?? '', 3)));
        if ($code === '' && $segments) $code = substr($segments[0]['flight'], 0, 2);
        $airlineName = self::str($d['airline_name'] ?? '', 80) ?: (self::AIRLINES[$code] ?? '');

        $familyHead = self::pickFamilyHead($passengers, strtoupper(self::str($d['family_head'] ?? '', 120)));

        $status = self::str($d['status'] ?? 'Confirmed', 30);
        return [
            'pnr' => strtoupper(preg_replace('/[^A-Z0-9\/]/i', '', self::str($d['pnr'] ?? '', 20))),
            'airline_code' => $code,
            'airline_name' => $airlineName,
            'status' => in_array($status, self::STATUSES, true) ? $status : 'Confirmed',
            'cabin' => self::str($d['cabin'] ?? 'Economy', 40) ?: 'Economy',
            'cabin_baggage' => self::str($d['cabin_baggage'] ?? '7 KG', 40),
            'issue_date' => self::date($d['issue_date'] ?? '') ?: date('Y-m-d'),
            'family_head' => $familyHead,
            'passengers' => $passengers,
            'segments' => $segments,
            'notes' => self::str($d['notes'] ?? '', 600),
        ];
    }

    /**
     * The Family Head is always a male adult: the chosen one when he is, otherwise the first male adult.
     * Only when no traveller is known to be a male adult: an adult not known to be female, then any adult.
     */
    public static function pickFamilyHead(array $passengers, string $wanted): string {
        $adult = static fn($p) => $p['type'] === 'Adult' && !in_array($p['title'], ['MSTR', 'INF', 'MISS'], true);
        $tiers = [
            static fn($p) => $adult($p) && $p['gender'] === 'M',
            static fn($p) => $adult($p) && $p['gender'] !== 'F',
            $adult,
            static fn($p) => true,
        ];
        foreach ($tiers as $ok) {
            $fit = array_values(array_filter($passengers, $ok));
            if (!$fit) continue;
            foreach ($fit as $p) if ($p['name'] === $wanted) return $wanted;
            return $fit[0]['name'];
        }
        return '';
    }

    /** "LHE-JED-LHE" style route from the segment chain. */
    public static function routeOf(array $segments): string {
        $route = [];
        foreach ($segments as $s) {
            if ($s['from'] !== '' && end($route) !== $s['from']) $route[] = $s['from'];
            if ($s['to'] !== '') $route[] = $s['to'];
        }
        return implode('-', $route);
    }

    /**
     * Groups segments into journeys: a new journey starts when the next flight doesn't continue from the
     * previous arrival airport or leaves more than 24h later. Used for "Departure" / "Return" headings.
     */
    public static function journeys(array $segments): array {
        $groups = [];
        foreach ($segments as $s) {
            $last = $groups ? end($groups[count($groups) - 1]) : null;
            $connects = false;
            if ($last && $last['to'] === $s['from'] && $last['arr_date'] && $s['dep_date']) {
                $gap = strtotime($s['dep_date'] . ' ' . ($s['dep_time'] ?: '12:00')) - strtotime($last['arr_date'] . ' ' . ($last['arr_time'] ?: '12:00'));
                $connects = $gap >= 0 && $gap <= 86400;
            }
            if ($connects) $groups[count($groups) - 1][] = $s;
            else $groups[] = [$s];
        }
        return $groups;
    }

    private static function columns(array $t): array {
        $segments = $t['segments'];
        $journeys = self::journeys($segments);
        $depDate = $segments[0]['dep_date'] ?? '';
        $retDate = count($journeys) > 1 ? ($journeys[count($journeys) - 1][0]['dep_date'] ?? '') : '';
        return [
            $t['pnr'], $t['airline_code'], $t['airline_name'], $t['family_head'], count($t['passengers']),
            self::routeOf($segments), $depDate ?: null, $retDate ?: null, $t['status'],
        ];
    }

    public static function save(array $data, array $file): array {
        $id = (int)($data['id'] ?? 0);
        $t = self::normalize($data);
        if (!$t['passengers']) return ['success' => false, 'message' => 'Add at least one passenger.'];
        if (!$t['segments']) return ['success' => false, 'message' => 'Add at least one flight.'];

        $hasFile = ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $upload = null;
        if ($hasFile) {
            $upload = self::checkUpload($file);
            if (isset($upload['error'])) return ['success' => false, 'message' => $upload['error']];
        }

        $actor = self::actor();
        $existing = null;
        if ($id > 0) {
            $existing = self::getById($id);
            if (!$existing) return ['success' => false, 'message' => 'Ticket not found.'];
            $t['passengers'] = self::keepPassports($t['passengers'], $existing);
            $t['family_head'] = self::pickFamilyHead($t['passengers'], $t['family_head']);
        }

        Database::beginTransaction();
        $stored = null;
        try {
            if ($existing) {
                Database::execute(
                    "UPDATE air_tickets SET pnr = ?, airline_code = ?, airline_name = ?, family_head = ?, pax_count = ?, route = ?, dep_date = ?, ret_date = ?, status = ?,
                            payload = ?, updated_by = ?, updated_at = NOW()
                     WHERE id = ?",
                    array_merge(self::columns($t), [json_encode($t, JSON_UNESCAPED_UNICODE), $actor, $id])
                );
            } else {
                Database::execute(
                    "INSERT INTO air_tickets (ticket_no, pnr, airline_code, airline_name, family_head, pax_count, route, dep_date, ret_date, status, payload, created_by, created_at, updated_by, updated_at)
                     VALUES ('', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())",
                    array_merge(self::columns($t), [json_encode($t, JSON_UNESCAPED_UNICODE), $actor, $actor])
                );
                $id = Database::lastInsertId();
                Database::execute("UPDATE air_tickets SET ticket_no = ? WHERE id = ?", ['TK-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT), $id]);
            }

            if ($upload) {
                $stored = self::storeUpload($id, $file, $upload);
                Database::execute(
                    "UPDATE air_tickets SET original_filename = ?, original_path = ?, original_mime = ? WHERE id = ?",
                    [$upload['name'], $stored, $upload['mime'], $id]
                );
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            if ($stored) @unlink(__DIR__ . '/../' . $stored);
            return ['success' => false, 'message' => 'Unable to save ticket: ' . $e->getMessage()];
        }

        // The replaced original is only removed once the new one is safely recorded.
        if ($stored && $existing && !empty($existing['original_path']) && $existing['original_path'] !== $stored) {
            @unlink(__DIR__ . '/../' . ltrim((string)$existing['original_path'], '/'));
        }
        return ['success' => true, 'message' => $existing ? 'Ticket updated.' : 'Ticket customized and saved.', 'id' => $id];
    }

    /**
     * Airline tickets rarely print passport numbers, so re-reading the ticket (or replacing its file) would blank
     * them. A traveller left without a number keeps the one saved before (same name), and on Ticket Bookings the
     * stored passport scans fill in whoever is still missing.
     */
    private static function keepPassports(array $passengers, array $existing): array {
        $key = static fn(string $n): string => implode(' ', (function (array $w) { sort($w); return $w; })(preg_split('/[^A-Z]+/', strtoupper($n), -1, PREG_SPLIT_NO_EMPTY)));
        $old = [];
        $oldGender = [];
        foreach ($existing['data']['passengers'] as $p) {
            if ($p['passport'] !== '') $old[$key($p['name'])] = $p['passport'];
            if ($p['gender'] !== '') $oldGender[$key($p['name'])] = $p['gender'];
        }
        $missing = [];
        foreach ($passengers as $i => $p) {
            if ($p['gender'] === '' && isset($oldGender[$key($p['name'])])) $passengers[$i]['gender'] = $oldGender[$key($p['name'])];
            if ($p['passport'] !== '') continue;
            if (isset($old[$key($p['name'])])) $passengers[$i]['passport'] = $old[$key($p['name'])];
            else $missing[$i] = $p;
        }
        $scans = [];
        foreach ((array)json_decode((string)($existing['passport_files'] ?? ''), true) as $f) {
            foreach ((array)($f['passports'] ?? []) as $pp) $scans[] = $pp;
        }
        if ($missing && $scans) {
            require_once __DIR__ . '/TicketBookingController.php';
            [$filled] = TicketBookingController::applyPassports(array_values($missing), $scans);
            foreach (array_keys($missing) as $n => $i) {
                $passengers[$i]['passport'] = $filled[$n]['passport'];
                if ($passengers[$i]['gender'] === '') $passengers[$i]['gender'] = $filled[$n]['gender'] ?? '';
            }
        }
        return $passengers;
    }

    private static function checkUpload(array $file): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['error' => match ((int)$file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds the server upload size limit.',
                UPLOAD_ERR_PARTIAL => 'The file upload was interrupted. Please try again.',
                default => 'File upload failed.',
            }];
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) return ['error' => 'The uploaded file is empty.'];
        if ($size > self::MAX_BYTES) return ['error' => 'File size exceeds the 10 MB limit.'];
        if (!is_uploaded_file((string)$file['tmp_name'])) return ['error' => 'Invalid upload source.'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']) ?: '';
        if (!isset(self::ALLOWED_MIME[$mime])) return ['error' => 'Only PDF, JPG, PNG or WEBP tickets are supported.'];
        return ['mime' => $mime, 'ext' => self::ALLOWED_MIME[$mime], 'name' => mb_substr(basename((string)($file['name'] ?? 'ticket')), 0, 200)];
    }

    private static function storeUpload(int $id, array $file, array $upload): string {
        $dir = __DIR__ . '/../storage/ticket_files/' . $id;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Unable to create ticket storage.');
        $name = 'original_' . bin2hex(random_bytes(12)) . '.' . $upload['ext'];
        if (!move_uploaded_file((string)$file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Unable to store the uploaded ticket.');
        return 'storage/ticket_files/' . $id . '/' . $name;
    }

    public static function getAll(string $search = ''): array {
        $sql = "SELECT id, ticket_no, pnr, airline_code, airline_name, family_head, pax_count, route, dep_date, ret_date, status, is_booking,
                       original_filename, original_path, created_by, created_at, updated_at
                FROM air_tickets WHERE deleted_at IS NULL";
        $params = [];
        if ($search !== '') {
            $sql .= " AND (ticket_no LIKE ? OR pnr LIKE ? OR family_head LIKE ? OR airline_name LIKE ? OR route LIKE ? OR payload LIKE ?)";
            $params = array_fill(0, 6, '%' . $search . '%');
        }
        return Database::fetchAll($sql . " ORDER BY id DESC", $params);
    }

    public static function getById(int $id): ?array {
        $row = Database::fetchOne("SELECT * FROM air_tickets WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$row) return null;
        $row['data'] = self::normalize(json_decode((string)$row['payload'], true) ?: []);
        return $row;
    }

    public static function delete(int $id): array {
        if (!self::getById($id)) return ['success' => false, 'message' => 'Ticket not found.'];
        Database::execute("UPDATE air_tickets SET deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE id = ?", [self::actor(), $id]);
        return ['success' => true, 'message' => 'Ticket deleted.'];
    }

    /** [bytes, mime] of the stored original, or null. */
    public static function originalBytes(int $id): ?array {
        $row = Database::fetchOne("SELECT original_path, original_mime FROM air_tickets WHERE id = ? AND deleted_at IS NULL", [$id]);
        $path = $row && $row['original_path'] ? __DIR__ . '/../' . ltrim((string)$row['original_path'], '/') : '';
        return $path && is_file($path) ? [(string)file_get_contents($path), (string)$row['original_mime']] : null;
    }

    /** Streams the stored original ticket (inline to open in the browser, or as a download). */
    public static function streamOriginal(int $id, bool $download): void {
        $row = Database::fetchOne("SELECT original_filename, original_path, original_mime FROM air_tickets WHERE id = ? AND deleted_at IS NULL", [$id]);
        $path = $row && $row['original_path'] ? __DIR__ . '/../' . ltrim((string)$row['original_path'], '/') : '';
        if (!$path || !is_file($path)) { http_response_code(404); exit('Original ticket not found.'); }
        $name = str_replace(['"', "\r", "\n"], '', (string)$row['original_filename']) ?: basename($path);
        header('Content-Type: ' . ($row['original_mime'] ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
