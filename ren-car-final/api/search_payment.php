<?php
require_once '../config/db.php';
require_once '../functions/payment_functions.php';
require_once '../functions/rental_functions.php';

header('Content-Type: application/json');

syncRentalStatuses();
syncPaymentsWithRentals();
$search = trim($_GET['search'] ?? '');
$payments = getPaymentsLinked($search);
$totalIncome = 0.0;
$incomeByTypeMap = [];

$latestPaymentDate = null;

foreach ($payments as $payment) {
    $paymentDate = $payment['payment_date'] ?? null;
    if ($paymentDate && ($latestPaymentDate === null || $paymentDate > $latestPaymentDate)) {
        $latestPaymentDate = $paymentDate;
    }

    $amount = (float)($payment['amount'] ?? 0);
    $totalIncome += $amount;
    $type = $payment['payment_type'] ?? 'Unknown';
    if (!isset($incomeByTypeMap[$type])) {
        $incomeByTypeMap[$type] = 0.0;
    }
    $incomeByTypeMap[$type] += $amount;
}

$incomeByType = [];
foreach ($incomeByTypeMap as $type => $total) {
    $incomeByType[] = ['payment_type' => $type, 'total' => $total];
}

echo json_encode([
    'payments' => $payments,
    'totalIncome' => $totalIncome,
    'incomeByType' => $incomeByType,
    'latestPaymentDate' => $latestPaymentDate,
]);
