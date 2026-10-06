<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/VoucherShare.php';

/**
 * Operations Logistics & Run-Sheet Controller
 * Aggregates daily arrivals, transport transfers, and hotel check-in/out transitions.
 */
class OperationsController {

    /**
     * Retrieve complete operational manifest for any date.
     */
    public static function getManifest(string $targetDate): array {
        $d = DateTime::createFromFormat('Y-m-d', $targetDate);
        if (!$d || $d->format('Y-m-d') !== $targetDate) {
            $targetDate = date('Y-m-d');
        }

        // 1. Arriving Flight Passengers (Master Bookings & Outbound Flights)
        $sqlArrivals = "SELECT 
                            mb.id,
                            mb.booking_date,
                            mb.passenger_name,
                            mb.passport_number,
                            mb.flight_number,
                            mb.arrival_date,
                            mb.departure_date,
                            mb.stay_days,
                            a.name AS agent_name,
                            a.phone AS agent_phone,
                            v.name AS vendor_name,
                            hv.flight_out_dep_time,
                            hv.flight_out_arr_time,
                            hv.flight_out_from,
                            hv.flight_out_to
                        FROM master_bookings mb
                        JOIN agents a ON mb.agent_id = a.id
                        JOIN vendors v ON mb.vendor_id = v.id
                        LEFT JOIN hotel_vouchers hv ON hv.master_booking_id = mb.id AND hv.deleted_at IS NULL
                        WHERE mb.deleted_at IS NULL AND (mb.arrival_date = ? OR hv.flight_out_arr_date = ?)
                        ORDER BY mb.flight_number ASC";
        $arrivals = Database::fetchAll($sqlArrivals, [$targetDate, $targetDate]);

        // 2. Transport Transfers & Pickups
        $sqlTransports = "SELECT 
                            tb.id,
                            tb.service_date,
                            tb.flight_number,
                            tb.terminal,
                            tb.pax_name,
                            tb.passport_number,
                            tb.pax_count,
                            tb.vehicle_type,
                            tb.pickup_time,
                            tb.route_details,
                            tb.sell_rate_sar,
                            tb.status,
                            tb.driver_name,
                            tb.driver_contact,
                            tb.auto_generated,
                            tb.hotel_booking_id,
                            tb.voucher_id,
                            a.name AS agent_name,
                            a.phone AS agent_phone,
                            v.name AS vendor_name
                          FROM transport_bookings tb
                          JOIN agents a ON tb.agent_id = a.id
                          LEFT JOIN vendors v ON tb.vendor_id = v.id
                          WHERE tb.deleted_at IS NULL AND tb.service_date = ?
                          ORDER BY tb.pickup_time ASC";
        $transports = Database::fetchAll($sqlTransports, [$targetDate]);

        usort($transports, fn($a, $b) => strcmp((string)$a['pickup_time'], (string)$b['pickup_time']));
        $transports = self::attachTransportVoucherDetails($transports);

        // 3. Hotel Check-ins (Makkah & Madinah) - Build Hotel Voucher stays + Only Hotel Booking stays
        $sqlCheckins = "SELECT
                            vs.id AS stay_id,
                            vs.city,
                            vs.hotel_name,
                            vs.room_type,
                            vs.meal_plan,
                            vs.checkin_date,
                            vs.checkout_date,
                            vs.nights,
                            hv.id AS voucher_id,
                            hv.voucher_no,
                            hv.family_head,
                            hv.total_pax,
                            hv.total_beds,
                            a.name AS agent_name,
                            a.phone AS agent_phone,
                            'v' AS source_type
                        FROM voucher_stays vs
                        JOIN hotel_vouchers hv ON vs.voucher_id = hv.id
                        JOIN agents a ON hv.agent_id = a.id
                        WHERE hv.deleted_at IS NULL AND vs.checkin_date = ?
                        UNION ALL
                        SELECT
                            hbs.id AS stay_id,
                            hbs.city,
                            hbs.hotel_name,
                            hbs.room_type,
                            hbs.meal_plan,
                            hbs.checkin_date,
                            hbs.checkout_date,
                            hbs.nights,
                            hb.id AS voucher_id,
                            hb.booking_ref AS voucher_no,
                            hb.lead_guest_name AS family_head,
                            (hb.pax_adults + hb.pax_children + hb.pax_infants) AS total_pax,
                            hbs.rooms AS total_beds,
                            a.name AS agent_name,
                            a.phone AS agent_phone,
                            'b' AS source_type
                        FROM hotel_booking_stays hbs
                        JOIN hotel_bookings hb ON hbs.booking_id = hb.id
                        JOIN agents a ON hb.agent_id = a.id
                        WHERE hb.deleted_at IS NULL AND hbs.checkin_date = ?
                        ORDER BY city ASC, hotel_name ASC";
        $checkins = Database::fetchAll($sqlCheckins, [$targetDate, $targetDate]);

        // 4. Hotel Check-outs (Makkah & Madinah) - Build Hotel Voucher stays + Only Hotel Booking stays
        $sqlCheckouts = "SELECT
                            vs.id AS stay_id,
                            vs.city,
                            vs.hotel_name,
                            vs.room_type,
                            vs.meal_plan,
                            vs.checkin_date,
                            vs.checkout_date,
                            vs.nights,
                            hv.id AS voucher_id,
                            hv.voucher_no,
                            hv.family_head,
                            hv.total_pax,
                            hv.total_beds,
                            a.name AS agent_name,
                            a.phone AS agent_phone
                         FROM voucher_stays vs
                         JOIN hotel_vouchers hv ON vs.voucher_id = hv.id
                         JOIN agents a ON hv.agent_id = a.id
                         WHERE hv.deleted_at IS NULL AND vs.checkout_date = ?
                         UNION ALL
                         SELECT
                            hbs.id AS stay_id,
                            hbs.city,
                            hbs.hotel_name,
                            hbs.room_type,
                            hbs.meal_plan,
                            hbs.checkin_date,
                            hbs.checkout_date,
                            hbs.nights,
                            hb.id AS voucher_id,
                            hb.booking_ref AS voucher_no,
                            hb.lead_guest_name AS family_head,
                            (hb.pax_adults + hb.pax_children + hb.pax_infants) AS total_pax,
                            hbs.rooms AS total_beds,
                            a.name AS agent_name,
                            a.phone AS agent_phone
                         FROM hotel_booking_stays hbs
                         JOIN hotel_bookings hb ON hbs.booking_id = hb.id
                         JOIN agents a ON hb.agent_id = a.id
                         WHERE hb.deleted_at IS NULL AND hbs.checkout_date = ?
                         ORDER BY city ASC, hotel_name ASC";
        $checkouts = Database::fetchAll($sqlCheckouts, [$targetDate, $targetDate]);

        return [
            'target_date'         => $targetDate,
            'is_today'            => $targetDate === date('Y-m-d'),
            'is_tomorrow'         => $targetDate === date('Y-m-d', strtotime('+1 day')),
            'arrivals'            => $arrivals,
            'transports'          => $transports,
            'checkins'            => $checkins,
            'checkouts'           => $checkouts,
            'total_arrivals_pax'  => count($arrivals),
            'total_transports'    => count($transports),
            'total_checkin_pax'   => array_sum(array_column($checkins, 'total_pax')),
            'total_checkout_pax'  => array_sum(array_column($checkouts, 'total_pax')),
        ];
    }

