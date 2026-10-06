/**
 * Hotel Voucher UI Engine
 * - Dynamic multi-stay rows
 * - Per-night buy/sell rates on every stay
 * - Room type presets + custom room type
 * - Date-driven night calculation
 */

const VOUCHER_ROOM_TYPES = [
    'Double Bed',
    'Triple Bed',
    'Sharing',
    'Twin Bed',
    'Quadruple Bed',
    'Four Bed Room',
    'Five Bed Room',
    'Six Bed Room',
    'Family Room',
    'Suite'
];

function roomTypeOptions(selected = 'Double Bed') {
    const options = VOUCHER_ROOM_TYPES.map(type =>
        `<option value="${escapeHtml(type)}" ${selected === type ? 'selected' : ''}>${escapeHtml(type)}</option>`
    ).join('');
    return options + `<option value="__custom__" ${selected && !VOUCHER_ROOM_TYPES.includes(selected) ? 'selected' : ''}>Custom Room Type...</option>`;
}

function createStayRowHtml() {
    return `
        <div class="md:col-span-2">
            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">City</label>
            <select class="stay-city w-full border border-slate-300 rounded-lg p-2 font-medium bg-slate-50 focus:bg-white outline-none">
                <option value="Madinah">Madinah</option>
                <option value="Makkah">Makkah</option>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Hotel Name</label>
            <input type="text" placeholder="Hotel Name" class="stay-hotel w-full border border-slate-300 rounded-lg p-2 font-semibold focus:ring-2 focus:ring-amber-500 outline-none">
        </div>
        <div class="md:col-span-2">
            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Room Type</label>
            <select class="stay-room-type w-full border border-slate-300 rounded-lg p-2 bg-slate-50 focus:bg-white outline-none" onchange="toggleCustomRoomType(this)">
                ${roomTypeOptions()}
            </select>
            <input type="text" placeholder="Enter custom room type" class="stay-room-custom hidden mt-1 w-full border border-indigo-300 rounded-lg p-2 bg-indigo-50 outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="md:col-span-1">
            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Meal</label>
            <select class="stay-meal-plan w-full border border-slate-300 rounded-lg p-2 bg-slate-50 outline-none">
                <option value="RO">RO</option>
                <option value="BB">BB</option>
                <option value="HB">HB</option>
                <option value="FB">FB</option>
            </select>
        </div>
        <div class="md:col-span-1">
            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Confirmation #</label>
            <input type="text" placeholder="Ref #" class="stay-confirmation w-full border border-slate-300 rounded-lg p-2 font-mono outline-none uppercase">
        </div>
        <div class="md:col-span-2">
            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Check-In</label>
            <input type="date" onchange="recalculateStayNights(this)" class="stay-checkin w-full border border-slate-300 rounded-lg p-2 font-mono outline-none">
        </div>
        <div class="md:col-span-2">
            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Check-Out</label>
            <input type="date" onchange="recalculateStayNights(this)" class="stay-checkout w-full border border-slate-300 rounded-lg p-2 font-mono outline-none">
        </div>
        <div class="md:col-span-1">
            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Nights</label>
            <input type="number" oninput="recalculateStayCost(this)" class="stay-nights w-full border border-slate-300 rounded-lg p-2 font-bold text-amber-700 outline-none text-center" value="1" min="1">
        </div>
        <div class="md:col-span-1">
            <label class="block text-[10px] font-semibold text-rose-600 mb-0.5">Buy/Night</label>
            <input type="number" step="0.01" oninput="recalculateStayCost(this)" placeholder="0.00" class="stay-buy-rate w-full border border-slate-300 rounded-lg p-2 font-mono text-rose-600 outline-none text-center">
        </div>
        <div class="md:col-span-1">
            <label class="block text-[10px] font-semibold text-emerald-700 mb-0.5">Sell/Night</label>
            <input type="number" step="0.01" oninput="recalculateStayCost(this)" placeholder="0.00" class="stay-sell-rate w-full border border-slate-300 rounded-lg p-2 font-mono font-bold text-emerald-700 outline-none text-center">
        </div>
        <div class="md:col-span-1 flex items-center justify-between pb-1">
            <div class="text-[11px] font-bold text-slate-700 font-mono stay-total-display">0 SAR</div>
            <button type="button" onclick="removeStayRow(this)" class="text-slate-400 hover:text-rose-600 transition p-1" title="Remove Stay">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </div>
    `;
}

