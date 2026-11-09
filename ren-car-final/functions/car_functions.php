<?php
require_once __DIR__ . '/../config/db.php';

/**
 * 🔍 GET CARS (SEARCH)
 */
function getCars($search = '') {
    global $pdo;

    $search = trim($search);

    if ($search !== '') {
        $stmt = $pdo->prepare("
            SELECT * FROM cars
            WHERE car_id LIKE :search
            OR license_plate LIKE :search
            OR brand LIKE :search
            OR model LIKE :search
            ORDER BY car_id ASC
        ");

        $stmt->execute([
            'search' => "%$search%"
        ]);
    } else {
        $stmt = $pdo->query("SELECT * FROM cars ORDER BY car_id ASC");
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * ➕ ADD CAR
 */
function addCar($car_id, $license_plate, $brand, $model, $year, $color, $seats, $daily_rate, $status, $mileage) {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO cars 
        (car_id, license_plate, brand, model, year, color, seats, daily_rate, status, mileage)
        VALUES 
        (:car_id, :license_plate, :brand, :model, :year, :color, :seats, :daily_rate, :status, :mileage)
    ");

    return $stmt->execute([
        'car_id' => $car_id,
        'license_plate' => $license_plate,
        'brand' => $brand,
        'model' => $model,
        'year' => $year,
        'color' => $color,
        'seats' => $seats,
        'daily_rate' => $daily_rate,
        'status' => $status,
        'mileage' => $mileage,
    ]);
}

/**
 * ✏️ UPDATE CAR
 */
function updateCar($car_id, $license_plate, $brand, $model, $year, $color, $seats, $daily_rate, $status, $mileage) {
    global $pdo;

    $stmt = $pdo->prepare("
        UPDATE cars SET
            license_plate = :license_plate,
            brand = :brand,
            model = :model,
            year = :year,
            color = :color,
            seats = :seats,
            daily_rate = :daily_rate,
            status = :status,
            mileage = :mileage
        WHERE car_id = :car_id
    ");

    return $stmt->execute([
        'license_plate' => $license_plate,
        'brand' => $brand,
        'model' => $model,
        'year' => $year,
        'color' => $color,
        'seats' => $seats,
        'daily_rate' => $daily_rate,
        'status' => $status,
        'mileage' => $mileage,
        'car_id' => $car_id,
    ]);
}

/**
 * ❌ DELETE CAR
 */
function deleteCar($car_id) {
    global $pdo;

    $stmt = $pdo->prepare("DELETE FROM cars WHERE car_id = :car_id");
    return $stmt->execute(['car_id' => $car_id]);
}

/**
 * 🔎 GET BY ID
 */
function getCarById($car_id) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM cars WHERE car_id = :car_id");
    $stmt->execute(['car_id' => $car_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 📊 CHART DATA
 */
function getCarsByStatus() {
    global $pdo;

    $stmt = $pdo->query("
        SELECT status, COUNT(*) as total
        FROM cars
        GROUP BY status
        ORDER BY status ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>