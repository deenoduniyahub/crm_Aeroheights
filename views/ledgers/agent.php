<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/LedgerController.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

if (!function_exists('formatAgentLedgerDate')) {
    function formatAgentLedgerDate($value): string {
        if ($value === null || trim((string)$value) === '') return '-';
        $timestamp = strtotime((string)$value);
        return $timestamp !== false ? date('d-m-Y', $timestamp) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$agents = AdminController::getAgents();
$selectedAgentId = (int)($_GET['agent_id'] ?? ($agents[0]['id'] ?? 1));
$ledgerData = LedgerController::getAgentLedger($selectedAgentId);
$agent = $ledgerData['agent'] ?? null;
?>

<main class="md:col-span-9 space-y-6">

    <!-- Header & Agent Selector Ribbon -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
        <form method="GET" action="index.php" class="flex items-center gap-3 w-full md:w-auto">
            <input type="hidden" name="page" value="agent_ledger">
            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Select B2B Travel Agent</label>
                <select name="agent_id" onchange="this.form.submit()" class="border border-slate-300 rounded-xl px-3.5 py-2 text-sm bg-white font-bold text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none">
                    <?php foreach ($agents as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $a['id'] === $selectedAgentId ? 'selected' : '' ?>><?= htmlspecialchars($a['name']) ?> (<?= htmlspecialchars($a['city']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <div class="flex items-center gap-3 w-full md:w-auto justify-end">
            <!-- Balance Card -->
            <div class="bg-rose-50 border border-rose-200 px-4 py-2 rounded-xl text-right">
                <span class="text-[10px] font-bold text-rose-500 uppercase block">Receivable Balance</span>
                <span class="text-base font-black text-rose-700 font-mono"><?= number_format((float)$ledgerData['current_balance_sar'], 0) ?> SAR</span>
            </div>

            <button type="button" onclick="openAgentPaymentModal(<?= $selectedAgentId ?>, '<?= htmlspecialchars(addslashes($agent['name'] ?? 'Agent')) ?>')" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-4 py-2.5 rounded-xl transition shadow-sm flex items-center">
                <i class="fa-solid fa-money-bill-wave mr-1.5"></i> Receive Payment (PKR/SAR)
            </button>

            <button type="button" onclick="openAgentAdjustmentModal(<?= $selectedAgentId ?>, '<?= htmlspecialchars(addslashes($agent['name'] ?? 'Agent')) ?>')" class="bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold px-4 py-2.5 rounded-xl transition shadow-sm flex items-center">
                <i class="fa-solid fa-plus mr-1.5"></i> Add Amount
            </button>

            <a href="index.php?page=print_agent_statement&agent_id=<?= $selectedAgentId ?>" target="_blank" class="bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold px-4 py-2.5 rounded-xl transition shadow-sm inline-flex items-center">
                <i class="fa-solid fa-print mr-1.5"></i> Print Statement
            </a>
        </div>
    </div>

    <!-- Official Ledger Statement Table with Colorful Payment Highlights -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="bg-slate-50 px-5 py-3.5 border-b border-slate-200 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-xs uppercase tracking-wider">
                Statement of Account: <span class="text-blue-600"><?= htmlspecialchars($agent['name'] ?? 'Agent') ?></span>
            </h3>
            <span class="text-[11px] font-mono font-semibold text-slate-500">Date: <?= date('d-M-Y') ?></span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px] text-left text-xs text-slate-600">
                <thead class="bg-slate-800 text-white uppercase font-bold text-[11px]">
                    <tr>
                        <th class="p-3.5">Date</th>
                        <th class="p-3.5">Flight</th>
                        <th class="p-3.5">Arrival</th>
                        <th class="p-3.5">Departure</th>
                        <th class="p-3.5">Mutamer Name / Description</th>
                        <th class="p-3.5">Passport #</th>
                        <th class="p-3.5 text-right">Debit / Rate (SAR)</th>
                        <th class="p-3.5 text-right">Credit / Paid (SAR)</th>
                        <th class="p-3.5 text-right">Running Balance (SAR)</th>
                        <th class="p-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono">
                    <?php if (empty($ledgerData['ledger'])): ?>
                        <tr><td colspan="10" class="p-6 text-center text-slate-400 font-sans">No transactions recorded for this client.</td></tr>
                    <?php else: foreach ($ledgerData['ledger'] as $row): 
                        $recordType = $row['record_type'] ?? '';
                        $isPayment = $recordType === 'payment' || (float)$row['credit_sar'] > 0;
                        $isAdjustment = $recordType === 'adjustment';
                        $isHotel = $recordType === 'hotel';
                        $isTransport = $recordType === 'transport';
                        $rowClass = $isPayment
                            ? 'bg-gradient-to-r from-emerald-50 via-emerald-50/80 to-emerald-100/60 font-semibold border-l-4 border-emerald-500 shadow-2xs'
                            : ($isAdjustment
                                ? 'bg-amber-50 border-l-4 border-amber-500 hover:bg-amber-100/70'
                            : ($isHotel
                                ? 'bg-amber-50 border-l-4 border-amber-400 hover:bg-amber-100/70'
                                : ($isTransport ? 'bg-sky-50 border-l-4 border-sky-400 hover:bg-sky-100/70' : 'hover:bg-slate-50/80')));
                    ?>
                        <tr class="<?= $rowClass ?> transition">
                            <td class="p-3.5 font-sans text-slate-600 font-medium"><?= formatAgentLedgerDate($row['entry_date']) ?></td>
                            <td class="p-3.5 font-sans font-bold text-slate-800"><?= htmlspecialchars($row['flight_number'] ?: '-') ?></td>
                            <td class="p-3.5 font-sans text-slate-600"><?= formatAgentLedgerDate($row['arrival_date'] ?? null) ?></td>
                            <td class="p-3.5 font-sans text-slate-600"><?= formatAgentLedgerDate($row['departure_date'] ?? null) ?></td>
                            <td class="p-3.5 font-sans font-medium text-slate-900">
                                <?php if ($isPayment): ?>
                                    <span class="inline-flex items-center text-emerald-800 font-bold mr-1">
                                        <i class="fa-solid fa-circle-check text-emerald-600 mr-1.5"></i>
                                    </span>
                                <?php elseif ($isHotel): ?>
                                    <span class="inline-flex items-center rounded-md bg-amber-200 text-amber-900 px-1.5 py-0.5 text-[9px] font-black uppercase mr-1.5">Hotel</span>
                                <?php elseif ($isTransport): ?>
                                    <span class="inline-flex items-center rounded-md bg-sky-200 text-sky-900 px-1.5 py-0.5 text-[9px] font-black uppercase mr-1.5">Transport</span>
                                <?php elseif ($isAdjustment): ?>
                                    <span class="inline-flex items-center rounded-md bg-amber-200 text-amber-900 px-1.5 py-0.5 text-[9px] font-black uppercase mr-1.5">Added</span>
                                <?php endif; ?>
                                <?php if ($isTransport && !empty($row['hotel_names'])): ?><span class="font-bold text-sky-900"><?= htmlspecialchars($row['hotel_names']) ?></span><span class="text-slate-400 font-normal"> — </span><?php endif; ?>
                                <?= htmlspecialchars($row['passenger_name']) ?>
                                <?php if ($isHotel && !empty($row['hotel_names'])): ?><span class="text-slate-500 font-normal"> (<?= htmlspecialchars($row['hotel_names']) ?>)</span><?php endif; ?>
                            </td>
                            <td class="p-3.5 font-bold text-slate-700"><?= htmlspecialchars($row['passport_number'] ?: '-') ?></td>
                            <td class="p-3.5 text-right text-rose-600 font-bold"><?= $row['debit_sar'] > 0 ? number_format((float)$row['debit_sar'], 0) : '-' ?></td>
                            <td class="p-3.5 text-right font-black <?= $isPayment ? 'text-emerald-700 text-sm' : 'text-slate-400' ?>">
                                <?= $row['credit_sar'] > 0 ? number_format((float)$row['credit_sar'], 0) : '-' ?>
                            </td>
                            <td class="p-3.5 text-right font-black text-slate-900"><?= number_format((float)$row['balance_sar'], 0) ?></td>
                            <td class="p-3.5 text-right whitespace-nowrap">
                                <?php if ($isPayment): ?>
                                    <button type="button" onclick='editAgentPayment(<?= json_encode([
                                        'payment_id' => (int)$row['id'],
                                        'agent_id' => $selectedAgentId,
                                        'payment_date' => $row['entry_date'],
                                        'bank_name' => $row['bank_name'] ?? preg_replace('/^Payment Received: /', '', (string)$row['passenger_name']),
                                        'receipt_number' => $row['flight_number'] ?? '',
                                        'remarks' => $row['remarks'] ?? '',
                                        'amount_pkr' => (float)($row['amount_pkr'] ?? 0),
                                        'exchange_rate' => (float)($row['exchange_rate'] ?? 0),
                                        'amount_sar' => (float)$row['credit_sar']
                                    ], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' class="text-indigo-600 hover:text-indigo-800 p-1" title="Edit payment">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button type="button" onclick="deleteAgentPayment(<?= (int)$row['id'] ?>, <?= $selectedAgentId ?>)" class="text-rose-500 hover:text-rose-700 p-1" title="Delete payment">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-slate-300">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot class="bg-slate-100 font-bold text-slate-800">
                    <tr>
                        <td colspan="9" class="p-3.5 text-right font-sans text-xs">Closing Outstanding Balance:</td>
                        <td class="p-3.5 text-right font-mono font-black text-sm text-rose-600">
                            <?= number_format((float)$ledgerData['current_balance_sar'], 0) ?> SAR
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</main>