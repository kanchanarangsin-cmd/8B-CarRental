<?php
require_once '../functions/car_functions.php';

header('Content-Type: application/json');

$id = $_GET['id'] ?? '';

$car = getCarById($id);
echo json_encode($car ?: []);
?>