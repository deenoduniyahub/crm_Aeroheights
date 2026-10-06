<?php
declare(strict_types=1);

/**
 * Master Front Controller & Unified RESTful API Router
 * Agency: Aeroheights Travels & Tours (crm.aeroheightstravels.com)
 */

date_default_timezone_set('Asia/Karachi'); // Pakistan office time (the live server runs on UTC)

require_once __DIR__ . '/config/Session.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/config/Auth.php';

// Controllers
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/AccountController.php';
require_once __DIR__ . '/controllers/AdminController.php';
require_once __DIR__ . '/controllers/BookingController.php';
require_once __DIR__ . '/controllers/VoucherController.php';
require_once __DIR__ . '/controllers/HotelBookingController.php';
require_once __DIR__ . '/controllers/LedgerController.php';
require_once __DIR__ . '/controllers/OperationsController.php';
require_once __DIR__ . '/controllers/BookingFileController.php';
require_once __DIR__ . '/controllers/PackageQuotationController.php';
require_once __DIR__ . '/controllers/AirTicketController.php';
require_once __DIR__ . '/controllers/TicketBookingController.php';
require_once __DIR__ . '/controllers/SmartMasterBookingController.php';
require_once __DIR__ . '/controllers/SmartVisaController.php';
require_once __DIR__ . '/services/BookingImportExport.php';
require_once __DIR__ . '/services/TicketAiReader.php';

Session::start();

// =============================================================================
// 0. Login / Logout (must be reachable without an existing session)
// =============================================================================
if (isset($_GET['api']) && $_GET['api'] === 'login') {
    header('Content-Type: application/json; charset=utf-8');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    echo json_encode(Auth::attempt(
        (string)($data['username'] ?? ''),
        (string)($data['password'] ?? ''),
        !empty($data['remember'])
    ));
    exit;
}

