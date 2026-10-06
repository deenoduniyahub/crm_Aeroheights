<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/LedgerController.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

// Helper function to format ledger dates consistently as DD-MM-YYYY
if (!function_exists('formatLedgerDate')) {
    function formatLedgerDate($value): string {
        if ($value === null || trim((string)$value) === '' || $value === '-') return '-';
        $value = trim((string)$value);
        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2})$/', $value, $m)) {
            $day=(int)$m[1]; $month=(int)$m[2]; $year=(int)$m[3];
            $year += ($year <= 69) ? 2000 : 1900;
            if (checkdate($month,$day,$year)) return sprintf('%02d-%02d-%04d',$day,$month,$year);
        }
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $value, $m)) {
            $year=(int)$m[1]; $month=(int)$m[2]; $day=(int)$m[3];
            if (checkdate($month,$day,$year)) return sprintf('%02d-%02d-%04d',$day,$month,$year);
        }
        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $value, $m)) {
            $day=(int)$m[1]; $month=(int)$m[2]; $year=(int)$m[3];
            if (checkdate($month,$day,$year)) return sprintf('%02d-%02d-%04d',$day,$month,$year);
        }
        $ts = strtotime($value);
        return $ts !== false ? date('d-m-Y', $ts) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$agents = AdminController::getAgents();
$selectedAgentId = !empty($_GET['agent_id']) ? (int)$_GET['agent_id'] : (int)($agents[0]['id'] ?? 0);
$ledgerData = $selectedAgentId > 0 ? LedgerController::getAgentLedger($selectedAgentId) : null;
$agent = $ledgerData['agent'] ?? null;
$entries = $ledgerData['ledger'] ?? [];
$currentBalance = $ledgerData['current_balance_sar'] ?? 0.00;
?>

