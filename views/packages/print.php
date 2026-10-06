<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/AdminController.php';
require_once __DIR__ . '/../../controllers/PackageQuotationController.php';

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function fmtDate(?string $date, string $format = 'd M Y'): string {
    if (!$date) return '';
    $ts = strtotime($date);
    return $ts ? strtoupper(date($format, $ts)) : '';
}

function fmtMoney($value): string {
    if ($value === '' || $value === null || !is_numeric($value)) return '';
    $n = (float)$value;
    return number_format($n, floor($n) == $n ? 0 : 2) . '/-';
}

// A saved quotation is printed by id (GET); an unsaved one is previewed straight from the posted form.
$quoteId = (int)($_GET['id'] ?? 0);
if ($quoteId > 0) {
    $record = PackageQuotationController::getById($quoteId);
    if (!$record) {
        die('<div style="font-family:Jost,Arial,sans-serif;padding:30px;text-align:center;color:#E0475B;">Quotation not found.</div>');
    }
    $q = $record['data'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $q = PackageQuotationController::normalize($_POST);
} else {
    header('Location: index.php?page=packages');
    exit;
}

$in = static fn(string $key): string => trim((string)($q[$key] ?? ''));

$settings    = AdminController::getSettings();
$agencyName  = $settings['agency_name'] ?? 'Aeroheights Travels & Tours';
$agencyLogo  = $settings['agency_logo'] ?? 'assets/img/logo.png';
$agencyPhone = $settings['agency_phone'] ?? '';
$agencyEmail = $settings['agency_email'] ?? 'aeroheights2024@gmail.com';
$agencyAddr  = $settings['agency_address'] ?? 'Pakistan';

$includeCatalog = [
    'visa'      => ['Umrah E-Visa', 'fa-passport'],
    'tickets'   => ['Return Tickets', 'fa-plane-departure'],
    'hotel'     => ['Hotel Accommodation', 'fa-hotel'],
    'transport' => ['Transport', 'fa-van-shuttle'],
    'ziyarat'   => ['Ziyarat', 'fa-kaaba'],
    'insurance' => ['Travel Insurance', 'fa-shield-heart'],
];
$includes = [];
foreach ($q['includes'] as $key) {
    if (isset($includeCatalog[$key])) $includes[] = $includeCatalog[$key];
}
foreach (preg_split('/\r\n|\r|\n/', $in('custom_includes')) as $line) {
    if (trim($line) !== '') $includes[] = [trim($line), 'fa-circle-check'];
}

$pax = [
    'Adults'  => $q['adults'],
    'Child'   => $q['children'],
    'Infants' => $q['infants'],
];

$hotels = $q['hotels'];
$showNights = (bool)array_filter(array_column($hotels, 'nights'));

$totalDays = $in('total_days');
if ($totalDays === '' && $showNights) {
    $totalDays = (string)array_sum(array_map('intval', array_column($hotels, 'nights')));
}

$prices = [
    '1 Adult price'  => $in('price_adult'),
    '1 Child price'  => $in('price_child'),
    '1 Infant price' => $in('price_infant'),
];
$totalPrice = $in('total_price');

$flights = [
    ['Departure to KSA', strtoupper($in('dep_sector')), fmtDate($in('dep_date')), strtoupper($in('dep_flight'))],
    ['Departure from KSA', strtoupper($in('ret_sector')), fmtDate($in('ret_date')), strtoupper($in('ret_flight'))],
];
$hasFlights = $in('dep_sector') . $in('dep_date') . $in('ret_sector') . $in('ret_date') !== '';

$clientName = $in('client_name') ?: 'Client';
$quoteNo    = $in('quote_no');
$signName   = $in('sign_name');
$signTitle  = $in('sign_title') ?: $agencyName;
$noteParts  = array_filter([$in('airline'), $in('baggage')]);
$note       = $in('note');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quotation <?= h($quoteNo) ?> — <?= h($clientName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ink: #161a35;
            --ink-2: #26206f;
            --orange: #2183DF;
            --amber: #FEC624;
            --muted: #8A90A8;
            --soft: #f6f8fc;
            --line: #dce5ed;
            --tint: #eef6fd;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Jost', Arial, sans-serif; background: #DCE5ED; margin: 0; padding: 24px; color: var(--ink); }

        .toolbar { max-width: 794px; margin: 0 auto 14px; display: flex; justify-content: flex-end; gap: 8px; }
        .toolbar button { border: 0; border-radius: 10px; padding: 9px 16px; font: 600 12px 'Jost', sans-serif; cursor: pointer; }
        .btn-close { background: #fff; color: #4A5170; border: 1px solid #DCE5ED !important; }
        .btn-print { background: #26206F; color: #fff; }

        .sheet { position: relative; max-width: 794px; margin: 0 auto; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 12px 30px rgba(22,26,53,0.10); }
        .top-bar { height: 8px; background: linear-gradient(90deg, var(--ink) 0 55%, var(--orange) 55% 85%, var(--amber) 85%); }
        .inner { position: relative; padding: 22px 40px 26px; }

        .watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; overflow: hidden; z-index: 0; }
        .watermark span { transform: rotate(-32deg); font-size: 76px; font-weight: 800; letter-spacing: 0.06em; color: rgba(33, 131, 223,0.06); white-space: nowrap; text-transform: uppercase; }
        .content { position: relative; z-index: 1; }

        .brand { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding-bottom: 14px; border-bottom: 1px solid var(--line); }
        .brand img { height: 62px; width: auto; object-fit: contain; }
        .brand-meta { text-align: right; font-size: 10.5px; color: var(--muted); line-height: 1.7; }
        .brand-meta b { color: var(--ink); font-weight: 700; font-size: 12px; display: block; }
        .brand-meta i { color: var(--orange); width: 14px; }

        .title-row { display: flex; align-items: flex-end; justify-content: space-between; margin: 18px 0 6px; }
        h1 { font-size: 34px; font-weight: 800; letter-spacing: 0.04em; margin: 0; color: var(--ink); line-height: 1; }
        h1 span { color: var(--orange); }
        .quote-meta { text-align: right; font-size: 10.5px; color: var(--muted); line-height: 1.7; }
        .quote-meta b { color: var(--ink); }

        .greet { margin: 14px 0 20px; }
        .greet .dear { font-size: 14px; font-weight: 600; margin: 0 0 3px; }
        .greet p { font-size: 12.5px; color: var(--muted); margin: 0; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 26px; }
        .section-h { font-size: 14.5px; font-weight: 700; margin: 0 0 12px; display: inline-block; padding-bottom: 5px; border-bottom: 3px solid var(--orange); }
        .section-h.full { display: block; border-bottom-width: 2px; }

        .inc { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
        .inc li { background: var(--soft); border-radius: 10px; padding: 9px 14px; font-size: 12.5px; font-weight: 500; display: flex; align-items: center; gap: 12px; }
        .inc i { color: var(--orange); font-size: 15px; width: 18px; text-align: center; }

        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12.5px; }
        .tbl thead th { background: var(--ink); color: #fff; font-size: 10.5px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; text-align: left; padding: 11px 16px; }
        .tbl thead th:first-child { border-top-left-radius: 10px; }
        .tbl thead th:last-child { border-top-right-radius: 10px; }
        .tbl tbody td { padding: 11px 16px; border-bottom: 1px solid var(--line); background: #fff; }
        .tbl tbody tr:nth-child(odd) td { background: #F6F8FC; }
        .tbl tbody td.strong { font-weight: 600; text-transform: uppercase; }
        .tbl tbody td.num { font-weight: 600; }

        .summary { margin-top: 26px; }

        .flights { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px; }
        .flight { border: 1px solid #FFE9A8; border-radius: 12px; overflow: hidden; }
        .flight .fh { background: var(--tint); text-align: center; font-weight: 600; font-size: 12.5px; padding: 9px; }
        .flight .fh i { color: var(--orange); margin-right: 6px; }
        .flight .fb { display: flex; justify-content: space-around; align-items: center; padding: 10px 8px; font-size: 13px; }
        .flight .fb b { font-weight: 700; letter-spacing: 0.04em; }
        .flight .fb small { display: block; color: var(--muted); font-size: 10px; text-align: center; }

        .divider { height: 2px; background: linear-gradient(90deg, var(--orange), var(--amber), transparent); border: 0; margin: 24px 0 20px; }

        .bottom { display: grid; grid-template-columns: 1fr 1fr; gap: 26px; align-items: start; }
        .note { background: #FFFAEB; border-left: 4px solid var(--orange); border-radius: 8px; padding: 12px 16px; font-size: 12px; color: var(--muted); line-height: 1.6; }
        .note b { color: var(--ink); }
        .sign { margin-top: 28px; text-align: center; max-width: 240px; }
        .sign .line { border-top: 1px dashed #DCE5ED; margin-bottom: 14px; }
        .sign .name { font-weight: 700; font-size: 14px; text-transform: uppercase; }
        .sign .role { font-size: 10.5px; color: var(--muted); letter-spacing: 0.12em; text-transform: uppercase; margin-top: 3px; line-height: 1.6; }

        .pricing { background: var(--ink); color: #fff; border-radius: 14px; padding: 18px 22px; }
        .pricing .ph { font-size: 12px; color: #DCE5ED; letter-spacing: 0.12em; text-transform: uppercase; margin-bottom: 8px; }
        .pricing .pr { display: flex; justify-content: space-between; font-size: 12.5px; padding: 9px 0; border-bottom: 1px solid rgba(255,255,255,0.10); }
        .pricing .total { display: flex; justify-content: space-between; align-items: center; border-top: 2px solid var(--orange); margin-top: 10px; padding-top: 14px; color: var(--amber); font-weight: 800; font-size: 19px; }

        .footer { margin-top: 26px; background: var(--soft); border-radius: 10px; padding: 11px 16px; font-size: 10.5px; color: var(--muted); display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px 16px; }
        .footer i { color: var(--orange); margin-right: 5px; }

        @media (max-width: 640px) {
            body { padding: 10px; }
            .inner { padding: 18px 16px; }
            .grid-2, .bottom, .flights { grid-template-columns: 1fr; }
            .brand { flex-direction: column; align-items: flex-start; }
            .brand-meta, .quote-meta { text-align: left; }
        }

        @media print {
            @page { size: A4 portrait; margin: 8mm; }
            body { background: #fff; padding: 0; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; border-radius: 0; max-width: none; }
            /* Compact spacing so a standard quotation fits on one A4 page */
            .inner { padding: 14px 26px 16px; }
            .brand { padding-bottom: 10px; }
            .brand img { height: 54px; }
            .title-row { margin-top: 12px; }
            .greet { margin: 8px 0 12px; }
            .inc { gap: 6px; }
            .inc li { padding: 7px 12px; }
            .tbl thead th { padding: 8px 14px; }
            .tbl tbody td { padding: 8px 14px; }
            .summary { margin-top: 16px; }
            .flights { margin-top: 12px; }
            .divider { margin: 14px 0 12px; }
            .sign { margin-top: 18px; }
            .footer { margin-top: 14px; }
            .tbl tr, .flight, .pricing, .note, .inc li { break-inside: avoid; page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn-close" onclick="window.close()"><i class="fa-solid fa-xmark"></i> Close</button>
        <button class="btn-print" onclick="window.print()"><i class="fa-solid fa-print"></i> Print / Save PDF</button>
    </div>

    <div class="sheet">
        <div class="top-bar"></div>
        <div class="inner">
            <div class="watermark"><span><?= h($agencyName) ?></span></div>
            <div class="content">

                <div class="brand">
                    <img src="<?= h($agencyLogo) ?>" alt="<?= h($agencyName) ?>" onerror="this.style.display='none'">
                    <div class="brand-meta">
                        <b><?= h($agencyName) ?></b>
                        <?php if ($agencyAddr): ?><div><i class="fa-solid fa-location-dot"></i> <?= h($agencyAddr) ?></div><?php endif; ?>
                        <?php if ($agencyPhone): ?><div><i class="fa-solid fa-phone"></i> <?= h($agencyPhone) ?></div><?php endif; ?>
                        <?php if ($agencyEmail): ?><div><i class="fa-solid fa-envelope"></i> <?= h($agencyEmail) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="title-row">
                    <h1>QUOTA<span>TION</span></h1>
                    <div class="quote-meta">
                        <?php if ($quoteNo): ?><div>Quotation No: <b><?= h($quoteNo) ?></b></div><?php endif; ?>
                        <?php if ($in('quote_date')): ?><div>Date: <b><?= h(fmtDate($in('quote_date'))) ?></b></div><?php endif; ?>
                        <?php if ($in('valid_until')): ?><div>Valid Until: <b><?= h(fmtDate($in('valid_until'))) ?></b></div><?php endif; ?>
                    </div>
                </div>

                <div class="greet">
                    <p class="dear">Dear <?= h($clientName) ?>,</p>
                    <p><?= h($in('intro') ?: 'Details of your Umrah package are mentioned below.') ?></p>
                </div>

                <div class="grid-2">
                    <div>
                        <h2 class="section-h">Package Includes</h2>
                        <ul class="inc">
                            <?php foreach ($includes as [$label, $icon]): ?>
                                <li><i class="fa-solid <?= h($icon) ?>"></i> <?= h($label) ?></li>
                            <?php endforeach; ?>
                            <?php if (!$includes): ?><li style="color:var(--muted)">—</li><?php endif; ?>
                        </ul>
                    </div>
                    <div>
                        <h2 class="section-h">Details of Mutamers</h2>
                        <table class="tbl">
                            <thead><tr><th>Category</th><th>Number</th></tr></thead>
                            <tbody>
                                <?php foreach ($pax as $cat => $count): ?>
                                    <tr><td><?= h($cat) ?></td><td class="num"><?= $count ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($hotels): ?>
                    <div class="summary">
                        <h2 class="section-h full">Package Summary<?= $totalDays !== '' ? ' (' . (int)$totalDays . ' Days)' : '' ?></h2>
                        <table class="tbl">
                            <thead>
                                <tr><th>City</th><th>Hotel</th><th>Distance</th><th>Room</th><?php if ($showNights): ?><th>Nights</th><?php endif; ?></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hotels as $ht): ?>
                                    <tr>
                                        <td><?= h($ht['city']) ?></td>
                                        <td class="strong"><?= h($ht['name']) ?></td>
                                        <td><?= h($ht['distance']) ?></td>
                                        <td class="strong"><?= h($ht['room']) ?></td>
                                        <?php if ($showNights): ?><td><?= h($ht['nights']) ?></td><?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($hasFlights): ?>
                    <div class="flights">
                        <?php foreach ($flights as $i => [$label, $sector, $date, $flightNo]): ?>
                            <div class="flight">
                                <div class="fh"><i class="fa-solid <?= $i === 0 ? 'fa-plane-departure' : 'fa-plane-arrival' ?>"></i><?= h($label) ?></div>
                                <div class="fb">
                                    <div><b><?= h($sector ?: '—') ?></b><?php if ($flightNo): ?><small><?= h($flightNo) ?></small><?php endif; ?></div>
                                    <div><?= h($date ?: '—') ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <hr class="divider">

                <div class="bottom">
                    <div>
                        <?php if ($noteParts || $note): ?>
                            <div class="note">
                                <b>Note:</b> <?= h(implode(' — ', array_slice($noteParts, 0, 1))) ?>
                                <?php if (count($noteParts) > 1): ?><br><?= h($noteParts[1]) ?><?php endif; ?>
                                <?php if ($note): ?><br><?= nl2br(h($note)) ?><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($signName || $in('sign_title')): ?>
                            <div class="sign">
                                <div class="line"></div>
                                <?php if ($signName): ?><div class="name"><?= h($signName) ?></div><?php endif; ?>
                                <div class="role"><?= h($signTitle) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="pricing">
                        <div class="ph">Pricing Details</div>
                        <?php foreach ($prices as $label => $value): ?>
                            <div class="pr"><span><?= h($label) ?></span><span><?= h(fmtMoney($value)) ?></span></div>
                        <?php endforeach; ?>
                        <div class="total"><span>Total Price</span><span><?= h(fmtMoney($totalPrice)) ?></span></div>
                    </div>
                </div>

                <div class="footer">
                    <span><i class="fa-solid fa-circle-info"></i>Prices are subject to availability at the time of booking.</span>
                    <span><i class="fa-solid fa-heart"></i>Thank you for choosing <?= h($agencyName) ?></span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
