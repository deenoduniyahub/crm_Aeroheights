<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/OperationsController.php';

/** WhatsApp "Send" button: one link for Makkah/Madinah, or a small chooser when the city is unknown. */
function renderWhatsAppSend(array $links): string {
    if (!$links) return '';
    $btn = 'inline-flex items-center gap-1 btn-whatsapp hover:opacity-90 text-white text-[10px] font-bold px-2.5 py-1 rounded-lg shadow-sm transition whitespace-nowrap';
    if (count($links) === 1) {
        $l = $links[0];
        return '<a href="' . htmlspecialchars($l['url']) . '" target="_blank" rel="noopener" class="' . $btn . ' no-print" title="Send to ' . htmlspecialchars($l['team'] . ' team (' . $l['number'] . ')') . ' on WhatsApp"><i class="fa-brands fa-whatsapp text-xs"></i> Send ' . htmlspecialchars($l['team']) . '</a>';
    }
    $items = '';
    foreach ($links as $l) {
        $items .= '<a href="' . htmlspecialchars($l['url']) . '" target="_blank" rel="noopener" class="block px-3 py-1.5 hover:bg-emerald-50 text-[11px] font-semibold text-slate-700 whitespace-nowrap"><i class="fa-brands fa-whatsapp text-[#25D366] mr-1"></i> ' . htmlspecialchars($l['team']) . ' <span class="text-slate-400 font-normal">' . htmlspecialchars($l['number']) . '</span></a>';
    }
    return '<details class="relative inline-block no-print"><summary class="' . $btn . ' cursor-pointer list-none" title="City unknown - choose the team"><i class="fa-brands fa-whatsapp text-xs"></i> Send <i class="fa-solid fa-caret-down"></i></summary><div class="absolute right-0 z-20 mt-1 bg-white border border-slate-200 rounded-lg shadow-lg py-1">' . $items . '</div></details>';
}

$activeDate   = $_GET['date'] ?? date('Y-m-d');
$manifest     = OperationsController::getManifest($activeDate);
$todayDate    = date('Y-m-d');
$tomorrowDate = date('Y-m-d', strtotime('+1 day'));
?>

