<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/LedgerController.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

$vendors = AdminController::getVendors();
$selectedVendorId = (int)($_GET['vendor_id'] ?? ($vendors[0]['id'] ?? 1));
$vendorData = LedgerController::getVendorLedger($selectedVendorId);
$vendor = $vendorData['vendor'] ?? null;
?>

<main class="md:col-span-9 space-y-6">

    <!-- Header & Vendor Selector Ribbon -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
        <form method="GET" action="index.php" class="flex items-center gap-3 w-full md:w-auto">
            <input type="hidden" name="page" value="vendor_ledger">
            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Select Supplier / Vendor</label>
                <select name="vendor_id" onchange="this.form.submit()" class="border border-slate-300 rounded-xl px-3.5 py-2 text-sm bg-white font-bold text-slate-800 focus:ring-2 focus:ring-cyan-600 outline-none">
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= $v['id'] === $selectedVendorId ? 'selected' : '' ?>><?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['country']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <div class="flex items-center gap-3 w-full md:w-auto justify-end">
            <!-- Total Outstanding Payable Badge -->
            <div class="bg-cyan-50 border border-cyan-200 px-4 py-2 rounded-xl text-right">
                <span class="text-[10px] font-bold text-cyan-600 uppercase block">Payable Balance</span>
                <span class="text-base font-black text-cyan-900 font-mono"><?= number_format((float)$vendorData['total_payable_pkr'], 2) ?> PKR</span>
            </div>

            <button type="button" onclick="openVendorPaymentModal(<?= $selectedVendorId ?>, '<?= htmlspecialchars(addslashes($vendor['name'] ?? 'Vendor')) ?>')" class="bg-cyan-700 hover:bg-cyan-800 text-white text-xs font-semibold px-4 py-2.5 rounded-xl transition shadow-sm flex items-center">
                <i class="fa-solid fa-hand-holding-dollar mr-1.5"></i> Disburse Payment (PKR)
            </button>

            <button type="button" onclick="window.print()" class="no-print bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold px-3.5 py-2.5 rounded-xl transition" title="Print Statement">
                <i class="fa-solid fa-print"></i>
            </button>
        </div>
    </div>

    <!-- Vendor Ledger Table with Colorful Disbursement Highlights -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="bg-slate-50 px-5 py-3.5 border-b border-slate-200 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-xs uppercase tracking-wider">
                Supplier Account Statement: <span class="text-cyan-700"><?= htmlspecialchars($vendor['name'] ?? 'Vendor') ?></span>
            </h3>
            <span class="text-[11px] font-mono font-semibold text-slate-500">Service: <?= htmlspecialchars($vendor['service_type'] ?? 'Visas & Stays') ?></span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-800 text-white uppercase font-bold text-[11px]">
                    <tr>
                        <th class="p-3.5">Date</th>
                        <th class="p-3.5">Description / Mutamer Name</th>
                        <th class="p-3.5">Passport #</th>
                        <th class="p-3.5 text-right">Charges / Buy Rate (PKR)</th>
                        <th class="p-3.5 text-right">Paid / Disbursed (PKR)</th>
                        <th class="p-3.5 text-right">Outstanding Payable (PKR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono">
                    <?php if (empty($vendorData['ledger'])): ?>
                        <tr><td colspan="6" class="p-6 text-center text-slate-400 font-sans">No transactions or purchase charges logged for this vendor.</td></tr>
                    <?php else: foreach ($vendorData['ledger'] as $row): 
                        $isPayment = ($row['record_type'] ?? '') === 'payment' || (float)$row['paid_pkr'] > 0;
                    ?>
                        <tr class="<?= $isPayment ? 'bg-gradient-to-r from-cyan-50 via-cyan-50/80 to-cyan-100/60 font-semibold border-l-4 border-cyan-600 shadow-2xs' : 'hover:bg-slate-50/80' ?> transition">
                            <td class="p-3.5 font-sans text-slate-500"><?= htmlspecialchars($row['entry_date'] ?? '') ?></td>
                            <td class="p-3.5 font-sans font-medium text-slate-900">
                                <?php if ($isPayment): ?>
                                    <span class="inline-flex items-center text-cyan-800 font-bold mr-1">
                                        <i class="fa-solid fa-circle-check text-cyan-600 mr-1.5"></i>
                                    </span>
                                <?php endif; ?>
                                <?= htmlspecialchars($row['passenger_name'] ?? '') ?>
                                <span class="text-[11px] text-slate-400 block font-normal"><?= htmlspecialchars($row['description'] ?? '') ?></span>
                            </td>
                            <td class="p-3.5 font-bold text-slate-700"><?= htmlspecialchars($row['passport_number'] ?: '-') ?></td>
                            <td class="p-3.5 text-right text-rose-600 font-bold"><?= $row['charge_pkr'] > 0 ? number_format((float)$row['charge_pkr'], 2) : '-' ?></td>
                            <td class="p-3.5 text-right font-black <?= $isPayment ? 'text-cyan-800 text-sm' : 'text-slate-400' ?>">
                                <?= $row['paid_pkr'] > 0 ? number_format((float)$row['paid_pkr'], 2) : '-' ?>
                            </td>
                            <td class="p-3.5 text-right font-black text-slate-900"><?= number_format((float)$row['balance_pkr'], 2) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot class="bg-slate-100 font-bold text-slate-800">
                    <tr>
                        <td colspan="5" class="p-3.5 text-right font-sans text-xs">Total Net Payable Balance:</td>
                        <td class="p-3.5 text-right font-mono font-black text-sm text-cyan-800">
                            <?= number_format((float)$vendorData['total_payable_pkr'], 2) ?> PKR
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</main>