function addVoucherStayRow() {
    const container = document.getElementById('voucherStaysContainer');
    if (!container) return;

    const div = document.createElement('div');
    div.className = 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-12 gap-2 stay-item bg-white p-3 rounded-xl border border-slate-200 shadow-2xs items-end animate-in fade-in duration-150';
    div.innerHTML = createStayRowHtml();
    container.appendChild(div);
    updateGrandVoucherTotals();
}

const addStayRow = addVoucherStayRow;

function removeStayRow(button) {
    const row = button.closest('.stay-item');
    const container = document.getElementById('voucherStaysContainer');
    if (!row || !container) return;

    // Hotel is optional, so the last stay can go too (blank Hotel Name also means "no hotel").
    row.remove();
    updateGrandVoucherTotals();
}

function toggleCustomRoomType(select) {
    const row = select.closest('.stay-item');
    const customInput = row?.querySelector('.stay-room-custom');
    if (!customInput) return;

    const custom = select.value === '__custom__';
    customInput.classList.toggle('hidden', !custom);
    customInput.required = custom;
    if (!custom) customInput.value = '';
}

function getStayRoomType(row) {
    const select = row.querySelector('.stay-room-type');
    const custom = row.querySelector('.stay-room-custom');
    if (select?.value === '__custom__') {
        return (custom?.value || '').trim();
    }
    return select?.value || 'Double Bed';
}

function recalculateStayNights(element) {
    const row = element.closest('.stay-item');
    if (!row) return;

    const checkin = row.querySelector('.stay-checkin')?.value;
    const checkout = row.querySelector('.stay-checkout')?.value;
    const nightsInput = row.querySelector('.stay-nights');

    if (checkin && checkout && nightsInput) {
        const d1 = new Date(`${checkin}T00:00:00`);
        const d2 = new Date(`${checkout}T00:00:00`);
        const diffDays = Math.round((d2.getTime() - d1.getTime()) / 86400000);
        if (diffDays > 0) {
            nightsInput.value = diffDays;
        }
    }
    recalculateStayCost(element);
}

function recalculateStayCost(element) {
    const row = element.closest('.stay-item');
    if (!row) return;

    const nights = Math.max(0, parseFloat(row.querySelector('.stay-nights')?.value) || 0);
    const sellRate = Math.max(0, parseFloat(row.querySelector('.stay-sell-rate')?.value) || 0);
    const totalDisplay = row.querySelector('.stay-total-display');
    if (totalDisplay) totalDisplay.textContent = `${(nights * sellRate).toFixed(2)} SAR`;
    updateGrandVoucherTotals();
}

function updateGrandVoucherTotals() {
    const rows = document.querySelectorAll('.stay-item');
    let totalNights = 0;
    let totalBuy = 0;
    let totalSell = 0;

    rows.forEach(row => {
        const nights = Math.max(0, parseFloat(row.querySelector('.stay-nights')?.value) || 0);
        const buyRate = Math.max(0, parseFloat(row.querySelector('.stay-buy-rate')?.value) || 0);
        const sellRate = Math.max(0, parseFloat(row.querySelector('.stay-sell-rate')?.value) || 0);
        totalNights += nights;
        totalBuy += nights * buyRate;
        totalSell += nights * sellRate;
    });

    const nightsEl = document.getElementById('grandTotalNights');
    const buyEl = document.getElementById('grandTotalBuy');
    const sellEl = document.getElementById('grandTotalSell');
    if (nightsEl) nightsEl.textContent = totalNights;
    if (buyEl) buyEl.textContent = totalBuy.toFixed(2);
    if (sellEl) sellEl.textContent = totalSell.toFixed(2);
}

