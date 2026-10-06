<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Session.php';
require_once __DIR__ . '/VoucherController.php';

/**
 * Only Hotel Booking Controller
 * Standalone multi-stay hotel bookings (e.g. Makkah -> Madinah -> Makkah again),
 * agent ledger posting (Total Sell Amount as debit, matching the Build Hotel Voucher flow),
 * plus Agent Hotel Invoice / Hotel Sale Voucher printouts.
 */
class HotelBookingController {

    private static function normalizeStays(array $stays): array {
        $normalized = [];

        foreach ($stays as $index => $stay) {
            if (!is_array($stay)) {
                continue;
            }

            $hotelName = trim((string)($stay['hotel_name'] ?? ''));
            if ($hotelName === '') {
                continue;
            }

            $city = trim((string)($stay['city'] ?? 'Makkah')) ?: 'Makkah';
            $roomType = trim((string)($stay['room_type'] ?? 'Standard'));
            if ($roomType === '' || $roomType === '__custom__') {
                $roomType = trim((string)($stay['custom_room_type'] ?? ''));
            }
            if ($roomType === '') {
                $roomType = 'Standard';
            }
            $mealPlan = trim((string)($stay['meal_plan'] ?? 'RO')) ?: 'RO';
            $confirmation = trim((string)($stay['confirmation_number'] ?? ''));
            $checkin = trim((string)($stay['checkin_date'] ?? ''));
            $checkout = trim((string)($stay['checkout_date'] ?? ''));

            $nights = max(1, (int)($stay['nights'] ?? 1));
            if ($checkin !== '' && $checkout !== '') {
                try {
                    $in = new DateTimeImmutable($checkin);
                    $out = new DateTimeImmutable($checkout);
                    $diff = (int)$in->diff($out)->days;
                    if ($out > $in && $diff > 0) {
                        $nights = $diff;
                    }
                } catch (Exception $e) {
                    // Keep the supplied nights if dates are invalid; validation below reports it.
                }
            }

            $errors = [];
            if ($checkin === '' || $checkout === '') {
                $errors[] = 'check-in and check-out dates are required';
            } else {
                try {
                    $in = new DateTimeImmutable($checkin);
                    $out = new DateTimeImmutable($checkout);
                    if ($out <= $in) {
                        $errors[] = 'check-out must be after check-in';
                    }
                } catch (Exception $e) {
                    $errors[] = 'invalid stay dates';
                }
            }

            if ($errors) {
                return [
                    'success' => false,
                    'message' => 'Hotel #' . ((int)$index + 1) . ': ' . implode(', ', $errors) . '.'
                ];
            }

            $rooms = max(1, (int)($stay['rooms'] ?? 1));
            $buyRate = max(0.0, round((float)($stay['buy_rate_per_night'] ?? 0), 2));
            $sellRate = max(0.0, round((float)($stay['sell_rate_per_night'] ?? 0), 2));

            $normalized[] = [
                'city' => $city,
                'hotel_name' => $hotelName,
                'room_type' => $roomType,
                'meal_plan' => $mealPlan,
                'confirmation_number' => $confirmation,
                'checkin_date' => $checkin,
                'checkout_date' => $checkout,
                'nights' => $nights,
                'rooms' => $rooms,
                'buy_rate_per_night' => $buyRate,
                'sell_rate_per_night' => $sellRate,
                'buy_total' => round($buyRate * $nights * $rooms, 2),
                'sell_total' => round($sellRate * $nights * $rooms, 2),
            ];
        }

        if (!$normalized) {
            return ['success' => false, 'message' => 'At least one hotel stay must be added.'];
        }

        return ['success' => true, 'stays' => $normalized];
    }

    private static function calculateTotals(array $stays): array {
        $totalNights = 0;
        $buy = 0.0;
        $sell = 0.0;

        foreach ($stays as $stay) {
            $totalNights += (int)$stay['nights'];
            $buy += (float)$stay['buy_total'];
            $sell += (float)$stay['sell_total'];
        }

        return [
            'total_nights' => $totalNights,
            'buy_total' => round($buy, 2),
            'sell_total' => round($sell, 2),
        ];
    }

