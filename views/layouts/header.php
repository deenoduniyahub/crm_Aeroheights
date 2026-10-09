<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/Session.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../config/Auth.php';

Session::start();
$flash = Session::getFlash();
$csrfToken = Session::getCsrfToken();
$agencyName = Database::fetchValue("SELECT setting_value FROM system_settings WHERE setting_key = 'agency_name'") ?: 'Aeroheights Travels & Tours';
$agencyLogo = Database::fetchValue("SELECT setting_value FROM system_settings WHERE setting_key = 'agency_logo'") ?: 'assets/img/logo.png';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <title><?= htmlspecialchars($agencyName) ?> | CRM & Operations</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="assets/js/brand-tailwind.js?v=<?= (int)@filemtime(__DIR__ . '/../../assets/js/brand-tailwind.js') ?>"></script>

    <!-- FontAwesome 6 Pro CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    
    <!-- Google Fonts: Jost (brand font everywhere) + Amiri for Urdu text only -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Jost:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400&family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Print Stylesheet -->
    <link rel="stylesheet" href="assets/css/print.css?v=<?= (int)@filemtime(__DIR__ . '/../../assets/css/print.css') ?>">

    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        .font-urdu { font-family: 'Amiri', serif; }
        /* Header uses the signature navy gradient (.brand-header in assets/js/brand-tailwind.js) */
        .brand-header .bg-slate-800, .brand-header .bg-slate-800\/80 { background-color: rgba(255,255,255,0.08); }
        .brand-header .border-slate-700, .brand-header .border-slate-700\/60 { border-color: rgba(255,255,255,0.14); }
    </style>
