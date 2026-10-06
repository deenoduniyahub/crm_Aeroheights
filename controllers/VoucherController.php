<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Session.php';
require_once __DIR__ . '/HotelBookingController.php';

/**
 * Hotel Voucher Controller
 * Multi-stay accommodation, per-night costing, custom room types and passenger manifests.
 */
class VoucherController {

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

            $city = (string)($stay['city'] ?? 'Makkah');
            if (!in_array($city, ['Makkah', 'Madinah'], true)) {
                $city = 'Makkah';
            }

            $roomType = trim((string)($stay['room_type'] ?? 'Double Bed'));
            if ($roomType === '' || $roomType === '__custom__') {
                $roomType = trim((string)($stay['custom_room_type'] ?? ''));
            }
            if ($roomType === '') {
                $roomType = 'Double Bed';
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
                    // Keep the supplied nights if dates are invalid; validation below will report it.
                }
            }

            $buyRate = max(0.0, round((float)($stay['buy_rate_per_night'] ?? 0), 2));
            $sellRate = max(0.0, round((float)($stay['sell_rate_per_night'] ?? 0), 2));

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
                    'message' => 'Stay #' . ((int)$index + 1) . ': ' . implode(', ', $errors) . '.'
                ];
            }

            $normalized[] = [
                'city' => $city,
                'hotel_name' => $hotelName,
                'room_type' => $roomType,
                'meal_plan' => $mealPlan,
                'confirmation_number' => $confirmation,
                'checkin_date' => $checkin,
                'checkout_date' => $checkout,
                'nights' => $nights,
                'buy_rate_per_night' => $buyRate,
                'sell_rate_per_night' => $sellRate,
            ];
        }

        // Hotel is optional: a voucher with no stays prints "Self" as the hotel (see print view).
        return ['success' => true, 'stays' => $normalized];
    }

    private static function normalizeMutamers(array $mutamers): array {
        $normalized = [];
        foreach ($mutamers as $m) {
            if (!is_array($m)) {
                continue;
            }
            $name = trim((string)($m['name'] ?? ''));
            $passport = strtoupper(trim((string)($m['passport'] ?? '')));
            if ($name === '' || $passport === '') {
                continue;
            }

            $gender = ($m['gender'] ?? 'M') === 'F' ? 'F' : 'M';
            $adultValue = $m['is_adult'] ?? 'Adult';
            $adult = in_array($adultValue, ['Adult', 'Child', 'Infant'], true)
                ? $adultValue : 'Adult';
            $bed = ($m['bed_assigned'] ?? 'Yes') === 'No' ? 'No' : 'Yes';

            $normalized[] = [
                'name' => $name,
                'passport' => $passport,
                'gender' => $gender,
                'is_adult' => $adult,
                'bed_assigned' => $bed,
            ];
        }
        return $normalized;
    }

    private static function calculateTotals(array $stays): array {
        $totalNights = 0;
        $buy = 0.0;
        $sell = 0.0;

        foreach ($stays as $stay) {
            $nights = (int)$stay['nights'];
            $totalNights += $nights;
            $buy += $nights * (float)$stay['buy_rate_per_night'];
            $sell += $nights * (float)$stay['sell_rate_per_night'];
        }

        return [
            'total_nights' => $totalNights,
            'buy_total' => round($buy, 2),
            'sell_total' => round($sell, 2),
        ];
    }

    private static function collectBaseData(array $data): array {
        return [
            'master_booking_id' => !empty($data['master_booking_id']) ? (int)$data['master_booking_id'] : null,
            'agent_id' => (int)($data['agent_id'] ?? 0),
            'vendor_id' => !empty($data['vendor_id']) ? (int)$data['vendor_id'] : null,
            'voucher_date' => !empty($data['voucher_date']) ? $data['voucher_date'] : date('Y-m-d'),
            'family_head' => trim((string)($data['family_head'] ?? '')),
            'company_name' => trim((string)($data['company_name'] ?? '')) ?: 'Aeroheights Travels & Tours',
            'package_name' => trim((string)($data['package_name'] ?? '')),
            'total_pax' => max(1, (int)($data['total_pax'] ?? 1)),
            'total_beds' => max(1, (int)($data['total_beds'] ?? 1)),
            'transporter_info' => trim((string)($data['transporter_info'] ?? 'Company Transport')) ?: 'Company Transport',
            'transport_type' => trim((string)($data['transport_type'] ?? '')),
            'special_instructions' => trim((string)($data['special_instructions'] ?? 'HOTEL ACCOMMODATION AS PER VOUCHER')),
            'flight_out_no' => strtoupper(trim((string)($data['flight_out_no'] ?? ''))),
            'flight_out_from' => trim((string)($data['flight_out_from'] ?? '')),
            'flight_out_to' => trim((string)($data['flight_out_to'] ?? '')),
            'flight_out_dep_date' => !empty($data['flight_out_dep_date']) ? $data['flight_out_dep_date'] : null,
            'flight_out_dep_time' => !empty($data['flight_out_dep_time']) ? $data['flight_out_dep_time'] : null,
            'flight_out_arr_date' => !empty($data['flight_out_arr_date']) ? $data['flight_out_arr_date'] : null,
            'flight_out_arr_time' => !empty($data['flight_out_arr_time']) ? $data['flight_out_arr_time'] : null,
            'flight_ret_no' => strtoupper(trim((string)($data['flight_ret_no'] ?? ''))),
            'flight_ret_from' => trim((string)($data['flight_ret_from'] ?? '')),
            'flight_ret_to' => trim((string)($data['flight_ret_to'] ?? '')),
            'flight_ret_dep_date' => !empty($data['flight_ret_dep_date']) ? $data['flight_ret_dep_date'] : null,
            'flight_ret_dep_time' => !empty($data['flight_ret_dep_time']) ? $data['flight_ret_dep_time'] : null,
            'flight_ret_arr_date' => !empty($data['flight_ret_arr_date']) ? $data['flight_ret_arr_date'] : null,
            'flight_ret_arr_time' => !empty($data['flight_ret_arr_time']) ? $data['flight_ret_arr_time'] : null,
            'transport_auto_enabled' => !empty($data['transport_enabled']) ? 1 : 0,
            'transport_auto_type' => trim((string)($data['transport_type_auto'] ?? '')) ?: 'CAR',
            'transport_auto_buy_rate' => max(0.0, round((float)($data['transport_buy_rate'] ?? 0), 2)),
            'transport_auto_sell_rate' => max(0.0, round((float)($data['transport_sell_rate'] ?? 0), 2)),
            'transport_route_overrides' => self::normalizeRouteOverrides((array)($data['transport_route_overrides'] ?? [])),
            // Routes the user unticked in "Route-wise Transport" - no transfer is created for these legs.
            'transport_skip_routes' => self::normalizeSkipRoutes((array)($data['transport_skip_routes'] ?? [])),
            'transport_custom_routes' => self::normalizeCustomRoutes((array)($data['transport_custom_routes'] ?? [])),
        ];
    }

    /**
     * Per-route transport overrides keyed by route (e.g. "MAK-JED" => STARIA), so a single leg can use a
     * different vehicle/rate than the default. Blank fields fall back to the default Transport Type / rates.
     */
    public static function normalizeRouteOverrides(array $rows): array {
        $overrides = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $route = strtoupper(trim((string)($row['route'] ?? '')));
            if ($route === '') continue;
            $type = trim((string)($row['type'] ?? ''));
            $buy = trim((string)($row['buy'] ?? ''));
            $sell = trim((string)($row['sell'] ?? ''));
            if ($type === '' && $buy === '' && $sell === '') continue;
            $overrides[$route] = [
                'type' => $type !== '' ? $type : null,
                'buy' => is_numeric($buy) ? max(0.0, round((float)$buy, 2)) : null,
                'sell' => is_numeric($sell) ? max(0.0, round((float)$sell, 2)) : null,
            ];
        }
        return $overrides;
    }

    /**
     * Extra user-added transfers ("+ Add Route", e.g. a Ziyarat or MAK-TAI) with their own date/vehicle/rates.
     * Blank vehicle/rates fall back to the default Transport Type / rates; a blank date falls back to the first check-in.
     */
    public static function normalizeCustomRoutes(array $rows): array {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $route = strtoupper(trim(preg_replace('/\s+/', ' ', (string)($row['route'] ?? ''))));
            if ($route === '') continue;
            $date = trim((string)($row['date'] ?? ''));
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            $buy = trim((string)($row['buy'] ?? ''));
            $sell = trim((string)($row['sell'] ?? ''));
            $out[] = [
                'route' => substr($route, 0, 255),
                'date' => $d && $d->format('Y-m-d') === $date ? $date : null,
                'type' => trim((string)($row['type'] ?? '')) ?: null,
                'buy' => is_numeric($buy) ? max(0.0, round((float)$buy, 2)) : null,
                'sell' => is_numeric($sell) ? max(0.0, round((float)$sell, 2)) : null,
            ];
        }
        return $out;
    }

    /** Routes the user unticked in "Route-wise Transport" (e.g. ["MAK-MED"]). */
    public static function normalizeSkipRoutes(array $routes): array {
        return array_values(array_unique(array_filter(array_map(static fn($r) => strtoupper(trim((string)$r)), $routes))));
    }

    /**
     * Without hotel stays there is no itinerary to date the transfers from, so every transfer
     * must be entered as a route with its own date.
     */
    private static function validateTransportWithoutStays(array $stays, array $base): ?array {
        if ($stays || empty($base['transport_auto_enabled'])) return null;
        if (!$base['transport_custom_routes']) {
            return ['success' => false, 'message' => 'No hotel added: add at least one transport route with its date (e.g. JED-MAK).'];
        }
        foreach ($base['transport_custom_routes'] as $i => $c) {
            if (empty($c['date'])) {
                return ['success' => false, 'message' => 'No hotel added: transport date is required for route #' . ($i + 1) . ' (' . $c['route'] . ').'];
            }
        }
        return null;
    }

    private static function extractMasterBookingIds(array $data): array {
        $ids = array_map('intval', (array)($data['master_booking_ids'] ?? []));
        return array_values(array_unique(array_filter($ids, static fn($id) => $id > 0)));
    }

    /** Create a hotel voucher. */
    public static function create(array $data): array {
        $base = self::collectBaseData($data);
        if ($base['agent_id'] <= 0 || $base['family_head'] === '') {
            return ['success' => false, 'message' => 'Agent and Family Head Name are required.'];
        }

        $stayResult = self::normalizeStays((array)($data['stays'] ?? []));
        if (!$stayResult['success']) return $stayResult;
        $stays = $stayResult['stays'];
        if ($error = self::validateTransportWithoutStays($stays, $base)) return $error;
        $mutamers = self::normalizeMutamers((array)($data['mutamers'] ?? []));
        $totals = self::calculateTotals($stays);
        $masterBookingIds = self::extractMasterBookingIds($data);

        $ownsTransaction = !Database::getConnection()->inTransaction();
        if ($ownsTransaction) Database::beginTransaction();
        try {
            $voucherNo = 'AHT-' . date('y') . '-' . strtoupper(bin2hex(random_bytes(3)));

            Database::execute(
                "INSERT INTO hotel_vouchers (
                    voucher_no, voucher_date, master_booking_id, agent_id, vendor_id, family_head, company_name, package_name,
                    total_pax, total_beds, total_nights, transporter_info, transport_type,
                    flight_out_no, flight_out_from, flight_out_to, flight_out_dep_date, flight_out_dep_time, flight_out_arr_date, flight_out_arr_time,
                    flight_ret_no, flight_ret_from, flight_ret_to, flight_ret_dep_date, flight_ret_dep_time, flight_ret_arr_date, flight_ret_arr_time,
                    buy_rate_sar, sell_rate_sar, special_instructions,
                    transport_auto_enabled, transport_auto_type, transport_auto_buy_rate, transport_auto_sell_rate,
                    created_by, created_at, updated_by, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())",
                [
                    $voucherNo, $base['voucher_date'], $base['master_booking_id'], $base['agent_id'], $base['vendor_id'], $base['family_head'], $base['company_name'], $base['package_name'],
                    $base['total_pax'], $base['total_beds'], $totals['total_nights'], $base['transporter_info'], $base['transport_type'],
                    $base['flight_out_no'], $base['flight_out_from'], $base['flight_out_to'], $base['flight_out_dep_date'], $base['flight_out_dep_time'], $base['flight_out_arr_date'], $base['flight_out_arr_time'],
                    $base['flight_ret_no'], $base['flight_ret_from'], $base['flight_ret_to'], $base['flight_ret_dep_date'], $base['flight_ret_dep_time'], $base['flight_ret_arr_date'], $base['flight_ret_arr_time'],
                    $totals['buy_total'], $totals['sell_total'], $base['special_instructions'],
                    $base['transport_auto_enabled'], $base['transport_auto_type'], $base['transport_auto_buy_rate'], $base['transport_auto_sell_rate'],
                    Session::getActor(), Session::getActor()
                ]
            );

            $voucherId = Database::lastInsertId();
            self::insertStays($voucherId, $stays);
            self::insertMutamers($voucherId, $mutamers);
            $linkedMasterBookings = self::syncMasterBookingLinks($voucherId, $masterBookingIds);
            self::regenerateAutoTransport($voucherId, $stays, $base, $linkedMasterBookings);

            if ($ownsTransaction) Database::commit();
            return [
                'success' => true,
                'message' => 'Hotel voucher generated successfully.',
                'voucher_id' => $voucherId,
                'voucher_no' => $voucherNo
            ];
        } catch (Throwable $e) {
            if ($ownsTransaction) Database::rollBack();
            return ['success' => false, 'message' => 'Voucher creation error: ' . $e->getMessage()];
        }
    }

    /** Update an existing hotel voucher. */
    public static function update(int $id, array $data): array {
        if ($id <= 0) return ['success' => false, 'message' => 'Invalid voucher ID.'];

        $base = self::collectBaseData($data);
        if ($base['agent_id'] <= 0 || $base['family_head'] === '') {
            return ['success' => false, 'message' => 'Agent and Family Head Name are required.'];
        }

        $stayResult = self::normalizeStays((array)($data['stays'] ?? []));
        if (!$stayResult['success']) return $stayResult;
        $stays = $stayResult['stays'];
        if ($error = self::validateTransportWithoutStays($stays, $base)) return $error;
        $mutamers = self::normalizeMutamers((array)($data['mutamers'] ?? []));
        $totals = self::calculateTotals($stays);
        $masterBookingIds = self::extractMasterBookingIds($data);

        $ownsTransaction = !Database::getConnection()->inTransaction();
        if ($ownsTransaction) Database::beginTransaction();
        try {
            Database::execute(
                "UPDATE hotel_vouchers SET
                    voucher_date = ?, agent_id = ?, vendor_id = ?, family_head = ?, company_name = ?, package_name = ?,
                    total_pax = ?, total_beds = ?, total_nights = ?, transporter_info = ?, transport_type = ?,
                    flight_out_no = ?, flight_out_from = ?, flight_out_to = ?, flight_out_dep_date = ?, flight_out_dep_time = ?, flight_out_arr_date = ?, flight_out_arr_time = ?,
                    flight_ret_no = ?, flight_ret_from = ?, flight_ret_to = ?, flight_ret_dep_date = ?, flight_ret_dep_time = ?, flight_ret_arr_date = ?, flight_ret_arr_time = ?,
                    buy_rate_sar = ?, sell_rate_sar = ?, special_instructions = ?,
                    transport_auto_enabled = ?, transport_auto_type = ?, transport_auto_buy_rate = ?, transport_auto_sell_rate = ?,
                    updated_by = ?, updated_at = NOW()
                WHERE id = ?",
                [
                    $base['voucher_date'], $base['agent_id'], $base['vendor_id'], $base['family_head'], $base['company_name'], $base['package_name'],
                    $base['total_pax'], $base['total_beds'], $totals['total_nights'], $base['transporter_info'], $base['transport_type'],
                    $base['flight_out_no'], $base['flight_out_from'], $base['flight_out_to'], $base['flight_out_dep_date'], $base['flight_out_dep_time'], $base['flight_out_arr_date'], $base['flight_out_arr_time'],
                    $base['flight_ret_no'], $base['flight_ret_from'], $base['flight_ret_to'], $base['flight_ret_dep_date'], $base['flight_ret_dep_time'], $base['flight_ret_arr_date'], $base['flight_ret_arr_time'],
                    $totals['buy_total'], $totals['sell_total'], $base['special_instructions'],
                    $base['transport_auto_enabled'], $base['transport_auto_type'], $base['transport_auto_buy_rate'], $base['transport_auto_sell_rate'],
                    Session::getActor(), $id
                ]
            );

            Database::execute("DELETE FROM voucher_stays WHERE voucher_id = ?", [$id]);
            Database::execute("DELETE FROM voucher_mutamers WHERE voucher_id = ?", [$id]);
            self::insertStays($id, $stays);
            self::insertMutamers($id, $mutamers);
            $linkedMasterBookings = self::syncMasterBookingLinks($id, $masterBookingIds);
            self::regenerateAutoTransport($id, $stays, $base, $linkedMasterBookings);

            if ($ownsTransaction) Database::commit();
            return ['success' => true, 'message' => 'Hotel voucher updated successfully.'];
        } catch (Throwable $e) {
            if ($ownsTransaction) Database::rollBack();
            return ['success' => false, 'message' => 'Error updating voucher: ' . $e->getMessage()];
        }
    }

    private static function insertStays(int $voucherId, array $stays): void {
        $sql = "INSERT INTO voucher_stays (
                    voucher_id, city, hotel_name, room_type, meal_plan,
                    confirmation_number, checkin_date, checkout_date, nights,
                    buy_rate_per_night, sell_rate_per_night
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        foreach ($stays as $s) {
            Database::execute($sql, [
                $voucherId,
                $s['city'],
                $s['hotel_name'],
                $s['room_type'],
                $s['meal_plan'],
                $s['confirmation_number'],
                $s['checkin_date'],
                $s['checkout_date'],
                $s['nights'],
                $s['buy_rate_per_night'],
                $s['sell_rate_per_night']
            ]);
        }
    }

    private static function insertMutamers(int $voucherId, array $mutamers): void {
        $sql = "INSERT INTO voucher_mutamers (
                    voucher_id, mutamer_name, passport_number, gender, is_adult, bed_assigned
                ) VALUES (?, ?, ?, ?, ?, ?)";

        foreach ($mutamers as $m) {
            Database::execute($sql, [
                $voucherId, $m['name'], $m['passport'], $m['gender'], $m['is_adult'], $m['bed_assigned']
            ]);
        }
    }

    /** Replace this voucher's linked Master Bookings and return the resolved (still-existing) rows. */
    private static function syncMasterBookingLinks(int $voucherId, array $masterBookingIds): array {
        Database::execute("DELETE FROM hotel_voucher_master_links WHERE voucher_id = ?", [$voucherId]);
        if (!$masterBookingIds) return [];

        $placeholders = implode(',', array_fill(0, count($masterBookingIds), '?'));
        $valid = Database::fetchAll(
            "SELECT id, booking_code, passenger_name, passport_number, flight_number, arrival_date, departure_date
             FROM master_bookings WHERE deleted_at IS NULL AND id IN ({$placeholders})",
            $masterBookingIds
        );
        foreach ($valid as $mb) {
            Database::execute(
                "INSERT IGNORE INTO hotel_voucher_master_links (voucher_id, master_booking_id, created_at) VALUES (?, ?, NOW())",
                [$voucherId, (int)$mb['id']]
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

    /** Departure transfers pick up this many hours before the return flight takes off. */
    private const DEPARTURE_PICKUP_HOURS_BEFORE = 5;

    /** Combine a flight's date + time into [date, time], optionally shifted by whole hours (null if either is missing). */
    private static function flightPickup(?string $date, ?string $time, int $shiftHours = 0): ?array {
        if (!$date || !$time) return null;
        try {
            $at = new DateTimeImmutable($date . ' ' . $time);
        } catch (Exception $e) {
            return null;
        }
        if ($shiftHours) $at = $at->modify(($shiftHours > 0 ? '+' : '') . $shiftHours . ' hours');
        return ['date' => $at->format('Y-m-d'), 'time' => $at->format('H:i:s')];
    }

    /**
     * The flight-based pickup for an airport leg dated $legDate, or null when the flight is more than a day
     * away from that date (stale or unrelated flight details shouldn't move the transfer).
     */
    private static function pickupNear(?array $pickup, string $legDate): ?array {
        if (!$pickup) return null;
        $days = abs((strtotime($pickup['date']) - strtotime($legDate)) / 86400);
        return $days <= 1 ? $pickup : null;
    }

    /**
     * Auto-build the full ground-transport itinerary for a hotel voucher (mirrors the
     * "Only Hotel Booking" auto-transport builder):
     *   JED Airport -> first stay city (on first check-in date)
     *   city(N) -> city(N+1) for every hotel-to-hotel change (on the checkout/checkin date they share)
     *   last stay city -> JED Airport (on the final check-out date)
     * Airport pickup leg: Master Booking flight (the PAK->KSA flight), else the voucher's outbound flight;
     * picked up at the outbound flight's landing date/time.
     * Airport drop leg: the voucher's return flight, else the Master Booking flight; picked up
     * DEPARTURE_PICKUP_HOURS_BEFORE hours before the return flight departs (may fall on the previous day).
     * Other legs keep the default 09:00 pickup.
     * Always clears any previously auto-generated legs for this voucher first, so re-saving/editing
     * keeps the transport itinerary in sync with the current stays.
     */
    private static function regenerateAutoTransport(int $voucherId, array $stays, array $base, array $masterBookings): void {
        Database::execute(
            "UPDATE transport_bookings SET deleted_at = NOW(), updated_by = ?, updated_at = NOW()
             WHERE voucher_id = ? AND auto_generated IN (1, 2) AND deleted_at IS NULL",
            [Session::getActor(), $voucherId]
        );

        if (empty($base['transport_auto_enabled'])) return;

        $paxNames = $masterBookings
            ? implode(', ', array_filter(array_column($masterBookings, 'passenger_name')))
            : $base['family_head'];
        $passportNumbers = $masterBookings
            ? (implode(', ', array_filter(array_column($masterBookings, 'passport_number'))) ?: null)
            : null;
        $paxCount = $masterBookings ? count($masterBookings) : max(1, $base['total_pax']);

        $masterFlight = null;
        foreach ($masterBookings as $mb) {
            if (!empty($mb['flight_number'])) { $masterFlight = $mb['flight_number']; break; }
        }
        $arrivalFlight = $masterFlight ?: ($base['flight_out_no'] ?: null);
        $departureFlight = ($base['flight_ret_no'] ?: null) ?: $masterFlight;
        $arrivalPickup = self::flightPickup($base['flight_out_arr_date'], $base['flight_out_arr_time']);
        $departurePickup = self::flightPickup($base['flight_ret_dep_date'], $base['flight_ret_dep_time'], -self::DEPARTURE_PICKUP_HOURS_BEFORE);

        $vehicleType = $base['transport_auto_type'];
        $buyRate = $base['transport_auto_buy_rate'];
        $sellRate = $base['transport_auto_sell_rate'];
        $singleMasterBookingId = count($masterBookings) === 1 ? (int)$masterBookings[0]['id'] : null;
        $actor = Session::getActor();

        // No hotel stays -> no itinerary legs; only the user-entered (dated) routes below are created.
        $legs = [];
        if ($stays) {
            $firstCity = self::cityAirportCode($stays[0]['city']);
            $legs[] = ['date' => $stays[0]['checkin_date'], 'route' => 'JED-' . $firstCity, 'flight' => $arrivalFlight,
                       'pickup' => self::pickupNear($arrivalPickup, $stays[0]['checkin_date'])];

            for ($i = 0; $i < count($stays) - 1; $i++) {
                $fromCity = self::cityAirportCode($stays[$i]['city']);
                $toCity = self::cityAirportCode($stays[$i + 1]['city']);
                if ($fromCity === $toCity) continue;
                $legs[] = ['date' => $stays[$i]['checkout_date'], 'route' => $fromCity . '-' . $toCity, 'flight' => null];
            }

            $lastCity = self::cityAirportCode($stays[count($stays) - 1]['city']);
            $lastCheckout = $stays[count($stays) - 1]['checkout_date'];
            $legs[] = ['date' => $lastCheckout, 'route' => $lastCity . '-JED', 'flight' => $departureFlight,
                       'pickup' => self::pickupNear($departurePickup, $lastCheckout)];
        }

        $sql = "INSERT INTO transport_bookings (
                    master_booking_id, voucher_id, agent_id, vendor_id, service_date, flight_number, terminal,
                    pax_name, passport_number, pax_count, vehicle_type, pickup_time, route_details,
                    buy_rate_sar, sell_rate_sar, status, auto_generated, created_by, created_at, updated_by, updated_at
                ) VALUES (?, ?, ?, NULL, ?, ?, 'Terminal 1', ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', 1, ?, NOW(), ?, NOW())";

        $overrides = $base['transport_route_overrides'] ?? [];
        $skipRoutes = $base['transport_skip_routes'] ?? [];
        foreach ($legs as $leg) {
            if (in_array($leg['route'], $skipRoutes, true)) continue;
            $o = $overrides[$leg['route']] ?? [];
            $pickup = $leg['pickup'] ?? null;
            Database::execute($sql, [
                $singleMasterBookingId, $voucherId, $base['agent_id'],
                $pickup['date'] ?? $leg['date'], $leg['flight'] ? strtoupper($leg['flight']) : null,
                $paxNames, $passportNumbers, $paxCount, $o['type'] ?? $vehicleType, $pickup['time'] ?? '09:00:00', $leg['route'],
                $o['buy'] ?? $buyRate, $o['sell'] ?? $sellRate, $actor, $actor
            ]);
        }

        // User-added custom routes are stored with auto_generated = 2 so they are re-created on every save
        // (like the itinerary legs) but can be told apart when the voucher is edited again.
        $customSql = str_replace("'scheduled', 1,", "'scheduled', 2,", $sql);
        foreach ($base['transport_custom_routes'] ?? [] as $c) {
            // Without stays the routes stand in for the itinerary, so airport legs carry the flight numbers.
            $flight = null;
            $pickup = null;
            $date = $c['date'] ?? $stays[0]['checkin_date'];
            if (!$stays) {
                if (str_starts_with($c['route'], 'JED-')) {
                    $flight = $arrivalFlight;
                    $pickup = self::pickupNear($arrivalPickup, $date);
                } elseif (str_ends_with($c['route'], '-JED')) {
                    $flight = $departureFlight;
                    $pickup = self::pickupNear($departurePickup, $date);
                }
            }
            Database::execute($customSql, [
                $singleMasterBookingId, $voucherId, $base['agent_id'],
                $pickup['date'] ?? $date, $flight ? strtoupper($flight) : null,
                $paxNames, $passportNumbers, $paxCount, $c['type'] ?? $vehicleType, $pickup['time'] ?? '09:00:00', $c['route'],
                $c['buy'] ?? $buyRate, $c['sell'] ?? $sellRate, $actor, $actor
            ]);
        }
    }

    /** Get a fully hydrated voucher for printing/editing. */
    public static function get(int $voucherId): ?array {
        $voucher = Database::fetchOne(
            "SELECT hv.*, a.name AS agent_name, a.phone AS agent_phone, v.name AS vendor_name
             FROM hotel_vouchers hv
             JOIN agents a ON hv.agent_id = a.id
             LEFT JOIN vendors v ON hv.vendor_id = v.id
             WHERE hv.id = ? AND hv.deleted_at IS NULL",
            [$voucherId]
        );
        if (!$voucher) return null;

        $voucher['stays'] = Database::fetchAll(
            "SELECT * FROM voucher_stays WHERE voucher_id = ? ORDER BY checkin_date ASC, id ASC",
            [$voucherId]
        );
        $voucher['mutamers'] = Database::fetchAll(
            "SELECT * FROM voucher_mutamers WHERE voucher_id = ? ORDER BY id ASC",
            [$voucherId]
        );
        $voucher['master_bookings'] = Database::fetchAll(
            "SELECT mb.id, mb.booking_code, mb.passenger_name, mb.passport_number, mb.flight_number, mb.arrival_date, mb.departure_date
             FROM hotel_voucher_master_links l
             JOIN master_bookings mb ON mb.id = l.master_booking_id AND mb.deleted_at IS NULL
             WHERE l.voucher_id = ?
             ORDER BY mb.id ASC",
            [$voucherId]
        );
        $voucher['transport_legs'] = Database::fetchAll(
            "SELECT route_details, vehicle_type, buy_rate_sar, sell_rate_sar, service_date, auto_generated
             FROM transport_bookings
             WHERE voucher_id = ? AND auto_generated IN (1, 2) AND deleted_at IS NULL
             ORDER BY service_date ASC, id ASC",
            [$voucherId]
        );
        return $voucher;
    }

    /** Get all vouchers with pagination. */
    public static function getAll(int $limit = 50): array {
        $limit = max(1, min(200, $limit));
        $vouchers = Database::fetchAll(
            "SELECT hv.*, a.name AS agent_name
             FROM hotel_vouchers hv
             JOIN agents a ON hv.agent_id = a.id
             WHERE hv.deleted_at IS NULL
             ORDER BY hv.id DESC
             LIMIT {$limit}"
        );

        if ($vouchers) {
            $ids = array_column($vouchers, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $linkRows = Database::fetchAll(
                "SELECT l.voucher_id, mb.passenger_name
                 FROM hotel_voucher_master_links l
                 JOIN master_bookings mb ON mb.id = l.master_booking_id AND mb.deleted_at IS NULL
                 WHERE l.voucher_id IN ({$placeholders})",
                $ids
            );
            $linkMap = [];
            foreach ($linkRows as $row) {
                $linkMap[(int)$row['voucher_id']][] = $row['passenger_name'];
            }

            $transportRows = Database::fetchAll(
                "SELECT voucher_id, COUNT(*) AS leg_count
                 FROM transport_bookings
                 WHERE voucher_id IN ({$placeholders}) AND auto_generated IN (1, 2) AND deleted_at IS NULL
                 GROUP BY voucher_id",
                $ids
            );
            $transportMap = [];
            foreach ($transportRows as $row) {
                $transportMap[(int)$row['voucher_id']] = (int)$row['leg_count'];
            }

            foreach ($vouchers as &$v) {
                $v['linked_master_bookings'] = $linkMap[(int)$v['id']] ?? [];
                $v['transport_leg_count'] = $transportMap[(int)$v['id']] ?? 0;
            }
            unset($v);
        }

        return $vouchers;
    }

    /** Soft-delete a voucher and retain its audit history. */
    public static function delete(int $id): array {
        if ($id <= 0) return ['success' => false, 'message' => 'Invalid voucher ID.'];
        $old = Database::fetchOne("SELECT * FROM hotel_vouchers WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$old) return ['success' => false, 'message' => 'Voucher not found.'];
        Database::execute("UPDATE hotel_vouchers SET deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE id = ?", [Session::getActor(), $id]);
        Database::execute("UPDATE transport_bookings SET deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE voucher_id = ? AND deleted_at IS NULL", [Session::getActor(), $id]);
        Database::execute("INSERT INTO audit_logs (entity_type, entity_id, action, old_values, new_values, created_by, created_at) VALUES ('hotel_voucher', ?, 'deleted', ?, NULL, ?, NOW())", [$id, json_encode($old, JSON_UNESCAPED_UNICODE), Session::getActor()]);
        return ['success' => true, 'message' => 'Hotel voucher deleted successfully.'];
    }

    /**
     * Convert a "Build Hotel Voucher" into a standalone "Only Hotel Booking".
     * Carries over the agent, hotel stays, transport auto-generation settings, and the
     * assigned Master Booking(s). The passenger count (adults/children/infants) is derived
     * from the Mutamers manifest when present, otherwise falls back to Total PAX as adults.
     * The original voucher is then retired (soft-deleted, with its auto-generated transport
     * legs) so the agent ledger isn't debited twice for the same stay; flight-itinerary and
     * package details that don't exist on a hotel booking are simply dropped — the user can
     * re-add anything relevant on the new booking.
     */
    public static function convertToHotelBooking(int $id): array {
        if ($id <= 0) return ['success' => false, 'message' => 'Invalid voucher ID.'];
        $v = self::get($id);
        if (!$v) return ['success' => false, 'message' => 'Hotel voucher not found.'];

        $masterBookings = $v['master_bookings'] ?? [];

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
                'rooms' => 1,
                'buy_rate_per_night' => (float)$s['buy_rate_per_night'],
                'sell_rate_per_night' => (float)$s['sell_rate_per_night'],
            ];
        }, $v['stays'] ?? []);

        $adults = 0; $children = 0; $infants = 0;
        foreach (($v['mutamers'] ?? []) as $m) {
            switch ($m['is_adult'] ?? 'Adult') {
                case 'Child': $children++; break;
                case 'Infant': $infants++; break;
                default: $adults++;
            }
        }
        if ($adults + $children + $infants <= 0) {
            $adults = max(1, (int)$v['total_pax']);
        }

        $remarks = trim((string)($v['package_name'] ?? ''));

        $data = [
            'agent_id' => (int)$v['agent_id'],
            'booking_date' => $v['voucher_date'],
            'lead_guest_name' => $v['family_head'],
            'company_name' => $v['company_name'] ?? '',
            'pax_adults' => $adults,
            'pax_children' => $children,
            'pax_infants' => $infants,
            'remarks' => $remarks,
            'stays' => $stays,
            'master_booking_ids' => array_column($masterBookings, 'id'),
            'transport_enabled' => !empty($v['transport_auto_enabled']),
            'transport_type' => $v['transport_auto_type'] ?: 'CAR',
            'transport_buy_rate' => (float)$v['transport_auto_buy_rate'],
            'transport_sell_rate' => (float)$v['transport_auto_sell_rate'],
        ];

        $ownsTransaction = !Database::getConnection()->inTransaction();
        if ($ownsTransaction) Database::beginTransaction();
        try {
            $result = HotelBookingController::create($data);
            if (!$result['success']) {
                if ($ownsTransaction) Database::rollBack();
                return $result;
            }

            self::markConverted($id, 'hotel_booking', (int)$result['booking_id']);

            if ($ownsTransaction) Database::commit();
            return [
                'success' => true,
                'message' => 'Hotel voucher converted to a standalone hotel booking. Review it for any missing details.',
                'booking_id' => $result['booking_id'],
                'booking_ref' => $result['booking_ref'],
            ];
        } catch (Throwable $e) {
            if ($ownsTransaction) Database::rollBack();
            return ['success' => false, 'message' => 'Conversion error: ' . $e->getMessage()];
        }
    }

    /** Soft-delete this voucher (and its auto transport legs) after it has been converted into another record type. */
    private static function markConverted(int $id, string $convertedToType, int $convertedToId): void {
        $old = Database::fetchOne("SELECT * FROM hotel_vouchers WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$old) return;
        Database::execute("UPDATE hotel_vouchers SET deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE id = ?", [Session::getActor(), $id]);
        Database::execute("UPDATE transport_bookings SET deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE voucher_id = ? AND deleted_at IS NULL", [Session::getActor(), $id]);
        Database::execute(
            "INSERT INTO audit_logs (entity_type, entity_id, action, old_values, new_values, created_by, created_at) VALUES ('hotel_voucher', ?, 'converted', ?, ?, ?, NOW())",
            [$id, json_encode($old, JSON_UNESCAPED_UNICODE), json_encode(['converted_to' => $convertedToType, 'id' => $convertedToId], JSON_UNESCAPED_UNICODE), Session::getActor()]
        );
    }
}
