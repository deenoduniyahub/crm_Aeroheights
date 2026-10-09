<?php
declare(strict_types=1);
require_once __DIR__ . '/../../controllers/BookingController.php';
require_once __DIR__ . '/../../controllers/AdminController.php';
require_once __DIR__ . '/../../controllers/AirTicketController.php';

// Display booking dates consistently as DD-MM-YYYY. Handles legacy two-digit years safely.
if (!function_exists('displayBookingDate')) {
    function displayBookingDate($value): string {
        if ($value === null || trim((string)$value) === '' || $value === '-') return '-';
        $value = trim((string)$value);
        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2})$/', $value, $m)) {
            $day=(int)$m[1]; $month=(int)$m[2]; $year=(int)$m[3];
            $year += ($year <= 69) ? 2000 : 1900;
            if (checkdate($month,$day,$year)) return sprintf('%02d-%02d-%04d',$day,$month,$year);
        }
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $value, $m)) {
            $year=(int)$m[1]; $month=(int)$m[2]; $day=(int)$m[3];
            if (checkdate($month,$day,$year)) return sprintf('%02d-%02d-%04d',$day,$month,$year);
        }
        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $value, $m)) {
            $day=(int)$m[1]; $month=(int)$m[2]; $year=(int)$m[3];
            if (checkdate($month,$day,$year)) return sprintf('%02d-%02d-%04d',$day,$month,$year);
        }
        $ts = strtotime($value);
        return $ts !== false ? date('d-m-Y',$ts) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$agents=AdminController::getAgents(); $vendors=AdminController::getVendors();
$filters=[
    'agent_id'=>!empty($_GET['agent_id'])?(int)$_GET['agent_id']:null,
    'vendor_id'=>!empty($_GET['vendor_id'])?(int)$_GET['vendor_id']:null,
    'search'=>trim((string)($_GET['search']??'')), 'date_from'=>$_GET['date_from']??'', 'date_to'=>$_GET['date_to']??'',
    'month'=>$_GET['month']??'', 'year'=>$_GET['year']??'', 'status'=>$_GET['status']??''
];
$bookings=BookingController::getAll($filters,100);
$airportLabel=static function(string $code): string { $city=AirTicketController::cityFor($code);return $city!==''?$city:$code; };
$canonicalHeaders=\BookingImportExport::HEADERS;
$canonicalMap=[]; foreach($canonicalHeaders as $h){$canonicalMap[$h]=match($h){'Booking ID'=>'booking_id','Agent (Client)'=>'agent','Supplier / Vendor'=>'vendor','Booking Date'=>'booking_date','Passenger Full Name'=>'passenger_name','Passport Number'=>'passport_number','Flight Number'=>'flight_number','Arrival Date (KSA)'=>'arrival_date','Departure Date (Exit)'=>'departure_date','Duration / Package'=>'stay_days','Visa Buy Cost (PKR)'=>'visa_buy','Visa Sell Price (PKR)'=>'visa_sell','Ticket Buy Cost (PKR)'=>'ticket_buy','Ticket Sell Price (PKR)'=>'ticket_sell','Attach Hotel (Y/N)'=>'attach_hotel','Attach Transport (Y/N)'=>'attach_transport','Notes'=>'notes','Status'=>'status','Created By'=>'created_by','Created At'=>'created_at','CustomField1'=>'custom_field1','CustomField2'=>'custom_field2',default=>''};}
?>

