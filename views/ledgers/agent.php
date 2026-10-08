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

<main class="md:col-span-9 space-y-4">

    <!-- Header & Agent Selector Ribbon -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col xl:flex-row items-center justify-between gap-3">
        <form method="GET" action="index.php" class="flex items-center gap-3 w-full xl:w-auto">
            <input type="hidden" name="page" value="agent_ledger">
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Select B2B Travel Agent</label>
                <select name="agent_id" onchange="this.form.submit()" class="border border-slate-300 rounded-lg px-3 py-2 text-xs bg-white font-semibold text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none">
                    <?php foreach ($agents as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $a['id'] === $selectedAgentId ? 'selected' : '' ?>><?= htmlspecialchars($a['name']) ?> (<?= htmlspecialchars($a['city']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <div class="flex flex-wrap items-center gap-2 w-full xl:w-auto xl:justify-end">
            <!-- Balance Card -->
            <div class="bg-rose-50/70 border border-rose-100 px-3 py-1.5 rounded-lg text-right">
                <span class="text-[9px] font-bold text-rose-500 uppercase block tracking-wide">Receivable Balance</span>
                <span class="text-sm font-bold text-rose-700 font-mono"><?= number_format((float)$ledgerData['current_balance_sar'], 0) ?> SAR</span>
            </div>

            <button type="button" onclick="openAgentPaymentModal(<?= $selectedAgentId ?>, '<?= htmlspecialchars(addslashes($agent['name'] ?? 'Agent')) ?>')" class="bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-semibold px-3 py-2 rounded-lg transition shadow-sm flex items-center">
                <i class="fa-solid fa-money-bill-wave mr-1.5"></i> Receive Payment (PKR/SAR)
            </button>

            <button type="button" onclick="openAgentAdjustmentModal(<?= $selectedAgentId ?>, '<?= htmlspecialchars(addslashes($agent['name'] ?? 'Agent')) ?>')" class="bg-amber-600 hover:bg-amber-700 text-white text-[11px] font-semibold px-3 py-2 rounded-lg transition shadow-sm flex items-center">
                <i class="fa-solid fa-plus mr-1.5"></i> Add Amount
            </button>

            <a href="index.php?page=print_agent_statement&agent_id=<?= $selectedAgentId ?>" target="_blank" class="bg-slate-800 hover:bg-slate-900 text-white text-[11px] font-semibold px-3 py-2 rounded-lg transition shadow-sm inline-flex items-center">
                <i class="fa-solid fa-print mr-1.5"></i> Print Statement
            </a>
        </div>
    </div>

    <!-- Official Ledger Statement Table with Colorful Payment Highlights -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="bg-white px-4 py-3 border-b border-slate-200 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-xs uppercase tracking-wider">
                Statement of Account: <span class="text-blue-600"><?= htmlspecialchars($agent['name'] ?? 'Agent') ?></span>
            </h3>
            <span class="text-[10px] font-mono font-medium text-slate-500">Date: <?= date('d-M-Y') ?></span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1000px] text-left text-[11px] text-slate-600">
                <thead class="bg-slate-50 text-slate-600 uppercase font-bold text-[9px] tracking-wide border-b border-slate-200">
                    <tr>
                        <th class="px-2.5 py-2.5 whitespace-nowrap">Date</th>
                        <th class="px-2.5 py-2.5 whitespace-nowrap">Flight</th>
                        <th class="px-2.5 py-2.5 whitespace-nowrap">Arrival</th>
                        <th class="px-2.5 py-2.5 whitespace-nowrap">Departure</th>
                        <th class="px-2.5 py-2.5">Mutamer Name / Description</th>
                        <th class="px-2.5 py-2.5 whitespace-nowrap">Passport #</th>
                        <th class="px-2.5 py-2.5 text-right whitespace-nowrap">Debit / Rate (SAR)</th>
                        <th class="px-2.5 py-2.5 text-right whitespace-nowrap">Credit / Paid (SAR)</th>
                        <th class="px-2.5 py-2.5 text-right whitespace-nowrap">Running Balance (SAR)</th>
                        <th class="px-2.5 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($ledgerData['ledger'])): ?>
                        <tr><td colspan="10" class="p-6 text-center text-slate-400">No transactions recorded for this client.</td></tr>
                    <?php else: foreach ($ledgerData['ledger'] as $row): 
                        $recordType = $row['record_type'] ?? '';
                        $isPayment = $recordType === 'payment' || (float)$row['credit_sar'] > 0;
                        $isAdjustment = $recordType === 'adjustment';
                        $isHotel = $recordType === 'hotel';
                        $isTransport = $recordType === 'transport';
                        $rowClass = $isPayment
                            ? 'bg-emerald-50/50 border-l-2 border-emerald-400'
                            : ($isAdjustment
                                ? 'bg-amber-50/50 border-l-2 border-amber-400 hover:bg-amber-50'
                            : ($isHotel
                                ? 'bg-amber-50/40 border-l-2 border-amber-300 hover:bg-amber-50/70'
                                : ($isTransport ? 'bg-sky-50/40 border-l-2 border-sky-300 hover:bg-sky-50/70' : 'hover:bg-slate-50/80')));
                    ?>
                        <tr class="<?= $rowClass ?> transition-colors">
                            <td class="px-2.5 py-2 font-mono text-[10px] text-slate-500 whitespace-nowrap"><?= formatAgentLedgerDate($row['entry_date']) ?></td>
                            <td class="px-2.5 py-2 font-sans font-semibold text-slate-700 whitespace-nowrap"><?= htmlspecialchars($row['flight_number'] ?: '-') ?></td>
                            <td class="px-2.5 py-2 font-mono text-[10px] text-slate-500 whitespace-nowrap"><?= formatAgentLedgerDate($row['arrival_date'] ?? null) ?></td>
                            <td class="px-2.5 py-2 font-mono text-[10px] text-slate-500 whitespace-nowrap"><?= formatAgentLedgerDate($row['departure_date'] ?? null) ?></td>
                            <td class="px-2.5 py-2 font-sans font-medium text-slate-800">
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
                            <td class="px-2.5 py-2 font-mono text-[10px] text-slate-500 whitespace-nowrap"><?= htmlspecialchars($row['passport_number'] ?: '-') ?></td>
                            <td class="px-2.5 py-2 text-right font-mono text-rose-600 font-semibold whitespace-nowrap"><?= $row['debit_sar'] > 0 ? number_format((float)$row['debit_sar'], 0) : '-' ?></td>
                            <td class="px-2.5 py-2 text-right font-mono font-semibold whitespace-nowrap <?= $isPayment ? 'text-emerald-700' : 'text-slate-400' ?>">
                                <?= $row['credit_sar'] > 0 ? number_format((float)$row['credit_sar'], 0) : '-' ?>
                            </td>
                            <td class="px-2.5 py-2 text-right font-mono font-semibold text-slate-800 whitespace-nowrap"><?= number_format((float)$row['balance_sar'], 0) ?></td>
                            <td class="px-2.5 py-2 text-right whitespace-nowrap">
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
                                    ], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' class="text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 p-1.5 rounded-md transition" title="Edit payment">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button type="button" onclick="deleteAgentPayment(<?= (int)$row['id'] ?>, <?= $selectedAgentId ?>)" class="text-rose-500 hover:text-rose-700 hover:bg-rose-50 p-1.5 rounded-md transition" title="Delete payment">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-slate-300">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot class="bg-slate-50 font-semibold text-slate-700 border-t border-slate-200">
                    <tr>
                        <td colspan="9" class="px-3 py-2.5 text-right text-[11px]">Closing Outstanding Balance:</td>
                        <td class="px-2.5 py-2.5 text-right font-mono font-bold text-xs text-rose-600 whitespace-nowrap">
                            <?= number_format((float)$ledgerData['current_balance_sar'], 0) ?> SAR
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</main>