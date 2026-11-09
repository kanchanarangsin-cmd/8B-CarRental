<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/payment_functions.php';

function syncRentalStatuses(?string $today = null): void {
    global $pdo;

    $today = $today ?: date('Y-m-d');

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $completeStmt = $pdo->prepare("UPDATE rentals SET status = 'Completed' WHERE return_date < ? AND status NOT IN ('Completed', 'Returned')");
        $completeStmt->execute([$today]);

        $upcomingStmt = $pdo->prepare("UPDATE rentals SET status = 'Booked' WHERE rental_date > ? AND status NOT IN ('Completed', 'Returned')");
        $upcomingStmt->execute([$today]);

        $activeStmt = $pdo->prepare("UPDATE rentals SET status = 'Rented' WHERE rental_date <= ? AND return_date >= ? AND status NOT IN ('Completed', 'Returned')");
        $activeStmt->execute([$today, $today]);

        $pdo->exec("UPDATE cars SET status = 'Available' WHERE status <> 'Maintenance'");

        $activeCarsStmt = $pdo->prepare("SELECT DISTINCT car_id FROM rentals WHERE status = 'Rented' AND rental_date <= ? AND return_date >= ?");
        $activeCarsStmt->execute([$today, $today]);
        $activeCars = $activeCarsStmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($activeCars)) {
            $placeholders = implode(',', array_fill(0, count($activeCars), '?'));
            $updateRented = $pdo->prepare("UPDATE cars SET status = 'Rented' WHERE car_id IN ($placeholders) AND status <> 'Maintenance'");
            $updateRented->execute($activeCars);
        }

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function countRentalsInRange(string $startDate, string $endDate): int {
    global $pdo;

    syncRentalStatuses();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rentals WHERE rental_date <= :end_date AND return_date >= :start_date AND status NOT IN ('Completed', 'Returned')");
    $stmt->execute([
        'start_date' => $startDate,
        'end_date' => $endDate,
    ]);

    return (int) $stmt->fetchColumn();
}

function getRentals($search = '', $statusFilter = '') {
    global $conn;

    syncRentalStatuses();

    $sql = "SELECT r.*, c.customer_id, c.name AS customer_name, ca.brand, ca.model
            FROM rentals r
            JOIN customers c ON r.customer_id = c.customer_id
            JOIN cars ca ON r.car_id = ca.car_id";

    $conditions = [];

    if (!empty($search)) {
        $search = mysqli_real_escape_string($conn, $search);
        $conditions[] = "(r.rental_id LIKE '%$search%'
                  OR c.customer_id LIKE '%$search%'
                  OR c.name LIKE '%$search%'
                  OR r.car_id LIKE '%$search%'
                  OR ca.brand LIKE '%$search%'
                  OR ca.model LIKE '%$search%'
                  OR CONCAT(ca.brand, ' ', ca.model) LIKE '%$search%')";
    }

    if (!empty($statusFilter)) {
        $statusFilter = mysqli_real_escape_string($conn, $statusFilter);
        if ($statusFilter === 'completed') {
            $conditions[] = "r.status IN ('Completed', 'Returned')";
        } elseif (strcasecmp($statusFilter, 'rented') === 0) {
            $conditions[] = "r.status = 'Rented'";
        } elseif (strcasecmp($statusFilter, 'booked') === 0) {
            $conditions[] = "r.status = 'Booked'";
        } else {
            $conditions[] = "r.status = '$statusFilter'";
        }
    }

    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= " ORDER BY CAST(SUBSTRING(r.rental_id, 5) AS UNSIGNED) DESC";
    $result = mysqli_query($conn, $sql);

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }

    return $data;
}

