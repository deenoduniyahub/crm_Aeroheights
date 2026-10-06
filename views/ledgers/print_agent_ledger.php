<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/LedgerController.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

if (!function_exists('formatPrintDate')) {
    function formatPrintDate($value): string {
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
        return $ts !== false ? date('d-m-Y', $ts) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$agentId = !empty($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
if ($agentId <= 0) {
    echo "Invalid Agent ID.";
    exit;
}

$ledgerData = LedgerController::getAgentLedger($agentId);
$agent = $ledgerData['agent'] ?? null;
$entries = $ledgerData['ledger'] ?? [];
$currentBalance = $ledgerData['current_balance_sar'] ?? 0.00;

if (!$agent) {
    echo "Agent not found.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Agent Statement - <?= htmlspecialchars($agent['company_name'] ?? $agent['name']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; color: #1e293b; }
        .header { display: flex; justify-content: space-between; align-items: center; border-b: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 15px; }
        .agency-title { font-size: 18px; font-weight: bold; color: #0f172a; text-transform: uppercase; }
        .sub-title { font-size: 12px; color: #64748b; }
        .meta-box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; }
        .meta-item { line-height: 1.4; }
        .meta-item strong { color: #334155; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background-color: #f1f5f9; text-transform: uppercase; font-size: 9px; color: #334155; }
        .text-right { text-align: right; }
        .font-mono { font-family: monospace; }
        .balance-card { text-align: right; font-size: 13px; font-weight: bold; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 15px;">
        <button onclick="window.print()" style="padding: 8px 16px; background-color: #0f172a; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
            Print Statement
        </button>
    </div>

    <div class="header">
        <div>
            <div class="agency-title">Aeroheights Travels & Tours</div>
            <div class="sub-title">Agent Statement of Account</div>
        </div>
        <div style="text-align: right;">
            <div><strong>Date:</strong> <?= date('d-m-Y') ?></div>
            <div><strong>Symbol:</strong> SAR</div>
        </div>
    </div>

    <div class="meta-box">
        <div class="meta-item">
            <strong>Agent / Agency:</strong> <?= htmlspecialchars($agent['company_name'] ?? $agent['name']) ?><br>
            <strong>Contact Person:</strong> <?= htmlspecialchars($agent['name']) ?><br>
            <strong>City:</strong> <?= htmlspecialchars($agent['city'] ?? 'Lahore') ?>
        </div>
        <div class="meta-item">
            <strong>Phone:</strong> <?= htmlspecialchars($agent['phone']) ?><br>
            <strong>Email:</strong> <?= htmlspecialchars($agent['email'] ?? '-') ?>
        </div>
        <div class="balance-card">
            <span style="font-size: 10px; color: #64748b; text-transform: uppercase;">Closing Net Balance</span><br>
            <span style="color: <?= $currentBalance > 0 ? '#1e40af' : '#059669' ?>;">
                <?= number_format($currentBalance, 2) ?> SAR
            </span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Flight</th>
                <th>Arrival</th>
                <th>Departure</th>
                <th>Passenger / Description</th>
                <th>Passport / Ref</th>
                <th>Service</th>
                <th class="text-right">Debit (SAR)</th>
                <th class="text-right">Credit (SAR)</th>
                <th class="text-right">Balance (SAR)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($entries)): ?>
                <tr>
                    <td colspan="10" style="text-align: center; color: #94a3b8; padding: 20px;">No transaction records found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td class="font-mono"><?= formatPrintDate($entry['entry_date']) ?></td>
                        <td class="font-mono"><?= htmlspecialchars((string)($entry['flight_number'] ?? '-')) ?></td>
                        <td class="font-mono"><?= formatPrintDate($entry['arrival_date'] ?? null) ?></td>
                        <td class="font-mono"><?= formatPrintDate($entry['departure_date'] ?? null) ?></td>
                        <td><strong><?= htmlspecialchars((string)($entry['passenger_name'] ?? '-')) ?></strong></td>
                        <td class="font-mono"><?= htmlspecialchars((string)($entry['passport_number'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars($entry['service_type']) ?></td>
                        <td class="text-right font-mono" style="color: #b91c1c;">
                            <?= (float)$entry['debit_sar'] > 0 ? number_format((float)$entry['debit_sar'], 2) : '-' ?>
                        </td>
                        <td class="text-right font-mono" style="color: #047857;">
                            <?= (float)$entry['credit_sar'] > 0 ? number_format((float)$entry['credit_sar'], 2) : '-' ?>
                        </td>
                        <td class="text-right font-mono" style="font-weight: bold;">
                            <?= number_format((float)$entry['balance_sar'], 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
