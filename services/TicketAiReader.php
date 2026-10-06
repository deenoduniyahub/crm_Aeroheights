<?php
declare(strict_types=1);

/**
 * Reads an original airline / portal ticket (PDF or image) with the Google Gemini API and returns the
 * ticket in the same shape as assets/js/ticket_reader.js, so the review form can be filled directly.
 *
 * Credentials live OUTSIDE the web root and git:
 *     ~/domains/<site>/aero_gemini.json   {"api_keys": ["key 1", "key 2", ...], "models": [optional, best first]}
 * (locally: C:\xampp\aero_gemini.json). Without that file the browser-only reader is used instead.
 * Several keys (from different Google accounts) work together: each request starts on the next key in turn and
 * a failing key is skipped immediately for the next one. Keys that hit their quota or are rejected are rested
 * for a while (aero_gemini_state.json next to the config) so later requests go straight to a working key.
 */
class TicketAiReader {
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    // Free-tier keys: Flash models only. Busy/over-quota models fall through to the next one.
    private const DEFAULT_MODELS = ['gemini-3.8-flash', 'gemini-flash-latest', 'gemini-3.7-flash', 'gemini-3.6-flash', 'gemini-3.5-flash'];
    // The web host cuts requests off at ~58s (HTTP 504), so one read must finish well inside that.
    private const TIME_BUDGET = 45;     // seconds for the whole read, across retries and fallbacks
    private const ATTEMPT_TIMEOUT = 28; // one Gemini call; some models hang without answering
    private const HEDGE_AFTER = 8;      // a call silent this long gets a parallel attempt on another model
    private const MAX_PARALLEL = 8;

    private const PROMPT = <<<'TXT'
You are reading an airline e-ticket / booking confirmation / itinerary for a Pakistani Umrah & travel agency.
The document may be a PDF or a screenshot/photo from any airline (Saudia, PIA, Airblue, AirSial, flydubai,
flynas, flyadeal, SalamAir, Air Arabia, Emirates, Qatar, Turkish, etc.), a GDS print (Sabre, Amadeus,
Travelport Viewtrip) or a travel portal. Extract the booking exactly as printed. Never guess or invent data;
leave a field as an empty string when it is not printed.

PNR
- "pnr" is the AIRLINE booking reference / airline PNR (labels: PNR, Booking Ref, Airline Ref,
  Confirmation Number, Record Locator, "<airline> booking reference"). It is usually 6 letters/digits.
- Do NOT use portal/agency numbers (e.g. "Ref. No: AS261521473", "Booking # 1581"), CRS/GDS "Reservation Code"
  when an airline confirmation number is also printed, or e-ticket numbers.
- If written like "SV/ABC123" return "ABC123". If two codes are joined like "XYZ789/DEF456", return the first.

PASSENGERS (every traveller, each once, in the order printed)
- "name": full name in natural order GIVEN NAMES then SURNAME, uppercase, no title.
  "KHAN/AHMED MR" -> "AHMED KHAN"; "MALIK, ALI RAZA MR" -> "ALI RAZA MALIK"; "MR USMAN TARIQ" -> "USMAN TARIQ".
  Drop "FNU"/"LNU" placeholders ("FNU USAMA" -> "USAMA").
- Some tickets print several travellers on one line separated only by wide spaces, without titles, e.g.
  "HASEEN BIBI   IQSA BIBI   MUHAMMAD MUJAHID" — that is THREE passengers. A single person's name is normally
  2-3 words (sometimes 4 with "BIN"/"BINT"/"UL"/"UD DIN"); never join several people into one name.
  Use the number of travellers stated anywhere (e.g. "5 pp", "Adults 5", seat/meal lines) to cross-check.
- "title": MR, MRS, MS, MISS, MSTR or INF when printed (MASTER -> MSTR), else "".
- "type": Adult, Child or Infant (CHD/Child/MSTR => Child, INF/Infant => Infant, otherwise Adult).
- "passport": passport number printed for that passenger, else "".
- "ticket_no": 13-digit e-ticket number for that passenger (digits only), else "". When ticket numbers are
  listed in a separate column in the same order as the names, match them by order.

FLIGHTS ("segments": one entry per flight number, in chronological order)
- "flight": airline code + number without spaces, e.g. "SV733", "PF716", "F3656", "FZ336".
  A heading like "Flight FZ 336/FZ 827" means TWO flights (connection), each its own segment.
- "from"/"to": 3-letter IATA airport codes (Lahore LHE, Karachi KHI, Islamabad ISB, Multan MUX, Sialkot SKT,
  Faisalabad LYP, Peshawar PEW, Jeddah JED, Madinah MED, Riyadh RUH, Dammam DMM, Taif TIF, Dubai DXB,
  Sharjah SHJ, Abu Dhabi AUH, Muscat MCT, Doha DOH, Bahrain BAH, Kuwait KWI, Istanbul IST, Milan MXP ...).
- "from_city"/"to_city": city names, e.g. "Lahore", "Jeddah".
- "dep_date"/"arr_date": YYYY-MM-DD. When the year is not printed, infer it from other dates on the ticket
  (booking/issue date or the other flights); a return in Jan after a Dec departure is the next year.
  If arrival is after midnight (overnight flight) the arrival date is the next day.
- "dep_time"/"arr_time": local 24-hour HH:MM ("10:10 PM" -> "22:10"). Use AM/PM markers even when they are
  printed in a separate row or column near the time (Travelport Viewtrip prints them above/below the times).
- "from_terminal"/"to_terminal": terminal name/number only, e.g. "M", "1", "Hajj", else "".
- "baggage": CHECKED baggage allowance for that flight as a weight or piece count, short, e.g. "46 KG",
  "46+7 KG", "2 PC", "1 PC (20 KG)", "30 KG". A bare number in a baggage column means kilograms ("23" -> "23 KG").
  Fare-family names like "Standard" or "Value" are NOT baggage. Never put hand/cabin baggage here. "" if not printed.
- "duration": flight time formatted like "5h 05m" or "3h 00m", else "".

OTHER
- "airline_code": IATA code of the main (marketing) airline, "airline_name": its common brand name
  (e.g. "Saudia", "PIA", "Airblue", "AirSial", "flydubai", "flyadeal", "flynas", "SalamAir").
- "status": one of Confirmed, Partially Confirmed, On Request, On Hold, Cancelled.
  ("not valid for travel"/"held until payment" => On Hold, "ON REQUEST" => On Request).
- "cabin": e.g. "Economy", "Economy Lite", "Business". "cabin_baggage": hand-carry allowance like "7 KG", else "".
- Ignore all fares, prices, taxes and charges completely.
TXT;

