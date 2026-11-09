<?php
require_once '../functions/employee_functions.php';

header('Content-Type: application/json');

$query = $_GET['query'] ?? '';

if ($query) {
    $employees = searchEmployees($query);
    echo json_encode($employees);
} else {
    echo json_encode([]);
}
?>