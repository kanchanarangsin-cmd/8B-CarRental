<?php
require_once '../config/db.php';
require_once '../functions/rental_functions.php';

header('Content-Type: application/json; charset=utf-8');

$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

if ($startDate === '' || $endDate === '') {
    echo json_encode([]);
    exit;
}

try {
    echo json_encode(getAvailableCars($startDate, $endDate), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'ไม่สามารถโหลดรถที่ว่างได้'], JSON_UNESCAPED_UNICODE);
}
