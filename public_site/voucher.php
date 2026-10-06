<?php
declare(strict_types=1);

/**
 * Public, read-only voucher page on the main website: https://aeroheightstravels.com/voucher.php?t=<token>
 * Reached by scanning the QR code on a printed voucher. Deploy to public_html/voucher.php (next to
 * the /crm folder). Only a valid signed token (see VoucherShare) shows a voucher; this page
 * never links to the software and nothing here can change data.
 */

ini_set('display_errors', '0');
date_default_timezone_set('Asia/Karachi');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, max-age=0');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

$appRoot = __DIR__ . '/crm';
require_once $appRoot . '/config/Database.php';
require_once $appRoot . '/controllers/VoucherShare.php';

function publicVoucherNotFound(): void {
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Voucher Not Found — Aeroheights Travels &amp; Tours</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f5f9; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; color: #0f172a; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 28px 24px; max-width: 420px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.06); }
        h1 { font-size: 18px; margin: 0 0 8px; color: #26206f; }
        p { font-size: 14px; color: #475569; margin: 0 0 18px; line-height: 1.5; }
        a { display: inline-block; background: #2183DF; color: #fff; text-decoration: none; font-weight: 700; padding: 10px 20px; border-radius: 999px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Voucher not found</h1>
        <p>This voucher link is invalid or the voucher is no longer available. Please contact Aeroheights Travels &amp; Tours for help.</p>
        <a href="https://aeroheightstravels.com/">Go to aeroheightstravels.com</a>
    </div>
</body>
</html>
    <?php
    exit;
}

$ref = VoucherShare::verify((string)($_GET['t'] ?? ''));
if (!$ref) publicVoucherNotFound();

define('AST_PUBLIC_VOUCHER', true);
$_GET = ['id' => (string)$ref['id']];

try {
    ob_start();
    if ($ref['type'] === 'v') {
        require_once $appRoot . '/controllers/VoucherController.php';
        if (!VoucherController::get($ref['id'])) { ob_end_clean(); publicVoucherNotFound(); }
        require $appRoot . '/views/vouchers/print.php';
    } else {
        require_once $appRoot . '/controllers/HotelBookingController.php';
        if (!HotelBookingController::get($ref['id'])) { ob_end_clean(); publicVoucherNotFound(); }
        require $appRoot . '/views/hotel_bookings/print_voucher.php';
    }
    $html = (string)ob_get_clean();
    // No viewport meta on purpose: phones show the whole A4 voucher zoomed-out, like the printed copy.
    echo str_replace('<meta charset="UTF-8">', '<meta charset="UTF-8">' . "\n    " . '<meta name="robots" content="noindex, nofollow">', $html);
} catch (Throwable $e) {
    if (ob_get_level()) ob_end_clean();
    publicVoucherNotFound();
}