    private const PASSPORT_PROMPT = <<<'TXT'
You are reading passport scans for a Pakistani Umrah & travel agency. The file may be a photo, a scan or a PDF
and may contain ONE OR SEVERAL passports (one per page or several on one page). Return one entry per distinct
passport (the data page with the photo). Prefer the machine-readable zone (the two lines of "<" characters at the
bottom) when it is readable, and check it against the printed fields. Never guess; use "" when not readable.

- "surname": surname / family name exactly as printed, uppercase. Pakistani passports may leave the surname
  empty and put the whole name in Given Names — then keep surname "".
- "given_names": given names exactly as printed, uppercase.
- "passport_no": passport number, letters and digits only (e.g. "AB1234567").
- "nationality": 3-letter code, e.g. "PAK".
- "gender": "M" or "F".
- "dob", "expiry": YYYY-MM-DD.
- "cnic": Citizenship / National Identity number (Pakistani CNIC like 35202-1234567-1) when printed, else "".
- "page": for a PDF, the 1-based page number holding this passport's data page (as a string, e.g. "2"); "1" for an image.
Ignore visa pages, ID cards and other documents that are not a passport data page.
TXT;

    private const VISA_PROMPT = <<<'TXT'
You are reading visas issued for a Pakistani Umrah & travel agency — mostly Saudi Arabia e-visas / Umrah visas
(Kingdom of Saudi Arabia, Ministry of Foreign Affairs / Nusuk printouts), sometimes other countries' visas.
The file may be a PDF or an image and may contain ONE OR SEVERAL visas (usually one per page). Return one entry
per distinct visa. Never guess; use "" when a field is not printed.

- "name": the visa holder's full name in English, uppercase, in natural order GIVEN NAMES then SURNAME
  (ignore Arabic script).
- "passport_no": the holder's passport number, letters and digits only. This is the most important field —
  read it carefully (label "Passport No." / "رقم الجواز").
- "visa_no": the visa number. "visa_type": e.g. "Umrah", "Tourist", "Visit".
- "nationality": as printed. "issue_date", "expiry_date": YYYY-MM-DD ("valid until" = expiry).
- "page": for a PDF, the 1-based page number holding this visa (as a string, e.g. "3"); "1" for an image.
TXT;

    private static function visaSchema(): array {
        $fields = ['name', 'passport_no', 'visa_no', 'visa_type', 'nationality', 'issue_date', 'expiry_date', 'page'];
        return [
            'type' => 'OBJECT',
            'properties' => [
                'visas' => ['type' => 'ARRAY', 'items' => [
                    'type' => 'OBJECT',
                    'properties' => array_fill_keys($fields, ['type' => 'STRING']),
                    'required' => $fields,
                    'propertyOrdering' => $fields,
                ]],
            ],
            'required' => ['visas'],
        ];
    }

