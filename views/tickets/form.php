<?php
declare(strict_types=1);

/**
 * Upload / review form for customized air tickets (new = ?page=tickets&new=1, edit = ?page=tickets&id=N).
 * The original ticket is read in the browser by assets/js/ticket_reader.js (pdf.js text, or OCR for scans
 * and images); the user reviews the result, picks the Family Head and saves through save_air_ticket.
 */

require_once __DIR__ . '/../../controllers/AirTicketController.php';
require_once __DIR__ . '/../../config/Auth.php';
require_once __DIR__ . '/../../services/TicketAiReader.php';

$ticketId = (int)($_GET['id'] ?? 0);
$record = null;
if ($ticketId > 0) {
    $record = AirTicketController::getById($ticketId);
    if (!$record) {
        echo '<main class="md:col-span-9"><div class="bg-white p-8 rounded-2xl border border-slate-200 text-center text-sm text-rose-600">Ticket not found. <a href="index.php?page=ticket_bookings" class="text-blue-600 underline">Back to Ticket Booking</a></div></main>';
        return;
    }
}
if (!Auth::canWrite()) {
    echo '<main class="md:col-span-9"><div class="bg-white p-8 rounded-2xl border border-slate-200 text-center text-sm text-slate-600">Your account is view-only. <a href="index.php?page=ticket_bookings" class="text-blue-600 underline">Back to Ticket Booking</a></div></main>';
    return;
}

$logos = [];
foreach (array_keys(AirTicketController::AIRLINES) as $code) {
    if ($logo = AirTicketController::logoFor($code)) $logos[$code] = $logo;
}
$inputCls = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400';
$labelCls = 'block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1';
$fromBookings = ($_GET['back'] ?? '') === 'ticket_bookings';
$backUrl = 'index.php?page=ticket_bookings';
?>

