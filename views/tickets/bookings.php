<?php
declare(strict_types=1);

/**
 * Tickets -> Ticket Booking. "Smartly Book Ticket" uploads the original ticket and the passports; Gemini reads
 * them and the booking (travellers, passport numbers, PNR, route, flights) is created automatically and also
 * appears in the Tickets tab. The team only fills in client / supplier and the PKR buy & sell rates.
 */

require_once __DIR__ . '/../../controllers/TicketBookingController.php';
require_once __DIR__ . '/../../controllers/AdminController.php';
require_once __DIR__ . '/../../config/Auth.php';

$agents = AdminController::getAgents();
$vendors = AdminController::getVendors();
$filters = [
    'search' => trim((string)($_GET['search'] ?? '')),
    'agent_id' => (int)($_GET['agent_id'] ?? 0) ?: '',
    'vendor_id' => (int)($_GET['vendor_id'] ?? 0) ?: '',
    'date_from' => (string)($_GET['date_from'] ?? ''),
    'date_to' => (string)($_GET['date_to'] ?? ''),
    'booked_from' => (string)($_GET['booked_from'] ?? ''),
    'booked_to' => (string)($_GET['booked_to'] ?? ''),
];
$rows = TicketBookingController::getAll($filters);
$canWrite = Auth::canWrite();
$aiReady = TicketAiReader::isEnabled();

$totBuy = array_sum(array_map(static fn($r) => (float)$r['buy_pkr'], $rows));
$totSell = array_sum(array_map(static fn($r) => (float)$r['sell_pkr'], $rows));
$pkr = static fn(float $v): string => number_format($v, 0);
$fmtDate = static fn(?string $d): string => $d ? date('d M Y', strtotime($d)) : '—';
$h = static fn($v): string => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$exportQuery = http_build_query(array_filter($filters, static fn($v) => $v !== '' && $v !== 0));
$fieldCls = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-violet-400';
?>