    private const TRAVEL_DOC_PROMPT = <<<'TXT'
You are sorting travel documents for a Pakistani Umrah & travel agency. The file (PDF or image) may contain ONE
OR SEVERAL documents, usually one per page, and they can be mixed:
- "visa": a visa (mostly Saudi Arabia e-visa / Umrah visa printouts, Nusuk, Ministry of Foreign Affairs).
- "passport": a passport DATA page (photo page with the machine-readable "<<<" lines).
Ignore anything else (tickets, ID cards, blank pages). Return one entry per distinct document. Never guess; use ""
when a field is not printed.

- "doc_type": "visa" or "passport".
- "name": the holder's full name in English, uppercase, natural order GIVEN NAMES then SURNAME (ignore Arabic).
  For passports combine Given Names + Surname.
- "passport_no": the holder's passport number, letters and digits only. Most important field — read carefully
  (on a visa it is labelled "Passport No."; on a passport prefer the machine-readable zone).
- "visa_no", "visa_type" (e.g. "Umrah", "Tourist"): visas only, else "".
- "nationality", "gender" ("M"/"F"), "dob": as printed; dates as YYYY-MM-DD.
- "issue_date", "expiry_date": YYYY-MM-DD (visa validity or passport expiry).
- "page": for a PDF, the 1-based page number of this document (as a string, e.g. "3"); "1" for an image.
TXT;

    private static function travelDocSchema(): array {
        $fields = ['doc_type', 'name', 'passport_no', 'visa_no', 'visa_type', 'nationality', 'gender', 'dob', 'issue_date', 'expiry_date', 'page'];
        $props = array_fill_keys($fields, ['type' => 'STRING']);
        $props['doc_type'] = ['type' => 'STRING', 'enum' => ['visa', 'passport']];
        return [
            'type' => 'OBJECT',
            'properties' => [
                'documents' => ['type' => 'ARRAY', 'items' => ['type' => 'OBJECT', 'properties' => $props, 'required' => $fields, 'propertyOrdering' => $fields]],
            ],
            'required' => ['documents'],
        ];
    }

