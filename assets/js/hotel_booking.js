/**
 * Only Hotel Booking UI Engine
 * - Dynamic multi-hotel stay rows (e.g. Makkah -> Madinah -> Makkah again)
 * - Per-night buy/sell rates + room quantity on every stay
 * - Date-driven night calculation, auto totals & profit margin
 */

const HOTEL_BOOKING_CITIES = ['Makkah', 'Madinah', 'Jeddah', 'Taif', 'Riyadh', 'Other'];
const HOTEL_BOOKING_ROOM_TYPES = ['Standard', 'Double Bed', 'Triple Bed', 'Quadruple Bed', 'Twin Bed', 'Suite', 'Family Room'];

let editingHotelBookingId = 0;
let editingHotelBookingDate = '';
let hbSelectedMasterBookings = [];
let hbMasterSearchTimer = null;
let hbMasterSearchSeq = 0;

function hbTodayIsoDate() {
    return new Date().toISOString().slice(0, 10);
}

function setHotelBookingField(id, value) {
    const field = document.getElementById(id);
    if (field) field.value = value ?? '';
}

function hbEscapeHtml(value) {
    const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#26206F;'};
    return String(value ?? '').replace(/[&<>"']/g, m => map[m]);
}

function hbCityOptions(selected = 'Makkah') {
    return HOTEL_BOOKING_CITIES.map(c => `<option value="${c}" ${selected === c ? 'selected' : ''}>${c}</option>`).join('');
}

function hbRoomTypeOptions(selected = 'Standard') {
    const options = HOTEL_BOOKING_ROOM_TYPES.map(t => `<option value="${hbEscapeHtml(t)}" ${selected === t ? 'selected' : ''}>${hbEscapeHtml(t)}</option>`).join('');
    return options + `<option value="__custom__" ${selected && !HOTEL_BOOKING_ROOM_TYPES.includes(selected) ? 'selected' : ''}>Custom Room Type...</option>`;
}

function hbToggleCustomRoomType(select) {
    const row = select.closest('.hb-stay-item');
    const customInput = row?.querySelector('.hb-stay-room-custom');
    if (!customInput) return;

    const custom = select.value === '__custom__';
    customInput.classList.toggle('hidden', !custom);
    customInput.required = custom;
    if (!custom) customInput.value = '';
}

function createHotelBookingStayRowHtml() {
    return `
        <div class="md:col-span-2">
            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">City</label>
            <select class="hb-stay-city w-full border border-slate-300 rounded-lg p-2 font-medium bg-slate-50 focus:bg-white outline-none">
                ${hbCityOptions()}
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Hotel Name</label>
            <input type="text" placeholder="Hotel Name" class="hb-stay-hotel w-full border border-slate-300 rounded-lg p-2 font-semibold focus:ring-2 focus:ring-teal-500 outline-none">
        </div>
        <div class="md:col-span-2">
            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Room Type</label>
            <select class="hb-stay-room-type w-full border border-slate-300 rounded-lg p-2 bg-slate-50 focus:bg-white outline-none" onchange="hbToggleCustomRoomType(this)">
                ${hbRoomTypeOptions()}
            </select>
            <input type="text" placeholder="Enter custom room type" class="hb-stay-room-custom hidden mt-1 w-full border border-indigo-300 rounded-lg p-2 bg-indigo-50 outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="md:col-span-1">
            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Meal</label>
            <select class="hb-stay-meal-plan w-full border border-slate-300 rounded-lg p-2 bg-slate-50 outline-none">
                <option value="RO">RO</option>
                <option value="BB">BB</option>
                <option value="HB">HB</option>
                <option value="FB">FB</option>
            </select>
        </div>
        <div class="md:col-span-1">
            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Confirmation #</label>
            <input type="text" placeholder="BRN #" class="hb-stay-confirmation w-full border border-slate-300 rounded-lg p-2 font-mono outline-none uppercase">
        </div>
        <div class="md:col-span-2">
            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Check-In</label>
            <input type="date" onchange="recalculateHotelBookingStayNights(this)" class="hb-stay-checkin w-full border border-slate-300 rounded-lg p-2 font-mono outline-none">
        </div>
        <div class="md:col-span-2">
            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Check-Out</label>
            <input type="date" onchange="recalculateHotelBookingStayNights(this)" class="hb-stay-checkout w-full border border-slate-300 rounded-lg p-2 font-mono outline-none">
        </div>
        <div class="md:col-span-1">
            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Nights</label>
            <input type="number" readonly class="hb-stay-nights w-full border border-slate-300 rounded-lg p-2 font-bold text-teal-700 bg-slate-100 outline-none text-center cursor-not-allowed" value="1" min="1">
        </div>
        <div class="md:col-span-1">
            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Rooms</label>
            <input type="number" oninput="recalculateHotelBookingStayCost(this)" class="hb-stay-rooms w-full border border-slate-300 rounded-lg p-2 font-bold outline-none text-center" value="1" min="1">
        </div>
        <div class="md:col-span-1">
            <label class="block text-[10px] font-semibold text-rose-600 mb-0.5">Buy/Night</label>
            <input type="number" step="0.01" oninput="recalculateHotelBookingStayCost(this)" placeholder="0.00" class="hb-stay-buy-rate w-full border border-slate-300 rounded-lg p-2 font-mono text-rose-600 outline-none text-center">
        </div>
        <div class="md:col-span-1">
            <label class="block text-[10px] font-semibold text-emerald-700 mb-0.5">Sell/Night</label>
            <input type="number" step="0.01" oninput="recalculateHotelBookingStayCost(this)" placeholder="0.00" class="hb-stay-sell-rate w-full border border-slate-300 rounded-lg p-2 font-mono font-bold text-emerald-700 outline-none text-center">
        </div>
        <div class="md:col-span-1 flex items-center justify-between pb-1">
            <div class="text-[11px] font-bold text-slate-700 font-mono hb-stay-total-display">0 SAR</div>
            <button type="button" onclick="removeHotelBookingStayRow(this)" class="text-slate-400 hover:text-rose-600 transition p-1" title="Remove Hotel">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </div>
    `;
}

function addHotelBookingStayRow() {
    const container = document.getElementById('hotelBookingStaysContainer');
    if (!container) return;
    const div = document.createElement('div');
    div.className = 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-12 gap-2 hb-stay-item bg-white p-3 rounded-xl border border-slate-200 shadow-2xs items-end animate-in fade-in duration-150';
    div.innerHTML = createHotelBookingStayRowHtml();
    container.appendChild(div);
    updateGrandHotelBookingTotals();
}

function removeHotelBookingStayRow(button) {
    const row = button.closest('.hb-stay-item');
    const container = document.getElementById('hotelBookingStaysContainer');
    if (!row || !container) return;
    if (container.querySelectorAll('.hb-stay-item').length <= 1) {
        alert('At least one hotel stay is required.');
        return;
    }
    row.remove();
    updateGrandHotelBookingTotals();
}

function hbNightsBetween(checkin, checkout) {
    if (!checkin || !checkout) return 1;
    const d1 = new Date(`${checkin}T00:00:00`);
    const d2 = new Date(`${checkout}T00:00:00`);
    const diffDays = Math.round((d2.getTime() - d1.getTime()) / 86400000);
    return diffDays > 0 ? diffDays : 1;
}

function recalculateHotelBookingStayNights(element) {
    const row = element.closest('.hb-stay-item');
    if (!row) return;
    const checkin = row.querySelector('.hb-stay-checkin')?.value;
    const checkout = row.querySelector('.hb-stay-checkout')?.value;
    const nightsInput = row.querySelector('.hb-stay-nights');
    if (checkin && checkout && nightsInput) {
        nightsInput.value = hbNightsBetween(checkin, checkout);
    }
    recalculateHotelBookingStayCost(element);
}

function recalculateHotelBookingStayCost(element) {
    const row = element.closest('.hb-stay-item');
    if (!row) return;

    const nights = Math.max(0, parseFloat(row.querySelector('.hb-stay-nights')?.value) || 0);
    const rooms = Math.max(1, parseFloat(row.querySelector('.hb-stay-rooms')?.value) || 1);
    const sellRate = Math.max(0, parseFloat(row.querySelector('.hb-stay-sell-rate')?.value) || 0);
    const totalDisplay = row.querySelector('.hb-stay-total-display');
    if (totalDisplay) totalDisplay.textContent = `${(nights * rooms * sellRate).toFixed(2)} SAR`;
    updateGrandHotelBookingTotals();
}

function updateGrandHotelBookingTotals() {
    const rows = document.querySelectorAll('.hb-stay-item');
    let totalNights = 0, totalBuy = 0, totalSell = 0;

    rows.forEach(row => {
        const nights = Math.max(0, parseFloat(row.querySelector('.hb-stay-nights')?.value) || 0);
        const rooms = Math.max(1, parseFloat(row.querySelector('.hb-stay-rooms')?.value) || 1);
        const buyRate = Math.max(0, parseFloat(row.querySelector('.hb-stay-buy-rate')?.value) || 0);
        const sellRate = Math.max(0, parseFloat(row.querySelector('.hb-stay-sell-rate')?.value) || 0);
        totalNights += nights;
        totalBuy += nights * rooms * buyRate;
        totalSell += nights * rooms * sellRate;
    });

    const nightsEl = document.getElementById('hb_grand_total_nights');
    const buyEl = document.getElementById('hb_grand_total_buy');
    const sellEl = document.getElementById('hb_grand_total_sell');
    const profitEl = document.getElementById('hb_grand_total_profit');
    if (nightsEl) nightsEl.textContent = totalNights;
    if (buyEl) buyEl.textContent = totalBuy.toFixed(2);
    if (sellEl) sellEl.textContent = totalSell.toFixed(2);
    if (profitEl) profitEl.textContent = (totalSell - totalBuy).toFixed(2);
}

function addExistingHotelBookingStay(stay) {
    const container = document.getElementById('hotelBookingStaysContainer');
    const row = document.createElement('div');
    row.className = 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-12 gap-2 hb-stay-item bg-white p-3 rounded-xl border border-slate-200 shadow-2xs items-end';
    row.innerHTML = createHotelBookingStayRowHtml();
    container.appendChild(row);

    row.querySelector('.hb-stay-city').value = stay.city || 'Makkah';
    row.querySelector('.hb-stay-hotel').value = stay.hotel_name || '';
    const knownRoomType = HOTEL_BOOKING_ROOM_TYPES.includes(stay.room_type);
    row.querySelector('.hb-stay-room-type').value = knownRoomType ? stay.room_type : '__custom__';
    row.querySelector('.hb-stay-room-custom').value = knownRoomType ? '' : (stay.room_type || '');
    hbToggleCustomRoomType(row.querySelector('.hb-stay-room-type'));
    row.querySelector('.hb-stay-meal-plan').value = stay.meal_plan || 'RO';
    row.querySelector('.hb-stay-confirmation').value = stay.confirmation_number || '';
    row.querySelector('.hb-stay-checkin').value = stay.checkin_date || '';
    row.querySelector('.hb-stay-checkout').value = stay.checkout_date || '';
    row.querySelector('.hb-stay-nights').value = stay.nights || 1;
    row.querySelector('.hb-stay-rooms').value = stay.rooms || 1;
    row.querySelector('.hb-stay-buy-rate').value = stay.buy_rate_per_night || 0;
    row.querySelector('.hb-stay-sell-rate').value = stay.sell_rate_per_night || 0;
    recalculateHotelBookingStayCost(row.querySelector('.hb-stay-sell-rate'));
}

function hbToggleTransportSection() {
    const enabled = document.getElementById('hb_transport_enabled')?.checked;
    document.getElementById('hb_transport_fields')?.classList.toggle('hidden', !enabled);
    if (enabled) hbRenderTransportRoutes();
}

/** Per-route vehicle/rate overrides, keyed by route (e.g. "MAK-JED"). Blank = use the default Transport Type/rates. */
let hbRouteOverrides = {};
/** Routes the user has unticked - no transfer is created for them. */
let hbSkippedRoutes = new Set();

const HB_CITY_CODES = {Makkah: 'MAK', Madinah: 'MED', Jeddah: 'JED', Taif: 'TAI', Riyadh: 'RUH'};

/** Mirrors HotelBookingController::regenerateAutoTransport() leg building. */
function hbComputeTransportRoutes() {
    const cities = Array.from(document.querySelectorAll('#hotelBookingStaysContainer .hb-stay-item'))
        .map(row => HB_CITY_CODES[row.querySelector('.hb-stay-city')?.value] || 'MAK');
    if (!cities.length) return [];
    const routes = ['JED-' + cities[0]];
    for (let i = 0; i < cities.length - 1; i++) {
        if (cities[i] !== cities[i + 1]) routes.push(cities[i] + '-' + cities[i + 1]);
    }
    routes.push(cities[cities.length - 1] + '-JED');
    return [...new Set(routes)];
}

function hbCaptureRouteOverrides() {
    document.querySelectorAll('#hb_transport_routes .hb-route-row').forEach(row => {
        const route = row.dataset.route;
        if (row.querySelector('.hb-route-on').checked) hbSkippedRoutes.delete(route);
        else hbSkippedRoutes.add(route);
        const o = {
            type: row.querySelector('.hb-route-type').value.trim(),
            buy: row.querySelector('.hb-route-buy').value.trim(),
            sell: row.querySelector('.hb-route-sell').value.trim()
        };
        if (o.type || o.buy || o.sell) hbRouteOverrides[route] = o;
        else delete hbRouteOverrides[route];
    });
}

function hbRenderTransportRoutes() {
    const box = document.getElementById('hb_transport_routes');
    if (!box) return;
    hbCaptureRouteOverrides();
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    const routes = hbComputeTransportRoutes();
    box.innerHTML = routes.map(route => {
        const o = hbRouteOverrides[route] || {};
        const on = !hbSkippedRoutes.has(route);
        const off = on ? '' : ' disabled';
        return `
            <div class="hb-route-row grid grid-cols-12 gap-2 items-center${on ? '' : ' opacity-50'}" data-route="${esc(route)}">
                <label class="col-span-3 flex items-center gap-2 cursor-pointer select-none" title="Untick to skip this transfer">
                    <input type="checkbox" class="hb-route-on w-4 h-4 accent-sky-600" ${on ? 'checked' : ''} onchange="hbRenderTransportRoutes()">
                    <span class="font-mono font-bold text-[11px] ${on ? 'text-sky-900' : 'text-slate-400 line-through'}">${esc(route)}</span>
                </label>
                <input type="text"${off} class="hb-route-type col-span-3 border border-slate-300 rounded-lg p-1.5 text-[11px] outline-none focus:ring-2 focus:ring-sky-500" placeholder="Default vehicle" value="${esc(o.type)}">
                <input type="number"${off} step="0.01" min="0" class="hb-route-buy col-span-3 border border-slate-300 rounded-lg p-1.5 text-[11px] font-mono text-rose-600 outline-none" placeholder="Default buy" value="${esc(o.buy)}">
                <input type="number"${off} step="0.01" min="0" class="hb-route-sell col-span-3 border border-slate-300 rounded-lg p-1.5 text-[11px] font-mono font-bold text-emerald-700 outline-none" placeholder="Default sell" value="${esc(o.sell)}">
            </div>`;
    }).join('') || '<div class="text-[11px] text-slate-400">Add a hotel stay to see the routes.</div>';
}

function hbCollectRouteOverrides() {
    hbCaptureRouteOverrides();
    const active = new Set(hbComputeTransportRoutes());
    return Object.entries(hbRouteOverrides)
        .filter(([route]) => active.has(route))
        .map(([route, o]) => ({route, type: o.type || '', buy: o.buy || '', sell: o.sell || ''}));
}

/** "+ Add Route": extra transfers (Ziyarat, MAK-TAI, ...) with their own date/vehicle/rates. */
function hbAddCustomRoute(data = {}) {
    const box = document.getElementById('hb_custom_routes');
    if (!box) return;
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    box.insertAdjacentHTML('beforeend', `
        <div class="hb-custom-row grid grid-cols-12 gap-2 items-center">
            <input type="text" class="hb-custom-route col-span-3 border border-slate-300 rounded-lg p-1.5 text-[11px] font-mono font-bold uppercase outline-none focus:ring-2 focus:ring-sky-500" placeholder="e.g. MAK-TAI" value="${esc(data.route)}">
            <input type="date" class="hb-custom-date col-span-2 border border-slate-300 rounded-lg p-1.5 text-[11px] outline-none" value="${esc(data.date)}">
            <input type="text" class="hb-custom-type col-span-2 border border-slate-300 rounded-lg p-1.5 text-[11px] outline-none" placeholder="Default vehicle" value="${esc(data.type)}">
            <input type="number" step="0.01" min="0" class="hb-custom-buy col-span-2 border border-slate-300 rounded-lg p-1.5 text-[11px] font-mono text-rose-600 outline-none" placeholder="Default buy" value="${esc(data.buy)}">
            <input type="number" step="0.01" min="0" class="hb-custom-sell col-span-2 border border-slate-300 rounded-lg p-1.5 text-[11px] font-mono font-bold text-emerald-700 outline-none" placeholder="Default sell" value="${esc(data.sell)}">
            <button type="button" onclick="this.closest('.hb-custom-row').remove()" class="col-span-1 text-slate-400 hover:text-rose-600" title="Remove route"><i class="fa-solid fa-trash-can"></i></button>
        </div>`);
    if (!data.route) box.lastElementChild.querySelector('.hb-custom-route').focus();
}

function hbCollectCustomRoutes() {
    return Array.from(document.querySelectorAll('#hb_custom_routes .hb-custom-row')).map(row => ({
        route: row.querySelector('.hb-custom-route').value.trim().toUpperCase(),
        date: row.querySelector('.hb-custom-date').value,
        type: row.querySelector('.hb-custom-type').value.trim(),
        buy: row.querySelector('.hb-custom-buy').value.trim(),
        sell: row.querySelector('.hb-custom-sell').value.trim()
    })).filter(r => r.route);
}

/** Unticked routes that are still part of the current itinerary. */
function hbCollectSkippedRoutes() {
    hbCaptureRouteOverrides();
    return hbComputeTransportRoutes().filter(route => hbSkippedRoutes.has(route));
}

// Keep the route list in sync when stays are added/removed or a city changes.
document.addEventListener('change', e => {
    if (e.target.closest?.('#hotelBookingStaysContainer') && document.getElementById('hb_transport_enabled')?.checked) hbRenderTransportRoutes();
});
document.addEventListener('click', e => {
    if (e.target.closest?.('#hotelBookingStaysContainer, [onclick*="addHotelBookingStayRow"]') && document.getElementById('hb_transport_enabled')?.checked) {
        setTimeout(hbRenderTransportRoutes, 0);
    }
});

function hbSearchMasterBookings(query) {
    const resultsBox = document.getElementById('hb_master_search_results');
    if (!resultsBox) return;
    clearTimeout(hbMasterSearchTimer);

    const q = (query || '').trim();
    if (q.length < 2) {
        resultsBox.classList.add('hidden');
        resultsBox.innerHTML = '';
        return;
    }

    const seq = ++hbMasterSearchSeq;
    hbMasterSearchTimer = setTimeout(async () => {
        try {
            const res = await fetch(`index.php?api=search_master_bookings&q=${encodeURIComponent(q)}`);
            const data = await res.json();
            if (seq !== hbMasterSearchSeq) return; // stale response, a newer search superseded it

            const results = (data.results || []).filter(r => !hbSelectedMasterBookings.some(m => String(m.id) === String(r.id)));
            if (!results.length) {
                resultsBox.innerHTML = '<div class="p-3 text-slate-400 text-center">No matching master bookings found.</div>';
                resultsBox.classList.remove('hidden');
                return;
            }

            resultsBox.innerHTML = results.map(r => `
                <button type="button" onclick='hbSelectMasterBooking(${JSON.stringify(r).replace(/'/g, "&#39;")})' class="w-full text-left px-3 py-2 hover:bg-indigo-50 border-b border-slate-100 last:border-b-0 transition">
                    <div class="font-bold text-slate-800">${hbEscapeHtml(r.passenger_name || '-')} <span class="font-mono text-indigo-600 text-[10px]">${hbEscapeHtml(r.booking_code || '')}</span></div>
                    <div class="text-[10px] text-slate-500">
                        ${r.flight_number ? 'Flight ' + hbEscapeHtml(r.flight_number) + ' &middot; ' : ''}
                        Arr: ${hbEscapeHtml(r.arrival_date || '-')} &middot; Dep: ${hbEscapeHtml(r.departure_date || '-')}
                        ${r.agent_name ? ' &middot; Agent: ' + hbEscapeHtml(r.agent_name) : ''}
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

function hbSelectMasterBooking(booking) {
    if (!hbSelectedMasterBookings.some(m => String(m.id) === String(booking.id))) {
        hbSelectedMasterBookings.push(booking);
    }
    hbRenderSelectedMasterBookings();
    const search = document.getElementById('hb_master_search');
    if (search) search.value = '';
    const resultsBox = document.getElementById('hb_master_search_results');
    if (resultsBox) { resultsBox.classList.add('hidden'); resultsBox.innerHTML = ''; }
}

function hbRemoveMasterBooking(id) {
    hbSelectedMasterBookings = hbSelectedMasterBookings.filter(m => String(m.id) !== String(id));
    hbRenderSelectedMasterBookings();
}

function hbRenderSelectedMasterBookings() {
    const box = document.getElementById('hb_master_selected');
    if (!box) return;
    if (!hbSelectedMasterBookings.length) {
        box.innerHTML = '<span class="text-[11px] text-slate-400 italic">No master bookings assigned yet.</span>';
        return;
    }
    box.innerHTML = hbSelectedMasterBookings.map(m => `
        <span class="inline-flex items-center gap-1.5 bg-indigo-100 text-indigo-800 pl-2.5 pr-1.5 py-1 rounded-full text-[11px] font-semibold">
            <i class="fa-solid fa-user"></i> ${hbEscapeHtml(m.passenger_name || '-')}
            <span class="font-mono text-indigo-500">${hbEscapeHtml(m.booking_code || '')}</span>
            <button type="button" onclick="hbRemoveMasterBooking('${m.id}')" class="w-4 h-4 rounded-full bg-indigo-200 hover:bg-rose-200 hover:text-rose-700 flex items-center justify-center transition" title="Remove">
                <i class="fa-solid fa-xmark text-[9px]"></i>
            </button>
        </span>
    `).join('');
}

document.addEventListener('click', (e) => {
    const wrap = document.getElementById('hb_master_search');
    const resultsBox = document.getElementById('hb_master_search_results');
    if (!wrap || !resultsBox) return;
    if (e.target !== wrap && !resultsBox.contains(e.target)) resultsBox.classList.add('hidden');
});

function resetHotelBookingForm() {
    editingHotelBookingId = 0;
    editingHotelBookingDate = '';
    document.getElementById('formHotelBooking')?.reset();
    setHotelBookingField('hb_booking_ref', '');
    setHotelBookingField('hb_booking_date', hbTodayIsoDate());
    setHotelBookingField('hb_company_name', 'Aeroheights Travels & Tours');
    setHotelBookingField('hb_pax_adults', 1);
    setHotelBookingField('hb_pax_children', 0);
    setHotelBookingField('hb_pax_infants', 0);

    const staysContainer = document.getElementById('hotelBookingStaysContainer');
    if (staysContainer) staysContainer.innerHTML = '';
    addHotelBookingStayRow();
    updateGrandHotelBookingTotals();

    hbSelectedMasterBookings = [];
    hbRenderSelectedMasterBookings();
    const searchResults = document.getElementById('hb_master_search_results');
    if (searchResults) { searchResults.classList.add('hidden'); searchResults.innerHTML = ''; }

    setHotelBookingField('hb_transport_type', 'CAR');
    setHotelBookingField('hb_transport_buy_rate', '');
    setHotelBookingField('hb_transport_sell_rate', '');
    const transportCheckbox = document.getElementById('hb_transport_enabled');
    if (transportCheckbox) transportCheckbox.checked = false;
    hbRouteOverrides = {};
    hbSkippedRoutes = new Set();
    document.getElementById('hb_transport_routes').innerHTML = '';
    document.getElementById('hb_custom_routes').innerHTML = '';
    hbToggleTransportSection();

    const btn = document.getElementById('hotelBookingSubmitButton');
    if (btn) btn.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1"></i> Save Booking';
}

async function submitHotelBookingForm(e) {
    e.preventDefault();

    const stayRows = Array.from(document.querySelectorAll('.hb-stay-item'));
    const stays = stayRows.map(row => ({
        city: row.querySelector('.hb-stay-city')?.value || 'Makkah',
        hotel_name: row.querySelector('.hb-stay-hotel')?.value.trim() || '',
        room_type: row.querySelector('.hb-stay-room-type')?.value || 'Standard',
        custom_room_type: row.querySelector('.hb-stay-room-custom')?.value.trim() || '',
        meal_plan: row.querySelector('.hb-stay-meal-plan')?.value || 'RO',
        confirmation_number: row.querySelector('.hb-stay-confirmation')?.value.trim() || '',
        checkin_date: row.querySelector('.hb-stay-checkin')?.value || '',
        checkout_date: row.querySelector('.hb-stay-checkout')?.value || '',
        nights: Math.max(1, parseInt(row.querySelector('.hb-stay-nights')?.value || '1', 10)),
        rooms: Math.max(1, parseInt(row.querySelector('.hb-stay-rooms')?.value || '1', 10)),
        buy_rate_per_night: Math.max(0, parseFloat(row.querySelector('.hb-stay-buy-rate')?.value || '0')),
        sell_rate_per_night: Math.max(0, parseFloat(row.querySelector('.hb-stay-sell-rate')?.value || '0'))
    }));

    for (let i = 0; i < stays.length; i++) {
        if (!stays[i].hotel_name) { alert(`Hotel name is required for Hotel #${i + 1}.`); return; }
        if (stays[i].room_type === '__custom__' && !stays[i].custom_room_type) { alert(`Custom room type is required for Hotel #${i + 1}.`); return; }
        if (!stays[i].checkin_date || !stays[i].checkout_date) { alert(`Check-in and Check-out are required for Hotel #${i + 1}.`); return; }
        if (new Date(`${stays[i].checkout_date}T00:00:00`) <= new Date(`${stays[i].checkin_date}T00:00:00`)) { alert(`Check-out must be after Check-in for Hotel #${i + 1}.`); return; }
    }

    const adults = Math.max(0, parseInt(document.getElementById('hb_pax_adults')?.value || '0', 10));
    const children = Math.max(0, parseInt(document.getElementById('hb_pax_children')?.value || '0', 10));
    const infants = Math.max(0, parseInt(document.getElementById('hb_pax_infants')?.value || '0', 10));
    if (adults + children + infants <= 0) { alert('At least one guest is required.'); return; }

    const payload = {
        agent_id: document.getElementById('hb_agent_id').value,
        booking_date: document.getElementById('hb_booking_date').value || editingHotelBookingDate || hbTodayIsoDate(),
        lead_guest_name: document.getElementById('hb_lead_guest_name').value.trim(),
        company_name: document.getElementById('hb_company_name')?.value.trim() || 'Aeroheights Travels & Tours',
        pax_adults: adults,
        pax_children: children,
        pax_infants: infants,
        remarks: document.getElementById('hb_remarks').value.trim(),
        stays,
        master_booking_ids: hbSelectedMasterBookings.map(m => m.id),
        transport_enabled: !!document.getElementById('hb_transport_enabled')?.checked,
        transport_type: document.getElementById('hb_transport_type')?.value.trim() || 'CAR',
        transport_buy_rate: Math.max(0, parseFloat(document.getElementById('hb_transport_buy_rate')?.value || '0')),
        transport_sell_rate: Math.max(0, parseFloat(document.getElementById('hb_transport_sell_rate')?.value || '0')),
        transport_route_overrides: hbCollectRouteOverrides(),
        transport_skip_routes: hbCollectSkippedRoutes(),
        transport_custom_routes: hbCollectCustomRoutes()
    };

    const submitBtn = e.submitter;
    if (submitBtn) { submitBtn.disabled = true; submitBtn.classList.add('opacity-60', 'cursor-not-allowed'); }

    try {
        const res = await fetch(`index.php?api=${editingHotelBookingId ? 'update_hotel_booking' : 'save_hotel_booking'}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''},
            body: JSON.stringify(editingHotelBookingId ? {...payload, id: editingHotelBookingId} : payload)
        });
        const raw = await res.text();
        let data;
        try {
            data = JSON.parse(raw);
        } catch (parseError) {
            console.error('Invalid JSON response:', raw);
            throw new Error('Server returned invalid response. Check PHP error logs.');
        }
        if (data.success) {
            const savedId = editingHotelBookingId || data.booking_id;
            window.open(`index.php?page=print_hotel_booking_invoice&id=${savedId}`, '_blank');
            window.location.reload();
        } else {
            alert(data.message || 'Error saving hotel booking');
            if (submitBtn) { submitBtn.disabled = false; submitBtn.classList.remove('opacity-60', 'cursor-not-allowed'); }
        }
    } catch (error) {
        alert('Network or server error: ' + error.message);
        if (submitBtn) { submitBtn.disabled = false; submitBtn.classList.remove('opacity-60', 'cursor-not-allowed'); }
    }
}

async function editHotelBooking(id) {
    try {
        const response = await fetch(`index.php?api=get_hotel_booking&id=${id}`);
        const result = await response.json();
        if (!result.success || !result.booking) throw new Error(result.message || 'Hotel booking not found.');
        const b = result.booking;
        editingHotelBookingId = id;
        editingHotelBookingDate = b.booking_date || '';

        setHotelBookingField('hb_agent_id', b.agent_id);
        setHotelBookingField('hb_booking_ref', b.booking_ref);
        setHotelBookingField('hb_booking_date', b.booking_date || hbTodayIsoDate());
        setHotelBookingField('hb_lead_guest_name', b.lead_guest_name);
        setHotelBookingField('hb_company_name', b.company_name || 'Aeroheights Travels & Tours');
        setHotelBookingField('hb_pax_adults', b.pax_adults);
        setHotelBookingField('hb_pax_children', b.pax_children);
        setHotelBookingField('hb_pax_infants', b.pax_infants);
        setHotelBookingField('hb_remarks', b.remarks);

        const staysContainer = document.getElementById('hotelBookingStaysContainer');
        if (staysContainer) staysContainer.innerHTML = '';
        (b.stays || []).forEach(addExistingHotelBookingStay);
        if (!b.stays?.length) addHotelBookingStayRow();
        updateGrandHotelBookingTotals();

        hbSelectedMasterBookings = (b.master_bookings || []).map(m => ({
            id: m.id, passenger_name: m.passenger_name, booking_code: m.booking_code,
            flight_number: m.flight_number, arrival_date: m.arrival_date, departure_date: m.departure_date
        }));
        hbRenderSelectedMasterBookings();
        const searchResults = document.getElementById('hb_master_search_results');
        if (searchResults) { searchResults.classList.add('hidden'); searchResults.innerHTML = ''; }

        setHotelBookingField('hb_transport_type', b.transport_type || 'CAR');
        setHotelBookingField('hb_transport_buy_rate', b.transport_buy_rate || '');
        setHotelBookingField('hb_transport_sell_rate', b.transport_sell_rate || '');
        const transportCheckbox = document.getElementById('hb_transport_enabled');
        if (transportCheckbox) transportCheckbox.checked = !!Number(b.transport_enabled);
        hbRouteOverrides = {};
        document.getElementById('hb_transport_routes').innerHTML = '';
        document.getElementById('hb_custom_routes').innerHTML = '';
        (b.transport_legs || []).filter(leg => Number(leg.auto_generated) === 2).forEach(leg => hbAddCustomRoute({
            route: leg.route_details, date: leg.service_date, type: leg.vehicle_type,
            buy: String(Number(leg.buy_rate_sar)), sell: String(Number(leg.sell_rate_sar))
        }));
        const hbItineraryLegs = (b.transport_legs || []).filter(leg => Number(leg.auto_generated) !== 2);
        hbItineraryLegs.forEach(leg => {
            const o = {};
            if ((leg.vehicle_type || '') !== (b.transport_type || 'CAR')) o.type = leg.vehicle_type || '';
            if (Number(leg.buy_rate_sar) !== Number(b.transport_buy_rate || 0)) o.buy = String(Number(leg.buy_rate_sar));
            if (Number(leg.sell_rate_sar) !== Number(b.transport_sell_rate || 0)) o.sell = String(Number(leg.sell_rate_sar));
            if (Object.keys(o).length) hbRouteOverrides[String(leg.route_details).toUpperCase()] = o;
        });
        hbSkippedRoutes = new Set();
        // Transport was on but a route has no saved transfer -> it was unticked last time.
        if (Number(b.transport_enabled) && hbItineraryLegs.length) {
            const savedRoutes = new Set(hbItineraryLegs.map(leg => String(leg.route_details).toUpperCase()));
            hbComputeTransportRoutes().forEach(route => { if (!savedRoutes.has(route)) hbSkippedRoutes.add(route); });
        }
        hbToggleTransportSection();

        const btn = document.getElementById('hotelBookingSubmitButton');
        if (btn) btn.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1"></i> Update Booking';
        document.getElementById('hotelBookingFormContainer').classList.remove('hidden');
        document.getElementById('hotelBookingFormContainer').scrollIntoView({behavior: 'smooth', block: 'start'});
    } catch (error) {
        alert(error.message || 'Unable to load hotel booking.');
    }
}

async function deleteHotelBooking(id) {
    if (!confirm('Delete this hotel booking? It will be removed from the booking list and agent ledger.')) return;
    const response = await fetch('index.php?api=delete_hotel_booking', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''},
        body: JSON.stringify({id})
    });
    const result = await response.json();
    if (result.success) location.reload();
    else alert(result.message || 'Unable to delete hotel booking.');
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('formHotelBooking')) {
        setHotelBookingField('hb_booking_date', hbTodayIsoDate());
        if (!document.querySelectorAll('.hb-stay-item').length) {
            addHotelBookingStayRow();
        }
        hbRenderSelectedMasterBookings();
    }
});
