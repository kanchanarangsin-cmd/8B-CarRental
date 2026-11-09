<?php
require_once '../functions/customer_functions.php';

header('Content-Type: application/json');

$query = $_GET['query'] ?? '';

if ($query) {
    $customers = searchCustomers($query);
    echo json_encode($customers);
} else {
    echo json_encode([]);
}
?>