function addVoucherMutamerRow() {
    const container = document.getElementById('voucherMutamersContainer');
    if (!container) return;

    const div = document.createElement('div');
    div.className = 'grid grid-cols-1 md:grid-cols-6 gap-2.5 mutamer-item bg-white p-2.5 rounded-xl border border-slate-200 shadow-2xs animate-in fade-in duration-150';
    div.innerHTML = `
        <input type="text" placeholder="Mutamer Full Name" oninput="updateVoucherPaxSummary()" class="mut-name border border-slate-300 rounded-lg p-2 font-semibold md:col-span-2 focus:ring-2 focus:ring-indigo-500 outline-none">
        <input type="text" placeholder="Passport #" class="mut-pass border border-slate-300 rounded-lg p-2 font-mono uppercase font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none">
        <select class="mut-gender w-full border border-slate-300 rounded-lg p-2 font-medium bg-slate-50">
            <option value="M">Male (M)</option>
            <option value="F">Female (F)</option>
        </select>
        <select class="mut-pax w-full border border-slate-300 rounded-lg p-2 font-medium bg-slate-50" onchange="updateVoucherPaxSummary()">
            <option value="Adult">Adult</option>
            <option value="Child">Child</option>
            <option value="Infant">Infant</option>
        </select>
        <div class="flex items-center gap-1.5">
            <select class="mut-bed w-full border border-slate-300 rounded-lg p-2 font-medium bg-slate-50">
                <option value="Yes">Bed: Yes</option>
                <option value="No">Bed: No</option>
            </select>
            <button type="button" onclick="removeMutamerRow(this)" class="text-slate-400 hover:text-rose-600 p-1.5 transition" title="Remove Mutamer"><i class="fa-solid fa-trash-can"></i></button>
        </div>
    `;
    container.appendChild(div);
    updateVoucherPaxSummary();
}

const addMutamerRow = addVoucherMutamerRow;

function removeMutamerRow(button) {
    const row = button.closest('.mutamer-item');
    const container = document.getElementById('voucherMutamersContainer');
    if (!row || !container) return;

    if (container.querySelectorAll('.mutamer-item').length <= 1) {
        alert('At least one passenger must remain on the manifest.');
        return;
    }
    row.remove();
    updateVoucherPaxSummary();
}

/** Auto-computes Total PAX from the Mutamers manifest (count of rows with a name entered). */
function updateVoucherPaxSummary() {
    const paxField = document.getElementById('vc_total_pax');
    if (!paxField) return;

    const rows = Array.from(document.querySelectorAll('.mutamer-item'))
        .filter(row => (row.querySelector('.mut-name')?.value || '').trim() !== '');
    paxField.value = Math.max(1, rows.length || document.querySelectorAll('.mutamer-item').length);
}

