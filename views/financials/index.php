<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/Session.php';
require_once __DIR__ . '/../../controllers/LedgerController.php';

// Only reached once the owner email + password lock is open (index.php shows views/financials/lock.php otherwise).
$mode   = ($_GET['mode'] ?? 'month') === 'week' ? 'week' : 'month';
$value  = $mode === 'week' ? (string)($_GET['week'] ?? date('o-\WW')) : (string)($_GET['month'] ?? date('Y-m'));
$report = LedgerController::getBuySellReport($mode, $value);
$out    = LedgerController::getOutstandingTotals();
$t      = $report['totals'];
$money  = static fn(float $v): string => number_format($v, 2);
$maxProfit = max([1, ...array_map(static fn($r) => abs($r["pkr"]["profit"]), $report["rows"])]);
?>

<main class="md:col-span-9 space-y-6">

    <!-- Header + period picker -->
    <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-slate-800 flex items-center">
                    <i class="fa-solid fa-chart-column text-blue-600 mr-2"></i> Buy / Sell Report
                </h2>
                <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($report['label']) ?> &middot; <?= date('d M Y', strtotime($report['start'])) ?> to <?= date('d M Y', strtotime($report['end'])) ?></p>
            </div>
            <div class="flex items-center gap-2 no-print">
                <button type="button" onclick="window.print()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold px-3.5 py-2 rounded-xl border border-slate-200 transition"><i class="fa-solid fa-print mr-1"></i> Print</button>
                <a href="index.php?api=lock_financial" class="bg-slate-100 hover:bg-rose-50 text-slate-700 hover:text-rose-600 text-xs font-semibold px-3.5 py-2 rounded-xl border border-slate-200 transition" title="Lock ledgers & reports"><i class="fa-solid fa-lock mr-1"></i> Lock</a>
            </div>
        </div>

        <form method="GET" action="index.php" class="flex flex-wrap items-center gap-2 no-print">
            <input type="hidden" name="page" value="financials">
            <div class="inline-flex bg-slate-100 p-1 rounded-xl border border-slate-200 text-xs font-bold">
                <a href="index.php?page=financials&mode=week" class="px-4 py-1.5 rounded-lg transition <?= $mode === 'week' ? 'bg-blue-600 text-white shadow' : 'text-slate-600 hover:text-slate-900' ?>">Weekly</a>
                <a href="index.php?page=financials&mode=month" class="px-4 py-1.5 rounded-lg transition <?= $mode === 'month' ? 'bg-blue-600 text-white shadow' : 'text-slate-600 hover:text-slate-900' ?>">Monthly</a>
            </div>
            <input type="hidden" name="mode" value="<?= $mode ?>">
            <?php if ($mode === 'week'): ?>
                <input type="week" name="week" value="<?= htmlspecialchars($report['value']) ?>" onchange="this.form.submit()" class="border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-semibold bg-slate-50 focus:bg-white">
            <?php else: ?>
                <input type="month" name="month" value="<?= htmlspecialchars($report['value']) ?>" onchange="this.form.submit()" class="border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-semibold bg-slate-50 focus:bg-white">
            <?php endif; ?>
        </form>
    </div>

    <!-- Totals (PKR) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Sell</span>
                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xs"><i class="fa-solid fa-sack-dollar"></i></div>
            </div>
            <h3 class="text-2xl font-black text-slate-900 mt-2 font-mono"><?= $money($t['pkr']['sell']) ?> <span class="text-xs font-normal text-slate-400">PKR</span></h3>
            <div class="mt-1 text-[11px] text-slate-500">Billed to agents / clients</div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Buy</span>
                <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center text-xs"><i class="fa-solid fa-receipt"></i></div>
            </div>
            <h3 class="text-2xl font-black text-rose-600 mt-2 font-mono"><?= $money($t['pkr']['buy']) ?> <span class="text-xs font-normal text-slate-400">PKR</span></h3>
            <div class="mt-1 text-[11px] text-slate-500">Cost from vendors / suppliers</div>
        </div>
        <div class="p-5 rounded-2xl shadow-sm text-white" style="background-image: linear-gradient(125deg, #161A35 0%, #26206F 55%, #285A9B 100%);">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-blue-100 uppercase tracking-wider">Profit</span>
                <div class="w-8 h-8 rounded-lg bg-white/15 flex items-center justify-center text-xs"><i class="fa-solid fa-arrow-trend-up"></i></div>
            </div>
            <h3 class="text-2xl font-black mt-2 font-mono"><?= $money($t['pkr']['profit']) ?> <span class="text-xs font-normal text-blue-100">PKR</span></h3>
            <div class="mt-1 text-[11px] font-bold text-amber-300"><?= $t['pkr']['margin'] ?>% margin</div>
        </div>
    </div>

    <!-- Per service -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="bg-slate-50 px-5 py-3.5 border-b border-slate-200 font-bold text-slate-800 text-xs uppercase tracking-wider">Buy / Sell by Service</div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600 min-w-[560px]">
                <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px]">
                    <tr>
                        <th class="p-3.5">Service</th>
                        <th class="p-3.5 text-right">Sell</th>
                        <th class="p-3.5 text-right">Buy</th>
                        <th class="p-3.5 text-right">Profit</th>
                        <th class="p-3.5 text-right">Margin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono">
                    <?php foreach ([
                        ['Visas & Master Bookings', $t['visas'], 'PKR'],
                        ['Hotels (Vouchers, Bookings & Stays)', $t['hotels'], 'PKR'],
                        ['Transport', $t['transports'], 'PKR'],
                        ['Air Ticket Bookings', $t['tickets'], 'PKR'],
                    ] as [$name, $s, $cur]): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-3.5 font-sans font-bold text-slate-800"><?= $name ?> <span class="text-[10px] font-semibold text-slate-400"><?= $cur ?></span></td>
                            <td class="p-3.5 text-right font-bold text-slate-900"><?= $money($s['sell']) ?></td>
                            <td class="p-3.5 text-right text-rose-600 font-semibold"><?= $money($s['buy']) ?></td>
                            <td class="p-3.5 text-right font-black <?= $s['profit'] < 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= $money($s['profit']) ?></td>
                            <td class="p-3.5 text-right font-bold text-slate-700"><?= $s['margin'] ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-slate-900 text-white font-mono text-xs">
                    <tr>
                        <td class="p-3.5 font-sans font-bold">Total (All services, PKR)</td>
                        <td class="p-3.5 text-right font-bold"><?= $money($t['pkr']['sell']) ?></td>
                        <td class="p-3.5 text-right"><?= $money($t['pkr']['buy']) ?></td>
                        <td class="p-3.5 text-right font-black text-amber-300"><?= $money($t['pkr']['profit']) ?></td>
                        <td class="p-3.5 text-right"><?= $t['pkr']['margin'] ?>%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="px-5 py-2.5 text-[10px] text-slate-400 border-t border-slate-100">All services, including air ticket bookings, are reported in PKR.</p>
    </div>

    <!-- Breakdown by day (weekly) / by week (monthly) -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="bg-slate-50 px-5 py-3.5 border-b border-slate-200 font-bold text-slate-800 text-xs uppercase tracking-wider"><?= $mode === 'week' ? 'Day by Day' : 'Week by Week' ?></div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600 min-w-[680px]">
                <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px]">
                    <tr>
                        <th class="p-3">Period</th>
                        <th class="p-3 text-right">Sell (PKR)</th>
                        <th class="p-3 text-right">Buy (PKR)</th>
                        <th class="p-3 text-right">Profit (PKR)</th>
                        <th class="p-3 w-32"></th>
                        <th class="p-3 text-right">Tickets Sell (PKR)</th>
                        <th class="p-3 text-right">Tickets Profit (PKR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono">
                    <?php foreach ($report['rows'] as $r): $p = $r['pkr']['profit']; ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-3 font-sans font-semibold text-slate-800 whitespace-nowrap"><?= htmlspecialchars($r['label']) ?></td>
                            <td class="p-3 text-right font-bold text-slate-900"><?= $money($r['pkr']['sell']) ?></td>
                            <td class="p-3 text-right text-rose-600"><?= $money($r['pkr']['buy']) ?></td>
                            <td class="p-3 text-right font-black <?= $p < 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= $money($p) ?></td>
                            <td class="p-3">
                                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full <?= $p < 0 ? 'bg-rose-500' : 'bg-blue-600' ?>" style="width: <?= round(abs($p) / $maxProfit * 100) ?>%"></div>
                                </div>
                            </td>
                            <td class="p-3 text-right text-slate-700"><?= $money($r['tickets']['sell']) ?></td>
                            <td class="p-3 text-right font-bold <?= $r['tickets']['profit'] < 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= $money($r['tickets']['profit']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Outstanding balances (all time) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <a href="index.php?page=agent_ledger" class="block bg-gradient-to-br from-amber-50 to-white p-6 rounded-2xl border border-amber-200 shadow-sm hover:shadow-md transition">
            <div class="flex justify-between items-center">
                <span class="text-xs font-bold text-amber-900 uppercase">Receivable from Agents</span>
                <i class="fa-solid fa-user-clock text-amber-500 text-lg"></i>
            </div>
            <h4 class="text-2xl font-black text-amber-700 mt-2 font-mono"><?= $money($out['receivable']) ?> PKR</h4>
            <p class="text-xs text-amber-800/80 mt-1">All-time balance still due from B2B agents.</p>
        </a>
        <a href="index.php?page=vendor_ledger" class="block bg-gradient-to-br from-blue-50 to-white p-6 rounded-2xl border border-blue-200 shadow-sm hover:shadow-md transition">
            <div class="flex justify-between items-center">
                <span class="text-xs font-bold text-blue-900 uppercase">Payable to Vendors</span>
                <i class="fa-solid fa-handshake text-blue-600 text-lg"></i>
            </div>
            <h4 class="text-2xl font-black text-blue-800 mt-2 font-mono"><?= $money($out['payable']) ?> PKR</h4>
            <p class="text-xs text-blue-800/80 mt-1">All-time balance still owed to visa, hotel and transport vendors.</p>
        </a>
    </div>
</main>
