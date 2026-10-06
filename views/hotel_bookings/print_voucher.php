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
    die('<div style="font-family:Arial;padding:30px;text-align:center;color:#b91c1c;">Hotel booking record not found.</div>');
}

$settings     = AdminController::getSettings();
$agencyName   = $settings['agency_name'] ?? 'Aeroheights Travels & Tours';
$agencyLogo   = $settings['agency_logo'] ?? 'assets/img/logo.png';
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

$totalPax = (int)$b['pax_adults'] + (int)$b['pax_children'] + (int)$b['pax_infants'];
$paxSummary = "A:{$b['pax_adults']} C:{$b['pax_children']} I:{$b['pax_infants']}";
$stays = $b['stays'] ?? [];
$confirmationSummary = implode(', ', array_filter(array_map(static fn($s) => $s['confirmation_number'], $stays))) ?: '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= h($companyName) ?> — Hotel Sale Voucher (<?= h($b['booking_ref']) ?>)</title>
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
        .wa-qr { background: #fff; padding: 4px; border: 1px solid #cbd5e1; border-radius: 8px; line-height: 0; }
        .wa-qr svg { display: block; width: 100%; height: 100%; }
        .wa-qr-big .wa-qr { width: 86px; height: 86px; }
        .wa-qr-small .wa-qr { width: 54px; height: 54px; }
        .wa-qr-caption { font-size: 8.5px; font-weight: 800; color: #15803d; letter-spacing: 0.03em; text-transform: uppercase; white-space: nowrap; }
        .wa-qr-small .wa-qr-caption { font-size: 7.5px; }
        .header-right-row { display: flex; justify-content: flex-end; align-items: center; gap: 10px; }
        /* Public (website) view: verified bar + link back to the main website */
        .public-bar { max-width: 820px; margin: 0 auto 12px; display: flex; justify-content: space-between; align-items: center; gap: 10px; font-size: 12px; font-weight: 700; }
        .public-bar a { color: #26206f; text-decoration: none; background: #fff; border: 1px solid #b5d6f6; padding: 7px 14px; border-radius: 999px; }
        .public-bar .verified { color: #15803d; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 7px 14px; border-radius: 999px; }
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
            background: linear-gradient(115deg, #eaf3fc 0%, #ffffff 38%, #ffffff 62%, #eaf3fc 100%);
            border: none; border-radius: 14px;
            box-shadow: inset 0 0 0 1px #cfe3f8;
            padding: 14px 18px; margin-bottom: 6px;
        }
        .header-grid::after {
            content: ''; position: absolute; left: 18px; right: 18px; bottom: -2px; height: 3px; border-radius: 3px;
            background: linear-gradient(90deg, #2183DF 0%, #0f172a 50%, #2183DF 100%);
        }
        .company-name.agency-big { color: #26206f; text-shadow: 0 1px 0 #fff, 0 2px 6px rgba(33, 131, 223, 0.18); }
        .mini-logo { padding: 0; margin-bottom: 4px; background: none; border: none; border-radius: 0; box-shadow: none; }
        .mini-logo img { height: 74px; filter: drop-shadow(0 2px 3px rgba(15, 23, 42, 0.12)); }
        .header-logo img { mix-blend-mode: multiply; } /* blend the white JPG background into the header band */

        .status-tag { display: inline-block; margin-top: 4px; background: #0f766e; color: #fff; font-size: 10px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; padding: 3px 10px; border-radius: 999px; }

        .title-banner { text-align: center; margin: 10px 0 10px; }
        .title-banner span { display: inline-block; background: #2183DF; color: #fff; font-weight: 800; font-size: 14px; letter-spacing: 0.06em; text-transform: uppercase; padding: 5px 26px; border-radius: 999px; }

        .meta-bar { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #777; font-size: 11.5px; font-weight: 700; margin-bottom: 10px; }
        .meta-bar div { padding: 6px 10px; border-right: 1px solid #777; }
        .meta-bar div:nth-child(2n) { border-right: none; }
        .meta-bar .lbl { color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 9.5px; display: block; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #777; padding: 5px 6px; font-size: 11px; }
        th { background-color: #d9d9d9; color: #111827; font-weight: 700; text-align: center; text-transform: uppercase; font-size: 9.5px; }
        td { color: #1e293b; }
        .text-center { text-align: center; }
        .font-bold { font-weight: 700; }

        .section-title { background: #d9d9d9; color: #111827; font-weight: 800; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; text-align: center; padding: 4px 6px; border: 1px solid #777; }

        .remarks-box { border: 1px solid #777; padding: 10px 12px; font-size: 11px; margin: 10px 0; line-height: 1.6; }
        .remarks-box ul { margin: 4px 0 0; padding-left: 18px; }

        .helpline-bar { display: grid; grid-template-columns: 1fr 1fr; font-size: 11.5px; font-weight: 800; margin: 10px 0 14px; border: 1px solid #777; }
        .helpline-bar div { padding: 6px 10px; }
        .helpline-bar div:first-child { border-right: 1px solid #777; }

        .footer-strip { text-align: center; font-size: 10px; color: #64748b; border-top: 1px solid #cbd5e1; padding-top: 8px; margin-top: 6px; }

        .print-actions { display: flex; justify-content: flex-end; gap: 8px; padding-top: 16px; margin-top: 12px; border-top: 1px solid #e2e8f0; }
        .print-actions button { font-size: 12px; font-weight: 700; padding: 8px 18px; border-radius: 10px; border: none; cursor: pointer; }
        .btn-close { background: #e2e8f0; color: #334155; }
        .btn-print { background: #2563eb; color: #fff; }

        @media print {
            @page { size: A4 portrait; margin: 10mm 12mm; }
            body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; background: #fff !important; color: #000; font-size: 11px; line-height: 1.25; padding: 0; }
            .sheet { max-width: none; border: none; border-radius: 0; box-shadow: none; padding: 0; }
            .no-print { display: none !important; }
            th { background-color: #d9d9d9 !important; color: #111827 !important; }
            .section-title { background: #d9d9d9 !important; color: #111827 !important; }
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

        <div class="header-grid">
            <div class="header-left">
                <p class="company-name<?= $showLogo ? '' : ' agency-big' ?>"><?= h($companyName) ?></p>
                <?php if ($showLogo): ?>
                    <p class="header-meta"><?= h($agencyAddr) ?><br>Email: <?= h($agencyEmail) ?><?= $agencyPhone ? ' | Tel: ' . h($agencyPhone) : '' ?></p>
                <?php else: ?>
                    <p class="agency-tagline"><span class="star">&#9733;</span>Your Trust Is Our Best Reward</p>
                <?php endif; ?>
            </div>
            <div class="header-logo">
                <?php if ($showLogo): ?>
                    <img src="<?= h($agencyLogo) ?>" alt="Agency Logo" onerror="this.style.display='none'">
                <?php endif; ?>
                <?php if (!$showLogo): ?>
                    <div class="wa-qr-box wa-qr-big"><div class="wa-qr" data-qr="<?= h($qrUrl) ?>"></div><span class="wa-qr-caption">Scan to Verify Voucher</span></div>
                <?php endif; ?>
            </div>
            <div class="header-right">
                <?php if (!$showLogo): ?>
                    <div class="mini-logo"><img src="<?= h($brandLogo) ?>" alt="Logo" onerror="this.parentNode.style.display='none'"></div>
                <?php endif; ?>
                <?php if ($showLogo): ?>
                    <div class="header-right-row">
                        <div>
                            <p class="header-meta">Date: <span class="font-mono font-bold"><?= h(fmtDate($b['booking_date'])) ?></span></p>
                            <span class="status-tag">Hotel Sale Voucher</span>
                        </div>
                        <div class="wa-qr-box wa-qr-small"><div class="wa-qr" data-qr="<?= h($qrUrl) ?>"></div><span class="wa-qr-caption">Verify</span></div>
                    </div>
                <?php else: ?>
                    <p class="header-meta">Date: <span class="font-mono font-bold"><?= h(fmtDate($b['booking_date'])) ?></span></p>
                    <span class="status-tag">Hotel Sale Voucher</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="title-banner"><span>Hotel Sale Voucher</span></div>

        <div class="meta-bar">
            <div><span class="lbl">Voucher Ref</span><span class="font-mono font-bold"><?= h($b['booking_ref']) ?></span></div>
            <div><span class="lbl">Hotel Confirmation / BRN #</span><?= h($confirmationSummary) ?></div>
            <div><span class="lbl">Lead Guest Name</span><?= h($b['lead_guest_name']) ?></div>
            <div><span class="lbl">Total PAX</span><?= $totalPax ?> (<?= h($paxSummary) ?>)</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:32px;">Rooms</th>
                    <th>Hotel Name</th>
                    <th>City</th>
                    <th>Room Type</th>
                    <th>Checkin</th>
                    <th style="width:44px;">Nights</th>
                    <th>Checkout</th>
                    <th>Confirmation #</th>
                    <th>Meal Plan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stays as $s): ?>
                <tr>
                    <td class="text-center font-bold"><?= (int)$s['rooms'] ?></td>
                    <td class="font-bold"><?= h($s['hotel_name']) ?></td>
                    <td class="text-center"><?= h($s['city']) ?></td>
                    <td class="text-center"><?= h($s['room_type']) ?></td>
                    <td class="font-mono text-center"><?= h(fmtDate($s['checkin_date'])) ?></td>
                    <td class="text-center font-bold"><?= (int)$s['nights'] ?></td>
                    <td class="font-mono text-center"><?= h(fmtDate($s['checkout_date'])) ?></td>
                    <td class="text-center font-mono"><?= h($s['confirmation_number'] ?: '-') ?></td>
                    <td class="text-center"><?= h($s['meal_plan']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right font-bold" style="background:#f1f5f9;">Total Nights</td>
                    <td class="text-center font-bold" style="background:#f1f5f9;"><?= (int)$b['total_nights'] ?></td>
                    <td colspan="3" style="background:#f1f5f9;"></td>
                </tr>
            </tfoot>
        </table>

        <div class="remarks-box">
            <strong>Remarks:</strong>
            <ul>
                <?php if ($b['remarks']): ?><li><?= h($b['remarks']) ?></li><?php endif; ?>
                <li>We hope the reservation is in accordance with your request.</li>
                <li>Check-in after 16:00 hours and Check-out by 12:00 hours.</li>
                <li>Any amendment to the booking is subject to availability.</li>
                <li>Reservation can only be secured on a confirmed basis; the booking is non-refundable once confirmed on a definite basis.</li>
            </ul>
        </div>

        <div class="section-title">Helpline</div>
        <div class="helpline-bar">
            <div>OFFICE: <?= h($makkahHelp) ?></div>
            <div>HELPLINE: <?= h($madinahHelp) ?></div>
        </div>

        <div class="footer-strip">
            <?php if ($showLogo): ?>
                <?= h($agencyName) ?> — <?= h($agencyAddr) ?><br>
                Email: <?= h($agencyEmail) ?><?= $agencyPhone ? ' &nbsp;|&nbsp; Tel: ' . h($agencyPhone) : '' ?>
            <?php else: ?>
                <?= h($companyName) ?>
            <?php endif; ?>
        </div>

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