<main class="md:col-span-9 space-y-6">

    <!-- Operations Filter & Quick Switch Bar -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col lg:flex-row items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2">
                <h2 class="text-base font-bold text-slate-800">Operational Run-Sheet & Manifest</h2>
                <?php if ($activeDate === $todayDate): ?>
                    <span class="bg-blue-100 text-blue-700 text-xs font-bold px-2.5 py-0.5 rounded-full">TODAY</span>
                <?php elseif ($activeDate === $tomorrowDate): ?>
                    <span class="bg-amber-100 text-amber-800 text-xs font-bold px-2.5 py-0.5 rounded-full">TOMORROW</span>
                <?php else: ?>
                    <span class="bg-slate-100 text-slate-700 text-xs font-bold px-2.5 py-0.5 rounded-full"><?= date('D, d M Y', strtotime($activeDate)) ?></span>
                <?php endif; ?>
            </div>
            <p class="text-xs text-slate-500 mt-0.5">Real-time daily schedule of arriving flights, transport transfers, and hotel transitions.</p>
        </div>

        <!-- Quick Switch Buttons & Custom Datepicker -->
        <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto">
            <!-- Today Button -->
            <a href="index.php?page=operations&date=<?= $todayDate ?>" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center <?= $activeDate === $todayDate ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
                <i class="fa-solid fa-sun mr-1.5"></i> Today
            </a>

            <!-- Tomorrow Button -->
            <a href="index.php?page=operations&date=<?= $tomorrowDate ?>" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center <?= $activeDate === $tomorrowDate ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
                <i class="fa-solid fa-forward mr-1.5"></i> Tomorrow Schedule
            </a>

            <!-- Custom Date Selector Form -->
            <form method="GET" action="index.php" class="flex items-center gap-1.5">
                <input type="hidden" name="page" value="operations">
                <input type="date" name="date" value="<?= htmlspecialchars($activeDate) ?>" class="border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-medium focus:ring-2 focus:ring-blue-500 outline-none bg-slate-50">
                <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold px-3 py-2 rounded-xl transition">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </form>

            <!-- Manifest Print Trigger -->
            <button onclick="window.print()" class="no-print bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 transition" title="Print Run-Sheet">
                <i class="fa-solid fa-print"></i>
            </button>
        </div>
    </div>

    <!-- Summary KPI Badges for Selected Date -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-base">
                <i class="fa-solid fa-plane-arrival"></i>
            </div>
            <div>
                <span class="text-[11px] font-semibold text-slate-400 uppercase">Arrivals</span>
                <div class="text-lg font-bold text-slate-800"><?= $manifest['total_arrivals_pax'] ?> <span class="text-xs font-normal text-slate-400">PAX</span></div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-base">
                <i class="fa-solid fa-van-shuttle"></i>
            </div>
            <div>
                <span class="text-[11px] font-semibold text-slate-400 uppercase">Transfers</span>
                <div class="text-lg font-bold text-slate-800"><?= $manifest['total_transports'] ?> <span class="text-xs font-normal text-slate-400">Trips</span></div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-base">
                <i class="fa-solid fa-door-open"></i>
            </div>
            <div>
                <span class="text-[11px] font-semibold text-slate-400 uppercase">Check-Ins</span>
                <div class="text-lg font-bold text-slate-800"><?= $manifest['total_checkin_pax'] ?> <span class="text-xs font-normal text-slate-400">PAX</span></div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold text-base">
                <i class="fa-solid fa-door-closed"></i>
            </div>
            <div>
                <span class="text-[11px] font-semibold text-slate-400 uppercase">Check-Outs</span>
                <div class="text-lg font-bold text-slate-800"><?= $manifest['total_checkout_pax'] ?> <span class="text-xs font-normal text-slate-400">PAX</span></div>
            </div>
        </div>
    </div>

    <!-- 1. FLIGHT ARRIVALS SCHEDULE -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="bg-indigo-50/70 border-b border-indigo-100 px-5 py-3.5 flex items-center justify-between">
            <div class="flex items-center space-x-2.5">
                <div class="w-7 h-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-xs">
                    <i class="fa-solid fa-plane-arrival"></i>
                </div>
                <h3 class="font-bold text-indigo-950 text-sm">Scheduled Flight Arrivals</h3>
            </div>
            <span class="text-xs font-bold bg-indigo-200/80 text-indigo-900 px-2.5 py-0.5 rounded-full"><?= count($manifest['arrivals']) ?> Registered</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-700 border-b border-slate-200 uppercase font-bold text-[11px]">
                    <tr>
                        <th class="p-3.5">Flight #</th>
                        <th class="p-3.5">From / Return Route</th>
                        <th class="p-3.5">Reached</th>
                        <th class="p-3.5">Mutamer Name</th>
                        <th class="p-3.5">Passport</th>
                        <th class="p-3.5">Booking Agent</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($manifest['arrivals'])): ?>
                        <tr>
                            <td colspan="6" class="p-6 text-center text-slate-400 font-medium">
                                <i class="fa-regular fa-calendar-xmark text-lg mb-1 block"></i>
                                No arriving flights scheduled for <?= date('d-M-Y', strtotime($activeDate)) ?>.
                            </td>
                        </tr>
                    <?php else: foreach ($manifest['arrivals'] as $r): ?>
                        <tr class="hover:bg-indigo-50/30 transition">
                            <td class="p-3.5">
                                <div class="font-mono font-bold text-indigo-600"><?= htmlspecialchars($r['flight_number'] ?: 'DIRECT/TBA') ?></div>
                                <?php if (!empty($r['return_flight_number'])): ?>
                                    <div class="mt-1 whitespace-nowrap text-[10px] font-mono font-semibold text-rose-600">Return · <?= htmlspecialchars($r['return_flight_number']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-3.5 font-medium text-slate-700">
                                <?php foreach ([
                                    ['label' => 'From', 'route' => $r['from_route'] ?? '', 'date' => $r['from_date'] ?? '', 'legs' => $r['from_legs'] ?? [], 'color' => 'indigo'],
                                    ['label' => 'Return', 'route' => $r['return_route'] ?? '', 'date' => $r['return_date'] ?? '', 'legs' => $r['return_legs'] ?? [], 'color' => 'rose'],
                                ] as $journey): ?>
                                    <?php if (!empty($journey['route'])): ?>
                                        <details class="group <?= $journey['label']==='Return'?'mt-1':'' ?>">
                                            <summary class="flex max-w-60 cursor-pointer list-none items-center gap-1 text-[10px] font-semibold <?= $journey['color']==='rose'?'text-rose-700':'text-indigo-700' ?> [&::-webkit-details-marker]:hidden" title="Show <?= htmlspecialchars(strtolower($journey['label'])) ?> flight times">
                                                <span class="shrink-0"><?= htmlspecialchars($journey['label']) ?> · <?= htmlspecialchars($journey['date'] ? date('d-m-y', strtotime($journey['date'])) : '-') ?></span>
                                                <span class="truncate"><?= htmlspecialchars($journey['route']) ?></span>
                                                <i class="fa-solid fa-chevron-down shrink-0 text-[9px] transition-transform group-open:rotate-180"></i>
                                            </summary>
                                            <div class="mt-1.5 min-w-56 space-y-1 rounded-lg border <?= $journey['color']==='rose'?'border-rose-100 bg-rose-50/70':'border-indigo-100 bg-indigo-50/70' ?> p-2 text-[10px] leading-4 text-slate-500">
                                                <?php foreach ($journey['legs'] as $leg): ?>
                                                    <div class="border-l-2 <?= $journey['color']==='rose'?'border-rose-200':'border-indigo-200' ?> pl-2">
                                                        <div class="font-semibold text-slate-700"><?= htmlspecialchars((string)($leg['from'] ?? '')) ?> → <?= htmlspecialchars((string)($leg['to'] ?? '')) ?><?= !empty($leg['flight'])?' · '.htmlspecialchars((string)$leg['flight']):'' ?></div>
                                                        <div>Depart <?= htmlspecialchars(!empty($leg['dep_date'])?date('d-m-y',strtotime($leg['dep_date'])):'-') ?> <?= htmlspecialchars((string)($leg['dep_time'] ?? '')) ?></div>
                                                        <div>Reached <?= htmlspecialchars(!empty($leg['arr_date'])?date('d-m-y',strtotime($leg['arr_date'])):'-') ?> <?= htmlspecialchars((string)($leg['arr_time'] ?? '')) ?></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </details>
                                    <?php elseif ($journey['label']==='From'): ?>
                                        <div class="text-[10px] text-slate-500"><?= htmlspecialchars($r['flight_out_from'] ? $r['flight_out_from'].' → '.$r['flight_out_to'] : '-') ?></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </td>
                            <td class="p-3.5 font-mono font-bold text-slate-900"><?= htmlspecialchars($r['flight_out_arr_time'] ? date('h:i A', strtotime($r['flight_out_arr_time'])) : 'Scheduled') ?></td>
                            <td class="p-3.5 font-semibold text-slate-800"><?= htmlspecialchars($r['passenger_name']) ?></td>
                            <td class="p-3.5 font-mono font-bold text-slate-700"><?= htmlspecialchars($r['passport_number']) ?></td>
                            <td class="p-3.5 font-semibold text-slate-700"><?= htmlspecialchars($r['agent_name']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2. TRANSPORT PICKUPS & DISPATCHES -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="bg-emerald-50/70 border-b border-emerald-100 px-5 py-3.5 flex items-center justify-between">
            <div class="flex items-center space-x-2.5">
                <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-xs">
                    <i class="fa-solid fa-van-shuttle"></i>
                </div>
                <h3 class="font-bold text-emerald-950 text-sm">Transport Pickup & Transfer Log</h3>
            </div>
            <span class="text-xs font-bold bg-emerald-200/80 text-emerald-900 px-2.5 py-0.5 rounded-full"><?= count($manifest['transports']) ?> Dispatches</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-700 border-b border-slate-200 uppercase font-bold text-[11px]">
                    <tr>
                        <th class="p-3.5">Pickup Time</th>
                        <th class="p-3.5">Passenger / Lead</th>
                        <th class="p-3.5">Vehicle</th>
                        <th class="p-3.5">Terminal</th>
                        <th class="p-3.5">Route</th>
                        <th class="p-3.5">Driver / Phone</th>
                        <th class="p-3.5">Agent</th>
                        <th class="p-3.5 text-right no-print">WhatsApp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($manifest['transports'])): ?>
                        <tr>
                            <td colspan="8" class="p-6 text-center text-slate-400 font-medium">
                                <i class="fa-regular fa-clock text-lg mb-1 block"></i>
                                No transport pickups scheduled for <?= date('d-M-Y', strtotime($activeDate)) ?>.
                            </td>
                        </tr>
                    <?php else: foreach ($manifest['transports'] as $t): ?>
                        <tr class="hover:bg-emerald-50/30 transition">
                            <td class="p-3.5 font-mono font-bold text-emerald-700 text-xs"><?= htmlspecialchars(date('h:i A', strtotime($t['pickup_time']))) ?></td>
                            <td class="p-3.5 font-semibold text-slate-800"><?= htmlspecialchars($t['pax_name']) ?> <span class="text-[10px] text-slate-400">(<?= $t['pax_count'] ?> PAX)</span></td>
                            <td class="p-3.5 font-medium"><span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-md font-semibold"><?= htmlspecialchars($t['vehicle_type']) ?></span></td>
                            <td class="p-3.5 text-slate-600"><?= htmlspecialchars($t['terminal']) ?></td>
                            <td class="p-3.5 font-bold text-slate-700">
                                <?= htmlspecialchars($t['route_details']) ?>
                                <?php if (!empty($t['auto_generated'])): ?><span class="ml-1 bg-sky-100 text-sky-700 px-1.5 py-0.5 rounded text-[9px] font-bold align-middle" title="Auto-generated from Only Hotel Booking itinerary">AUTO</span><?php endif; ?>
                                
                            </td>
                            <td class="p-3.5 font-mono"><?= htmlspecialchars($t['driver_name'] ? $t['driver_name'] . ' (' . $t['driver_contact'] . ')' : 'Pending Dispatch') ?></td>
                            <td class="p-3.5 text-slate-600"><?= htmlspecialchars($t['agent_name']) ?></td>
                            <td class="p-3.5 text-right no-print"><?= renderWhatsAppSend(OperationsController::whatsAppTransport($t)) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. HOTEL CHECK-INS & CHECK-OUTS -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Check-Ins -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="bg-amber-50/70 border-b border-amber-100 px-4 py-3 flex items-center justify-between">
                <h3 class="font-bold text-amber-950 text-xs flex items-center">
                    <i class="fa-solid fa-door-open mr-2 text-amber-600"></i> Hotel Check-Ins
                </h3>
                <span class="text-[10px] font-bold bg-amber-200 text-amber-900 px-2 py-0.5 rounded-full"><?= count($manifest['checkins']) ?></span>
            </div>
            <div class="p-3 divide-y divide-slate-100 text-xs max-h-72 overflow-y-auto manifest-scroll">
                <?php if (empty($manifest['checkins'])): ?>
                    <div class="p-4 text-center text-slate-400">No hotel check-ins scheduled.</div>
                <?php else: foreach ($manifest['checkins'] as $ci): ?>
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <div class="font-bold text-slate-800"><?= htmlspecialchars($ci['city']) ?> - <?= htmlspecialchars($ci['hotel_name']) ?></div>
                            <div class="text-[11px] text-slate-500"><?= htmlspecialchars($ci['family_head']) ?> (<?= $ci['total_pax'] ?> PAX / <?= $ci['total_beds'] ?> Beds)</div>
                            <div class="text-[10px] text-indigo-600 font-semibold">Agent: <?= htmlspecialchars($ci['agent_name'] ?? '') ?></div>
                        </div>
                        <div class="text-right">
                            <span class="font-mono font-bold text-amber-700 block"><?= htmlspecialchars($ci['voucher_no']) ?></span>
                            <span class="text-[10px] text-slate-400 font-medium"><?= $ci['nights'] ?> Nights | Check-in <?= htmlspecialchars($ci['checkin_date']) ?></span>
                            <div class="mt-1"><?= renderWhatsAppSend(OperationsController::whatsAppCheckin($ci)) ?></div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Check-Outs -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="bg-rose-50/70 border-b border-rose-100 px-4 py-3 flex items-center justify-between">
                <h3 class="font-bold text-rose-950 text-xs flex items-center">
                    <i class="fa-solid fa-door-closed mr-2 text-rose-600"></i> Hotel Check-Outs
                </h3>
                <span class="text-[10px] font-bold bg-rose-200 text-rose-900 px-2 py-0.5 rounded-full"><?= count($manifest['checkouts']) ?></span>
            </div>
            <div class="p-3 divide-y divide-slate-100 text-xs max-h-72 overflow-y-auto manifest-scroll">
                <?php if (empty($manifest['checkouts'])): ?>
                    <div class="p-4 text-center text-slate-400">No hotel check-outs scheduled.</div>
                <?php else: foreach ($manifest['checkouts'] as $co): ?>
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <div class="font-bold text-slate-800"><?= htmlspecialchars($co['city']) ?> - <?= htmlspecialchars($co['hotel_name']) ?></div>
                            <div class="text-[11px] text-slate-500"><?= htmlspecialchars($co['family_head']) ?> (<?= $co['total_pax'] ?> PAX)</div>
                            <div class="text-[10px] text-indigo-600 font-semibold">Agent: <?= htmlspecialchars($co['agent_name'] ?? '') ?></div>
                        </div>
                        <div class="text-right">
                            <span class="font-mono font-bold text-rose-700 block"><?= htmlspecialchars($co['voucher_no']) ?></span>
                            <span class="text-[10px] text-slate-400 font-medium">Checkout <?= htmlspecialchars($co['checkout_date']) ?></span>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

    </div>

</main>


<style>
    @media print {
        .no-print { display: none !important; }
        /* Expand scrollable lists so every entry prints, not just the visible part */
        .manifest-scroll {
            max-height: none !important;
            overflow: visible !important;
        }
        .manifest-scroll > div {
            break-inside: avoid;
            page-break-inside: avoid;
        }
    }
</style>
