<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/VoucherController.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../controllers/VoucherShare.php';

$voucherId = (int)($_GET['id'] ?? 1);
$v = VoucherController::get($voucherId);

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function fmtDate(?string $date, string $format = 'd-M-y'): string {
    if (!$date) return '-';
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '-';
}

if (!$v) {
    die('<div style="font-family:Jost,Arial,sans-serif;padding:30px;text-align:center;color:#E0475B;">Voucher record not found.</div>');
}

$agencyLogo   = Database::fetchValue("SELECT setting_value FROM system_settings WHERE setting_key = 'agency_logo'") ?: 'assets/img/logo.png';
$defaultAgency = Database::fetchValue("SELECT setting_value FROM system_settings WHERE setting_key = 'agency_name'") ?: 'Aeroheights Travels & Tours';
$agencyPhone  = Database::fetchValue("SELECT setting_value FROM system_settings WHERE setting_key = 'agency_phone'") ?: '';
$makkahHelp   = Database::fetchValue("SELECT setting_value FROM system_settings WHERE setting_key = 'makkah_helpline'") ?: '+92 303 5137777';
$madinahHelp  = Database::fetchValue("SELECT setting_value FROM system_settings WHERE setting_key = 'madinah_helpline'") ?: '+92 303 4512512';

$companyName = trim((string)($v['company_name'] ?? '')) ?: $defaultAgency;
$showLogo    = ($companyName === 'Aeroheights Travels & Tours');

// Header QR -> signed read-only copy of this voucher on the main website (see VoucherShare).
$isPublicView = defined('AST_PUBLIC_VOUCHER');
$qrUrl = VoucherShare::url('v', $voucherId);
// Other agencies' vouchers carry the AST partner logo (transparent PNG) in the badge + watermark.
$brandLogo = $showLogo ? $agencyLogo : 'assets/img/logo.png';
if ($isPublicView) {
    // Embed the logos so the public page never references the software's paths.
    $embedLogo = static function (string $path): string {
        $logoFile = __DIR__ . '/../../' . ltrim($path, '/');
        $logoMime = ['png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml'][strtolower(pathinfo($logoFile, PATHINFO_EXTENSION))] ?? 'image/jpeg';
        return is_file($logoFile) ? 'data:' . $logoMime . ';base64,' . base64_encode((string)file_get_contents($logoFile)) : '';
    };
    $agencyLogo = $embedLogo((string)$agencyLogo);
    $brandLogo = $showLogo ? $agencyLogo : $embedLogo($brandLogo);
}

// PAX category counts are always derived live from the manifest (ground truth).
$mutamers = $v['mutamers'] ?? [];
$paxTotal = count($mutamers);
$paxAdult = 0; $paxChild = 0; $paxInfant = 0;
foreach ($mutamers as $m) {
    switch ($m['is_adult'] ?? 'Adult') {
        case 'Child':  $paxChild++;  break;
        case 'Infant': $paxInfant++; break;
        default:       $paxAdult++;  break;
    }
}
if ($paxTotal === 0) { $paxTotal = (int)($v['total_pax'] ?? 0); $paxAdult = $paxTotal; }
$paxSummary = "PAX: {$paxTotal} (A:{$paxAdult},C:{$paxChild},I:{$paxInfant}), Beds=" . (int)($v['total_beds'] ?? 0);

$ubCode = 'AHT-' . str_pad((string)$v['id'], 4, '0', STR_PAD_LEFT);

// Real ground-transport itinerary auto-generated from the voucher's Transport section
// (Airport -> hotel -> hotel -> Airport), chained into one readable route string
// (e.g. JED-MAK + MAK-MED + MED-MAK + MAK-JED => JED-MAK-MED-MAK-JED).
$transportLegs = Database::fetchAll(
    "SELECT service_date, vehicle_type, route_details
     FROM transport_bookings
     WHERE voucher_id = ? AND auto_generated IN (1, 2) AND deleted_at IS NULL
     ORDER BY service_date ASC, id ASC",
    [$voucherId]
);

