<?php
require_once '../functions/customer_functions.php';

header('Content-Type: application/json');

$id = $_GET['id'] ?? '';

$customer = getCustomerById($id);
echo json_encode($customer ?: []);
?>