    private static function collectBaseData(array $data): array {
        return [
            'agent_id' => (int)($data['agent_id'] ?? 0),
            'booking_date' => !empty($data['booking_date']) ? $data['booking_date'] : date('Y-m-d'),
            'lead_guest_name' => trim((string)($data['lead_guest_name'] ?? '')),
            'company_name' => trim((string)($data['company_name'] ?? '')) ?: 'Aeroheights Travels & Tours',
            'pax_adults' => max(0, (int)($data['pax_adults'] ?? 1)),
            'pax_children' => max(0, (int)($data['pax_children'] ?? 0)),
            'pax_infants' => max(0, (int)($data['pax_infants'] ?? 0)),
            'remarks' => trim((string)($data['remarks'] ?? '')),
            'transport_enabled' => !empty($data['transport_enabled']) ? 1 : 0,
            'transport_type' => trim((string)($data['transport_type'] ?? '')) ?: 'Car',
            'transport_buy_rate' => max(0.0, round((float)($data['transport_buy_rate'] ?? 0), 2)),
            'transport_sell_rate' => max(0.0, round((float)($data['transport_sell_rate'] ?? 0), 2)),
            // Same per-route vehicle/rate overrides and unticked routes as Build Hotel Voucher.
            'transport_route_overrides' => VoucherController::normalizeRouteOverrides((array)($data['transport_route_overrides'] ?? [])),
            'transport_skip_routes' => VoucherController::normalizeSkipRoutes((array)($data['transport_skip_routes'] ?? [])),
            'transport_custom_routes' => VoucherController::normalizeCustomRoutes((array)($data['transport_custom_routes'] ?? [])),
        ];
    }

    private static function extractMasterBookingIds(array $data): array {
        $ids = array_map('intval', (array)($data['master_booking_ids'] ?? []));
        return array_values(array_unique(array_filter($ids, static fn($id) => $id > 0)));
    }

    private static function validate(array $base): ?string {
        if ($base['agent_id'] <= 0) return 'Please select an agent.';
        if ($base['lead_guest_name'] === '') return 'Lead guest / passenger name is required.';
        if (($base['pax_adults'] + $base['pax_children'] + $base['pax_infants']) <= 0) {
            return 'At least one guest is required.';
        }
        return null;
    }