    /** Travel documents cleaned up; visa fields shared with tidyVisas() plus doc_type, gender and date of birth. */
    private static function tidyDocs(array $d): array {
        $out = [];
        foreach ((array)($d['documents'] ?? []) as $doc) {
            $type = ($doc['doc_type'] ?? '') === 'passport' ? 'passport' : 'visa';
            $clean = self::tidyVisas(['visas' => [$doc]]);
            if (!$clean) continue;
            $g = strtoupper(trim((string)($doc['gender'] ?? '')));
            $dob = trim((string)($doc['dob'] ?? ''));
            $out[] = ['doc_type' => $type] + $clean[0] + [
                'gender' => in_array($g, ['M', 'F'], true) ? $g : '',
                'dob' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) ? $dob : '',
            ];
        }
        return $out;
    }

    /** Visa fields cleaned up: name uppercase, dates YYYY-MM-DD, passport number letters/digits only. */
    private static function tidyVisas(array $d): array {
        $up = static fn($v) => strtoupper(trim(preg_replace('/\s+/', ' ', (string)($v ?? ''))));
        $date = static fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string)$v)) ? trim((string)$v) : '';
        $out = [];
        foreach ((array)($d['visas'] ?? []) as $v) {
            $no = preg_replace('/[^A-Z0-9]/', '', $up($v['passport_no'] ?? ''));
            $name = $up($v['name'] ?? '');
            if ($no === '' && $name === '') continue;
            $out[] = [
                'name' => $name,
                'passport_no' => $no,
                'visa_no' => preg_replace('/[^A-Z0-9\-]/', '', $up($v['visa_no'] ?? '')),
                'visa_type' => trim((string)($v['visa_type'] ?? '')),
                'nationality' => $up($v['nationality'] ?? ''),
                'issue_date' => $date($v['issue_date'] ?? ''),
                'expiry_date' => $date($v['expiry_date'] ?? ''),
                'page' => max(1, (int)($v['page'] ?? 1)),
            ];
        }
        return $out;
    }

    private static function passportSchema(): array {
        $fields = ['surname', 'given_names', 'passport_no', 'nationality', 'gender', 'dob', 'expiry', 'cnic', 'page'];
        return [
            'type' => 'OBJECT',
            'properties' => [
                'passports' => ['type' => 'ARRAY', 'items' => [
                    'type' => 'OBJECT',
                    'properties' => array_fill_keys($fields, ['type' => 'STRING']),
                    'required' => $fields,
                    'propertyOrdering' => $fields,
                ]],
            ],
            'required' => ['passports'],
        ];
    }

    private static function schema(): array {
        $str = ['type' => 'STRING'];
        return [
            'type' => 'OBJECT',
            'properties' => [
                'pnr' => $str,
                'airline_code' => $str,
                'airline_name' => $str,
                'status' => ['type' => 'STRING', 'enum' => ['Confirmed', 'Partially Confirmed', 'On Request', 'On Hold', 'Cancelled']],
                'cabin' => $str,
                'cabin_baggage' => $str,
                'passengers' => ['type' => 'ARRAY', 'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'title' => $str,
                        'name' => $str,
                        'type' => ['type' => 'STRING', 'enum' => ['Adult', 'Child', 'Infant']],
                        'passport' => $str,
                        'ticket_no' => $str,
                    ],
                    'required' => ['title', 'name', 'type', 'passport', 'ticket_no'],
                    'propertyOrdering' => ['title', 'name', 'type', 'passport', 'ticket_no'],
                ]],
                'segments' => ['type' => 'ARRAY', 'items' => [
                    'type' => 'OBJECT',
                    'properties' => array_fill_keys(['flight', 'from', 'to', 'from_city', 'to_city', 'dep_date', 'dep_time', 'arr_date', 'arr_time', 'from_terminal', 'to_terminal', 'baggage', 'duration'], $str),
                    'required' => ['flight', 'from', 'to', 'from_city', 'to_city', 'dep_date', 'dep_time', 'arr_date', 'arr_time', 'from_terminal', 'to_terminal', 'baggage', 'duration'],
                    'propertyOrdering' => ['flight', 'from', 'to', 'from_city', 'to_city', 'dep_date', 'dep_time', 'arr_date', 'arr_time', 'from_terminal', 'to_terminal', 'baggage', 'duration'],
                ]],
            ],
            'required' => ['pnr', 'airline_code', 'airline_name', 'status', 'cabin', 'cabin_baggage', 'passengers', 'segments'],
            'propertyOrdering' => ['pnr', 'airline_code', 'airline_name', 'status', 'cabin', 'cabin_baggage', 'passengers', 'segments'],
        ];
    }

    /** ['api_key' => ..., 'models' => [...]] or null when AI reading is not configured. */
    public static function config(): ?array {
        $file = self::configFile();
        if (!is_readable($file)) return null;
        $cfg = json_decode((string)file_get_contents($file), true);
        if (!is_array($cfg)) return null;
        $keys = array_merge((array)($cfg['api_keys'] ?? []), isset($cfg['api_key']) ? [$cfg['api_key']] : []);
        $keys = array_values(array_unique(array_filter(array_map('trim', array_filter($keys, 'is_string')))));
        if (!$keys) return null;
        $models = array_values(array_filter((array)($cfg['models'] ?? self::DEFAULT_MODELS), 'is_string'));
        return ['api_keys' => $keys, 'models' => $models ?: self::DEFAULT_MODELS];
    }

    private static function configFile(): string { return dirname(__DIR__, 3) . '/aero_gemini.json'; }
    private static function stateFile(): string { return dirname(__DIR__, 3) . '/aero_gemini_state.json'; }
    private static function keyId(string $key): string { return substr(hash('sha256', $key), 0, 12); }

    private static function loadState(): array {
        $raw = @file_get_contents(self::stateFile());
        $state = $raw ? json_decode($raw, true) : null;
        $state = is_array($state) ? $state : [];
        return ['next' => (int)($state['next'] ?? 0), 'rest' => (array)($state['rest'] ?? []), 'good' => (array)($state['good'] ?? [])];
    }

    /** Rests a model on every key ("high demand" / hanging calls are model-wide on Gemini). */
    private static function restModel(string $model, int $seconds): void {
        $state = self::loadState();
        $state['rest']['*|' . $model] = max((int)($state['rest']['*|' . $model] ?? 0), time() + $seconds);
        self::saveState($state);
    }

    /** Remembers that a model just answered, so the next reads try it first. */
    private static function markGood(string $model): void {
        $state = self::loadState();
        $state['good'][$model] = time();
        unset($state['rest']['*|' . $model]);
        self::saveState($state);
    }

    private static function saveState(array $state): void {
        $now = time();
        $state['rest'] = array_filter((array)($state['rest'] ?? []), static fn($until) => $until > $now);
        @file_put_contents(self::stateFile(), json_encode($state), LOCK_EX);
    }

    /**
     * Rests one key for one model ($model '*' = every model, e.g. an invalid key). Quotas are per key AND per
     * model on Gemini, so a model that is used up on a key does not stop the other models on that key.
     */
    private static function rest(string $key, string $model, int $seconds): void {
        $state = self::loadState(); // re-read: other requests may have written meanwhile
        $id = self::keyId($key) . '|' . $model;
        $state['rest'][$id] = max((int)($state['rest'][$id] ?? 0), time() + $seconds);
        self::saveState($state);
    }

    /**
     * Every [model, key] pair worth trying, best model first. Within a model the keys rotate per request so all
     * accounts share the load. Pairs that are resting are left out; if everything rests, the pairs that become
     * free soonest are tried anyway.
     */
    private static function candidates(array $cfg): array {
        $keys = $cfg['api_keys'];
        $n = count($keys);
        $state = self::loadState();
        $start = $state['next'] % $n;
        $state['next'] = ($start + 1) % $n;
        self::saveState($state);

        $now = time();
        $free = [];
        $resting = [];
        foreach ($cfg['models'] as $model) {
            for ($i = 0; $i < $n; $i++) {
                $k = ($start + $i) % $n;
                $id = self::keyId($keys[$k]);
                $until = max((int)($state['rest'][$id . '|' . $model] ?? 0), (int)($state['rest'][$id . '|*'] ?? 0), (int)($state['rest']['*|' . $model] ?? 0));
                if ($until > $now) $resting[] = [$until, $model, $k];
                else $free[] = [$model, $k];
            }
        }
        // Models that answered in the last hour go first (the configured order is kept otherwise).
        $rank = array_flip($cfg['models']);
        $recent = static fn(string $m): int => ((int)($state['good'][$m] ?? 0) > $now - 3600) ? 0 : 1;
        usort($free, static fn($a, $b) => [$recent($a[0]), $rank[$a[0]]] <=> [$recent($b[0]), $rank[$b[0]]]);
        if ($free) return $free;
        usort($resting, static fn($a, $b) => $a[0] <=> $b[0]);
        return array_map(static fn($r) => [$r[1], $r[2]], array_slice($resting, 0, $n));
    }

    public static function isEnabled(): bool {
        return self::config() !== null;
    }

    /** Reads an uploaded file ($_FILES entry). */
    public static function readUpload(array $file, string $kind = 'ticket'): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            return ['success' => false, 'message' => 'No file received.'];
        }
        if ((int)$file['size'] > 10485760) return ['success' => false, 'message' => 'File size exceeds the 10 MB limit.'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']) ?: '';
        return self::readMany([['kind' => in_array($kind, ['passport', 'visa', 'travel_doc'], true) ? $kind : 'ticket', 'bytes' => (string)file_get_contents((string)$file['tmp_name']), 'mime' => $mime]])[0];
    }

    /** Reads raw ticket bytes (application/pdf, image/jpeg, image/png, image/webp). */
    public static function readBytes(string $bytes, string $mime): array {
        return self::readMany([['kind' => 'ticket', 'bytes' => $bytes, 'mime' => $mime]])[0];
    }

    /** Reads one passport scan / PDF (a PDF may hold several passports). Returns ['passports' => [...]]. */
    public static function readPassport(string $bytes, string $mime): array {
        return self::readMany([['kind' => 'passport', 'bytes' => $bytes, 'mime' => $mime]])[0];
    }

    /**
     * Reads several documents at once within $budget seconds (kept under the web host's ~60s gateway limit).
     * All jobs run in parallel over the keys and models. A job whose attempt fails moves straight to the next
     * free [model, key]; an attempt that is still silent after HEDGE_AFTER seconds gets a second attempt on
     * another pair alongside it, and the first good answer wins. Jobs: ['kind' => 'ticket'|'passport', 'bytes', 'mime'].
     * Results keep the job order: ticket => ['ticket' => ...], passport => ['passports' => [...]].
     */
    public static function readMany(array $jobs, int $budget = self::TIME_BUDGET): array {
        $results = [];
        $cfg = self::config();
        $pending = [];
        foreach (array_values($jobs) as $i => $job) {
            if (!in_array($job['mime'] ?? '', ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
                $results[$i] = ['success' => false, 'message' => 'Only PDF, JPG, PNG or WEBP files are supported.'];
            } elseif (!$cfg) {
                $results[$i] = ['success' => false, 'message' => 'AI reading is not configured.', 'not_configured' => true];
            } else {
                $pending[$i] = $job;
            }
        }
        if (!$pending) { ksort($results); return $results; }

        $deadline = microtime(true) + $budget;
        $keys = $cfg['api_keys'];
        $pairs = self::candidates($cfg);
        $dead = [];      // "model|k" used up / broken / hanging during this run — skipped by every job
        $busy = [];      // "model|k" => time it may be tried again (overloaded just now)
        $waitUntil = []; // per job: pause before going round the pairs again
        $next = [];      // per job: index into $pairs
        $tried = [];     // per job: "model|k" => true
        $lastError = [];
        $payloads = [];  // per job and model: request body (built once)
        $active = [];    // (int)handle => [handle, job, model, k, started]
        $mh = curl_multi_init();
        $offset = 0;
        foreach ($pending as $i => $_) {
            // Spread the jobs' first attempts over different keys of the best model.
            $next[$i] = $offset++ % max(1, min(count($keys), count($pairs)));
            $tried[$i] = [];
            $lastError[$i] = 'AI reading failed.';
        }

        $start = function (int $i, string $avoidModel = '') use (&$next, &$tried, &$dead, &$busy, &$active, &$payloads, $pairs, $pending, $keys, $mh, $deadline): bool {
            $left = $deadline - microtime(true);
            if ($left < 6) return false;
            $count = count($pairs);
            $now = microtime(true);
            for ($step = 0; $step < $count; $step++) {
                [$model, $k] = $pairs[($next[$i] + $step) % $count];
                $pid = $model . '|' . $k;
                if (isset($tried[$i][$pid]) || isset($dead[$pid]) || ($busy[$pid] ?? 0) > $now || $model === $avoidModel) continue;
                $next[$i] = ($next[$i] + $step + 1) % $count;
                $tried[$i][$pid] = true;
                $payloads[$i][$model] ??= self::payload($pending[$i], $model);
                $ch = self::handle(sprintf(self::ENDPOINT, rawurlencode($model)), $keys[$k], $payloads[$i][$model], (int)max(5, min(self::ATTEMPT_TIMEOUT, $left - 2)));
                curl_multi_add_handle($mh, $ch);
                $active[(int)$ch] = [$ch, $i, $model, $k, microtime(true)];
                return true;
            }
            return false;
        };

        while (true) {
            // Keep one attempt running per unfinished job, plus a hedge when the current one is slow.
            $now = microtime(true);
            foreach ($pending as $i => $_) {
                if (isset($results[$i]) || ($waitUntil[$i] ?? 0) > $now) continue;
                $running = array_filter($active, static fn($a) => $a[1] === $i);
                $slow = $running && count($running) < 2 && min(array_map(static fn($a) => $a[4], $running)) < $now - self::HEDGE_AFTER;
                if ((!$running || $slow) && count($active) < self::MAX_PARALLEL) $start($i, $slow ? reset($running)[2] : '');
            }
            // Jobs that went through every usable pair without luck: pause, then go round again while time is left.
            foreach ($pending as $i => $_) {
                if (isset($results[$i]) || ($waitUntil[$i] ?? 0) > $now || array_filter($active, static fn($a) => $a[1] === $i)) continue;
                $usable = array_filter($pairs, static fn($p) => !isset($dead[$p[0] . '|' . $p[1]]));
                if ($usable && $deadline - $now > 12) {
                    $tried[$i] = [];
                    $waitUntil[$i] = $now + 3;
                } else {
                    $results[$i] = ['success' => false, 'message' => 'The AI service is busy right now — please try again in a minute. (' . $lastError[$i] . ')'];
                }
            }
            if (!array_diff_key($pending, $results)) break;
            if (!$active) { usleep(300000); continue; }

            curl_multi_exec($mh, $stillRunning);
            if ($stillRunning) curl_multi_select($mh, 0.5);
            curl_multi_exec($mh, $stillRunning);
            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                if (!isset($active[(int)$ch])) continue;
                [, $i, $model, $k] = $active[(int)$ch];
                unset($active[(int)$ch]);
                $raw = (string)curl_multi_getcontent($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err = $code === 0 ? (curl_error($ch) ?: 'no response') : '';
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                if (isset($results[$i])) continue; // a parallel attempt already answered

                $res = self::handleResponse($pending[$i]['kind'], $code, $raw, $err, $keys[$k], $k, $model);
                if ($res['success']) {
                    $results[$i] = $res;
                    foreach ($active as $id => $a) { // cancel this job's other attempt
                        if ($a[1] !== $i) continue;
                        curl_multi_remove_handle($mh, $a[0]);
                        curl_close($a[0]);
                        unset($active[$id]);
                    }
                    continue;
                }
                $lastError[$i] = $res['message'];
                if (!empty($res['dead'])) $dead[$model . '|' . $k] = true;
                if (!empty($res['busy'])) $busy[$model . '|' . $k] = microtime(true) + 6;
                if (!empty($res['model_gone']) || !empty($res['model_busy'])) foreach (array_keys($keys) as $kk) $dead[$model . '|' . $kk] = true;
            }
        }
        curl_multi_close($mh);
        ksort($results);
        return $results;
    }

    private static function payload(array $job, string $model): string {
        $kind = $job['kind'] ?? 'ticket';
        $req = [
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    ['inline_data' => ['mime_type' => $job['mime'], 'data' => base64_encode((string)$job['bytes'])]],
                    ['text' => match ($kind) { 'passport' => self::PASSPORT_PROMPT, 'visa' => self::VISA_PROMPT, 'travel_doc' => self::TRAVEL_DOC_PROMPT, default => self::PROMPT }],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0,
                'responseMimeType' => 'application/json',
                'responseSchema' => match ($kind) { 'passport' => self::passportSchema(), 'visa' => self::visaSchema(), 'travel_doc' => self::travelDocSchema(), default => self::schema() },
            ],
        ];
        if (str_starts_with($model, 'gemini-3')) $req['generationConfig']['thinkingConfig'] = ['thinkingLevel' => 'low'];
        return (string)json_encode($req, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Parses one Gemini answer. Failures rest the [key, model] pair for as long as it is useless: a used-up daily
     * quota until Google's reset (retryDelay), overload / no answer for a couple of minutes, a bad key for hours.
     */
    private static function handleResponse(string $kind, int $status, string $raw, string $curlError, string $key, int $k, string $model): array {
        if ($curlError !== '') {
            self::restModel($model, 120); // hanging calls come from an overloaded model, not from one key
            return ['success' => false, 'message' => 'No answer from ' . $model . ' (key ' . ($k + 1) . ').', 'dead' => true, 'model_busy' => true];
        }
        $json = json_decode($raw, true);
        if ($status === 200) {
            $text = '';
            foreach ((array)($json['candidates'][0]['content']['parts'] ?? []) as $part) {
                if (isset($part['text']) && empty($part['thought'])) $text .= $part['text'];
            }
            $data = json_decode($text, true);
            if (!is_array($data)) return ['success' => false, 'message' => 'The AI returned an unreadable answer.'];
            self::markGood($model);
            $out = ['success' => true, 'model' => $model, 'key' => $k + 1];
            return match ($kind) { 'passport' => $out + ['passports' => self::tidyPassports($data)], 'visa' => $out + ['visas' => self::tidyVisas($data)], 'travel_doc' => $out + ['documents' => self::tidyDocs($data)], default => $out + ['ticket' => self::tidy($data)] };
        }

        $message = (string)($json['error']['message'] ?? ('HTTP ' . $status));
        $short = 'AI service error (' . $model . ', key ' . ($k + 1) . '): ' . strtok($message, "\n");
        if ($status === 429) {
            $delay = 60;
            foreach ((array)($json['error']['details'] ?? []) as $d) {
                if (isset($d['retryDelay']) && preg_match('/^(\d+)/', (string)$d['retryDelay'], $m)) $delay = max($delay, (int)$m[1]);
                foreach ((array)($d['violations'] ?? []) as $v) {
                    if (stripos((string)($v['quotaId'] ?? ''), 'PerDay') !== false) $delay = max($delay, 3600);
                }
            }
            self::rest($key, $model, min($delay + 30, 26 * 3600));
            return ['success' => false, 'message' => $short, 'dead' => true];
        }
        if (in_array($status, [400, 401, 403], true) && preg_match('/api.?key|permission|denied|billing/i', $message)) {
            self::rest($key, '*', 6 * 3600); // invalid or blocked key
            return ['success' => false, 'message' => $short, 'dead' => true];
        }
        if ($status === 404 || preg_match('/no longer available|not found/i', $message)) {
            self::rest($key, $model, 24 * 3600);
            return ['success' => false, 'message' => $short, 'model_gone' => true];
        }
        // 500/503 "high demand": rest this pair briefly, try the next one now.
        // 500/503 "high demand" comes and goes per key: rest this pair briefly, others carry on.
        self::rest($key, $model, 45);
        return ['success' => false, 'message' => $short, 'busy' => true];
    }

    private static function handle(string $url, string $key, string $body, int $timeout) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        return $ch;
    }

    /** Re-cleans a read result sent back by the browser (ticket => tidy ticket, passport => passport list). */
    public static function clean(string $kind, array $data): array {
        return match ($kind) { 'passport' => self::tidyPassports(['passports' => $data]), 'visa' => self::tidyVisas(['visas' => $data]), 'travel_doc' => self::tidyDocs(['documents' => $data]), default => self::tidy($data) };
    }

    /** Passport fields cleaned up: names uppercase, dates YYYY-MM-DD, passport number letters/digits only. */
    private static function tidyPassports(array $d): array {
        $up = static fn($v) => strtoupper(trim(preg_replace('/\s+/', ' ', (string)($v ?? ''))));
        $date = static fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string)$v)) ? trim((string)$v) : '';
        $out = [];
        foreach ((array)($d['passports'] ?? []) as $p) {
            $no = preg_replace('/[^A-Z0-9]/', '', $up($p['passport_no'] ?? ''));
            $surname = $up($p['surname'] ?? '');
            $given = $up($p['given_names'] ?? '');
            if ($no === '' && $surname === '' && $given === '') continue;
            $out[] = [
                'surname' => $surname,
                'given_names' => $given,
                'passport_no' => $no,
                'nationality' => $up($p['nationality'] ?? ''),
                'gender' => in_array($g = $up($p['gender'] ?? ''), ['M', 'F'], true) ? $g : '',
                'dob' => $date($p['dob'] ?? ''),
                'expiry' => $date($p['expiry'] ?? ''),
                'cnic' => preg_replace('/[^0-9\-]/', '', (string)($p['cnic'] ?? '')),
                'page' => max(1, (int)($p['page'] ?? 1)),
            ];
        }
        return $out;
    }

    /** Light clean-up so the result drops straight into the review form. */
    private static function tidy(array $t): array {
        $up = static fn($v) => strtoupper(trim(preg_replace('/\s+/', ' ', (string)($v ?? ''))));
        $passengers = [];
        $seen = [];
        foreach ((array)($t['passengers'] ?? []) as $p) {
            $name = trim(preg_replace('/^(FNU|LNU)\s+/', '', $up($p['name'] ?? '')));
            if ($name === '' || isset($seen[$name])) continue;
            $seen[$name] = true;
            $passengers[] = [
                'title' => in_array($title = str_replace(['.', 'MASTER'], ['', 'MSTR'], $up($p['title'] ?? '')), ['MR', 'MRS', 'MS', 'MISS', 'MSTR', 'INF'], true) ? $title : '',
                'name' => $name,
                'type' => in_array($p['type'] ?? '', ['Adult', 'Child', 'Infant'], true) ? $p['type'] : 'Adult',
                'passport' => $up($p['passport'] ?? ''),
                'ticket_no' => preg_replace('/\D/', '', (string)($p['ticket_no'] ?? '')),
            ];
        }
        $segments = [];
        foreach ((array)($t['segments'] ?? []) as $s) {
            $seg = [];
            foreach (['flight', 'from', 'to', 'from_city', 'to_city', 'dep_date', 'dep_time', 'arr_date', 'arr_time', 'from_terminal', 'to_terminal', 'baggage', 'duration'] as $k) {
                $seg[$k] = trim((string)($s[$k] ?? ''));
            }
            $seg['flight'] = preg_replace('/[^A-Z0-9]/', '', $up($seg['flight']));
            $seg['from'] = $up($seg['from']);
            $seg['to'] = $up($seg['to']);
            foreach (['dep_date', 'arr_date'] as $k) if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $seg[$k])) $seg[$k] = '';
            foreach (['dep_time', 'arr_time'] as $k) {
                $seg[$k] = preg_match('/^(\d{1,2}):(\d{2})/', $seg[$k], $m) ? sprintf('%02d:%s', (int)$m[1], $m[2]) : '';
            }
            // "04h:40m", "03h 00min", "5H 0M" -> "4h 40m"
            if (preg_match('/(\d{1,2})\s*h\D{0,4}?(\d{1,2})\s*m/i', $seg['duration'], $m)) $seg['duration'] = (int)$m[1] . 'h ' . sprintf('%02d', (int)$m[2]) . 'm';
            if ($seg['flight'] !== '' || $seg['from'] !== '') $segments[] = $seg;
        }
        usort($segments, static fn($a, $b) => strcmp($a['dep_date'] . $a['dep_time'], $b['dep_date'] . $b['dep_time']));
        return [
            'pnr' => preg_replace('/[^A-Z0-9]/', '', $up($t['pnr'] ?? '')),
            'airline_code' => $up($t['airline_code'] ?? ''),
            'airline_name' => trim((string)($t['airline_name'] ?? '')),
            'status' => (string)($t['status'] ?? 'Confirmed') ?: 'Confirmed',
            'cabin' => trim((string)($t['cabin'] ?? '')) ?: 'Economy',
            'cabin_baggage' => trim((string)($t['cabin_baggage'] ?? '')),
            'passengers' => $passengers,
            'segments' => $segments,
        ];
    }
}
