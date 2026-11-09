<?php
require_once '../functions/payment_functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_date = $_POST['payment_date'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $payment_type = $_POST['payment_type'] ?? '';

    if ($payment_date && $amount && $payment_type) {
        if (addPayment($payment_date, $amount, $payment_type)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to add payment']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Missing fields']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>