    /** Create a standalone multi-hotel booking and post it to the agent ledger. */
    public static function create(array $data): array {
        $base = self::collectBaseData($data);
        $err = self::validate($base);
        if ($err) return ['success' => false, 'message' => $err];

        $stayResult = self::normalizeStays((array)($data['stays'] ?? []));
        if (!$stayResult['success']) return $stayResult;
        $stays = $stayResult['stays'];
        $totals = self::calculateTotals($stays);
        $masterBookingIds = self::extractMasterBookingIds($data);

        $ownsTransaction = !Database::getConnection()->inTransaction();
        if ($ownsTransaction) Database::beginTransaction();
        try {
            $ref = 'HB-' . date('y') . '-' . strtoupper(bin2hex(random_bytes(3)));

            Database::execute(
                "INSERT INTO hotel_bookings (
                    booking_ref, booking_date, agent_id, total_nights, lead_guest_name, company_name,
                    pax_adults, pax_children, pax_infants, remarks,
                    transport_enabled, transport_type, transport_buy_rate, transport_sell_rate,
                    buy_total_sar, sell_total_sar,
                    created_by, created_at, updated_by, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())",
                [
                    $ref, $base['booking_date'], $base['agent_id'], $totals['total_nights'], $base['lead_guest_name'], $base['company_name'],
                    $base['pax_adults'], $base['pax_children'], $base['pax_infants'], $base['remarks'] ?: null,
                    $base['transport_enabled'], $base['transport_type'], $base['transport_buy_rate'], $base['transport_sell_rate'],
                    $totals['buy_total'], $totals['sell_total'], Session::getActor(), Session::getActor()
                ]
            );

            $id = Database::lastInsertId();
            self::insertStays($id, $stays);
            $linkedMasterBookings = self::syncMasterBookingLinks($id, $masterBookingIds);
            self::regenerateAutoTransport($id, $stays, $base, $linkedMasterBookings);

            if ($ownsTransaction) Database::commit();
            return [
                'success' => true,
                'message' => 'Hotel booking saved and posted to the agent ledger.',
                'booking_id' => $id,
                'booking_ref' => $ref
            ];
        } catch (Throwable $e) {
            if ($ownsTransaction) Database::rollBack();
            return ['success' => false, 'message' => 'Error creating hotel booking: ' . $e->getMessage()];
        }
    }

    /** Update an existing standalone hotel booking. */
    public static function update(int $id, array $data): array {
        if ($id <= 0) return ['success' => false, 'message' => 'Invalid booking ID.'];

        $base = self::collectBaseData($data);
        $err = self::validate($base);
        if ($err) return ['success' => false, 'message' => $err];

        $stayResult = self::normalizeStays((array)($data['stays'] ?? []));
        if (!$stayResult['success']) return $stayResult;
        $stays = $stayResult['stays'];
        $totals = self::calculateTotals($stays);
        $masterBookingIds = self::extractMasterBookingIds($data);

        $existing = Database::fetchOne("SELECT id FROM hotel_bookings WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$existing) return ['success' => false, 'message' => 'Hotel booking not found.'];

        $ownsTransaction = !Database::getConnection()->inTransaction();
        if ($ownsTransaction) Database::beginTransaction();
        try {
            Database::execute(
                "UPDATE hotel_bookings SET
                    booking_date = ?, agent_id = ?, total_nights = ?, lead_guest_name = ?, company_name = ?,
                    pax_adults = ?, pax_children = ?, pax_infants = ?, remarks = ?,
                    transport_enabled = ?, transport_type = ?, transport_buy_rate = ?, transport_sell_rate = ?,
                    buy_total_sar = ?, sell_total_sar = ?, updated_by = ?, updated_at = NOW()
                 WHERE id = ?",
                [
                    $base['booking_date'], $base['agent_id'], $totals['total_nights'], $base['lead_guest_name'], $base['company_name'],
                    $base['pax_adults'], $base['pax_children'], $base['pax_infants'], $base['remarks'] ?: null,
                    $base['transport_enabled'], $base['transport_type'], $base['transport_buy_rate'], $base['transport_sell_rate'],
                    $totals['buy_total'], $totals['sell_total'], Session::getActor(), $id
                ]
            );

            Database::execute("DELETE FROM hotel_booking_stays WHERE booking_id = ?", [$id]);
            self::insertStays($id, $stays);
            $linkedMasterBookings = self::syncMasterBookingLinks($id, $masterBookingIds);
            self::regenerateAutoTransport($id, $stays, $base, $linkedMasterBookings);

            if ($ownsTransaction) Database::commit();
            return ['success' => true, 'message' => 'Hotel booking updated successfully.'];
        } catch (Throwable $e) {
            if ($ownsTransaction) Database::rollBack();
            return ['success' => false, 'message' => 'Error updating hotel booking: ' . $e->getMessage()];
        }
    }

    private static function insertStays(int $bookingId, array $stays): void {
        $sql = "INSERT INTO hotel_booking_stays (
                    booking_id, city, hotel_name, room_type, meal_plan, confirmation_number,
                    checkin_date, checkout_date, nights, rooms, buy_rate_per_night, sell_rate_per_night,
                    buy_total, sell_total
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        foreach ($stays as $s) {
            Database::execute($sql, [
                $bookingId, $s['city'], $s['hotel_name'], $s['room_type'], $s['meal_plan'], $s['confirmation_number'] ?: null,
                $s['checkin_date'], $s['checkout_date'], $s['nights'], $s['rooms'], $s['buy_rate_per_night'], $s['sell_rate_per_night'],
                $s['buy_total'], $s['sell_total']
            ]);
        }
    }

    /** Replace this booking's linked Master Bookings and return the resolved (still-existing) rows. */
    private static function syncMasterBookingLinks(int $bookingId, array $masterBookingIds): array {
        Database::execute("DELETE FROM hotel_booking_master_links WHERE hotel_booking_id = ?", [$bookingId]);
        if (!$masterBookingIds) return [];

        $placeholders = implode(',', array_fill(0, count($masterBookingIds), '?'));
        $valid = Database::fetchAll(
            "SELECT id, booking_code, passenger_name, passport_number, flight_number, arrival_date, departure_date
             FROM master_bookings WHERE deleted_at IS NULL AND id IN ({$placeholders})",
            $masterBookingIds
        );
        foreach ($valid as $mb) {
            Database::execute(
                "INSERT IGNORE INTO hotel_booking_master_links (hotel_booking_id, master_booking_id, created_at) VALUES (?, ?, NOW())",
                [$bookingId, (int)$mb['id']]
            );
        }
        return $valid;
    }

    private static function cityAirportCode(string $city): string {
        static $map = ['Makkah' => 'MAK', 'Madinah' => 'MED', 'Jeddah' => 'JED', 'Taif' => 'TAI', 'Riyadh' => 'RUH'];
        if (isset($map[$city])) return $map[$city];
        $code = strtoupper(preg_replace('/[^A-Za-z]/', '', $city));
        return $code !== '' ? substr($code, 0, 3) : 'CITY';
    }

    /**
     * Auto-build the full ground-transport itinerary for a hotel booking:
     *   JED Airport -> first hotel city (on first check-in date)
     *   city(N) -> city(N+1) for every hotel-to-hotel change (on the checkout/checkin date they share)
     *   last hotel city -> JED Airport (on the final check-out / departure date)
     * Uses the assigned Master Booking(s)' flight numbers for the two airport legs when available.
     * Always clears any previously auto-generated legs for this booking first, so re-saving/editing
     * keeps the transport itinerary in sync with the current stays.
     */
    private static function regenerateAutoTransport(int $bookingId, array $stays, array $base, array $masterBookings): void {
        Database::execute(
            "UPDATE transport_bookings SET deleted_at = NOW(), updated_by = ?, updated_at = NOW()
             WHERE hotel_booking_id = ? AND auto_generated IN (1, 2) AND deleted_at IS NULL",
            [Session::getActor(), $bookingId]
        );

        if (empty($base['transport_enabled']) || !$stays) return;

        $paxNames = $masterBookings
            ? implode(', ', array_filter(array_column($masterBookings, 'passenger_name')))
            : $base['lead_guest_name'];
        $passportNumbers = $masterBookings
            ? (implode(', ', array_filter(array_column($masterBookings, 'passport_number'))) ?: null)
            : null;
        $paxCount = $masterBookings
            ? count($masterBookings)
            : max(1, $base['pax_adults'] + $base['pax_children'] + $base['pax_infants']);

        $arrivalFlight = null;
        $departureFlight = null;
        foreach ($masterBookings as $mb) {
            if (!empty($mb['flight_number'])) {
                if ($arrivalFlight === null) $arrivalFlight = $mb['flight_number'];
                $departureFlight = $mb['flight_number'];
            }
        }

        $vehicleType = $base['transport_type'];
        $buyRate = $base['transport_buy_rate'];
        $sellRate = $base['transport_sell_rate'];
        $singleMasterBookingId = count($masterBookings) === 1 ? (int)$masterBookings[0]['id'] : null;
        $actor = Session::getActor();

        $legs = [];
        $firstCity = self::cityAirportCode($stays[0]['city']);
        $legs[] = ['date' => $stays[0]['checkin_date'], 'route' => 'JED-' . $firstCity, 'flight' => $arrivalFlight];

        for ($i = 0; $i < count($stays) - 1; $i++) {
            $fromCity = self::cityAirportCode($stays[$i]['city']);
            $toCity = self::cityAirportCode($stays[$i + 1]['city']);
            if ($fromCity === $toCity) continue;
            $legs[] = ['date' => $stays[$i]['checkout_date'], 'route' => $fromCity . '-' . $toCity, 'flight' => null];
        }

        $lastCity = self::cityAirportCode($stays[count($stays) - 1]['city']);
        $legs[] = ['date' => $stays[count($stays) - 1]['checkout_date'], 'route' => $lastCity . '-JED', 'flight' => $departureFlight];

        $sql = "INSERT INTO transport_bookings (
                    master_booking_id, hotel_booking_id, agent_id, vendor_id, service_date, flight_number, terminal,
                    pax_name, passport_number, pax_count, vehicle_type, pickup_time, route_details,
                    buy_rate_sar, sell_rate_sar, status, auto_generated, created_by, created_at, updated_by, updated_at
                ) VALUES (?, ?, ?, NULL, ?, ?, 'Terminal 1', ?, ?, ?, ?, '09:00:00', ?, ?, ?, 'scheduled', 1, ?, NOW(), ?, NOW())";

        $overrides = $base['transport_route_overrides'] ?? [];
        $skipRoutes = $base['transport_skip_routes'] ?? [];
        foreach ($legs as $leg) {
            if (in_array($leg['route'], $skipRoutes, true)) continue;
            $o = $overrides[$leg['route']] ?? [];
            Database::execute($sql, [
                $singleMasterBookingId, $bookingId, $base['agent_id'],
                $leg['date'], $leg['flight'] ? strtoupper($leg['flight']) : null,
                $paxNames, $passportNumbers, $paxCount, $o['type'] ?? $vehicleType, $leg['route'],
                $o['buy'] ?? $buyRate, $o['sell'] ?? $sellRate, $actor, $actor
            ]);
        }

        // User-added custom routes (auto_generated = 2), re-created on every save like the itinerary legs.
        $customSql = str_replace("'scheduled', 1,", "'scheduled', 2,", $sql);
        foreach ($base['transport_custom_routes'] ?? [] as $c) {
            Database::execute($customSql, [
                $singleMasterBookingId, $bookingId, $base['agent_id'],
                $c['date'] ?? $stays[0]['checkin_date'], null,
                $paxNames, $passportNumbers, $paxCount, $c['type'] ?? $vehicleType, $c['route'],
                $c['buy'] ?? $buyRate, $c['sell'] ?? $sellRate, $actor, $actor
            ]);
        }
    }

    /** Get a fully hydrated booking (with its hotel stays + linked Master Bookings) for printing/editing. */
    public static function get(int $id): ?array {
        $b = Database::fetchOne(
            "SELECT hb.*, a.name AS agent_name, a.phone AS agent_phone, a.company_name AS agent_company
             FROM hotel_bookings hb
             JOIN agents a ON hb.agent_id = a.id
             WHERE hb.id = ? AND hb.deleted_at IS NULL",
            [$id]
        );
        if (!$b) return null;

        $b['stays'] = Database::fetchAll(
            "SELECT * FROM hotel_booking_stays WHERE booking_id = ? ORDER BY checkin_date ASC, id ASC",
            [$id]
        );
        $b['master_bookings'] = Database::fetchAll(
            "SELECT mb.id, mb.booking_code, mb.passenger_name, mb.passport_number, mb.flight_number, mb.arrival_date, mb.departure_date
             FROM hotel_booking_master_links l
             JOIN master_bookings mb ON mb.id = l.master_booking_id AND mb.deleted_at IS NULL
             WHERE l.hotel_booking_id = ?
             ORDER BY mb.id ASC",
            [$id]
        );
        $b['transport_legs'] = Database::fetchAll(
            "SELECT route_details, vehicle_type, buy_rate_sar, sell_rate_sar, service_date, auto_generated
             FROM transport_bookings
             WHERE hotel_booking_id = ? AND auto_generated IN (1, 2) AND deleted_at IS NULL
             ORDER BY service_date ASC, id ASC",
            [$id]
        );
        return $b;
    }

    /**
     * Rebuild the route-wise transport choices (overrides + unticked routes) from the saved
     * auto-generated legs, so converting to a voucher keeps exactly the same transfers.
     */
    private static function routeSettingsFromLegs(array $b): array {
        $allLegs = $b['transport_legs'] ?? [];
        $legs = array_values(array_filter($allLegs, static fn($l) => (int)$l['auto_generated'] === 1));
        $custom = [];
        foreach ($allLegs as $l) {
            if ((int)$l['auto_generated'] !== 2) continue;
            $custom[] = ['route' => $l['route_details'], 'date' => $l['service_date'], 'type' => $l['vehicle_type'],
                         'buy' => (string)(float)$l['buy_rate_sar'], 'sell' => (string)(float)$l['sell_rate_sar']];
        }
        $overrides = [];
        foreach ($legs as $leg) {
            $o = ['route' => strtoupper((string)$leg['route_details']), 'type' => '', 'buy' => '', 'sell' => ''];
            if ((string)$leg['vehicle_type'] !== (string)($b['transport_type'] ?: 'CAR')) $o['type'] = (string)$leg['vehicle_type'];
            if ((float)$leg['buy_rate_sar'] !== (float)$b['transport_buy_rate']) $o['buy'] = (string)(float)$leg['buy_rate_sar'];
            if ((float)$leg['sell_rate_sar'] !== (float)$b['transport_sell_rate']) $o['sell'] = (string)(float)$leg['sell_rate_sar'];
            if ($o['type'] !== '' || $o['buy'] !== '' || $o['sell'] !== '') $overrides[] = $o;
        }
        $skip = [];
        if (!empty($b['transport_enabled']) && $legs && !empty($b['stays'])) {
            $saved = array_map(static fn($l) => strtoupper((string)$l['route_details']), $legs);
            $cities = array_map(static fn($s) => self::cityAirportCode((string)$s['city']), $b['stays']);
            $routes = ['JED-' . $cities[0]];
            for ($i = 0; $i < count($cities) - 1; $i++) {
                if ($cities[$i] !== $cities[$i + 1]) $routes[] = $cities[$i] . '-' . $cities[$i + 1];
            }
            $routes[] = $cities[count($cities) - 1] . '-JED';
            $skip = array_values(array_diff(array_unique($routes), $saved));
        }
        return [$overrides, $skip, $custom];
    }

    /** Get all standalone hotel bookings with pagination. */
    public static function getAll(int $limit = 50): array {
        $limit = max(1, min(200, $limit));
        $bookings = Database::fetchAll(
            "SELECT hb.*, a.name AS agent_name
             FROM hotel_bookings hb
             JOIN agents a ON hb.agent_id = a.id
             WHERE hb.deleted_at IS NULL
             ORDER BY hb.id DESC
             LIMIT {$limit}"
        );

        if ($bookings) {
            $ids = array_column($bookings, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stayRows = Database::fetchAll(
                "SELECT * FROM hotel_booking_stays WHERE booking_id IN ({$placeholders}) ORDER BY checkin_date ASC, id ASC",
                $ids
            );
            $stayMap = [];
            foreach ($stayRows as $row) {
                $stayMap[(int)$row['booking_id']][] = $row;
            }

            $linkRows = Database::fetchAll(
                "SELECT l.hotel_booking_id, mb.passenger_name
                 FROM hotel_booking_master_links l
                 JOIN master_bookings mb ON mb.id = l.master_booking_id AND mb.deleted_at IS NULL
                 WHERE l.hotel_booking_id IN ({$placeholders})",
                $ids
            );
            $linkMap = [];
            foreach ($linkRows as $row) {
                $linkMap[(int)$row['hotel_booking_id']][] = $row['passenger_name'];
            }

            $transportRows = Database::fetchAll(
                "SELECT hotel_booking_id, COUNT(*) AS leg_count
                 FROM transport_bookings
                 WHERE hotel_booking_id IN ({$placeholders}) AND auto_generated IN (1, 2) AND deleted_at IS NULL
                 GROUP BY hotel_booking_id",
                $ids
            );
            $transportMap = [];
            foreach ($transportRows as $row) {
                $transportMap[(int)$row['hotel_booking_id']] = (int)$row['leg_count'];
            }

            foreach ($bookings as &$b) {
                $b['stays'] = $stayMap[(int)$b['id']] ?? [];
                $b['linked_master_bookings'] = $linkMap[(int)$b['id']] ?? [];
                $b['transport_leg_count'] = $transportMap[(int)$b['id']] ?? 0;
            }
            unset($b);
        }

        return $bookings;
    }

    /** Soft-delete a booking and retain its audit history; removes it from the agent ledger. */
    public static function delete(int $id): array {
        if ($id <= 0) return ['success' => false, 'message' => 'Invalid booking ID.'];
        $old = Database::fetchOne("SELECT * FROM hotel_bookings WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$old) return ['success' => false, 'message' => 'Hotel booking not found.'];
        Database::execute("UPDATE hotel_bookings SET deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE id = ?", [Session::getActor(), $id]);
        Database::execute("UPDATE transport_bookings SET deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE hotel_booking_id = ? AND deleted_at IS NULL", [Session::getActor(), $id]);
        Database::execute("INSERT INTO audit_logs (entity_type, entity_id, action, old_values, new_values, created_by, created_at) VALUES ('hotel_booking', ?, 'deleted', ?, NULL, ?, NOW())", [$id, json_encode($old, JSON_UNESCAPED_UNICODE), Session::getActor()]);
        return ['success' => true, 'message' => 'Hotel booking deleted successfully.'];
    }

    /**
     * Convert a standalone "Only Hotel Booking" into a "Build Hotel Voucher".
     * Carries over the agent, hotel stays, transport auto-generation settings, and the
     * assigned Master Booking(s) — pulling each linked passenger's name/passport into the
     * voucher's Mutamers manifest and their flight number/dates into the outbound/return
     * flight legs when available. The original booking is then retired (soft-deleted, with
     * its auto-generated transport legs) so the agent ledger isn't debited twice for the
     * same stay; any details the source booking didn't have (package name, structured
     * "from/to" cities, etc.) are simply left blank for the user to fill in on the new voucher.
     */
    public static function convertToVoucher(int $id): array {
        if ($id <= 0) return ['success' => false, 'message' => 'Invalid booking ID.'];
        $b = self::get($id);
        if (!$b) return ['success' => false, 'message' => 'Hotel booking not found.'];

        $masterBookings = $b['master_bookings'] ?? [];

        $stays = array_map(static function (array $s): array {
            return [
                'city' => $s['city'],
                'hotel_name' => $s['hotel_name'],
                'room_type' => $s['room_type'],
                'meal_plan' => $s['meal_plan'],
                'confirmation_number' => $s['confirmation_number'],
                'checkin_date' => $s['checkin_date'],
                'checkout_date' => $s['checkout_date'],
                'nights' => (int)$s['nights'],
                'buy_rate_per_night' => (float)$s['buy_rate_per_night'],
                'sell_rate_per_night' => (float)$s['sell_rate_per_night'],
            ];
        }, $b['stays'] ?? []);

        $mutamers = [];
        foreach ($masterBookings as $mb) {
            if (empty($mb['passenger_name']) || empty($mb['passport_number'])) continue;
            $mutamers[] = [
                'name' => $mb['passenger_name'],
                'passport' => $mb['passport_number'],
                'gender' => 'M',
                'is_adult' => 'Adult',
                'bed_assigned' => 'Yes',
            ];
        }

        $arrivalFlight = null; $arrivalDate = null;
        $departureFlight = null; $departureDate = null;
        foreach ($masterBookings as $mb) {
            if (!empty($mb['flight_number'])) {
                if ($arrivalFlight === null) { $arrivalFlight = $mb['flight_number']; $arrivalDate = $mb['arrival_date'] ?? null; }
                $departureFlight = $mb['flight_number'];
                $departureDate = $mb['departure_date'] ?? null;
            }
        }

        $totalPax = $masterBookings
            ? count($masterBookings)
            : max(1, (int)$b['pax_adults'] + (int)$b['pax_children'] + (int)$b['pax_infants']);

        [$routeOverrides, $skipRoutes, $customRoutes] = self::routeSettingsFromLegs($b);
        $data = [
            'agent_id' => (int)$b['agent_id'],
            'family_head' => $b['lead_guest_name'],
            'company_name' => $b['company_name'] ?? '',
            'package_name' => '',
            'total_pax' => $totalPax,
            'total_beds' => max(1, (int)$b['pax_adults'] + (int)$b['pax_children']),
            'voucher_date' => $b['booking_date'],
            'flight_out_no' => $arrivalFlight ?? '',
            'flight_out_arr_date' => $arrivalDate,
            'flight_ret_no' => $departureFlight ?? '',
            'flight_ret_dep_date' => $departureDate,
            'stays' => $stays,
            'mutamers' => $mutamers,
            'master_booking_ids' => array_column($masterBookings, 'id'),
            'transport_enabled' => !empty($b['transport_enabled']),
            'transport_type_auto' => $b['transport_type'] ?: 'CAR',
            'transport_buy_rate' => (float)$b['transport_buy_rate'],
            'transport_sell_rate' => (float)$b['transport_sell_rate'],
            'transport_route_overrides' => $routeOverrides,
            'transport_skip_routes' => $skipRoutes,
            'transport_custom_routes' => $customRoutes,
        ];

        $ownsTransaction = !Database::getConnection()->inTransaction();
        if ($ownsTransaction) Database::beginTransaction();
        try {
            $result = VoucherController::create($data);
            if (!$result['success']) {
                if ($ownsTransaction) Database::rollBack();
                return $result;
            }

            self::markConverted($id, 'hotel_voucher', (int)$result['voucher_id']);

            if ($ownsTransaction) Database::commit();
            return [
                'success' => true,
                'message' => 'Hotel booking converted to a hotel voucher. Review it for any missing details.',
                'voucher_id' => $result['voucher_id'],
                'voucher_no' => $result['voucher_no'],
            ];
        } catch (Throwable $e) {
            if ($ownsTransaction) Database::rollBack();
            return ['success' => false, 'message' => 'Conversion error: ' . $e->getMessage()];
        }
    }

    /** Soft-delete this booking (and its auto transport legs) after it has been converted into another record type. */
    private static function markConverted(int $id, string $convertedToType, int $convertedToId): void {
        $old = Database::fetchOne("SELECT * FROM hotel_bookings WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$old) return;
        Database::execute("UPDATE hotel_bookings SET deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE id = ?", [Session::getActor(), $id]);
        Database::execute("UPDATE transport_bookings SET deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE hotel_booking_id = ? AND deleted_at IS NULL", [Session::getActor(), $id]);
        Database::execute(
            "INSERT INTO audit_logs (entity_type, entity_id, action, old_values, new_values, created_by, created_at) VALUES ('hotel_booking', ?, 'converted', ?, ?, ?, NOW())",
            [$id, json_encode($old, JSON_UNESCAPED_UNICODE), json_encode(['converted_to' => $convertedToType, 'id' => $convertedToId], JSON_UNESCAPED_UNICODE), Session::getActor()]
        );
    }
}
