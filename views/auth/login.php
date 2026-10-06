<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/Session.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../config/Auth.php';

Session::start();

// Already logged in (or remember-me cookie still valid) - skip straight to the app.
if (Auth::check()) {
    header('Location: index.php?page=operations');
    exit;
}

$csrfToken      = Session::getCsrfToken();
$agencyName     = Database::fetchValue("SELECT setting_value FROM system_settings WHERE setting_key = 'agency_name'") ?: 'Aeroheights Travels & Tours';
$loginError     = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($agencyName) ?> | CRM Login</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="assets/js/brand-tailwind.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Jost', ui-sans-serif, system-ui, sans-serif; color: #4A5170; background: radial-gradient(900px 500px at 12% -8%, rgba(55, 212, 217, .22) 0%, transparent 60%), linear-gradient(125deg, #161A35 0%, #26206F 55%, #285A9B 100%); background-attachment: fixed; }
        .field { width: 100%; color: #161A35; border: 1px solid #DCE5ED; border-radius: 0.75rem; padding: 0.65rem 0.9rem 0.65rem 2.5rem; font-size: 0.875rem; outline: none; background: #F6F8FC; transition: border-color .15s, box-shadow .15s, background .15s; }
        .field:focus { border-color: #2183DF; box-shadow: 0 0 0 3px #2183DF33; background: #fff; }
        .field-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: #8A90A8; font-size: 0.85rem; }
        .btn-primary { width: 100%; background: linear-gradient(90deg, #FEC624 0%, #FFD95E 100%); color: #161A35; font-weight: 700; padding: 0.7rem; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; transition: filter .15s, box-shadow .15s; box-shadow: 0 6px 18px rgba(254, 198, 36, .28); }
        .btn-primary:hover { filter: brightness(1.04); box-shadow: 0 8px 22px rgba(254, 198, 36, .38); }
        .eyebrow { color: #37D4D9; }
        .msg-error { background: #FDF0F2; color: #E0475B; border-color: #F6BAC2; }
        .msg-ok { background: #EDF8FA; color: #2F6A7B; border-color: #A9E3EA; }
        .btn-primary:disabled { opacity: .7; cursor: wait; }
    </style>
</head>
<body class="min-h-full flex items-center justify-center p-4">

    <div class="w-full max-w-sm">
        <div class="flex flex-col items-center mb-6">
            <img src="assets/img/logo-dark.png" alt="<?= htmlspecialchars($agencyName) ?>" class="h-20 w-auto object-contain mb-3" onerror="this.outerHTML='<div class=\'text-white font-black text-2xl\'>AEROHEIGHTS</div>';">
            <p class="eyebrow text-xs tracking-widest uppercase font-semibold">Travel CRM &middot; Secure Team Login</p>
        </div>

        <div class="bg-white rounded-3xl shadow-2xl p-6 sm:p-7 border-t-4" style="border-top-color: #FEC624;">
            <div id="msgBox" class="hidden mb-4 rounded-xl px-4 py-2.5 text-xs font-semibold flex items-start">
                <i id="msgIcon" class="fa-solid fa-triangle-exclamation mr-2 mt-0.5"></i>
                <span id="msgText"></span>
            </div>

            <!-- Step 1: Sign in -->
            <form id="loginForm" class="space-y-4">
                <h2 class="text-slate-900 font-bold text-lg">Sign in</h2>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Email or Username</label>
                    <div class="relative">
                        <i class="fa-solid fa-envelope field-icon"></i>
                        <input type="text" id="login_username" autocomplete="username" required autofocus class="field">
                    </div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold text-slate-700">Password</label>
                        <button type="button" onclick="showStep('forgot')" class="text-[11px] font-semibold text-blue-600 hover:text-indigo-700">Forgot password?</button>
                    </div>
                    <div class="relative">
                        <i class="fa-solid fa-lock field-icon"></i>
                        <input type="password" id="login_password" autocomplete="current-password" required class="field" style="padding-right: 2.5rem;">
                        <button type="button" onclick="togglePasswordVisibility('login_password', this)" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" aria-label="Show password">
                            <i class="fa-solid fa-eye text-sm"></i>
                        </button>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-xs font-medium text-slate-600 select-none cursor-pointer">
                    <input type="checkbox" id="login_remember" checked class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    Keep me signed in on this device
                </label>

                <button type="submit" id="loginSubmitBtn" class="btn-primary">
                    <i class="fa-solid fa-right-to-bracket mr-2"></i> Sign In
                </button>
            </form>

            <!-- Step 2: Ask for an emailed code -->
            <form id="forgotForm" class="space-y-4 hidden">
                <h2 class="text-slate-900 font-bold text-lg">Reset password</h2>
                <p class="text-xs text-slate-500">Enter your account email or username. We'll email you a 6-digit code to create a new password.</p>
                <div class="relative">
                    <i class="fa-solid fa-envelope field-icon"></i>
                    <input type="text" id="forgot_login" autocomplete="username" required placeholder="Email or username" class="field">
                </div>
                <button type="submit" id="forgotBtn" class="btn-primary"><i class="fa-solid fa-paper-plane mr-2"></i> Email me a code</button>
                <button type="button" onclick="showStep('login')" class="w-full text-xs font-semibold text-slate-500 hover:text-slate-800"><i class="fa-solid fa-arrow-left mr-1"></i> Back to sign in</button>
            </form>

            <!-- Step 3: Code + new password -->
            <form id="resetForm" class="space-y-4 hidden">
                <h2 class="text-slate-900 font-bold text-lg">Create new password</h2>
                <p class="text-xs text-slate-500">Check your email inbox (and spam folder) for the 6-digit code.</p>
                <div class="relative">
                    <i class="fa-solid fa-shield-halved field-icon"></i>
                    <input type="text" id="reset_code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required placeholder="6-digit code" class="field tracking-[0.4em] font-bold">
                </div>
                <div class="relative">
                    <i class="fa-solid fa-lock field-icon"></i>
                    <input type="password" id="reset_password" autocomplete="new-password" minlength="8" required placeholder="New password (min 8 characters)" class="field">
                </div>
                <div class="relative">
                    <i class="fa-solid fa-lock field-icon"></i>
                    <input type="password" id="reset_confirm" autocomplete="new-password" minlength="8" required placeholder="Confirm new password" class="field">
                </div>
                <button type="submit" id="resetBtn" class="btn-primary"><i class="fa-solid fa-check mr-2"></i> Save new password</button>
                <div class="flex justify-between text-xs font-semibold">
                    <button type="button" onclick="showStep('login')" class="text-slate-500 hover:text-slate-800"><i class="fa-solid fa-arrow-left mr-1"></i> Sign in</button>
                    <button type="button" onclick="showStep('forgot')" class="text-blue-600 hover:text-indigo-700">Send a new code</button>
                </div>
            </form>
        </div>

        <p class="text-center text-[11px] mt-5" style="color: #8A90A8;">
            <i class="fa-solid fa-shield-halved mr-1"></i> Authorized team members only &middot;
            <a href="https://aeroheightstravels.com/" class="hover:text-white">aeroheightstravels.com</a>
        </p>
    </div>

    <script>
        const CSRF = document.querySelector('meta[name="csrf-token"]').content;
        let resetLogin = '';

        function togglePasswordVisibility(id, btn) {
            const input = document.getElementById(id);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.querySelector('i').classList.toggle('fa-eye', !show);
            btn.querySelector('i').classList.toggle('fa-eye-slash', show);
        }

        function showMessage(message, ok) {
            const box = document.getElementById('msgBox');
            box.className = 'mb-4 rounded-xl px-4 py-2.5 text-xs font-semibold flex items-start border ' + (ok ? 'msg-ok' : 'msg-error');
            document.getElementById('msgIcon').className = 'fa-solid mr-2 mt-0.5 ' + (ok ? 'fa-circle-check' : 'fa-triangle-exclamation');
            document.getElementById('msgText').textContent = message;
        }
        function hideMessage() { document.getElementById('msgBox').classList.add('hidden'); }

        function showStep(step) {
            hideMessage();
            ['login', 'forgot', 'reset'].forEach(s => document.getElementById(s + 'Form').classList.toggle('hidden', s !== step));
            if (step === 'forgot') {
                const f = document.getElementById('forgot_login');
                if (!f.value) f.value = document.getElementById('login_username').value;
                f.focus();
            }
        }

        async function post(api, body) {
            const res = await fetch('index.php?api=' + api, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify(body)
            });
            return res.json();
        }

        function busy(btn, on, label) {
            btn.disabled = on;
            if (on) { btn.dataset.label = btn.innerHTML; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> ' + label; }
            else if (btn.dataset.label) { btn.innerHTML = btn.dataset.label; }
        }

        <?php if ($loginError === 'session'): ?>
        showMessage('Your session has expired. Please sign in again.', false);
        <?php endif; ?>

        document.getElementById('loginForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('loginSubmitBtn');
            busy(btn, true, 'Signing in...');
            try {
                const data = await post('login', {
                    username: document.getElementById('login_username').value,
                    password: document.getElementById('login_password').value,
                    remember: document.getElementById('login_remember').checked
                });
                if (data.success) { window.location.href = 'index.php?page=operations'; return; }
                showMessage(data.message || 'Invalid email / username or password.', false);
            } catch (err) {
                showMessage('Unable to reach the server. Please try again.', false);
            }
            busy(btn, false);
        });

        document.getElementById('forgotForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('forgotBtn');
            resetLogin = document.getElementById('forgot_login').value.trim();
            busy(btn, true, 'Sending...');
            try {
                const data = await post('request_password_otp', { login: resetLogin });
                if (data.success) {
                    showStep('reset');
                    showMessage(data.message, true);
                    document.getElementById('reset_code').focus();
                } else {
                    showMessage(data.message || 'Could not send the code.', false);
                }
            } catch (err) {
                showMessage('Unable to reach the server. Please try again.', false);
            }
            busy(btn, false);
        });

        document.getElementById('resetForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('resetBtn');
            busy(btn, true, 'Saving...');
            try {
                const data = await post('reset_password_otp', {
                    login: resetLogin,
                    code: document.getElementById('reset_code').value,
                    password: document.getElementById('reset_password').value,
                    confirm: document.getElementById('reset_confirm').value
                });
                if (data.success) {
                    document.getElementById('resetForm').reset();
                    document.getElementById('login_username').value = resetLogin;
                    showStep('login');
                    showMessage(data.message, true);
                    document.getElementById('login_password').focus();
                } else {
                    showMessage(data.message || 'Could not reset the password.', false);
                }
            } catch (err) {
                showMessage('Unable to reach the server. Please try again.', false);
            }
            busy(btn, false);
        });
    </script>
</body>
</html>
