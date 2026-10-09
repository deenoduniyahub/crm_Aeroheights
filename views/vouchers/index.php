<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/VoucherController.php';
require_once __DIR__ . '/../../controllers/HotelBookingController.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

$agents        = AdminController::getAgents();
$vendors       = AdminController::getVendors();
$vouchers      = VoucherController::getAll(50);
$hotelBookings = HotelBookingController::getAll(50);
?>

<main class="md:col-span-9 space-y-6">

    <!-- Header Action Ribbon -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-hotel text-amber-500 mr-2"></i> Hotel Accommodation Voucher Generator
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Build multi-stay accommodation plans with per-night rate calculations, flight itineraries, and passenger rosters.</p>
        </div>
        <div class="flex gap-2 w-full md:w-auto">
            <button onclick="resetVoucherForm(); document.getElementById('voucherFormContainer').classList.toggle('hidden')" class="bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold px-4 py-2.5 rounded-xl transition shadow-sm flex items-center">
                <i class="fa-solid fa-plus-circle mr-1.5"></i> Build Hotel Voucher
            </button>
            <button onclick="resetHotelBookingForm(); document.getElementById('hotelBookingFormContainer').classList.toggle('hidden')" class="bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold px-4 py-2.5 rounded-xl transition shadow-sm flex items-center">
                <i class="fa-solid fa-hotel mr-1.5"></i> Only Hotel Booking
            </button>
        </div>
    </div>

    <!-- Voucher Generator Form -->
    <div id="voucherFormContainer" class="hidden bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-6">
        <div class="border-b pb-3 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-xs uppercase tracking-wider">
                <i class="fa-solid fa-file-invoice text-amber-500 mr-1.5"></i> Hotel Voucher Specification
            </h3>
            <span class="text-[11px] font-mono text-slate-400">Automatic Nightly Costing Enabled</span>
        </div>

        <form id="formHotelVoucher" onsubmit="submitVoucherForm(event)" class="space-y-6 text-xs">
            
            <!-- Master Details -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Booking Agent</label>
                    <select id="vc_agent_id" required class="w-full border rounded-xl p-2.5 bg-slate-50 focus:bg-white font-medium outline-none focus:ring-2 focus:ring-amber-500">
                        <option value="">Select Agent...</option>
                        <?php foreach ($agents as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Family Head / Lead Mutamer</label>
                    <input type="text" id="vc_family_head" required placeholder="Lead Passenger Full Name" class="w-full border rounded-xl p-2.5 font-medium outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Total PAX / Beds</label>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" id="vc_total_pax" value="1" min="1" readonly title="Auto-calculated from Mutamers Manifest" placeholder="PAX" class="w-full border rounded-xl p-2.5 font-medium outline-none bg-slate-100 text-slate-500 cursor-not-allowed">
                        <input type="number" id="vc_total_beds" value="1" min="1" placeholder="Beds" class="w-full border rounded-xl p-2.5 font-medium outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Hotel Vendor / Supplier</label>
                    <select id="vc_vendor_id" class="w-full border rounded-xl p-2.5 bg-slate-50 focus:bg-white font-medium outline-none focus:ring-2 focus:ring-amber-500">
                        <option value="">Select Vendor (Optional)...</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Company Name (on Voucher)</label>
                    <input type="text" id="vc_company_name" list="vc_company_name_options" value="Aeroheights Travels & Tours" placeholder="Aeroheights Travels & Tours" class="w-full border rounded-xl p-2.5 font-medium outline-none focus:ring-2 focus:ring-amber-500">
                    <datalist id="vc_company_name_options">
                        <option value="Aeroheights Travels & Tours">
                    </datalist>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Package Name</label>
                    <input type="text" id="vc_package_name" placeholder="e.g. 9 Days Standard" class="w-full border rounded-xl p-2.5 font-medium outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Voucher Date</label>
                    <input type="date" id="vc_voucher_date" class="w-full border rounded-xl p-2.5 font-mono outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Transport Type</label>
                    <input type="text" id="vc_transport_type" placeholder="e.g. CAR, BUS, STARIA" class="w-full border rounded-xl p-2.5 font-medium outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block font-semibold text-slate-700 mb-1">Transport Details / Description</label>
                    <input type="text" id="vc_transporter" placeholder="e.g. JED-MAK by CAR, MAK-MED-JED by BUS" class="w-full border rounded-xl p-2.5 font-medium outline-none focus:ring-2 focus:ring-amber-500">
                </div>
            </div>

            <!-- STRUCTURED OUTBOUND FLIGHT -->
            <div class="bg-indigo-50/50 p-4 rounded-xl border border-indigo-200 space-y-3">
                <span class="font-bold text-indigo-950 uppercase tracking-wider text-xs block">
                    <i class="fa-solid fa-plane-departure mr-1.5 text-indigo-600"></i> Outbound Flight
                </span>
                <div class="grid grid-cols-1 sm:grid-cols-3 md:grid-cols-7 gap-2">
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Flight No</label>
                        <input type="text" id="vc_out_flight_no" placeholder="Flight #" class="w-full border rounded-lg p-2 font-mono uppercase bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">From (City)</label>
                        <input type="text" id="vc_out_from" placeholder="Airport / city code" class="w-full border rounded-lg p-2 bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">To (City)</label>
                        <input type="text" id="vc_out_to" placeholder="Airport / city code" class="w-full border rounded-lg p-2 bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Departure Date</label>
                        <input type="date" id="vc_out_dep_date" class="w-full border rounded-lg p-2 font-mono bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Dep Time</label>
                        <input type="time" id="vc_out_dep_time" class="w-full border rounded-lg p-2 font-mono bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Arrival Date</label>
                        <input type="date" id="vc_out_arr_date" class="w-full border rounded-lg p-2 font-mono bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Arr Time</label>
                        <input type="time" id="vc_out_arr_time" class="w-full border rounded-lg p-2 font-mono bg-white">
                    </div>
                </div>
            </div>

            <!-- STRUCTURED RETURN FLIGHT -->
            <div class="bg-rose-50/50 p-4 rounded-xl border border-rose-200 space-y-3">
                <span class="font-bold text-rose-950 uppercase tracking-wider text-xs block">
                    <i class="fa-solid fa-plane-arrival mr-1.5 text-rose-600"></i> Return Flight
                </span>
                <div class="grid grid-cols-1 sm:grid-cols-3 md:grid-cols-7 gap-2">
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Flight No</label>
                        <input type="text" id="vc_ret_flight_no" placeholder="Flight #" class="w-full border rounded-lg p-2 font-mono uppercase bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">From (City)</label>
                        <input type="text" id="vc_ret_from" placeholder="Airport / city code" class="w-full border rounded-lg p-2 bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">To (City)</label>
                        <input type="text" id="vc_ret_to" placeholder="Airport / city code" class="w-full border rounded-lg p-2 bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Departure Date</label>
                        <input type="date" id="vc_ret_dep_date" class="w-full border rounded-lg p-2 font-mono bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Dep Time</label>
                        <input type="time" id="vc_ret_dep_time" class="w-full border rounded-lg p-2 font-mono bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Arrival Date</label>
                        <input type="date" id="vc_ret_arr_date" class="w-full border rounded-lg p-2 font-mono bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Arr Time</label>
                        <input type="time" id="vc_ret_arr_time" class="w-full border rounded-lg p-2 font-mono bg-white">
                    </div>
                </div>
            </div>

            <!-- Dynamic Multi-Stay Intervals with Per-Night Rates -->
            <div class="bg-amber-50/40 p-4 rounded-xl border border-amber-200/70 space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-bold text-amber-950 uppercase tracking-wider text-xs block">
                            <i class="fa-solid fa-bed mr-1.5 text-amber-600"></i> Hotel Accommodation Stays & Per-Night Rates
                        </span>
                        <span class="text-[11px] text-slate-500">Nights × Rate = Total Stay Cost · Optional — leave Hotel Name blank for no hotel (voucher shows "Self")</span>
                    </div>
                    <button type="button" onclick="addVoucherStayRow()" class="text-amber-700 hover:text-amber-900 font-bold flex items-center text-xs">
                        <i class="fa-solid fa-plus-circle mr-1"></i> Add Another Stay
                    </button>
                </div>
                
                <div id="voucherStaysContainer" class="space-y-3">
                    <!-- Default Stay Row 1 -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-12 gap-2 stay-item bg-white p-3 rounded-xl border border-slate-200 shadow-2xs items-end">
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">City</label>
                            <select class="stay-city w-full border border-slate-300 rounded-lg p-2 font-medium bg-slate-50 focus:bg-white outline-none">
                                <option value="Makkah">Makkah</option><option value="Madinah">Madinah</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Hotel Name</label>
                            <input type="text" placeholder="Hotel Name" class="stay-hotel w-full border border-slate-300 rounded-lg p-2 font-semibold focus:ring-2 focus:ring-amber-500 outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Room Type</label>
                            <select class="stay-room-type w-full border border-slate-300 rounded-lg p-2 bg-slate-50 focus:bg-white outline-none" onchange="toggleCustomRoomType(this)">
                                <option value="Double Bed">Double Bed</option><option value="Triple Bed">Triple Bed</option><option value="Sharing">Sharing</option>
                                <option value="Twin Bed">Twin Bed</option><option value="Quadruple Bed">Quadruple Bed</option><option value="Four Bed Room">Four Bed Room</option>
                                <option value="Five Bed Room">Five Bed Room</option><option value="Six Bed Room">Six Bed Room</option><option value="Family Room">Family Room</option><option value="Suite">Suite</option>
                                <option value="__custom__">Custom Room Type...</option>
                            </select>
                            <input type="text" placeholder="Enter custom room type" class="stay-room-custom hidden mt-1 w-full border border-indigo-300 rounded-lg p-2 bg-indigo-50 outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div class="md:col-span-1">
                            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Meal</label>
                            <select class="stay-meal-plan w-full border border-slate-300 rounded-lg p-2 bg-slate-50 outline-none"><option value="RO">RO</option><option value="BB">BB</option><option value="HB">HB</option><option value="FB">FB</option></select>
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
                        <div class="md:col-span-1"><label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Nights</label><input type="number" oninput="recalculateStayCost(this)" class="stay-nights w-full border border-slate-300 rounded-lg p-2 font-bold text-amber-700 outline-none text-center" value="1" min="1"></div>
                        <div class="md:col-span-1"><label class="block text-[10px] font-semibold text-rose-600 mb-0.5">Buy/Night</label><input type="number" step="0.01" oninput="recalculateStayCost(this)" placeholder="0.00" class="stay-buy-rate w-full border border-slate-300 rounded-lg p-2 font-mono text-rose-600 outline-none text-center"></div>
                        <div class="md:col-span-1"><label class="block text-[10px] font-semibold text-emerald-700 mb-0.5">Sell/Night</label><input type="number" step="0.01" oninput="recalculateStayCost(this)" placeholder="0.00" class="stay-sell-rate w-full border border-slate-300 rounded-lg p-2 font-mono font-bold text-emerald-700 outline-none text-center"></div>
                        <div class="md:col-span-1 flex items-center justify-between pb-1"><div class="text-[11px] font-bold text-slate-700 font-mono stay-total-display">0 PKR</div><button type="button" onclick="removeStayRow(this)" class="text-slate-400 hover:text-rose-600 transition p-1" title="Remove Stay"><i class="fa-solid fa-trash-can"></i></button></div>
                    </div>
                </div>

                <!-- Grand Stay Total Bar -->
                <div class="bg-white p-3 rounded-xl border border-amber-200 flex flex-wrap items-center justify-between text-xs font-mono font-bold">
                    <div class="text-slate-600 font-sans">Total Accommodation Nights: <span id="grandTotalNights" class="text-amber-700 font-mono font-bold">1</span> Nights</div>
                    <div class="flex items-center space-x-4">
                        <div class="text-rose-600">Total Buy Cost: <span id="grandTotalBuy">0.00</span> PKR</div>
                        <div class="text-emerald-700 font-black text-sm">Total Sell Price: <span id="grandTotalSell">0.00</span> PKR</div>
                    </div>
                </div>
            </div>

            <!-- Dynamic Mutamers Manifest -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-slate-800 uppercase tracking-wider text-xs">
                        <i class="fa-solid fa-users mr-1.5 text-indigo-600"></i> Mutamers Passenger Manifest
                    </span>
                    <button type="button" onclick="addVoucherMutamerRow()" class="text-indigo-600 hover:text-indigo-800 font-bold flex items-center">
                        <i class="fa-solid fa-plus-circle mr-1"></i> Add Mutamer
                    </button>
                </div>
                <div id="voucherMutamersContainer" class="space-y-2">
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-2.5 mutamer-item bg-white p-2.5 rounded-xl border border-slate-200 shadow-2xs">
                        <input type="text" placeholder="Mutamer Full Name" oninput="updateVoucherPaxSummary()" class="mut-name border rounded-lg p-2 font-semibold md:col-span-2">
                        <input type="text" placeholder="Passport #" class="mut-pass border rounded-lg p-2 font-mono uppercase font-bold text-slate-700">
                        <select class="mut-gender w-full border rounded-lg p-2 font-medium">
                            <option value="M">Male (M)</option>
                            <option value="F">Female (F)</option>
                        </select>
                        <select class="mut-pax w-full border rounded-lg p-2 font-medium" onchange="updateVoucherPaxSummary()">
                            <option value="Adult">Adult</option>
                            <option value="Child">Child</option>
                            <option value="Infant">Infant</option>
                        </select>
                        <div class="flex items-center gap-1.5">
                            <select class="mut-bed w-full border rounded-lg p-2 font-medium">
                                <option value="Yes">Bed: Yes</option>
                                <option value="No">Bed: No</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assign Master Booking(s) (Optional) -->
            <div class="bg-indigo-50/40 p-4 rounded-xl border border-indigo-200/70 space-y-3">
                <div>
                    <span class="font-bold text-indigo-950 uppercase tracking-wider text-xs block">
                        <i class="fa-solid fa-link mr-1.5 text-indigo-600"></i> Assign Master Booking(s) <span class="text-slate-400 font-normal normal-case">(Optional)</span>
                    </span>
                    <span class="text-[11px] text-slate-500">Search &amp; link this hotel voucher to one or more Master Bookings — their flight dates power the automatic Transport itinerary below. You can assign multiple passengers/bookings.</span>
                </div>
                <div class="relative">
                    <input type="text" id="vc_master_search" placeholder="Search by passenger name, booking code, passport or flight #..." autocomplete="off"
                           class="w-full border border-slate-300 rounded-lg p-2.5 bg-white outline-none focus:ring-2 focus:ring-indigo-500"
                           oninput="vcSearchMasterBookings(this.value)" onfocus="vcSearchMasterBookings(this.value)">
                    <div id="vc_master_search_results" class="hidden absolute z-20 mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg max-h-64 overflow-y-auto text-xs"></div>
                </div>
                <div id="vc_master_selected" class="flex flex-wrap gap-2"></div>
            </div>

            <!-- Transport (Optional, auto-generated itinerary) -->
            <div class="bg-sky-50/40 p-4 rounded-xl border border-sky-200/70 space-y-3">
                <label class="flex items-center gap-2 cursor-pointer w-fit">
                    <input type="checkbox" id="vc_transport_enabled" onchange="vcToggleTransportSection()" class="w-4 h-4 rounded text-sky-600 focus:ring-sky-500">
                    <span class="font-bold text-sky-950 uppercase tracking-wider text-xs">
                        <i class="fa-solid fa-van-shuttle mr-1.5 text-sky-600"></i> Transport <span class="text-slate-400 font-normal normal-case">(Optional — auto-builds the full Airport ⇄ Hotel itinerary)</span>
                    </span>
                </label>
                <div id="vc_transport_fields" class="hidden space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Transport Type</label>
                            <input type="text" id="vc_auto_transport_type" placeholder="e.g. CAR / GMC / Hiace" value="CAR" class="w-full border border-slate-300 rounded-lg p-2 bg-white outline-none focus:ring-2 focus:ring-sky-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-rose-600 mb-0.5">Buy Rate (per transfer)</label>
                            <input type="number" step="0.01" id="vc_auto_transport_buy_rate" placeholder="0.00" class="w-full border border-slate-300 rounded-lg p-2 font-mono text-rose-600 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-emerald-700 mb-0.5">Sell Rate (per transfer)</label>
                            <input type="number" step="0.01" id="vc_auto_transport_sell_rate" placeholder="0.00" class="w-full border border-slate-300 rounded-lg p-2 font-mono font-bold text-emerald-700 outline-none">
                        </div>
                    </div>
                    <div class="bg-white border border-sky-200 rounded-lg p-2.5 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[11px] font-bold text-sky-900"><i class="fa-solid fa-route text-sky-600 mr-1"></i> Route-wise Transport <span class="font-normal text-slate-500">(untick a route to skip it · leave blank to use the default above)</span></span>
                            <button type="button" onclick="vcRenderTransportRoutes()" class="text-[10px] font-semibold text-sky-700 bg-sky-50 hover:bg-sky-100 border border-sky-200 rounded-md px-2 py-1"><i class="fa-solid fa-rotate mr-1"></i> Refresh Routes</button>
                        </div>
                        <div id="vc_transport_routes" class="space-y-1.5"></div>
                        <div class="border-t border-sky-100 pt-2 space-y-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[11px] font-bold text-sky-900"><i class="fa-solid fa-map-pin text-sky-600 mr-1"></i> Custom Routes <span class="font-normal text-slate-500">(e.g. Ziyarat, MAK-TAI · blank vehicle/rates = default above)</span></span>
                                <button type="button" onclick="vcAddCustomRoute()" class="text-[10px] font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-md px-2 py-1"><i class="fa-solid fa-plus mr-1"></i> Add Route</button>
                            </div>
                            <div id="vc_custom_routes" class="space-y-1.5"></div>
                        </div>
                    </div>
                    <div class="text-[11px] text-slate-500 bg-white border border-sky-200 rounded-lg p-2.5">
                        <i class="fa-solid fa-wand-magic-sparkles text-sky-600 mr-1"></i>
                        On save, transfers are auto-created for every leg of the itinerary — Airport → first hotel, hotel → hotel on each city change, and last hotel → Airport — dated from each stay's check-in/check-out, using the assigned Master Booking's flight numbers when available. They appear instantly in the Transport module and the Operations Manifest.
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="resetVoucherForm(); document.getElementById('voucherFormContainer').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs transition">
                    Cancel
                </button>
                <button type="submit" id="voucherSubmitButton" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold px-6 py-2 rounded-xl text-xs transition shadow-sm">
                    <i class="fa-solid fa-print mr-1"></i> Generate & Print Voucher
                </button>
            </div>
        </form>
    </div>

    <!-- Only Hotel Booking Form -->
    <div id="hotelBookingFormContainer" class="hidden bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-6">
        <div class="border-b pb-3 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-xs uppercase tracking-wider">
                <i class="fa-solid fa-bed text-teal-600 mr-1.5"></i> Only Hotel Booking Specification
            </h3>
            <span class="text-[11px] font-mono text-slate-400">Standalone Hotel Booking — Invoice + Voucher</span>
        </div>

        <form id="formHotelBooking" onsubmit="submitHotelBookingForm(event)" class="space-y-6 text-xs">

            <!-- Agent & General Details -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Select Agent</label>
                    <select id="hb_agent_id" required class="w-full border rounded-xl p-2.5 bg-slate-50 focus:bg-white font-medium outline-none focus:ring-2 focus:ring-teal-500">
                        <option value="">Select Agent...</option>
                        <?php foreach ($agents as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Booking Reference</label>
                    <input type="text" id="hb_booking_ref" readonly placeholder="Auto-generated on save" class="w-full border rounded-xl p-2.5 font-mono bg-slate-100 text-slate-500 cursor-not-allowed">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Booking Date</label>
                    <input type="date" id="hb_booking_date" class="w-full border rounded-xl p-2.5 font-mono outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Lead Guest / Passenger Name</label>
                    <input type="text" id="hb_lead_guest_name" required placeholder="Lead Passenger Full Name" class="w-full border rounded-xl p-2.5 font-semibold outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Company Name (on Voucher)</label>
                    <input type="text" id="hb_company_name" list="hb_company_name_options" value="Aeroheights Travels & Tours" placeholder="Aeroheights Travels & Tours" class="w-full border rounded-xl p-2.5 font-medium outline-none focus:ring-2 focus:ring-teal-500">
                    <datalist id="hb_company_name_options">
                        <option value="Aeroheights Travels & Tours">
                    </datalist>
                </div>
            </div>

            <!-- Guest Breakdown & Remarks -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                <span class="font-bold text-slate-800 uppercase tracking-wider text-xs block">
                    <i class="fa-solid fa-users mr-1.5 text-indigo-600"></i> Guest Breakdown &amp; Remarks
                </span>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3">
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Adults</label>
                        <input type="number" id="hb_pax_adults" value="1" min="0" class="w-full border border-slate-300 rounded-lg p-2 font-mono text-center bg-white outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Children</label>
                        <input type="number" id="hb_pax_children" value="0" min="0" class="w-full border border-slate-300 rounded-lg p-2 font-mono text-center bg-white outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Infants</label>
                        <input type="number" id="hb_pax_infants" value="0" min="0" class="w-full border border-slate-300 rounded-lg p-2 font-mono text-center bg-white outline-none">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Special Requests / Remarks</label>
                        <input type="text" id="hb_remarks" placeholder="e.g. non-smoking, high floor, connected rooms" class="w-full border border-slate-300 rounded-lg p-2 bg-white outline-none">
                    </div>
                </div>
            </div>

            <!-- Dynamic Multi-Hotel Stays with Per-Night Rates -->
            <div class="bg-teal-50/40 p-4 rounded-xl border border-teal-200/70 space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-bold text-teal-950 uppercase tracking-wider text-xs block">
                            <i class="fa-solid fa-hotel mr-1.5 text-teal-600"></i> Hotels &amp; Stay Details
                        </span>
                        <span class="text-[11px] text-slate-500">Add as many hotels as needed — e.g. Makkah Hotel, then Madinah Hotel, then Makkah Hotel again.</span>
                    </div>
                    <button type="button" onclick="addHotelBookingStayRow()" class="text-teal-700 hover:text-teal-900 font-bold flex items-center text-xs whitespace-nowrap">
                        <i class="fa-solid fa-plus-circle mr-1"></i> Add Another Hotel
                    </button>
                </div>

                <div id="hotelBookingStaysContainer" class="space-y-3"></div>

                <!-- Grand Stay Total Bar -->
                <div class="bg-white p-3 rounded-xl border border-teal-200 flex flex-wrap items-center justify-between gap-2 text-xs font-mono font-bold">
                    <div class="text-slate-600 font-sans">Total Nights (all hotels): <span id="hb_grand_total_nights" class="text-teal-700 font-mono font-bold">0</span></div>
                    <div class="flex items-center flex-wrap gap-4">
                        <div class="text-rose-600">Total Buy: <span id="hb_grand_total_buy">0.00</span> PKR</div>
                        <div class="text-emerald-700 font-black text-sm">Total Sell: <span id="hb_grand_total_sell">0.00</span> PKR</div>
                        <div class="text-indigo-700">Profit: <span id="hb_grand_total_profit">0.00</span> PKR</div>
                    </div>
                </div>
            </div>

            <!-- Assign Master Booking(s) (Optional) -->
            <div class="bg-indigo-50/40 p-4 rounded-xl border border-indigo-200/70 space-y-3">
                <div>
                    <span class="font-bold text-indigo-950 uppercase tracking-wider text-xs block">
                        <i class="fa-solid fa-link mr-1.5 text-indigo-600"></i> Assign Master Booking(s) <span class="text-slate-400 font-normal normal-case">(Optional)</span>
                    </span>
                    <span class="text-[11px] text-slate-500">Search &amp; link this hotel booking to one or more Master Bookings — their flight dates power the automatic Transport itinerary below. You can assign multiple passengers/bookings.</span>
                </div>
                <div class="relative">
                    <input type="text" id="hb_master_search" placeholder="Search by passenger name, booking code, passport or flight #..." autocomplete="off"
                           class="w-full border border-slate-300 rounded-lg p-2.5 bg-white outline-none focus:ring-2 focus:ring-indigo-500"
                           oninput="hbSearchMasterBookings(this.value)" onfocus="hbSearchMasterBookings(this.value)">
                    <div id="hb_master_search_results" class="hidden absolute z-20 mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg max-h-64 overflow-y-auto text-xs"></div>
                </div>
                <div id="hb_master_selected" class="flex flex-wrap gap-2"></div>
            </div>

            <!-- Transport (Optional, auto-generated itinerary) -->
            <div class="bg-sky-50/40 p-4 rounded-xl border border-sky-200/70 space-y-3">
                <label class="flex items-center gap-2 cursor-pointer w-fit">
                    <input type="checkbox" id="hb_transport_enabled" onchange="hbToggleTransportSection()" class="w-4 h-4 rounded text-sky-600 focus:ring-sky-500">
                    <span class="font-bold text-sky-950 uppercase tracking-wider text-xs">
                        <i class="fa-solid fa-van-shuttle mr-1.5 text-sky-600"></i> Transport <span class="text-slate-400 font-normal normal-case">(Optional — auto-builds the full Airport ⇄ Hotel itinerary)</span>
                    </span>
                </label>
                <div id="hb_transport_fields" class="hidden space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Transport Type</label>
                            <input type="text" id="hb_transport_type" placeholder="e.g. CAR / GMC / Hiace" value="CAR" class="w-full border border-slate-300 rounded-lg p-2 bg-white outline-none focus:ring-2 focus:ring-sky-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-rose-600 mb-0.5">Buy Rate (per transfer)</label>
                            <input type="number" step="0.01" id="hb_transport_buy_rate" placeholder="0.00" class="w-full border border-slate-300 rounded-lg p-2 font-mono text-rose-600 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-emerald-700 mb-0.5">Sell Rate (per transfer)</label>
                            <input type="number" step="0.01" id="hb_transport_sell_rate" placeholder="0.00" class="w-full border border-slate-300 rounded-lg p-2 font-mono font-bold text-emerald-700 outline-none">
                        </div>
                    </div>
                    <div class="bg-white border border-sky-200 rounded-lg p-2.5 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[11px] font-bold text-sky-900"><i class="fa-solid fa-route text-sky-600 mr-1"></i> Route-wise Transport <span class="font-normal text-slate-500">(untick a route to skip it · leave blank to use the default above)</span></span>
                            <button type="button" onclick="hbRenderTransportRoutes()" class="text-[10px] font-semibold text-sky-700 bg-sky-50 hover:bg-sky-100 border border-sky-200 rounded-md px-2 py-1"><i class="fa-solid fa-rotate mr-1"></i> Refresh Routes</button>
                        </div>
                        <div id="hb_transport_routes" class="space-y-1.5"></div>
                        <div class="border-t border-sky-100 pt-2 space-y-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[11px] font-bold text-sky-900"><i class="fa-solid fa-map-pin text-sky-600 mr-1"></i> Custom Routes <span class="font-normal text-slate-500">(e.g. Ziyarat, MAK-TAI · blank vehicle/rates = default above)</span></span>
                                <button type="button" onclick="hbAddCustomRoute()" class="text-[10px] font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-md px-2 py-1"><i class="fa-solid fa-plus mr-1"></i> Add Route</button>
                            </div>
                            <div id="hb_custom_routes" class="space-y-1.5"></div>
                        </div>
                    </div>
                    <div class="text-[11px] text-slate-500 bg-white border border-sky-200 rounded-lg p-2.5">
                        <i class="fa-solid fa-wand-magic-sparkles text-sky-600 mr-1"></i>
                        On save, transfers are auto-created for every leg of the itinerary — Airport → first hotel, hotel → hotel on each city change, and last hotel → Airport — dated from each stay's check-in/check-out, using the assigned Master Booking's flight numbers when available. They appear instantly in the Transport module and the Operations Manifest.
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="resetHotelBookingForm(); document.getElementById('hotelBookingFormContainer').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs transition">
                    Cancel
                </button>
                <button type="submit" id="hotelBookingSubmitButton" class="bg-teal-600 hover:bg-teal-700 text-white font-semibold px-6 py-2 rounded-xl text-xs transition shadow-sm">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Save Booking
                </button>
            </div>
        </form>
    </div>

    <!-- Existing Vouchers Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-700 border-b border-slate-200 uppercase font-bold text-[11px]">
                    <tr>
                        <th class="p-3.5">Voucher #</th>
                        <th class="p-3.5">Date</th>
                        <th class="p-3.5">Agent</th>
                        <th class="p-3.5">Family Head / Lead</th>
                        <th class="p-3.5">Total Nights</th>
                        <th class="p-3.5">PAX</th>
                        <th class="p-3.5 text-right">Sell Total (PKR)</th>
                        <th class="p-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($vouchers)): ?>
                        <tr><td colspan="8" class="p-6 text-center text-slate-400">No hotel vouchers generated yet.</td></tr>
                    <?php else: foreach ($vouchers as $v): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="p-3.5 font-mono font-bold text-amber-600"><?= htmlspecialchars($v['voucher_no']) ?></td>
                            <td class="p-3.5 text-slate-500"><?= htmlspecialchars($v['voucher_date']) ?></td>
                            <td class="p-3.5 font-semibold text-slate-800"><?= htmlspecialchars($v['agent_name']) ?></td>
                            <td class="p-3.5 font-medium text-slate-900">
                                <?= htmlspecialchars($v['family_head']) ?>
                                <?php if (!empty($v['linked_master_bookings'])): ?><span class="bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded text-[10px] font-bold ml-1" title="<?= htmlspecialchars(implode(', ', $v['linked_master_bookings'])) ?>"><i class="fa-solid fa-link"></i> <?= count($v['linked_master_bookings']) ?></span><?php endif; ?>
                                <?php if (!empty($v['transport_leg_count'])): ?><span class="bg-sky-100 text-sky-700 px-1.5 py-0.5 rounded text-[10px] font-bold ml-1" title="Auto-generated transport legs"><i class="fa-solid fa-van-shuttle"></i> <?= (int)$v['transport_leg_count'] ?></span><?php endif; ?>
                            </td>
                            <td class="p-3.5 font-bold text-slate-700"><?= $v['total_nights'] ?> Nights</td>
                            <td class="p-3.5"><span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-[10px] font-semibold"><?= $v['total_pax'] ?> PAX / <?= $v['total_beds'] ?> Beds</span></td>
                            <td class="p-3.5 text-right font-mono font-bold text-emerald-700"><?= number_format((float)$v['sell_rate_pkr'], 2) ?></td>
                            <td class="p-3.5 text-right">
                                <div class="flex justify-end gap-2">
                                    <button type="button" onclick="editVoucher(<?= (int)$v['id'] ?>)" class="text-indigo-600 hover:text-indigo-800 p-1.5" title="Edit Voucher">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <a href="index.php?page=print_voucher&id=<?= $v['id'] ?>" target="_blank" class="text-slate-700 hover:text-slate-900 p-1.5" title="Print Voucher">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                    <button type="button" onclick="convertVoucherToHotelBooking(<?= (int)$v['id'] ?>)" class="text-teal-600 hover:text-teal-800 p-1.5" title="Convert to Only Hotel Booking">
                                        <i class="fa-solid fa-right-left"></i>
                                    </button>
                                    <button type="button" onclick="deleteVoucher(<?= (int)$v['id'] ?>)" class="text-slate-400 hover:text-rose-600 p-1.5" title="Delete Voucher">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Only Hotel Bookings Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="bg-slate-50 px-5 py-3.5 border-b border-slate-200">
            <h3 class="font-bold text-slate-800 text-xs uppercase tracking-wider flex items-center">
                <i class="fa-solid fa-hotel mr-2 text-teal-600"></i> Only Hotel Bookings
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-700 border-b border-slate-200 uppercase font-bold text-[11px]">
                    <tr>
                        <th class="p-3.5">Booking Ref</th>
                        <th class="p-3.5">Date</th>
                        <th class="p-3.5">Agent</th>
                        <th class="p-3.5">Lead Guest</th>
                        <th class="p-3.5">Hotel / City</th>
                        <th class="p-3.5">Nights</th>
                        <th class="p-3.5 text-right">Sell Total (PKR)</th>
                        <th class="p-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($hotelBookings)): ?>
                        <tr><td colspan="8" class="p-6 text-center text-slate-400">No standalone hotel bookings created yet.</td></tr>
                    <?php else: foreach ($hotelBookings as $hb):
                        $hbStays = $hb['stays'] ?? [];
                        $hbHotelSummary = implode(', ', array_map(
                            static fn($s) => $s['hotel_name'] . ' (' . $s['city'] . ')',
                            $hbStays
                        )) ?: '-';
                    ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="p-3.5 font-mono font-bold text-teal-600"><?= htmlspecialchars($hb['booking_ref']) ?></td>
                            <td class="p-3.5 text-slate-500"><?= htmlspecialchars($hb['booking_date']) ?></td>
                            <td class="p-3.5 font-semibold text-slate-800"><?= htmlspecialchars($hb['agent_name']) ?></td>
                            <td class="p-3.5 font-medium text-slate-900"><?= htmlspecialchars($hb['lead_guest_name']) ?></td>
                            <td class="p-3.5 text-slate-700">
                                <?= htmlspecialchars($hbHotelSummary) ?>
                                <?php if (count($hbStays) > 1): ?><span class="bg-teal-100 text-teal-700 px-1.5 py-0.5 rounded text-[10px] font-bold ml-1"><?= count($hbStays) ?> Hotels</span><?php endif; ?>
                                <?php if (!empty($hb['linked_master_bookings'])): ?><span class="bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded text-[10px] font-bold ml-1" title="<?= htmlspecialchars(implode(', ', $hb['linked_master_bookings'])) ?>"><i class="fa-solid fa-link"></i> <?= count($hb['linked_master_bookings']) ?></span><?php endif; ?>
                                <?php if (!empty($hb['transport_leg_count'])): ?><span class="bg-sky-100 text-sky-700 px-1.5 py-0.5 rounded text-[10px] font-bold ml-1" title="Auto-generated transport legs"><i class="fa-solid fa-van-shuttle"></i> <?= (int)$hb['transport_leg_count'] ?></span><?php endif; ?>
                            </td>
                            <td class="p-3.5 font-bold text-slate-700"><?= (int)$hb['total_nights'] ?> Nights</td>
                            <td class="p-3.5 text-right font-mono font-bold text-emerald-700"><?= number_format((float)$hb['sell_total_pkr'], 2) ?></td>
                            <td class="p-3.5 text-right">
                                <div class="flex justify-end gap-2">
                                    <button type="button" onclick="editHotelBooking(<?= (int)$hb['id'] ?>)" class="text-indigo-600 hover:text-indigo-800 p-1.5" title="Edit Booking">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <a href="index.php?page=print_hotel_booking_invoice&id=<?= $hb['id'] ?>" target="_blank" class="text-emerald-700 hover:text-emerald-900 p-1.5" title="Print Agent Hotel Invoice">
                                        <i class="fa-solid fa-file-invoice-dollar"></i>
                                    </a>
                                    <a href="index.php?page=print_hotel_booking_voucher&id=<?= $hb['id'] ?>" target="_blank" class="text-slate-700 hover:text-slate-900 p-1.5" title="Print Hotel Sale Voucher">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                    <button type="button" onclick="convertHotelBookingToVoucher(<?= (int)$hb['id'] ?>)" class="text-amber-600 hover:text-amber-800 p-1.5" title="Convert to Build Hotel Voucher">
                                        <i class="fa-solid fa-right-left"></i>
                                    </button>
                                    <button type="button" onclick="deleteHotelBooking(<?= (int)$hb['id'] ?>)" class="text-slate-400 hover:text-rose-600 p-1.5" title="Delete Booking">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script>
let editingVoucherId = 0;
let editingVoucherDate = '';

function setVoucherField(id, value) {
    const field = document.getElementById(id);
    if (field) field.value = value ?? '';
}

function todayIsoDate() {
    return new Date().toISOString().slice(0, 10);
}

function resetVoucherForm() {
    editingVoucherId = 0;
    editingVoucherDate = '';
    document.getElementById('formHotelVoucher')?.reset();
    setVoucherField('vc_company_name', 'Aeroheights Travels & Tours');
    setVoucherField('vc_voucher_date', todayIsoDate());
    const stays = document.getElementById('voucherStaysContainer');
    const mutamers = document.getElementById('voucherMutamersContainer');
    if (stays) stays.innerHTML = '';
    if (mutamers) mutamers.innerHTML = '';
    addVoucherStayRow();
    addVoucherMutamerRow();

    vcSelectedMasterBookings = [];
    vcRenderSelectedMasterBookings();
    const vcSearchResults = document.getElementById('vc_master_search_results');
    if (vcSearchResults) { vcSearchResults.classList.add('hidden'); vcSearchResults.innerHTML = ''; }

    setVoucherField('vc_auto_transport_type', 'CAR');
    setVoucherField('vc_auto_transport_buy_rate', '');
    setVoucherField('vc_auto_transport_sell_rate', '');
    const vcTransportCheckbox = document.getElementById('vc_transport_enabled');
    if (vcTransportCheckbox) vcTransportCheckbox.checked = false;
    vcRouteOverrides = {};
    vcSkippedRoutes = new Set();
    document.getElementById('vc_transport_routes').innerHTML = '';
    document.getElementById('vc_custom_routes').innerHTML = '';
    vcToggleTransportSection();

    document.getElementById('voucherSubmitButton').innerHTML = '<i class="fa-solid fa-print mr-1"></i> Generate & Print Voucher';
}

function addExistingStay(stay) {
    const container = document.getElementById('voucherStaysContainer');
    const row = document.createElement('div');
    row.className = 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-12 gap-2 stay-item bg-white p-3 rounded-xl border border-slate-200 shadow-2xs items-end';
    row.innerHTML = createStayRowHtml();
    container.appendChild(row);
    row.querySelector('.stay-city').value = stay.city || 'Makkah';
    row.querySelector('.stay-hotel').value = stay.hotel_name || '';
    const knownRoom = VOUCHER_ROOM_TYPES.includes(stay.room_type);
    row.querySelector('.stay-room-type').value = knownRoom ? stay.room_type : '__custom__';
    row.querySelector('.stay-room-custom').value = knownRoom ? '' : (stay.room_type || '');
    toggleCustomRoomType(row.querySelector('.stay-room-type'));
    row.querySelector('.stay-meal-plan').value = stay.meal_plan || 'RO';
    row.querySelector('.stay-confirmation').value = stay.confirmation_number || '';
    row.querySelector('.stay-checkin').value = stay.checkin_date || '';
    row.querySelector('.stay-checkout').value = stay.checkout_date || '';
    row.querySelector('.stay-nights').value = stay.nights || 1;
    row.querySelector('.stay-buy-rate').value = stay.buy_rate_per_night || 0;
    row.querySelector('.stay-sell-rate').value = stay.sell_rate_per_night || 0;
    recalculateStayCost(row.querySelector('.stay-sell-rate'));
}

function addExistingMutamer(mutamer) {
    const container = document.getElementById('voucherMutamersContainer');
    addVoucherMutamerRow();
    const row = container.lastElementChild;
    row.querySelector('.mut-name').value = mutamer.mutamer_name || '';
    row.querySelector('.mut-pass').value = mutamer.passport_number || '';
    row.querySelector('.mut-gender').value = mutamer.gender || 'M';
    row.querySelector('.mut-pax').value = mutamer.is_adult || 'Adult';
    row.querySelector('.mut-bed').value = mutamer.bed_assigned || 'Yes';
    updateVoucherPaxSummary();
}

async function editVoucher(id) {
    try {
        const response = await fetch(`index.php?api=get_voucher&id=${id}`);
        const result = await response.json();
        if (!result.success || !result.voucher) throw new Error(result.message || 'Voucher not found.');
        const voucher = result.voucher;
        editingVoucherId = id;
        editingVoucherDate = voucher.voucher_date || '';
        setVoucherField('vc_agent_id', voucher.agent_id);
        setVoucherField('vc_family_head', voucher.family_head);
        setVoucherField('vc_company_name', voucher.company_name || 'Aeroheights Travels & Tours');
        setVoucherField('vc_package_name', voucher.package_name);
        setVoucherField('vc_voucher_date', voucher.voucher_date || todayIsoDate());
        setVoucherField('vc_total_pax', voucher.total_pax);
        setVoucherField('vc_total_beds', voucher.total_beds);
        setVoucherField('vc_vendor_id', voucher.vendor_id);
        setVoucherField('vc_transporter', voucher.transporter_info);
        setVoucherField('vc_transport_type', voucher.transport_type);
        setVoucherField('vc_out_flight_no', voucher.flight_out_no);
        setVoucherField('vc_out_from', voucher.flight_out_from);
        setVoucherField('vc_out_to', voucher.flight_out_to);
        setVoucherField('vc_out_dep_date', voucher.flight_out_dep_date);
        setVoucherField('vc_out_dep_time', voucher.flight_out_dep_time);
        setVoucherField('vc_out_arr_date', voucher.flight_out_arr_date);
        setVoucherField('vc_out_arr_time', voucher.flight_out_arr_time);
        setVoucherField('vc_ret_flight_no', voucher.flight_ret_no);
        setVoucherField('vc_ret_from', voucher.flight_ret_from);
        setVoucherField('vc_ret_to', voucher.flight_ret_to);
        setVoucherField('vc_ret_dep_date', voucher.flight_ret_dep_date);
        setVoucherField('vc_ret_dep_time', voucher.flight_ret_dep_time);
        setVoucherField('vc_ret_arr_date', voucher.flight_ret_arr_date);
        setVoucherField('vc_ret_arr_time', voucher.flight_ret_arr_time);

        document.getElementById('voucherStaysContainer').innerHTML = '';
        (voucher.stays || []).forEach(addExistingStay);
        if (!voucher.stays?.length) addVoucherStayRow();
        document.getElementById('voucherMutamersContainer').innerHTML = '';
        (voucher.mutamers || []).forEach(addExistingMutamer);
        if (!voucher.mutamers?.length) addVoucherMutamerRow();
        updateGrandVoucherTotals();

        vcSelectedMasterBookings = (voucher.master_bookings || []).map(m => ({
            id: m.id, passenger_name: m.passenger_name, booking_code: m.booking_code,
            passport_number: m.passport_number, flight_number: m.flight_number,
            flight_itinerary_json: m.flight_itinerary_json, gender: m.gender, pax_type: m.pax_type,
            arrival_date: m.arrival_date, departure_date: m.departure_date
        }));
        vcRenderSelectedMasterBookings();
        vcSyncMutamerManifest();
        const vcSearchResults = document.getElementById('vc_master_search_results');
        if (vcSearchResults) { vcSearchResults.classList.add('hidden'); vcSearchResults.innerHTML = ''; }

        setVoucherField('vc_auto_transport_type', voucher.transport_auto_type || 'CAR');
        setVoucherField('vc_auto_transport_buy_rate', voucher.transport_auto_buy_rate || '');
        setVoucherField('vc_auto_transport_sell_rate', voucher.transport_auto_sell_rate || '');
        const vcTransportCheckbox = document.getElementById('vc_transport_enabled');
        if (vcTransportCheckbox) vcTransportCheckbox.checked = !!Number(voucher.transport_auto_enabled);
        vcRouteOverrides = {};
        document.getElementById('vc_transport_routes').innerHTML = '';
        document.getElementById('vc_custom_routes').innerHTML = '';
        (voucher.transport_legs || []).filter(leg => Number(leg.auto_generated) === 2).forEach(leg => vcAddCustomRoute({
            route: leg.route_details, date: leg.service_date, type: leg.vehicle_type,
            buy: String(Number(leg.buy_rate_pkr)), sell: String(Number(leg.sell_rate_pkr))
        }));
        const vcItineraryLegs = (voucher.transport_legs || []).filter(leg => Number(leg.auto_generated) !== 2);
        vcItineraryLegs.forEach(leg => {
            const o = {};
            if ((leg.vehicle_type || '') !== (voucher.transport_auto_type || 'CAR')) o.type = leg.vehicle_type || '';
            if (Number(leg.buy_rate_pkr) !== Number(voucher.transport_auto_buy_rate || 0)) o.buy = String(Number(leg.buy_rate_pkr));
            if (Number(leg.sell_rate_pkr) !== Number(voucher.transport_auto_sell_rate || 0)) o.sell = String(Number(leg.sell_rate_pkr));
            if (Object.keys(o).length) vcRouteOverrides[String(leg.route_details).toUpperCase()] = o;
        });
        vcSkippedRoutes = new Set();
        // Transport was on but a route has no saved transfer -> it was unticked last time.
        if (Number(voucher.transport_auto_enabled) && vcItineraryLegs.length) {
            const savedRoutes = new Set(vcItineraryLegs.map(leg => String(leg.route_details).toUpperCase()));
            vcComputeTransportRoutes().forEach(route => { if (!savedRoutes.has(route)) vcSkippedRoutes.add(route); });
        }
        vcToggleTransportSection();

        document.getElementById('voucherSubmitButton').innerHTML = '<i class="fa-solid fa-save mr-1"></i> Update & Print Voucher';
        document.getElementById('voucherFormContainer').classList.remove('hidden');
        document.getElementById('voucherFormContainer').scrollIntoView({behavior: 'smooth', block: 'start'});
    } catch (error) {
        alert(error.message || 'Unable to load voucher.');
    }
}

async function deleteVoucher(id) {
    if (!confirm('Delete this hotel voucher? It will be removed from the voucher list and ledgers.')) return;
    const response = await fetch('index.php?api=delete_voucher', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''},
        body: JSON.stringify({id})
    });
    const result = await response.json();
    if (result.success) location.reload();
    else alert(result.message || 'Unable to delete voucher.');
}

