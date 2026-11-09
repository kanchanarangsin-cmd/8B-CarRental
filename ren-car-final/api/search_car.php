<?php
require_once '../functions/car_functions.php';

header('Content-Type: application/json');

$query = $_GET['query'] ?? '';

if ($query) {
    $cars = searchCars($query);
    echo json_encode($cars);
} else {
    echo json_encode([]);
}
?>