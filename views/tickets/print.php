<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/AdminController.php';
require_once __DIR__ . '/../../controllers/AirTicketController.php';

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$ticketId = (int)($_GET['id'] ?? 0);
$record = $ticketId > 0 ? AirTicketController::getById($ticketId) : null;
if (!$record) {
    die('<div style="font-family:Arial;padding:30px;text-align:center;color:#b91c1c;">Ticket not found.</div>');
}
$t = $record['data'];

$settings    = AdminController::getSettings();
$agencyName  = $settings['agency_name'] ?? 'Aeroheights Travels & Tours';
$agencyLogo  = $settings['agency_logo'] ?? 'assets/img/logo.png';
$agencyEmail = $settings['agency_email'] ?? '';
$agencyAddr  = $settings['agency_address'] ?? '';
// Pakistan helpline contacts printed on every customized ticket.
$helplines = [
    ['Office No.', '+92 303 5137777'],
];

$airlineCode = $t['airline_code'];
$airlineName = $t['airline_name'] ?: (AirTicketController::AIRLINES[$airlineCode] ?? 'Airline');
$airlineLogo = AirTicketController::logoFor($airlineCode);

$fmtDay  = static fn(string $d): string => $d ? date('D, d M Y', strtotime($d)) : '';
$fmtShort = static fn(string $d): string => $d ? date('d M Y', strtotime($d)) : '';
$flightLabel = static fn(string $f): string => preg_match('/^([A-Z0-9]{2})(\d+)$/', $f, $m) ? $m[1] . ' ' . $m[2] : $f;

$segments = $t['segments'];
$journeys = AirTicketController::journeys($segments);
$origin = $segments[0]['from'] ?? '';
$journeyLabel = static function (int $i, int $count, array $group) use ($origin): array {
    $lastTo = end($group)['to'] ?? '';
    if ($i === 0) return ['Departure', 'fa-plane-departure'];
    if ($i === $count - 1 && $lastTo === $origin) return ['Return', 'fa-plane-arrival'];
    return ['Onward Journey', 'fa-route'];
};

$layover = static function (array $prev, array $next): string {
    if (!$prev['arr_date'] || !$next['dep_date'] || !$prev['arr_time'] || !$next['dep_time']) return '';
    $mins = (int)round((strtotime($next['dep_date'] . ' ' . $next['dep_time']) - strtotime($prev['arr_date'] . ' ' . $prev['arr_time'])) / 60);
    if ($mins <= 0) return '';
    return intdiv($mins, 60) . 'h ' . str_pad((string)($mins % 60), 2, '0', STR_PAD_LEFT) . 'm';
};

$statusTone = [
    'Confirmed' => ['#ecfdf5', '#047857', 'fa-circle-check'],
    'Partially Confirmed' => ['#fffbeb', '#b45309', 'fa-circle-half-stroke'],
    'On Request' => ['#eff6ff', '#1d4ed8', 'fa-hourglass-half'],
    'On Hold' => ['#eef6fd', '#1a6bb8', 'fa-circle-pause'],
    'Cancelled' => ['#fef2f2', '#b91c1c', 'fa-circle-xmark'],
][$t['status']] ?? ['#ecfdf5', '#047857', 'fa-circle-check'];