$transportRouteChain = '';
$transportVehicleType = '';
$transportDateRange = '';
if ($transportLegs) {
    $routeChain = [];
    foreach ($transportLegs as $i => $leg) {
        $segments = array_values(array_filter(explode('-', (string)$leg['route_details']), static fn($p) => $p !== ''));
        if (!$segments) continue;
        // Continue the chain when this leg starts where the last one ended; otherwise (e.g. a custom route) append it whole.
        if (!$routeChain) $routeChain = $segments;
        elseif (end($routeChain) === $segments[0]) $routeChain = array_merge($routeChain, array_slice($segments, 1));
        else $routeChain = array_merge($routeChain, $segments);
    }
    $transportRouteChain = implode('-', $routeChain);

    $vehicleTypes = array_values(array_unique(array_filter(array_map(
        static fn($leg) => strtoupper(trim((string)$leg['vehicle_type'])), $transportLegs
    ))));
    $transportVehicleType = implode(', ', $vehicleTypes);

    $firstDate = fmtDate($transportLegs[0]['service_date']);
    $lastDate = fmtDate($transportLegs[count($transportLegs) - 1]['service_date']);
    $transportDateRange = $firstDate === $lastDate ? $firstDate : "{$firstDate} - {$lastDate}";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= h($companyName) ?> — Hotel Voucher (<?= h($v['voucher_no']) ?>)</title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800;900&family=Noto+Nastaliq+Urdu:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Jost', Arial, sans-serif;
            background: #DCE5ED;
            margin: 0;
            padding: 24px;
            color: #161A35;
        }
        .font-mono { font-family: 'Jost', Arial, sans-serif; font-variant-numeric: tabular-nums; }
        .urdu-text {
            direction: rtl;
            text-align: right;
            font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', Tahoma, Arial, sans-serif;
            font-size: 13px;
            line-height: 2.1;
        }

        .sheet {
            max-width: 820px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #8A90A8;
            border-radius: 10px;
            padding: 20px 24px;
            box-shadow: 0 10px 25px rgba(22,26,53,0.08);
        }

        /* Header */
        .header-grid {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 16px;
            align-items: start;
            border-bottom: 2px solid #161A35;
            padding-bottom: 10px;
        }
        .company-name { font-size: 17px; font-weight: 800; color: #26206f; text-transform: uppercase; letter-spacing: 0.02em; margin: 0; }
        .header-left .company-name { color: #26206f; }
        .header-right .company-name { color: #161A35; }
        .header-sub { font-size: 10.5px; color: #8A90A8; font-weight: 700; margin-top: 2px; }
        .header-meta { font-size: 11px; color: #4A5170; margin-top: 4px; }
        .header-logo { text-align: center; }
        .header-logo img { height: 58px; width: auto; object-fit: contain; }
        /* Large faded logo watermark behind the whole voucher */
        .sheet { position: relative; overflow: hidden; }
        .sheet > *:not(.voucher-watermark) { position: relative; z-index: 1; }
        .voucher-watermark {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-12deg);
            width: 78%; max-width: 620px; opacity: 0.08; pointer-events: none; z-index: 0;
            mix-blend-mode: multiply;
        }
        .voucher-watermark img { width: 100%; height: auto; display: block; }

        /* Mini logo badge on the right when another agency's name is printed */
        /* Header QR (scan -> verified copy of this voucher on aeroheightstravels.com) */
        .wa-qr-box { display: inline-flex; flex-direction: column; align-items: center; gap: 3px; }
        .wa-qr { background: #fff; padding: 4px; border: 1px solid #DCE5ED; border-radius: 8px; line-height: 0; }
        .wa-qr svg { display: block; width: 100%; height: 100%; }
        .wa-qr-big .wa-qr { width: 86px; height: 86px; }
        .wa-qr-small .wa-qr { width: 54px; height: 54px; }
        .wa-qr-caption { font-size: 8.5px; font-weight: 800; color: #3B8296; letter-spacing: 0.03em; text-transform: uppercase; white-space: nowrap; }
        .wa-qr-small .wa-qr-caption { font-size: 7.5px; }
        .header-right-row { display: flex; justify-content: flex-end; align-items: center; gap: 10px; }
        /* Public (website) view: verified bar + link back to the main website */
        .public-bar { max-width: 820px; margin: 0 auto 12px; display: flex; justify-content: space-between; align-items: center; gap: 10px; font-size: 12px; font-weight: 700; }
        .public-bar a { color: #26206f; text-decoration: none; background: #fff; border: 1px solid #b5d6f6; padding: 7px 14px; border-radius: 999px; }
        .public-bar .verified { color: #3B8296; background: #EDF8FA; border: 1px solid #A9E3EA; padding: 7px 14px; border-radius: 999px; }
        .mini-logo {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 4px 8px; margin-bottom: 4px; background: #fff;
            border: 1px solid #b5d6f6; border-radius: 10px;
            box-shadow: 0 2px 6px rgba(33, 131, 223, 0.18), inset 0 0 0 2px #eef6fd;
        }
        /* Another agency's name printed big and clear; our logo stays small on the right */
        .company-name.agency-big { font-size: 26px; font-weight: 900; letter-spacing: 0.03em; line-height: 1.15; }
        .agency-tagline {
            display: inline-block; margin: 5px 0 2px; padding: 3px 12px 3px 10px;
            background: linear-gradient(90deg, #eef6fd, #ffffff); border-left: 3px solid #2183DF; border-radius: 0 999px 999px 0;
            font-size: 11px; font-weight: 800; color: #26206f; letter-spacing: 0.06em; text-transform: uppercase;
        }
        .agency-tagline .star { color: #2183DF; margin-right: 4px; }
        .mini-logo img { height: 36px; width: auto; object-fit: contain; display: block; }

        /* Attractive header: soft cream band with an orange/navy accent line; partner logo shown clean and large (no box) */
        .header-grid {
            position: relative; align-items: center;
            background: linear-gradient(115deg, #EEF6FD 0%, #ffffff 38%, #ffffff 62%, #EEF6FD 100%);
            border: none; border-radius: 14px;
            box-shadow: inset 0 0 0 1px #D9EAFB;
            padding: 14px 18px; margin-bottom: 6px;
        }
        .header-grid::after {
            content: ''; position: absolute; left: 18px; right: 18px; bottom: -2px; height: 3px; border-radius: 3px;
            background: linear-gradient(90deg, #FEC624 0%, #26206F 30%, #285A9B 70%, #FEC624 100%);
        }
        .company-name.agency-big { color: #26206f; text-shadow: 0 1px 0 #fff, 0 2px 6px rgba(33, 131, 223, 0.18); }
        .mini-logo { padding: 0; margin-bottom: 4px; background: none; border: none; border-radius: 0; box-shadow: none; }
        .mini-logo img { height: 74px; filter: drop-shadow(0 2px 3px rgba(22, 26, 53, 0.12)); }
        .header-logo img { mix-blend-mode: multiply; } /* blend the white JPG background into the header band */

        .header-bottom { display: flex; justify-content: space-between; align-items: flex-end; padding: 8px 0 4px; font-size: 11.5px; }
        .header-bottom .pax-line { font-weight: 700; }

        .title-banner {
            text-align: center;
            margin: 6px 0 10px;
        }
        .title-banner span {
            display: inline-block;
            background: linear-gradient(125deg, #161A35 0%, #26206F 55%, #285A9B 100%);
            color: #fff;
            font-weight: 800;
            font-size: 15px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 5px 26px;
            border-radius: 999px;
        }

        .meta-bar {
            display: grid;
            grid-template-columns: 2fr 1fr 1.4fr;
            border: 1px solid #8A90A8;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .meta-bar div { padding: 5px 8px; border-right: 1px solid #8A90A8; }
        .meta-bar div:last-child { border-right: none; }

        .section-title {
            background: #DCE5ED;
            color: #161A35;
            font-weight: 800;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            text-align: center;
            padding: 4px 6px;
            border: 1px solid #8A90A8;
        }

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #8A90A8; padding: 3px 5px; font-size: 10.5px; }
        th { background-color: #DCE5ED; color: #161A35; font-weight: 700; text-align: center; text-transform: uppercase; font-size: 9.5px; }
        td { color: #161A35; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: 700; }

        .total-nights-row td { text-align: right; font-weight: 800; background: #F6F8FC; }

        .flight-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
        .flight-grid .section-title { margin-bottom: 0; }

        .special-instructions { font-size: 11px; margin: 4px 0 10px; }
        .special-instructions strong { font-style: italic; }

        .helpline-bar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            font-size: 11px;
            font-weight: 800;
            margin: 6px 0 14px;
        }

        .urdu-block { border-top: 1px solid #8A90A8; padding-top: 10px; }
        .urdu-block p { margin: 0 0 6px; }
        .urdu-bullet { font-weight: 900; font-size: 15px; margin-left: 6px; color: #161A35; }

        .print-actions { display: flex; justify-content: flex-end; gap: 8px; padding-top: 16px; margin-top: 12px; border-top: 1px solid #DCE5ED; }
        .print-actions button {
            font-size: 12px; font-weight: 700; padding: 8px 18px; border-radius: 10px; border: none; cursor: pointer;
        }
        .btn-close { background: #DCE5ED; color: #4A5170; }
        .btn-print { background: #26206F; color: #fff; }

        @media print {
            @page { size: A4 portrait; margin: 8mm 10mm; }
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                background: #fff !important;
                color: #161A35;
                font-size: 11px;
                line-height: 1.25;
                padding: 0;
            }
            .sheet { max-width: none; border: none; border-radius: 0; box-shadow: none; padding: 0; }
            .no-print { display: none !important; }
            table { width: 100%; border-collapse: collapse; page-break-inside: avoid; }
            th, td { border: 1px solid #8A90A8; padding: 3px 5px; font-size: 10.5px; }
            th { background-color: #DCE5ED !important; color: #161A35 !important; font-weight: 700; text-align: center; }
            .section-title { background: #DCE5ED !important; color: #161A35 !important; }
            .urdu-text {
                direction: rtl;
                text-align: right;
                font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', Tahoma, Arial, sans-serif;
                font-size: 10px;
                line-height: 1.5;
            }
        }
    </style>
</head>
<body>
<?php if ($isPublicView): ?>
    <div class="public-bar no-print">
        <a href="https://aeroheightstravels.com/">&larr; Visit aeroheightstravels.com</a>
        <span class="verified">&#10004; Verified Voucher</span>
    </div>
<?php endif; ?>

    <div class="sheet">
        <div class="voucher-watermark" aria-hidden="true"><img src="<?= h($brandLogo) ?>" alt="" onerror="this.parentNode.style.display='none'"></div>

        <!-- Header -->
        <div class="header-grid">
            <div class="header-left">
                <p class="company-name<?= $showLogo ? '' : ' agency-big' ?>"><?= h($companyName) ?></p>
                <?php if (!$showLogo): ?><p class="agency-tagline"><span class="star">&#9733;</span>Your Trust Is Our Best Reward</p><?php endif; ?>
                <p class="header-meta">Voucher Date: <?= strtoupper(fmtDate($v['voucher_date'], 'd/M/Y')) ?></p>
            </div>
            <div class="header-logo">
                <?php if ($showLogo): ?>
                    <img src="<?= h($agencyLogo) ?>" alt="Agency Logo" onerror="this.style.display='none'">
                <?php endif; ?>
                <?php if (!$showLogo): ?>
                    <div class="wa-qr-box wa-qr-big"><div class="wa-qr" data-qr="<?= h($qrUrl) ?>"></div><span class="wa-qr-caption">Scan to Verify Voucher</span></div>
                <?php endif; ?>
            </div>
            <div class="header-right text-right" style="text-align:right;">
                <?php if (!$showLogo): ?>
                    <div class="mini-logo"><img src="<?= h($brandLogo) ?>" alt="Logo" onerror="this.parentNode.style.display='none'"></div>
                <?php else: ?>
                    <div class="header-right-row">
                        <div>
                            <p class="company-name"><?= h($companyName) ?></p>
                            <p class="header-sub">Umrah &amp; Travel Services</p>
                        </div>
                        <div class="wa-qr-box wa-qr-small"><div class="wa-qr" data-qr="<?= h($qrUrl) ?>"></div><span class="wa-qr-caption">Verify</span></div>
                    </div>
                <?php endif; ?>
                <!-- <p class="header-meta"><strong>Whats APP:</strong> </p> -->
            </div>
        </div>

        <div class="header-bottom">
            <div>
                <div>Package: <?= h($v['package_name'] ?: '-') ?></div>
                <div class="pax-line"><?= h($paxSummary) ?></div>
            </div>
        </div>

        <div class="title-banner"><span>Hotel Voucher</span></div>

        <div class="meta-bar">
            <div>Family Head: <?= h($v['family_head']) ?></div>
            <div><?= h($ubCode) ?></div>
            <div>Manual No: —</div>
        </div>

        <!-- Mutamers Manifest -->
        <div class="section-title">Mutamers</div>
        <table>
            <thead>
                <tr>
                    <th style="width:32px;">SNO</th>
                    <th>Passport</th>
                    <th>Mutamer Name</th>
                    <th style="width:28px;">G</th>
                    <th style="width:52px;">PAX</th>
                    <th style="width:40px;">Bed</th>
                    <th>MOFA #</th>
                    <th>GRP #</th>
                    <th>Visa #</th>
                    <th>PNR</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mutamers as $idx => $m): ?>
                    <tr>
                        <td class="text-center font-bold"><?= $idx + 1 ?></td>
                        <td class="font-mono font-bold"><?= h($isPublicView ? VoucherShare::maskPassport($m['passport_number']) : $m['passport_number']) ?></td>
                        <td class="font-bold"><?= h($m['mutamer_name']) ?></td>
                        <td class="text-center"><?= h($m['gender']) ?></td>
                        <td class="text-center"><?= h($m['is_adult']) ?></td>
                        <td class="text-center font-bold"><?= h($m['bed_assigned']) ?></td>
                        <td></td>
                        <td class="text-center">0</td>
                        <td></td>
                        <td></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Accommodation -->
        <div class="section-title">Accommodation</div>
        <table>
            <thead>
                <tr>
                    <th>City</th>
                    <th>Hotel Name</th>
                    <th>View</th>
                    <th>Meal</th>
                    <th>Conf#</th>
                    <th>Room Type</th>
                    <th>Checkin</th>
                    <th>Checkout</th>
                    <th>Nights</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($v['stays'] as $s): ?>
                    <tr>
                        <td class="font-bold"><?= h($s['city']) ?></td>
                        <td><?= h($s['hotel_name']) ?></td>
                        <td class="text-center">Standard</td>
                        <td class="text-center"><?= h($s['meal_plan']) ?></td>
                        <td class="text-center"><?= h($s['confirmation_number']) ?></td>
                        <td><?= h($s['room_type']) ?></td>
                        <td class="font-mono text-center"><?= fmtDate($s['checkin_date']) ?></td>
                        <td class="font-mono text-center"><?= fmtDate($s['checkout_date']) ?></td>
                        <td class="text-center font-bold"><?= (int)$s['nights'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($v['stays'])): ?>
                    <tr>
                        <td></td>
                        <td class="font-bold">Self</td>
                        <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="total-nights-row">
                    <td colspan="9">Total Nights: <span class="font-mono"><?= (int)$v['total_nights'] ?></span></td>
                </tr>
            </tfoot>
        </table>

        <!-- Transport / Services -->
        <div class="section-title">Transport/Services</div>
        <table>
            <thead>
                <tr>
                    <th style="width:90px;">Travel Date</th>
                    <th>Transporter</th>
                    <th>Type</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="font-mono text-center"><?= h($transportDateRange ?: '-') ?></td>
                    <?php // No transport added on the voucher -> the guests arrange their own ("Self"). ?>
                    <td class="font-bold text-center"><?= $transportLegs ? 'Company Transport' : 'Self' ?></td>
                    <td class="text-center"><?= h($transportVehicleType ?: ($v['transport_type'] ?: '-')) ?></td>
                    <td><?= h($transportRouteChain ?: '-') ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Flight Details -->
        <div class="flight-grid">
            <div>
                <div class="section-title">Departure (Pakistan to KSA)</div>
                <table>
                    <thead>
                        <tr><th>Flight</th><th>Sector</th><th>Departure</th><th>Arrival</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-center"><?= h($v['flight_out_no'] ?: '-') ?></td>
                            <td class="text-center"><?= h($v['flight_out_from'] && $v['flight_out_to'] ? $v['flight_out_from'] . '-' . $v['flight_out_to'] : '-') ?></td>
                            <td class="font-mono text-center"><?= $v['flight_out_dep_date'] ? strtoupper(fmtDate($v['flight_out_dep_date'], 'd-M')) . ' ' . substr((string)$v['flight_out_dep_time'], 0, 5) : '-' ?></td>
                            <td class="font-mono text-center"><?= $v['flight_out_arr_date'] ? strtoupper(fmtDate($v['flight_out_arr_date'], 'd-M')) . ' ' . substr((string)$v['flight_out_arr_time'], 0, 5) : '-' ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div>
                <div class="section-title">Arrival (KSA to PAK)</div>
                <table>
                    <thead>
                        <tr><th>Flight</th><th>Sector</th><th>Departure</th><th>Arrival</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-center"><?= h($v['flight_ret_no'] ?: '-') ?></td>
                            <td class="text-center"><?= h($v['flight_ret_from'] && $v['flight_ret_to'] ? $v['flight_ret_from'] . '-' . $v['flight_ret_to'] : '-') ?></td>
                            <td class="font-mono text-center"><?= $v['flight_ret_dep_date'] ? strtoupper(fmtDate($v['flight_ret_dep_date'], 'd-M')) . ' ' . substr((string)$v['flight_ret_dep_time'], 0, 5) : '-' ?></td>
                            <td class="font-mono text-center"><?= $v['flight_ret_arr_date'] ? strtoupper(fmtDate($v['flight_ret_arr_date'], 'd-M')) . ' ' . substr((string)$v['flight_ret_arr_time'], 0, 5) : '-' ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="special-instructions"><strong>Special Instructions:</strong> <?= h($v['special_instructions'] ?: 'HOTEL ACCOMMODATION AS PER VOUCHER') ?></div>

        <div class="helpline-bar">
            <div>OFFICE: <?= h($makkahHelp) ?></div>
            <div>HELPLINE: <?= h($madinahHelp) ?></div>
        </div>

        <!-- Urdu Terms & Conditions -->
        <div class="urdu-block urdu-text" dir="rtl">
            <p><span class="urdu-bullet">●</span>آپ کا ہوٹل اور پیکج اس دستاویز پر لکھ دیا گیا ہے۔ اس کے مطابق آپ کو رہائش اور دیگر سہولیات فراہم کی جائیں گی۔</p>
            <p><span class="urdu-bullet">●</span>سفری سامان حرمین شریفین کی طرف لے جانا سعودی انتظامیہ کی طرف سے ممنوع ہے خلاف ورزی کی صورت میں حکومت جرمانہ کر سکتی ہے جس کی ادائیگی آپ کی ذمہ داری ہوگی۔</p>
            <p><span class="urdu-bullet">●</span>نشہ آور اشیاء شرعاً اور قانوناً ممنوع ہیں۔ سعودیہ میں منشیات لے جانے کی سزا موت ہے۔</p>
            <p><span class="urdu-bullet">●</span>حرمین شریفین کے اندر زمین پر پڑی چیز پرس موبائل فون یا کوئی قیمتی چیز ہرگز نہ اٹھائیں ورنہ آپ مشکل میں پڑ جائیں گے۔</p>
            <p><span class="urdu-bullet">●</span>جدہ ایئرپورٹ پر امیگریشن اور سعودی انتظامات میں 3 سے 5 گھنٹے لگ سکتے ہیں۔ سعودیہ پہنچ کر اپنے گھر والوں کو خیریت سے پہنچنے کا فون ضرور کر لیں۔</p>
            <p><span class="urdu-bullet">●</span>ہوٹل میں چیک ان کا ٹائم دوپہر 04:00 بجے ہے اور چیک آؤٹ کا ٹائم دوپہر 02:00 بجے ہے۔ اس کے بعد اگلی Night چارج ہوگی۔</p>
            <p><span class="urdu-bullet">●</span>واپسی فلائٹ سے 10 گھنٹے پہلے معتمر اپنے سامان سمیت ہوٹل کے رسیپشن پر موجود رہیں۔</p>
            <p><span class="urdu-bullet">●</span>سعودی حکومت کے قانون کے مطابق کمپنی کے ہوٹلوں کے علاوہ کسی غیر رجسٹرڈ ہوٹل میں قیام کرنا جرم ہے۔</p>
        </div>

        <!-- Print Controls -->
        <div class="print-actions no-print">
            <?php if (!$isPublicView): ?><button type="button" class="btn-close" onclick="window.close()">Close</button><?php endif; ?>
            <button type="button" class="btn-print" onclick="window.print()">Print Voucher</button>
        </div>

    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <script>
    document.querySelectorAll('.wa-qr[data-qr]').forEach(function (el) {
        if (typeof qrcode !== 'function' || !el.dataset.qr) { el.closest('.wa-qr-box').style.display = 'none'; return; }
        var qr = qrcode(0, 'M');
        qr.addData(el.dataset.qr);
        qr.make();
        el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
    });
    </script>
<?php if ($isPublicView): ?>
    <script>
    // Opened from the QR: make Back go to the main website instead of leaving the page history empty.
    (function () {
        var here = location.href;
        history.replaceState({ home: 1 }, '', '/');
        history.pushState({ voucher: 1 }, '', here);
        window.addEventListener('popstate', function () { location.replace('https://aeroheightstravels.com/'); });
    })();
    </script>
<?php endif; ?>
</body>
</html>