<main class="md:col-span-9 space-y-6">

    <!-- Header Bar -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col lg:flex-row items-center justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-wand-magic-sparkles text-violet-500 mr-2"></i> Ticket Booking
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Upload the original ticket and passports &mdash; travellers, passport numbers, PNR, route and flights are filled in automatically. You only add the rates.</p>
        </div>
        <div class="flex flex-wrap gap-2 justify-end">
            <a href="index.php?api=export_ticket_bookings<?= $exportQuery ? '&' . $h($exportQuery) : '' ?>" class="bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold px-4 py-2.5 rounded-xl"><i class="fa-solid fa-file-csv mr-1.5"></i> Export CSV</a>
            <?php if ($canWrite): ?>
                <button type="button" onclick="openSmartBook()" class="bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow-md shadow-violet-600/25">
                    <i class="fa-solid fa-wand-magic-sparkles mr-1.5"></i> Smartly Book Ticket
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$aiReady): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl px-5 py-3 text-xs"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Gemini AI keys are not configured on this server, so Smart Booking cannot read files yet.</div>
    <?php endif; ?>

    <!-- Filters & totals -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm space-y-3">
        <form method="GET" action="index.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2.5 text-xs">
            <input type="hidden" name="page" value="ticket_bookings">
            <input type="text" name="search" value="<?= $h($filters['search']) ?>" placeholder="Name, passport, PNR, route, flight" class="lg:col-span-2 border border-slate-300 rounded-xl p-2.5 bg-slate-50">
            <select name="agent_id" class="border border-slate-300 rounded-xl p-2.5 bg-slate-50"><option value="">All Clients</option><?php foreach ($agents as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$filters['agent_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= $h($a['name']) ?></option><?php endforeach; ?></select>
            <select name="vendor_id" class="border border-slate-300 rounded-xl p-2.5 bg-slate-50"><option value="">All Suppliers</option><?php foreach ($vendors as $v): ?><option value="<?= (int)$v['id'] ?>" <?= (int)$filters['vendor_id'] === (int)$v['id'] ? 'selected' : '' ?>><?= $h($v['name']) ?></option><?php endforeach; ?></select>
            <label class="flex flex-col text-[10px] font-bold uppercase text-slate-400">Booking date from<input type="date" name="booked_from" value="<?= $h($filters['booked_from']) ?>" class="mt-0.5 border border-slate-300 rounded-xl p-2.5 font-mono text-xs text-slate-700 normal-case"></label>
            <label class="flex flex-col text-[10px] font-bold uppercase text-slate-400">Booking date to<input type="date" name="booked_to" value="<?= $h($filters['booked_to']) ?>" class="mt-0.5 border border-slate-300 rounded-xl p-2.5 font-mono text-xs text-slate-700 normal-case"></label>
            <label class="flex flex-col text-[10px] font-bold uppercase text-slate-400">Departure from<input type="date" name="date_from" value="<?= $h($filters['date_from']) ?>" class="mt-0.5 border border-slate-300 rounded-xl p-2.5 font-mono text-xs text-slate-700 normal-case"></label>
            <label class="flex flex-col text-[10px] font-bold uppercase text-slate-400">Departure to<input type="date" name="date_to" value="<?= $h($filters['date_to']) ?>" class="mt-0.5 border border-slate-300 rounded-xl p-2.5 font-mono text-xs text-slate-700 normal-case"></label>
            <div class="lg:col-span-4 flex gap-2 justify-end items-end">
                <button class="bg-slate-800 text-white rounded-xl px-4 py-2.5 font-semibold"><i class="fa-solid fa-filter mr-1"></i> Apply Filters</button>
                <a href="index.php?page=ticket_bookings" class="px-3 py-2.5 bg-slate-100 hover:bg-slate-200 rounded-xl flex items-center" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 pt-3 border-t border-slate-100">
            <div class="rounded-xl bg-slate-50 px-4 py-2.5"><div class="text-[10px] font-bold uppercase text-slate-400">Bookings</div><div class="text-base font-bold text-slate-800"><?= count($rows) ?></div></div>
            <div class="rounded-xl bg-rose-50 px-4 py-2.5"><div class="text-[10px] font-bold uppercase text-rose-400">Total Buy (PKR)</div><div class="text-base font-bold font-mono text-rose-700"><?= $pkr($totBuy) ?></div></div>
            <div class="rounded-xl bg-indigo-50 px-4 py-2.5"><div class="text-[10px] font-bold uppercase text-indigo-400">Total Sell (PKR)</div><div class="text-base font-bold font-mono text-indigo-700"><?= $pkr($totSell) ?></div></div>
            <div class="rounded-xl bg-emerald-50 px-4 py-2.5"><div class="text-[10px] font-bold uppercase text-emerald-500">Profit (PKR)</div><div class="text-base font-bold font-mono <?= $totSell - $totBuy < 0 ? 'text-rose-700' : 'text-emerald-700' ?>"><?= $pkr($totSell - $totBuy) ?></div></div>
        </div>
    </div>

    <!-- Bookings table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-700 border-b border-slate-200 uppercase font-bold text-[10px]">
                    <tr>
                        <th class="p-3">Booking</th>
                        <th class="p-3">Travellers</th>
                        <th class="p-3">Passport No.</th>
                        <th class="p-3">PNR</th>
                        <th class="p-3">Route</th>
                        <th class="p-3">Flight No.</th>
                        <th class="p-3">Client</th>
                        <th class="p-3 text-right">Buy (PKR)</th>
                        <th class="p-3 text-right">Sell (PKR)</th>
                        <th class="p-3 text-center">Original Ticket</th>
                        <th class="p-3 text-center">Updated Ticket</th>
                        <th class="p-3 text-center">Passports</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (!$rows): ?>
                        <tr><td colspan="13" class="p-10 text-center text-slate-400">
                            <i class="fa-solid fa-wand-magic-sparkles text-3xl text-slate-200 block mb-2"></i>
                            <?= $filters['search'] !== '' || $filters['agent_id'] || $filters['vendor_id'] || $filters['date_from'] || $filters['date_to'] || $filters['booked_from'] || $filters['booked_to'] ? 'No ticket bookings match these filters.' : 'No ticket bookings yet. Click <b>Smartly Book Ticket</b> to create the first one.' ?>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r):
                        $id = (int)$r['id'];
                        $profit = (float)$r['sell_pkr'] - (float)$r['buy_pkr'];
                        $rowJson = $h(json_encode([
                            'id' => $id, 'ticket_no' => $r['ticket_no'], 'pnr' => $r['pnr'], 'agent_id' => $r['agent_id'], 'vendor_id' => $r['vendor_id'],
                            'booking_date' => $r['booking_date'], 'buy_pkr' => (float)$r['buy_pkr'], 'sell_pkr' => (float)$r['sell_pkr'], 'booking_remarks' => $r['booking_remarks'],
                        ], JSON_UNESCAPED_UNICODE)); ?>
                        <tr class="hover:bg-violet-50/30 align-top" id="tbRow<?= $id ?>" data-booking="<?= $rowJson ?>">
                            <td class="p-3 whitespace-nowrap">
                                <div class="font-mono font-bold text-slate-800"><?= $h($r['ticket_no']) ?></div>
                                <div class="text-[10px] text-slate-500" title="Booking date"><i class="fa-regular fa-calendar mr-0.5"></i><?= $fmtDate($r['booking_date']) ?></div>
                                <?php if (!empty($r['ai_notes'])): ?>
                                    <button type="button" onclick="showNotes(<?= $id ?>)" class="mt-1 text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded-full px-2 py-0.5" title="<?= $h($r['ai_notes']) ?>"><i class="fa-solid fa-triangle-exclamation mr-0.5"></i> Check</button>
                                    <template id="tbNotes<?= $id ?>"><?= $h($r['ai_notes']) ?></template>
                                <?php endif; ?>
                            </td>
<?php
                            // Family Head first and always visible; the others fold away behind "+N more".
                            $pax = $r['passengers'];
                            usort($pax, static fn($a, $b) => ($b['name'] === $r['family_head']) <=> ($a['name'] === $r['family_head']));
                            $more = count($pax) - 1;
                            $hiddenMissing = count(array_filter(array_slice($pax, 1), static fn($p) => $p['passport'] === ''));
                            // A search that matches one of the folded travellers opens the row by itself.
                            $q = strtoupper($filters['search']);
                            $open = $q !== '' && (bool)array_filter(array_slice($pax, 1), static fn($p) => str_contains($p['name'], $q) || str_contains($p['passport'], $q));
                            $extraCls = 'tb-extra' . ($open ? '' : ' hidden');
                            ?>
                            <td class="p-3">
                                <?php foreach ($pax as $n => $p): ?>
                                    <div class="font-semibold text-slate-900 whitespace-nowrap leading-5 <?= $n ? $extraCls : '' ?>"><?= $h(trim($p['title'] . ' ' . $p['name'])) ?><?= !$n && $more ? ' <span class="text-[9px] font-bold text-sky-600" title="Family Head">HEAD</span>' : '' ?></div>
                                <?php endforeach; ?>
                                <?php if ($more): ?>
                                    <button type="button" onclick="tbTogglePax(<?= $id ?>)" class="tb-more mt-1 inline-flex items-center gap-1 text-[10px] font-bold text-violet-700 bg-violet-50 hover:bg-violet-100 border border-violet-200 rounded-full px-2 py-0.5 transition" title="Show / hide all travellers">
                                        <span class="tb-more-label <?= $open ? 'hidden' : '' ?>">+<?= $more ?> more</span>
                                        <?php if ($hiddenMissing): ?><span class="tb-more-label h-1.5 w-1.5 rounded-full bg-rose-500 <?= $open ? 'hidden' : '' ?>" title="<?= $hiddenMissing ?> without passport number"></span><?php endif; ?>
                                        <span class="tb-less-label <?= $open ? '' : 'hidden' ?>">Show less</span>
                                        <i class="fa-solid fa-chevron-down text-[8px] transition-transform <?= $open ? 'rotate-180' : '' ?>"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 font-mono">
                                <?php foreach ($pax as $n => $p): ?>
                                    <div class="leading-5 whitespace-nowrap <?= $n ? $extraCls : '' ?> <?= $p['passport'] ? 'text-slate-800' : 'text-rose-400' ?>"><?= $p['passport'] ? $h($p['passport']) : 'missing' ?></div>
                                <?php endforeach; ?>
                            </td>
                            <td class="p-3 font-mono font-bold text-slate-800 whitespace-nowrap"><?= $h($r['pnr'] ?: '—') ?></td>
                            <td class="p-3 whitespace-nowrap">
                                <div class="font-mono font-semibold text-slate-700"><?= $h($r['route'] ?: '—') ?></div>
                                <div class="text-[10px] text-slate-400"><?= $fmtDate($r['dep_date']) ?><?= $r['ret_date'] ? ' &rarr; ' . $fmtDate($r['ret_date']) : '' ?></div>
                            </td>
                            <td class="p-3 font-mono text-indigo-600 whitespace-nowrap"><?php foreach ($r['flights'] as $f): ?><div class="leading-5"><?= $h($f) ?></div><?php endforeach; ?></td>
                            <td class="p-3">
                                <div class="font-semibold text-slate-700"><?= $h($r['agent_name'] ?: '—') ?></div>
                                <?php if ($r['vendor_name']): ?><div class="text-[10px] text-slate-400">Supplier: <?= $h($r['vendor_name']) ?></div><?php endif; ?>
                            </td>
                            <td class="p-3 text-right">
                                <?php if ($canWrite): ?>
                                    <input type="number" min="0" step="1" value="<?= (float)$r['buy_pkr'] ?: '' ?>" placeholder="0" data-id="<?= $id ?>" data-field="buy_pkr" onchange="saveRate(this)" class="rate-input w-24 text-right font-mono text-rose-600 border border-slate-200 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-rose-300">
                                <?php else: ?>
                                    <span class="font-mono text-rose-600"><?= $pkr((float)$r['buy_pkr']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-right">
                                <?php if ($canWrite): ?>
                                    <input type="number" min="0" step="1" value="<?= (float)$r['sell_pkr'] ?: '' ?>" placeholder="0" data-id="<?= $id ?>" data-field="sell_pkr" onchange="saveRate(this)" class="rate-input w-24 text-right font-mono font-bold text-indigo-700 border border-slate-200 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                                <?php else: ?>
                                    <span class="font-mono font-bold text-indigo-700"><?= $pkr((float)$r['sell_pkr']) ?></span>
                                <?php endif; ?>
                                <div id="tbProfit<?= $id ?>" class="text-[10px] mt-1 font-mono <?= $profit < 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= ((float)$r['sell_pkr'] || (float)$r['buy_pkr']) ? 'Profit ' . $pkr($profit) : '' ?></div>
                            </td>
                            <td class="p-3 text-center whitespace-nowrap">
                                <?php if ($r['original_path']): ?>
                                    <a href="index.php?api=air_ticket_original&id=<?= $id ?>" target="_blank" class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100" title="Open original ticket"><i class="fa-regular fa-file-lines"></i></a>
                                    <a href="index.php?api=air_ticket_original&id=<?= $id ?>&download=1" class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100" title="Download original ticket"><i class="fa-solid fa-download"></i></a>
                                <?php else: ?><span class="text-slate-300">—</span><?php endif; ?>
                            </td>
                            <td class="p-3 text-center whitespace-nowrap">
                                <a href="index.php?page=print_ticket&id=<?= $id ?>" target="_blank" class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-sky-600 hover:bg-sky-50" title="Open updated ticket"><i class="fa-solid fa-eye"></i></a>
                                <a href="index.php?page=print_ticket&id=<?= $id ?>&download=1" target="_blank" class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-orange-500 hover:bg-orange-50" title="Download updated ticket (PDF)"><i class="fa-solid fa-file-arrow-down"></i></a>
                            </td>
                            <td class="p-3 text-center whitespace-nowrap">
                                <?php if ($r['passport_list']): foreach ($r['passport_list'] as $i => $pf):
                                    $holders = implode(', ', array_map(static fn($p) => trim($p['given_names'] . ' ' . $p['surname']) . ' (' . $p['passport_no'] . ')', (array)($pf['passports'] ?? []))); ?>
                                    <a href="index.php?api=ticket_booking_passport&id=<?= $id ?>&i=<?= (int)$i ?>" target="_blank" class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-amber-600 hover:bg-amber-50" title="<?= $h(($pf['name'] ?? 'Passport') . ($holders ? ' — ' . $holders : '')) ?>"><i class="fa-solid fa-passport"></i></a>
                                <?php endforeach; else: ?><span class="text-slate-300">—</span><?php endif; ?>
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <?php if ($canWrite): ?>
                                    <button type="button" onclick="openEditBooking(tbRowData(<?= $id ?>))" class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-indigo-600 hover:bg-indigo-50" title="Edit client, supplier & rates"><i class="fa-solid fa-pen-to-square"></i></button>
                                    <a href="index.php?page=tickets&id=<?= $id ?>&back=ticket_bookings" class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-sky-600 hover:bg-sky-50" title="Edit ticket details (travellers, flights)"><i class="fa-solid fa-ticket"></i></a>
                                    <button type="button" onclick="deleteBooking(<?= $id ?>, '<?= $h(addslashes((string)($r['pnr'] ?: $r['ticket_no']))) ?>')" class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
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
const TB_AGENTS = <?= json_encode(array_map(static fn($a) => ['id' => (int)$a['id'], 'name' => $a['name']], $agents), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const TB_VENDORS = <?= json_encode(array_map(static fn($v) => ['id' => (int)$v['id'], 'name' => $v['name']], $vendors), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const TB_FIELD = <?= json_encode($fieldCls) ?>;
const TB_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const TB_MAX = 10485760;

// "+N more" opens the other travellers (names and passport numbers together); clicking again folds them away.
function tbTogglePax(id) {
    const row = document.getElementById('tbRow' + id);
    const open = row.querySelector('.tb-extra').classList.contains('hidden');
    row.querySelectorAll('.tb-extra').forEach(el => el.classList.toggle('hidden', !open));
    row.querySelectorAll('.tb-more-label').forEach(el => el.classList.toggle('hidden', open));
    row.querySelector('.tb-less-label').classList.toggle('hidden', !open);
    row.querySelector('.tb-more i').classList.toggle('rotate-180', open);
}
function tbRowData(id) { return JSON.parse(document.getElementById('tbRow' + id).dataset.booking); }
function tbEsc(v) { const d = document.createElement('div'); d.textContent = v ?? ''; return d.innerHTML; }
function tbOptions(list, selected, empty) {
    return `<option value="">${empty}</option>` + list.map(x => `<option value="${x.id}" ${Number(selected) === x.id ? 'selected' : ''}>${tbEsc(x.name)}</option>`).join('');
}
function tbModal(inner) {
    document.getElementById('dynamicModalContainer').innerHTML =
        `<div class="fixed inset-0 bg-slate-900/70 z-50 flex items-start justify-center p-3 overflow-y-auto"><div class="bg-white rounded-2xl max-w-2xl w-full p-5 shadow-2xl my-8">${inner}</div></div>`;
}
function tbRatesHtml(b = {}) {
    const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    return `<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div><label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Booking Date</label><input id="tb_date" type="date" required class="${TB_FIELD} font-mono" value="${tbEsc(b.booking_date || today)}"></div>
        <div class="hidden sm:block"></div>
        <div><label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Client (Agent)</label><select id="tb_agent" class="${TB_FIELD}">${tbOptions(TB_AGENTS, b.agent_id, 'Select client (optional)')}</select></div>
        <div><label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Supplier (Vendor)</label><select id="tb_vendor" class="${TB_FIELD}">${tbOptions(TB_VENDORS, b.vendor_id, 'Select supplier (optional)')}</select></div>
        <div><label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Buy (PKR)</label><input id="tb_buy" type="number" min="0" step="1" class="${TB_FIELD} font-mono" value="${b.buy_pkr || ''}" placeholder="0" oninput="tbProfitHint()"></div>
        <div><label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Sell (PKR)</label><input id="tb_sell" type="number" min="0" step="1" class="${TB_FIELD} font-mono" value="${b.sell_pkr || ''}" placeholder="0" oninput="tbProfitHint()"></div>
        <div class="sm:col-span-2"><label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Remarks</label><input id="tb_remarks" class="${TB_FIELD}" maxlength="500" value="${tbEsc(b.booking_remarks || '')}"></div>
    </div><div id="tb_profit" class="text-[11px] font-mono text-right mt-1"></div>`;
}
function tbProfitHint() {
    const b = parseFloat(document.getElementById('tb_buy')?.value) || 0, s = parseFloat(document.getElementById('tb_sell')?.value) || 0;
    const el = document.getElementById('tb_profit');
    if (el) { el.textContent = (b || s) ? 'Profit: ' + (s - b).toLocaleString() + ' PKR' : ''; el.className = 'text-[11px] font-mono text-right mt-1 ' + (s - b < 0 ? 'text-rose-600' : 'text-emerald-600'); }
}
function tbDropZone(id, icon, title, hint, multiple) {
    return `<label class="block border-2 border-dashed border-slate-300 hover:border-violet-400 rounded-2xl p-4 text-center cursor-pointer bg-slate-50/60 transition" id="${id}Zone"
                ondragover="event.preventDefault();this.classList.add('border-violet-500')" ondragleave="this.classList.remove('border-violet-500')"
                ondrop="event.preventDefault();this.classList.remove('border-violet-500');tbSetFiles('${id}', event.dataTransfer.files)">
        <i class="fa-solid ${icon} text-2xl text-violet-500"></i>
        <div class="text-xs font-bold text-slate-800 mt-1">${title}</div>
        <div class="text-[10px] text-slate-500">${hint}</div>
        <input type="file" id="${id}" class="hidden" accept="application/pdf,image/jpeg,image/png,image/webp" ${multiple ? 'multiple' : ''} onchange="tbSetFiles('${id}', this.files)">
        <div id="${id}List" class="mt-2 text-[11px] text-left space-y-0.5"></div>
    </label>`;
}
const tbFiles = { tb_ticket: [], tb_passports: [] };
function tbSetFiles(id, list) {
    const ok = [];
    for (const f of Array.from(list || [])) {
        if (f.size > TB_MAX) { showToast('error', f.name + ' is larger than 10 MB.'); continue; }
        if (!/^(application\/pdf|image\/(jpeg|png|webp))$/.test(f.type) && !/\.(pdf|jpe?g|png|webp)$/i.test(f.name)) { showToast('error', f.name + ': only PDF, JPG, PNG or WEBP.'); continue; }
        ok.push(f);
    }
    tbFiles[id] = id === 'tb_ticket' ? ok.slice(0, 1) : tbFiles[id].concat(ok).slice(0, 20);
    tbRenderFiles(id);
}
function tbRemoveFile(id, i) { tbFiles[id].splice(i, 1); tbRenderFiles(id); }
function tbRenderFiles(id) {
    document.getElementById(id + 'List').innerHTML = tbFiles[id].map((f, i) =>
        `<div class="flex items-center justify-between gap-2 bg-white border border-slate-200 rounded-lg px-2 py-1"><span class="truncate"><i class="fa-regular fa-file mr-1 text-slate-400"></i>${tbEsc(f.name)}</span><button type="button" onclick="event.preventDefault();tbRemoveFile('${id}',${i})" class="text-rose-500"><i class="fa-solid fa-xmark"></i></button></div>`).join('');
}

function openSmartBook(existing = null) {
    tbFiles.tb_ticket = []; tbFiles.tb_passports = [];
    const edit = !!existing;
    tbModal(`<div class="flex justify-between items-start border-b pb-3 mb-4">
            <div><h3 class="font-bold text-slate-800"><i class="fa-solid fa-wand-magic-sparkles text-violet-500 mr-1"></i> ${edit ? 'Add Passports / Replace Ticket — ' + tbEsc(existing.ticket_no) : 'Smartly Book Ticket'}</h3>
            <p class="text-[11px] text-slate-500">Gemini AI reads the ticket and every passport together, matches each passport to its traveller and creates the booking.</p></div>
            <button onclick="closeActiveModal()" class="text-slate-400"><i class="fa-solid fa-xmark text-lg"></i></button></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            ${tbDropZone('tb_ticket', 'fa-plane-departure', 'Original Ticket' + (edit ? ' (optional)' : ''), 'PDF or image of the airline ticket', false)}
            ${tbDropZone('tb_passports', 'fa-passport', 'Passports', 'PDF(s) or images — one or many, any order', true)}
        </div>
        ${edit ? '' : `<div class="mt-4 pt-4 border-t border-slate-100"><div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Optional — can be added later</div>${tbRatesHtml()}</div>`}
        <div id="tb_status" class="hidden mt-4 rounded-xl px-4 py-3 text-xs"></div>
        <div class="flex justify-end gap-2 mt-4">
            <button type="button" onclick="closeActiveModal()" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-semibold">Cancel</button>
            <button type="button" id="tb_go" onclick="runSmartBook(${edit ? existing.id : 0})" class="px-5 py-2 bg-gradient-to-r from-violet-600 to-indigo-600 text-white rounded-xl text-xs font-bold"><i class="fa-solid fa-bolt mr-1"></i> ${edit ? 'Read & Update' : 'Create Automatically'}</button>
        </div>`);
}

function tbStatus(cls, html) {
    const el = document.getElementById('tb_status');
    el.className = 'mt-4 rounded-xl px-4 py-3 text-xs ' + cls;
    el.innerHTML = html;
}

// Files already read successfully are not sent to the AI again when the user presses Try Again.
const tbReadCache = new WeakMap();
async function tbReadFile(kind, file) {
    if (tbReadCache.has(file)) return tbReadCache.get(file);
    let data = { success: false, message: 'not read' };
    for (let attempt = 0; attempt < 3; attempt++) {
        if (attempt) await new Promise(r => setTimeout(r, attempt * 6000));
        const fd = new FormData();
        fd.append('kind', kind);
        fd.append('file', file);
        try {
            const res = await fetch('index.php?api=read_booking_file', { method: 'POST', headers: { 'X-CSRF-Token': TB_CSRF }, body: fd });
            data = await res.json().catch(() => ({ success: false, message: 'Unexpected server response (HTTP ' + res.status + ').' }));
        } catch (e) {
            data = { success: false, message: 'Network error: ' + e.message };
        }
        if (data.success) { tbReadCache.set(file, data); break; }
        if (/supported|10 MB|No file/i.test(data.message || '')) break; // retrying will not help
    }
    return data;
}

async function runSmartBook(id) {
    const ticket = tbFiles.tb_ticket[0], passports = tbFiles.tb_passports;
    if (!id && !ticket) { showToast('error', 'Please add the original ticket.'); return; }
    if (id && !ticket && !passports.length) { showToast('error', 'Add a ticket or at least one passport.'); return; }
    const fd = new FormData();
    if (id) fd.append('id', id);
    if (ticket) fd.append('ticket', ticket);
    passports.forEach(f => fd.append('passports[]', f));
    if (!id) {
        fd.append('agent_id', document.getElementById('tb_agent').value);
        fd.append('vendor_id', document.getElementById('tb_vendor').value);
        fd.append('buy_pkr', document.getElementById('tb_buy').value);
        fd.append('sell_pkr', document.getElementById('tb_sell').value);
        fd.append('booking_remarks', document.getElementById('tb_remarks').value);
        fd.append('booking_date', document.getElementById('tb_date').value);
    }
    const btn = document.getElementById('tb_go');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Reading…';
    // 1) Each file is read in its own short request (the host stops any request after ~58s), 3 at a time.
    const jobs = [];
    if (ticket) jobs.push({ kind: 'ticket', file: ticket });
    passports.forEach(f => jobs.push({ kind: 'passport', file: f }));
    let done = 0;
    const progress = () => tbStatus('bg-violet-50 text-violet-800', `<i class="fa-solid fa-robot mr-1"></i> Gemini is reading your files… <b>${done} / ${jobs.length}</b> done${ticket ? '' : ' (passports)'}.
        <div class="h-1.5 bg-violet-100 rounded-full mt-2 overflow-hidden"><div class="h-full bg-violet-500 transition-all" style="width:${Math.round(done / jobs.length * 100)}%"></div></div>`);
    progress();
    let cursor = 0;
    const worker = async () => {
        while (cursor < jobs.length) {
            const job = jobs[cursor++];
            job.result = await tbReadFile(job.kind, job.file);
            done++;
            progress();
        }
    };
    await Promise.all([worker(), worker(), worker()]);

    const failTicket = ticket && !jobs[0].result.success;
    if (failTicket) {
        tbStatus('bg-rose-50 text-rose-800', '<i class="fa-solid fa-circle-xmark mr-1"></i> Could not read the ticket: ' + tbEsc(jobs[0].result.message || 'unknown error'));
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-rotate-right mr-1"></i> Try Again';
        return;
    }

    // 2) Save: files plus what was read (no AI calls in this request).
    tbStatus('bg-violet-50 text-violet-800', '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Creating the booking…');
    fd.append('reads', JSON.stringify({
        ticket: ticket ? jobs[0].result.ticket : null,
        passports: jobs.filter(j => j.kind === 'passport').map(j => ({ success: !!j.result.success, passports: j.result.passports || [], message: j.result.message || '' })),
    }));
    let data;
    try {
        const res = await fetch('index.php?api=smart_book_ticket', { method: 'POST', headers: { 'X-CSRF-Token': TB_CSRF }, body: fd });
        data = await res.json().catch(() => ({ success: false, message: 'Unexpected server response (HTTP ' + res.status + ').' }));
    } catch (e) {
        data = { success: false, message: 'Network error: ' + e.message };
    }

    if (!data.success) {
        tbStatus('bg-rose-50 text-rose-800', '<i class="fa-solid fa-circle-xmark mr-1"></i> ' + tbEsc(data.message || 'Smart booking failed.'));
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-rotate-right mr-1"></i> Try Again';
        return;
    }
    const warnings = data.warnings || [];
    if (!warnings.length) {
        showToast('success', data.message);
        location.reload();
        return;
    }
    tbStatus('bg-amber-50 text-amber-900', `<div class="font-bold mb-1"><i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> ${tbEsc(data.message)}</div>
        <div class="font-semibold mt-2">Please check:</div><ul class="list-disc ml-5 mt-1 space-y-0.5">${warnings.map(w => '<li>' + tbEsc(w) + '</li>').join('')}</ul>`);
    btn.disabled = false;
    btn.innerHTML = 'Done';
    btn.onclick = () => location.reload();
}

function openEditBooking(b) {
    tbModal(`<div class="flex justify-between items-start border-b pb-3 mb-4">
            <div><h3 class="font-bold text-slate-800">Edit Ticket Booking ${tbEsc(b.ticket_no)}</h3><p class="text-[11px] text-slate-500">PNR ${tbEsc(b.pnr || '—')} · rates are in PKR and never printed on the ticket.</p></div>
            <button onclick="closeActiveModal()" class="text-slate-400"><i class="fa-solid fa-xmark text-lg"></i></button></div>
        ${tbRatesHtml(b)}
        <div class="flex flex-wrap justify-between gap-2 mt-5">
            <div class="flex gap-2">
                <button type="button" onclick='openSmartBook(${JSON.stringify({ id: b.id, ticket_no: b.ticket_no })})' class="px-3 py-2 bg-violet-50 text-violet-700 rounded-xl text-xs font-bold"><i class="fa-solid fa-passport mr-1"></i> Add Passports / Replace Ticket</button>
                <a href="index.php?page=tickets&id=${b.id}&back=ticket_bookings" class="px-3 py-2 bg-sky-50 text-sky-700 rounded-xl text-xs font-bold"><i class="fa-solid fa-ticket mr-1"></i> Edit Travellers &amp; Flights</a>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="closeActiveModal()" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-semibold">Cancel</button>
                <button type="button" onclick="saveEditBooking(${b.id})" class="px-5 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">Save</button>
            </div>
        </div>`);
    tbProfitHint();
}

async function saveEditBooking(id) {
    const res = await apiRequest('index.php?api=save_ticket_booking', {
        id,
        agent_id: document.getElementById('tb_agent').value,
        vendor_id: document.getElementById('tb_vendor').value,
        buy_pkr: document.getElementById('tb_buy').value,
        sell_pkr: document.getElementById('tb_sell').value,
        booking_remarks: document.getElementById('tb_remarks').value,
        booking_date: document.getElementById('tb_date').value,
    });
    if (res.success) { showToast('success', res.message); location.reload(); }
    else showToast('error', res.message);
}

// Inline Buy / Sell: saved as soon as the value changes; the other fields are kept as they are.
async function saveRate(input) {
    const id = Number(input.dataset.id);
    const row = document.getElementById('tbRow' + id);
    const b = tbRowData(id);
    const buy = row.querySelector('[data-field="buy_pkr"]').value, sell = row.querySelector('[data-field="sell_pkr"]').value;
    input.classList.add('opacity-50');
    const res = await apiRequest('index.php?api=save_ticket_booking', { id, booking_date: b.booking_date, agent_id: b.agent_id, vendor_id: b.vendor_id, buy_pkr: buy, sell_pkr: sell, booking_remarks: b.booking_remarks });
    input.classList.remove('opacity-50');
    if (!res.success) { showToast('error', res.message); return; }
    b.buy_pkr = parseFloat(buy) || 0; b.sell_pkr = parseFloat(sell) || 0;
    row.dataset.booking = JSON.stringify(b);
    const p = b.sell_pkr - b.buy_pkr, el = document.getElementById('tbProfit' + id);
    el.textContent = (b.buy_pkr || b.sell_pkr) ? 'Profit ' + Math.round(p).toLocaleString() : '';
    el.className = 'text-[10px] mt-1 font-mono ' + (p < 0 ? 'text-rose-600' : 'text-emerald-600');
    showToast('success', 'Rate saved.');
}

function showNotes(id) {
    const text = document.getElementById('tbNotes' + id)?.innerHTML || '';
    tbModal(`<div class="flex justify-between items-start border-b pb-3 mb-3"><h3 class="font-bold text-slate-800"><i class="fa-solid fa-triangle-exclamation text-amber-500 mr-1"></i> Please check this booking</h3><button onclick="closeActiveModal()" class="text-slate-400"><i class="fa-solid fa-xmark text-lg"></i></button></div>
        <ul class="list-disc ml-5 space-y-1 text-xs text-slate-700">${text.split('\n').filter(Boolean).map(l => '<li>' + l + '</li>').join('')}</ul>
        <div class="flex justify-end mt-4"><a href="index.php?page=tickets&id=${id}&back=ticket_bookings" class="px-4 py-2 bg-sky-600 text-white rounded-xl text-xs font-bold">Edit Travellers &amp; Flights</a></div>`);
}

async function deleteBooking(id, label) {
    if (!confirm('Delete ticket booking ' + label + '? It will also be removed from the Tickets tab.')) return;
    const res = await apiRequest('index.php?api=delete_air_ticket', { id });
    if (res.success) { showToast('success', res.message); document.getElementById('tbRow' + id)?.remove(); }
    else showToast('error', res.message);
}
</script>