async function submitVoucherForm(e) {
    e.preventDefault();

    const stayRows = Array.from(document.querySelectorAll('.stay-item'));
    const allStays = stayRows.map(row => ({
        city: row.querySelector('.stay-city')?.value || 'Makkah',
        hotel_name: row.querySelector('.stay-hotel')?.value.trim() || '',
        room_type: row.querySelector('.stay-room-type')?.value || 'Double Bed',
        custom_room_type: row.querySelector('.stay-room-custom')?.value.trim() || '',
        meal_plan: row.querySelector('.stay-meal-plan')?.value || 'RO',
        confirmation_number: row.querySelector('.stay-confirmation')?.value.trim() || '',
        checkin_date: row.querySelector('.stay-checkin')?.value || '',
        checkout_date: row.querySelector('.stay-checkout')?.value || '',
        nights: Math.max(1, parseInt(row.querySelector('.stay-nights')?.value || '1', 10)),
        buy_rate_per_night: Math.max(0, parseFloat(row.querySelector('.stay-buy-rate')?.value || '0')),
        sell_rate_per_night: Math.max(0, parseFloat(row.querySelector('.stay-sell-rate')?.value || '0'))
    }));

    // Hotel is optional: rows left without a Hotel Name are ignored (the voucher then prints "Self").
    const stays = allStays.filter(s => s.hotel_name);
    for (let i = 0; i < stays.length; i++) {
        if (stays[i].room_type === '__custom__' && !stays[i].custom_room_type) { alert(`Custom room type is required for Stay #${i + 1}.`); return; }
        if (!stays[i].checkin_date || !stays[i].checkout_date) { alert(`Check-in and Check-out are required for Stay #${i + 1}.`); return; }
        if (new Date(`${stays[i].checkout_date}T00:00:00`) <= new Date(`${stays[i].checkin_date}T00:00:00`)) { alert(`Check-out must be after Check-in for Stay #${i + 1}.`); return; }
    }
    if (!stays.length && document.getElementById('vc_transport_enabled')?.checked) {
        const customRoutes = vcCollectCustomRoutes();
        if (!customRoutes.length) { alert('No hotel added: add at least one transport route with its date under Custom Routes (e.g. JED-MAK).'); return; }
        const undated = customRoutes.findIndex(r => !r.date);
        if (undated !== -1) { alert(`No hotel added: transport date is required for route #${undated + 1} (${customRoutes[undated].route}).`); return; }
    }

    const mutamers = Array.from(document.querySelectorAll('.mutamer-item')).map(row => ({
        name: row.querySelector('.mut-name')?.value.trim() || '',
        passport: row.querySelector('.mut-pass')?.value.trim() || '',
        gender: row.querySelector('.mut-gender')?.value || 'M',
        is_adult: row.querySelector('.mut-pax')?.value || 'Adult',
        bed_assigned: row.querySelector('.mut-bed')?.value || 'Yes'
    })).filter(m => m.name || m.passport);

    const totalBuy = stays.reduce((sum, s) => sum + (s.nights * s.buy_rate_per_night), 0);
    const totalSell = stays.reduce((sum, s) => sum + (s.nights * s.sell_rate_per_night), 0);

    const payload = {
        agent_id: document.getElementById('vc_agent_id').value,
        vendor_id: document.getElementById('vc_vendor_id')?.value || null,
        family_head: document.getElementById('vc_family_head').value.trim(),
        company_name: document.getElementById('vc_company_name').value.trim() || 'Aeroheights Travels & Tours',
        package_name: document.getElementById('vc_package_name').value.trim(),
        total_pax: document.getElementById('vc_total_pax').value,
        total_beds: document.getElementById('vc_total_beds').value,
        transporter_info: document.getElementById('vc_transporter').value.trim(),
        transport_type: document.getElementById('vc_transport_type').value.trim(),
        flight_out_no: document.getElementById('vc_out_flight_no').value.trim(),
        flight_out_from: document.getElementById('vc_out_from').value.trim(),
        flight_out_to: document.getElementById('vc_out_to').value.trim(),
        flight_out_dep_date: document.getElementById('vc_out_dep_date').value,
        flight_out_dep_time: document.getElementById('vc_out_dep_time').value,
        flight_out_arr_date: document.getElementById('vc_out_arr_date').value,
        flight_out_arr_time: document.getElementById('vc_out_arr_time').value,
        flight_ret_no: document.getElementById('vc_ret_flight_no').value.trim(),
        flight_ret_from: document.getElementById('vc_ret_from').value.trim(),
        flight_ret_to: document.getElementById('vc_ret_to').value.trim(),
        flight_ret_dep_date: document.getElementById('vc_ret_dep_date').value,
        flight_ret_dep_time: document.getElementById('vc_ret_dep_time').value,
        flight_ret_arr_date: document.getElementById('vc_ret_arr_date').value,
        flight_ret_arr_time: document.getElementById('vc_ret_arr_time').value,
        buy_rate_pkr: Number(totalBuy.toFixed(2)),
        sell_rate_pkr: Number(totalSell.toFixed(2)),
        voucher_date: document.getElementById('vc_voucher_date').value || editingVoucherDate || todayIsoDate(),
        stays,
        mutamers,
        master_booking_ids: vcSelectedMasterBookings.map(m => m.id),
        transport_enabled: !!document.getElementById('vc_transport_enabled')?.checked,
        transport_type_auto: document.getElementById('vc_auto_transport_type')?.value.trim() || 'CAR',
        transport_buy_rate: Math.max(0, parseFloat(document.getElementById('vc_auto_transport_buy_rate')?.value || '0')),
        transport_sell_rate: Math.max(0, parseFloat(document.getElementById('vc_auto_transport_sell_rate')?.value || '0')),
        transport_route_overrides: vcCollectRouteOverrides(),
        transport_skip_routes: vcCollectSkippedRoutes(),
        transport_custom_routes: vcCollectCustomRoutes()
    };

    const submitBtn = e.submitter;
    if (submitBtn) { submitBtn.disabled = true; submitBtn.classList.add('opacity-60', 'cursor-not-allowed'); }

    try {
        const res = await fetch(`index.php?api=${editingVoucherId ? 'update_voucher' : 'save_voucher'}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''},
            body: JSON.stringify(editingVoucherId ? {...payload, id: editingVoucherId} : payload)
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
            window.open(`index.php?page=print_voucher&id=${editingVoucherId || data.voucher_id}`, '_blank');
            window.location.reload();
        } else {
            alert(data.message || 'Error creating voucher');
            if (submitBtn) { submitBtn.disabled = false; submitBtn.classList.remove('opacity-60', 'cursor-not-allowed'); }
        }
    } catch (error) {
        alert('Network or server error: ' + error.message);
        if (submitBtn) { submitBtn.disabled = false; submitBtn.classList.remove('opacity-60', 'cursor-not-allowed'); }
    }
}

/**
 * Convert between "Only Hotel Booking" and "Build Hotel Voucher".
 * The source record is retired (soft-deleted, along with its auto-generated transport legs)
 * once the new one is created, so the agent ledger is never debited twice for the same stay.
 * After conversion, reload straight into the new record's edit form so any details that
 * don't map across (flight legs, package name, mutamer passports, etc.) can be filled in.
 */
async function convertHotelBookingToVoucher(id) {
    if (!confirm('Convert this hotel booking into a Build Hotel Voucher?\n\nThe assigned Master Booking(s), hotel stays, and Transport settings will carry over. The original booking will be replaced by the new voucher (no duplicate ledger entry). You can review and fill in any missing details afterward.')) return;
    try {
        const response = await fetch('index.php?api=convert_hotel_booking_to_voucher', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''},
            body: JSON.stringify({id})
        });
        const result = await response.json();
        if (result.success) {
            window.location.href = `index.php?page=vouchers&open_voucher=${result.voucher_id}`;
        } else {
            alert(result.message || 'Unable to convert hotel booking.');
        }
    } catch (error) {
        alert('Network or server error: ' + error.message);
    }
}

async function convertVoucherToHotelBooking(id) {
    if (!confirm('Convert this hotel voucher into an Only Hotel Booking?\n\nThe assigned Master Booking(s), hotel stays, and Transport settings will carry over. The original voucher will be replaced by the new booking (no duplicate ledger entry). Flight itinerary / package details don\'t apply to a hotel booking and will be dropped. You can review and fill in any missing details afterward.')) return;
    try {
        const response = await fetch('index.php?api=convert_voucher_to_hotel_booking', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''},
            body: JSON.stringify({id})
        });
        const result = await response.json();
        if (result.success) {
            window.location.href = `index.php?page=vouchers&open_booking=${result.booking_id}`;
        } else {
            alert(result.message || 'Unable to convert hotel voucher.');
        }
    } catch (error) {
        alert('Network or server error: ' + error.message);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const openVoucherId = parseInt(params.get('open_voucher') || '0', 10);
    const openBookingId = parseInt(params.get('open_booking') || '0', 10);

    if (openVoucherId > 0) {
        document.getElementById('voucherFormContainer')?.classList.remove('hidden');
        editVoucher(openVoucherId);
    }
    if (openBookingId > 0) {
        document.getElementById('hotelBookingFormContainer')?.classList.remove('hidden');
        editHotelBooking(openBookingId);
    }
});
</script>