<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/UserController.php';
require_once __DIR__ . '/../../config/Auth.php';

$teamUsers  = UserController::getAll();
$currentUid = (int)(Auth::user()['id'] ?? 0);

$roleLabels = [
    'admin'       => ['label' => 'Admin', 'badge' => 'bg-amber-100 text-amber-800 border-amber-200'],
    'full_access' => ['label' => 'Full Access', 'badge' => 'bg-emerald-100 text-emerald-800 border-emerald-200'],
    'view_only'   => ['label' => 'View Only', 'badge' => 'bg-slate-100 text-slate-600 border-slate-200'],
];
?>

<main class="md:col-span-9 space-y-6">

    <!-- Top Banner -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-users-gear text-slate-700 mr-2"></i> Team Login Accounts
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Add, edit, or remove login access for your team. Only Admins can manage this page.</p>
        </div>
        <button onclick="openNewUserModal()" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-2.5 rounded-xl transition shadow-sm flex items-center justify-center">
            <i class="fa-solid fa-user-plus mr-1.5"></i> Add Team Login
        </button>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="bg-slate-50 px-5 py-3.5 border-b border-slate-200 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-xs uppercase tracking-wider flex items-center">
                <i class="fa-solid fa-key mr-2 text-blue-600"></i> Active & Inactive Logins
            </h3>
            <span class="text-xs font-bold text-slate-500"><?= count($teamUsers) ?> Accounts</span>
        </div>
        <div class="p-2 overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="text-slate-400 uppercase text-[10px] font-bold border-b">
                    <tr>
                        <th class="p-2.5">Username</th>
                        <th class="p-2.5">Full Name</th>
                        <th class="p-2.5">Email</th>
                        <th class="p-2.5">Role</th>
                        <th class="p-2.5">Status</th>
                        <th class="p-2.5">Last Login</th>
                        <th class="p-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($teamUsers as $u): $rl = $roleLabels[$u['role']] ?? $roleLabels['view_only']; ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-2.5 font-bold text-slate-800 font-mono"><?= htmlspecialchars($u['username']) ?></td>
                            <td class="p-2.5 text-slate-600"><?= htmlspecialchars($u['full_name'] ?: '-') ?></td>
                            <td class="p-2.5 text-slate-600"><?= htmlspecialchars($u['email'] ?: '-') ?></td>
                            <td class="p-2.5">
                                <span class="text-[10px] font-bold px-2 py-1 rounded-full border <?= $rl['badge'] ?>"><?= $rl['label'] ?></span>
                            </td>
                            <td class="p-2.5">
                                <?php if ((int)$u['is_active'] === 1): ?>
                                    <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">Active</span>
                                <?php else: ?>
                                    <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-rose-100 text-rose-700">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-2.5 text-slate-500 font-mono"><?= $u['last_login_at'] ? htmlspecialchars($u['last_login_at']) : 'Never' ?></td>
                            <td class="p-2.5 text-right whitespace-nowrap">
                                <button onclick='editUser(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="text-blue-600 hover:text-blue-800 mr-2" title="Edit Login">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <?php if ((int)$u['id'] !== $currentUid): ?>
                                    <button onclick="deleteUser(<?= (int)$u['id'] ?>)" class="text-rose-500 hover:text-rose-700" title="Delete Login">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-slate-300" title="This is your own account"><i class="fa-solid fa-trash-can"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
function openNewUserModal() {
    const modal = document.getElementById('dynamicModalContainer');
    modal.innerHTML = `
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="font-bold text-slate-800 text-sm" id="user_modal_title">Add Team Login</h3>
                    <button onclick="closeActiveModal()" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <form onsubmit="submitUserForm(event)" class="space-y-3 text-xs">
                    <input type="hidden" id="user_modal_id" value="">
                    <div>
                        <label class="block font-semibold mb-1">Username</label>
                        <input type="text" id="user_modal_username" required placeholder="Username" pattern="[a-zA-Z0-9_.]{3,50}" class="w-full border rounded-xl p-2.5 font-mono">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Full Name</label>
                        <input type="text" id="user_modal_fullname" placeholder="Full name" class="w-full border rounded-xl p-2.5">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Email (sign-in + password-reset codes)</label>
                        <input type="email" id="user_modal_email" placeholder="name@example.com" class="w-full border rounded-xl p-2.5">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Access Level</label>
                        <select id="user_modal_role" class="w-full border rounded-xl p-2.5">
                            <option value="full_access">Full Access (create, edit, delete)</option>
                            <option value="view_only">View Only (read-only)</option>
                            <option value="admin">Admin (full access + manage logins)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1" id="user_modal_pw_label">Password</label>
                        <input type="password" id="user_modal_password" placeholder="Minimum 8 characters" class="w-full border rounded-xl p-2.5">
                        <p class="text-[10px] text-slate-400 mt-1" id="user_modal_pw_hint">Leave blank when editing to keep the current password.</p>
                    </div>
                    <label class="flex items-center gap-2 font-semibold select-none cursor-pointer">
                        <input type="checkbox" id="user_modal_active" checked class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        Account Active
                    </label>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl transition mt-2">
                        Save Login Account
                    </button>
                </form>
            </div>
        </div>
    `;
}

function editUser(user) {
    openNewUserModal();
    document.getElementById('user_modal_title').textContent = 'Edit Team Login';
    document.getElementById('user_modal_id').value = user.id;
    document.getElementById('user_modal_username').value = user.username;
    document.getElementById('user_modal_fullname').value = user.full_name || '';
    document.getElementById('user_modal_email').value = user.email || '';
    document.getElementById('user_modal_role').value = user.role;
    document.getElementById('user_modal_active').checked = Number(user.is_active) === 1;
    document.getElementById('user_modal_pw_label').textContent = 'New Password (optional)';
}

async function submitUserForm(e) {
    e.preventDefault();
    const payload = {
        id: document.getElementById('user_modal_id').value,
        username: document.getElementById('user_modal_username').value,
        full_name: document.getElementById('user_modal_fullname').value,
        email: document.getElementById('user_modal_email').value,
        role: document.getElementById('user_modal_role').value,
        password: document.getElementById('user_modal_password').value,
        is_active: document.getElementById('user_modal_active').checked ? 1 : 0
    };

    const res = await fetch('index.php?api=save_user', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        alert(data.message || 'Error saving login account');
    }
}

async function deleteUser(id) {
    if (!confirm('Remove this login account? The team member will no longer be able to sign in.')) return;
    const res = await fetch('index.php?api=delete_user', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        alert(data.message || 'Error removing login account');
    }
}
</script>
