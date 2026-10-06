<?php
declare(strict_types=1);

/**
 * Complete Package Quotation builder (new = ?page=packages&new=1, edit = ?page=packages&id=N).
 * Saved through the save_package_quotation API; "Preview" posts the unsaved form to the print view.
 * An unsaved new quotation is kept as a draft in the browser.
 */

require_once __DIR__ . '/../../controllers/PackageQuotationController.php';
require_once __DIR__ . '/../../config/Auth.php';

$quoteId = (int)($_GET['id'] ?? 0);
$existing = null;
if ($quoteId > 0) {
    $record = PackageQuotationController::getById($quoteId);
    if (!$record) {
        echo '<main class="md:col-span-9"><div class="bg-white p-8 rounded-2xl border border-slate-200 text-center text-sm text-rose-600">Quotation not found. <a href="index.php?page=packages" class="text-blue-600 underline">Back to quotations</a></div></main>';
        return;
    }
    $existing = $record['data'];
}
$canWrite = Auth::canWrite();

$standardIncludes = [
    'visa'      => 'Umrah E-Visa',
    'tickets'   => 'Return Tickets',
    'hotel'     => 'Hotel Accommodation',
    'transport' => 'Transport',
    'ziyarat'   => 'Ziyarat',
    'insurance' => 'Travel Insurance',
];
$defaultChecked = ['visa', 'tickets', 'hotel', 'transport'];
$roomTypes = ['DBL', 'TRPL', 'QUAD', 'QUINT', 'SHARING', 'SINGLE'];
$inputCls = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400';
$labelCls = 'block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1';
?>