function escapeHtml(value) {
    const map = {'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'};
    return String(value ?? '').replace(/[&<>"']/g, m => map[m]);
}

/**
 * Voucher "Assign Master Booking(s)" + auto-Transport section
 * (mirrors the equivalent hb_* functions used by the Only Hotel Booking form).
 */
let vcSelectedMasterBookings = [];
let vcMasterSearchTimer = null;
let vcMasterSearchSeq = 0;

function vcToggleTransportSection() {
    const enabled = document.getElementById('vc_transport_enabled')?.checked;
    document.getElementById('vc_transport_fields')?.classList.toggle('hidden', !enabled);
    if (enabled) vcRenderTransportRoutes();
}

/** Per-route vehicle/rate overrides, keyed by route (e.g. "MAK-JED"). Blank = use the default Transport Type/rates. */
let vcRouteOverrides = {};
/** Routes the user has unticked - no transfer is created for them. */
let vcSkippedRoutes = new Set();

const VC_CITY_CODES = {Makkah: 'MAK', Madinah: 'MED', Jeddah: 'JED', Taif: 'TAI', Riyadh: 'RUH'};

/** Mirrors VoucherController::regenerateAutoTransport() leg building. */
function vcComputeTransportRoutes() {
    const cities = Array.from(document.querySelectorAll('#voucherStaysContainer .stay-item'))
        .filter(row => row.querySelector('.stay-hotel')?.value.trim())
        .map(row => VC_CITY_CODES[row.querySelector('.stay-city')?.value] || 'MAK');
    if (!cities.length) return [];
    const routes = ['JED-' + cities[0]];
    for (let i = 0; i < cities.length - 1; i++) {
        if (cities[i] !== cities[i + 1]) routes.push(cities[i] + '-' + cities[i + 1]);
    }
    routes.push(cities[cities.length - 1] + '-JED');
    return [...new Set(routes)];
}

function vcCaptureRouteOverrides() {
    document.querySelectorAll('#vc_transport_routes .vc-route-row').forEach(row => {
        const route = row.dataset.route;
        if (row.querySelector('.vc-route-on').checked) vcSkippedRoutes.delete(route);
        else vcSkippedRoutes.add(route);
        const o = {
            type: row.querySelector('.vc-route-type').value.trim(),
            buy: row.querySelector('.vc-route-buy').value.trim(),
            sell: row.querySelector('.vc-route-sell').value.trim()
        };
        if (o.type || o.buy || o.sell) vcRouteOverrides[route] = o;
        else delete vcRouteOverrides[route];
    });
}

function vcRenderTransportRoutes() {
    const box = document.getElementById('vc_transport_routes');
    if (!box) return;
    vcCaptureRouteOverrides();
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    const routes = vcComputeTransportRoutes();
    box.innerHTML = routes.map(route => {
        const o = vcRouteOverrides[route] || {};
        const on = !vcSkippedRoutes.has(route);
        const off = on ? '' : ' disabled';
        return `
            <div class="vc-route-row grid grid-cols-12 gap-2 items-center${on ? '' : ' opacity-50'}" data-route="${esc(route)}">
                <label class="col-span-3 flex items-center gap-2 cursor-pointer select-none" title="Untick to skip this transfer">
                    <input type="checkbox" class="vc-route-on w-4 h-4 accent-sky-600" ${on ? 'checked' : ''} onchange="vcRenderTransportRoutes()">
                    <span class="font-mono font-bold text-[11px] ${on ? 'text-sky-900' : 'text-slate-400 line-through'}">${esc(route)}</span>
                </label>
                <input type="text"${off} class="vc-route-type col-span-3 border border-slate-300 rounded-lg p-1.5 text-[11px] outline-none focus:ring-2 focus:ring-sky-500" placeholder="Default vehicle" value="${esc(o.type)}">
                <input type="number"${off} step="0.01" min="0" class="vc-route-buy col-span-3 border border-slate-300 rounded-lg p-1.5 text-[11px] font-mono text-rose-600 outline-none" placeholder="Default buy" value="${esc(o.buy)}">
                <input type="number"${off} step="0.01" min="0" class="vc-route-sell col-span-3 border border-slate-300 rounded-lg p-1.5 text-[11px] font-mono font-bold text-emerald-700 outline-none" placeholder="Default sell" value="${esc(o.sell)}">
            </div>`;
    }).join('');
    if (!routes.length) {
        box.innerHTML = '<div class="text-[11px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-md p-2"><i class="fa-solid fa-circle-info mr-1"></i> No hotel added &mdash; add each transfer under Custom Routes below with its date (date is required).</div>';
        // Start the user off with the airport pickup, dated from the outbound flight's arrival.
        if (!document.querySelector('#vc_custom_routes .vc-custom-row')) {
            vcAddCustomRoute({route: 'JED-MAK', date: document.getElementById('vc_out_arr_date')?.value || ''});
        }
    }
}

function vcCollectRouteOverrides() {
    vcCaptureRouteOverrides();
    const active = new Set(vcComputeTransportRoutes());
    return Object.entries(vcRouteOverrides)
        .filter(([route]) => active.has(route))
        .map(([route, o]) => ({route, type: o.type || '', buy: o.buy || '', sell: o.sell || ''}));
}

/** "+ Add Route": extra transfers (Ziyarat, MAK-TAI, ...) with their own date/vehicle/rates. */
function vcAddCustomRoute(data = {}) {
    const box = document.getElementById('vc_custom_routes');
    if (!box) return;
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    box.insertAdjacentHTML('beforeend', `
        <div class="vc-custom-row grid grid-cols-12 gap-2 items-center">
            <input type="text" class="vc-custom-route col-span-3 border border-slate-300 rounded-lg p-1.5 text-[11px] font-mono font-bold uppercase outline-none focus:ring-2 focus:ring-sky-500" placeholder="e.g. MAK-TAI" value="${esc(data.route)}">
            <input type="date" class="vc-custom-date col-span-2 border border-slate-300 rounded-lg p-1.5 text-[11px] outline-none" value="${esc(data.date)}">
            <input type="text" class="vc-custom-type col-span-2 border border-slate-300 rounded-lg p-1.5 text-[11px] outline-none" placeholder="Default vehicle" value="${esc(data.type)}">
            <input type="number" step="0.01" min="0" class="vc-custom-buy col-span-2 border border-slate-300 rounded-lg p-1.5 text-[11px] font-mono text-rose-600 outline-none" placeholder="Default buy" value="${esc(data.buy)}">
            <input type="number" step="0.01" min="0" class="vc-custom-sell col-span-2 border border-slate-300 rounded-lg p-1.5 text-[11px] font-mono font-bold text-emerald-700 outline-none" placeholder="Default sell" value="${esc(data.sell)}">
            <button type="button" onclick="this.closest('.vc-custom-row').remove()" class="col-span-1 text-slate-400 hover:text-rose-600" title="Remove route"><i class="fa-solid fa-trash-can"></i></button>
        </div>`);
    if (!data.route) box.lastElementChild.querySelector('.vc-custom-route').focus();
}

function vcCollectCustomRoutes() {
    return Array.from(document.querySelectorAll('#vc_custom_routes .vc-custom-row')).map(row => ({
        route: row.querySelector('.vc-custom-route').value.trim().toUpperCase(),
        date: row.querySelector('.vc-custom-date').value,
        type: row.querySelector('.vc-custom-type').value.trim(),
        buy: row.querySelector('.vc-custom-buy').value.trim(),
        sell: row.querySelector('.vc-custom-sell').value.trim()
    })).filter(r => r.route);
}

/** Unticked routes that are still part of the current itinerary. */
function vcCollectSkippedRoutes() {
    vcCaptureRouteOverrides();
    return vcComputeTransportRoutes().filter(route => vcSkippedRoutes.has(route));
}

// Keep the route list in sync when stays are added/removed or a city changes.
document.addEventListener('change', e => {
    if (e.target.closest?.('#voucherStaysContainer') && document.getElementById('vc_transport_enabled')?.checked) vcRenderTransportRoutes();
});
document.addEventListener('click', e => {
    if (e.target.closest?.('#voucherStaysContainer, [onclick*="addVoucherStayRow"], [onclick*="addStayRow"]') && document.getElementById('vc_transport_enabled')?.checked) {
        setTimeout(vcRenderTransportRoutes, 0);
    }
});

function vcSearchMasterBookings(query) {
    const resultsBox = document.getElementById('vc_master_search_results');
    if (!resultsBox) return;
    clearTimeout(vcMasterSearchTimer);

    const q = (query || '').trim();
    if (q.length < 2) {
        resultsBox.classList.add('hidden');
        resultsBox.innerHTML = '';
        return;
    }

    const seq = ++vcMasterSearchSeq;
    vcMasterSearchTimer = setTimeout(async () => {
        try {
            const res = await fetch(`index.php?api=search_master_bookings&q=${encodeURIComponent(q)}`);
            const data = await res.json();
            if (seq !== vcMasterSearchSeq) return; // stale response, a newer search superseded it

            const results = (data.results || []).filter(r => !vcSelectedMasterBookings.some(m => String(m.id) === String(r.id)));
            if (!results.length) {
                resultsBox.innerHTML = '<div class="p-3 text-slate-400 text-center">No matching master bookings found.</div>';
                resultsBox.classList.remove('hidden');
                return;
            }

            resultsBox.innerHTML = results.map(r => `
                <button type="button" onclick='vcSelectMasterBooking(${JSON.stringify(r).replace(/'/g, "&#39;")})' class="w-full text-left px-3 py-2 hover:bg-indigo-50 border-b border-slate-100 last:border-b-0 transition">
                    <div class="font-bold text-slate-800">${escapeHtml(r.passenger_name || '-')} <span class="font-mono text-indigo-600 text-[10px]">${escapeHtml(r.booking_code || '')}</span></div>
                    <div class="text-[10px] text-slate-500">
                        ${r.flight_number ? 'Flight ' + escapeHtml(r.flight_number) + ' &middot; ' : ''}
                        Arr: ${escapeHtml(r.arrival_date || '-')} &middot; Dep: ${escapeHtml(r.departure_date || '-')}
                        ${r.agent_name ? ' &middot; Agent: ' + escapeHtml(r.agent_name) : ''}
                    </div>
                </button>
            `).join('');
            resultsBox.classList.remove('hidden');
        } catch (error) {
            resultsBox.innerHTML = '<div class="p-3 text-rose-500 text-center">Search failed. Please retry.</div>';
            resultsBox.classList.remove('hidden');
        }
    }, 300);
}

function vcSelectMasterBooking(booking) {
    if (!vcSelectedMasterBookings.some(m => String(m.id) === String(booking.id))) {
        vcSelectedMasterBookings.push(booking);
    }
    vcRenderSelectedMasterBookings();
    const search = document.getElementById('vc_master_search');
    if (search) search.value = '';
    const resultsBox = document.getElementById('vc_master_search_results');
    if (resultsBox) { resultsBox.classList.add('hidden'); resultsBox.innerHTML = ''; }
}

function vcRemoveMasterBooking(id) {
    vcSelectedMasterBookings = vcSelectedMasterBookings.filter(m => String(m.id) !== String(id));
    vcRenderSelectedMasterBookings();
}

function vcRenderSelectedMasterBookings() {
    const box = document.getElementById('vc_master_selected');
    if (!box) return;
    if (!vcSelectedMasterBookings.length) {
        box.innerHTML = '<span class="text-[11px] text-slate-400 italic">No master bookings assigned yet.</span>';
        return;
    }
    box.innerHTML = vcSelectedMasterBookings.map(m => `
        <span class="inline-flex items-center gap-1.5 bg-indigo-100 text-indigo-800 pl-2.5 pr-1.5 py-1 rounded-full text-[11px] font-semibold">
            <i class="fa-solid fa-user"></i> ${escapeHtml(m.passenger_name || '-')}
            <span class="font-mono text-indigo-500">${escapeHtml(m.booking_code || '')}</span>
            <button type="button" onclick="vcRemoveMasterBooking('${m.id}')" class="w-4 h-4 rounded-full bg-indigo-200 hover:bg-rose-200 hover:text-rose-700 flex items-center justify-center transition" title="Remove">
                <i class="fa-solid fa-xmark text-[9px]"></i>
            </button>
        </span>
    `).join('');
}

document.addEventListener('click', (e) => {
    const wrap = document.getElementById('vc_master_search');
    const resultsBox = document.getElementById('vc_master_search_results');
    if (!wrap || !resultsBox) return;
    if (e.target !== wrap && !resultsBox.contains(e.target)) resultsBox.classList.add('hidden');
});

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('formHotelVoucher')) {
        vcRenderSelectedMasterBookings();
    }
});