<main class="md:col-span-9 space-y-6">

    <!-- Header Bar -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col lg:flex-row items-center justify-between gap-4">
        <div>
            <a href="<?= $backUrl ?>" class="text-[11px] font-semibold text-slate-500 hover:text-sky-600"><i class="fa-solid fa-arrow-left mr-1"></i> Ticket Booking</a>
            <h2 class="text-base font-bold text-slate-800 flex items-center mt-1">
                <i class="fa-solid fa-ticket text-sky-500 mr-2"></i>
                <?= $record ? 'Edit Ticket ' . htmlspecialchars($record['ticket_no']) : 'Customize a New Ticket' ?>
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Upload the original ticket, check what was read, choose the Family Head and click <b>Customize Ticket</b>.</p>
        </div>
        <?php if ($record): ?>
            <div class="flex flex-wrap gap-2">
                <?php if ($record['original_path'] && TicketAiReader::isEnabled()): ?>
                    <button type="button" onclick="rereadOriginalWithAi()" class="bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold px-3 py-2 rounded-xl transition"><i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Re-read with AI</button>
                <?php endif; ?>
                <a href="index.php?page=print_ticket&id=<?= (int)$record['id'] ?>" target="_blank" class="bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold px-3 py-2 rounded-xl transition"><i class="fa-solid fa-eye mr-1"></i> View Customized</a>
                <?php if ($record['original_path']): ?>
                    <a href="index.php?api=air_ticket_original&id=<?= (int)$record['id'] ?>" target="_blank" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 transition"><i class="fa-regular fa-file-lines mr-1"></i> Original</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Step 1: Upload -->
    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="bg-slate-50 border-b border-slate-200 px-5 py-3 flex items-center gap-2">
            <span class="h-6 w-6 rounded-full bg-sky-600 text-white text-[11px] font-bold flex items-center justify-center">1</span>
            <h3 class="font-bold text-slate-800 text-xs"><?= $record ? 'Replace Original Ticket (optional)' : 'Upload Original Ticket' ?></h3>
        </div>
        <div class="p-5 space-y-4">
            <label id="dropZone" for="ticketFile" class="group flex flex-col items-center justify-center gap-2 border-2 border-dashed border-slate-300 hover:border-sky-400 hover:bg-sky-50/40 rounded-2xl px-6 py-9 text-center cursor-pointer transition">
                <span class="h-14 w-14 rounded-2xl bg-gradient-to-br from-sky-500 to-blue-700 text-white flex items-center justify-center text-2xl shadow-lg shadow-sky-500/30 group-hover:scale-105 transition"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                <span class="text-sm font-bold text-slate-800">Drop the ticket here or click to choose</span>
                <span class="text-[11px] text-slate-500">PDF from any airline or portal, or a screenshot / photo (JPG, PNG, WEBP) &middot; max 10 MB</span>
                <input type="file" id="ticketFile" accept="application/pdf,.pdf,image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" class="hidden">
            </label>

            <div id="readStatus" class="hidden rounded-xl border px-4 py-3 text-xs"></div>

            <?php if (!$record): ?>
                <p class="text-center text-[11px] text-slate-400">No file? <button type="button" onclick="showReview(true)" class="text-sky-600 font-semibold hover:underline">Enter ticket details manually</button></p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Step 2: Review -->
    <form id="ticketForm" class="<?= $record ? '' : 'hidden' ?> space-y-6" onsubmit="saveTicket(event)">

        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="bg-slate-50 border-b border-slate-200 px-5 py-3 flex items-center gap-2">
                <span class="h-6 w-6 rounded-full bg-sky-600 text-white text-[11px] font-bold flex items-center justify-center">2</span>
                <h3 class="font-bold text-slate-800 text-xs">Ticket Details</h3>
            </div>
            <div class="p-5 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="<?= $labelCls ?>">Airline PNR</label>
                    <input id="f_pnr" class="<?= $inputCls ?> font-mono font-bold uppercase tracking-wider" maxlength="20" placeholder="PNR">
                </div>
                <div>
                    <label class="<?= $labelCls ?>">Airline</label>
                    <select id="f_airline_code" class="<?= $inputCls ?>" onchange="airlineChanged()">
                        <option value="">— Other / Not listed —</option>
                        <?php foreach (AirTicketController::AIRLINES as $code => $name): ?>
                            <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($code . ' — ' . $name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="<?= $labelCls ?>">Airline Name on Ticket</label>
                    <input id="f_airline_name" class="<?= $inputCls ?>" maxlength="80" placeholder="e.g. SalamAir" oninput="updateLogoPreview()">
                </div>
                <div>
                    <label class="<?= $labelCls ?>">Logo on Ticket</label>
                    <div id="logoPreview" class="h-[34px] rounded-xl border border-slate-200 bg-white flex items-center justify-center px-2 text-[11px] font-bold text-slate-700 overflow-hidden"></div>
                </div>
                <div>
                    <label class="<?= $labelCls ?>">Status</label>
                    <select id="f_status" class="<?= $inputCls ?>">
                        <?php foreach (AirTicketController::STATUSES as $st): ?><option><?= htmlspecialchars($st) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="<?= $labelCls ?>">Cabin Class</label>
                    <input id="f_cabin" class="<?= $inputCls ?>" maxlength="40" list="cabinList" placeholder="Economy">
                    <datalist id="cabinList"><option>Economy</option><option>Economy Lite</option><option>Premium Economy</option><option>Business</option><option>First Class</option></datalist>
                </div>
                <div>
                    <label class="<?= $labelCls ?>">Hand Carry</label>
                    <input id="f_cabin_baggage" class="<?= $inputCls ?>" maxlength="40" placeholder="7 KG">
                </div>
                <div>
                    <label class="<?= $labelCls ?>">Issue Date</label>
                    <input id="f_issue_date" type="date" class="<?= $inputCls ?>">
                </div>
                <div class="col-span-2 md:col-span-4">
                    <label class="<?= $labelCls ?>"><i class="fa-solid fa-crown text-orange-500 mr-1"></i> Family Head (shown as lead passenger)</label>
                    <select id="f_family_head" class="<?= $inputCls ?> font-bold"></select>
                </div>
            </div>
        </section>

        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="bg-slate-50 border-b border-slate-200 px-5 py-3 flex items-center justify-between gap-2">
                <h3 class="font-bold text-slate-800 text-xs flex items-center"><i class="fa-solid fa-users text-sky-500 mr-2"></i> Passengers <span id="paxCount" class="ml-2 text-[10px] font-bold bg-sky-100 text-sky-800 px-2 py-0.5 rounded-full">0</span></h3>
                <div class="flex gap-2">
                    <button type="button" onclick="togglePasteNames()" class="text-[11px] font-semibold text-slate-600 bg-white border border-slate-300 hover:bg-slate-100 px-3 py-1.5 rounded-lg"><i class="fa-solid fa-paste mr-1"></i> Paste Names</button>
                    <button type="button" onclick="addPassenger()" class="text-[11px] font-semibold text-white bg-sky-600 hover:bg-sky-700 px-3 py-1.5 rounded-lg"><i class="fa-solid fa-plus mr-1"></i> Add</button>
                </div>
            </div>
            <div id="pasteBox" class="hidden px-5 pt-4">
                <textarea id="pasteNames" rows="3" class="<?= $inputCls ?> font-mono" placeholder="One passenger per line"></textarea>
                <div class="flex justify-end mt-2"><button type="button" onclick="applyPastedNames()" class="text-[11px] font-bold text-white bg-slate-900 px-3 py-1.5 rounded-lg">Add These Passengers</button></div>
            </div>
            <div class="p-5 space-y-2" id="paxRows"></div>
        </section>

        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="bg-slate-50 border-b border-slate-200 px-5 py-3 flex items-center justify-between gap-2">
                <h3 class="font-bold text-slate-800 text-xs flex items-center"><i class="fa-solid fa-plane text-sky-500 mr-2"></i> Flights <span id="segCount" class="ml-2 text-[10px] font-bold bg-sky-100 text-sky-800 px-2 py-0.5 rounded-full">0</span></h3>
                <button type="button" onclick="addSegment()" class="text-[11px] font-semibold text-white bg-sky-600 hover:bg-sky-700 px-3 py-1.5 rounded-lg"><i class="fa-solid fa-plus mr-1"></i> Add Flight</button>
            </div>
            <div class="p-5 space-y-3" id="segRows"></div>
        </section>

        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5">
            <label class="<?= $labelCls ?>">Extra Note on Ticket (optional)</label>
            <textarea id="f_notes" rows="2" maxlength="600" class="<?= $inputCls ?>" placeholder="e.g. Meal included, Hajj Terminal Jeddah..."></textarea>
            <p class="text-[10px] text-slate-400 mt-1"><i class="fa-solid fa-shield-halved mr-1"></i> Fares and prices are never shown on the customized ticket.</p>
        </section>

        <div class="sticky bottom-3 z-10 bg-white/95 backdrop-blur rounded-2xl border border-slate-200 shadow-lg px-5 py-3 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div id="saveHint" class="text-[11px] text-slate-500"></div>
            <div class="flex gap-2 w-full sm:w-auto">
                <a href="<?= $backUrl ?>" class="flex-1 sm:flex-none text-center bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold px-4 py-2.5 rounded-xl border border-slate-300">Cancel</a>
                <button type="submit" id="saveBtn" class="flex-1 sm:flex-none bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white text-xs font-bold px-6 py-2.5 rounded-xl shadow-md shadow-orange-500/30 transition">
                    <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> <?= $record ? 'Update Ticket' : 'Customize Ticket' ?>
                </button>
            </div>
        </div>
    </form>
</main>

<script src="assets/js/ticket_reader.js"></script>
<script>
const TK_ID = <?= $record ? (int)$record['id'] : 0 ?>;
const TK_AFTER_SAVE = <?= json_encode($fromBookings ? 'index.php?page=ticket_bookings' : '') ?>;
const TK_EXISTING = <?= json_encode($record['data'] ?? null, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const TK_AIRLINES = <?= json_encode(AirTicketController::AIRLINES, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const TK_LOGOS = <?= json_encode($logos, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
const TK_CITIES = <?= json_encode(AirTicketController::AIRPORT_CITIES, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const TK_TITLES = ['', 'MR', 'MRS', 'MS', 'MISS', 'MSTR', 'INF'];
const TK_TYPES = ['Adult', 'Child', 'Infant'];
const TK_INPUT = <?= json_encode($inputCls) ?>;
const TK_AI = <?= TicketAiReader::isEnabled() ? 'true' : 'false' ?>;
let pendingFile = null;

const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const $ = id => document.getElementById(id);

function showReview(scroll) {
    $('ticketForm').classList.remove('hidden');
    if (!$('paxRows').children.length) addPassenger();
    if (!$('segRows').children.length) addSegment();
    if (!$('f_issue_date').value) $('f_issue_date').value = new Date().toISOString().slice(0, 10);
    refreshFamilyHead();
    if (scroll) $('ticketForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ---------------------------------------------------------------- Airline
function airlineChanged() {
    const code = $('f_airline_code').value;
    if (code && TK_AIRLINES[code]) $('f_airline_name').value = TK_AIRLINES[code];
    updateLogoPreview();
}
function updateLogoPreview() {
    const code = $('f_airline_code').value, name = $('f_airline_name').value.trim();
    $('logoPreview').innerHTML = TK_LOGOS[code]
        ? `<img src="${esc(TK_LOGOS[code])}" alt="" class="max-h-6 w-auto">`
        : `<span><i class="fa-solid fa-plane text-orange-500 mr-1"></i>${esc(name || 'Airline name')}</span>`;
}

// ---------------------------------------------------------------- Passengers
function addPassenger(p = {}) {
    const row = document.createElement('div');
    row.className = 'pax-row grid grid-cols-12 gap-2 items-center bg-slate-50/60 border border-slate-200 rounded-xl p-2';
    row.innerHTML = `
        <select class="px-title col-span-3 md:col-span-1 ${TK_INPUT} !px-1.5">${TK_TITLES.map(t => `<option ${t === (p.title || '') ? 'selected' : ''}>${t}</option>`).join('')}</select>
        <input class="px-name col-span-9 md:col-span-4 ${TK_INPUT} font-bold uppercase" placeholder="FULL NAME" value="${esc(p.name)}">
        <select class="px-type col-span-4 md:col-span-2 ${TK_INPUT}">${TK_TYPES.map(t => `<option ${t === (p.type || 'Adult') ? 'selected' : ''}>${t}</option>`).join('')}</select>
        <input class="px-passport col-span-4 md:col-span-2 ${TK_INPUT} font-mono uppercase" placeholder="Passport" value="${esc(p.passport)}">
        <input class="px-ticket col-span-3 md:col-span-2 ${TK_INPUT} font-mono" placeholder="E-ticket no." value="${esc(p.ticket_no)}">
        <button type="button" class="col-span-1 h-8 w-8 mx-auto rounded-lg text-rose-500 hover:bg-rose-50" title="Remove"><i class="fa-solid fa-xmark"></i></button>`;
    row.dataset.gender = p.gender || ''; // from the passport scan; used to pick the Family Head
    row.querySelector('button').onclick = () => { row.remove(); refreshFamilyHead(); };
    row.querySelector('.px-name').addEventListener('input', () => refreshFamilyHead());
    row.querySelector('.px-title').addEventListener('change', () => refreshFamilyHead());
    row.querySelector('.px-type').addEventListener('change', () => refreshFamilyHead());
    $('paxRows').appendChild(row);
    refreshFamilyHead();
}
function collectPassengers() {
    return [...document.querySelectorAll('.pax-row')].map(r => ({
        title: r.querySelector('.px-title').value, name: r.querySelector('.px-name').value.trim().toUpperCase(),
        type: r.querySelector('.px-type').value, passport: r.querySelector('.px-passport').value.trim().toUpperCase(),
        ticket_no: r.querySelector('.px-ticket').value.trim(), gender: r.dataset.gender || ''
    })).filter(p => p.name);
}
// Same rule as the server (AirTicketController::pickFamilyHead): the Family Head is a male adult.
function headCandidates(list) {
    const gender = p => ['M', 'F'].includes(p.gender) ? p.gender : (['MR', 'MSTR'].includes(p.title) ? 'M' : (['MRS', 'MS', 'MISS'].includes(p.title) ? 'F' : ''));
    const adult = p => p.type === 'Adult' && !['MSTR', 'INF', 'MISS'].includes(p.title);
    for (const ok of [p => adult(p) && gender(p) === 'M', p => adult(p) && gender(p) !== 'F', adult, () => true]) {
        const fit = list.filter(ok);
        if (fit.length) return fit;
    }
    return [];
}
function refreshFamilyHead(preferred) {
    const sel = $('f_family_head'), current = preferred || sel.value;
    const pax = collectPassengers();
    const names = headCandidates(pax).map(p => p.name);
    sel.innerHTML = names.length ? names.map(n => `<option ${n === current ? 'selected' : ''}>${esc(n)}</option>`).join('') : '<option value="">Add passengers first</option>';
    $('paxCount').textContent = pax.length;
}
function togglePasteNames() { $('pasteBox').classList.toggle('hidden'); $('pasteNames').focus(); }
function applyPastedNames() {
    const lines = $('pasteNames').value.split(/[\n,]+/).map(s => s.trim()).filter(Boolean);
    // Drop the empty starter row so pasted names start at #1.
    document.querySelectorAll('.pax-row').forEach(r => { if (!r.querySelector('.px-name').value.trim()) r.remove(); });
    lines.forEach(line => {
        const m = line.match(/^(MRS|MR|MS|MISS|MSTR|MASTER|INF)\.?\s+(.+)$/i);
        const title = m ? m[1].toUpperCase().replace('MASTER', 'MSTR') : '';
        addPassenger({ title, name: (m ? m[2] : line).toUpperCase(), type: title === 'MSTR' ? 'Child' : 'Adult' });
    });
    $('pasteNames').value = '';
    $('pasteBox').classList.add('hidden');
}

// ---------------------------------------------------------------- Flights
function addSegment(s = {}) {
    const row = document.createElement('div');
    row.className = 'seg-row relative border border-slate-200 rounded-2xl p-3 bg-gradient-to-br from-white to-slate-50';
    const field = (cls, label, attrs, span) => `<div class="${span}"><label class="block text-[9px] font-bold uppercase tracking-wider text-slate-400 mb-0.5">${label}</label><input class="${cls} ${TK_INPUT}" ${attrs}></div>`;
    row.innerHTML = `
        <div class="flex items-center justify-between mb-2">
            <span class="seg-no text-[10px] font-black uppercase tracking-widest text-sky-700"></span>
            <button type="button" class="h-7 w-7 rounded-lg text-rose-500 hover:bg-rose-50" title="Remove flight"><i class="fa-solid fa-trash-can text-[11px]"></i></button>
        </div>
        <div class="grid grid-cols-6 md:grid-cols-12 gap-2">
            ${field('sg-flight font-mono font-bold uppercase', 'Flight No.', `value="${esc(s.flight)}" placeholder="SV733" maxlength="10"`, 'col-span-2')}
            ${field('sg-from font-mono font-bold uppercase', 'From', `value="${esc(s.from)}" placeholder="LHE" maxlength="3"`, 'col-span-2 md:col-span-1')}
            ${field('sg-to font-mono font-bold uppercase', 'To', `value="${esc(s.to)}" placeholder="JED" maxlength="3"`, 'col-span-2 md:col-span-1')}
            ${field('sg-dep-date', 'Departure Date', `type="date" value="${esc(s.dep_date)}"`, 'col-span-3 md:col-span-2')}
            ${field('sg-dep-time', 'Dep. Time', `type="time" value="${esc(s.dep_time)}"`, 'col-span-3 md:col-span-2')}
            ${field('sg-arr-date', 'Arrival Date', `type="date" value="${esc(s.arr_date)}"`, 'col-span-3 md:col-span-2')}
            ${field('sg-arr-time', 'Arr. Time', `type="time" value="${esc(s.arr_time)}"`, 'col-span-3 md:col-span-2')}
            ${field('sg-baggage', 'Checked Baggage', `value="${esc(s.baggage)}" placeholder="2 PC / 46 KG" maxlength="40"`, 'col-span-3 md:col-span-3')}
            ${field('sg-duration', 'Duration', `value="${esc(s.duration)}" placeholder="5h 05m" maxlength="20"`, 'col-span-3 md:col-span-3')}
            ${field('sg-from-term', 'Dep. Terminal', `value="${esc(s.from_terminal)}" placeholder="M" maxlength="20"`, 'col-span-3 md:col-span-3')}
            ${field('sg-to-term', 'Arr. Terminal', `value="${esc(s.to_terminal)}" placeholder="1 / Hajj" maxlength="20"`, 'col-span-3 md:col-span-3')}
        </div>
        <div class="sg-route mt-2 text-[11px] text-slate-500"></div>`;
    row.querySelector('button').onclick = () => { row.remove(); numberSegments(); };
    row.querySelectorAll('.sg-from,.sg-to').forEach(i => i.addEventListener('input', () => routeHint(row)));
    row.querySelector('.sg-dep-date').addEventListener('change', e => { const a = row.querySelector('.sg-arr-date'); if (!a.value) a.value = e.target.value; });
    $('segRows').appendChild(row);
    routeHint(row);
    numberSegments();
}
function routeHint(row) {
    const f = row.querySelector('.sg-from').value.toUpperCase(), t = row.querySelector('.sg-to').value.toUpperCase();
    row.querySelector('.sg-route').innerHTML = f || t ? `<i class="fa-solid fa-plane-departure text-orange-500 mr-1"></i>${esc(TK_CITIES[f] || f || '?')} <i class="fa-solid fa-arrow-right-long mx-1 text-slate-300"></i> ${esc(TK_CITIES[t] || t || '?')}` : '';
}
function numberSegments() {
    const rows = document.querySelectorAll('.seg-row');
    rows.forEach((r, i) => r.querySelector('.seg-no').textContent = 'Flight ' + (i + 1));
    $('segCount').textContent = rows.length;
}
function collectSegments() {
    return [...document.querySelectorAll('.seg-row')].map(r => {
        const v = c => r.querySelector(c).value.trim();
        return {
            flight: v('.sg-flight').toUpperCase().replace(/\s+/g, ''), from: v('.sg-from').toUpperCase(), to: v('.sg-to').toUpperCase(),
            dep_date: v('.sg-dep-date'), dep_time: v('.sg-dep-time'), arr_date: v('.sg-arr-date'), arr_time: v('.sg-arr-time'),
            baggage: v('.sg-baggage'), duration: v('.sg-duration'), from_terminal: v('.sg-from-term'), to_terminal: v('.sg-to-term')
        };
    }).filter(s => s.flight || s.from || s.to);
}

// ---------------------------------------------------------------- Fill the whole form from a ticket object
function fillForm(t) {
    $('f_pnr').value = t.pnr || '';
    $('f_airline_code').value = TK_AIRLINES[t.airline_code] ? t.airline_code : '';
    $('f_airline_name').value = t.airline_name || TK_AIRLINES[t.airline_code] || '';
    $('f_status').value = t.status || 'Confirmed';
    $('f_cabin').value = t.cabin || 'Economy';
    $('f_cabin_baggage').value = t.cabin_baggage || '7 KG';
    $('f_issue_date').value = t.issue_date || new Date().toISOString().slice(0, 10);
    $('f_notes').value = t.notes || '';
    $('paxRows').innerHTML = '';
    (t.passengers || []).forEach(p => addPassenger(p));
    $('segRows').innerHTML = '';
    (t.segments || []).forEach(s => addSegment(s));
    updateLogoPreview();
    showReview(false);
    refreshFamilyHead(t.family_head);
}

// ---------------------------------------------------------------- Upload + read
// Two readers run side by side: the built-in reader (instant, in the browser) fills the form first, then the
// Gemini AI reader (server, more accurate) replaces it when it answers. Blank AI fields keep the built-in value.
const read = { token: 0, file: null, local: null, localState: 'idle', localStep: '', localError: '', method: '', ai: 'idle', aiError: '', aiModel: '', aiStarted: 0, dirty: false };
let aiResult = null, aiTick = null;

function setStatus(kind, html) {
    const el = $('readStatus');
    const tones = { busy: 'bg-sky-50 border-sky-200 text-sky-800', ok: 'bg-emerald-50 border-emerald-200 text-emerald-900', warn: 'bg-amber-50 border-amber-200 text-amber-900', err: 'bg-rose-50 border-rose-200 text-rose-800' };
    el.className = 'rounded-xl border px-4 py-3 text-xs ' + tones[kind];
    el.innerHTML = html;
}

function chipHtml() {
    return read.file ? `<b>${esc(read.file.name)}</b> <span class="opacity-60">(${(read.file.size / 1024).toFixed(0)} KB)</span>` : '<b>Saved original ticket</b>';
}

function currentTicket() {
    return { pnr: $('f_pnr').value.trim(), passengers: collectPassengers(), segments: collectSegments() };
}

function renderReadStatus() {
    const spin = '<i class="fa-solid fa-spinner fa-spin"></i>';
    const line = (icon, cls, html) => `<div class="flex items-start gap-2 ${cls}"><span class="w-4 text-center mt-0.5">${icon}</span><div>${html}</div></div>`;
    const rows = [];
    if (read.localState === 'busy') rows.push(line(spin, 'text-slate-600', `Quick read: <span id="readStep">${esc(read.localStep || 'Reading…')}</span>`));
    else if (read.localState === 'done') rows.push(line('<i class="fa-solid fa-circle-check text-emerald-600"></i>', 'text-slate-600', `Quick read done (${read.method === 'ocr' ? 'scanned · OCR' : 'text PDF'}): ${read.local.passengers.length} passenger(s), ${read.local.segments.length} flight(s).`));
    else if (read.localState === 'error') rows.push(line('<i class="fa-solid fa-circle-xmark text-rose-500"></i>', 'text-slate-600', `Quick read failed (${esc(read.localError)}).`));

    if (read.ai === 'busy') rows.push(line(spin, 'text-violet-800 font-semibold', `<i class="fa-solid fa-wand-magic-sparkles mr-1"></i>Gemini AI is reading the ticket for a perfect result…${read.aiRound > 1 ? ' (second try)' : ''} <span class="font-normal opacity-70" id="aiTimer"></span>`));
    else if (read.ai === 'retrying') rows.push(line(spin, 'text-violet-800', `<i class="fa-solid fa-rotate mr-1"></i>Google AI is busy on all keys — trying again automatically in a moment…`));
    else if (read.ai === 'done') rows.push(line('<i class="fa-solid fa-wand-magic-sparkles text-violet-600"></i>', 'text-violet-900 font-semibold', `AI verified — the details below were read by Gemini AI. <span class="font-normal opacity-60">(${esc(read.aiModel)})</span>`));
    else if (read.ai === 'kept') rows.push(line('<i class="fa-solid fa-hand text-amber-600"></i>', 'text-amber-900', `The AI result is ready, but your manual edits were kept. <button type="button" onclick="applyAiAgain()" class="underline font-semibold">Apply AI result</button>`));
    else if (read.ai === 'failed') rows.push(line('<i class="fa-solid fa-triangle-exclamation text-amber-600"></i>', 'text-amber-900', `AI could not read it right now (${esc(read.aiError)}). ${read.localState === 'done' ? 'The quick-read details are kept. ' : ''}<button type="button" onclick="startAi()" class="underline font-semibold">Retry AI</button>`));

    const busy = read.localState === 'busy' || read.ai === 'busy' || read.ai === 'retrying';
    const warnings = [];
    if (!busy && (read.localState === 'done' || read.ai === 'done' || read.ai === 'kept')) {
        const t = currentTicket();
        if (!t.pnr) warnings.push('PNR not found');
        if (!t.passengers.length) warnings.push('no passenger names found — use <b>Paste Names</b> or <b>Add</b>');
        if (!t.segments.length) warnings.push('no flights found — add them with <b>Add Flight</b>');
        t.segments.forEach((s, i) => { if (!s.dep_date || !s.dep_time || !s.arr_time || !s.from || !s.to) warnings.push(`flight ${i + 1} (${esc(s.flight || '?')}) is missing some details`); });
    }
    const kind = busy ? 'busy' : (warnings.length || read.ai === 'failed' || read.ai === 'kept' ? 'warn' : (read.localState === 'error' && read.ai !== 'done' ? 'err' : 'ok'));
    setStatus(kind, `<div class="font-semibold mb-1.5"><i class="fa-solid fa-paperclip mr-1"></i>${chipHtml()}</div><div class="space-y-1">${rows.join('')}</div>` +
        (warnings.length ? `<div class="mt-2 pt-2 border-t border-amber-200">Please check: ${warnings.join('; ')}.</div>`
            : (!busy ? '<div class="mt-2 opacity-80">Review the details below, choose the Family Head and click <b>Customize Ticket</b>.</div>' : '')));
}

function prepared(t) {
    t.family_head = t.family_head || t.passengers[0]?.name || '';
    t.cabin_baggage = t.cabin_baggage || '7 KG';
    return t;
}

// AI result first; anything the AI left blank is taken from the quick read.
function mergeResults(ai, local) {
    if (!local) return ai;
    const key = n => String(n || '').toUpperCase().replace(/[^A-Z ]/g, '').split(/\s+/).filter(Boolean).sort().join(' ');
    for (const f of ['pnr', 'airline_code', 'airline_name', 'cabin_baggage']) if (!ai[f] && local[f]) ai[f] = local[f];
    if (!ai.passengers.length) ai.passengers = local.passengers;
    else ai.passengers.forEach(p => {
        const m = local.passengers.find(q => key(q.name) === key(p.name));
        if (m) for (const f of ['title', 'passport', 'ticket_no']) if (!p[f] && m[f]) p[f] = m[f];
    });
    if (!ai.segments.length) ai.segments = local.segments;
    else ai.segments.forEach(s => {
        const m = local.segments.find(q => q.flight && q.flight === s.flight);
        if (m) for (const f of Object.keys(m)) if (!s[f] && m[f]) s[f] = m[f];
    });
    return ai;
}

// round 1 = first try; when every key and model is busy, one more round starts by itself a few seconds later.
async function startAi(round = 1) {
    if (!TK_AI) return;
    const token = read.token;
    read.ai = 'busy'; read.aiError = ''; read.aiRound = round;
    if (round === 1) read.aiStarted = Date.now();
    renderReadStatus();
    clearInterval(aiTick);
    aiTick = setInterval(() => { const el = $('aiTimer'); if (el) el.textContent = Math.round((Date.now() - read.aiStarted) / 1000) + 's'; }, 1000);
    try {
        const fd = new FormData();
        if (read.file) fd.append('file', read.file); else fd.append('id', TK_ID);
        const res = await fetch('index.php?api=read_air_ticket_ai', { method: 'POST', headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content }, body: fd });
        const data = await res.json().catch(() => ({ success: false, message: 'unexpected server response, HTTP ' + res.status }));
        if (token !== read.token) return;
        if (!data.success) throw new Error(data.message || 'AI reading failed');
        aiResult = data.ticket; read.aiModel = data.model + (data.key ? ' · key ' + data.key : '');
        if (read.dirty && !confirm('Gemini AI has finished reading the ticket.\n\nReplace the details in the form with the AI result? (Your manual changes will be replaced.)')) read.ai = 'kept';
        else applyAiAgain(true);
    } catch (e) {
        if (token !== read.token) return;
        read.aiError = e.message || String(e);
        if (round === 1 && !/not configured|not supported|not found|10 MB/i.test(read.aiError)) {
            read.ai = 'retrying';
            setTimeout(() => { if (token === read.token && read.ai === 'retrying') startAi(2); }, 4000);
        } else {
            read.ai = 'failed';
        }
    } finally {
        if (token === read.token) { clearInterval(aiTick); renderReadStatus(); }
    }
}

function applyAiAgain(silent) {
    if (!aiResult) return;
    const head = $('f_family_head').value;
    const merged = prepared(mergeResults(JSON.parse(JSON.stringify(aiResult)), read.local ? JSON.parse(JSON.stringify(read.local)) : null));
    // Tickets seldom print passport numbers: keep the ones this record already has (e.g. from passport scans).
    if (TK_EXISTING) {
        const key = n => String(n || '').toUpperCase().replace(/[^A-Z ]/g, '').split(/\s+/).filter(Boolean).sort().join(' ');
        merged.passengers.forEach(p => {
            const old = TK_EXISTING.passengers.find(q => key(q.name) === key(p.name));
            if (old && !p.passport && old.passport) p.passport = old.passport;
        });
    }
    if (head && merged.passengers.some(p => p.name === head)) merged.family_head = head;
    fillForm(merged);
    read.ai = 'done'; read.dirty = false;
    if (!silent) renderReadStatus();
}

async function handleFile(file) {
    if (!file) return;
    if (file.size > 10 * 1024 * 1024) { setStatus('err', '<i class="fa-solid fa-triangle-exclamation mr-1"></i> File is larger than 10 MB.'); return; }
    if (!/pdf|jpe?g|png|webp/i.test(file.type + file.name)) { setStatus('err', '<i class="fa-solid fa-triangle-exclamation mr-1"></i> Please choose a PDF or an image (JPG, PNG, WEBP).'); return; }
    if (TK_ID && !confirm('Read this file and replace the details below with what it contains?')) {
        pendingFile = file;
        setStatus('ok', `<i class="fa-solid fa-paperclip mr-1"></i> <b>${esc(file.name)}</b> will replace the original when you click Update. Details below were kept.`);
        return;
    }
    pendingFile = file;
    const token = ++read.token;
    Object.assign(read, { file, local: null, localState: 'busy', localStep: '', localError: '', method: '', ai: 'idle', dirty: false });
    aiResult = null;
    renderReadStatus();
    startAi();
    try {
        const out = await TicketReader.readFile(file, msg => { read.localStep = msg; const s = $('readStep'); if (s) s.textContent = msg; });
        if (token !== read.token) return;
        read.local = out.result; read.method = out.method; read.localState = 'done';
        if (aiResult && read.ai === 'done') applyAiAgain(true); // AI answered first: fill its blanks from the quick read
        else if (read.ai !== 'kept') {
            fillForm(prepared(JSON.parse(JSON.stringify(out.result))));
            read.dirty = false;
            $('ticketForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    } catch (e) {
        console.error(e);
        if (token !== read.token) return;
        read.localState = 'error'; read.localError = e.message || String(e);
        if (read.ai !== 'done') showReview(true);
    }
    renderReadStatus();
}

// Edit mode: read the saved original again with AI.
function rereadOriginalWithAi() {
    if (!confirm('Read the saved original ticket again with Gemini AI and replace the details below?')) return;
    ++read.token;
    Object.assign(read, { file: null, local: null, localState: 'idle', ai: 'idle', dirty: false });
    aiResult = null;
    $('readStatus').scrollIntoView({ behavior: 'smooth', block: 'center' });
    startAi();
}

// Any manual change after a read counts as an edit the AI must not silently overwrite.
$('ticketForm').addEventListener('input', () => { read.dirty = true; });
$('ticketForm').addEventListener('change', () => { read.dirty = true; });
$('ticketForm').addEventListener('click', e => { const b = e.target.closest('button'); if (b && b.id !== 'saveBtn') read.dirty = true; });

$('ticketFile').addEventListener('change', e => handleFile(e.target.files[0]));
['dragenter', 'dragover'].forEach(ev => $('dropZone').addEventListener(ev, e => { e.preventDefault(); $('dropZone').classList.add('border-sky-500', 'bg-sky-50'); }));
['dragleave', 'drop'].forEach(ev => $('dropZone').addEventListener(ev, e => { e.preventDefault(); $('dropZone').classList.remove('border-sky-500', 'bg-sky-50'); }));
$('dropZone').addEventListener('drop', e => handleFile(e.dataTransfer.files[0]));

// ---------------------------------------------------------------- Save
async function saveTicket(e) {
    e.preventDefault();
    const ticket = {
        id: TK_ID, pnr: $('f_pnr').value.trim().toUpperCase(), airline_code: $('f_airline_code').value,
        airline_name: $('f_airline_name').value.trim(), status: $('f_status').value, cabin: $('f_cabin').value.trim(),
        cabin_baggage: $('f_cabin_baggage').value.trim(), issue_date: $('f_issue_date').value, family_head: $('f_family_head').value,
        passengers: collectPassengers(), segments: collectSegments(), notes: $('f_notes').value.trim()
    };
    if (!ticket.passengers.length) { showToast('error', 'Add at least one passenger.'); return; }
    if (!ticket.segments.length) { showToast('error', 'Add at least one flight.'); return; }
    if (!ticket.airline_code && !ticket.airline_name) { showToast('error', 'Choose the airline or type its name.'); return; }

    const btn = $('saveBtn'), label = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Customizing…';
    try {
        const fd = new FormData();
        fd.append('ticket', JSON.stringify(ticket));
        if (pendingFile) fd.append('file', pendingFile);
        const res = await fetch('index.php?api=save_air_ticket', { method: 'POST', headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content }, body: fd });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Unable to save ticket.');
        window.location.href = TK_AFTER_SAVE || ('index.php?page=print_ticket&id=' + data.id);
    } catch (err) {
        showToast('error', err.message);
        btn.disabled = false;
        btn.innerHTML = label;
    }
}

if (TK_EXISTING) fillForm(TK_EXISTING); else updateLogoPreview();
</script>
