<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/TicketBookingController.php';
require_once __DIR__ . '/../services/TicketAiReader.php';

/**
 * Master Bookings -> "Smart Visa / Passport Upload".
 * The browser reads every file with Gemini (read_booking_file, kind=travel_doc), which sorts each page into a visa
 * or a passport. match() finds the Master Booking each document belongs to: same passport number first, then a
 * passport number one character off (scan / typing slip), then the name. The browser attaches the document (its
 * own page for multi-document PDFs) as the booking's Visa or Passport file.
 */
class SmartVisaController {
    /** $docs = [['file' => n, 'doc' => {doc_type, name, passport_no, ...}], ...] -> per document: best booking + up to 3 candidates. */
    public static function match(array $docs): array {
        $list = [];
        foreach ($docs as $d) {
            $raw = (array)($d['doc'] ?? $d['visa'] ?? []);
            $raw['doc_type'] ??= 'visa';
            $clean = TicketAiReader::clean('travel_doc', [$raw]);
            if ($clean) $list[] = $clean[0] + ['file' => (int)($d['file'] ?? 0)];
        }
        if (!$list) return ['success' => true, 'rows' => []];

        // Open bookings (recent first), with which documents they already have.
        $bookings = Database::fetchAll(
            "SELECT mb.id, mb.booking_code, mb.passenger_name, UPPER(REPLACE(mb.passport_number, ' ', '')) AS passport, mb.arrival_date,
                    mb.booking_date, a.name AS agent_name,
                    (SELECT COUNT(*) FROM booking_files bf WHERE bf.booking_id = mb.id AND bf.attachment_type = 'visa' AND bf.deleted_at IS NULL) AS has_visa,
                    (SELECT COUNT(*) FROM booking_files bf WHERE bf.booking_id = mb.id AND bf.attachment_type = 'passport' AND bf.deleted_at IS NULL) AS has_passport
             FROM master_bookings mb LEFT JOIN agents a ON a.id = mb.agent_id
             WHERE mb.deleted_at IS NULL AND mb.status <> 'cancelled'
             ORDER BY mb.id DESC LIMIT 5000"
        );

        $rows = [];
        foreach ($list as $v) {
            $hasKey = $v['doc_type'] === 'passport' ? 'has_passport' : 'has_visa';
            $cands = [];
            foreach ($bookings as $b) {
                $score = 0.0;
                $how = '';
                if ($v['passport_no'] !== '' && $b['passport'] !== '') {
                    if ($b['passport'] === $v['passport_no']) { $score = 3; $how = 'passport'; }
                    elseif (strlen($b['passport']) === strlen($v['passport_no']) && levenshtein($b['passport'], $v['passport_no']) === 1) { $score = 1.5; $how = 'passport_close'; }
                }
                $name = $v['name'] !== '' ? TicketBookingController::nameSimilarity($v['name'], (string)$b['passenger_name']) : 0;
                if ($how === '' && $name >= 0.67) { $score = $name; $how = 'name'; }
                elseif ($how !== '') $score += $name;
                if ($how === '') continue;
                if (!(int)$b[$hasKey]) $score += 0.2; // prefer the booking still waiting for this document
                $cands[] = [
                    'score' => round($score, 2), 'how' => $how, 'id' => (int)$b['id'], 'code' => $b['booking_code'], 'name' => $b['passenger_name'],
                    'passport' => $b['passport'], 'arrival_date' => $b['arrival_date'], 'agent' => $b['agent_name'],
                    'has_visa' => (int)$b['has_visa'] > 0, 'has_passport' => (int)$b['has_passport'] > 0,
                    'has_file' => (int)$b[$hasKey] > 0,
                ];
            }
            usort($cands, static fn($a, $b) => [$b['score'], $b['id']] <=> [$a['score'], $a['id']]);
            $cands = array_slice($cands, 0, 3);
            $rows[] = ['visa' => $v, 'candidates' => $cands, 'booking_id' => $cands[0]['id'] ?? null];
        }

        // Two documents of the same kind pointing at the same booking: keep it for the stronger match only.
        $taken = [];
        foreach ($rows as $i => $r) {
            if (!$r['booking_id']) continue;
            $slot = $r['booking_id'] . '|' . $r['visa']['doc_type'];
            $s = $r['candidates'][0]['score'];
            if (isset($taken[$slot]) && $rows[$taken[$slot]]['candidates'][0]['score'] >= $s) { $rows[$i]['booking_id'] = null; $rows[$i]['clash'] = true; continue; }
            if (isset($taken[$slot])) { $rows[$taken[$slot]]['booking_id'] = null; $rows[$taken[$slot]]['clash'] = true; }
            $taken[$slot] = $i;
        }
        return ['success' => true, 'rows' => $rows];
    }
}
