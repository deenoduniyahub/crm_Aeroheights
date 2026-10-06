<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/HotelBookingController.php';
require_once __DIR__ . '/../../controllers/AdminController.php';
require_once __DIR__ . '/../../config/Database.php';

$bookingId = (int)($_GET['id'] ?? 0);
$b = HotelBookingController::get($bookingId);

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function fmtDate(?string $date, string $format = 'd/m/Y'): string {
    if (!$date) return '-';
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '-';
}

if (!$b) {
    die('<div style="font-family:Arial;padding:30px;text-align:center;color:#b91c1c;">Hotel booking record not found.</div>');
}

$settings     = AdminController::getSettings();
$agencyName   = $settings['agency_name'] ?? 'Aeroheights Travels & Tours';
$agencyLogo   = $settings['agency_logo'] ?? 'assets/img/logo.png';
$agencyPhone  = $settings['agency_phone'] ?? '';
$agencyEmail  = $settings['agency_email'] ?? 'aeroheights2024@gmail.com';
$agencyAddr   = $settings['agency_address'] ?? 'Pakistan';

$bank = Database::fetchOne("SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY id ASC LIMIT 1");

$totalPax = (int)$b['pax_adults'] + (int)$b['pax_children'] + (int)$b['pax_infants'];
$paxSummary = "A:{$b['pax_adults']} C:{$b['pax_children']} I:{$b['pax_infants']}";
$stays = $b['stays'] ?? [];
$hotelSummary = implode(' / ', array_map(static fn($s) => $s['hotel_name'] . ' (' . $s['city'] . ')', $stays));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= h($agencyName) ?> — Hotel Invoice (<?= h($b['booking_ref']) ?>)</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', Arial, sans-serif; background: #e2e8f0; margin: 0; padding: 24px; color: #0f172a; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }

        .sheet { max-width: 780px; margin: 0 auto; background: #fff; border: 1px solid #94a3b8; border-radius: 10px; padding: 22px 26px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); }

        .header-grid { display: grid; grid-template-columns: 1fr auto 1fr; gap: 16px; align-items: start; border-bottom: 2px solid #0f172a; padding-bottom: 10px; }
        .company-name { font-size: 16px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.02em; margin: 0; }
        .header-meta { font-size: 11px; color: #334155; margin-top: 3px; line-height: 1.5; }
        .header-logo { text-align: center; }
        .header-logo img { height: 56px; width: auto; object-fit: contain; }
        .header-right { text-align: right; }
        .status-tag { display: inline-block; margin-top: 4px; background: #0f172a; color: #fff; font-size: 10px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; padding: 3px 10px; border-radius: 999px; }

        .title-banner { text-align: center; margin: 10px 0 10px; }
        .title-banner span { display: inline-block; background: #2183DF; color: #fff; font-weight: 800; font-size: 14px; letter-spacing: 0.06em; text-transform: uppercase; padding: 5px 26px; border-radius: 999px; }

        .meta-bar { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #777; font-size: 11.5px; font-weight: 700; margin-bottom: 10px; }
        .meta-bar div { padding: 6px 10px; border-right: 1px solid #777; }
        .meta-bar div:nth-child(2n) { border-right: none; }
        .meta-bar .lbl { color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 9.5px; display: block; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #777; padding: 5px 6px; font-size: 11px; }
        th { background-color: #0f172a; color: #fff; font-weight: 700; text-align: center; text-transform: uppercase; font-size: 9.5px; }
        td { color: #1e293b; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: 700; }

        .totals-box { width: 260px; margin-left: auto; border: 1px solid #777; font-size: 11.5px; margin-bottom: 12px; }
        .totals-box div { display: flex; justify-content: space-between; padding: 5px 10px; border-bottom: 1px solid #e2e8f0; }
        .totals-box div:last-child { border-bottom: none; background: #f1f5f9; font-weight: 800; font-size: 13px; }

        .bank-section { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 12px 0; font-size: 11px; }
        .bank-section .section-title { background: #d9d9d9; color: #111827; font-weight: 800; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; padding: 4px 8px; border: 1px solid #777; margin-bottom: 6px; }
        .bank-section p { margin: 2px 0; }

        .remarks { font-size: 10.5px; color: #475569; margin: 10px 0; line-height: 1.5; }

        .print-actions { display: flex; justify-content: flex-end; gap: 8px; padding-top: 16px; margin-top: 12px; border-top: 1px solid #e2e8f0; }
        .print-actions button { font-size: 12px; font-weight: 700; padding: 8px 18px; border-radius: 10px; border: none; cursor: pointer; }
        .btn-close { background: #e2e8f0; color: #334155; }
        .btn-print { background: #2563eb; color: #fff; }

        .footer-strip { text-align: center; font-size: 10px; color: #64748b; border-top: 1px solid #cbd5e1; padding-top: 8px; margin-top: 6px; }

        @media print {
            @page { size: A4 portrait; margin: 10mm 12mm; }
            body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; background: #fff !important; color: #000; font-size: 11px; line-height: 1.25; padding: 0; }
            .sheet { max-width: none; border: none; border-radius: 0; box-shadow: none; padding: 0; }
            .no-print { display: none !important; }
            th { background-color: #0f172a !important; color: #fff !important; }
        }
    </style>
</head>
<body>
    <div class="sheet">

        <div class="header-grid">
            <div class="header-left">
                <p class="company-name"><?= h($agencyName) ?></p>
                <p class="header-meta"><?= h($agencyAddr) ?><br>Email: <?= h($agencyEmail) ?><?= $agencyPhone ? ' | Tel: ' . h($agencyPhone) : '' ?></p>
            </div>
            <div class="header-logo">
                <img src="<?= h($agencyLogo) ?>" alt="Agency Logo" onerror="this.style.display='none'">
            </div>
            <div class="header-right">
                <p class="header-meta">Invoice Date: <span class="font-mono font-bold"><?= h(fmtDate($b['booking_date'])) ?></span></p>
                <p class="header-meta">To: <strong><?= h($b['agent_name']) ?></strong><?= $b['agent_phone'] ? '<br>' . h($b['agent_phone']) : '' ?></p>
                <span class="status-tag">Agent Hotel Invoice</span>
            </div>
        </div>

        <div class="title-banner"><span>Hotel Invoice</span></div>

        <div class="meta-bar">
            <div><span class="lbl">Invoice / Res No</span><span class="font-mono font-bold"><?= h($b['booking_ref']) ?></span></div>
            <div><span class="lbl">Hotel(s)</span><?= h($hotelSummary) ?></div>
            <div><span class="lbl">Guest Name</span><?= h($b['lead_guest_name']) ?></div>
            <div><span class="lbl">Total PAX</span><?= $totalPax ?> (<?= h($paxSummary) ?>)</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:32px;">Rooms</th>
                    <th>Hotel Name</th>
                    <th>Room Type</th>
                    <th>Checkin</th>
                    <th style="width:44px;">Nights</th>
                    <th>Checkout</th>
                    <th>Meal Plan</th>
                    <th class="text-right">Rate/Night (SAR)</th>
                    <th class="text-right">Amount (SAR)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stays as $s): ?>
                <tr>
                    <td class="text-center font-bold"><?= (int)$s['rooms'] ?></td>
                    <td class="font-bold"><?= h($s['hotel_name']) ?> <span class="text-slate-400">(<?= h($s['city']) ?>)</span></td>
                    <td class="text-center"><?= h($s['room_type']) ?></td>
                    <td class="font-mono text-center"><?= h(fmtDate($s['checkin_date'])) ?></td>
                    <td class="text-center font-bold"><?= (int)$s['nights'] ?></td>
                    <td class="font-mono text-center"><?= h(fmtDate($s['checkout_date'])) ?></td>
                    <td class="text-center"><?= h($s['meal_plan']) ?></td>
                    <td class="text-right font-mono"><?= number_format((float)$s['sell_rate_per_night'], 2) ?></td>
                    <td class="text-right font-mono font-bold"><?= number_format((float)$s['sell_total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right font-bold" style="background:#f1f5f9;">Total Nights</td>
                    <td class="text-center font-bold" style="background:#f1f5f9;"><?= (int)$b['total_nights'] ?></td>
                    <td colspan="3" style="background:#f1f5f9;"></td>
                    <td class="text-right font-bold" style="background:#f1f5f9;"><?= number_format((float)$b['sell_total_sar'], 2) ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="totals-box">
            <div><span>Net Accommodation Charges</span><span class="font-mono">SAR <?= number_format((float)$b['sell_total_sar'], 2) ?></span></div>
            <div><span>Total Amount Payable</span><span class="font-mono">SAR <?= number_format((float)$b['sell_total_sar'], 2) ?></span></div>
        </div>

        <?php if ($b['remarks']): ?>
        <div class="remarks">
            <strong>Remarks:</strong> <?= h($b['remarks']) ?>
        </div>
        <?php endif; ?>

        <?php if ($bank): ?>
        <div class="bank-section">
            <div>
                <div class="section-title">Our Bank Details</div>
                <p><strong>Bank Name:</strong> <?= h($bank['bank_name']) ?></p>
                <p><strong>Account Title:</strong> <?= h($bank['account_title']) ?></p>
                <p><strong>Account #:</strong> <?= h($bank['account_number']) ?></p>
                <?php if ($bank['branch_code']): ?><p><strong>Branch Code:</strong> <?= h($bank['branch_code']) ?></p><?php endif; ?>
            </div>
            <div>
                <div class="section-title">Payment Terms</div>
                <p>Kindly settle the amount payable as per your agreed credit terms to avoid disruption to future bookings.</p>
                <p>This invoice reflects the confirmed sell rate agreed with your agency.</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="footer-strip">
            <?= h($agencyName) ?> — <?= h($agencyAddr) ?><br>
            Email: <?= h($agencyEmail) ?><?= $agencyPhone ? ' &nbsp;|&nbsp; Tel: ' . h($agencyPhone) : '' ?>
        </div>

        <div class="print-actions no-print">
            <button type="button" class="btn-close" onclick="window.close()">Close</button>
            <button type="button" class="btn-print" onclick="window.print()">Print Invoice</button>
        </div>

    </div>
</body>
</html>