<main class="md:col-span-9 space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-calculator text-indigo-600 mr-2"></i> Agent Financial Ledger
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">View statement of account, debit/credit entries, and flight/stay dates per client.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button onclick="openPaymentModal(<?= $selectedAgentId ?>)" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-4 py-2.5 rounded-xl">
                <i class="fa-solid fa-plus-circle mr-1.5"></i> Record Payment Received
            </button>
            <button onclick="printAgentLedger(<?= $selectedAgentId ?>)" class="bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold px-4 py-2.5 rounded-xl">
                <i class="fa-solid fa-print mr-1.5"></i> Print Ledger
            </button>
        </div>
    </div>

    <!-- Agent Selection Header -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
        <form method="GET" action="index.php" class="flex items-center gap-2 w-full sm:w-auto">
            <input type="hidden" name="page" value="agent_ledger">
            <label for="agent_id" class="text-xs font-bold text-slate-700 whitespace-nowrap">Select Agent:</label>
            <select name="agent_id" id="agent_id" onchange="this.form.submit()" class="border border-slate-300 rounded-xl p-2 bg-slate-50 text-xs font-semibold focus:ring-2 focus:ring-indigo-500">
                <?php foreach ($agents as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $selectedAgentId === (int)$a['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['name']) ?> (<?= htmlspecialchars($a['city'] ?? 'Lahore') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($agent): ?>
            <div class="flex items-center gap-6 text-xs">
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Company</span>
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($agent['company_name'] ?? $agent['name']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Phone</span>
                    <span class="font-mono text-slate-700"><?= htmlspecialchars($agent['phone']) ?></span>
                </div>
                <div class="bg-indigo-50 border border-indigo-100 px-3 py-1.5 rounded-xl text-right">
                    <span class="text-indigo-600 block text-[10px] uppercase font-bold">Net Balance (SAR)</span>
                    <span class="font-mono font-bold text-sm <?= $currentBalance > 0 ? 'text-indigo-700' : 'text-emerald-600' ?>">
                        <?= number_format($currentBalance, 2) ?> SAR
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Ledger Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-700 border-b border-slate-200 uppercase font-bold text-[10px]">
                    <tr>
                        <th class="p-3">Date</th>
                        <th class="p-3">Flight</th>
                        <th class="p-3">Arrival</th>
                        <th class="p-3">Departure</th>
                        <th class="p-3">Passenger / Title</th>
                        <th class="p-3">Passport / Ref</th>
                        <th class="p-3">Service Type</th>
                        <th class="p-3 text-right">Debit (SAR)</th>
                        <th class="p-3 text-right">Credit (SAR)</th>
                        <th class="p-3 text-right">Balance (SAR)</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="11" class="p-8 text-center text-slate-400">No ledger transactions found for this agent.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($entries as $entry): ?>
                            <?php $isPayment = ($entry['record_type'] ?? '') === 'payment'; ?>
                            <tr class="hover:bg-slate-50/80">
                                <td class="p-3 font-mono whitespace-nowrap"><?= formatLedgerDate($entry['entry_date']) ?></td>
                                <td class="p-3 font-mono text-indigo-600 whitespace-nowrap"><?= htmlspecialchars((string)($entry['flight_number'] ?? '-')) ?></td>
                                <td class="p-3 font-mono whitespace-nowrap"><?= formatLedgerDate($entry['arrival_date'] ?? null) ?></td>
                                <td class="p-3 font-mono whitespace-nowrap"><?= formatLedgerDate($entry['departure_date'] ?? null) ?></td>
                                <td class="p-3 font-semibold text-slate-900"><?= htmlspecialchars((string)($entry['passenger_name'] ?? '-')) ?></td>
                                <td class="p-3 font-mono whitespace-nowrap"><?= htmlspecialchars((string)($entry['passport_number'] ?? '-')) ?></td>
                                <td class="p-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium <?= str_contains(strtolower($entry['service_type']), 'payment') ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-800' ?>">
                                        <?= htmlspecialchars($entry['service_type']) ?>
                                    </span>
                                </td>
                                <td class="p-3 text-right font-mono text-rose-600 font-semibold">
                                    <?= (float)$entry['debit_sar'] > 0 ? number_format((float)$entry['debit_sar'], 2) : '-' ?>
                                </td>
                                <td class="p-3 text-right font-mono text-emerald-600 font-semibold">
                                    <?= (float)$entry['credit_sar'] > 0 ? number_format((float)$entry['credit_sar'], 2) : '-' ?>
                                </td>
                                <td class="p-3 text-right font-mono font-bold text-slate-800">
                                    <?= number_format((float)$entry['balance_sar'], 2) ?>
                                </td>
                                <td class="p-3 text-right whitespace-nowrap">
                                    <?php if ($isPayment): ?>
                                        <button type="button" onclick='editAgentPayment(<?= json_encode([
                                            'payment_id' => (int)$entry['id'],
                                            'agent_id' => $selectedAgentId,
                                            'payment_date' => $entry['entry_date'],
                                            'bank_name' => $entry['bank_name'] ?? preg_replace('/^Payment Received: /', '', (string)$entry['passenger_name']),
                                            'receipt_number' => $entry['flight_number'] ?? '',
                                            'remarks' => $entry['remarks'] ?? '',
                                            'amount_pkr' => (float)($entry['amount_pkr'] ?? 0),
                                            'exchange_rate' => (float)($entry['exchange_rate'] ?? 0),
                                            'amount_sar' => (float)$entry['credit_sar']
                                        ], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' class="text-indigo-600 hover:text-indigo-800 p-1" title="Edit payment">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button type="button" onclick="deleteAgentPayment(<?= (int)$entry['id'] ?>, <?= $selectedAgentId ?>)" class="text-rose-500 hover:text-rose-700 p-1" title="Delete payment">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-slate-300">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
const AST_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

function printAgentLedger(agentId) {
    if (!agentId) return;
    window.open(`index.php?page=print_agent_ledger&agent_id=${agentId}`, 'astPrintLedger', 'width=1100,height=800,scrollbars=yes,resizable=yes');
}

function openPaymentModal(agentId) {
    if (!agentId) return;
    const modal = document.getElementById('dynamicModalContainer');
    const today = new Date().toISOString().slice(0, 10);
    modal.innerHTML = `
        <div class="fixed inset-0 bg-slate-900/70 z-50 flex items-center justify-center p-3">
            <div class="bg-white rounded-2xl max-w-md w-full p-5 shadow-2xl">
                <div class="flex justify-between items-center border-b pb-3 mb-4">
                    <h3 class="font-bold text-slate-800">Record Agent Payment Received</h3>
                    <button onclick="closeActiveModal()" class="text-slate-400"><i class="fa-solid fa-xmark text-lg"></i></button>
                </div>
                <form id="agentPaymentForm" class="space-y-3">
                    <input type="hidden" name="agent_id" value="${agentId}">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Payment Date</label>
                        <input type="date" name="payment_date" value="${today}" class="w-full border rounded-xl p-2.5 text-xs font-mono" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Amount (PKR)</label>
                        <input type="number" step="0.01" min="1" id="pay_pkr" name="amount_pkr" placeholder="e.g. 500000" class="w-full border rounded-xl p-2.5 text-xs font-mono" oninput="calcSar()" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Exchange Rate (PKR / SAR)</label>
                        <input type="number" step="0.01" id="pay_rate" name="exchange_rate" value="76.00" class="w-full border rounded-xl p-2.5 text-xs font-mono" oninput="calcSar()" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Converted Amount (SAR)</label>
                        <input type="text" id="pay_sar" class="w-full border rounded-xl p-2.5 text-xs font-mono bg-slate-100 font-bold text-indigo-700" readonly value="0.00 SAR">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Bank Name / Cash Vault</label>
                        <input type="text" name="bank_name" placeholder="e.g. Meezan Bank / Cash in Hand" class="w-full border rounded-xl p-2.5 text-xs" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Receipt / Reference No.</label>
                        <input type="text" name="receipt_number" placeholder="Ref/Deposit slip #" class="w-full border rounded-xl p-2.5 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Remarks</label>
                        <input type="text" name="remarks" placeholder="Optional notes" class="w-full border rounded-xl p-2.5 text-xs">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" onclick="closeActiveModal()" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-semibold">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold">Save Receipt</button>
                    </div>
                </form>
            </div>
        </div>
    `;

    document.getElementById('agentPaymentForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        const res = await fetch('index.php?api=record_agent_payment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': AST_CSRF },
            body: JSON.stringify(data)
        });
        const ret = await res.json();
        if (ret.success) {
            closeActiveModal();
            location.reload();
        } else {
            alert(ret.message || 'Error recording payment.');
        }
    });
}

