<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/Auth.php';

$me = Database::fetchOne("SELECT id, username, email, full_name, role FROM users WHERE id = ?", [(int)(Auth::user()['id'] ?? 0)]) ?? [];
$maskedEmail = preg_replace_callback('/^(.)(.*)(@.*)$/', static fn($m) => $m[1] . str_repeat('*', max(1, strlen($m[2]))) . $m[3], (string)($me['email'] ?? ''));
$roleLabels = ['admin' => 'Owner / Admin', 'full_access' => 'Full Access', 'view_only' => 'View Only'];
?>

<main class="md:col-span-9 space-y-6">

    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
        <h2 class="text-base font-bold text-slate-800 flex items-center">
            <i class="fa-solid fa-user-shield text-indigo-600 mr-2"></i> My Account & Password
        </h2>
        <p class="text-xs text-slate-500 mt-0.5">Signed in as <strong><?= htmlspecialchars($me['username'] ?? '') ?></strong> &middot; <?= htmlspecialchars($roleLabels[$me['role'] ?? ''] ?? '') ?></p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Account details -->
        <form id="profileForm" class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4 text-xs">
            <h3 class="font-bold text-slate-800 text-sm flex items-center"><i class="fa-solid fa-id-card text-blue-600 mr-2"></i> Account Details</h3>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Full Name</label>
                <input type="text" id="pf_full_name" value="<?= htmlspecialchars($me['full_name'] ?? '') ?>" class="w-full border rounded-xl p-2.5">
            </div>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Username</label>
                <input type="text" id="pf_username" value="<?= htmlspecialchars($me['username'] ?? '') ?>" required class="w-full border rounded-xl p-2.5 font-mono">
            </div>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Email (login + password-reset codes)</label>
                <input type="email" id="pf_email" value="<?= htmlspecialchars($me['email'] ?? '') ?>" required class="w-full border rounded-xl p-2.5">
            </div>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Current Password (to confirm)</label>
                <input type="password" id="pf_current" autocomplete="current-password" required class="w-full border rounded-xl p-2.5">
            </div>
            <div class="flex justify-end">
                <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white font-semibold px-6 py-2.5 rounded-xl transition shadow-sm">Save Details</button>
            </div>
        </form>

        <!-- Change password by emailed code -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4 text-xs">
            <h3 class="font-bold text-slate-800 text-sm flex items-center"><i class="fa-solid fa-key text-amber-500 mr-2"></i> Change Password</h3>
            <?php if (empty($me['email'])): ?>
                <p class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-3 font-semibold">Add an email address under Account Details first &mdash; the code is sent there.</p>
            <?php else: ?>
                <p class="text-slate-500">For security a 6-digit code is emailed to <strong class="text-slate-700"><?= htmlspecialchars($maskedEmail) ?></strong>. Enter it below with your new password. All other signed-in devices are logged out afterwards.</p>
                <button type="button" id="otpBtn" class="w-full bg-blue-600 hover:bg-indigo-600 text-white font-bold py-2.5 rounded-xl transition shadow-sm"><i class="fa-solid fa-paper-plane mr-1.5"></i> Email me a code</button>
                <form id="pwForm" class="space-y-3 hidden">
                    <input type="text" id="pw_code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required placeholder="6-digit code" class="w-full border rounded-xl p-2.5 tracking-[0.4em] font-bold">
                    <input type="password" id="pw_new" autocomplete="new-password" minlength="8" required placeholder="New password (min 8 characters)" class="w-full border rounded-xl p-2.5">
                    <input type="password" id="pw_confirm" autocomplete="new-password" minlength="8" required placeholder="Confirm new password" class="w-full border rounded-xl p-2.5">
                    <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-semibold py-2.5 rounded-xl transition shadow-sm">Save New Password</button>
                </form>
            <?php endif; ?>
            <div id="pwMsg" class="hidden rounded-xl p-3 font-semibold border"></div>
        </div>
    </div>
</main>

<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const post = (api, body) => fetch('index.php?api=' + api, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(body || {})
    }).then(r => r.json());
    const msg = (text, ok) => {
        const el = document.getElementById('pwMsg');
        el.textContent = text;
        el.className = 'rounded-xl p-3 font-semibold border ' + (ok ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200');
    };

    document.getElementById('profileForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = await post('update_profile', {
            full_name: document.getElementById('pf_full_name').value,
            username: document.getElementById('pf_username').value,
            email: document.getElementById('pf_email').value,
            current_password: document.getElementById('pf_current').value
        });
        alert(data.message || 'Saved.');
        if (data.success) window.location.reload();
    });

    const otpBtn = document.getElementById('otpBtn');
    if (otpBtn) otpBtn.addEventListener('click', async () => {
        otpBtn.disabled = true;
        try {
            const data = await post('request_my_otp');
            msg(data.message, data.success);
            if (data.success) { document.getElementById('pwForm').classList.remove('hidden'); document.getElementById('pw_code').focus(); }
        } catch (err) { msg('Unable to reach the server. Please try again.', false); }
        otpBtn.disabled = false;
    });

    const pwForm = document.getElementById('pwForm');
    if (pwForm) pwForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = await post('reset_my_password', {
            code: document.getElementById('pw_code').value,
            password: document.getElementById('pw_new').value,
            confirm: document.getElementById('pw_confirm').value
        });
        msg(data.success ? 'Password changed successfully. Use the new password next time you sign in.' : data.message, data.success);
        if (data.success) { pwForm.reset(); pwForm.classList.add('hidden'); }
    });
})();
</script>