function addRental($customer_id, $car_id, $rental_date, $return_date, $payment_type = 'Cash') {
    global $pdo;

    try {
        $pdo->beginTransaction();

        syncRentalStatuses();

        $rentalIdStmt = $pdo->query("SELECT rental_id FROM rentals ORDER BY CAST(SUBSTRING(rental_id, 5) AS UNSIGNED) DESC LIMIT 1");
        $lastRentalId = $rentalIdStmt->fetchColumn();
        $nextRentalNum = $lastRentalId ? ((int)substr($lastRentalId, 4) + 1) : 1;
        $rental_id = 'RENT' . str_pad((string)$nextRentalNum, 3, '0', STR_PAD_LEFT);

        $carStmt = $pdo->prepare("SELECT daily_rate, status FROM cars WHERE car_id = :car_id LIMIT 1");
        $carStmt->execute(['car_id' => $car_id]);
        $car = $carStmt->fetch(PDO::FETCH_ASSOC);

        if (!$car) {
            throw new Exception('ไม่พบข้อมูลรถ');
        }
        if ($car['status'] === 'Maintenance') {
            throw new Exception('รถคันนี้อยู่ระหว่างซ่อม ไม่สามารถเช่าได้');
        }

        $overlapStmt = $pdo->prepare("SELECT COUNT(*) FROM rentals WHERE car_id = :car_id AND status NOT IN ('Completed', 'Returned') AND NOT (return_date < :rental_date OR rental_date > :return_date)");
        $overlapStmt->execute([
            'car_id' => $car_id,
            'rental_date' => $rental_date,
            'return_date' => $return_date,
        ]);
        if ((int)$overlapStmt->fetchColumn() > 0) {
            throw new Exception('รถคันนี้ถูกเช่าอยู่ในช่วงวันที่ที่เลือกแล้ว');
        }

        $days = (int)ceil((strtotime($return_date) - strtotime($rental_date)) / 86400);
        if ($days < 1) {
            $days = 1;
        }
        $daily_rate = (float)$car['daily_rate'];
        $total_price = $daily_rate * $days;

        $status = ($rental_date > date('Y-m-d')) ? 'Booked' : 'Rented';

        $stmt = $pdo->prepare("INSERT INTO rentals (rental_id, customer_id, car_id, rental_date, return_date, total_price, status)
            VALUES (:rental_id, :customer_id, :car_id, :rental_date, :return_date, :total_price, :status)");

        $stmt->execute([
            'rental_id' => $rental_id,
            'customer_id' => $customer_id,
            'car_id' => $car_id,
            'rental_date' => $rental_date,
            'return_date' => $return_date,
            'total_price' => $total_price,
            'status' => $status
        ]);

        upsertPaymentForRental($rental_id, $rental_date, $total_price, $payment_type);

        if ($status === 'Rented') {
            $updateCar = $pdo->prepare("UPDATE cars SET status = 'Rented' WHERE car_id = :car_id AND status <> 'Maintenance'");
            $updateCar->execute(['car_id' => $car_id]);
        }

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function getRentalById($rental_id) {
    global $pdo;

    syncRentalStatuses();

    $stmt = $pdo->prepare("SELECT r.*, c.customer_id, c.name as customer_name, ca.brand, ca.model, ca.daily_rate
        FROM rentals r
        JOIN customers c ON r.customer_id = c.customer_id
        JOIN cars ca ON r.car_id = ca.car_id
        WHERE r.rental_id = :rental_id");

    $stmt->execute(['rental_id' => $rental_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateRental($rental_id, $return_date, $status, $payment_type = null) {
    global $pdo;

    try {
        $pdo->beginTransaction();

        $getRental = $pdo->prepare("SELECT r.*, ca.daily_rate FROM rentals r JOIN cars ca ON r.car_id = ca.car_id WHERE r.rental_id = :rental_id");
        $getRental->execute(['rental_id' => $rental_id]);
        $rental = $getRental->fetch(PDO::FETCH_ASSOC);
        if (!$rental) {
            throw new Exception('ไม่พบรายการเช่า');
        }

        $today = date('Y-m-d');
        $requestedStatus = trim((string)$status);

        if ($return_date < $rental['rental_date']) {
            throw new Exception('วันที่คืนรถต้องไม่ก่อนวันที่เริ่มเช่า');
        }

        if (in_array($requestedStatus, ['Completed', 'Returned'], true) || $return_date < $today) {
            $status = 'Completed';
        } elseif ($rental['rental_date'] > $today) {
            $status = 'Booked';
        } else {
            $status = 'Rented';
        }

        $days = (int)ceil((strtotime($return_date) - strtotime($rental['rental_date'])) / 86400);
        if ($days < 1) {
            $days = 1;
        }
        $total_price = (float)$rental['daily_rate'] * $days;

        $stmt = $pdo->prepare("UPDATE rentals SET return_date = :return_date, total_price = :total_price, status = :status WHERE rental_id = :rental_id");
        $stmt->execute([
            'return_date' => $return_date,
            'total_price' => $total_price,
            'status' => $status,
            'rental_id' => $rental_id
        ]);

        $existingPayment = getPaymentByRentalId($rental_id);
        $effectivePaymentType = $payment_type ?: ($existingPayment['payment_type'] ?? 'Cash');
        upsertPaymentForRental($rental_id, $rental['rental_date'], $total_price, $effectivePaymentType);

        if (in_array($status, ['Returned', 'Completed'], true)) {
            $updateCar = $pdo->prepare("UPDATE cars SET status = 'Available' WHERE car_id = :car_id AND status <> 'Maintenance'");
            $updateCar->execute(['car_id' => $rental['car_id']]);
        } elseif ($status === 'Booked') {
            $updateCar = $pdo->prepare("UPDATE cars SET status = 'Available' WHERE car_id = :car_id AND status <> 'Maintenance'");
            $updateCar->execute(['car_id' => $rental['car_id']]);
        } else {
            $updateCar = $pdo->prepare("UPDATE cars SET status = 'Rented' WHERE car_id = :car_id AND status <> 'Maintenance'");
            $updateCar->execute(['car_id' => $rental['car_id']]);
        }

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function deleteRental($rental_id) {
    global $pdo;

    try {
        $pdo->beginTransaction();

        $getRental = $pdo->prepare("SELECT car_id, customer_id FROM rentals WHERE rental_id = :rental_id");
        $getRental->execute(['rental_id' => $rental_id]);
        $rental = $getRental->fetch(PDO::FETCH_ASSOC);

        if ($rental) {
            deletePaymentByRentalId($rental_id);

            $stmt = $pdo->prepare("DELETE FROM rentals WHERE rental_id = :rental_id");
            $stmt->execute(['rental_id' => $rental_id]);

            $updateCar = $pdo->prepare("UPDATE cars SET status = 'Available' WHERE car_id = :car_id AND status <> 'Maintenance'");
            $updateCar->execute(['car_id' => $rental['car_id']]);
        }

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function getAvailableCars(?string $startDate = null, ?string $endDate = null): array {
    global $pdo;

    syncRentalStatuses();

    if (!empty($startDate) && !empty($endDate)) {
        $stmt = $pdo->prepare("SELECT car_id, CONCAT(brand, ' ', model) as car_name, brand, model, daily_rate
            FROM cars
            WHERE status <> 'Maintenance'
              AND car_id NOT IN (
                SELECT car_id FROM rentals
                WHERE status NOT IN ('Completed', 'Returned')
                  AND rental_date <= :end_date
                  AND return_date >= :start_date
              )
            ORDER BY brand, model");
        $stmt->execute([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmt = $pdo->query("SELECT car_id, CONCAT(brand, ' ', model) as car_name, brand, model, daily_rate
        FROM cars
        WHERE status = 'Available'
        ORDER BY brand, model");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllCustomers(?string $startDate = null, ?string $endDate = null) {
    global $pdo;

    syncRentalStatuses();

    if (empty($startDate) || empty($endDate)) {
        $stmt = $pdo->query("SELECT customer_id, name FROM customers ORDER BY customer_id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $sql = "SELECT c.customer_id, c.name
            FROM customers c
            WHERE c.customer_id NOT IN (
                SELECT DISTINCT r.customer_id
                FROM rentals r
                WHERE r.status NOT IN ('Completed', 'Returned')
                  AND r.rental_date <= :end_date
                  AND r.return_date >= :start_date
            )
            ORDER BY c.customer_id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'start_date' => $startDate,
        'end_date' => $endDate,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function countRentalsByStatuses(array $statuses): int {
    global $pdo;
    syncRentalStatuses();

    if (empty($statuses)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM rentals WHERE status IN ($placeholders)");
    $stmt->execute(array_values($statuses));
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return intval($result['count'] ?? 0);
}

function countRentalsByStatus($status) {
    global $pdo;
    syncRentalStatuses();

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rentals WHERE status = :status");
    $stmt->execute(['status' => $status]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return intval($result['count'] ?? 0);
}

function getTotalRentals() {
    global $pdo;
    syncRentalStatuses();

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM rentals");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return intval($result['count']) ?? 0;
}
