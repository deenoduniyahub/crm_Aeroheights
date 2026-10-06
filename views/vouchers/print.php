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

$defaultAgency = Database::fetchValue("SELECT setting_value FROM system_settings WHERE setting_key = 'agency_name'") ?: 'Aeroheights Travels & Tours';
$agencyPhone  = Database::fetchValue("SELECT setting_value FROM system_settings WHERE setting_key = 'agency_phone'") ?: '';
$makkahHelp   = Database::fetchValue("SELECT setting_value FROM system_settings WHERE setting_key = 'makkah_helpline'") ?: '+92 303 5137777';
$madinahHelp  = Database::fetchValue("SELECT setting_value FROM system_settings WHERE setting_key = 'madinah_helpline'") ?: '+92 303 4512512';

$companyName = trim((string)($v['company_name'] ?? '')) ?: $defaultAgency;
$showLogo    = ($companyName === 'Aeroheights Travels & Tours');

// Header QR -> signed read-only copy of this voucher on the main website (see VoucherShare).
$isPublicView = defined('AST_PUBLIC_VOUCHER');
$qrUrl = VoucherShare::url('v', $voucherId);
// Header band is navy, so it carries the white-text logo; other agencies' vouchers show our logo as a small partner badge.
$bandLogo = 'assets/img/logo-dark.png';
$brandLogo = 'assets/img/logo.png';
if ($isPublicView) {
    // Embed the logos so the public page never references the software's paths.
    $embedLogo = static function (string $path): string {
        $logoFile = __DIR__ . '/../../' . ltrim($path, '/');
        $logoMime = ['png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml'][strtolower(pathinfo($logoFile, PATHINFO_EXTENSION))] ?? 'image/jpeg';
        return is_file($logoFile) ? 'data:' . $logoMime . ';base64,' . base64_encode((string)file_get_contents($logoFile)) : '';
    };
    $bandLogo = $embedLogo($bandLogo);
    $brandLogo = $embedLogo($brandLogo);
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
$paxBreakdown = implode(' · ', array_filter([
    $paxAdult ? $paxAdult . ' adult' . ($paxAdult === 1 ? '' : 's') : '',
    $paxChild ? $paxChild . ' child' . ($paxChild === 1 ? '' : 'ren') : '',
    $paxInfant ? $paxInfant . ' infant' . ($paxInfant === 1 ? '' : 's') : '',
])) ?: '—';
$totalBeds = (int)($v['total_beds'] ?? 0);

// One boarding-pass card per direction.
$flightCard = static function (string $p) use ($v): array {
    $when = static fn($d, $t) => $d ? date('D, d M', strtotime((string)$d)) . ($t ? ' · ' . substr((string)$t, 0, 5) : '') : '—';
    return [
        'no'   => trim((string)($v["flight_{$p}_no"] ?? '')),
        'from' => strtoupper(trim((string)($v["flight_{$p}_from"] ?? ''))),
        'to'   => strtoupper(trim((string)($v["flight_{$p}_to"] ?? ''))),
        'dep'  => $when($v["flight_{$p}_dep_date"] ?? null, $v["flight_{$p}_dep_time"] ?? null),
        'arr'  => $when($v["flight_{$p}_arr_date"] ?? null, $v["flight_{$p}_arr_time"] ?? null),
    ];
};
$flights = [['Pakistan → Saudi Arabia', $flightCard('out')], ['Saudi Arabia → Pakistan', $flightCard('ret')]];

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

    $firstDate = fmtDate($transportLegs[0]['service_date'], 'd M');
    $lastDate = fmtDate($transportLegs[count($transportLegs) - 1]['service_date'], 'd M');
    $transportDateRange = $firstDate === $lastDate ? $firstDate : "{$firstDate} – {$lastDate}";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= h($companyName) ?> — Hotel Voucher (<?= h($v['voucher_no']) ?>)</title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800&family=Noto+Nastaliq+Urdu:wght@400;700&display=swap" rel="stylesheet">
    <?php require __DIR__ . '/_voucher_theme.php'; ?>
</head>
<body>
<?php if ($isPublicView): ?>
    <div class="public-bar no-print">
        <a href="https://aeroheightstravels.com/">&larr; aeroheightstravels.com</a>
        <span class="verified"><b>&#10004;</b> Verified Voucher</span>
    </div>
<?php endif; ?>

<div class="doc">
    <!-- Header band -->
    <header class="band">
        <div class="band-row">
            <div class="band-brand">
                <?php if ($showLogo): ?>
                    <img src="<?= h($bandLogo) ?>" alt="<?= h($companyName) ?>">
                <?php else: ?>
                    <div class="agency"><?= h($companyName) ?></div>
                    <div class="tagline">Umrah &amp; Travel Services</div>
                <?php endif; ?>
            </div>
            <div class="band-side">
                <?php if (!$showLogo): ?><div class="partner"><img src="<?= h($brandLogo) ?>" alt="Partner" onerror="this.parentNode.style.display='none'"></div><?php endif; ?>
                <?php if ($qrUrl): ?><div class="qr-tile"><div class="qr" data-qr="<?= h($qrUrl) ?>"></div><span>Scan to verify</span></div><?php endif; ?>
            </div>
        </div>
        <div class="band-title">
            <div>
                <div class="eyebrow">Accommodation Confirmation</div>
                <h1>Hotel Voucher</h1>
            </div>
            <div class="chips">
                <span class="chip"><small>Voucher</small><b class="num"><?= h($v['voucher_no']) ?></b></span>
                <span class="chip"><small>Issued</small><b class="num"><?= h(fmtDate($v['voucher_date'], 'd M Y')) ?></b></span>
            </div>
        </div>
    </header>

    <div class="content">
        <!-- Summary tiles -->
        <div class="tiles">
            <div class="tile lead">
                <div class="k">Family Head</div>
                <div class="v"><?= h($v['family_head'] ?: '—') ?></div>
                <div class="s"><?= h($v['package_name'] ?: 'Hotel accommodation') ?></div>
            </div>
            <div class="tile">
                <div class="k">Travellers</div>
                <div class="v num"><?= $paxTotal ?> PAX</div>
                <div class="s"><?= h($paxBreakdown) ?></div>
            </div>
            <div class="tile">
                <div class="k">Beds</div>
                <div class="v num"><?= $totalBeds ?: '—' ?></div>
                <div class="s">assigned</div>
            </div>
            <div class="tile">
                <div class="k">Stay</div>
                <div class="v num"><?= (int)$v['total_nights'] ?> Nights</div>
                <div class="s"><?= count($v['stays']) ?> hotel<?= count($v['stays']) === 1 ? '' : 's' ?></div>
            </div>
        </div>

        <!-- Accommodation -->
        <div class="sec"><span class="dot">1</span><h2>Accommodation</h2><span class="line"></span></div>
        <?php foreach ($v['stays'] as $s): $city = strtolower(trim((string)$s['city'])); ?>
            <div class="stay <?= $city === 'makkah' ? 'makkah' : ($city === 'madinah' ? 'madinah' : '') ?>">
                <div class="bar"></div>
                <div class="stay-body">
                    <div>
                        <span class="city-tag"><?= h($s['city'] ?: 'Hotel') ?></span>
                        <div class="hotel"><?= h($s['hotel_name']) ?></div>
                        <div class="facts">
                            <?php if ($s['room_type']): ?><span class="fact">Room <b><?= h($s['room_type']) ?></b></span><?php endif; ?>
                            <?php if ($s['meal_plan']): ?><span class="fact">Meal <b><?= h($s['meal_plan']) ?></b></span><?php endif; ?>
                            <span class="fact">Conf. <b class="num"><?= h($s['confirmation_number'] ?: 'Awaited') ?></b></span>
                        </div>
                    </div>
                    <div class="timeline">
                        <div class="tl-end"><div class="k">Check-in</div><div class="d num"><?= h(fmtDate($s['checkin_date'], 'd M Y')) ?></div><div class="w"><?= h(fmtDate($s['checkin_date'], 'l')) ?></div></div>
                        <div class="tl-mid"><span class="nights num"><?= (int)$s['nights'] ?> Night<?= (int)$s['nights'] === 1 ? '' : 's' ?></span></div>
                        <div class="tl-end out"><div class="k">Check-out</div><div class="d num"><?= h(fmtDate($s['checkout_date'], 'd M Y')) ?></div><div class="w"><?= h(fmtDate($s['checkout_date'], 'l')) ?></div></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($v['stays'])): ?>
            <div class="stay"><div class="bar"></div><div class="stay-body"><div><span class="city-tag">Accommodation</span><div class="hotel">Self arranged</div></div></div></div>
        <?php endif; ?>
        <div class="total-row"><span>Total stay: <b class="num"><?= (int)$v['total_nights'] ?> nights</b></span></div>

        <!-- Travellers -->
        <div class="sec"><span class="dot">2</span><h2>Travellers</h2><span class="line"></span><span class="aside"><?= $paxTotal ?> on this voucher</span></div>
        <table class="list">
            <thead><tr><th>#</th><th>Name</th><th>Passport</th><th class="c">Gender</th><th class="c">Type</th><th class="c">Bed</th></tr></thead>
            <tbody>
                <?php foreach ($mutamers as $idx => $m): ?>
                    <tr>
                        <td class="idx num"><?= str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT) ?></td>
                        <td class="name"><?= h($m['mutamer_name']) ?></td>
                        <td class="num"><?= h($isPublicView ? VoucherShare::maskPassport($m['passport_number']) : $m['passport_number']) ?></td>
                        <td class="c"><?= h(['M' => 'Male', 'F' => 'Female'][$m['gender']] ?? $m['gender']) ?></td>
                        <td class="c"><span class="pill"><?= h($m['is_adult'] ?: 'Adult') ?></span></td>
                        <td class="c"><?= h($m['bed_assigned'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$mutamers): ?><tr><td colspan="6" class="c" style="color:#8A90A8;">Traveller list to follow</td></tr><?php endif; ?>
            </tbody>
        </table>

        <!-- Flights -->
        <div class="sec"><span class="dot">3</span><h2>Flights</h2><span class="line"></span></div>
        <div class="passes">
            <?php foreach ($flights as [$label, $f]): $has = $f['no'] || $f['from'] || $f['to']; ?>
                <div class="pass<?= $has ? '' : ' empty' ?>">
                    <div class="pass-head"><span><?= h($label) ?></span><span class="fno num"><?= h($f['no'] ?: 'TBA') ?></span></div>
                    <div class="route">
                        <div class="code"><?= h($f['from'] ?: '———') ?></div>
                        <div class="plane">&#9992;</div>
                        <div class="code to"><?= h($f['to'] ?: '———') ?></div>
                    </div>
                    <div class="pass-foot"><span>Departs <b class="num"><?= h($f['dep']) ?></b></span><span>Lands <b class="num"><?= h($f['arr']) ?></b></span></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Ground transport -->
        <div class="sec"><span class="dot">4</span><h2>Ground Transport</h2><span class="line"></span></div>
        <div class="ride">
            <span class="ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="11" rx="2"/><path d="M3 11h18M7 19v-3M17 19v-3"/></svg></span>
            <div>
                <div class="route-chain"><?= h($transportRouteChain ? str_replace('-', ' → ', $transportRouteChain) : 'Not included') ?></div>
                <div class="sub num"><?= h(trim(($transportVehicleType ?: ($v['transport_type'] ?: '')) . ($transportDateRange ? ' · ' . $transportDateRange : ''), ' ·') ?: '—') ?></div>
            </div>
            <div class="who"><span>Arranged by</span><b><?= $transportLegs ? 'Company Transport' : 'Self' ?></b></div>
        </div>

        <div class="note"><b>Special instructions:</b> <?= h($v['special_instructions'] ?: 'Hotel accommodation as per this voucher.') ?></div>

        <!-- Urdu guidance -->
        <div class="urdu" dir="rtl">
            <h3>معتمرین کے لیے اہم ہدایات</h3>
            <ol>
                <li>آپ کا ہوٹل اور پیکج اس دستاویز پر درج ہے، اسی کے مطابق آپ کو رہائش اور دیگر سہولیات فراہم کی جائیں گی۔</li>
                <li>سفری سامان حرمین شریفین کی طرف لے جانا سعودی انتظامیہ کی طرف سے ممنوع ہے، خلاف ورزی پر جرمانہ آپ کی ذمہ داری ہوگا۔</li>
                <li>نشہ آور اشیاء شرعاً اور قانوناً ممنوع ہیں۔ سعودیہ میں منشیات لے جانے کی سزا موت ہے۔</li>
                <li>حرمین شریفین میں زمین پر پڑی کوئی چیز، پرس، موبائل فون یا قیمتی سامان ہرگز نہ اٹھائیں۔</li>
                <li>جدہ ایئرپورٹ پر امیگریشن میں 3 سے 5 گھنٹے لگ سکتے ہیں۔ پہنچ کر گھر والوں کو خیریت کی اطلاع ضرور دیں۔</li>
                <li>ہوٹل میں چیک اِن دوپہر 04:00 بجے اور چیک آؤٹ دوپہر 02:00 بجے ہے، اس کے بعد اگلی رات کا چارج ہوگا۔</li>
                <li>واپسی کی فلائٹ سے 10 گھنٹے پہلے اپنے سامان سمیت ہوٹل کے استقبالیہ پر موجود رہیں۔</li>
                <li>سعودی قانون کے مطابق کمپنی کے ہوٹلوں کے علاوہ کسی غیر رجسٹرڈ جگہ پر قیام جرم ہے۔</li>
            </ol>
        </div>
    </div>

    <!-- Footer strip -->
    <footer class="foot">
        <div class="contacts">
            <span><small>Office</small><b class="num"><?= h($makkahHelp) ?></b></span>
            <span><small>Helpline</small><b class="num"><?= h($madinahHelp) ?></b></span>
            <span><small>Website</small><b>aeroheightstravels.com</b></span>
        </div>
        <div class="motto">Your Trust Is Our Best Reward</div>
    </footer>
</div>

<div class="actions no-print">
    <?php if (!$isPublicView): ?><button type="button" class="btn-close" onclick="window.close()">Close</button><?php endif; ?>
    <button type="button" class="btn-print" onclick="window.print()">Print Voucher</button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<script>
document.querySelectorAll('.qr[data-qr]').forEach(function (el) {
    if (typeof qrcode !== 'function' || !el.dataset.qr) { el.closest('.qr-tile').style.display = 'none'; return; }
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
