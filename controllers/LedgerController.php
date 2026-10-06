<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Session.php';

class LedgerController {
    /**
     * Buy / Sell totals for [$start, $end): SAR services (visa & bookings, hotels, transport) plus
     * Ticket Bookings, which are priced in PKR and reported on their own (never mixed into SAR).
     */
    public static function periodTotals(string $start, string $end): array {
        $sum = static function (string $sql) use ($start, $end): float {
            return (float)(Database::fetchValue($sql, [$start, $end]) ?? 0);
        };

        $visaSell = $sum('SELECT COALESCE(SUM(sell_rate_sar + ticket_sell_rate_sar), 0) FROM master_bookings WHERE deleted_at IS NULL AND booking_date >= ? AND booking_date < ?');
        $visaBuy  = $sum('SELECT COALESCE(SUM(buy_rate_sar + ticket_buy_rate_sar), 0) FROM master_bookings WHERE deleted_at IS NULL AND booking_date >= ? AND booking_date < ?');

        $hotelSell = $sum("SELECT COALESCE(SUM(CASE WHEN hs.net_accommodation_charge > 0 THEN hs.net_accommodation_charge + hs.vat ELSE (hs.per_night_sell * hs.nights) + hs.vat END), 0)
                           FROM hotel_stays hs JOIN master_bookings mb ON mb.id = hs.booking_id
                           WHERE hs.deleted_at IS NULL AND mb.deleted_at IS NULL AND hs.checkin_date >= ? AND hs.checkin_date < ?")
                   + $sum('SELECT COALESCE(SUM(sell_rate_sar), 0) FROM hotel_vouchers WHERE deleted_at IS NULL AND voucher_date >= ? AND voucher_date < ?')
                   + $sum('SELECT COALESCE(SUM(sell_total_sar), 0) FROM hotel_bookings WHERE deleted_at IS NULL AND booking_date >= ? AND booking_date < ?');
        $hotelBuy  = $sum('SELECT COALESCE(SUM(per_night_buy * nights), 0) FROM hotel_stays hs JOIN master_bookings mb ON mb.id = hs.booking_id WHERE hs.deleted_at IS NULL AND mb.deleted_at IS NULL AND hs.checkin_date >= ? AND hs.checkin_date < ?')
                   + $sum('SELECT COALESCE(SUM(buy_rate_sar), 0) FROM hotel_vouchers WHERE deleted_at IS NULL AND voucher_date >= ? AND voucher_date < ?')
                   + $sum('SELECT COALESCE(SUM(buy_total_sar), 0) FROM hotel_bookings WHERE deleted_at IS NULL AND booking_date >= ? AND booking_date < ?');

        $transportSell = $sum('SELECT COALESCE(SUM(tt.sell_rate), 0) FROM transport_transfers tt JOIN master_bookings mb ON mb.id = tt.booking_id WHERE tt.deleted_at IS NULL AND mb.deleted_at IS NULL AND tt.service_date >= ? AND tt.service_date < ?')
                       + $sum('SELECT COALESCE(SUM(sell_rate_sar), 0) FROM transport_bookings WHERE deleted_at IS NULL AND service_date >= ? AND service_date < ?');
        $transportBuy  = $sum('SELECT COALESCE(SUM(tt.buy_rate), 0) FROM transport_transfers tt JOIN master_bookings mb ON mb.id = tt.booking_id WHERE tt.deleted_at IS NULL AND mb.deleted_at IS NULL AND tt.service_date >= ? AND tt.service_date < ?')
                       + $sum('SELECT COALESCE(SUM(buy_rate_sar), 0) FROM transport_bookings WHERE deleted_at IS NULL AND service_date >= ? AND service_date < ?');

        $ticketSell = $sum('SELECT COALESCE(SUM(sell_pkr), 0) FROM air_tickets WHERE deleted_at IS NULL AND is_booking = 1 AND COALESCE(booking_date, DATE(created_at)) >= ? AND COALESCE(booking_date, DATE(created_at)) < ?');
        $ticketBuy  = $sum('SELECT COALESCE(SUM(buy_pkr), 0) FROM air_tickets WHERE deleted_at IS NULL AND is_booking = 1 AND COALESCE(booking_date, DATE(created_at)) >= ? AND COALESCE(booking_date, DATE(created_at)) < ?');

        $line = static fn(float $sell, float $buy): array => [
            'sell' => round($sell, 2), 'buy' => round($buy, 2), 'profit' => round($sell - $buy, 2),
            'margin' => $sell > 0 ? round((($sell - $buy) / $sell) * 100, 1) : 0,
        ];
        return [
            'visas'      => $line($visaSell, $visaBuy),
            'hotels'     => $line($hotelSell, $hotelBuy),
            'transports' => $line($transportSell, $transportBuy),
            'sar'        => $line($visaSell + $hotelSell + $transportSell, $visaBuy + $hotelBuy + $transportBuy),
            'tickets'    => $line($ticketSell, $ticketBuy),
        ];
    }

    /**
     * Weekly (ISO week, Mon-Sun, "2026-W41") or monthly ("2026-10") Buy / Sell report:
     * totals per service plus a breakdown by day (weekly) or by week (monthly).
     */
    public static function getBuySellReport(string $mode, string $value): array {
        $mode = $mode === 'week' ? 'week' : 'month';
        if ($mode === 'week') {
            if (!preg_match('/^(\d{4})-W(\d{2})$/', $value, $m) || (int)$m[2] < 1 || (int)$m[2] > 53) $value = date('o-\WW');
            [$y, $w] = array_map('intval', explode('-W', $value));
            $start = (new DateTimeImmutable())->setISODate($y, $w)->setTime(0, 0);
            $end = $start->modify('+7 days');
            $label = 'Week ' . $w . ' · ' . $start->format('d M') . ' – ' . $end->modify('-1 day')->format('d M Y');
            $buckets = [];
            for ($d = $start; $d < $end; $d = $d->modify('+1 day')) {
                $buckets[] = [$d->format('D, d M'), $d, $d->modify('+1 day')];
            }
        } else {
            $monthDate = DateTimeImmutable::createFromFormat('!Y-m', $value);
            if (!$monthDate || $monthDate->format('Y-m') !== $value) { $value = date('Y-m'); $monthDate = new DateTimeImmutable($value . '-01'); }
            $start = $monthDate;
            $end = $start->modify('+1 month');
            $label = $start->format('F Y');
            $buckets = [];
            for ($d = $start, $i = 1; $d < $end; $d = $next, $i++) {
                $next = min($d->modify('monday next week'), $end);
                $buckets[] = ['Week ' . $i . ' (' . $d->format('d') . '–' . $next->modify('-1 day')->format('d M') . ')', $d, $next];
            }
        }

        $rows = [];
        foreach ($buckets as [$name, $from, $to]) {
            $t = self::periodTotals($from->format('Y-m-d'), $to->format('Y-m-d'));
            $rows[] = ['label' => $name, 'sar' => $t['sar'], 'tickets' => $t['tickets']];
        }

        return [
            'mode' => $mode, 'value' => $value, 'label' => $label,
            'start' => $start->format('Y-m-d'), 'end' => $end->modify('-1 day')->format('Y-m-d'),
            'totals' => self::periodTotals($start->format('Y-m-d'), $end->format('Y-m-d')),
            'rows' => $rows,
        ];
    }

    /** Market-wide outstanding balances: owed by agents, owed to vendors (SAR). */
    public static function getOutstandingTotals(): array {
        $totalReceivable = 0.0;
        foreach (Database::fetchAll('SELECT id FROM agents', []) as $agentRow) {
            $totalReceivable += (float)(self::getAgentLedger((int)$agentRow['id'])['current_balance_sar'] ?? 0);
        }
        $totalPayable = 0.0;
        foreach (Database::fetchAll('SELECT id FROM vendors', []) as $vendorRow) {
            $totalPayable += (float)(self::getVendorLedger((int)$vendorRow['id'])['total_payable_sar'] ?? 0);
        }
        return ['receivable' => round($totalReceivable, 2), 'payable' => round($totalPayable, 2)];
    }

    public static function getAgentLedger(int $agentId): array {
        $agent = Database::fetchOne('SELECT * FROM agents WHERE id = ?', [$agentId]);
        if (!$agent) {
            return ['success' => false, 'agent' => null, 'ledger' => [], 'current_balance_sar' => 0.0];
        }

        $rows = Database::fetchAll(
            "SELECT id, COALESCE(booking_date, DATE(created_at), CURRENT_DATE) AS entry_date,
                    flight_number, arrival_date, departure_date, passenger_name, passport_number,
                    'Booking' AS service_type, NULL AS hotel_names, (sell_rate_sar + ticket_sell_rate_sar) AS debit_sar,
                    0.00 AS credit_sar, remarks, 'booking' AS record_type, created_at
             FROM master_bookings
             WHERE agent_id = ? AND deleted_at IS NULL
             UNION ALL
             SELECT hs.id, COALESCE(hs.checkin_date, mb.booking_date, DATE(hs.created_at), CURRENT_DATE) AS entry_date,
                  mb.flight_number, COALESCE(hs.checkin_date, mb.arrival_date) AS arrival_date,
                  COALESCE(hs.checkout_date, mb.departure_date) AS departure_date,
                  mb.passenger_name, mb.passport_number,
                  CONCAT('Hotel: ', hs.hotel_name, ' (', hs.nights, ' Nights)') AS service_type,
                  hs.hotel_name AS hotel_names,
                  CASE WHEN hs.net_accommodation_charge > 0
                     THEN hs.net_accommodation_charge + hs.vat
                     ELSE (hs.per_night_sell * hs.nights) + hs.vat END AS debit_sar,
                  0.00 AS credit_sar, hs.notes AS remarks, 'hotel' AS record_type, hs.created_at
             FROM hotel_stays hs
             JOIN master_bookings mb ON mb.id = hs.booking_id
             WHERE mb.agent_id = ? AND mb.deleted_at IS NULL AND hs.deleted_at IS NULL
                 AND (hs.net_accommodation_charge > 0 OR hs.per_night_sell > 0 OR hs.vat > 0)
             UNION ALL
             SELECT tt.id, COALESCE(tt.service_date, mb.booking_date, DATE(tt.created_at), CURRENT_DATE) AS entry_date,
                  COALESCE(NULLIF(tt.flight_number, ''), mb.flight_number) AS flight_number,
                  mb.arrival_date, mb.departure_date,
                  TRIM(SUBSTRING_INDEX(COALESCE(NULLIF(tt.pax_name, ''), mb.passenger_name), ',', 1)) AS passenger_name,
                  TRIM(SUBSTRING_INDEX(COALESCE(NULLIF(tt.passport_number, ''), mb.passport_number), ',', 1)) AS passport_number,
                  CONCAT('Transport: ', COALESCE(tt.vehicle_type, ''), ' (', COALESCE(tt.route_details, ''), ')') AS service_type,
                  CONCAT_WS(' · ', NULLIF(UPPER(TRIM(tt.vehicle_type)), ''), NULLIF(TRIM(tt.route_details), '')) AS hotel_names,
                  tt.sell_rate AS debit_sar, 0.00 AS credit_sar, tt.notes AS remarks,
                  'transport' AS record_type, tt.created_at
             FROM transport_transfers tt
             JOIN master_bookings mb ON mb.id = tt.booking_id
             WHERE mb.agent_id = ? AND mb.deleted_at IS NULL AND tt.deleted_at IS NULL
                 AND tt.sell_rate >= 0
             UNION ALL
                  SELECT tb.id, COALESCE(tb.service_date, DATE(tb.created_at), CURRENT_DATE) AS entry_date,
                      tb.flight_number, NULL AS arrival_date, NULL AS departure_date,
                      CONCAT(COALESCE(NULLIF(TRIM(hv.family_head), ''), NULLIF(TRIM(hb.lead_guest_name), ''), ''), '||', tb.pax_name) AS passenger_name,
                      COALESCE(hv.voucher_no, hb.booking_ref, NULLIF(TRIM(SUBSTRING_INDEX(tb.passport_number, ',', 1)), '')) AS passport_number,
                      CONCAT('Transport: ', tb.vehicle_type, ' (', tb.route_details, ')') AS service_type,
                      CONCAT_WS(' · ', NULLIF(UPPER(TRIM(tb.vehicle_type)), ''), NULLIF(TRIM(tb.route_details), ''),
                          CASE WHEN tb.pax_count > 1 THEN CONCAT(tb.pax_count, ' PAX') END) AS hotel_names,
                      tb.sell_rate_sar AS debit_sar, 0.00 AS credit_sar, tb.terminal AS remarks,
                      'transport' AS record_type, tb.created_at
                  FROM transport_bookings tb
                  LEFT JOIN hotel_vouchers hv ON hv.id = tb.voucher_id
                  LEFT JOIN hotel_bookings hb ON hb.id = tb.hotel_booking_id
                  WHERE tb.agent_id = ? AND tb.deleted_at IS NULL AND tb.sell_rate_sar > 0
                  UNION ALL
                  SELECT hv.id, COALESCE(hv.voucher_date, DATE(hv.created_at), CURRENT_DATE) AS entry_date,
                      '' AS flight_number, NULL AS arrival_date, NULL AS departure_date,
                      hv.family_head AS passenger_name, hv.voucher_no AS passport_number,
                      CONCAT('Hotel Package (', hv.total_nights, ' Nights, ', hv.total_pax, ' PAX)',
                          CASE WHEN (SELECT GROUP_CONCAT(vs.hotel_name SEPARATOR ' - ') FROM voucher_stays vs WHERE vs.voucher_id = hv.id) IS NOT NULL
                              THEN CONCAT(' (', (SELECT GROUP_CONCAT(vs.hotel_name SEPARATOR ' - ') FROM voucher_stays vs WHERE vs.voucher_id = hv.id), ')')
                              ELSE '' END) AS service_type,
                      (SELECT GROUP_CONCAT(vs.hotel_name SEPARATOR ' - ') FROM voucher_stays vs WHERE vs.voucher_id = hv.id) AS hotel_names,
                      hv.sell_rate_sar AS debit_sar, 0.00 AS credit_sar, hv.transporter_info AS remarks,
                      'hotel' AS record_type, hv.created_at
                  FROM hotel_vouchers hv
                  WHERE hv.agent_id = ? AND hv.deleted_at IS NULL AND hv.sell_rate_sar > 0
                  UNION ALL
                  SELECT hb.id, COALESCE(hb.booking_date, DATE(hb.created_at), CURRENT_DATE) AS entry_date,
                      '' AS flight_number, NULL AS arrival_date, NULL AS departure_date,
                      hb.lead_guest_name AS passenger_name, hb.booking_ref AS passport_number,
                      CONCAT('Hotel Booking (', hb.total_nights, ' Nights, ', (hb.pax_adults + hb.pax_children + hb.pax_infants), ' PAX)',
                          CASE WHEN (SELECT GROUP_CONCAT(hbs.hotel_name SEPARATOR ' - ') FROM hotel_booking_stays hbs WHERE hbs.booking_id = hb.id) IS NOT NULL
                              THEN CONCAT(' (', (SELECT GROUP_CONCAT(hbs.hotel_name SEPARATOR ' - ') FROM hotel_booking_stays hbs WHERE hbs.booking_id = hb.id), ')')
                              ELSE '' END) AS service_type,
                      (SELECT GROUP_CONCAT(hbs.hotel_name SEPARATOR ' - ') FROM hotel_booking_stays hbs WHERE hbs.booking_id = hb.id) AS hotel_names,
                      hb.sell_total_sar AS debit_sar, 0.00 AS credit_sar, hb.remarks, 'hotel' AS record_type, hb.created_at
                  FROM hotel_bookings hb
                  WHERE hb.agent_id = ? AND hb.deleted_at IS NULL AND hb.sell_total_sar > 0
                  UNION ALL
             SELECT id, COALESCE(payment_date, DATE(created_at), CURRENT_DATE) AS entry_date,
                    receipt_number AS flight_number, NULL AS arrival_date, NULL AS departure_date,
                    CONCAT('Payment Received: ', bank_name) AS passenger_name, '' AS passport_number,
                    'Payment Receipt' AS service_type, NULL AS hotel_names, 0.00 AS debit_sar, amount_sar AS credit_sar,
                    remarks, 'payment' AS record_type, created_at
             FROM agent_payments
             WHERE agent_id = ?
                 UNION ALL
                 SELECT id, adjustment_date AS entry_date, '' AS flight_number,
                        NULL AS arrival_date, NULL AS departure_date,
                        reason AS passenger_name, '' AS passport_number,
                        'Additional Amount' AS service_type, NULL AS hotel_names, amount_sar AS debit_sar,
                        0.00 AS credit_sar, reason AS remarks, 'adjustment' AS record_type, created_at
                 FROM agent_adjustments
                 WHERE agent_id = ?
             ORDER BY entry_date ASC, created_at ASC, id ASC",
            [$agentId, $agentId, $agentId, $agentId, $agentId, $agentId, $agentId, $agentId]
        );

        $balance = (float)($agent['opening_balance_sar'] ?? 0);
        $ledger = [];
        foreach ($rows as $row) {
            if (($row['record_type'] ?? '') === 'payment') {
                $paymentDetails = Database::fetchOne(
                    'SELECT amount_pkr, exchange_rate, bank_name FROM agent_payments WHERE id = ? AND agent_id = ?',
                    [(int)$row['id'], $agentId]
                );
                if ($paymentDetails) $row = array_merge($row, $paymentDetails);
            }
            if (($row['record_type'] ?? '') === 'transport' && str_contains((string)$row['passenger_name'], '||')) {
                [$lead, $paxList] = explode('||', (string)$row['passenger_name'], 2);
                $row['passenger_name'] = self::ledgerLeadName($lead, $paxList);
            }
            $balance += (float)$row['debit_sar'] - (float)$row['credit_sar'];
            $row['balance_sar'] = round($balance, 2);
            unset($row['created_at']);
            $ledger[] = $row;
        }

        return [
            'success' => true,
            'agent' => $agent,
            'ledger' => $ledger,
            'current_balance_sar' => round($balance, 2)
        ];
    }

    /**
     * Transport-module rows (voucher / hotel booking / bulk group transfers) can carry every traveller in pax_name.
     * The agent ledger shows ONE name per transfer: the voucher Family Head / hotel Lead Guest when it is a real
     * name (not a booking code like "AHT-100028"), else the first traveller who looks male (no gender is stored,
     * so common female name markers are skipped), else the first traveller.
     */
    private static function ledgerLeadName(string $lead, string $paxList): string {
        $lead = trim($lead);
        if ($lead !== '' && !preg_match('/^[A-Z]{2,5}-?\d+$/i', $lead)) return $lead;
        $names = array_values(array_filter(array_map('trim', explode(',', $paxList)), static fn($n) => $n !== ''));
        if (!$names) return $lead !== '' ? $lead : '-';
        foreach ($names as $name) {
            if (preg_match('/^(muhammad|mohammad|muhammed|mohammed|mohd|syed|sayed|hafiz|haji|malik|rana|ch\.?|chaudhry)\b/i', $name)) return $name;
        }
        foreach ($names as $name) {
            if (!preg_match('/\b(bibi|begum|khatoon|khatun|bano|banu|parveen|perveen|fatima|fatma|kausar|kosar|sultana|nasreen|nasrin|akhtar bibi|ammara|hafsa|ayesha|aisha|zainab|maryam|mariam|maria|nadia|rubina|samina|shazia|tahira|kaneez|razia|sughra|safia)\b/i', $name)) return $name;
        }
        return $names[0];
    }

    public static function recordAgentPayment(array $data): array {
        $agentId = (int)($data['agent_id'] ?? 0);
        $amountPkr = round((float)($data['amount_pkr'] ?? 0), 2);
        $exchangeRate = round((float)($data['exchange_rate'] ?? 0), 2);
        $paymentDate = trim((string)($data['payment_date'] ?? date('Y-m-d')));
        $bankName = trim((string)($data['bank_name'] ?? ''));
        $receiptNumber = trim((string)($data['receipt_number'] ?? ''));
        $remarks = trim((string)($data['remarks'] ?? ''));

        if ($agentId <= 0 || $amountPkr <= 0 || $bankName === '') {
            return ['success' => false, 'message' => 'Agent, valid PKR amount, and bank name are required.'];
        }

        if (!Database::fetchOne('SELECT id FROM agents WHERE id = ?', [$agentId])) {
            return ['success' => false, 'message' => 'Agent not found.'];
        }

        $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $paymentDate);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $paymentDate) {
            return ['success' => false, 'message' => 'Please provide a valid payment date.'];
        }