</head>
<body class="h-full text-slate-600 antialiased flex flex-col font-sans">

    <!-- Flash Notifications Toast -->
    <?php if ($flash): ?>
        <div id="flashNotification" class="no-print fixed top-4 right-4 z-50 flex items-center p-4 mb-4 rounded-2xl shadow-2xl transition-all duration-500 ease-in-out transform translate-y-0 <?= $flash['type'] === 'success' ? 'bg-emerald-600 text-white' : ($flash['type'] === 'error' ? 'bg-rose-600 text-white' : 'bg-slate-900 text-white') ?>">
            <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-white/20 mr-3">
                <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : ($flash['type'] === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-info') ?>"></i>
            </div>
            <div class="text-xs font-semibold mr-4"><?= htmlspecialchars($flash['message']) ?></div>
            <button type="button" onclick="document.getElementById('flashNotification').remove()" class="ml-auto -mx-1.5 -my-1.5 rounded-lg p-1.5 inline-flex h-8 w-8 text-white hover:bg-white/10">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <script>
            setTimeout(() => {
                const toast = document.getElementById('flashNotification');
                if (toast) {
                    toast.classList.add('opacity-0', '-translate-y-2');
                    setTimeout(() => toast.remove(), 500);
                }
            }, 4000);
        </script>
    <?php endif; ?>

    <!-- Master Header Bar -->
    <header class="no-print brand-header text-white shadow-lg sticky top-0 z-40 border-b border-blue-600/40">
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between">

            <!-- Mobile Menu Toggle -->
            <button type="button" onclick="toggleMobileSidebar(true)" aria-label="Open navigation menu" aria-controls="mobileSidebar" aria-expanded="false" class="md:hidden flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-xl bg-slate-800 hover:bg-slate-700 active:bg-slate-600 text-white border border-slate-700 transition">
                <i class="fa-solid fa-bars text-base"></i>
            </button>

            <!-- Mobile Logo: absolutely centered in the header bar -->
            <a href="index.php?page=operations" class="md:hidden absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 flex items-center justify-center overflow-hidden h-10 w-32">
                <img src="assets/img/logo-dark.png" alt="Agency Logo" class="h-full w-full object-contain" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'bg-amber-500 text-slate-950 font-black px-2 py-1 rounded text-sm\'>AHT</div>';">
            </a>

            <!-- Desktop Agency Logo & Branding -->
            <a href="index.php?page=operations" class="hidden md:flex items-center space-x-3 group min-w-0">
                <div class="group-hover:scale-105 transition transform flex items-center justify-center overflow-hidden h-12 w-40 flex-shrink-0">
                    <img src="assets/img/logo-dark.png" alt="Agency Logo" class="h-full w-full object-contain" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'bg-amber-500 text-slate-950 font-black px-2 py-1 rounded text-sm\'>AHT</div>';">
                </div>
                <div class="min-w-0">
                    <div class="flex items-center space-x-2">
                        <h1 class="font-black text-lg leading-tight tracking-tight truncate"><?= htmlspecialchars($agencyName) ?></h1>
                        <span class="inline-flex text-[10px] font-bold px-2 py-0.5 rounded-full uppercase btn-gold">CRM</span>
                    </div>
                    <p class="text-xs text-slate-400 truncate">Your Trust Is Our Best Reward · Umrah, Visa & Travel CRM</p>
                </div>
            </a>

            <!-- Header Tools -->
            <div class="flex items-center space-x-1.5 sm:space-x-3 flex-shrink-0">
                <!-- System Status -->
                <div class="hidden lg:flex items-center space-x-2 bg-slate-800/80 px-3 py-1.5 rounded-xl border border-slate-700/60 text-xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="text-slate-300 font-medium">Office: +92 303 5137777</span>
                </div>

                <!-- Live System Clock -->
                <div class="hidden sm:flex bg-slate-800 text-slate-200 px-2 sm:px-3.5 py-1.5 rounded-xl border border-slate-700 text-[11px] sm:text-xs font-mono font-semibold items-center shadow-inner">
                    <i class="fa-regular fa-clock mr-1.5 sm:mr-2 text-amber-400"></i>
                    <span id="globalClockDisplay">--:--:--</span>
                </div>

                <!-- Financial Security Lock Status -->
                <?php if (Session::isFinancialUnlocked()): ?>
                    <a href="index.php?api=lock_financial" title="Financial Section Unlocked - Click to Lock" class="bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30 border border-emerald-500/40 text-xs font-semibold px-2 sm:px-3 py-1.5 rounded-xl transition flex items-center">
                        <i class="fa-solid fa-lock-open sm:mr-1.5 text-emerald-400"></i> <span class="hidden sm:inline">Unlocked</span>
                    </a>
                <?php else: ?>
                    <a href="index.php?page=financials" title="Financial Section Locked" class="bg-slate-800 text-slate-400 hover:text-white border border-slate-700 text-xs font-semibold px-2 sm:px-3 py-1.5 rounded-xl transition flex items-center">
                        <i class="fa-solid fa-lock sm:mr-1.5 text-amber-500"></i> <span class="hidden sm:inline">Protected</span>
                    </a>
                <?php endif; ?>

                <!-- Signed-in User & Logout -->
                <?php $currentUser = Auth::user(); if ($currentUser): ?>
                    <a href="index.php?api=logout" title="Sign Out" class="sm:hidden flex items-center justify-center h-9 w-9 flex-shrink-0 rounded-xl bg-slate-800 text-slate-300 hover:text-white hover:bg-rose-600 border border-slate-700 transition">
                        <i class="fa-solid fa-right-from-bracket text-sm"></i>
                    </a>
                    <div class="hidden sm:flex items-center bg-slate-800 text-slate-200 pl-3 pr-1.5 py-1.5 rounded-xl border border-slate-700 text-xs font-semibold">
                        <i class="fa-solid fa-user-circle mr-2 text-amber-400"></i>
                        <span class="mr-2"><?= htmlspecialchars($currentUser['full_name'] ?: $currentUser['username']) ?></span>
                        <a href="index.php?api=logout" title="Sign Out" class="ml-1 bg-slate-700 hover:bg-rose-600 text-slate-300 hover:text-white px-2 py-1 rounded-lg transition">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Mobile Sidebar Backdrop -->
    <div id="sidebarBackdrop" onclick="toggleMobileSidebar(false)" class="no-print hidden md:hidden fixed inset-0 bg-slate-900/60 z-40" aria-hidden="true"></div>

    <!-- Master Layout Body Wrapper -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex-1 w-full grid grid-cols-1 md:grid-cols-12 gap-6">