// Forgot password: emailed 6-digit code, then a new password (no session needed)
if (isset($_GET['api']) && in_array($_GET['api'], ['request_password_otp', 'reset_password_otp'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!Session::validateCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Security token expired. Please refresh the page and try again.']);
        exit;
    }
    try {
        echo json_encode($_GET['api'] === 'request_password_otp'
            ? AccountController::requestOtp((string)($data['login'] ?? ''))
            : AccountController::resetWithOtp((string)($data['login'] ?? ''), (string)($data['code'] ?? ''), (string)($data['password'] ?? ''), (string)($data['confirm'] ?? '')));
    } catch (Throwable $e) {
        error_log('Password reset: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
    }
    exit;
}

if (isset($_GET['api']) && $_GET['api'] === 'logout') {
    Auth::logout();
    header('Location: index.php?page=login');
    exit;
}

if (($_GET['page'] ?? '') === 'login') {
    require __DIR__ . '/views/auth/login.php';
    exit;
}

// Every other page and API action requires a signed-in team member.
Auth::requireLogin(isset($_GET['api']));

// =============================================================================
// 1. REST API Dispatcher (JSON Endpoints)
// =============================================================================
if (isset($_GET['api'])) {
    $api = $_GET['api'];

    // Read-only endpoints are reachable by every role, including view-only accounts.
    // Everything else mutates data and is blocked for view-only accounts.
    // Own-account actions are allowed for every signed-in role (including view-only).
    $accountApis = ['update_profile', 'request_my_otp', 'reset_my_password', 'unlock_financial', 'lock_financial'];
    // Ledger money actions need the owner's email + password unlock (same lock as the Buy / Sell reports).
    $financialApis = ['record_agent_payment', 'save_agent_payment', 'update_agent_payment', 'delete_agent_payment', 'save_agent_adjustment', 'save_vendor_payment'];
    if (in_array($api, $financialApis, true) && !Session::isFinancialUnlocked()) {
        header('Content-Type: application/json', true, 403);
        echo json_encode(['success' => false, 'message' => 'Ledgers are locked. Unlock with the owner email and password first.']);
        exit;
    }
    $readOnlyApis = ['get_booking', 'get_voucher', 'get_hotel_booking', 'search_master_bookings', 'download_booking_file', 'export_bookings', 'air_ticket_original', 'ticket_booking_passport', 'export_ticket_bookings'];
    if (!in_array($api, $readOnlyApis, true) && !in_array($api, $accountApis, true)) {
        Auth::requireWrite();
    }

    // Binary/download endpoints must not be wrapped in JSON.
    if ($api === 'download_booking_file') {
        BookingFileController::download((int)($_GET['id'] ?? 0), (string)($_GET['token'] ?? ''));
    }
    if ($api === 'air_ticket_original') {
        AirTicketController::streamOriginal((int)($_GET['id'] ?? 0), !empty($_GET['download']));
    }
    if ($api === 'ticket_booking_passport') {
        TicketBookingController::streamPassport((int)($_GET['id'] ?? 0), (int)($_GET['i'] ?? 0), !empty($_GET['download']));
    }
    if ($api === 'export_ticket_bookings') {
        TicketBookingController::exportCsv($_GET);
    }
    if ($api === 'export_bookings') {
        BookingImportExport::export([
            'booking_id' => !empty($_GET['booking_id']) ? (int)$_GET['booking_id'] : null,
            'date_from' => $_GET['date_from'] ?? '', 'date_to' => $_GET['date_to'] ?? '',
            'month' => $_GET['month'] ?? '', 'year' => $_GET['year'] ?? '',
            'agent_id' => $_GET['agent_id'] ?? '', 'vendor_id' => $_GET['vendor_id'] ?? '', 'status' => $_GET['status'] ?? ''
        ], $_GET['format'] ?? 'csv');
    }

    header('Content-Type: application/json; charset=utf-8');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf_token'] ?? '');

    try {
        switch ($api) {

            // Team Login Management (admin-only)
            case 'save_user':
                Auth::requireAdmin(true);
                echo json_encode(UserController::save($data));
                break;

            case 'delete_user':
                Auth::requireAdmin(true);
                echo json_encode(UserController::delete((int)($data['id'] ?? 0), (int)(Auth::user()['id'] ?? 0)));
                break;

            // Own account: profile + password change by emailed code
            case 'update_profile':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                echo json_encode(AccountController::updateProfile((int)(Auth::user()['id'] ?? 0), $data));
                break;

            case 'request_my_otp':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                echo json_encode(AccountController::requestOtp((string)(Auth::user()['username'] ?? '')));
                break;

            case 'reset_my_password':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                echo json_encode(AccountController::resetWithOtp((string)(Auth::user()['username'] ?? ''), (string)($data['code'] ?? ''), (string)($data['password'] ?? ''), (string)($data['confirm'] ?? '')));
                break;

            // Ledgers & Buy / Sell reports: unlocked by the owner's email + password
            case 'unlock_financial':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                echo json_encode(AccountController::unlockFinancial((string)($data['login'] ?? ''), (string)($data['password'] ?? '')));
                break;

            case 'lock_financial':
                AuthController::logoutFinancial();
                header('Location: index.php?page=financials');
                exit;

            // Master Bookings
            case 'save_booking':
                echo json_encode(BookingController::create($data));
                break;

            case 'update_booking':
                echo json_encode(BookingController::update((int)($data['id'] ?? 0), $data));
                break;

            case 'bulk_update_bookings':
                echo json_encode(BookingController::bulkUpdate($data));
                break;

            case 'get_booking':
                $booking = BookingController::getById((int)($_GET['id'] ?? 0));
                echo json_encode(['success' => $booking !== null, 'booking' => $booking]);
                break;

            case 'delete_booking':
                echo json_encode(BookingController::delete((int)($data['id'] ?? 0)));
                break;

            case 'search_master_bookings':
                echo json_encode(['success' => true, 'results' => BookingController::searchLite((string)($_GET['q'] ?? ''))]);
                break;

            // Hotel Vouchers
            case 'save_voucher':
                echo json_encode(VoucherController::create($data));
                break;

            case 'update_voucher':
                echo json_encode(VoucherController::update((int)($data['id'] ?? 0), $data));
                break;

            case 'get_voucher':
                $voucher = VoucherController::get((int)($_GET['id'] ?? 0));
                echo json_encode(['success' => $voucher !== null, 'voucher' => $voucher]);
                break;

            case 'delete_voucher':
                echo json_encode(VoucherController::delete((int)($data['id'] ?? 0)));
                break;

            // Only Hotel Booking (standalone hotel booking + invoice/voucher)
            case 'save_hotel_booking':
                echo json_encode(HotelBookingController::create($data));
                break;

            case 'update_hotel_booking':
                echo json_encode(HotelBookingController::update((int)($data['id'] ?? 0), $data));
                break;

            case 'get_hotel_booking':
                $hotelBooking = HotelBookingController::get((int)($_GET['id'] ?? 0));
                echo json_encode(['success' => $hotelBooking !== null, 'booking' => $hotelBooking]);
                break;

            case 'delete_hotel_booking':
                echo json_encode(HotelBookingController::delete((int)($data['id'] ?? 0)));
                break;

            // Conversion between "Build Hotel Voucher" and "Only Hotel Booking"
            case 'convert_hotel_booking_to_voucher':
                echo json_encode(HotelBookingController::convertToVoucher((int)($data['id'] ?? 0)));
                break;

            case 'convert_voucher_to_hotel_booking':
                echo json_encode(VoucherController::convertToHotelBooking((int)($data['id'] ?? 0)));
                break;

            // Financial Ledgers & Cash Flow
            case 'record_agent_payment':
            case 'save_agent_payment':
                echo json_encode(LedgerController::recordAgentPayment($data));
                break;

            case 'update_agent_payment':
                echo json_encode(LedgerController::updateAgentPayment($data));
                break;

            case 'delete_agent_payment':
                echo json_encode(LedgerController::deleteAgentPayment($data));
                break;

                case 'save_agent_adjustment':
                    echo json_encode(LedgerController::recordAgentAdjustment($data));
                    break;

            case 'save_vendor_payment':
                echo json_encode(LedgerController::recordVendorPayment($data));
                break;

            // Complete Package Quotations
            case 'save_package_quotation':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                echo json_encode(PackageQuotationController::save($data));
                break;

            case 'delete_package_quotation':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                echo json_encode(PackageQuotationController::delete((int)($data['id'] ?? 0)));
                break;

            // Customized Air Tickets (multipart: ticket JSON + optional original file)
            case 'save_air_ticket':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                $ticketData = json_decode((string)($_POST['ticket'] ?? ''), true);
                echo json_encode(AirTicketController::save(is_array($ticketData) ? $ticketData : [], $_FILES['file'] ?? []));
                break;

            // AI (Gemini) reading of an uploaded ticket, or of a saved ticket's original (id)
            case 'read_air_ticket_ai':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                session_write_close(); // the AI call can take a minute; don't lock the user's other requests
                @set_time_limit(150);
                if (!empty($_FILES['file'])) {
                    echo json_encode(TicketAiReader::readUpload($_FILES['file']));
                } else {
                    $original = AirTicketController::originalBytes((int)($_POST['id'] ?? 0));
                    echo json_encode($original ? TicketAiReader::readBytes($original[0], $original[1]) : ['success' => false, 'message' => 'Original ticket not found.']);
                }
                break;

            // Ticket Booking: original ticket + passports read by Gemini, saved as a ticket with booking rates
            case 'smart_book_ticket':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                session_write_close(); // the AI reads can take a minute; don't lock the user's other requests
                @set_time_limit(240);
                echo json_encode(TicketBookingController::smartBook($_POST, $_FILES));
                break;

            // One file (ticket or passport) per request so every request stays under the host's gateway limit
            case 'read_booking_file':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                session_write_close();
                @set_time_limit(90);
                echo json_encode(TicketAiReader::readUpload($_FILES['file'] ?? [], (string)($_POST['kind'] ?? 'ticket')));
                break;

            // Master Bookings -> Smart Auto Booking: AI read results in, one draft booking per traveller out
            case 'smart_master_prepare':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                echo json_encode(SmartMasterBookingController::prepare($data));
                break;

            // Master Bookings -> Smart Visa Upload: which booking each AI-read visa belongs to
            case 'smart_visa_match':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                echo json_encode(SmartVisaController::match((array)($data['visas'] ?? [])));
                break;

            case 'save_ticket_booking':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                echo json_encode(TicketBookingController::update($data));
                break;

            case 'delete_air_ticket':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                echo json_encode(AirTicketController::delete((int)($data['id'] ?? 0)));
                break;

            // Master Booking Import / Attachments
            case 'preview_booking_import':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                echo json_encode(BookingImportExport::stageUploadedFile($_FILES['file'] ?? []));
                break;

            case 'confirm_booking_import':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                echo json_encode(BookingImportExport::confirmImport((string)($data['token'] ?? ''), (array)($data['mapping'] ?? [])));
                break;

            case 'upload_booking_file':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                echo json_encode(BookingFileController::upload((int)($data['booking_id'] ?? $_POST['booking_id'] ?? 0), (string)($data['type'] ?? $_POST['type'] ?? ''), $_FILES['file'] ?? []));
                break;

            case 'remove_booking_file':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                echo json_encode(BookingFileController::remove((int)($data['file_id'] ?? 0)));
                break;

            case 'bulk_upload_booking_file':
                if (!Session::validateCsrfToken($csrf)) throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
                $bulkFileBookingIds = json_decode((string)($_POST['booking_ids'] ?? '[]'), true);
                echo json_encode(BookingFileController::bulkUpload(is_array($bulkFileBookingIds) ? $bulkFileBookingIds : [], (string)($_POST['type'] ?? ''), $_FILES['file'] ?? []));
                break;

            // Admin Entities & Settings
            case 'save_agent':
                echo json_encode(AdminController::saveAgent($data));
                break;

            case 'delete_agent':
                echo json_encode(AdminController::deleteAgent((int)($data['id'] ?? 0)));
                break;

            case 'save_vendor':
                echo json_encode(AdminController::saveVendor($data));
                break;

            case 'delete_vendor':
                echo json_encode(AdminController::deleteVendor((int)($data['id'] ?? 0)));
                break;

            case 'update_settings':
                echo json_encode(AdminController::updateSettings($data));
                break;

            default:
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => "Unknown API endpoint: {$api}"]);
                break;
        }
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// =============================================================================
// 2. Dedicated Standalone Print View Routes
// =============================================================================
$page = $_GET['page'] ?? 'operations';

if ($page === 'print_voucher') {
    require __DIR__ . '/views/vouchers/print.php';
    exit;
}

if ($page === 'print_hotel_booking_invoice') {
    require __DIR__ . '/views/hotel_bookings/print_invoice.php';
    exit;
}

if ($page === 'print_hotel_booking_voucher') {
    require __DIR__ . '/views/hotel_bookings/print_voucher.php';
    exit;
}

// Ledgers, statements and Buy / Sell reports sit behind the owner email + password lock.
$financialPages = ['agent_ledger', 'vendor_ledger', 'financials', 'print_agent_statement'];
$financialLocked = in_array($page, $financialPages, true) && !Session::isFinancialUnlocked();
if ($financialLocked && $page === 'print_agent_statement') {
    header('Location: index.php?page=agent_ledger');
    exit;
}

if ($page === 'print_agent_statement') {
    require __DIR__ . '/views/ledgers/print_agent.php';
    exit;
}

if ($page === 'print_bookings') {
    require __DIR__ . '/views/bookings/print.php';
    exit;
}

if ($page === 'print_package') {
    require __DIR__ . '/views/packages/print.php';
    exit;
}

if ($page === 'print_ticket') {
    require __DIR__ . '/views/tickets/print.php';
    exit;
}

// No separate Tickets tab: Ticket Booking is the ticket list. The ticket form (?page=tickets&id=N / &new=1)
// stays because Ticket Booking opens it to edit travellers and flights.
if ($page === 'tickets' && empty($_GET['id']) && empty($_GET['new'])) {
    header('Location: index.php?page=ticket_bookings');
    exit;
}

// Admin-only pages must be gated before any layout HTML is streamed, otherwise the
// redirect below cannot fire (PHP cannot send a Location header once output has started).
if ($page === 'users') {
    Auth::requireAdmin();
}

// =============================================================================
// 3. Main Dashboard Layout & View Dispatcher
// =============================================================================
require __DIR__ . '/views/layouts/header.php';
require __DIR__ . '/views/layouts/sidebar.php';

if ($financialLocked) {
    $page = 'financial_lock';
}

switch ($page) {
    case 'financial_lock':
        require __DIR__ . '/views/financials/lock.php';
        break;

    case 'profile':
        require __DIR__ . '/views/admin/profile.php';
        break;

    case 'operations':
        require __DIR__ . '/views/operations/index.php';
        break;

    case 'bookings':
        require __DIR__ . '/views/bookings/index.php';
        break;

    case 'vouchers':
        require __DIR__ . '/views/vouchers/index.php';
        break;

    case 'packages':
        require __DIR__ . '/views/packages/index.php';
        break;

    case 'tickets':
        require __DIR__ . '/views/tickets/index.php';
        break;

    case 'ticket_bookings':
        require __DIR__ . '/views/tickets/bookings.php';
        break;

    case 'agent_ledger':
        require __DIR__ . '/views/ledgers/agent.php';
        break;

    case 'vendor_ledger':
        require __DIR__ . '/views/ledgers/vendor.php';
        break;

    case 'financials':
        require __DIR__ . '/views/financials/index.php';
        break;

    case 'settings':
        require __DIR__ . '/views/admin/settings.php';
        break;

    case 'users':
        require __DIR__ . '/views/admin/users.php';
        break;

    default:
        require __DIR__ . '/views/operations/index.php';
        break;
}

require __DIR__ . '/views/layouts/footer.php';

