<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/PackageQuotationController.php';
require_once __DIR__ . '/../../config/Auth.php';

// ?page=packages&new=1 or &id=N opens the builder form; otherwise show the saved quotations list.
if (isset($_GET['new']) || isset($_GET['id'])) {
    require __DIR__ . '/form.php';
    return;
}

$search = trim((string)($_GET['q'] ?? ''));
$quotations = PackageQuotationController::getAll($search);
$canWrite = Auth::canWrite();

$fmtDate = static fn(?string $d): string => $d ? date('d M Y', strtotime($d)) : '—';
?>

<main class="md:col-span-9 space-y-6">

    <!-- Header Bar -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col lg:flex-row items-center justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-suitcase-rolling text-orange-500 mr-2"></i> Complete Package Quotations
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Saved Umrah package quotations &mdash; edit, print or delete them any time.</p>
        </div>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full lg:w-auto">
            <form method="get" class="flex items-center gap-2">
                <input type="hidden" name="page" value="packages">
                <div class="relative flex-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search no., client, hotel..." class="w-full sm:w-60 rounded-xl border border-slate-300 pl-8 pr-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
                <?php if ($search !== ''): ?>
                    <a href="index.php?page=packages" class="text-xs text-slate-500 hover:text-slate-700 px-2" title="Clear search"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </form>
            <?php if ($canWrite): ?>
                <a href="index.php?page=packages&new=1" class="text-center bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold px-4 py-2 rounded-xl shadow-md shadow-orange-500/20 transition">
                    <i class="fa-solid fa-plus mr-1"></i> New Quotation
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Saved Quotations -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="bg-slate-50 border-b border-slate-200 px-5 py-3 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-xs flex items-center"><i class="fa-solid fa-folder-open mr-2 text-orange-500"></i> Saved Quotations</h3>
            <span class="text-[10px] font-bold bg-orange-100 text-orange-800 px-2 py-0.5 rounded-full"><?= count($quotations) ?></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-900 text-white text-[10px] uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Quotation</th>
                        <th class="px-4 py-3 text-left">Client</th>
                        <th class="px-4 py-3 text-left">Hotels</th>
                        <th class="px-4 py-3 text-left">Travel</th>
                        <th class="px-4 py-3 text-center">PAX</th>
                        <th class="px-4 py-3 text-right">Total (PKR)</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (!$quotations): ?>
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">
                            <?= $search !== '' ? 'No quotations match your search.' : 'No quotations saved yet. Click "New Quotation" to create one.' ?>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($quotations as $q): ?>
                        <tr class="hover:bg-orange-50/40" id="quoteRow<?= (int)$q['id'] ?>">
                            <td class="px-4 py-3">
                                <div class="font-mono font-bold text-slate-800"><?= htmlspecialchars($q['quote_no']) ?></div>
                                <div class="text-[10px] text-slate-400"><?= $fmtDate($q['quote_date']) ?><?= $q['created_by'] ? ' &middot; ' . htmlspecialchars($q['created_by']) : '' ?></div>
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-700"><?= htmlspecialchars($q['client_name'] ?: 'Client') ?></td>
                            <td class="px-4 py-3 text-slate-600">
                                <?php foreach ($q['hotels'] as $ht): ?>
                                    <div><span class="text-slate-400"><?= htmlspecialchars($ht['city']) ?>:</span> <?= htmlspecialchars($ht['name']) ?></div>
                                <?php endforeach; ?>
                                <?php if (!$q['hotels']): ?><span class="text-slate-300">—</span><?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                                <?php if ($q['dep_sector']): ?><div class="font-semibold"><?= htmlspecialchars($q['dep_sector']) ?></div><?php endif; ?>
                                <div class="text-[10px] text-slate-400"><?= $fmtDate($q['dep_date']) ?> &rarr; <?= $fmtDate($q['ret_date']) ?></div>
                            </td>
                            <td class="px-4 py-3 text-center text-slate-600 whitespace-nowrap">A:<?= (int)$q['adults'] ?> C:<?= (int)$q['children'] ?> I:<?= (int)$q['infants'] ?></td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-orange-600 whitespace-nowrap"><?= number_format((float)$q['total_price']) ?>/-</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="index.php?page=print_package&id=<?= (int)$q['id'] ?>" target="_blank" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100" title="Print"><i class="fa-solid fa-print"></i></a>
                                <?php if ($canWrite): ?>
                                    <a href="index.php?page=packages&id=<?= (int)$q['id'] ?>" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-blue-600 hover:bg-blue-50" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <button type="button" onclick="deleteQuotation(<?= (int)$q['id'] ?>, '<?= htmlspecialchars(addslashes($q['quote_no'])) ?>')" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-rose-500 hover:bg-rose-50" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
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
async function deleteQuotation(id, quoteNo) {
    if (!confirm('Delete quotation ' + quoteNo + '?')) return;
    const res = await apiRequest('index.php?api=delete_package_quotation', { id });
    if (res.success) {
        showToast('success', res.message);
        document.getElementById('quoteRow' + id)?.remove();
    } else {
        showToast('error', res.message);
    }
}
</script>
