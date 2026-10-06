<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/AdminController.php';

$agents   = AdminController::getAgents();
$vendors  = AdminController::getVendors();
$banks    = AdminController::getBankAccounts();
$settings = AdminController::getSettings();
?>

<main class="md:col-span-9 space-y-6">

    <!-- Admin Top Banner -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-sliders text-slate-700 mr-2"></i> Administrative Control Center
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Manage B2B Clients, Suppliers, Bank Accounts, Helplines and Team WhatsApp numbers.</p>
        </div>
        <div class="flex gap-2 w-full sm:w-auto">
            <button onclick="openNewAgentModal()" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-2.5 rounded-xl transition shadow-sm flex items-center justify-center">
                <i class="fa-solid fa-user-plus mr-1.5"></i> Add Agent
            </button>
            <button onclick="openNewVendorModal()" class="bg-cyan-700 hover:bg-cyan-800 text-white text-xs font-semibold px-4 py-2.5 rounded-xl transition shadow-sm flex items-center justify-center">
                <i class="fa-solid fa-handshake mr-1.5"></i> Add Supplier
            </button>
        </div>
    </div>

    <!-- 1. AGENTS & VENDORS MANAGEMENT -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- B2B Agents Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col justify-between">
            <div>
                <div class="bg-slate-50 px-5 py-3.5 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="font-bold text-slate-800 text-xs uppercase tracking-wider flex items-center">
                        <i class="fa-solid fa-users mr-2 text-blue-600"></i> Registered B2B Travel Agents
                    </h3>
                    <span class="text-xs font-bold text-slate-500"><?= count($agents) ?> Agents</span>
                </div>
                <div class="p-2 overflow-x-auto max-h-80 overflow-y-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-400 uppercase text-[10px] font-bold border-b">
                            <tr>
                                <th class="p-2.5">Agent Name</th>
                                <th class="p-2.5">Company / City</th>
                                <th class="p-2.5">Phone</th>
                                <th class="p-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($agents as $a): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="p-2.5 font-bold text-slate-800"><?= htmlspecialchars($a['name']) ?></td>
                                    <td class="p-2.5 text-slate-500"><?= htmlspecialchars($a['company_name'] ?: '-') ?> (<?= htmlspecialchars($a['city']) ?>)</td>
                                    <td class="p-2.5 font-mono text-slate-600"><?= htmlspecialchars($a['phone']) ?></td>
                                    <td class="p-2.5 text-right">
                                        <button onclick="editAgent(<?= htmlspecialchars(json_encode($a)) ?>)" class="text-blue-600 hover:text-blue-800 mr-2" title="Edit Agent">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button onclick="deleteAgent(<?= $a['id'] ?>)" class="text-rose-500 hover:text-rose-700" title="Delete Agent">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Suppliers & Vendors Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col justify-between">
            <div>
                <div class="bg-slate-50 px-5 py-3.5 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="font-bold text-slate-800 text-xs uppercase tracking-wider flex items-center">
                        <i class="fa-solid fa-handshake mr-2 text-cyan-600"></i> Registered Suppliers & Vendors
                    </h3>
                    <span class="text-xs font-bold text-slate-500"><?= count($vendors) ?> Vendors</span>
                </div>
                <div class="p-2 overflow-x-auto max-h-80 overflow-y-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-400 uppercase text-[10px] font-bold border-b">
                            <tr>
                                <th class="p-2.5">Vendor Name</th>
                                <th class="p-2.5">Service / Country</th>
                                <th class="p-2.5">Phone</th>
                                <th class="p-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($vendors as $v): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="p-2.5 font-bold text-slate-800"><?= htmlspecialchars($v['name']) ?></td>
                                    <td class="p-2.5 text-slate-500"><?= htmlspecialchars($v['service_type']) ?> (<?= htmlspecialchars($v['country']) ?>)</td>
                                    <td class="p-2.5 font-mono text-slate-600"><?= htmlspecialchars($v['phone']) ?></td>
                                    <td class="p-2.5 text-right">
                                        <button onclick="editVendor(<?= htmlspecialchars(json_encode($v)) ?>)" class="text-cyan-600 hover:text-cyan-800 mr-2" title="Edit Vendor">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button onclick="deleteVendor(<?= $v['id'] ?>)" class="text-rose-500 hover:text-rose-700" title="Delete Vendor">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- 2. GLOBAL SYSTEM CONFIGURATION & FINANCIAL SETTINGS -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-6">
        <div class="border-b pb-3">
            <h3 class="font-bold text-slate-800 text-sm flex items-center">
                <i class="fa-solid fa-gear text-amber-500 mr-2"></i> Agency Configuration, Logo & Currency Rates
            </h3>
        </div>

        <form onsubmit="handleSettingsUpdate(event)" class="space-y-4 text-xs">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Agency Name</label>
                    <input type="text" id="set_agency_name" value="<?= htmlspecialchars($settings['agency_name'] ?? 'Aeroheights Travels & Tours') ?>" required class="w-full border rounded-xl p-2.5">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Agency Logo Path</label>
                    <input type="text" id="set_agency_logo" value="<?= htmlspecialchars($settings['agency_logo'] ?? 'assets/img/logo.png') ?>" required class="w-full border rounded-xl p-2.5 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Default Rate (PKR / SAR)</label>
                    <input type="number" step="0.01" id="set_exchange_rate" value="<?= htmlspecialchars($settings['default_exchange_rate'] ?? '76.00') ?>" required class="w-full border rounded-xl p-2.5 font-bold text-emerald-700">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Agency Phone / UAN</label>
                    <input type="text" id="set_agency_phone" value="<?= htmlspecialchars($settings['agency_phone'] ?? '') ?>" placeholder="+92 300 0000000" class="w-full border rounded-xl p-2.5 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Agency Email</label>
                    <input type="email" id="set_agency_email" value="<?= htmlspecialchars($settings['agency_email'] ?? 'aeroheights2024@gmail.com') ?>" class="w-full border rounded-xl p-2.5">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Agency Physical Address</label>
                    <input type="text" id="set_agency_address" value="<?= htmlspecialchars($settings['agency_address'] ?? 'Pakistan') ?>" class="w-full border rounded-xl p-2.5">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Official Office No. (vouchers)</label>
                    <input type="text" id="set_makkah_helpline" value="<?= htmlspecialchars($settings['makkah_helpline'] ?? '+92 303 5137777') ?>" required class="w-full border rounded-xl p-2.5 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Secondary Helpline (vouchers only)</label>
                    <input type="text" id="set_madinah_helpline" value="<?= htmlspecialchars($settings['madinah_helpline'] ?? '+92 303 4512512') ?>" required class="w-full border rounded-xl p-2.5 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Makkah Team WhatsApp</label>
                    <input type="text" id="set_makkah_team_whatsapp" value="<?= htmlspecialchars($settings['makkah_team_whatsapp'] ?? '+92 303 5137777') ?>" class="w-full border rounded-xl p-2.5 font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Madinah Team WhatsApp</label>
                    <input type="text" id="set_madinah_team_whatsapp" value="<?= htmlspecialchars($settings['madinah_team_whatsapp'] ?? '+92 303 5137777') ?>" class="w-full border rounded-xl p-2.5 font-mono">
                    <p class="text-[10px] text-slate-400 mt-1">Used by the Operations Manifest "Send" buttons (official number handles Makkah &amp; Madinah).</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-semibold text-slate-700 mb-1">Bank Details (printed on agent statements, one account per line)</label>
                    <textarea id="set_bank_details" rows="3" placeholder="Bank Name: Aeroheights Travels & Tours | Account: ... | IBAN: ..." class="w-full border rounded-xl p-2.5 font-mono"><?= htmlspecialchars($settings['bank_details'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white font-semibold px-6 py-2.5 rounded-xl text-xs transition shadow-sm">
                    Save System Settings
                </button>
            </div>
        </form>
    </div>

</main>

<script>
function openNewAgentModal() {
    const modal = document.getElementById('dynamicModalContainer');
    modal.innerHTML = `
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="font-bold text-slate-800 text-sm">Register B2B Agent</h3>
                    <button onclick="closeActiveModal()" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <form onsubmit="submitAgentForm(event)" class="space-y-3 text-xs">
                    <input type="hidden" id="agent_modal_id" value="">
                    <div>
                        <label class="block font-semibold mb-1">Agent / Contact Name</label>
                        <input type="text" id="agent_modal_name" required placeholder="e.g. Shah E Lasani" class="w-full border rounded-xl p-2.5">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Agency / Company Name</label>
                        <input type="text" id="agent_modal_company" placeholder="e.g. Shah E Lasani Travel Services" class="w-full border rounded-xl p-2.5">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-semibold mb-1">Phone Number</label>
                            <input type="text" id="agent_modal_phone" required placeholder="+92 300 1234567" class="w-full border rounded-xl p-2.5 font-mono">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">City</label>
                            <input type="text" id="agent_modal_city" value="Lahore" class="w-full border rounded-xl p-2.5">
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl transition mt-2">
                        Save Agent Record
                    </button>
                </form>
            </div>
        </div>
    `;
}

function editAgent(agent) {
    openNewAgentModal();
    document.getElementById('agent_modal_id').value = agent.id;
    document.getElementById('agent_modal_name').value = agent.name;
    document.getElementById('agent_modal_company').value = agent.company_name || '';
    document.getElementById('agent_modal_phone').value = agent.phone;
    document.getElementById('agent_modal_city').value = agent.city || 'Lahore';
}

async function submitAgentForm(e) {
    e.preventDefault();
    const payload = {
        id: document.getElementById('agent_modal_id').value,
        name: document.getElementById('agent_modal_name').value,
        company_name: document.getElementById('agent_modal_company').value,
        phone: document.getElementById('agent_modal_phone').value,
        city: document.getElementById('agent_modal_city').value
    };

    const res = await fetch('index.php?api=save_agent', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        alert(data.message || 'Error saving agent');
    }
}

async function deleteAgent(id) {
    if (!confirm('Are you sure you want to delete this agent? All associated bookings will be affected.')) return;
    const res = await fetch('index.php?api=delete_agent', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    });
    const data = await res.json();
    if (data.success) window.location.reload();
}

function openNewVendorModal() {
    const modal = document.getElementById('dynamicModalContainer');
    modal.innerHTML = `
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="font-bold text-slate-800 text-sm">Register Supplier / Vendor</h3>
                    <button onclick="closeActiveModal()" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <form onsubmit="submitVendorForm(event)" class="space-y-3 text-xs">
                    <input type="hidden" id="vendor_modal_id" value="">
                    <div>
                        <label class="block font-semibold mb-1">Vendor / Supplier Name</label>
                        <input type="text" id="vendor_modal_name" required placeholder="e.g. Chatta Travels" class="w-full border rounded-xl p-2.5">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Service Type</label>
                        <input type="text" id="vendor_modal_service" value="Umrah Visas & Hotel Stays" class="w-full border rounded-xl p-2.5">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-semibold mb-1">Phone Number</label>
                            <input type="text" id="vendor_modal_phone" required placeholder="+966 50 000 0000" class="w-full border rounded-xl p-2.5 font-mono">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Country</label>
                            <input type="text" id="vendor_modal_country" value="Saudi Arabia" class="w-full border rounded-xl p-2.5">
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-cyan-700 hover:bg-cyan-800 text-white font-semibold py-2.5 rounded-xl transition mt-2">
                        Save Supplier Record
                    </button>
                </form>
            </div>
        </div>
    `;
}

function editVendor(vendor) {
    openNewVendorModal();
    document.getElementById('vendor_modal_id').value = vendor.id;
    document.getElementById('vendor_modal_name').value = vendor.name;
    document.getElementById('vendor_modal_service').value = vendor.service_type || '';
    document.getElementById('vendor_modal_phone').value = vendor.phone;
    document.getElementById('vendor_modal_country').value = vendor.country || 'Saudi Arabia';
}

async function submitVendorForm(e) {
    e.preventDefault();
    const payload = {
        id: document.getElementById('vendor_modal_id').value,
        name: document.getElementById('vendor_modal_name').value,
        service_type: document.getElementById('vendor_modal_service').value,
        phone: document.getElementById('vendor_modal_phone').value,
        country: document.getElementById('vendor_modal_country').value
    };

    const res = await fetch('index.php?api=save_vendor', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        alert(data.message || 'Error saving vendor');
    }
}

async function deleteVendor(id) {
    if (!confirm('Are you sure you want to delete this vendor?')) return;
    const res = await fetch('index.php?api=delete_vendor', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    });
    const data = await res.json();
    if (data.success) window.location.reload();
}

async function handleSettingsUpdate(e) {
    e.preventDefault();
    const payload = {
        agency_name: document.getElementById('set_agency_name').value,
        agency_logo: document.getElementById('set_agency_logo').value,
        agency_phone: document.getElementById('set_agency_phone').value,
        agency_email: document.getElementById('set_agency_email').value,
        agency_address: document.getElementById('set_agency_address').value,
        default_exchange_rate: document.getElementById('set_exchange_rate').value,
        makkah_team_whatsapp: document.getElementById('set_makkah_team_whatsapp').value,
        madinah_team_whatsapp: document.getElementById('set_madinah_team_whatsapp').value,
        bank_details: document.getElementById('set_bank_details').value,
        makkah_helpline: document.getElementById('set_makkah_helpline').value,
        madinah_helpline: document.getElementById('set_madinah_helpline').value
    };

    const res = await fetch('index.php?api=update_settings', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    });
    const data = await res.json();
    alert(data.message || 'Settings saved.');
    if (data.success) window.location.reload();
}
</script>

