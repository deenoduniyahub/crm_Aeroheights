<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/AirTicketController.php';
require_once __DIR__ . '/../../config/Auth.php';

// ?page=tickets&new=1 or &id=N opens the upload / review form; otherwise list the saved tickets.
if (isset($_GET['new']) || isset($_GET['id'])) {
    require __DIR__ . '/form.php';
    return;
}

$search = trim((string)($_GET['q'] ?? ''));
$tickets = AirTicketController::getAll($search);
$canWrite = Auth::canWrite();

$fmtDate = static fn(?string $d): string => $d ? date('d M Y', strtotime($d)) : '—';
$statusCls = [
    'Confirmed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'Partially Confirmed' => 'bg-amber-50 text-amber-700 border-amber-200',
    'On Request' => 'bg-blue-50 text-blue-700 border-blue-200',
    'On Hold' => 'bg-orange-50 text-orange-700 border-orange-200',
    'Cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
];
?>

<main class="md:col-span-9 space-y-6">

    <!-- Header Bar -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col lg:flex-row items-center justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-ticket text-sky-500 mr-2"></i> Customized Air Tickets
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Upload any airline or portal ticket &mdash; it is read automatically and rebuilt in our branded design.</p>
        </div>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full lg:w-auto">
            <form method="get" class="flex items-center gap-2">
                <input type="hidden" name="page" value="tickets">
                <div class="relative flex-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search PNR, name, airline, route..." class="w-full sm:w-64 rounded-xl border border-slate-300 pl-8 pr-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-sky-400">
                </div>
                <?php if ($search !== ''): ?>
                    <a href="index.php?page=tickets" class="text-xs text-slate-500 hover:text-slate-700 px-2" title="Clear search"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </form>
            <?php if ($canWrite): ?>
                <a href="index.php?page=tickets&new=1" class="text-center bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold px-4 py-2 rounded-xl shadow-md shadow-sky-600/20 transition">
                    <i class="fa-solid fa-cloud-arrow-up mr-1"></i> Upload Ticket
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Saved Tickets -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="bg-slate-50 border-b border-slate-200 px-5 py-3 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-xs flex items-center"><i class="fa-solid fa-folder-open mr-2 text-sky-500"></i> Saved Tickets</h3>
            <span class="text-[10px] font-bold bg-sky-100 text-sky-800 px-2 py-0.5 rounded-full"><?= count($tickets) ?></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-900 text-white text-[10px] uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Ticket / PNR</th>
                        <th class="px-4 py-3 text-left">Airline</th>
                        <th class="px-4 py-3 text-left">Family Head</th>
                        <th class="px-4 py-3 text-left">Route &amp; Dates</th>
                        <th class="px-4 py-3 text-center">PAX</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-right">Customized</th>
                        <th class="px-4 py-3 text-right">Original</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (!$tickets): ?>
                        <tr><td colspan="8" class="px-4 py-12 text-center text-slate-400">
                            <?php if ($search !== ''): ?>
                                No tickets match your search.
                            <?php else: ?>
                                <i class="fa-solid fa-ticket text-3xl text-slate-200 block mb-2"></i>
                                No tickets yet. Click <b>Upload Ticket</b> to customize your first one.
                            <?php endif; ?>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($tickets as $t):
                        $id = (int)$t['id'];
                        $logo = AirTicketController::logoFor((string)$t['airline_code']); ?>
                        <tr class="hover:bg-sky-50/40" id="ticketRow<?= $id ?>">
                            <td class="px-4 py-3">
                                <div class="font-mono font-bold text-slate-800 text-[13px]"><?= htmlspecialchars($t['pnr'] ?: '—') ?></div>
                                <div class="text-[10px] text-slate-400"><?php if (!empty($t['is_booking'])): ?><a href="index.php?page=ticket_bookings" class="font-bold text-violet-600 hover:underline" title="Created in Ticket Booking">BOOKING</a> &middot; <?php endif; ?><?= htmlspecialchars($t['ticket_no']) ?> &middot; <?= $fmtDate(substr((string)$t['created_at'], 0, 10)) ?><?= $t['created_by'] ? ' &middot; ' . htmlspecialchars($t['created_by']) : '' ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($logo): ?>
                                    <img src="<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars((string)$t['airline_name']) ?>" class="h-5 w-auto max-w-[90px] object-contain">
                                <?php else: ?>
                                    <span class="font-semibold text-slate-700"><?= htmlspecialchars($t['airline_name'] ?: $t['airline_code'] ?: '—') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-700"><?= htmlspecialchars($t['family_head'] ?: '—') ?></td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="font-mono font-semibold text-slate-700"><?= htmlspecialchars($t['route'] ?: '—') ?></div>
                                <div class="text-[10px] text-slate-400"><?= $fmtDate($t['dep_date']) ?><?= $t['ret_date'] ? ' &rarr; ' . $fmtDate($t['ret_date']) : '' ?></div>
                            </td>
                            <td class="px-4 py-3 text-center font-bold text-slate-700"><?= (int)$t['pax_count'] ?></td>
                            <td class="px-4 py-3"><span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full border <?= $statusCls[$t['status']] ?? $statusCls['Confirmed'] ?>"><?= htmlspecialchars($t['status'] ?: 'Confirmed') ?></span></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="index.php?page=print_ticket&id=<?= $id ?>" target="_blank" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-sky-600 hover:bg-sky-50" title="Open customized ticket"><i class="fa-solid fa-eye"></i></a>
                                <a href="index.php?page=print_ticket&id=<?= $id ?>&download=1" target="_blank" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-orange-500 hover:bg-orange-50" title="Download customized PDF"><i class="fa-solid fa-file-arrow-down"></i></a>
                                <?php if ($canWrite): ?>
                                    <a href="index.php?page=tickets&id=<?= $id ?>" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-blue-600 hover:bg-blue-50" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <button type="button" onclick="deleteTicket(<?= $id ?>, '<?= htmlspecialchars(addslashes((string)($t['pnr'] ?: $t['ticket_no']))) ?>')" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-rose-500 hover:bg-rose-50" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <?php if ($t['original_path']): ?>
                                    <a href="index.php?api=air_ticket_original&id=<?= $id ?>" target="_blank" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100" title="Open original ticket"><i class="fa-regular fa-file-lines"></i></a>
                                    <a href="index.php?api=air_ticket_original&id=<?= $id ?>&download=1" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100" title="Download original ticket"><i class="fa-solid fa-download"></i></a>
                                <?php else: ?>
                                    <span class="text-slate-300">—</span>
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
async function deleteTicket(id, label) {
    if (!confirm('Delete ticket ' + label + '?')) return;
    const res = await apiRequest('index.php?api=delete_air_ticket', { id });
    if (res.success) {
        showToast('success', res.message);
        document.getElementById('ticketRow' + id)?.remove();
    } else {
        showToast('error', res.message);
    }
}
</script>
