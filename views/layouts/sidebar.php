<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/OperationsController.php';
require_once __DIR__ . '/../../config/Auth.php';

$currentRoute = $_GET['page'] ?? 'operations';
$quickStats = OperationsController::getQuickStats();
$tomorrowTotal = $quickStats['tomorrow']['arrivals'] + $quickStats['tomorrow']['transports'] + $quickStats['tomorrow']['checkins'];
?>

<!-- Left Sidebar Navigation -->
<aside id="mobileSidebar" class="no-print fixed inset-y-0 left-0 z-50 w-[82%] max-w-xs bg-slate-50 overflow-y-auto shadow-2xl transform -translate-x-full transition-transform duration-300 ease-in-out p-3 space-y-3 md:static md:inset-auto md:z-auto md:w-auto md:max-w-none md:col-span-3 md:bg-transparent md:overflow-visible md:shadow-none md:transform-none md:transition-none md:p-0">

    <!-- Mobile Drawer Header -->
    <div class="flex items-center justify-between md:hidden pb-1">
        <span class="text-sm font-black text-slate-800">Menu</span>
        <button type="button" onclick="toggleMobileSidebar(false)" aria-label="Close navigation menu" class="h-9 w-9 flex items-center justify-center rounded-xl bg-slate-200 text-slate-600 hover:bg-slate-300">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Main Operations Navigation -->
    <div class="bg-white p-3 rounded-2xl shadow-sm border border-slate-200/80 space-y-1">
        <div class="px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
            Operations & Logistics
        </div>

        <!-- Operations Manifest & Run-Sheet -->
        <a href="index.php?page=operations" class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center justify-between transition <?= $currentRoute === 'operations' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-700 hover:bg-slate-100/80' ?>">
            <div class="flex items-center">
                <i class="fa-solid fa-calendar-day w-5 text-center mr-2.5 <?= $currentRoute === 'operations' ? 'text-white' : 'text-blue-600' ?>"></i>
                <span>Operations Manifest</span>
            </div>
            <?php if ($tomorrowTotal > 0): ?>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= $currentRoute === 'operations' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800' ?>" title="Tomorrow's scheduled logistics">
                    <?= $tomorrowTotal ?> tmrw
                </span>
            <?php endif; ?>
        </a>

        <!-- Master Booking (Self-Sheet) -->
        <a href="index.php?page=bookings" class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center transition <?= $currentRoute === 'bookings' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-700 hover:bg-slate-100/80' ?>">
            <i class="fa-solid fa-passport w-5 text-center mr-2.5 <?= $currentRoute === 'bookings' ? 'text-white' : 'text-indigo-600' ?>"></i>
            <span>Master Bookings</span>
        </a>

        <!-- Hotel Voucher Engine -->
        <a href="index.php?page=vouchers" class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center transition <?= $currentRoute === 'vouchers' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-700 hover:bg-slate-100/80' ?>">
            <i class="fa-solid fa-hotel w-5 text-center mr-2.5 <?= $currentRoute === 'vouchers' ? 'text-white' : 'text-amber-500' ?>"></i>
            <span>Hotel Vouchers</span>
        </a>

        <!-- Complete Package Quotation -->
        <a href="index.php?page=packages" class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center transition <?= $currentRoute === 'packages' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-700 hover:bg-slate-100/80' ?>">
            <i class="fa-solid fa-suitcase-rolling w-5 text-center mr-2.5 <?= $currentRoute === 'packages' ? 'text-white' : 'text-orange-500' ?>"></i>
            <span>Complete Package</span>
        </a>

        <!-- Customized Air Tickets -->
        <a href="index.php?page=tickets" class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center transition <?= $currentRoute === 'tickets' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-700 hover:bg-slate-100/80' ?>">
            <i class="fa-solid fa-ticket w-5 text-center mr-2.5 <?= $currentRoute === 'tickets' ? 'text-white' : 'text-sky-500' ?>"></i>
            <span>Tickets</span>
        </a>

        <!-- Ticket Booking (AI: original ticket + passports) -->
        <a href="index.php?page=ticket_bookings" class="w-full text-left ml-4 pl-3.5 pr-3 py-2 rounded-xl text-xs font-semibold flex items-center transition border-l-2 <?= $currentRoute === 'ticket_bookings' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20 border-blue-600' : 'text-slate-700 hover:bg-slate-100/80 border-sky-200' ?>" style="width: calc(100% - 1rem);">
            <i class="fa-solid fa-wand-magic-sparkles w-5 text-center mr-2.5 <?= $currentRoute === 'ticket_bookings' ? 'text-white' : 'text-violet-500' ?>"></i>
            <span>Ticket Booking</span>
        </a>
    </div>

    <!-- Accounting & Ledgers -->
    <div class="bg-white p-3 rounded-2xl shadow-sm border border-slate-200/80 space-y-1">
        <div class="px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
            Accounting & Ledgers
        </div>

        <!-- Agent Receivable Ledger -->
        <a href="index.php?page=agent_ledger" class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center transition <?= $currentRoute === 'agent_ledger' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-700 hover:bg-slate-100/80' ?>">
            <i class="fa-solid fa-file-invoice-dollar w-5 text-center mr-2.5 <?= $currentRoute === 'agent_ledger' ? 'text-white' : 'text-rose-500' ?>"></i>
            <span>Agent Ledgers (B2B)</span>
        </a>

        <!-- Vendor Payable Ledger -->
        <a href="index.php?page=vendor_ledger" class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center transition <?= $currentRoute === 'vendor_ledger' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-700 hover:bg-slate-100/80' ?>">
            <i class="fa-solid fa-handshake w-5 text-center mr-2.5 <?= $currentRoute === 'vendor_ledger' ? 'text-white' : 'text-cyan-600' ?>"></i>
            <span>Vendor Payables</span>
        </a>

        <!-- Password Protected Buy / Sell Reports (weekly & monthly) -->
        <a href="index.php?page=financials" class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center justify-between transition <?= $currentRoute === 'financials' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-700 hover:bg-slate-100/80' ?>">
            <div class="flex items-center">
                <i class="fa-solid fa-chart-column w-5 text-center mr-2.5 <?= $currentRoute === 'financials' ? 'text-white' : 'text-amber-600' ?>"></i>
                <span>Buy / Sell Reports</span>
            </div>
            <i class="fa-solid fa-lock text-[10px] <?= $currentRoute === 'financials' ? 'text-white/70' : 'text-slate-400' ?>"></i>
        </a>
    </div>

    <!-- Administrative Control Center -->
    <div class="bg-white p-3 rounded-2xl shadow-sm border border-slate-200/80 space-y-1">
        <div class="px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
            Control Center
        </div>

        <a href="index.php?page=settings" class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center transition <?= $currentRoute === 'settings' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-700 hover:bg-slate-100/80' ?>">
            <i class="fa-solid fa-sliders w-5 text-center mr-2.5 <?= $currentRoute === 'settings' ? 'text-white' : 'text-slate-600' ?>"></i>
            <span>Admin Settings & Entities</span>
        </a>

        <a href="index.php?page=profile" class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center transition <?= $currentRoute === 'profile' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-700 hover:bg-slate-100/80' ?>">
            <i class="fa-solid fa-user-shield w-5 text-center mr-2.5 <?= $currentRoute === 'profile' ? 'text-white' : 'text-slate-600' ?>"></i>
            <span>My Account & Password</span>
        </a>

        <?php if (Auth::isAdmin()): ?>
        <a href="index.php?page=users" class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center transition <?= $currentRoute === 'users' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-700 hover:bg-slate-100/80' ?>">
            <i class="fa-solid fa-users-gear w-5 text-center mr-2.5 <?= $currentRoute === 'users' ? 'text-white' : 'text-slate-600' ?>"></i>
            <span>Team Logins</span>
        </a>
        <?php endif; ?>
    </div>

    <!-- Aeroheights Office Contacts Card -->
    <div class="text-white p-4 rounded-2xl shadow-md space-y-3" style="background: linear-gradient(135deg, #26206f 0%, #161a35 100%);">
        <div class="text-xs font-bold flex items-center text-amber-400">
            <i class="fa-solid fa-headset mr-2"></i> Aeroheights Office
        </div>
        <div class="space-y-1.5 text-[11px] font-mono text-slate-300">
            <a href="tel:+923035137777" class="flex justify-between items-center bg-white/5 hover:bg-white/10 p-2 rounded-xl border border-white/10 transition">
                <span class="text-slate-300 font-sans">Office:</span>
                <span class="font-bold text-amber-300">+92 303 5137777</span>
            </a>
            <a href="tel:+923034512512" class="flex justify-between items-center bg-white/5 hover:bg-white/10 p-2 rounded-xl border border-white/10 transition">
                <span class="text-slate-300 font-sans">Mobile:</span>
                <span class="font-bold text-amber-300">+92 303 4512512</span>
            </a>
            <a href="https://aeroheightstravels.com/" target="_blank" rel="noopener" class="block text-center text-[10px] font-sans text-blue-300 hover:text-white pt-1">aeroheightstravels.com</a>
        </div>
    </div>
</aside>