$paxCounts = array_count_values(array_column($t['passengers'], 'type'));
$paxSummary = implode(' · ', array_map(static fn($type, $n) => $n . ' ' . $type . ($n > 1 ? 's' : ''), array_keys($paxCounts), $paxCounts));
$route = AirTicketController::routeOf($segments);
$fileName = trim(($t['pnr'] ? $t['pnr'] . ' - ' : '') . ucwords(strtolower($t['family_head'])) . ' - ' . $route . ' Ticket');
$autoDownload = !empty($_GET['download']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($fileName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ink: #161a35;
            --ink-2: #26206f;
            --ink-soft: #3b4559;
            --accent: #2183DF;
            --accent-2: #FEC624;
            --muted: #7a8394;
            --hair: #e7e9ee;
            --paper: #ffffff;
            --wash: #f6f8fc;
        }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px 12px; background: #dde2ea; color: var(--ink); font: 500 11px/1.45 'Jost', Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }
        .cap { font-size: 8px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: var(--muted); }

        /* Toolbar (screen only) */
        .toolbar { max-width: 794px; margin: 0 auto 14px; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; }
        .toolbar .grp { display: flex; flex-wrap: wrap; gap: 8px; }
        .toolbar a, .toolbar button { display: inline-flex; align-items: center; gap: 7px; border: 0; border-radius: 9px; padding: 8px 14px; font: 700 11.5px 'Jost', sans-serif; cursor: pointer; text-decoration: none; }
        .btn-light { background: #fff; color: #334155; box-shadow: inset 0 0 0 1px #cbd5e1; }
        .btn-dark { background: var(--ink); color: #fff; }
        .btn-accent { background: var(--accent); color: #fff; box-shadow: 0 6px 16px rgba(33, 131, 223,.28); }
        .btn-accent[disabled] { opacity: .6; cursor: wait; }

        /* Sheet */
        .sheet { position: relative; width: 794px; max-width: 100%; margin: 0 auto; background: var(--paper); border-radius: 12px; overflow: hidden; box-shadow: 0 18px 40px rgba(14,20,34,.16); }
        .edge { height: 4px; background: linear-gradient(90deg, var(--ink) 0 62%, var(--accent) 62% 88%, var(--accent-2) 88%); }
        .inner { padding: 22px 34px 18px; }

        /* Header */
        .head { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .head .agency img { height: 52px; width: auto; display: block; }
        .doc { text-align: center; }
        .doc .t { font-size: 15px; font-weight: 800; letter-spacing: .32em; color: var(--ink); }
        .doc .s { margin-top: 2px; font-size: 8.5px; letter-spacing: .24em; color: var(--muted); font-weight: 600; text-transform: uppercase; }
        .carrier { display: flex; align-items: center; justify-content: flex-end; min-width: 150px; height: 46px; }
        .carrier img { max-height: 34px; max-width: 150px; width: auto; display: block; }
        .carrier .word { display: inline-flex; align-items: center; gap: 7px; font-weight: 800; font-size: 15px; color: var(--ink); letter-spacing: .01em; }
        .carrier .word i { color: var(--accent); font-size: 12px; transform: rotate(-35deg); }
        .rule { margin: 14px 0 0; height: 1px; background: linear-gradient(90deg, transparent, var(--hair) 8%, var(--hair) 92%, transparent); }

        /* Summary band */
        .band { position: relative; margin-top: 14px; display: flex; align-items: stretch; border-radius: 12px; overflow: hidden; color: #fff;
                background: radial-gradient(120% 140% at 100% 0%, rgba(33, 131, 223,.22) 0%, rgba(33, 131, 223,0) 46%), linear-gradient(135deg, var(--ink) 0%, var(--ink-2) 100%); }
        .band::before { content: ""; position: absolute; inset: 0; background: repeating-linear-gradient(135deg, rgba(255,255,255,.025) 0 1px, transparent 1px 7px); pointer-events: none; }
        .band > div { position: relative; padding: 11px 16px; border-left: 1px solid rgba(255,255,255,.08); display: flex; flex-direction: column; justify-content: center; }
        .band > div:first-child { border-left: 0; }
        .band .cap { color: rgba(255,255,255,.55); }
        .band .v { margin-top: 3px; font-size: 12px; font-weight: 700; color: #fff; white-space: nowrap; }
        .band .pnr .v { font-size: 15px; letter-spacing: .2em; color: var(--accent-2); font-weight: 700; }
        .band .lead { flex: 1; min-width: 0; }
        .band .lead .v { font-size: 12.5px; letter-spacing: .03em; overflow: hidden; text-overflow: ellipsis; }
        .band .lead small { display: block; margin-top: 1px; font-size: 9px; color: rgba(255,255,255,.55); font-weight: 600; }
        .pill { display: inline-flex; align-items: center; gap: 5px; margin-top: 4px; padding: 2px 8px 2px 7px; border-radius: 999px; font-size: 9.5px; font-weight: 700; background: rgba(255,255,255,.1); color: #fff; width: max-content; }
        .pill .dot { width: 6px; height: 6px; border-radius: 50%; }

        /* Section title */
        .sec { display: flex; align-items: center; gap: 10px; margin: 18px 0 8px; }
        .sec .ttl { font-size: 9px; font-weight: 800; letter-spacing: .22em; text-transform: uppercase; color: var(--ink); }
        .sec .meta { font-size: 9.5px; color: var(--muted); font-weight: 600; }
        .sec::after { content: ""; flex: 1; height: 1px; background: var(--hair); }

        /* Journey + flight rows */
        .journey + .journey { margin-top: 10px; }
        .jhead { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
        .tag { display: inline-flex; align-items: center; gap: 5px; padding: 2px 8px; border-radius: 5px; background: var(--ink); color: #fff; font-size: 8px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; }
        .tag i { color: var(--accent-2); font-size: 8.5px; }
        .jhead .meta { font-size: 9.5px; color: var(--ink-soft); font-weight: 600; }
        .jhead .meta b { letter-spacing: .06em; }

        .flight { display: flex; align-items: stretch; border: 1px solid var(--hair); border-radius: 11px; background: #fff; page-break-inside: avoid; break-inside: avoid; position: relative; }
        .flight + .flight, .conn + .flight { margin-top: 6px; }
        .when { width: 74px; flex-shrink: 0; text-align: center; padding: 10px 6px; border-right: 1px solid var(--hair); background: var(--wash); border-radius: 11px 0 0 11px; display: flex; flex-direction: column; justify-content: center; }
        .when .dw { font-size: 8px; font-weight: 800; letter-spacing: .2em; color: var(--accent); text-transform: uppercase; }
        .when .dd { font-size: 22px; font-weight: 800; line-height: 1.05; letter-spacing: -.01em; }
        .when .my { font-size: 8.5px; font-weight: 700; color: var(--muted); letter-spacing: .08em; text-transform: uppercase; }
        .route { flex: 1; display: flex; align-items: center; gap: 10px; padding: 10px 16px; min-width: 0; }
        .pt { width: 132px; }
        .pt.to { text-align: right; }
        .pt .tm { font-size: 17px; font-weight: 800; letter-spacing: -.01em; line-height: 1.1; }
        .pt .ap { margin-top: 1px; font-size: 10.5px; font-weight: 700; color: var(--ink); }
        .pt .ap b { letter-spacing: .08em; }
        .pt .ap span { color: var(--muted); font-weight: 600; }
        .pt .sub { font-size: 8.5px; color: var(--muted); font-weight: 600; }
        .path { flex: 1; text-align: center; min-width: 90px; }
        .path .fl { font-size: 9px; font-weight: 700; color: var(--ink-soft); letter-spacing: .06em; }
        .path .ln { position: relative; height: 14px; margin: 3px 0; }
        .path .ln::before { content: ""; position: absolute; left: 0; right: 0; top: 50%; border-top: 1px dashed #c9ced8; }
        .path .ln::after { content: ""; position: absolute; right: 0; top: calc(50% - 3px); width: 6px; height: 6px; border-radius: 50%; background: var(--accent); }
        .path .ln i { position: relative; z-index: 1; background: #fff; padding: 0 6px; color: var(--accent); font-size: 11px; line-height: 14px; }
        .path .ln span.o { position: absolute; left: 0; top: calc(50% - 3px); width: 6px; height: 6px; border-radius: 50%; border: 1.5px solid var(--accent); background: #fff; }
        .path .du { font-size: 8.5px; color: var(--muted); font-weight: 600; }
        .stub { width: 148px; flex-shrink: 0; position: relative; padding: 9px 14px; border-left: 1.5px dashed #d8dce4; display: flex; flex-direction: column; justify-content: center; gap: 3px; }
        .stub::before, .stub::after { content: ""; position: absolute; left: -7.5px; width: 13px; height: 13px; border-radius: 50%; background: #fff; border: 1px solid var(--hair); }
        .stub::before { border-top-color: #fff; }
        .stub::after { border-bottom-color: #fff; }
        .stub::before { top: -7px; }
        .stub::after { bottom: -7px; }
        .stub .r { display: flex; justify-content: space-between; gap: 6px; font-size: 8.5px; color: var(--muted); font-weight: 600; }
        .stub .r b { color: var(--ink); font-size: 9.5px; font-weight: 700; white-space: nowrap; }
        .conn { margin: 5px 0 0 74px; padding-left: 16px; font-size: 9px; font-weight: 700; color: #a2560d; }
        .conn i { margin-right: 4px; }

        /* Passengers */
        table.pax { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        table.pax th { text-align: left; padding: 6px 10px; font-size: 8px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: var(--muted); background: var(--wash); border-top: 1px solid var(--hair); border-bottom: 1px solid var(--hair); }
        table.pax td { padding: 6px 10px; border-bottom: 1px solid var(--hair); }
        table.pax tr { page-break-inside: avoid; break-inside: avoid; }
        table.pax .n { color: var(--muted); font-weight: 700; width: 26px; }
        table.pax .nm { font-weight: 800; letter-spacing: .02em; }
        table.pax .tt { color: var(--muted); font-weight: 700; margin-right: 3px; font-size: 9.5px; }
        table.pax tr.lead td { background: #fff7ef; }
        table.pax tr.lead td.n { box-shadow: inset 2px 0 0 var(--accent); }
        .lead-tag { display: inline-block; margin-left: 6px; padding: 1px 6px; border-radius: 4px; border: 1px solid rgba(33, 131, 223,.45); color: #b4570c; font-size: 7.5px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; vertical-align: 1px; }
        .ty { font-size: 9px; font-weight: 700; color: var(--ink-soft); }

        /* Notes + helpline */
        .foot-grid { display: grid; grid-template-columns: 1.45fr 1fr; gap: 12px; margin-top: 16px; page-break-inside: avoid; break-inside: avoid; }
        .notes { border: 1px solid var(--hair); border-radius: 11px; padding: 11px 14px; }
        .notes .cap, .help .cap { display: block; margin-bottom: 6px; }
        .notes ul { margin: 0; padding: 0; list-style: none; columns: 1; }
        .notes li { position: relative; padding-left: 12px; font-size: 9.5px; color: var(--ink-soft); margin-bottom: 2px; line-height: 1.5; }
        .notes li::before { content: ""; position: absolute; left: 0; top: 6px; width: 4px; height: 4px; border-radius: 1px; background: var(--accent); transform: rotate(45deg); }
        .notes .extra { margin-top: 6px; padding-top: 6px; border-top: 1px dashed var(--hair); font-size: 9.5px; color: var(--ink-soft); white-space: pre-line; }
        .help { border-radius: 11px; padding: 11px 14px; color: #fff; background: linear-gradient(150deg, var(--ink), var(--ink-2)); }
        .help .cap { color: var(--accent-2); }
        .help .ln { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 5px 0; border-top: 1px solid rgba(255,255,255,.08); }
        .help .ln:first-of-type { border-top: 0; }
        .help .ln span { font-size: 9px; color: rgba(255,255,255,.6); font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .help .ln span i { color: var(--accent); width: 11px; text-align: center; }
        .help .ln b { font-size: 11px; font-weight: 700; letter-spacing: .03em; }

        .foot { margin-top: 14px; padding-top: 10px; border-top: 1px solid var(--hair); display: flex; justify-content: space-between; align-items: center; gap: 12px; font-size: 8.5px; color: var(--muted); font-weight: 600; }
        .foot .c { display: flex; flex-wrap: wrap; gap: 3px 12px; }
        .foot i { color: var(--accent); margin-right: 3px; }
        .foot .w { color: var(--ink); font-weight: 700; white-space: nowrap; letter-spacing: .02em; }

        @media (max-width: 640px) {
            .inner { padding: 16px; }
            .head { flex-wrap: wrap; }
            .doc { order: 3; width: 100%; text-align: left; }
            .band { flex-wrap: wrap; }
            .band > div { flex: 1 1 40%; border-left: 0; border-top: 1px solid rgba(255,255,255,.08); }
            .flight { flex-wrap: wrap; }
            .stub { width: 100%; border-left: 0; border-top: 1.5px dashed #d8dce4; flex-direction: row; flex-wrap: wrap; }
            .stub::before, .stub::after { display: none; }
            .pt { width: 96px; }
            .foot-grid { grid-template-columns: 1fr; }
            .conn { margin-left: 0; }
        }
        @page { size: A4; margin: 0; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none !important; }
            .sheet { box-shadow: none; border-radius: 0; width: 100%; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <div class="grp">
        <a href="index.php?page=ticket_bookings" class="btn-light"><i class="fa-solid fa-arrow-left"></i> Ticket Booking</a>
        <?php if (!empty($record['original_path'])): ?>
            <a href="index.php?api=air_ticket_original&id=<?= (int)$record['id'] ?>" target="_blank" class="btn-light"><i class="fa-regular fa-file-lines"></i> Original Ticket</a>
        <?php endif; ?>
    </div>
    <div class="grp">
        <button type="button" class="btn-dark" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
        <button type="button" class="btn-accent" id="btnPdf" onclick="downloadPdf()"><i class="fa-solid fa-file-arrow-down"></i> Download PDF</button>
    </div>
</div>

<div class="sheet" id="ticketSheet">
    <div class="edge"></div>
    <div class="inner">

        <div class="head">
            <div class="agency"><img src="<?= h($agencyLogo) ?>" alt="<?= h($agencyName) ?>"></div>
            <div class="doc">
                <div class="t">E-TICKET</div>
                <div class="s">Itinerary &amp; Receipt &middot; <?= h($record['ticket_no']) ?></div>
            </div>
            <div class="carrier">
                <?php if ($airlineLogo): ?>
                    <img src="<?= h($airlineLogo) ?>" alt="<?= h($airlineName) ?>">
                <?php else: ?>
                    <span class="word"><i class="fa-solid fa-plane"></i><?= h($airlineName) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="rule"></div>

        <div class="band">
            <div class="pnr"><span class="cap">Booking Ref (PNR)</span><span class="v mono"><?= h($t['pnr'] ?: '—') ?></span></div>
            <div class="lead"><span class="cap">Lead Passenger</span><span class="v"><?= h($t['family_head']) ?></span><small><?= count($t['passengers']) ?> traveller<?= count($t['passengers']) > 1 ? 's' : '' ?> &middot; <?= h($paxSummary) ?></small></div>
            <div><span class="cap">Issued</span><span class="v"><?= h($fmtShort($t['issue_date'])) ?></span></div>
            <div><span class="cap">Class</span><span class="v"><?= h($t['cabin']) ?></span></div>
            <div><span class="cap">Status</span><span class="pill"><span class="dot" style="background:<?= $statusTone[1] === '#047857' ? '#34d399' : $statusTone[1] ?>"></span><?= h($t['status']) ?></span></div>
        </div>

        <div class="sec"><span class="ttl">Flight Itinerary</span><span class="meta"><?= h(str_replace('-', ' → ', $route)) ?></span></div>

        <?php foreach ($journeys as $ji => $group):
            [$label, $icon] = $journeyLabel($ji, count($journeys), $group);
            $first = $group[0]; $last = end($group); ?>
            <div class="journey">
                <div class="jhead">
                    <span class="tag"><i class="fa-solid <?= $icon ?>"></i> <?= h($label) ?></span>
                    <span class="meta"><b><?= h($first['from']) ?> → <?= h($last['to']) ?></b> &nbsp;·&nbsp; <?= count($group) > 1 ? (count($group) - 1) . ' stop' . (count($group) > 2 ? 's' : '') : 'Non-stop' ?></span>
                </div>
                <?php foreach ($group as $si => $s):
                    if ($si > 0 && ($lay = $layover($group[$si - 1], $s)) !== ''): ?>
                        <div class="conn"><i class="fa-regular fa-clock"></i> Connection in <?= h($s['from_city'] ?: $s['from']) ?> &middot; <?= h($lay) ?> layover</div>
                    <?php endif;
                    $dts = $s['dep_date'] ? strtotime($s['dep_date']) : 0; ?>
                    <div class="flight">
                        <div class="when">
                            <div class="dw"><?= $dts ? date('D', $dts) : '' ?></div>
                            <div class="dd"><?= $dts ? date('d', $dts) : '--' ?></div>
                            <div class="my"><?= $dts ? date('M Y', $dts) : '' ?></div>
                        </div>
                        <div class="route">
                            <div class="pt">
                                <div class="tm"><?= h($s['dep_time'] ?: '--:--') ?></div>
                                <div class="ap"><b><?= h($s['from'] ?: '—') ?></b> <span><?= h($s['from_city']) ?></span></div>
                                <div class="sub"><?= $s['from_terminal'] ? 'Terminal ' . h($s['from_terminal']) : '&nbsp;' ?></div>
                            </div>
                            <div class="path">
                                <div class="fl"><?= h($flightLabel($s['flight'])) ?></div>
                                <div class="ln"><span class="o"></span><i class="fa-solid fa-plane"></i></div>
                                <div class="du"><?= h($s['duration'] ?: 'Non-stop') ?></div>
                            </div>
                            <div class="pt to">
                                <div class="tm"><?= h($s['arr_time'] ?: '--:--') ?></div>
                                <div class="ap"><span><?= h($s['to_city']) ?></span> <b><?= h($s['to'] ?: '—') ?></b></div>
                                <div class="sub"><?= $s['arr_date'] && $s['arr_date'] !== $s['dep_date'] ? '<b style="color:#b4570c">+1</b> ' . h($fmtShort($s['arr_date'])) : '' ?><?= $s['to_terminal'] ? ($s['arr_date'] !== $s['dep_date'] ? ' · ' : '') . 'Terminal ' . h($s['to_terminal']) : '' ?>&nbsp;</div>
                            </div>
                        </div>
                        <div class="stub">
                            <div class="r">Airline <b><?= h(AirTicketController::AIRLINES[substr($s['flight'], 0, 2)] ?? $airlineName) ?></b></div>
                            <div class="r">Flight <b class="mono"><?= h($flightLabel($s['flight'])) ?></b></div>
                            <div class="r">Baggage <b><?= h($s['baggage'] ?: 'As per airline') ?></b></div>
                            <div class="r">Hand carry <b><?= h($t['cabin_baggage'] ?: '7 KG') ?></b></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <div class="sec"><span class="ttl">Passengers</span><span class="meta"><?= h($paxSummary) ?></span></div>
        <table class="pax">
            <thead><tr><th class="n">#</th><th>Passenger Name</th><th>Type</th><th>Passport</th><th>E-Ticket Number</th></tr></thead>
            <tbody>
            <?php foreach ($t['passengers'] as $i => $p): $isHead = $p['name'] === $t['family_head']; ?>
                <tr class="<?= $isHead ? 'lead' : '' ?>">
                    <td class="n mono"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></td>
                    <td><span class="tt"><?= h($p['title']) ?></span><span class="nm"><?= h($p['name']) ?></span><?php if ($isHead): ?><span class="lead-tag">Family Head</span><?php endif; ?></td>
                    <td class="ty"><?= h($p['type']) ?></td>
                    <td class="mono"><?= h($p['passport'] ?: '—') ?></td>
                    <td class="mono"><?= h($p['ticket_no'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="foot-grid">
            <div class="notes">
                <span class="cap">Important Information</span>
                <ul>
                    <li>Report at the airline check-in counter at least <b>4 hours</b> before departure.</li>
                    <li>Reconfirm flight timings 48 hours before departure.</li>
                    <li>Carry your original passport, visa and this e-ticket for check-in.</li>
                    <li>Baggage as per airline policy; excess baggage is chargeable.</li>
                    <li>Visas and travel documents are the traveller's own responsibility.</li>
                </ul>
                <?php if ($t['notes'] !== ''): ?><div class="extra"><?= h($t['notes']) ?></div><?php endif; ?>
            </div>
            <div class="help">
                <span class="cap">24/7 Helpline &middot; Pakistan</span>
                <?php foreach ($helplines as [$name, $phone]): ?>
                    <div class="ln"><span><i class="fa-solid fa-phone"></i><?= h($name) ?></span><b class="mono"><?= h($phone) ?></b></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="foot">
            <div class="c">
                <span class="w"><?= h($agencyName) ?></span>
                <?php if ($agencyAddr): ?><span><i class="fa-solid fa-location-dot"></i><?= h($agencyAddr) ?></span><?php endif; ?>
                <?php if ($agencyEmail): ?><span><i class="fa-regular fa-envelope"></i><?= h($agencyEmail) ?></span><?php endif; ?>
            </div>
            <div class="w">Wishing you a safe &amp; blessed journey</div>
        </div>
    </div>
    <div class="edge"></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
const PDF_NAME = <?= json_encode($fileName . '.pdf') ?>;
async function downloadPdf() {
    const btn = document.getElementById('btnPdf');
    const sheet = document.getElementById('ticketSheet');
    btn.disabled = true;
    const label = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Preparing PDF…';
    // Render at true A4 width even on phones, without rounded corners / shadow.
    const prev = sheet.getAttribute('style') || '';
    sheet.setAttribute('style', 'width:794px;max-width:none;border-radius:0;box-shadow:none;');
    try {
        await document.fonts.ready;
        const A4W = 794, A4H = 1123;
        const canvasOpts = { scale: 2, useCORS: true, backgroundColor: '#ffffff', windowWidth: A4W };
        if (sheet.offsetHeight <= A4H * 1.4) {
            // Typical ticket: shrink slightly so the whole ticket sits on one A4 page.
            const canvas = await html2pdf().set({ html2canvas: canvasOpts }).from(sheet).toCanvas().get('canvas');
            const pdf = new window.jspdf.jsPDF({ unit: 'px', format: [A4W, A4H], orientation: 'portrait', hotfixes: ['px_scaling'] });
            const k = Math.min(A4W / canvas.width, A4H / canvas.height);
            const w = canvas.width * k, h = canvas.height * k;
            pdf.addImage(canvas.toDataURL('image/jpeg', 0.95), 'JPEG', (A4W - w) / 2, 0, w, h);
            pdf.save(PDF_NAME);
        } else {
            // Long itinerary: full size across several A4 pages, never cutting through a flight card.
            await html2pdf().set({
                margin: 0,
                filename: PDF_NAME,
                image: { type: 'jpeg', quality: 0.95 },
                html2canvas: canvasOpts,
                jsPDF: { unit: 'px', format: [A4W, A4H], orientation: 'portrait', hotfixes: ['px_scaling'] },
                pagebreak: { mode: ['css', 'legacy'], avoid: ['.seg', '.layover', 'table.pax tr', '.bottom', '.lead', '.sec-title'] }
            }).from(sheet).save();
        }
    } finally {
        sheet.setAttribute('style', prev);
        btn.disabled = false;
        btn.innerHTML = label;
    }
}
<?php if ($autoDownload): ?>
window.addEventListener('load', () => setTimeout(downloadPdf, 400));
<?php endif; ?>
</script>
</body>
</html>