function calcSar() {
    const pkr = parseFloat(document.getElementById('pay_pkr')?.value || 0);
    const rate = parseFloat(document.getElementById('pay_rate')?.value || 76);
    if (pkr > 0 && rate > 0) {
        const sar = (pkr / rate).toFixed(2);
        document.getElementById('pay_sar').value = sar + ' SAR';
    } else {
        document.getElementById('pay_sar').value = '0.00 SAR';
    }
}

function ledgerEsc(value) {
    const element = document.createElement('div');
    element.textContent = value ?? '';
    return element.innerHTML;
}

function editAgentPayment(payment) {
    const modal = document.getElementById('dynamicModalContainer');
    modal.innerHTML = `
        <div class="fixed inset-0 bg-slate-900/70 z-50 flex items-center justify-center p-3">
            <div class="bg-white rounded-2xl max-w-md w-full p-5 shadow-2xl">
                <div class="flex justify-between items-center border-b pb-3 mb-4">
                    <h3 class="font-bold text-slate-800">Edit Payment Receipt</h3>
                    <button type="button" onclick="closeActiveModal()" class="text-slate-400"><i class="fa-solid fa-xmark text-lg"></i></button>
                </div>
                <form id="editAgentPaymentForm" class="space-y-3">
                    <input type="hidden" name="payment_id" value="${payment.payment_id}">
                    <input type="hidden" name="agent_id" value="${payment.agent_id}">
                    <div><label class="block text-xs font-bold text-slate-600 mb-1">Payment Date</label><input type="date" name="payment_date" value="${ledgerEsc(payment.payment_date)}" class="w-full border rounded-xl p-2.5 text-xs font-mono" required></div>
                    <div><label class="block text-xs font-bold text-slate-600 mb-1">Amount (PKR)</label><input type="number" step="0.01" min="1" id="edit_pay_pkr" name="amount_pkr" value="${payment.amount_pkr}" class="w-full border rounded-xl p-2.5 text-xs font-mono" oninput="calcEditSar()" required></div>
                    <div><label class="block text-xs font-bold text-slate-600 mb-1">Exchange Rate (PKR / SAR)</label><input type="number" step="0.01" min="0.01" id="edit_pay_rate" name="exchange_rate" value="${payment.exchange_rate}" class="w-full border rounded-xl p-2.5 text-xs font-mono" oninput="calcEditSar()" required></div>
                    <div><label class="block text-xs font-bold text-slate-600 mb-1">Converted Amount (SAR)</label><input type="text" id="edit_pay_sar" value="${Number(payment.amount_sar).toFixed(2)} SAR" class="w-full border rounded-xl p-2.5 text-xs font-mono bg-slate-100 font-bold text-indigo-700" readonly></div>
                    <div><label class="block text-xs font-bold text-slate-600 mb-1">Bank Name / Cash Vault</label><input type="text" name="bank_name" value="${ledgerEsc(payment.bank_name)}" class="w-full border rounded-xl p-2.5 text-xs" required></div>
                    <div><label class="block text-xs font-bold text-slate-600 mb-1">Receipt / Reference No.</label><input type="text" name="receipt_number" value="${ledgerEsc(payment.receipt_number)}" class="w-full border rounded-xl p-2.5 text-xs"></div>
                    <div><label class="block text-xs font-bold text-slate-600 mb-1">Remarks</label><input type="text" name="remarks" value="${ledgerEsc(payment.remarks)}" class="w-full border rounded-xl p-2.5 text-xs"></div>
                    <div class="flex justify-end gap-2 pt-2"><button type="button" onclick="closeActiveModal()" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-semibold">Cancel</button><button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold">Update Receipt</button></div>
                </form>
            </div>
        </div>
    `;
    document.getElementById('editAgentPaymentForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(event.target).entries());
        const response = await fetch('index.php?api=update_agent_payment', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':AST_CSRF}, body:JSON.stringify(payload)});
        const result = await response.json();
        if (result.success) { closeActiveModal(); location.reload(); } else alert(result.message || 'Unable to update payment receipt.');
    });
}

function calcEditSar() {
    const amount = parseFloat(document.getElementById('edit_pay_pkr')?.value || 0);
    const rate = parseFloat(document.getElementById('edit_pay_rate')?.value || 0);
    document.getElementById('edit_pay_sar').value = amount > 0 && rate > 0 ? (amount / rate).toFixed(2) + ' SAR' : '0.00 SAR';
}

async function deleteAgentPayment(paymentId, agentId) {
    if (!confirm('Delete this payment receipt? This will reduce the agent credit balance.')) return;
    const response = await fetch('index.php?api=delete_agent_payment', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':AST_CSRF}, body:JSON.stringify({payment_id:paymentId, agent_id:agentId})});
    const result = await response.json();
    if (result.success) location.reload(); else alert(result.message || 'Unable to delete payment receipt.');
}
</script>