    /* ------------------------------------------------------------------
     * WhatsApp "Send" (Operations Manifest: Hotel Check-Ins + Transport)
     * ------------------------------------------------------------------
     * WhatsApp links can only carry text, so the message includes the signed public link
     * to the printable Hotel Voucher (opens as the A4 voucher -> Print / Save as PDF).
     */
    /** Team WhatsApp numbers (digits only), editable in Admin Settings; default is the office number. */
    public static function whatsappTeams(): array {
        static $teams = null;
        if ($teams !== null) return $teams;
        $rows = Database::fetchAll("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('makkah_team_whatsapp', 'madinah_team_whatsapp')");
        $map = array_column($rows, 'setting_value', 'setting_key');
        $digits = static fn($v) => preg_replace('/\D+/', '', (string)$v) ?: '923035137777';
        return $teams = [
            'Makkah'  => $digits($map['makkah_team_whatsapp'] ?? ''),
            'Madinah' => $digits($map['madinah_team_whatsapp'] ?? ''),
        ];
    }

    /** Makkah / Madinah (any common spelling or airport code), otherwise null. */
    public static function cityTeam(?string $text): ?string {
        $tokens = preg_split('/[^A-Z]+/', strtoupper((string)$text), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($tokens as $t) {
            if (in_array($t, ['MAK', 'MAKKAH', 'MAKKA', 'MECCA', 'MAKKAH'], true)) return 'Makkah';
            if (in_array($t, ['MED', 'MADINAH', 'MADINA', 'MEDINA', 'MEDINAH'], true)) return 'Madinah';
        }
        return null;
    }

    /**
     * Team that handles a transfer: the pickup city, or the drop city when the pickup is the
     * Jeddah airport (JED-MAK -> Makkah, MAK-MED -> Makkah, MED-JED -> Madinah, "ZIYARAT MED" -> Madinah).
     */
    public static function transportTeam(?string $route): ?string {
        $tokens = preg_split('/[^A-Z]+/', strtoupper((string)$route), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($tokens as $t) {
            if ($team = self::cityTeam($t)) return $team;
        }
        return null;
    }

    /**
     * Builds the WhatsApp link(s): one for a known city, otherwise one per team so the user can choose.
     * Uses api.whatsapp.com/send rather than wa.me: the wa.me redirect turns most emojis into "?" boxes.
     */
    private static function waLinks(?string $team, string $message): array {
        $all = self::whatsappTeams();
        $teams = $team ? [$team => $all[$team]] : $all;
        $links = [];
        foreach ($teams as $name => $number) {
            $links[] = ['team' => $name, 'number' => '+' . $number, 'url' => 'https://api.whatsapp.com/send?phone=' . $number . '&text=' . rawurlencode($message)];
        }
        return $links;
    }

    /**
     * Rooms for a stay. Only Hotel Booking stores rooms; Build Hotel Voucher stores beds, so rooms are
     * worked out from the room type (Quad = 4 beds per room, Triple = 3, ...). Sharing stays show beds.
     */
    private static function waRooms(array $ci): string {
        $beds = max(1, (int)($ci['total_beds'] ?? 1));
        if (($ci['source_type'] ?? 'v') === 'b') return (string)$beds;
        $type = strtolower((string)($ci['room_type'] ?? ''));
        $perRoom = ['sharing' => 0, 'six' => 6, 'quint' => 5, 'five' => 5, 'quad' => 4, 'quard' => 4, 'four' => 4,
                    'triple' => 3, 'three' => 3, 'double' => 2, 'twin' => 2, 'two' => 2, 'single' => 1];
        foreach ($perRoom as $word => $capacity) {
            if (!str_contains($type, $word)) continue;
            return $capacity === 0 ? $beds . ' Bed' . ($beds > 1 ? 's' : '') . ' (Sharing)' : (string)(int)ceil($beds / $capacity);
        }
        return $beds . ' Bed' . ($beds > 1 ? 's' : '');
    }

    private static function waVoucherUrl(string $type, ?int $id): string {
        return $id ? VoucherShare::url($type, $id) : '';
    }

    /** WhatsApp "Send" link(s) for one Hotel Check-In row. */
    public static function whatsAppCheckin(array $ci): array {
        $m = self::checkinMessage($ci);
        return self::waLinks($m['team'], $m['text']);
    }

    /** WhatsApp "Send" link(s) for one Transport row . */
    public static function whatsAppTransport(array $t): array {
        $m = self::transportMessage($t);
        return $m ? self::waLinks($m['team'], $m['text']) : [];
    }

    /**
     * Check-in message text + team (Makkah / Madinah / null): hotel, agent, rooms, room type, voucher link.
     * Shared by the manifest Send button and the daily automatic sender (cron/whatsapp_daily.php).
     */
    public static function checkinMessage(array $ci): array {
        $type = ($ci['source_type'] ?? 'v') === 'b' ? 'b' : 'v';
        $url = self::waVoucherUrl($type, isset($ci['voucher_id']) ? (int)$ci['voucher_id'] : null);
        $lines = [
            '🏨 *Hotel:* ' . ($ci['hotel_name'] ?: '-'),
            '🤝 *Agent:* ' . ($ci['agent_name'] ?: '-'),
            '🚪 *Total Rooms:* ' . self::waRooms($ci),
            '🛏️ *Room Type:* ' . ($ci['room_type'] ?: '-'),
        ];
        if ($url !== '') $lines[] = '📄 *Voucher:* ' . $url;
        return ['team' => self::cityTeam($ci['city'] ?? ''), 'text' => implode("\n", $lines)];
    }

    /** Transport message text + team: agent, vehicle, route. */
    public static function transportMessage(array $t): ?array {
        // No voucher link here: the hotel check-in message already carries it.
        $lines = [
            '🤝 *Agent:* ' . ($t['agent_name'] ?: '-'),
            '🚐 *Transport:* ' . strtoupper((string)($t['vehicle_type'] ?: '-')),
            '🛣️ *Route:* ' . str_replace('-', ' ➡️ ', (string)($t['route_details'] ?: '-')),
        ];
        $travellers = $t['travellers'] ?? array_values(array_filter(array_map('trim', explode(',', (string)($t['pax_name'] ?? '')))));
        if ($travellers) {
            $pax = max(count($travellers), (int)($t['pax_count'] ?? 0));
            $lines[] = '👥 *Travellers (' . $pax . '):*';
            foreach ($travellers as $i => $name) $lines[] = ($i + 1) . '. ' . $name;
            if ($pax > count($travellers)) $lines[] = '➕ ' . ($pax - count($travellers)) . ' more';
        }
        return ['team' => self::transportTeam($t['route_details'] ?? ''), 'text' => implode("\n", $lines)];
    }

    /**
     * For transfers made from a Hotel Voucher / Only Hotel Booking: voucher no, one lead name
     * (instead of every traveller) and the pickup / drop hotel for that date.
     */
    private static function attachTransportVoucherDetails(array $transports): array {
        $cache = [];
        foreach ($transports as &$t) {
            $names = array_values(array_filter(array_map('trim', explode(',', (string)($t['pax_name'] ?? '')))));
            $t['lead_name'] = $names[0] ?? ($t['pax_name'] ?? '');
            // Every traveller on this transfer (shown in the WhatsApp transport message).
            $t['travellers'] = $names;
            $key = !empty($t['voucher_id']) ? 'v' . (int)$t['voucher_id'] : (!empty($t['hotel_booking_id']) ? 'b' . (int)$t['hotel_booking_id'] : null);
            if (!$key) continue;
            if (!isset($cache[$key])) {
                $id = (int)substr($key, 1);
                $mutamers = [];
                if ($key[0] === 'v') {
                    $head = Database::fetchOne("SELECT voucher_no, family_head AS lead FROM hotel_vouchers WHERE id = ?", [$id]);
                    $stays = Database::fetchAll("SELECT city, hotel_name, checkin_date, checkout_date FROM voucher_stays WHERE voucher_id = ? ORDER BY checkin_date, id", [$id]);
                    $mutamers = array_values(array_filter(array_map('trim', array_column(
                        Database::fetchAll("SELECT mutamer_name FROM voucher_mutamers WHERE voucher_id = ? ORDER BY id", [$id]), 'mutamer_name'
                    ))));
                } else {
                    $head = Database::fetchOne("SELECT booking_ref AS voucher_no, lead_guest_name AS lead FROM hotel_bookings WHERE id = ?", [$id]);
                    $stays = Database::fetchAll("SELECT city, hotel_name, checkin_date, checkout_date FROM hotel_booking_stays WHERE booking_id = ? ORDER BY checkin_date, id", [$id]);
                }
                $cache[$key] = ['head' => $head ?: [], 'stays' => $stays, 'mutamers' => $mutamers];
            }
            $info = $cache[$key];
            // A voucher's Mutamers list is the full traveller list (the leg itself may only hold the family head).
            if (count($info['mutamers']) > count($t['travellers'])) $t['travellers'] = $info['mutamers'];
            $t['voucher_no'] = $info['head']['voucher_no'] ?? null;
            $lead = trim((string)($info['head']['lead'] ?? ''));
            if ($lead !== '' && !preg_match('/^[A-Z]{2,5}-?\d+$/i', $lead)) $t['lead_name'] = $lead;
            // Airport legs are timed from the flights, so they can fall a day off the hotel dates
            // (pickup the night before an early-morning departure, or a landing after midnight).
            $route = strtoupper((string)($t['route_details'] ?? ''));
            $nextDay = date('Y-m-d', strtotime($t['service_date'] . ' +1 day'));
            $prevDay = date('Y-m-d', strtotime($t['service_date'] . ' -1 day'));
            foreach ($info['stays'] as $s) {
                $label = $s['hotel_name'] . ' (' . $s['city'] . ')';
                if ($s['checkout_date'] === $t['service_date'] && empty($t['pickup_hotel'])) $t['pickup_hotel'] = $label;
                if ($s['checkin_date'] === $t['service_date'] && empty($t['drop_hotel'])) $t['drop_hotel'] = $label;
            }
            foreach ($info['stays'] as $s) {
                $label = $s['hotel_name'] . ' (' . $s['city'] . ')';
                if (str_ends_with($route, '-JED') && $s['checkout_date'] === $nextDay && empty($t['pickup_hotel'])) $t['pickup_hotel'] = $label;
                if (str_starts_with($route, 'JED-') && $s['checkin_date'] === $prevDay && empty($t['drop_hotel'])) $t['drop_hotel'] = $label;
            }
        }
        unset($t);
        return $transports;
    }

    /**
     * Get summary metrics for quick dashboard badges.
     */
    public static function getQuickStats(): array {
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $todayArrivals = (int)Database::fetchValue("SELECT COUNT(*) FROM master_bookings WHERE deleted_at IS NULL AND arrival_date = ?", [$today]);
        $tomorrowArrivals = (int)Database::fetchValue("SELECT COUNT(*) FROM master_bookings WHERE deleted_at IS NULL AND arrival_date = ?", [$tomorrow]);

        $todayTransports = (int)Database::fetchValue("SELECT COUNT(*) FROM transport_bookings WHERE deleted_at IS NULL AND service_date = ?", [$today]);
        $tomorrowTransports = (int)Database::fetchValue("SELECT COUNT(*) FROM transport_bookings WHERE deleted_at IS NULL AND service_date = ?", [$tomorrow]);

        $todayCheckins = (int)Database::fetchValue("SELECT
                (SELECT COUNT(*) FROM voucher_stays vs JOIN hotel_vouchers hv ON vs.voucher_id = hv.id WHERE hv.deleted_at IS NULL AND vs.checkin_date = ?)
                + (SELECT COUNT(*) FROM hotel_booking_stays hbs JOIN hotel_bookings hb ON hbs.booking_id = hb.id WHERE hb.deleted_at IS NULL AND hbs.checkin_date = ?)", [$today, $today]);
        $tomorrowCheckins = (int)Database::fetchValue("SELECT
                (SELECT COUNT(*) FROM voucher_stays vs JOIN hotel_vouchers hv ON vs.voucher_id = hv.id WHERE hv.deleted_at IS NULL AND vs.checkin_date = ?)
                + (SELECT COUNT(*) FROM hotel_booking_stays hbs JOIN hotel_bookings hb ON hbs.booking_id = hb.id WHERE hb.deleted_at IS NULL AND hbs.checkin_date = ?)", [$tomorrow, $tomorrow]);

        return [
            'today' => [
                'date'       => $today,
                'arrivals'   => $todayArrivals,
                'transports' => $todayTransports,
                'checkins'   => $todayCheckins,
            ],
            'tomorrow' => [
                'date'       => $tomorrow,
                'arrivals'   => $tomorrowArrivals,
                'transports' => $tomorrowTransports,
                'checkins'   => $tomorrowCheckins,
            ]
        ];
    }
}

