<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/HotelBookingController.php';
require_once __DIR__ . '/../../controllers/AdminController.php';
require_once __DIR__ . '/../../controllers/VoucherShare.php';

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
    die('<div style="font-family:Jost,Arial,sans-serif;padding:30px;text-align:center;color:#E0475B;">Hotel booking record not found.</div>');
}

$settings     = AdminController::getSettings();
$agencyName   = $settings['agency_name'] ?? 'Aeroheights Travels & Tours';
$agencyPhone  = $settings['agency_phone'] ?? '';
$agencyEmail  = $settings['agency_email'] ?? 'aeroheights2024@gmail.com';
$agencyAddr   = $settings['agency_address'] ?? 'Pakistan';
$makkahHelp   = $settings['makkah_helpline'] ?? '+92 303 5137777';
$madinahHelp  = $settings['madinah_helpline'] ?? '+92 303 4512512';

// Company name on the voucher is chosen per booking; the agency logo only shows for our own brand.
$companyName  = trim((string)($b['company_name'] ?? '')) ?: 'Aeroheights Travels & Tours';
$showLogo     = ($companyName === 'Aeroheights Travels & Tours');

// Header QR -> signed read-only copy of this voucher on the main website (see VoucherShare).
$isPublicView = defined('AST_PUBLIC_VOUCHER');
$qrUrl = VoucherShare::url('b', $bookingId);
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

$totalPax = (int)$b['pax_adults'] + (int)$b['pax_children'] + (int)$b['pax_infants'];
$paxA = (int)$b['pax_adults']; $paxC = (int)$b['pax_children']; $paxI = (int)$b['pax_infants'];
$paxBreakdown = implode(' · ', array_filter([
    $paxA ? $paxA . ' adult' . ($paxA === 1 ? '' : 's') : '',
    $paxC ? $paxC . ' child' . ($paxC === 1 ? '' : 'ren') : '',
    $paxI ? $paxI . ' infant' . ($paxI === 1 ? '' : 's') : '',
])) ?: '—';
$stays = $b['stays'] ?? [];
$totalRooms = array_sum(array_map(static fn($s) => (int)$s['rooms'], $stays));
$confirmationSummary = implode(', ', array_filter(array_map(static fn($s) => $s['confirmation_number'], $stays))) ?: '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= h($companyName) ?> — Hotel Booking Voucher (<?= h($b['booking_ref']) ?>)</title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php require __DIR__ . '/../vouchers/_voucher_theme.php'; ?>
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
                    <div class="tagline">Hotel Reservations</div>
                <?php endif; ?>
            </div>
            <div class="band-side">
                <?php if (!$showLogo): ?><div class="partner"><img src="<?= h($brandLogo) ?>" alt="Partner" onerror="this.parentNode.style.display='none'"></div><?php endif; ?>
                <?php if ($qrUrl): ?><div class="qr-tile"><div class="qr" data-qr="<?= h($qrUrl) ?>"></div><span>Scan to verify</span></div><?php endif; ?>
            </div>
        </div>
        <div class="band-title">
            <div>
                <div class="eyebrow">Reservation Confirmed</div>
                <h1>Hotel Booking Voucher</h1>
            </div>
            <div class="chips">
                <span class="chip"><small>Ref</small><b class="num"><?= h($b['booking_ref']) ?></b></span>
                <span class="chip"><small>Issued</small><b class="num"><?= h(fmtDate($b['booking_date'], 'd M Y')) ?></b></span>
            </div>
        </div>
    </header>

    <div class="content">
        <!-- Summary tiles -->
        <div class="tiles">
            <div class="tile lead">
                <div class="k">Lead Guest</div>
                <div class="v"><?= h($b['lead_guest_name'] ?: '—') ?></div>
                <div class="s">Name on the reservation</div>
            </div>
            <div class="tile">
                <div class="k">Guests</div>
                <div class="v num"><?= $totalPax ?> PAX</div>
                <div class="s"><?= h($paxBreakdown) ?></div>
            </div>
            <div class="tile">
                <div class="k">Rooms</div>
                <div class="v num"><?= $totalRooms ?: '—' ?></div>
                <div class="s">booked</div>
            </div>
            <div class="tile">
                <div class="k">Stay</div>
                <div class="v num"><?= (int)$b['total_nights'] ?> Nights</div>
                <div class="s"><?= count($stays) ?> hotel<?= count($stays) === 1 ? '' : 's' ?></div>
            </div>
        </div>

        <!-- Hotels -->
        <div class="sec"><span class="dot">1</span><h2>Hotels</h2><span class="line"></span><span class="aside">Confirmation: <?= h($confirmationSummary) ?></span></div>
        <?php foreach ($stays as $s): $city = strtolower(trim((string)$s['city'])); ?>
            <div class="stay <?= $city === 'makkah' ? 'makkah' : ($city === 'madinah' ? 'madinah' : '') ?>">
                <div class="bar"></div>
                <div class="stay-body">
                    <div>
                        <span class="city-tag"><?= h($s['city'] ?: 'Hotel') ?></span>
                        <div class="hotel"><?= h($s['hotel_name']) ?></div>
                        <div class="facts">
                            <span class="fact"><b class="num"><?= (int)$s['rooms'] ?></b> room<?= (int)$s['rooms'] === 1 ? '' : 's' ?></span>
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
        <div class="total-row"><span>Total stay: <b class="num"><?= (int)$b['total_nights'] ?> nights</b></span></div>

        <!-- Terms -->
        <div class="sec"><span class="dot">2</span><h2>Booking Notes</h2><span class="line"></span></div>
        <div class="note" style="margin-top:0;">
            <?php if ($b['remarks']): ?><div><b>Remarks:</b> <?= h($b['remarks']) ?></div><?php endif; ?>
            <ul>
                <li>Check-in from 16:00 and check-out by 12:00 (hotel time).</li>
                <li>Please show this voucher and the lead guest's passport at the hotel reception.</li>
                <li>Any change to the booking is subject to hotel availability.</li>
                <li>Once confirmed on a definite basis, the reservation is non-refundable.</li>
            </ul>
        </div>
    </div>

    <!-- Footer strip -->
    <footer class="foot">
        <div class="contacts">
            <span><small>Office</small><b class="num"><?= h($makkahHelp) ?></b></span>
            <span><small>Helpline</small><b class="num"><?= h($madinahHelp) ?></b></span>
            <?php if ($showLogo && $agencyEmail): ?><span><small>Email</small><b><?= h($agencyEmail) ?></b></span><?php endif; ?>
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
