<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/LedgerController.php';
require_once __DIR__ . '/../../config/Database.php';

if (!function_exists('formatAgentPrintDate')) {
    function formatAgentPrintDate($value): string {
        if ($value === null || trim((string)$value) === '') return '-';
        $timestamp = strtotime((string)$value);
        return $timestamp !== false ? date('d-m-Y', $timestamp) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$agentId = (int)($_GET['agent_id'] ?? 1);
$data = LedgerController::getAgentLedger($agentId);
$agent = $data['agent'] ?? null;
$ledger = $data['ledger'] ?? [];

if (!$agent) {
    die('<div style="font-family:Jost,Arial,sans-serif;padding:30px;text-align:center;color:#E0475B;">Agent record not found.</div>');
}

$agencyLogo = Database::fetchValue("SELECT setting_value FROM system_settings WHERE setting_key = 'agency_logo'") ?: 'assets/img/logo.png';
$agencyPhone = Database::fetchValue("SELECT setting_value FROM system_settings WHERE setting_key = 'agency_phone'") ?: '+92 303 5137777';
$bankDetails = array_values(array_filter(array_map('trim', explode("
", (string)(Database::fetchValue("SELECT setting_value FROM system_settings WHERE setting_key = 'bank_details'") ?: '')))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Statement — <?= htmlspecialchars($agent['name']) ?> (<?= date('d-M-Y') ?>)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="assets/js/brand-tailwind.js?v=<?= (int)@filemtime(__DIR__ . '/../../assets/js/brand-tailwind.js') ?>"></script>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Jost', Arial, sans-serif; }
        .font-mono { font-family: 'Jost', Arial, sans-serif; font-variant-numeric: tabular-nums; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; font-size: 10.5pt; }
            .print-border { border: 1px solid #DCE5ED !important; }
        }
    </style>
</head>
<body class="bg-slate-100 p-4 sm:p-8">

    <div class="max-w-4xl mx-auto bg-white border border-slate-300 p-8 rounded-2xl shadow-xl space-y-6 print-border">
        
        <!-- Header with Agency Logo -->
        <div class="flex justify-between items-start border-b-2 border-slate-900 pb-4">
            <div class="flex flex-col items-start gap-1.5">
                <img src="<?= htmlspecialchars($agencyLogo) ?>" alt="Agency Logo" class="h-14 w-auto object-contain" onerror="this.style.display='none'">
                <p class="text-[11px] text-slate-600"><strong>Phone / WhatsApp:</strong> <?= htmlspecialchars($agencyPhone) ?></p>
            </div>
            <div class="text-right">
                <span class="text-lg font-black text-rose-600 uppercase tracking-wider block">Statement of Account</span>
                <span class="text-base font-bold text-slate-900"><?= htmlspecialchars($agent['name']) ?></span>
                <p class="text-xs text-slate-500"><?= htmlspecialchars($agent['company_name'] ?: '') ?> (<?= htmlspecialchars($agent['city']) ?>)</p>
                <p class="text-[11px] text-slate-400 mt-1"><strong>Statement Date:</strong> <?= date('d-M-Y') ?></p>
            </div>
        </div>

        <!-- Ledger Statement Table (Displaying Rounded Values) -->
        <div>
            <table class="w-full text-xs text-left border border-slate-300">
                <thead class="bg-slate-800 text-white uppercase font-bold text-[10px]">
                    <tr>
                        <th class="p-2.5 border border-slate-300">Date</th>
                        <th class="p-2.5 border border-slate-300">Flight</th>
                        <th class="p-2.5 border border-slate-300">Arrival</th>
                        <th class="p-2.5 border border-slate-300">Departure</th>
                        <th class="p-2.5 border border-slate-300">Mutamer / Description</th>
                        <th class="p-2.5 border border-slate-300">Passport #</th>
                        <th class="p-2.5 border border-slate-300 text-right">Debit (PKR)</th>
                        <th class="p-2.5 border border-slate-300 text-right">Credit (PKR)</th>
                        <th class="p-2.5 border border-slate-300 text-right">Balance (PKR)</th>
                    </tr>
                </thead>
                <tbody class="font-mono">
                    <?php if (empty($ledger)): ?>
                        <tr><td colspan="10" class="p-4 text-center text-slate-400 font-sans">No transaction entries found on record.</td></tr>
                    <?php else: foreach ($ledger as $row):
                        $recordType = $row['record_type'] ?? '';
                        $isHotel = $recordType === 'hotel';
                        $isTransport = $recordType === 'transport';
                        $rowClass = $row['credit_pkr'] > 0
                            ? 'bg-emerald-50/50'
                            : ($isHotel ? 'bg-amber-50' : ($isTransport ? 'bg-sky-50' : ''));
                    ?>
                        <tr class="<?= $rowClass ?>">
                            <td class="p-2 border border-slate-300 font-sans text-slate-600"><?= formatAgentPrintDate($row['entry_date']) ?></td>
                            <td class="p-2 border border-slate-300 font-sans font-bold text-slate-800"><?= htmlspecialchars($row['flight_number'] ?: '-') ?></td>
                            <td class="p-2 border border-slate-300 font-sans text-slate-600"><?= formatAgentPrintDate($row['arrival_date'] ?? null) ?></td>
                            <td class="p-2 border border-slate-300 font-sans text-slate-600"><?= formatAgentPrintDate($row['departure_date'] ?? null) ?></td>
                            <td class="p-2 border border-slate-300 font-sans font-medium text-slate-900">
                                <?php if ($isHotel): ?><span style="background:#FFE9A8;color:#7D5D00;padding:2px 5px;border-radius:4px;font-size:8px;font-weight:800;text-transform:uppercase;">Hotel</span><?php endif; ?>
                                <?php if ($isTransport): ?><span style="background:#B5D6F6;color:#285A9B;padding:2px 5px;border-radius:4px;font-size:8px;font-weight:800;text-transform:uppercase;">Transport</span><?php endif; ?>
                                <?php if ($isTransport && !empty($row['hotel_names'])): ?><b style="color:#285A9B;"><?= htmlspecialchars($row['hotel_names']) ?></b> — <?php endif; ?>
                                <?= htmlspecialchars($row['passenger_name']) ?>
                                <?php if ($isHotel && !empty($row['hotel_names'])): ?><span class="font-sans text-slate-500"> (<?= htmlspecialchars($row['hotel_names']) ?>)</span><?php endif; ?>
                            </td>
                            <td class="p-2 border border-slate-300 font-bold"><?= htmlspecialchars($row['passport_number'] ?: '-') ?></td>
                            <td class="p-2 border border-slate-300 text-right text-rose-600 font-bold"><?= $row['debit_pkr'] > 0 ? number_format((float)$row['debit_pkr'], 0) : '-' ?></td>
                            <td class="p-2 border border-slate-300 text-right text-emerald-600 font-bold"><?= $row['credit_pkr'] > 0 ? number_format((float)$row['credit_pkr'], 0) : '-' ?></td>
                            <td class="p-2 border border-slate-300 text-right font-black text-slate-900"><?= number_format((float)$row['balance_pkr'], 0) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot class="bg-slate-100 font-bold">
                    <tr>
                        <td colspan="8" class="p-3 border border-slate-300 text-right font-sans text-xs">Closing Outstanding Balance:</td>
                        <td class="p-3 border border-slate-300 text-right font-mono font-black text-sm text-rose-700">
                            <?= number_format((float)$data['current_balance_pkr'], 0) ?> PKR
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Official Bank Account Details for Remittance -->
        <?php if ($bankDetails): ?>
        <div class="border border-slate-300 p-4 rounded-xl bg-slate-50 text-xs text-slate-600 space-y-1.5">
            <h4 class="font-bold uppercase text-[10px] text-slate-500">Designated Bank Accounts for Remittance:</h4>
            <?php foreach ($bankDetails as $bankLine): ?><p><?= htmlspecialchars($bankLine) ?></p><?php endforeach; ?>
            <p class="text-[10px] text-slate-400 mt-1">Please share the deposit slip on WhatsApp (+92 303 5137777) immediately after transfer.</p>
        </div>
        <?php endif; ?>

        <!-- Print Action -->
        <div class="no-print flex justify-end gap-2 pt-4 border-t">
            <button onclick="window.close()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 font-semibold rounded-lg text-xs transition">Close</button>
            <button onclick="window.print()" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-xs transition shadow-md">
                <i class="fa-solid fa-print mr-1"></i> Print Statement
            </button>
        </div>

    </div>

</body>
</html>