<main class="md:col-span-9 space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col xl:flex-row items-center justify-between gap-4">
        <div><h2 class="text-base font-bold text-slate-800 flex items-center"><i class="fa-solid fa-passport text-indigo-600 mr-2"></i> Master Bookings & Bulk File Engine</h2><p class="text-xs text-slate-500 mt-0.5">Import/export bookings, attach Ticket/Passport/Visa PDFs, and manage multiple Hotel/Transport lines.</p></div>
        <div class="flex flex-wrap gap-2 justify-end">
            <?php if (Auth::canWrite()): ?><button onclick="SmartMB.open()" class="relative overflow-hidden bg-gradient-to-r from-violet-600 via-fuchsia-600 to-pink-500 hover:brightness-110 text-white text-xs font-extrabold px-4 py-2.5 rounded-xl shadow-lg shadow-fuchsia-600/30"><i class="fa-solid fa-wand-magic-sparkles mr-1.5"></i> Smart Auto Booking <span class="ml-1 text-[9px] font-black bg-white/25 rounded-full px-1.5 py-0.5 align-middle">AI</span></button><button onclick="SmartVisa.open()" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:brightness-110 text-white text-xs font-extrabold px-4 py-2.5 rounded-xl shadow-lg shadow-teal-600/30"><i class="fa-solid fa-stamp mr-1.5"></i> Smart Visa / Passport <span class="ml-1 text-[9px] font-black bg-white/25 rounded-full px-1.5 py-0.5 align-middle">AI</span></button><?php endif; ?>
            <button onclick="openImportModal()" class="bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold px-4 py-2.5 rounded-xl"><i class="fa-solid fa-file-import mr-1.5"></i> Import Bookings</button>
            <button onclick="openBookingModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-4 py-2.5 rounded-xl"><i class="fa-solid fa-plus-circle mr-1.5"></i> New Booking</button>
            <button onclick="openBulkEditModal()" class="bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold px-4 py-2.5 rounded-xl"><i class="fa-solid fa-pen-to-square mr-1.5"></i> Bulk Edit Selected</button>
        </div>
    </div>

    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm space-y-3">
        <form method="GET" action="index.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2.5 text-xs">
            <input type="hidden" name="page" value="bookings">
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Booking ID, Name, Passport, Flight" class="border border-slate-300 rounded-xl p-2.5 bg-slate-50">
            <select name="agent_id" class="border border-slate-300 rounded-xl p-2.5 bg-slate-50"><option value="">All Agents</option><?php foreach($agents as $a): ?><option value="<?= $a['id'] ?>" <?= $filters['agent_id']==(int)$a['id']?'selected':'' ?>><?= htmlspecialchars($a['name']) ?></option><?php endforeach; ?></select>
            <select name="vendor_id" class="border border-slate-300 rounded-xl p-2.5 bg-slate-50"><option value="">All Suppliers</option><?php foreach($vendors as $v): ?><option value="<?= $v['id'] ?>" <?= $filters['vendor_id']==(int)$v['id']?'selected':'' ?>><?= htmlspecialchars($v['name']) ?></option><?php endforeach; ?></select>
            <select name="status" class="border border-slate-300 rounded-xl p-2.5 bg-slate-50"><option value="">All Statuses</option><?php foreach(['draft','confirmed','issued','completed','cancelled'] as $st): ?><option value="<?= $st ?>" <?= $filters['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option><?php endforeach; ?></select>
            <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>" class="border border-slate-300 rounded-xl p-2.5 font-mono" title="Date From">
            <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>" class="border border-slate-300 rounded-xl p-2.5 font-mono" title="Date To">
            <input type="month" name="month" value="<?= htmlspecialchars($filters['month']) ?>" class="border border-slate-300 rounded-xl p-2.5 font-mono" title="Month">
            <input type="number" min="2000" max="2100" name="year" value="<?= htmlspecialchars((string)$filters['year']) ?>" placeholder="Year" class="border border-slate-300 rounded-xl p-2.5 font-mono">
            <div class="lg:col-span-2 flex gap-2"><button class="flex-1 bg-slate-800 text-white rounded-xl p-2.5 font-semibold"><i class="fa-solid fa-filter mr-1"></i> Apply Filters</button><a href="index.php?page=bookings" class="px-3 bg-slate-100 hover:bg-slate-200 rounded-xl flex items-center"><i class="fa-solid fa-rotate-left"></i></a></div>
        </form>
        <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-slate-100">
            <span class="text-[11px] font-bold text-slate-500 mr-1">Export / Print current filter:</span>
            <button onclick="exportBookings('csv')" class="px-3 py-1.5 rounded-lg bg-sky-50 text-sky-700 text-[11px] font-bold"><i class="fa-solid fa-file-csv mr-1"></i> CSV</button>
            <button onclick="printBookings()" class="px-3 py-1.5 rounded-lg bg-rose-50 text-rose-700 text-[11px] font-bold"><i class="fa-solid fa-print mr-1"></i> Print</button>
            <span class="text-[10px] text-slate-400 ml-auto">Use your browser Print → Save as PDF</span>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden"><div class="overflow-x-auto"><table class="w-full text-left text-xs text-slate-600"><thead class="bg-slate-50 text-slate-700 border-b border-slate-200 uppercase font-bold text-[10px]"><tr><th class="p-3">Select</th><th class="p-3">Date</th><th class="p-3">Flight</th><th class="p-3">From</th><th class="p-3">Return</th><th class="p-3">Passenger</th><th class="p-3">Passport</th><th class="p-3">Agent</th><th class="p-3 text-right">Visa Buy</th><th class="p-3 text-right">Visa Sell</th><th class="p-3">Ticket 1</th><th class="p-3">Ticket 2</th><th class="p-3">Passport</th><th class="p-3">Visa</th><th class="p-3 text-right">Actions</th></tr></thead><tbody class="divide-y divide-slate-100">
    <?php if(!$bookings): ?><tr><td colspan="15" class="p-8 text-center text-slate-400">No booking records found.</td></tr><?php else: foreach($bookings as $b): $files=[]; foreach(($b['attachments']??[]) as $af){$files[$af['attachment_type']]=$af;} $itinerary=json_decode((string)($b['flight_itinerary_json']??'[]'),true); $itinerary=is_array($itinerary)?$itinerary:[]; $journeys=AirTicketController::journeys($itinerary); $fromJourney=$journeys[0]??[]; $returnJourney=count($journeys)>1?$journeys[count($journeys)-1]:[]; ?>
    <?php $t1 = $files['ticket_1'] ?? $files['ticket'] ?? null; $t2 = $files['ticket_2'] ?? null; ?>
    <tr class="hover:bg-slate-50/80">
    <td class="p-3"><input type="checkbox" class="booking-select" value="<?= (int)$b['id'] ?>" data-name="<?= htmlspecialchars((string)($b['passenger_name']??'')) ?>" data-passport="<?= htmlspecialchars((string)($b['passport_number']??'')) ?>" onchange="syncBookingSelection()" title="Select booking"></td>
    <td class="p-3 font-mono"><?= displayBookingDate($b['booking_date']) ?></td>
    <td class="p-3">
        <div class="whitespace-nowrap font-mono font-bold text-indigo-600"><?= htmlspecialchars((string)($b['flight_number']??'-')) ?></div>
    </td>
    <?php foreach([['journey'=>$fromJourney,'fallback'=>$b['arrival_date']??'','kind'=>'from'],['journey'=>$returnJourney,'fallback'=>$b['departure_date']??'','kind'=>'return']] as $flightCell): $journey=$flightCell['journey']; $firstLeg=$journey[0]??[]; $lastLeg=$journey?end($journey):[]; $routeFrom=trim((string)($firstLeg['from']??'')); $routeTo=trim((string)($lastLeg['to']??'')); $routeDate=trim((string)($firstLeg['dep_date']??$flightCell['fallback'])); $routeDateLabel=$routeDate!==''&&strtotime($routeDate)!==false?date('d-m-y',strtotime($routeDate)):'-'; $journeyRoute=$routeFrom!==''&&$routeTo!==''?$routeFrom.' → '.$airportLabel($routeTo):''; ?>
    <td class="p-3 min-w-40">
        <?php if($journey&&$journeyRoute!==''): ?>
        <details class="group">
            <summary class="flex cursor-pointer list-none items-center gap-1.5 text-[11px] font-semibold text-indigo-700 [&::-webkit-details-marker]:hidden" title="Show flight times and connecting legs">
                <span class="whitespace-nowrap"><?= htmlspecialchars($routeDateLabel) ?></span>
                <span class="whitespace-nowrap"><?= htmlspecialchars($routeFrom) ?> → <?= htmlspecialchars($airportLabel($routeTo)) ?></span>
                <i class="fa-solid fa-chevron-down shrink-0 text-[9px] text-indigo-500 transition-transform group-open:rotate-180"></i>
            </summary>
            <div class="mt-2 min-w-64 space-y-2 rounded-xl border border-indigo-100 bg-indigo-50/70 p-2.5 shadow-sm">
                <div class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-wide text-indigo-800">
                    <i class="fa-solid <?= $flightCell['kind']==='from'?'fa-plane-departure':'fa-plane-arrival' ?>"></i>
                    <?= $flightCell['kind']==='from'?'From journey':'Return journey' ?>
                </div>
                <?php foreach($journey as $leg): ?>
                <div class="border-l-2 border-indigo-200 pl-2 text-[10px] leading-4 text-slate-600">
                    <div class="font-bold text-slate-800"><?= htmlspecialchars((string)($leg['from']??'')) ?> → <?= htmlspecialchars($airportLabel((string)($leg['to']??''))) ?><?= !empty($leg['flight'])?' · '.htmlspecialchars((string)$leg['flight']):'' ?></div>
                    <div><span class="font-semibold text-slate-500">Depart</span> <?= htmlspecialchars(!empty($leg['dep_date'])&&strtotime($leg['dep_date'])!==false?date('d-m-y',strtotime($leg['dep_date'])):'-') ?> <?= htmlspecialchars((string)($leg['dep_time']??'')) ?></div>
                    <div><span class="font-semibold text-slate-500">Reached</span> <?= htmlspecialchars(!empty($leg['arr_date'])&&strtotime($leg['arr_date'])!==false?date('d-m-y',strtotime($leg['arr_date'])):'-') ?> <?= htmlspecialchars((string)($leg['arr_time']??'')) ?></div>
                </div>
                <?php endforeach; ?>
                <div class="border-t border-indigo-100 pt-1 text-[10px] font-semibold text-indigo-800">
                    Reached <?= htmlspecialchars(!empty($lastLeg['arr_date'])&&strtotime($lastLeg['arr_date'])!==false?date('d-m-y',strtotime($lastLeg['arr_date'])):'-') ?> · <?= htmlspecialchars((string)($lastLeg['arr_time']??'')) ?>
                </div>
            </div>
        </details>
        <?php else: ?>
            <span class="font-mono text-slate-500"><?= displayBookingDate($flightCell['fallback']?:'-') ?></span>
        <?php endif; ?>
    </td>
    <?php endforeach; ?>
    <td class="p-3 font-semibold text-slate-900"><?= htmlspecialchars((string)($b['passenger_name']??'-')) ?></td>
    <td class="p-3 font-mono"><?= htmlspecialchars((string)($b['passport_number']??'-')) ?></td>
    <td class="p-3"><?= htmlspecialchars((string)($b['agent_name']??'-')) ?></td>
    <td class="p-3 text-right font-mono text-rose-600"><?= number_format((float)$b['buy_rate_pkr'],2) ?></td>
    <td class="p-3 text-right font-mono font-bold text-indigo-700"><?= number_format((float)$b['sell_rate_pkr'],2) ?></td>
    <td class="p-3"><?= !empty($t1) ? '<a target="_blank" class="text-sky-700 font-bold text-xs hover:underline" href="'.htmlspecialchars($t1['download_url']).'">Download</a>' : '<span class="text-slate-300">—</span>' ?></td>
    <td class="p-3"><?= !empty($t2) ? '<a target="_blank" class="text-sky-700 font-bold text-xs hover:underline" href="'.htmlspecialchars($t2['download_url']).'">Download</a>' : '<span class="text-slate-300">—</span>' ?></td>
    <td class="p-3"><?= !empty($files['passport']) ? '<a target="_blank" class="text-amber-700 font-bold text-xs hover:underline" href="'.htmlspecialchars($files['passport']['download_url']).'">Download</a>' : '<span class="text-slate-300">—</span>' ?></td>
    <td class="p-3"><?= !empty($files['visa']) ? '<a target="_blank" class="text-indigo-700 font-bold text-xs hover:underline" href="'.htmlspecialchars($files['visa']['download_url']).'">Download</a>' : '<span class="text-slate-300">—</span>' ?></td>
    <td class="p-3 text-right"><div class="flex justify-end gap-2"><button onclick="editBooking(<?= (int)$b['id'] ?>)" class="text-indigo-600 p-1" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button><button onclick="exportSingleBooking(<?= (int)$b['id'] ?>)" class="text-slate-700 p-1" title="Print Booking"><i class="fa-solid fa-print"></i></button><button onclick="deleteMasterBooking(<?= (int)$b['id'] ?>)" class="text-slate-400 hover:text-rose-600 p-1" title="Delete"><i class="fa-solid fa-trash-can"></i></button></div></td>
    </tr><?php endforeach; endif; ?></tbody></table></div></div>
</main>

<script>
const AST_AGENTS=<?= json_encode($agents,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const AST_VENDORS=<?= json_encode($vendors,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const AST_CANONICAL=<?= json_encode($canonicalHeaders,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const AST_CANONICAL_MAP=<?= json_encode($canonicalMap,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const AST_CSRF=document.querySelector('meta[name="csrf-token"]')?.content||'';
let pendingFiles={visa:null,ticket_1:null,ticket_2:null,passport:null};

function esc(v){const d=document.createElement('div');d.textContent=v??'';return d.innerHTML;}
function roomOptions(value='Double Bed'){const types=['Double Bed','Triple Bed','Sharing','Twin Bed','Quadruple Bed','Four Bed Room','Five Bed Room','Six Bed Room','Family Room','Suite'];const known=types.includes(value);return types.map(t=>`<option value="${esc(t)}" ${value===t?'selected':''}>${esc(t)}</option>`).join('')+`<option value="__custom__" ${!known?'selected':''}>Custom Room Type...</option>`;}
function stayRowHtml(s={},i=0){const room=s.room_type||'Double Bed';return `<div class="stay-row border border-slate-200 rounded-xl p-3 bg-white space-y-2" data-index="${i}"><div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2"><div><label class="field-label">City</label><select name="hotel_stays[${i}][city]" class="stay-city field">${['Makkah','Madinah'].map(x=>`<option ${s.city===x?'selected':''}>${x}</option>`).join('')}</select></div><div><label class="field-label">Hotel Name</label><input name="hotel_stays[${i}][hotel_name]" class="stay-hotel field" value="${esc(s.hotel_name||'')}" placeholder="Hotel Name"></div><div><label class="field-label">Room Type</label><select name="hotel_stays[${i}][room_type]" class="stay-room field" onchange="toggleBookingCustomRoom(this)">${roomOptions(room)}</select><input name="hotel_stays[${i}][custom_room_type]" class="stay-custom-room field mt-1 ${room==='__custom__'||!['Double Bed','Triple Bed','Sharing','Twin Bed','Quadruple Bed','Four Bed Room','Five Bed Room','Six Bed Room','Family Room','Suite'].includes(room)?'':'hidden'}" value="${esc(s.custom_room_type||(!['Double Bed','Triple Bed','Sharing','Twin Bed','Quadruple Bed','Four Bed Room','Five Bed Room','Six Bed Room','Family Room','Suite'].includes(room)?room:''))}" placeholder="Custom room type"></div><div><label class="field-label">Check-In</label><input type="date" name="hotel_stays[${i}][checkin_date]" class="stay-in field" value="${esc(s.checkin_date||'')}" onchange="syncStayNights(this)"></div><div><label class="field-label">Check-Out</label><input type="date" name="hotel_stays[${i}][checkout_date]" class="stay-out field" value="${esc(s.checkout_date||'')}" onchange="syncStayNights(this)"></div><div><label class="field-label">Nights</label><input type="number" min="1" name="hotel_stays[${i}][nights]" class="stay-nights field font-mono" value="${esc(s.nights||1)}"></div></div><div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2"><div><label class="field-label">Buy/Night (PKR)</label><input type="number" min="0" step="0.01" name="hotel_stays[${i}][buy_rate_per_night]" class="stay-buy field font-mono text-rose-700" value="${esc(s.per_night_buy||s.buy_rate_per_night||0)}"></div><div><label class="field-label">Sell/Night (PKR)</label><input type="number" min="0" step="0.01" name="hotel_stays[${i}][sell_rate_per_night]" class="stay-sell field font-mono text-emerald-700" value="${esc(s.per_night_sell||s.sell_rate_per_night||0)}"></div><div><label class="field-label">PAX</label><input type="number" min="1" name="hotel_stays[${i}][pax]" class="stay-pax field" value="${esc(s.pax||1)}"></div><div><label class="field-label">Meal Plan</label><input name="hotel_stays[${i}][meal_plan]" class="stay-meal field" value="${esc(s.meal_plan||'RO')}" placeholder="RO / BB / HB"></div><div><label class="field-label">View</label><input name="hotel_stays[${i}][view]" class="stay-view field" value="${esc(s.view||'')}" placeholder="Kaaba / City"></div><div class="flex items-end gap-2"><button type="button" onclick="removeBookingStay(this)" class="flex-1 bg-rose-50 text-rose-700 rounded-lg p-2 font-bold">Remove</button></div></div><div class="grid grid-cols-1 sm:grid-cols-3 gap-2"><div><label class="field-label">Net Accommodation Charge (PKR)</label><input type="number" min="0" step="0.01" name="hotel_stays[${i}][net_accommodation_charge]" class="stay-net field" value="${esc(s.net_accommodation_charge||0)}"></div><div><label class="field-label">VAT (PKR)</label><input type="number" min="0" step="0.01" name="hotel_stays[${i}][vat]" class="stay-vat field" value="${esc(s.vat||0)}"></div><div><label class="field-label">Notes</label><input name="hotel_stays[${i}][notes]" class="stay-notes field" value="${esc(s.notes||'')}" placeholder="Stay notes"></div></div></div>`;}
function transportRowHtml(t={},i=0){return `<div class="transport-row border border-slate-200 rounded-xl p-3 bg-white space-y-2" data-index="${i}"><div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2"><div><label class="field-label">Service Date</label><input type="date" name="transport_transfers[${i}][service_date]" class="tr-date field" value="${esc(t.service_date||'')}" ></div><div><label class="field-label">Pickup Time (24h)</label><input type="time" name="transport_transfers[${i}][pickup_time]" class="tr-time field font-mono" value="${esc((t.pickup_time||'08:00').slice(0,5))}"></div><div><label class="field-label">Flight</label><input name="transport_transfers[${i}][flight_number]" class="tr-flight field" value="${esc(t.flight_number||'')}" placeholder="SV123"></div><div><label class="field-label">Terminal</label><input name="transport_transfers[${i}][terminal]" class="tr-terminal field" value="${esc(t.terminal||'Terminal 1')}"></div><div><label class="field-label">Vehicle Type</label><input name="transport_transfers[${i}][vehicle_type]" class="tr-vehicle field" value="${esc(t.vehicle_type||'Car')}"></div><div><label class="field-label">Route</label><input name="transport_transfers[${i}][route_details]" class="tr-route field" value="${esc(t.route_details||'JED-MAK')}" placeholder="JED-MAK"></div></div><div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2"><div><label class="field-label">Passenger</label><input name="transport_transfers[${i}][pax_name]" class="tr-pax-name field" value="${esc(t.pax_name||'')}" ></div><div><label class="field-label">Passport</label><input name="transport_transfers[${i}][passport_number]" class="tr-passport field font-mono" value="${esc(t.passport_number||'')}" ></div><div><label class="field-label">PAX Count</label><input type="number" min="1" name="transport_transfers[${i}][pax_count]" class="tr-count field" value="${esc(t.pax_count||1)}"></div><div><label class="field-label">Buy Rate (Cost PKR)</label><input type="number" min="0" step="0.01" name="transport_transfers[${i}][buy_rate]" class="tr-buy field font-mono text-rose-700" value="${esc(t.buy_rate||0)}"></div><div><label class="field-label">Sell Rate (Price PKR)</label><input type="number" min="0" step="0.01" name="transport_transfers[${i}][sell_rate]" class="tr-sell field font-mono text-emerald-700" value="${esc(t.sell_rate||0)}"></div><div class="flex items-end gap-2"><input name="transport_transfers[${i}][notes]" class="tr-notes field flex-1" value="${esc(t.notes||'')}" placeholder="Notes"><button type="button" onclick="removeBookingTransport(this)" class="bg-rose-50 text-rose-700 rounded-lg px-3 py-2 font-bold">×</button></div></div></div>`;}
function openBookingModal(b=null){const edit=!!b;const today=new Date().toISOString().slice(0,10);const stays=b?.hotel_stays||[];const transfers=b?.transport_transfers||[];pendingFiles={visa:null,ticket_1:null,ticket_2:null,passport:null};const modal=document.getElementById('dynamicModalContainer');modal.innerHTML=`<div class="fixed inset-0 bg-slate-900/70 z-50 flex items-start justify-center p-3 overflow-y-auto overscroll-contain"><div class="bg-white rounded-2xl max-w-6xl w-full p-5 shadow-2xl my-5 max-h-[calc(100vh-1.5rem)] overflow-y-auto overscroll-contain"><div class="flex justify-between items-center border-b pb-3 mb-4"><div><h3 class="font-bold text-slate-800">${edit?'Edit Master Booking':'Create Master Booking'}</h3><p class="text-[11px] text-slate-500">All money fields are PKR. Transport time is 24-hour HH:mm.</p></div><button onclick="closeActiveModal()" class="text-slate-400"><i class="fa-solid fa-xmark text-lg"></i></button></div><form id="masterBookingForm" class="space-y-4"><div class="grid grid-cols-1 md:grid-cols-4 gap-2.5"><div><label class="field-label">Booking ID</label><input id="mb_code" class="field bg-slate-100 font-mono" value="${esc(b?.booking_code||'Auto-generated')}" readonly></div><div><label class="field-label">Booking Date</label><input id="mb_date" type="date" class="field" value="${esc(b?.booking_date||today)}"></div><div><label class="field-label">Agent (Client)</label><select id="mb_agent" class="field"><option value="">Select Agent</option>${AST_AGENTS.map(a=>`<option value="${a.id}" ${b&&b.agent_id==a.id?'selected':''}>${esc(a.name)}</option>`).join('')}</select></div><div><label class="field-label">Supplier / Vendor</label><select id="mb_vendor" class="field"><option value="">Select Supplier</option>${AST_VENDORS.map(v=>`<option value="${v.id}" ${b&&b.vendor_id==v.id?'selected':''}>${esc(v.name)}</option>`).join('')}</select></div></div><div class="grid grid-cols-1 md:grid-cols-4 gap-2.5"><div><label class="field-label">Passenger Full Name</label><input id="mb_name" class="field" value="${esc(b?.passenger_name||'')}"></div><div><label class="field-label">Passport Number</label><input id="mb_passport" class="field font-mono uppercase" value="${esc(b?.passport_number||'')}"></div><div><label class="field-label">Flight Number</label><input id="mb_flight" class="field font-mono uppercase" value="${esc(b?.flight_number||'')}"></div><div><label class="field-label">Duration / Package</label><input id="mb_stay" class="field" value="${esc(b?.stay_days||'')}" placeholder="14 nights"></div></div><div class="grid grid-cols-1 md:grid-cols-6 gap-2.5 bg-slate-50 border border-slate-200 rounded-xl p-3"><div><label class="field-label">Arrival Date (KSA)</label><input id="mb_arrival" type="date" class="field" value="${esc(b?.arrival_date||'')}"></div><div><label class="field-label">Departure Date (Exit)</label><input id="mb_departure" type="date" class="field" value="${esc(b?.departure_date||'')}"></div><div><label class="field-label">Visa Buy (PKR)</label><input id="mb_visa_buy" type="number" min="0" step="0.01" class="field font-mono text-rose-700" value="${esc(b?.buy_rate_pkr||0)}"></div><div><label class="field-label">Visa Sell (PKR)</label><input id="mb_visa_sell" type="number" min="0" step="0.01" class="field font-mono text-indigo-700" value="${esc(b?.sell_rate_pkr||0)}"></div><div><label class="field-label">Ticket Buy (PKR)</label><input id="mb_ticket_buy" type="number" min="0" step="0.01" class="field font-mono text-rose-700" value="${esc(b?.ticket_buy_rate_pkr||0)}"></div><div><label class="field-label">Ticket Sell (PKR)</label><input id="mb_ticket_sell" type="number" min="0" step="0.01" class="field font-mono text-sky-700" value="${esc(b?.ticket_sell_rate_pkr||0)}"></div></div><div class="grid grid-cols-1 md:grid-cols-4 gap-2.5"><div><label class="field-label">Status</label><select id="mb_status" class="field">${['draft','confirmed','issued','completed','cancelled'].map(x=>`<option ${b?.status===x?'selected':''}>${x}</option>`).join('')}</select></div><div><label class="field-label">CustomField1</label><input id="mb_cf1" class="field" value="${esc(b?.custom_field1||'')}"></div><div><label class="field-label">CustomField2</label><input id="mb_cf2" class="field" value="${esc(b?.custom_field2||'')}"></div><div><label class="field-label">Notes</label><input id="mb_notes" class="field" value="${esc(b?.remarks||'')}"></div></div><section class="border border-amber-200 bg-amber-50/40 rounded-xl p-3"><div class="flex items-center justify-between mb-2"><div><h4 class="font-bold text-amber-950 text-xs"><i class="fa-solid fa-bed mr-1"></i> Hotel Accommodation Stays</h4><p class="text-[10px] text-slate-500">Each added stay clones the complete field set.</p></div><button type="button" onclick="addBookingStay()" class="text-amber-800 font-bold text-xs"><i class="fa-solid fa-plus-circle mr-1"></i>Add Another Stay</button></div><label class="flex items-center gap-2 text-xs font-semibold mb-2"><input id="attachHotel" type="checkbox" ${b?.attach_hotel||stays.length?'checked':''} onchange="toggleSection('hotelStaySection',this.checked)"> Attach Hotel Accommodation</label><div id="hotelStaySection" class="space-y-2 ${b?.attach_hotel||stays.length?'':'hidden'}"><div id="bookingStays">${(stays.length?stays:[{}]).map((x,i)=>stayRowHtml(x,i)).join('')}</div></div></section><section class="border border-emerald-200 bg-emerald-50/40 rounded-xl p-3"><div class="flex items-center justify-between mb-2"><div><h4 class="font-bold text-emerald-950 text-xs"><i class="fa-solid fa-van-shuttle mr-1"></i> Airport Pickup / Transport Transfers</h4><p class="text-[10px] text-slate-500">Buy and Sell are stored independently for every transfer.</p></div><button type="button" onclick="addBookingTransport()" class="text-emerald-800 font-bold text-xs"><i class="fa-solid fa-plus-circle mr-1"></i>Add Another Transfer</button></div><label class="flex items-center gap-2 text-xs font-semibold mb-2"><input id="attachTransport" type="checkbox" ${b?.attach_transport||transfers.length?'checked':''} onchange="toggleSection('transportSection',this.checked)"> Attach Airport Pickup / Transport</label><div id="transportSection" class="space-y-2 ${b?.attach_transport||transfers.length?'':'hidden'}"><div id="bookingTransports">${(transfers.length?transfers:[{}]).map((x,i)=>transportRowHtml(x,i)).join('')}</div></div></section><section class="border border-indigo-200 bg-indigo-50/40 rounded-xl p-3"><h4 class="font-bold text-indigo-950 text-xs mb-2"><i class="fa-solid fa-paperclip mr-1"></i> Ticket, Passport & Visa Attachments (PDF only, max 10 MB)</h4><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">${attachmentBox('ticket_1',b)}${attachmentBox('ticket_2',b)}${attachmentBox('passport',b)}${attachmentBox('visa',b)}</div></section><div class="flex justify-end gap-2 pt-2 border-t"><button type="button" onclick="closeActiveModal()" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-semibold">Cancel</button><button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold"><i class="fa-solid fa-floppy-disk mr-1"></i>${edit?'Update Booking':'Save Booking'}</button></div></form></div></div>`;document.getElementById('masterBookingForm').addEventListener('submit',e=>handleBookingFormSubmit(e,b?.id||null));}
function bookingItineraryRowHtml(segment={}) {
    const extras={from_city:segment.from_city||'',to_city:segment.to_city||'',from_terminal:segment.from_terminal||'',to_terminal:segment.to_terminal||'',baggage:segment.baggage||'',duration:segment.duration||''};
    return `<div class="booking-itinerary-row grid grid-cols-2 md:grid-cols-8 gap-2 items-end rounded-lg border border-slate-200 bg-white p-2">
        <div><label class="field-label">Flight</label><input class="it-flight field font-mono uppercase" maxlength="20" value="${esc(segment.flight||'')}" oninput="syncMasterBookingFlight(this)"></div>
        <div><label class="field-label">From</label><input class="it-from field font-mono uppercase" maxlength="3" value="${esc(segment.from||'')}" placeholder="SKT"></div>
        <div><label class="field-label">To</label><input class="it-to field font-mono uppercase" maxlength="3" value="${esc(segment.to||'')}" placeholder="DXB"></div>
        <div><label class="field-label">Departure Date</label><input type="date" class="it-dep-date field" value="${esc(segment.dep_date||'')}"></div>
        <div><label class="field-label">Departure Time</label><input type="time" class="it-dep-time field" value="${esc((segment.dep_time||'').slice(0,5))}"></div>
        <div><label class="field-label">Arrival Date</label><input type="date" class="it-arr-date field" value="${esc(segment.arr_date||'')}"></div>
        <div><label class="field-label">Arrival Time</label><input type="time" class="it-arr-time field" value="${esc((segment.arr_time||'').slice(0,5))}"></div>
        <div class="flex items-end"><input type="hidden" class="it-extras" value="${esc(JSON.stringify(extras))}"><button type="button" onclick="removeBookingItineraryRow(this)" class="w-full rounded-lg bg-rose-50 text-rose-600 p-2 text-xs font-bold" title="Remove leg"><i class="fa-solid fa-trash"></i></button></div>
    </div>`;
}
function addBookingItineraryRow(journey='from',segment={}){document.getElementById(journey==='return'?'bookingItineraryReturn':'bookingItineraryFrom').insertAdjacentHTML('beforeend',bookingItineraryRowHtml(segment));}
function removeBookingItineraryRow(button){button.closest('.booking-itinerary-row')?.remove();syncMasterBookingFlight();}
function syncMasterBookingFlight(input){const first=document.querySelector('#bookingItineraryFrom .it-flight');const field=document.getElementById('mb_flight');if(field&&(!input||input===first)&&(input===first||first?.value.trim()))field.value=first?.value.trim().toUpperCase()||'';}
function collectBookingItinerary(){
    return ['bookingItineraryFrom','bookingItineraryReturn'].flatMap(id=>Array.from(document.querySelectorAll(`#${id} .booking-itinerary-row`))).map(row=>{
        let extras={};try{extras=JSON.parse(row.querySelector('.it-extras')?.value||'{}');}catch(error){console.error('Invalid saved flight itinerary metadata.',error);}
        return {...extras,flight:row.querySelector('.it-flight').value.trim().toUpperCase(),from:row.querySelector('.it-from').value.trim().toUpperCase(),to:row.querySelector('.it-to').value.trim().toUpperCase(),dep_date:row.querySelector('.it-dep-date').value,dep_time:row.querySelector('.it-dep-time').value,arr_date:row.querySelector('.it-arr-date').value,arr_time:row.querySelector('.it-arr-time').value};
    }).filter(segment=>segment.flight||segment.from||segment.to||segment.dep_date||segment.arr_date);
}
function bookingItineraryJourneys(segments){
    const journeys=[];
    for(const segment of segments){
        const lastJourney=journeys.at(-1)||[];
        const previous=lastJourney.at(-1);
        const arrived=previous?.arr_date?new Date(`${previous.arr_date}T${previous.arr_time||'12:00'}`).getTime():NaN;
        const departs=segment.dep_date?new Date(`${segment.dep_date}T${segment.dep_time||'12:00'}`).getTime():NaN;
        const gap=departs-arrived;
        if(previous&&previous.to===segment.from&&Number.isFinite(gap)&&gap>=0&&gap<=86400000)lastJourney.push(segment);
        else journeys.push([segment]);
    }
    return journeys;
}
function deriveMasterBookingDates(segments,booking){
    if(!segments.length)return {arrival:booking?.arrival_date||'',departure:booking?.departure_date||''};
    const journeys=[];
    for(const segment of segments){
        const previous=journeys.at(-1)?.at(-1);
        const arrivalAt=previous?.arr_date?new Date(`${previous.arr_date}T${previous.arr_time||'12:00'}`):null;
        const departureAt=segment.dep_date?new Date(`${segment.dep_date}T${segment.dep_time||'12:00'}`):null;
        const gap=arrivalAt&&departureAt?departureAt-arrivalAt:null;
        if(previous&&previous.to&&previous.to===segment.from&&gap!==null&&gap>=0&&gap<=86400000)journeys.at(-1).push(segment);
        else journeys.push([segment]);
    }
    const fromLegs=journeys[0]||[];
    const returnLegs=journeys.length>1?journeys.at(-1):[];
    return {
        arrival:fromLegs.at(-1)?.arr_date||booking?.arrival_date||'',
        departure:returnLegs[0]?.dep_date||booking?.departure_date||''
    };
}
const openBookingModalWithoutItinerary=openBookingModal;
openBookingModal=function(booking=null){
    openBookingModalWithoutItinerary(booking);
    let segments=[];
    try{segments=JSON.parse(booking?.flight_itinerary_json||'[]');if(!Array.isArray(segments))segments=[];}
    catch(error){console.error('Unable to read saved Master Booking itinerary.',error);}
    const journeys=bookingItineraryJourneys(segments);
    const fromSegments=journeys.shift()||[];
    const returnSegments=journeys.flat();
    for(const id of ['mb_arrival','mb_departure']){
        const cell=document.getElementById(id)?.parentElement;
        if(cell)cell.classList.add('hidden');
    }
    document.getElementById('mb_arrival')?.closest('.grid')?.classList.replace('md:grid-cols-6','md:grid-cols-4');
    const flightGrid=document.getElementById('mb_flight')?.closest('.grid');
    if(!flightGrid)return;
    flightGrid.insertAdjacentHTML('afterend',`<section class="border border-sky-200 bg-sky-50/40 rounded-xl p-3 space-y-2">
        <div><h4 class="font-bold text-sky-950 text-xs"><i class="fa-solid fa-route mr-1"></i> Flight itinerary (any route)</h4><p class="text-[10px] text-slate-500">Enter outbound and return journeys separately. Add one leg for each connecting flight; all flight details are saved to the booking and Operation Manifest.</p></div>
        <div class="grid grid-cols-2 md:grid-cols-6 gap-2"><div><label class="field-label">Gender</label><select id="mb_gender" class="field"><option value="">Not specified</option><option value="M" ${booking?.gender==='M'?'selected':''}>Male</option><option value="F" ${booking?.gender==='F'?'selected':''}>Female</option></select></div><div><label class="field-label">Passenger Type</label><select id="mb_pax_type" class="field">${['Adult','Child','Infant'].map(type=>`<option value="${type}" ${(booking?.pax_type||'Adult')===type?'selected':''}>${type}</option>`).join('')}</select></div></div>
        <div class="rounded-lg border border-indigo-200 bg-white p-2.5 space-y-2"><div class="flex items-center justify-between gap-2"><div><h5 class="text-[11px] font-extrabold text-indigo-900"><i class="fa-solid fa-plane-departure mr-1"></i>From journey</h5><p class="text-[10px] text-slate-500">Starting flight and any connecting flights.</p></div><button type="button" onclick="addBookingItineraryRow('from')" class="text-indigo-700 font-bold text-[10px]"><i class="fa-solid fa-plus-circle mr-1"></i>Add From Leg</button></div><div id="bookingItineraryFrom" class="space-y-2">${(fromSegments.length?fromSegments:[{}]).map(bookingItineraryRowHtml).join('')}</div></div>
        <div class="rounded-lg border border-rose-200 bg-white p-2.5 space-y-2"><div class="flex items-center justify-between gap-2"><div><h5 class="text-[11px] font-extrabold text-rose-900"><i class="fa-solid fa-plane-arrival mr-1"></i>Return journey</h5><p class="text-[10px] text-slate-500">Return flight and any connecting flights. Leave empty for a one-way booking.</p></div><button type="button" onclick="addBookingItineraryRow('return')" class="text-rose-700 font-bold text-[10px]"><i class="fa-solid fa-plus-circle mr-1"></i>Add Return Leg</button></div><div id="bookingItineraryReturn" class="space-y-2">${(returnSegments.length?returnSegments:[{}]).map(bookingItineraryRowHtml).join('')}</div></div>
    </section>`);
};
function attachmentBox(type,b){const labelMap={ticket_1:'Ticket 1 (Arrival/Outward)',ticket_2:'Ticket 2 (Return - Optional)',passport:'Passport',visa:'Visa'};const label=labelMap[type]||type;const f=(b?.attachments||[]).find(x=>x.attachment_type===type||(type==='ticket_1'&&x.attachment_type==='ticket'));return `<div class="bg-white border rounded-xl p-3"><div class="text-[10px] uppercase font-bold text-slate-500 mb-1">${label}</div>${f?`<div class="flex items-center justify-between gap-2 mb-2"><a href="${esc(f.download_url||'')}" target="_blank" class="text-xs text-indigo-700 font-semibold truncate">${esc(f.original_filename)}</a><button type="button" onclick="removeBookingFile(${f.id})" class="text-rose-600 text-xs font-bold">Remove</button></div>`:`<div class="text-[11px] text-slate-400 mb-2">No file uploaded.</div>`}<input type="file" accept="application/pdf,.pdf" onchange="pendingFiles.${type}=this.files[0]||null;validatePendingPdf(this)" class="w-full text-[11px]"></div>`;}
function validatePendingPdf(input){const f=input.files?.[0];if(!f)return;if(f.size>10485760){alert('File exceeds the 10 MB limit.');input.value='';return;}if(f.type!=='application/pdf'&&!/\.pdf$/i.test(f.name)){alert('Only PDF files are supported.');input.value='';}}
async function removeBookingFile(id){if(!confirm('Remove this attachment?'))return;const r=await fetch('index.php?api=remove_booking_file',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':AST_CSRF},body:JSON.stringify({file_id:id})});const d=await r.json();if(d.success){showToast('success',d.message);closeActiveModal();location.reload();}else alert(d.message);}
function toggleSection(id,checked){document.getElementById(id)?.classList.toggle('hidden',!checked);}
function toggleBookingCustomRoom(sel){const row=sel.closest('.stay-row');const input=row?.querySelector('.stay-custom-room');if(!input)return;const custom=sel.value==='__custom__';input.classList.toggle('hidden',!custom);input.required=custom;}
function syncStayNights(el){const row=el.closest('.stay-row');const a=row?.querySelector('.stay-in')?.value,b=row?.querySelector('.stay-out')?.value;if(a&&b){const n=Math.round((new Date(b+'T00:00:00')-new Date(a+'T00:00:00'))/86400000);if(n>0)row.querySelector('.stay-nights').value=n;}}
function renumberRows(selector,prefix){document.querySelectorAll(selector).forEach((row,i)=>{row.dataset.index=i;row.querySelectorAll('[name]').forEach(el=>{el.name=el.name.replace(new RegExp(prefix+'\\[\\d+\\]'),prefix+'['+i+']');});});}
function addBookingStay(){const c=document.getElementById('bookingStays');c.insertAdjacentHTML('beforeend',stayRowHtml({},c.querySelectorAll('.stay-row').length));renumberRows('.stay-row','hotel_stays');document.getElementById('attachHotel').checked=true;toggleSection('hotelStaySection',true);}
function removeBookingStay(btn){const c=document.getElementById('bookingStays');if(c.querySelectorAll('.stay-row').length<=1){alert('At least one accommodation stay must remain.');return;}btn.closest('.stay-row').remove();renumberRows('.stay-row','hotel_stays');}
function addBookingTransport(){const c=document.getElementById('bookingTransports');c.insertAdjacentHTML('beforeend',transportRowHtml({},c.querySelectorAll('.transport-row').length));renumberRows('.transport-row','transport_transfers');document.getElementById('attachTransport').checked=true;toggleSection('transportSection',true);}
function removeBookingTransport(btn){const c=document.getElementById('bookingTransports');if(c.querySelectorAll('.transport-row').length<=1){alert('At least one transport transfer must remain.');return;}btn.closest('.transport-row').remove();renumberRows('.transport-row','transport_transfers');}
function collectStays(){return Array.from(document.querySelectorAll('.stay-row')).map(r=>({line_item_id:r.dataset.lineItemId||'',city:r.querySelector('.stay-city').value,hotel_name:r.querySelector('.stay-hotel').value,room_type:r.querySelector('.stay-room').value,custom_room_type:r.querySelector('.stay-custom-room').value,checkin_date:r.querySelector('.stay-in').value,checkout_date:r.querySelector('.stay-out').value,nights:r.querySelector('.stay-nights').value,buy_rate_per_night:r.querySelector('.stay-buy').value,sell_rate_per_night:r.querySelector('.stay-sell').value,pax:r.querySelector('.stay-pax').value,meal_plan:r.querySelector('.stay-meal').value,view:r.querySelector('.stay-view').value,net_accommodation_charge:r.querySelector('.stay-net').value,vat:r.querySelector('.stay-vat').value,notes:r.querySelector('.stay-notes').value}));}
function collectTransfers(){return Array.from(document.querySelectorAll('.transport-row')).map(r=>({line_item_id:r.dataset.lineItemId||'',service_date:r.querySelector('.tr-date').value,pickup_time:r.querySelector('.tr-time').value,flight_number:r.querySelector('.tr-flight').value,terminal:r.querySelector('.tr-terminal').value,pax_name:r.querySelector('.tr-pax-name').value,passport_number:r.querySelector('.tr-passport').value,pax_count:r.querySelector('.tr-count').value,vehicle_type:r.querySelector('.tr-vehicle').value,route_details:r.querySelector('.tr-route').value,buy_rate:r.querySelector('.tr-buy').value,sell_rate:r.querySelector('.tr-sell').value,notes:r.querySelector('.tr-notes').value}));}
async function handleBookingFormSubmit(e,id){
    e.preventDefault();
    const hotel=document.getElementById('attachHotel').checked;
    const transport=document.getElementById('attachTransport').checked;
    const itinerary=collectBookingItinerary();
    const travelDates=deriveMasterBookingDates(itinerary,{
        arrival_date:document.getElementById('mb_arrival').value,
        departure_date:document.getElementById('mb_departure').value
    });
    const payload={
        id,booking_date:document.getElementById('mb_date').value,agent_id:document.getElementById('mb_agent').value,
        vendor_id:document.getElementById('mb_vendor').value,passenger_name:document.getElementById('mb_name').value,
        passport_number:document.getElementById('mb_passport').value,flight_number:document.getElementById('mb_flight').value,
        flight_itinerary:itinerary,gender:document.getElementById('mb_gender')?.value||'',
        pax_type:document.getElementById('mb_pax_type')?.value||'Adult',
        arrival_date:travelDates.arrival,departure_date:travelDates.departure,
        stay_days:document.getElementById('mb_stay').value,buy_rate_pkr:document.getElementById('mb_visa_buy').value,
        sell_rate_pkr:document.getElementById('mb_visa_sell').value,ticket_buy_rate_pkr:document.getElementById('mb_ticket_buy').value,
        ticket_sell_rate_pkr:document.getElementById('mb_ticket_sell').value,status:document.getElementById('mb_status').value,
        custom_field1:document.getElementById('mb_cf1').value,custom_field2:document.getElementById('mb_cf2').value,
        remarks:document.getElementById('mb_notes').value,include_hotel:hotel,include_transport:transport,
        hotel_stays:hotel?collectStays():[],transport_transfers:transport?collectTransfers():[]
    };
    const endpoint=id?'index.php?api=update_booking':'index.php?api=save_booking';
    const r=await fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':AST_CSRF},body:JSON.stringify(payload)});
    const d=await r.json();
    if(!d.success){alert(d.message||'Unable to save booking.');return;}
    const bookingId=id||d.booking_id;
    for(const type of ['ticket_1','ticket_2','passport','visa'])if(pendingFiles[type])await uploadBookingFile(bookingId,type,pendingFiles[type]);
    location.reload();
}
async function uploadBookingFile(bookingId,type,file){const fd=new FormData();fd.append('booking_id',bookingId);fd.append('type',type);fd.append('file',file);const r=await fetch('index.php?api=upload_booking_file',{method:'POST',headers:{'X-CSRF-Token':AST_CSRF},body:fd});const d=await r.json();if(!d.success)alert(`${type} upload failed: ${d.message}`);}
function syncBookingSelection(){const selected=document.querySelectorAll('.booking-select:checked').length;document.querySelectorAll('[onclick="openBulkEditModal()"]')?.forEach(btn=>btn.classList.toggle('opacity-60',selected===0));}
function toggleAllBookings(checked){document.querySelectorAll('.booking-select').forEach(input=>input.checked=checked);syncBookingSelection();}
function openBulkEditModal(){
    const ids=Array.from(document.querySelectorAll('.booking-select:checked')).map(input=>Number(input.value));
    if(!ids.length){alert('Select at least one booking first.');return;}
    const modal=document.getElementById('dynamicModalContainer');
    modal.innerHTML=`<div class="fixed inset-0 bg-slate-900/70 z-50 flex items-start justify-center p-3 overflow-y-auto overscroll-contain"><div class="bg-white rounded-2xl max-w-2xl w-full p-5 shadow-2xl my-5 max-h-[calc(100vh-1.5rem)] overflow-y-auto overscroll-contain"><div class="flex justify-between items-center border-b pb-3 mb-4"><div><h3 class="font-bold text-slate-800">Bulk Edit Bookings</h3><p class="text-[11px] text-slate-500">Updating ${ids.length} selected booking(s). Leave fields blank to keep their current values.</p></div><button onclick="closeActiveModal()" class="text-slate-400"><i class="fa-solid fa-xmark text-lg"></i></button></div><form id="bulkBookingForm" class="space-y-3"><div class="grid grid-cols-1 md:grid-cols-2 gap-3"><div><label class="field-label">Agent (Client)</label><select name="agent_id" class="field"><option value="">Keep Current</option>${AST_AGENTS.map(a=>`<option value="${a.id}">${esc(a.name)}</option>`).join('')}</select></div><div><label class="field-label">Supplier / Vendor</label><select name="vendor_id" class="field"><option value="">Keep Current</option>${AST_VENDORS.map(v=>`<option value="${v.id}">${esc(v.name)}</option>`).join('')}</select></div><div><label class="field-label">Flight Number</label><input name="flight_number" class="field" placeholder="SV123"></div><div><label class="field-label">Arrival Date</label><input name="arrival_date" type="date" class="field"></div><div><label class="field-label">Departure Date</label><input name="departure_date" type="date" class="field"></div><div><label class="field-label">Visa Buy Rate (PKR)</label><input name="buy_rate_pkr" type="number" step="0.01" min="0" class="field"></div><div><label class="field-label">Visa Sell Rate (PKR)</label><input name="sell_rate_pkr" type="number" step="0.01" min="0" class="field"></div><div><label class="field-label">Ticket Buy Rate (PKR)</label><input name="ticket_buy_rate_pkr" type="number" step="0.01" min="0" class="field"></div><div><label class="field-label">Ticket Sell Rate (PKR)</label><input name="ticket_sell_rate_pkr" type="number" step="0.01" min="0" class="field"></div></div><div id="bulkTransportBox"></div><div class="border border-indigo-200 bg-indigo-50/40 rounded-xl p-3 space-y-2"><h4 class="font-bold text-indigo-950 text-xs"><i class="fa-solid fa-paperclip mr-1"></i> Ticket Attachments (PDF only, max 10 MB)</h4><p class="text-[10px] text-slate-500">Use this when the same Arrival/Return ticket PDF applies to all selected bookings. It will replace any existing Ticket 1 / Ticket 2 file on each selected booking.</p><div class="grid grid-cols-1 md:grid-cols-2 gap-3"><div><label class="field-label">Ticket 1 (Arrival)</label><input id="bulk_ticket_1" type="file" accept="application/pdf,.pdf" class="w-full text-[11px]"></div><div><label class="field-label">Ticket 2 (Return)</label><input id="bulk_ticket_2" type="file" accept="application/pdf,.pdf" class="w-full text-[11px]"></div></div></div><div class="flex justify-end gap-2 pt-3 border-t"><button type="button" onclick="closeActiveModal()" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-semibold">Cancel</button><button type="submit" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold">Apply to Selected</button></div></form></div></div>`;
    renderBulkTransportBox(ids);
    document.getElementById('bulk_ticket_1').addEventListener('change',e=>validatePendingPdf(e.target));
    document.getElementById('bulk_ticket_2').addEventListener('change',e=>validatePendingPdf(e.target));
    document.getElementById('bulkBookingForm').addEventListener('submit',async event=>{
        event.preventDefault();
        const submitBtn=event.target.querySelector('button[type="submit"]');
        if(submitBtn){submitBtn.disabled=true;submitBtn.textContent='Applying...';}
        try{
            const payload=Object.fromEntries(new FormData(event.target).entries());
            payload.booking_ids=ids;
            const transport=collectBulkTransport();
            if(transport===false)return;
            if(transport)payload.transport=transport;
            const hasFieldUpdates=!!transport||Object.entries(payload).some(([k,v])=>k!=='booking_ids'&&String(v).trim()!=='');
            if(hasFieldUpdates){
                const response=await fetch('index.php?api=bulk_update_bookings',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':AST_CSRF},body:JSON.stringify(payload)});
                const result=await response.json();
                if(!result.success){alert(result.message||'Unable to update bookings.');return;}
            }
            const ticket1=document.getElementById('bulk_ticket_1').files?.[0];
            const ticket2=document.getElementById('bulk_ticket_2').files?.[0];
            for(const [type,file] of [['ticket_1',ticket1],['ticket_2',ticket2]]){
                if(!file)continue;
                const fd=new FormData();fd.append('booking_ids',JSON.stringify(ids));fd.append('type',type);fd.append('file',file);
                const r=await fetch('index.php?api=bulk_upload_booking_file',{method:'POST',headers:{'X-CSRF-Token':AST_CSRF},body:fd});
                const d=await r.json();
                if(!d.success){alert(`${type==='ticket_1'?'Ticket 1 (Arrival)':'Ticket 2 (Return)'} upload failed: ${d.message}`);return;}
            }
            closeActiveModal();location.reload();
        }finally{
            if(submitBtn){submitBtn.disabled=false;submitBtn.textContent='Apply to Selected';}
        }
    });
}
/**
 * Bulk Edit -> Transport: one transfer per ticked route for the whole group, booked under the chosen
 * Family Head, so the agent ledger is charged once per vehicle/route (not once per traveller).
 */
const BULK_TRANSPORT_ROUTES=[''];
function bulkRouteRow(route,checked=false){
    const off=checked?'':'disabled';
    return `<div class="bt-route grid grid-cols-12 gap-2 items-center ${checked?'':'opacity-50'}"><label class="col-span-3 flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" class="bt-on w-4 h-4 accent-sky-600" ${checked?'checked':''} onchange="bulkToggleRoute(this)"><input class="bt-name font-mono font-bold text-[11px] text-sky-900 bg-transparent w-full outline-none uppercase" value="${esc(route)}" placeholder="From-To (e.g. JED-MAK)"></label><input class="bt-type col-span-2 border border-slate-300 rounded-lg p-1.5 text-[11px]" placeholder="Default vehicle" ${off}><input type="number" step="0.01" min="0" class="bt-buy col-span-2 border border-slate-300 rounded-lg p-1.5 text-[11px] font-mono text-rose-600" placeholder="Default buy" ${off}><input type="number" step="0.01" min="0" class="bt-sell col-span-2 border border-slate-300 rounded-lg p-1.5 text-[11px] font-mono font-bold text-emerald-700" placeholder="Default sell" ${off}><input type="date" class="bt-date col-span-3 border border-slate-300 rounded-lg p-1.5 text-[11px]" title="Enter the service date for this route." ${off}></div>`;
}
function bulkToggleRoute(cb){const row=cb.closest('.bt-route');row.classList.toggle('opacity-50',!cb.checked);row.querySelectorAll('.bt-type,.bt-buy,.bt-sell,.bt-date').forEach(i=>i.disabled=!cb.checked);}
function bulkAddRoute(){document.getElementById('bt_routes').insertAdjacentHTML('beforeend',bulkRouteRow('',true));document.querySelector('#bt_routes .bt-route:last-child .bt-name').focus();}
function renderBulkTransportBox(ids){
    const members=ids.map(id=>{const el=document.querySelector(`.booking-select[value="${id}"]`);return {id,name:el?.dataset.name||('Booking #'+id),passport:el?.dataset.passport||''};});
    document.getElementById('bulkTransportBox').innerHTML=`<div class="border border-sky-200 bg-sky-50/40 rounded-xl p-3 space-y-2">
        <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="bt_enabled" class="w-4 h-4 accent-sky-600" onchange="document.getElementById('bt_fields').classList.toggle('hidden',!this.checked)"><span class="font-bold text-sky-950 text-xs"><i class="fa-solid fa-van-shuttle mr-1"></i> TRANSPORT <span class="font-normal text-slate-500">(Optional — one transfer per route for the whole group)</span></span></label>
        <div id="bt_fields" class="hidden space-y-2">
            <div><label class="field-label">Family Head <span class="text-rose-600">*</span></label><select id="bt_head" class="field"><option value="">Select family head...</option>${members.map(m=>`<option value="${m.id}">${esc(m.name)}${m.passport?' — '+esc(m.passport):''}</option>`).join('')}</select><p class="text-[10px] text-slate-500 mt-1">Charged to the agent ledger <b>once per route</b> under this name (PAX ${members.length}) — not separately for each traveller.</p></div>
            <div class="grid grid-cols-3 gap-2"><div><label class="field-label">Transport Type</label><input id="bt_type" class="field" value="CAR"></div><div><label class="field-label">Buy Rate (per transfer)</label><input id="bt_buy" type="number" step="0.01" min="0" class="field" placeholder="0.00"></div><div><label class="field-label">Sell Rate (per transfer)</label><input id="bt_sell" type="number" step="0.01" min="0" class="field" placeholder="0.00"></div></div>
            <div class="bg-white border border-sky-100 rounded-lg p-2 space-y-1.5"><div class="flex justify-between items-center"><span class="text-[11px] font-bold text-sky-900"><i class="fa-solid fa-route text-sky-600 mr-1"></i> Route-wise Transport <span class="font-normal text-slate-500">(tick routes · blank = default above)</span></span><button type="button" onclick="bulkAddRoute()" class="text-[10px] font-semibold text-sky-700 bg-sky-50 hover:bg-sky-100 border border-sky-200 rounded-md px-2 py-1"><i class="fa-solid fa-plus mr-1"></i> Add Route</button></div>
                <div class="grid grid-cols-12 gap-2 text-[9px] uppercase font-bold text-slate-400"><span class="col-span-3">Route</span><span class="col-span-2">Vehicle</span><span class="col-span-2">Buy</span><span class="col-span-2">Sell</span><span class="col-span-3">Date</span></div>
                <div id="bt_routes" class="space-y-1.5">${BULK_TRANSPORT_ROUTES.map(r=>bulkRouteRow(r)).join('')}</div></div>
        </div></div>`;
}
/** Returns null (transport off), false (invalid, already alerted) or the transport payload. */
function collectBulkTransport(){
    if(!document.getElementById('bt_enabled')?.checked)return null;
    const headId=Number(document.getElementById('bt_head').value||0);
    if(!headId){alert('Select the Family Head for the transport.');return false;}
    const routes=Array.from(document.querySelectorAll('#bt_routes .bt-route')).filter(row=>row.querySelector('.bt-on').checked).map(row=>({
        route:row.querySelector('.bt-name').value.trim().toUpperCase(),type:row.querySelector('.bt-type').value.trim(),
        buy:row.querySelector('.bt-buy').value.trim(),sell:row.querySelector('.bt-sell').value.trim(),date:row.querySelector('.bt-date').value
    })).filter(r=>r.route);
    if(!routes.length){alert('Tick at least one transport route.');return false;}
    return {enabled:1,head_id:headId,type:document.getElementById('bt_type').value.trim(),buy:document.getElementById('bt_buy').value.trim(),sell:document.getElementById('bt_sell').value.trim(),routes};
}
async function editBooking(id){const r=await fetch(`index.php?api=get_booking&id=${id}`);const d=await r.json();if(d.success)openBookingModal(d.booking);else alert(d.message||'Failed to load booking.');}
async function deleteMasterBooking(id){if(!confirm('Delete this booking? It will be soft-deleted and removed from ledgers/payables.'))return;const r=await fetch('index.php?api=delete_booking',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':AST_CSRF},body:JSON.stringify({id})});const d=await r.json();if(d.success)location.reload();else alert(d.message);}
function exportParams(format){const p=new URLSearchParams({api:'export_bookings',format:format});for(const [k,v] of Object.entries(<?= json_encode($filters) ?>)){if(v!==null&&v!=='')p.set(k,v);}return p.toString();}
function exportBookings(format){if(format!=='csv')return;window.location.href='index.php?'+exportParams(format);}
function printBookings(){const p=new URLSearchParams({page:'print_bookings'});for(const [k,v] of Object.entries(<?= json_encode($filters) ?>)){if(v!==null&&v!=='')p.set(k,v);}window.open('index.php?'+p.toString(),'astBookingPrint','width=1100,height=800,scrollbars=yes,resizable=yes');}
function exportSingleBooking(id){window.open('index.php?page=print_bookings&booking_id='+encodeURIComponent(id),'astBookingPrint','width=1100,height=800,scrollbars=yes,resizable=yes');}

function openImportModal(){document.getElementById('dynamicModalContainer').innerHTML=`<div class="fixed inset-0 bg-slate-900/70 z-50 flex items-center justify-center p-3 overflow-y-auto"><div class="bg-white rounded-2xl max-w-6xl w-full p-5 shadow-2xl my-5"><div class="flex justify-between items-center border-b pb-3 mb-4"><div><h3 class="font-bold">Import Bookings</h3><p class="text-[11px] text-slate-500">CSV/XLSX • missing columns become empty • dates: YYYY-MM-DD or DD/MM/YYYY • numeric commas are accepted.</p></div><button onclick="closeActiveModal()" class="text-slate-400"><i class="fa-solid fa-xmark"></i></button></div><div class="space-y-4"><div class="border-2 border-dashed border-slate-300 rounded-xl p-6 text-center"><input id="importFile" type="file" accept=".csv,.xlsx" class="text-xs"><button onclick="stageImport()" class="ml-2 px-4 py-2 bg-slate-800 text-white rounded-lg text-xs font-bold">Upload & Preview</button></div><div id="importPreview" class="hidden space-y-3"></div></div></div></div>`;}
async function stageImport(){
    const f=document.getElementById('importFile')?.files?.[0];
    if(!f){alert('Select a CSV or XLSX file.');return;}
    if(f.size>10*1024*1024){alert('Import file is too large. Maximum size is 10 MB.');return;}
    const btn=document.querySelector('#importFile')?.nextElementSibling;
    if(btn){btn.disabled=true;btn.textContent='Uploading...';}
    try{
        const fd=new FormData();fd.append('file',f);
        const r=await fetch('index.php?api=preview_booking_import',{method:'POST',headers:{'X-CSRF-Token':AST_CSRF},body:fd});
        const text=await r.text();
        let d;try{d=JSON.parse(text);}catch(e){throw new Error(text.replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim()||'Server returned an invalid response. Check PHP error log.');}
        if(!d.success){alert(d.message||'Unable to preview import file.');return;}
        renderImportPreview(d);
    }catch(e){alert('Import preview failed: '+(e.message||e));}
    finally{if(btn){btn.disabled=false;btn.textContent='Upload & Preview';}}
}
function renderImportPreview(d){const box=document.getElementById('importPreview');box.classList.remove('hidden');const opts=AST_CANONICAL.map((h,i)=>`<option value="${esc(AST_CANONICAL_MAP[h])}">${esc(h)}</option>`).join('');let html=`<div class="bg-slate-50 rounded-xl p-3"><div class="font-bold text-xs mb-2">Column Mapping</div><div class="overflow-x-auto"><table class="w-full text-[10px]"><thead><tr><th class="p-2 text-left">Source Header</th><th class="p-2 text-left">Map To Canonical Field</th></tr></thead><tbody>`;for(const h of d.headers){const m=d.mapping?.[h]||'';html+=`<tr><td class="p-2 font-semibold">${esc(h)}</td><td class="p-2"><select class="import-map w-full border rounded p-1" data-header="${esc(h)}"><option value="">Ignore / Custom Field</option>${AST_CANONICAL.map(c=>{const key=AST_CANONICAL_MAP[c];return `<option value="${key}" ${m===key?'selected':''}>${esc(c)}</option>`}).join('')}</select></td></tr>`;}html+=`</tbody></table></div></div><div><div class="font-bold text-xs mb-2">First 5 Rows</div><div class="overflow-x-auto border rounded-xl"><table class="w-full text-[10px]"><thead><tr>${d.headers.map(h=>`<th class="p-2 bg-slate-50 text-left">${esc(h)}</th>`).join('')}</tr></thead><tbody>${d.preview.map(r=>`<tr>${d.headers.map((_,i)=>`<td class="p-2 border-t">${esc(r[i]||'')}</td>`).join('')}</tr>`).join('')}</tbody></table></div></div><div class="p-3 rounded-xl ${d.validation?.length?'bg-rose-50 text-rose-800':'bg-emerald-50 text-emerald-800'} text-xs"><b>Validation preview:</b> ${d.validation?.length?d.validation.length+' invalid preview row(s).':'No validation errors in the preview rows.'}</div><div class="flex justify-end gap-2"><button onclick="closeActiveModal()" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-semibold">Cancel</button><button onclick="confirmImport('${d.token}')" class="px-5 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">Confirm Import</button></div>`;box.innerHTML=html;}
async function confirmImport(token){const mapping={};document.querySelectorAll('.import-map').forEach(s=>mapping[s.dataset.header]=s.value);const r=await fetch('index.php?api=confirm_booking_import',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':AST_CSRF},body:JSON.stringify({token,mapping})});const d=await r.json();if(d.success){alert(`Import complete. Created: ${d.created}, Updated: ${d.updated}, Failed: ${d.failed}.`);location.reload();}else{let msg=d.message||'Import failed.';if(d.failed_csv_base64){const a=document.createElement('a');a.href='data:text/csv;base64,'+d.failed_csv_base64;a.download='failed-booking-import.csv';a.textContent='Download failed rows CSV';a.className='block text-indigo-700 font-bold mt-2';document.getElementById('importPreview').appendChild(a);}alert(msg);}}
</script>
<style>.field{width:100%;border:1px solid #DCE5ED;border-radius:.65rem;padding:.45rem .6rem;background:#fff;outline:0;font-size:12px}.field:focus{box-shadow:0 0 0 2px #EEEDF7}.field-label{display:block;font-size:10px;font-weight:700;color:#4A5170;margin-bottom:3px}</style>
<script src="assets/js/smart_master_booking.js?v=<?= (int)@filemtime(__DIR__ . '/../../assets/js/smart_master_booking.js') ?>"></script>
<script src="assets/js/smart_visa_upload.js?v=<?= (int)@filemtime(__DIR__ . '/../../assets/js/smart_visa_upload.js') ?>"></script>