        if ($exchangeRate <= 0) {
            $exchangeRate = (float)(Database::fetchValue(
                "SELECT setting_value FROM system_settings WHERE setting_key = 'default_exchange_rate'"
            ) ?: 76.00);
        }

        if ($exchangeRate <= 0) {
            return ['success' => false, 'message' => 'Please provide a valid exchange rate.'];
        }

        $amountSar = round($amountPkr / $exchangeRate, 2);
        Database::execute(
            'INSERT INTO agent_payments (agent_id, payment_date, bank_name, amount_pkr, exchange_rate, amount_sar, receipt_number, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$agentId, $paymentDate, $bankName, $amountPkr, $exchangeRate, $amountSar, $receiptNumber ?: null, $remarks ?: null]
        );

        return [
            'success' => true,
            'message' => 'Payment received and converted to ' . number_format($amountSar, 2) . ' SAR.',
            'amount_sar' => $amountSar
        ];
    }

    public static function updateAgentPayment(array $data): array {
        $paymentId = (int)($data['payment_id'] ?? 0);
        $agentId = (int)($data['agent_id'] ?? 0);
        $amountPkr = round((float)($data['amount_pkr'] ?? 0), 2);
        $exchangeRate = round((float)($data['exchange_rate'] ?? 0), 2);
        $paymentDate = trim((string)($data['payment_date'] ?? ''));
        $bankName = trim((string)($data['bank_name'] ?? ''));
        $receiptNumber = trim((string)($data['receipt_number'] ?? ''));
        $remarks = trim((string)($data['remarks'] ?? ''));

        if ($paymentId <= 0 || $agentId <= 0 || $amountPkr <= 0 || $exchangeRate <= 0 || $bankName === '') {
            return ['success'=>false,'message'=>'Payment, agent, valid amounts, exchange rate, and bank name are required.'];
        }
        $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $paymentDate);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $paymentDate) {
            return ['success'=>false,'message'=>'Please provide a valid payment date.'];
        }
        $payment = Database::fetchOne('SELECT id FROM agent_payments WHERE id = ? AND agent_id = ?', [$paymentId, $agentId]);
        if (!$payment) return ['success'=>false,'message'=>'Payment receipt not found.'];

        $amountSar = round($amountPkr / $exchangeRate, 2);
        Database::execute(
            'UPDATE agent_payments SET payment_date = ?, bank_name = ?, amount_pkr = ?, exchange_rate = ?, amount_sar = ?, receipt_number = ?, remarks = ? WHERE id = ? AND agent_id = ?',
            [$paymentDate, $bankName, $amountPkr, $exchangeRate, $amountSar, $receiptNumber ?: null, $remarks ?: null, $paymentId, $agentId]
        );
        return ['success'=>true,'message'=>'Payment receipt updated successfully.'];
    }

    public static function deleteAgentPayment(array $data): array {
        $paymentId = (int)($data['payment_id'] ?? 0);
        $agentId = (int)($data['agent_id'] ?? 0);
        if ($paymentId <= 0 || $agentId <= 0) return ['success'=>false,'message'=>'Invalid payment receipt.'];
        $payment = Database::fetchOne('SELECT id FROM agent_payments WHERE id = ? AND agent_id = ?', [$paymentId, $agentId]);
        if (!$payment) return ['success'=>false,'message'=>'Payment receipt not found.'];
        Database::execute('DELETE FROM agent_payments WHERE id = ? AND agent_id = ?', [$paymentId, $agentId]);
        return ['success'=>true,'message'=>'Payment receipt deleted successfully.'];
    }

    public static function recordVendorPayment(array $data): array {
        $vendorId = (int)($data['vendor_id'] ?? 0);
        $amountSar = round((float)($data['amount_sar'] ?? 0), 2);
        $paymentDate = trim((string)($data['payment_date'] ?? date('Y-m-d')));
        $paymentMode = trim((string)($data['payment_mode'] ?? 'Direct Bank Transfer')) ?: 'Direct Bank Transfer';
        $referenceNumber = trim((string)($data['reference_number'] ?? ''));
        $remarks = trim((string)($data['remarks'] ?? ''));

        if ($vendorId <= 0 || $amountSar <= 0) {
            return ['success'=>false,'message'=>'Vendor and a valid SAR payment amount are required.'];
        }
        if (!Database::fetchOne('SELECT id FROM vendors WHERE id = ?', [$vendorId])) {
            return ['success'=>false,'message'=>'Vendor not found.'];
        }
        $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $paymentDate);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $paymentDate) {
            return ['success'=>false,'message'=>'Please provide a valid payment date.'];
        }
        Database::execute(
            'INSERT INTO vendor_payments (vendor_id, payment_date, amount_sar, payment_mode, reference_number, remarks) VALUES (?, ?, ?, ?, ?, ?)',
            [$vendorId, $paymentDate, $amountSar, $paymentMode, $referenceNumber ?: null, $remarks ?: null]
        );
        return ['success'=>true,'message'=>'Vendor payment recorded successfully.'];
    }

    public static function recordAgentAdjustment(array $data): array {
            $agentId = (int)($data['agent_id'] ?? 0);
            $amount = round((float)($data['amount_sar'] ?? 0), 2);
            $date = trim((string)($data['adjustment_date'] ?? date('Y-m-d')));
            $reason = trim((string)($data['reason'] ?? ''));
            if ($agentId <= 0 || $amount <= 0 || $reason === '') {
                return ['success' => false, 'message' => 'Agent, valid SAR amount, date, and reason are required.'];
            }
            $agent = Database::fetchOne('SELECT id FROM agents WHERE id = ?', [$agentId]);
            if (!$agent) return ['success' => false, 'message' => 'Agent not found.'];
            $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
                return ['success' => false, 'message' => 'Please provide a valid adjustment date.'];
            }
            Database::execute(
                'INSERT INTO agent_adjustments (agent_id, adjustment_date, amount_sar, reason, created_by) VALUES (?, ?, ?, ?, ?)',
                [$agentId, $date, $amount, $reason, Session::getActor()]
            );
            return ['success' => true, 'message' => 'Additional amount added to the agent ledger.'];
        }
    public static function getVendorLedger(int $vendorId): array {
        $vendor = Database::fetchOne('SELECT * FROM vendors WHERE id = ?', [$vendorId]);
        if (!$vendor) {
            return ['success' => false, 'vendor' => null, 'ledger' => [], 'total_payable_sar' => 0.0];
        }

        $rows = Database::fetchAll(
            "SELECT id, COALESCE(booking_date, DATE(created_at), CURRENT_DATE) AS entry_date,
                    passenger_name, passport_number, 'Booking Cost' AS description,
                    (buy_rate_sar + ticket_buy_rate_sar) AS charge_sar, 0.00 AS paid_sar,
                    'booking' AS record_type, created_at
             FROM master_bookings
             WHERE vendor_id = ? AND deleted_at IS NULL
             UNION ALL
             SELECT id, COALESCE(payment_date, DATE(created_at), CURRENT_DATE) AS entry_date,
                    CONCAT('Payment Disbursed: ', payment_mode) AS passenger_name,
                    reference_number AS passport_number, COALESCE(remarks, '') AS description,
                    0.00 AS charge_sar, amount_sar AS paid_sar, 'payment' AS record_type, created_at
             FROM vendor_payments
             WHERE vendor_id = ?
             ORDER BY entry_date ASC, created_at ASC, id ASC",
            [$vendorId, $vendorId]
        );

        $balance = (float)($vendor['opening_payable_sar'] ?? 0);
        $ledger = [];
        foreach ($rows as $row) {
            $balance += (float)$row['charge_sar'] - (float)$row['paid_sar'];
            $row['balance_sar'] = round($balance, 2);
            unset($row['created_at']);
            $ledger[] = $row;
        }

        return [
            'success' => true,
            'vendor' => $vendor,
            'ledger' => $ledger,
            'total_payable_sar' => round($balance, 2)
        ];
    }
}