<main class="md:col-span-9 space-y-6">

    <!-- Header Bar -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col lg:flex-row items-center justify-between gap-4">
        <div>
            <a href="index.php?page=packages" class="text-[11px] font-semibold text-slate-500 hover:text-orange-600"><i class="fa-solid fa-arrow-left mr-1"></i> All Quotations</a>
            <h2 class="text-base font-bold text-slate-800 flex items-center mt-1">
                <i class="fa-solid fa-suitcase-rolling text-orange-500 mr-2"></i>
                <span id="formTitle"><?= $existing ? 'Edit Quotation ' . htmlspecialchars($existing['quote_no']) : 'New Package Quotation' ?></span>
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Fill in the Umrah package details, save it, then print the branded quotation or save it as PDF.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?php if (!$existing): ?>
                <button type="button" onclick="resetPackageForm()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 transition" title="Clear the form">
                    <i class="fa-solid fa-rotate-left mr-1"></i> Clear
                </button>
            <?php endif; ?>
            <button type="button" onclick="previewQuotation()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 transition" title="Preview without saving">
                <i class="fa-solid fa-eye mr-1"></i> Preview
            </button>
            <?php if ($canWrite): ?>
                <button type="button" onclick="saveQuotation(false)" class="save-btn bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold px-4 py-2 rounded-xl transition">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Save
                </button>
                <button type="button" onclick="saveQuotation(true)" class="save-btn bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold px-4 py-2 rounded-xl shadow-md shadow-orange-500/20 transition">
                    <i class="fa-solid fa-print mr-1"></i> Save &amp; Print
                </button>
            <?php endif; ?>
        </div>
    </div>

    <form id="packageForm" method="post" action="index.php?page=print_package" target="_blank" class="space-y-6" autocomplete="off" onsubmit="event.preventDefault()">

        <!-- 1. Quotation & Client -->
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="bg-slate-50 border-b border-slate-200 px-5 py-3">
                <h3 class="font-bold text-slate-800 text-xs flex items-center"><i class="fa-solid fa-file-signature mr-2 text-orange-500"></i> Quotation &amp; Client</h3>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="<?= $labelCls ?>">Quotation No.</label>
                    <input type="text" name="quote_no" id="quoteNo" class="<?= $inputCls ?> font-mono">
                </div>
                <div>
                    <label class="<?= $labelCls ?>">Quotation Date</label>
                    <input type="date" name="quote_date" value="<?= date('Y-m-d') ?>" class="<?= $inputCls ?>">
                </div>
                <div>
                    <label class="<?= $labelCls ?>">Valid Until</label>
                    <input type="date" name="valid_until" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" class="<?= $inputCls ?>">
                </div>
                <div>
                    <label class="<?= $labelCls ?>">Client Name</label>
                    <input type="text" name="client_name" placeholder="Blank = &quot;Dear Client&quot;" class="<?= $inputCls ?>">
                </div>
                <div class="sm:col-span-2 lg:col-span-4">
                    <label class="<?= $labelCls ?>">Intro Line</label>
                    <input type="text" name="intro" value="Details of your Umrah package are mentioned below." class="<?= $inputCls ?>">
                </div>
            </div>
        </section>

        <!-- 2. Package Includes & Mutamers -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="bg-slate-50 border-b border-slate-200 px-5 py-3">
                    <h3 class="font-bold text-slate-800 text-xs flex items-center"><i class="fa-solid fa-list-check mr-2 text-orange-500"></i> Package Includes</h3>
                </div>
                <div class="p-5 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <?php foreach ($standardIncludes as $key => $label): ?>
                            <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 cursor-pointer hover:bg-orange-50">
                                <input type="checkbox" name="includes[]" value="<?= $key ?>" <?= in_array($key, $defaultChecked, true) ? 'checked' : '' ?> class="h-4 w-4 accent-orange-500">
                                <?= htmlspecialchars($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div>
                        <label class="<?= $labelCls ?>">Other Inclusions (one per line)</label>
                        <textarea name="custom_includes" rows="2" placeholder="e.g. Makkah Ziyarat by Coach" class="<?= $inputCls ?>"></textarea>
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="bg-slate-50 border-b border-slate-200 px-5 py-3">
                    <h3 class="font-bold text-slate-800 text-xs flex items-center"><i class="fa-solid fa-users mr-2 text-orange-500"></i> Details of Mutamers</h3>
                </div>
                <div class="p-5 grid grid-cols-3 gap-4">
                    <div>
                        <label class="<?= $labelCls ?>">Adults</label>
                        <input type="number" min="0" name="adults" value="1" class="<?= $inputCls ?> pax-input">
                    </div>
                    <div>
                        <label class="<?= $labelCls ?>">Child</label>
                        <input type="number" min="0" name="children" value="0" class="<?= $inputCls ?> pax-input">
                    </div>
                    <div>
                        <label class="<?= $labelCls ?>">Infants</label>
                        <input type="number" min="0" name="infants" value="0" class="<?= $inputCls ?> pax-input">
                    </div>
                </div>
            </section>
        </div>

        <!-- 3. Package Summary (Hotels) -->
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="bg-slate-50 border-b border-slate-200 px-5 py-3 flex items-center justify-between">
                <h3 class="font-bold text-slate-800 text-xs flex items-center"><i class="fa-solid fa-hotel mr-2 text-orange-500"></i> Package Summary &mdash; Hotels</h3>
                <div class="flex items-center gap-2">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total Days</label>
                    <input type="number" min="0" name="total_days" id="totalDays" class="w-20 rounded-lg border border-slate-300 px-2 py-1 text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-orange-400" title="Auto-calculated from flight dates; you can overwrite it">
                </div>
            </div>
            <div class="p-5 space-y-3">
                <div class="hidden md:grid grid-cols-12 gap-2 px-1">
                    <div class="col-span-2 <?= $labelCls ?>">City</div>
                    <div class="col-span-4 <?= $labelCls ?>">Hotel</div>
                    <div class="col-span-2 <?= $labelCls ?>">Distance</div>
                    <div class="col-span-2 <?= $labelCls ?>">Room</div>
                    <div class="col-span-1 <?= $labelCls ?>">Nights</div>
                </div>
                <div id="hotelRows" class="space-y-2"></div>
                <button type="button" onclick="addHotelRow()" class="text-xs font-semibold text-orange-600 hover:text-orange-700">
                    <i class="fa-solid fa-plus mr-1"></i> Add Hotel
                </button>
            </div>
        </section>

        <!-- 4. Flights -->
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="bg-slate-50 border-b border-slate-200 px-5 py-3">
                <h3 class="font-bold text-slate-800 text-xs flex items-center"><i class="fa-solid fa-plane mr-2 text-orange-500"></i> Flights</h3>
            </div>
            <div class="p-5 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="rounded-xl border border-orange-100 bg-orange-50/40 p-4 space-y-3">
                    <div class="text-xs font-bold text-slate-800"><i class="fa-solid fa-plane-departure text-orange-500 mr-1"></i> Departure to KSA</div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="<?= $labelCls ?>">Sector</label>
                            <input type="text" name="dep_sector" placeholder="SKT-JED" class="<?= $inputCls ?> uppercase">
                        </div>
                        <div>
                            <label class="<?= $labelCls ?>">Date</label>
                            <input type="date" name="dep_date" id="depDate" class="<?= $inputCls ?>">
                        </div>
                        <div>
                            <label class="<?= $labelCls ?>">Flight No.</label>
                            <input type="text" name="dep_flight" placeholder="PF-718" class="<?= $inputCls ?> uppercase">
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-orange-100 bg-orange-50/40 p-4 space-y-3">
                    <div class="text-xs font-bold text-slate-800"><i class="fa-solid fa-plane-arrival text-orange-500 mr-1"></i> Departure from KSA</div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="<?= $labelCls ?>">Sector</label>
                            <input type="text" name="ret_sector" placeholder="JED-SKT" class="<?= $inputCls ?> uppercase">
                        </div>
                        <div>
                            <label class="<?= $labelCls ?>">Date</label>
                            <input type="date" name="ret_date" id="retDate" class="<?= $inputCls ?>">
                        </div>
                        <div>
                            <label class="<?= $labelCls ?>">Flight No.</label>
                            <input type="text" name="ret_flight" placeholder="PF-719" class="<?= $inputCls ?> uppercase">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 5. Note, Pricing & Signatory -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="bg-slate-50 border-b border-slate-200 px-5 py-3">
                    <h3 class="font-bold text-slate-800 text-xs flex items-center"><i class="fa-solid fa-note-sticky mr-2 text-orange-500"></i> Note &amp; Signatory</h3>
                </div>
                <div class="p-5 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="<?= $labelCls ?>">Airline</label>
                            <input type="text" name="airline" placeholder="Air Sial" class="<?= $inputCls ?>">
                        </div>
                        <div>
                            <label class="<?= $labelCls ?>">Baggage</label>
                            <input type="text" name="baggage" placeholder="7+20kg on both sides" class="<?= $inputCls ?>">
                        </div>
                    </div>
                    <div>
                        <label class="<?= $labelCls ?>">Additional Note</label>
                        <textarea name="note" rows="3" placeholder="Any other terms or remarks for the client" class="<?= $inputCls ?>"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="<?= $labelCls ?>">Signatory Name</label>
                            <input type="text" name="sign_name" class="<?= $inputCls ?>">
                        </div>
                        <div>
                            <label class="<?= $labelCls ?>">Designation</label>
                            <input type="text" name="sign_title" placeholder="CEO, Aeroheights Travels &amp; Tours" class="<?= $inputCls ?>">
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="bg-slate-900 px-5 py-3">
                    <h3 class="font-bold text-white text-xs flex items-center"><i class="fa-solid fa-tags mr-2 text-orange-400"></i> Pricing Details (PKR)</h3>
                </div>
                <div class="p-5 space-y-3">
                    <?php foreach (['adult' => 'Adult', 'child' => 'Child', 'infant' => 'Infant'] as $k => $lbl): ?>
                        <div class="grid grid-cols-3 gap-3 items-end">
                            <div class="col-span-2">
                                <label class="<?= $labelCls ?>">1 <?= $lbl ?> Price</label>
                                <input type="number" min="0" step="any" name="price_<?= $k ?>" class="<?= $inputCls ?> price-input font-mono">
                            </div>
                            <div class="text-right text-[11px] text-slate-500 pb-2 font-mono" id="sub_<?= $k ?>">&times; 0 = 0</div>
                        </div>
                    <?php endforeach; ?>
                    <div class="border-t-2 border-orange-400 pt-3 grid grid-cols-3 gap-3 items-end">
                        <div class="col-span-2">
                            <label class="<?= $labelCls ?>">Total Price <span class="normal-case font-medium text-slate-400">(auto &mdash; edit to override)</span></label>
                            <input type="number" min="0" step="any" name="total_price" id="totalPrice" class="<?= $inputCls ?> font-mono font-bold text-orange-600">
                        </div>
                        <div class="pb-2 text-right">
                            <button type="button" onclick="recalcTotal(true)" class="text-[11px] font-semibold text-slate-500 hover:text-orange-600" title="Recalculate from prices">
                                <i class="fa-solid fa-calculator mr-1"></i> Recalc
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="flex flex-wrap justify-end gap-2">
            <button type="button" onclick="previewQuotation()" class="bg-white hover:bg-slate-100 text-slate-700 text-sm font-semibold px-5 py-3 rounded-xl border border-slate-300 transition">
                <i class="fa-solid fa-eye mr-1"></i> Preview
            </button>
            <?php if ($canWrite): ?>
                <button type="button" onclick="saveQuotation(false)" class="save-btn bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold px-6 py-3 rounded-xl transition">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Save Quotation
                </button>
                <button type="button" onclick="saveQuotation(true)" class="save-btn bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold px-6 py-3 rounded-xl shadow-md shadow-orange-500/20 transition">
                    <i class="fa-solid fa-print mr-1"></i> Save &amp; Print
                </button>
            <?php endif; ?>
        </div>
    </form>
</main>

<template id="hotelRowTpl">
    <div class="hotel-row grid grid-cols-2 md:grid-cols-12 gap-2 items-center rounded-xl border border-slate-200 md:border-0 p-2 md:p-0">
        <div class="md:col-span-2">
            <input type="text" name="hotel_city[]" list="cityList" placeholder="City" class="<?= $inputCls ?>">
        </div>
        <div class="md:col-span-4">
            <input type="text" name="hotel_name[]" placeholder="Hotel name" class="<?= $inputCls ?> uppercase">
        </div>
        <div class="md:col-span-2">
            <input type="text" name="hotel_distance[]" placeholder="e.g. 850 m" class="<?= $inputCls ?>">
        </div>
        <div class="md:col-span-2">
            <input type="text" name="hotel_room[]" list="roomList" placeholder="DBL" class="<?= $inputCls ?> uppercase">
        </div>
        <div class="md:col-span-1">
            <input type="number" min="0" name="hotel_nights[]" placeholder="0" class="<?= $inputCls ?>">
        </div>
        <div class="md:col-span-1 text-right">
            <button type="button" onclick="removeHotelRow(this)" class="h-8 w-8 rounded-lg text-rose-500 hover:bg-rose-50" title="Remove hotel">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </div>
    </div>
</template>

<datalist id="cityList"><option value="Makkah"><option value="Madinah"><option value="Jeddah"><option value="Taif"></datalist>
<datalist id="roomList"><?php foreach ($roomTypes as $rt): ?><option value="<?= $rt ?>"><?php endforeach; ?></datalist>

<script>
(function () {
    const DRAFT_KEY = 'aero_package_quotation_draft';
    const EXISTING = <?= json_encode($existing, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    let quoteId = <?= $quoteId ?>;
    const form = document.getElementById('packageForm');
    const rows = document.getElementById('hotelRows');
    let totalOverridden = false;

    function newQuoteNo() {
        const d = new Date(), p = n => String(n).padStart(2, '0');
        return 'QT-' + String(d.getFullYear()).slice(2) + p(d.getMonth() + 1) + p(d.getDate()) + '-' + p(d.getHours()) + p(d.getMinutes());
    }

    window.addHotelRow = function (data) {
        const node = document.getElementById('hotelRowTpl').content.firstElementChild.cloneNode(true);
        if (data) {
            node.querySelector('[name="hotel_city[]"]').value = data.city || '';
            node.querySelector('[name="hotel_name[]"]').value = data.name || '';
            node.querySelector('[name="hotel_distance[]"]').value = data.distance || '';
            node.querySelector('[name="hotel_room[]"]').value = data.room || '';
            node.querySelector('[name="hotel_nights[]"]').value = data.nights || '';
        }
        rows.appendChild(node);
    };

    window.removeHotelRow = function (btn) {
        btn.closest('.hotel-row').remove();
        if (!rows.children.length) addHotelRow();
        saveDraft();
    };

    const num = name => parseFloat(form.elements[name].value) || 0;
    const fmt = n => n.toLocaleString('en-US', { maximumFractionDigits: 2 });

    window.recalcTotal = function (force) {
        if (force) totalOverridden = false;
        const pairs = [['adult', 'adults'], ['child', 'children'], ['infant', 'infants']];
        let total = 0;
        pairs.forEach(([k, pax]) => {
            const sub = num('price_' + k) * num(pax);
            total += sub;
            document.getElementById('sub_' + k).textContent = '× ' + num(pax) + ' = ' + fmt(sub);
        });
        if (!totalOverridden) document.getElementById('totalPrice').value = total || '';
    };

    function recalcDays() {
        const dep = document.getElementById('depDate').value, ret = document.getElementById('retDate').value;
        if (dep && ret) {
            const days = Math.round((new Date(ret) - new Date(dep)) / 86400000) + 1;
            if (days > 0) document.getElementById('totalDays').value = days;
        }
    }

    function collect() {
        const data = {};
        Array.from(form.elements).forEach(el => {
            if (!el.name || el.name.endsWith('[]')) return;
            data[el.name] = el.value;
        });
        data.includes = Array.from(form.querySelectorAll('[name="includes[]"]:checked')).map(el => el.value);
        data.hotels = Array.from(rows.querySelectorAll('.hotel-row')).map(r => ({
            city: r.querySelector('[name="hotel_city[]"]').value,
            name: r.querySelector('[name="hotel_name[]"]').value,
            distance: r.querySelector('[name="hotel_distance[]"]').value,
            room: r.querySelector('[name="hotel_room[]"]').value,
            nights: r.querySelector('[name="hotel_nights[]"]').value
        }));
        data._totalOverridden = totalOverridden;
        return data;
    }

    // Drafts only apply to a new, not-yet-saved quotation.
    function saveDraft() {
        if (quoteId) return;
        try { localStorage.setItem(DRAFT_KEY, JSON.stringify(collect())); } catch (e) {}
    }

    function clearDraft() {
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
    }

    function fillForm(data) {
        Object.keys(data).forEach(name => {
            const el = form.elements[name];
            if (el && el.tagName && (typeof data[name] === 'string' || typeof data[name] === 'number')) el.value = data[name];
        });
        form.querySelectorAll('[name="includes[]"]').forEach(el => { el.checked = (data.includes || []).includes(el.value); });
        rows.innerHTML = '';
        (data.hotels && data.hotels.length ? data.hotels : [{}]).forEach(h => addHotelRow(h));
    }

    function loadDraft() {
        let data = null;
        try { data = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) {}
        if (!data) return false;
        fillForm(data);
        totalOverridden = !!data._totalOverridden;
        return true;
    }

    window.previewQuotation = function () {
        form.submit();
    };

    let saving = false;
    window.saveQuotation = async function (printAfter) {
        if (saving) return;
        saving = true;
        document.querySelectorAll('.save-btn').forEach(b => b.disabled = true);
        // Open the print tab synchronously so the browser does not block it as a popup.
        const printWin = printAfter ? window.open('', '_blank') : null;
        try {
            const payload = collect();
            payload.id = quoteId;
            const res = await apiRequest('index.php?api=save_package_quotation', payload);
            if (!res.success) {
                showToast('error', res.message);
                if (printWin) printWin.close();
                return;
            }
            showToast('success', res.message);
            if (!quoteId) {
                clearDraft();
                quoteId = res.id;
                history.replaceState(null, '', 'index.php?page=packages&id=' + quoteId);
                const clearBtn = document.querySelector('[onclick="resetPackageForm()"]');
                if (clearBtn) clearBtn.remove();
            }
            document.getElementById('formTitle').textContent = 'Edit Quotation ' + (form.elements.quote_no.value || '');
            if (printWin) printWin.location.href = 'index.php?page=print_package&id=' + quoteId;
        } catch (e) {
            if (printWin) printWin.close();
        } finally {
            saving = false;
            document.querySelectorAll('.save-btn').forEach(b => b.disabled = false);
        }
    };

    window.resetPackageForm = function () {
        // Keep the signatory between quotations, clear everything else.
        const signName = form.elements.sign_name.value, signTitle = form.elements.sign_title.value;
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
        form.reset();
        rows.innerHTML = '';
        addHotelRow({ city: 'Makkah', room: 'DBL' });
        addHotelRow({ city: 'Madinah', room: 'DBL' });
        form.elements.sign_name.value = signName;
        form.elements.sign_title.value = signTitle;
        document.getElementById('quoteNo').value = newQuoteNo();
        totalOverridden = false;
        recalcTotal();
        saveDraft();
    };

    if (EXISTING) {
        fillForm(EXISTING);
        const calc = (parseFloat(EXISTING.price_adult) || 0) * EXISTING.adults + (parseFloat(EXISTING.price_child) || 0) * EXISTING.children + (parseFloat(EXISTING.price_infant) || 0) * EXISTING.infants;
        totalOverridden = Math.abs(calc - (parseFloat(EXISTING.total_price) || 0)) > 0.001;
    } else if (!loadDraft()) {
        addHotelRow({ city: 'Makkah', room: 'DBL' });
        addHotelRow({ city: 'Madinah', room: 'DBL' });
        document.getElementById('quoteNo').value = newQuoteNo();
    }
    recalcTotal();

    form.addEventListener('input', e => {
        if (e.target.id === 'totalPrice') totalOverridden = e.target.value !== '';
        if (e.target.classList.contains('price-input') || e.target.classList.contains('pax-input')) recalcTotal();
        if (e.target.id === 'depDate' || e.target.id === 'retDate') recalcDays();
        saveDraft();
    });
    form.addEventListener('change', saveDraft);
})();
</script>
