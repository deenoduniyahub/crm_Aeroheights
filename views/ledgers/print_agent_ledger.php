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
$currentBalance = $ledgerData['current_balance_pkr'] ?? 0.00;

if (!$agent) {
    echo "Agent not found.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800;900&display=swap">
    <title>Agent Statement - <?= htmlspecialchars($agent['company_name'] ?? $agent['name']) ?></title>
    <style>
        body { font-family:Jost,Arial,sans-serif; font-size: 11px; margin: 20px; color: #161A35; }
        .header { display: flex; justify-content: space-between; align-items: center; border-b: 2px solid #161A35; padding-bottom: 10px; margin-bottom: 15px; }
        .agency-title { font-size: 18px; font-weight: bold; color: #161A35; text-transform: uppercase; }
        .sub-title { font-size: 12px; color: #8A90A8; }
        .meta-box { border: 1px solid #DCE5ED; border-radius: 6px; padding: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; }
        .meta-item { line-height: 1.4; }
        .meta-item strong { color: #4A5170; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #DCE5ED; padding: 6px 8px; text-align: left; }
        th { background-color: #F6F8FC; text-transform: uppercase; font-size: 9px; color: #4A5170; }
        .text-right { text-align: right; }
        .font-mono { font-family: 'Jost', Arial, sans-serif; font-variant-numeric: tabular-nums; }
        .balance-card { text-align: right; font-size: 13px; font-weight: bold; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 15px;">
        <button onclick="window.print()" style="padding: 8px 16px; background-color: #161A35; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
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
            <div><strong>Symbol:</strong> PKR</div>
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
            <span style="font-size: 10px; color: #8A90A8; text-transform: uppercase;">Closing Net Balance</span><br>
            <span style="color: <?= $currentBalance > 0 ? '#26206F' : '#3B8296' ?>;">
                <?= number_format($currentBalance, 2) ?> PKR
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
                <th class="text-right">Debit (PKR)</th>
                <th class="text-right">Credit (PKR)</th>
                <th class="text-right">Balance (PKR)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($entries)): ?>
                <tr>
                    <td colspan="10" style="text-align: center; color: #8A90A8; padding: 20px;">No transaction records found.</td>
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
                        <td class="text-right font-mono" style="color: #E0475B;">
                            <?= (float)$entry['debit_pkr'] > 0 ? number_format((float)$entry['debit_pkr'], 2) : '-' ?>
                        </td>
                        <td class="text-right font-mono" style="color: #3B8296;">
                            <?= (float)$entry['credit_pkr'] > 0 ? number_format((float)$entry['credit_pkr'], 2) : '-' ?>
                        </td>
                        <td class="text-right font-mono" style="font-weight: bold;">
                            <?= number_format((float)$entry['balance_pkr'], 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
