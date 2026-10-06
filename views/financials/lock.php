<?php
declare(strict_types=1);

// Shown instead of Agent Ledgers / Vendor Payables / Buy & Sell Reports until the owner unlocks them.
$lockTitles = [
    'agent_ledger'  => ['Agent Ledgers (B2B)', 'fa-file-invoice-dollar'],
    'vendor_ledger' => ['Vendor Payables', 'fa-handshake'],
    'financials'    => ['Weekly & Monthly Buy / Sell Reports', 'fa-chart-column'],
];
[$lockTitle, $lockIcon] = $lockTitles[$_GET['page'] ?? 'financials'] ?? $lockTitles['financials'];
?>

<main class="md:col-span-9 space-y-6">
    <div class="bg-white p-8 sm:p-10 rounded-3xl border border-slate-200/80 shadow-xl text-center max-w-md mx-auto space-y-6 my-10">
        <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto text-2xl border border-indigo-100 shadow-inner">
            <i class="fa-solid <?= $lockIcon ?>"></i>
        </div>
        <div>
            <h3 class="font-extrabold text-slate-900 text-lg"><?= htmlspecialchars($lockTitle) ?></h3>
            <p class="text-xs text-slate-500 mt-1">This section is protected. Enter the owner's email and password to open Agent Ledgers, Vendor Payables and the Buy / Sell reports. It locks again after 30 minutes of inactivity.</p>
        </div>

        <div id="unlockError" class="hidden bg-rose-50 text-rose-700 border border-rose-200 rounded-xl px-4 py-2.5 text-xs font-semibold"></div>

        <form id="unlockForm" class="space-y-3 text-left">
            <div class="relative">
                <i class="fa-solid fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" id="unlock_login" autocomplete="username" required autofocus placeholder="Owner email or username"
                       class="w-full border border-slate-300 rounded-xl py-2.5 pl-10 pr-3.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>
            <div class="relative">
                <i class="fa-solid fa-key absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="password" id="unlock_password" autocomplete="current-password" required placeholder="Password"
                       class="w-full border border-slate-300 rounded-xl py-2.5 pl-10 pr-3.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>
            <button type="submit" id="unlockBtn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs py-3.5 rounded-xl transition shadow-md">
                <i class="fa-solid fa-lock-open mr-1.5 text-amber-400"></i> Unlock
            </button>
        </form>
        <a href="index.php?page=profile" class="inline-block text-[11px] font-semibold text-blue-600 hover:underline">Forgot the password? Reset it with an email code</a>
    </div>
</main>

<script>
document.getElementById('unlockForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('unlockBtn');
    const box = document.getElementById('unlockError');
    btn.disabled = true;
    try {
        const res = await fetch('index.php?api=unlock_financial', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ login: document.getElementById('unlock_login').value, password: document.getElementById('unlock_password').value })
        });
        const data = await res.json();
        if (data.success) { window.location.reload(); return; }
        box.textContent = data.message || 'Access denied.';
    } catch (err) {
        box.textContent = 'Unable to reach the server. Please try again.';
    }
    box.classList.remove('hidden');
    btn.disabled = false;
});
</script>
