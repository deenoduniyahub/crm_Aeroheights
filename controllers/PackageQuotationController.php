<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Session.php';

/**
 * Complete Package quotations.
 * Key fields are stored as columns for listing/search; the whole normalized form is kept in `payload` (JSON).
 */
class PackageQuotationController {
    public const INCLUDE_KEYS = ['visa', 'tickets', 'hotel', 'transport', 'ziyarat', 'insurance'];

    private static function actor(): string { return Session::getActor(); }

    private static function str($value, int $max = 255): string {
        return mb_substr(trim((string)($value ?? '')), 0, $max);
    }

    private static function date($value): string {
        $value = trim((string)($value ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : '';
    }

    private static function price($value): string {
        $value = trim((string)($value ?? ''));
        return is_numeric($value) && (float)$value >= 0 ? $value : '';
    }

    /**
     * Normalizes form input into the canonical quotation shape used by the form, the DB payload and the print view.
     * Accepts hotels either as a list of rows (JSON from the form) or as parallel hotel_city[]/hotel_name[]… arrays (POST).
     */
    public static function normalize(array $d): array {
        $hotels = [];
        $rawHotels = $d['hotels'] ?? null;
        if (!is_array($rawHotels)) {
            $rawHotels = [];
            foreach ((array)($d['hotel_city'] ?? []) as $i => $city) {
                $rawHotels[] = [
                    'city' => $city,
                    'name' => $d['hotel_name'][$i] ?? '',
                    'distance' => $d['hotel_distance'][$i] ?? '',
                    'room' => $d['hotel_room'][$i] ?? '',
                    'nights' => $d['hotel_nights'][$i] ?? '',
                ];
            }
        }
        foreach ($rawHotels as $h) {
            if (!is_array($h)) continue;
            $row = [
                'city' => self::str($h['city'] ?? '', 60),
                'name' => self::str($h['name'] ?? '', 150),
                'distance' => self::str($h['distance'] ?? '', 40),
                'room' => self::str($h['room'] ?? '', 40),
                'nights' => ($n = trim((string)($h['nights'] ?? ''))) !== '' ? (string)max(0, (int)$n) : '',
            ];
            if ($row['city'] !== '' || $row['name'] !== '') $hotels[] = $row;
        }

        $q = [
            'quote_no' => self::str($d['quote_no'] ?? '', 50),
            'quote_date' => self::date($d['quote_date'] ?? ''),
            'valid_until' => self::date($d['valid_until'] ?? ''),
            'client_name' => self::str($d['client_name'] ?? '', 150),
            'intro' => self::str($d['intro'] ?? '', 300),
            'includes' => array_values(array_intersect(self::INCLUDE_KEYS, (array)($d['includes'] ?? []))),
            'custom_includes' => self::str($d['custom_includes'] ?? '', 1000),
            'adults' => max(0, (int)($d['adults'] ?? 0)),
            'children' => max(0, (int)($d['children'] ?? 0)),
            'infants' => max(0, (int)($d['infants'] ?? 0)),
            'total_days' => ($t = trim((string)($d['total_days'] ?? ''))) !== '' ? (string)max(0, (int)$t) : '',
            'hotels' => $hotels,
            'dep_sector' => strtoupper(self::str($d['dep_sector'] ?? '', 20)),
            'dep_date' => self::date($d['dep_date'] ?? ''),
            'dep_flight' => strtoupper(self::str($d['dep_flight'] ?? '', 20)),
            'ret_sector' => strtoupper(self::str($d['ret_sector'] ?? '', 20)),
            'ret_date' => self::date($d['ret_date'] ?? ''),
            'ret_flight' => strtoupper(self::str($d['ret_flight'] ?? '', 20)),
            'airline' => self::str($d['airline'] ?? '', 100),
            'baggage' => self::str($d['baggage'] ?? '', 150),
            'note' => self::str($d['note'] ?? '', 2000),
            'sign_name' => self::str($d['sign_name'] ?? '', 100),
            'sign_title' => self::str($d['sign_title'] ?? '', 150),
            'price_adult' => self::price($d['price_adult'] ?? ''),
            'price_child' => self::price($d['price_child'] ?? ''),
            'price_infant' => self::price($d['price_infant'] ?? ''),
            'total_price' => self::price($d['total_price'] ?? ''),
        ];

        if ($q['total_price'] === '') {
            $q['total_price'] = (string)((float)$q['price_adult'] * $q['adults'] + (float)$q['price_child'] * $q['children'] + (float)$q['price_infant'] * $q['infants']);
        }
        return $q;
    }

    public static function save(array $data): array {
        $id = (int)($data['id'] ?? 0);
        $q = self::normalize($data);
        if ($q['adults'] + $q['children'] + $q['infants'] === 0) {
            return ['success' => false, 'message' => 'Add at least one Mutamer (adult, child or infant).'];
        }

        $actor = self::actor();
        $params = [
            $q['quote_no'], $q['quote_date'] ?: null, $q['client_name'] ?: null,
            $q['adults'], $q['children'], $q['infants'],
            $q['dep_date'] ?: null, $q['ret_date'] ?: null, (float)$q['total_price'],
        ];

        if ($id > 0) {
            if (!self::getById($id)) return ['success' => false, 'message' => 'Quotation not found.'];
            if ($q['quote_no'] === '') $q['quote_no'] = $params[0] = 'QT-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
            Database::execute(
                "UPDATE package_quotations
                 SET quote_no = ?, quote_date = ?, client_name = ?, adults = ?, children = ?, infants = ?, dep_date = ?, ret_date = ?, total_price = ?,
                     payload = ?, updated_by = ?, updated_at = NOW()
                 WHERE id = ?",
                array_merge($params, [json_encode($q, JSON_UNESCAPED_UNICODE), $actor, $id])
            );
            return ['success' => true, 'message' => 'Quotation updated successfully.', 'id' => $id];
        }

        Database::execute(
            "INSERT INTO package_quotations (quote_no, quote_date, client_name, adults, children, infants, dep_date, ret_date, total_price, payload, created_by, created_at, updated_by, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())",
            array_merge($params, [json_encode($q, JSON_UNESCAPED_UNICODE), $actor, $actor])
        );
        $newId = Database::lastInsertId();
        if ($q['quote_no'] === '') {
            $q['quote_no'] = 'QT-' . str_pad((string)$newId, 5, '0', STR_PAD_LEFT);
            Database::execute("UPDATE package_quotations SET quote_no = ?, payload = ? WHERE id = ?", [$q['quote_no'], json_encode($q, JSON_UNESCAPED_UNICODE), $newId]);
        }
        return ['success' => true, 'message' => 'Quotation saved successfully.', 'id' => $newId];
    }

    public static function getAll(string $search = ''): array {
        $sql = "SELECT id, quote_no, quote_date, client_name, adults, children, infants, dep_date, ret_date, total_price, payload, created_by, updated_at
                FROM package_quotations WHERE deleted_at IS NULL";
        $params = [];
        if ($search !== '') {
            $sql .= " AND (quote_no LIKE ? OR client_name LIKE ? OR payload LIKE ?)";
            $like = '%' . $search . '%';
            $params = [$like, $like, $like];
        }
        $rows = Database::fetchAll($sql . " ORDER BY id DESC", $params);
        foreach ($rows as &$row) {
            $payload = json_decode((string)$row['payload'], true) ?: [];
            $row['hotels'] = $payload['hotels'] ?? [];
            $row['dep_sector'] = $payload['dep_sector'] ?? '';
            unset($row['payload']);
        }
        return $rows;
    }

    public static function getById(int $id): ?array {
        $row = Database::fetchOne("SELECT * FROM package_quotations WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$row) return null;
        $row['data'] = self::normalize(json_decode((string)$row['payload'], true) ?: []);
        return $row;
    }

    public static function delete(int $id): array {
        if (!self::getById($id)) return ['success' => false, 'message' => 'Quotation not found.'];
        Database::execute("UPDATE package_quotations SET deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE id = ?", [self::actor(), $id]);
        return ['success' => true, 'message' => 'Quotation deleted.'];
    }
}
