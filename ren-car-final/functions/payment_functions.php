<?php
require_once __DIR__ . '/../config/db.php';

function rental_suffix_from_id(string $rentalId): string {
    return preg_replace('/^RENT/i', '', trim($rentalId));
}

function payment_id_from_rental_id(string $rentalId): string {
    return 'PAY' . rental_suffix_from_id($rentalId);
}

function payment_exists(string $paymentId): bool {
    global $pdo;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM payments WHERE payment_id = ?');
    $stmt->execute([$paymentId]);
    return (int)$stmt->fetchColumn() > 0;
}

function getPaymentByRentalId(string $rentalId): ?array {
    global $pdo;
    $paymentId = payment_id_from_rental_id($rentalId);
    $stmt = $pdo->prepare('SELECT * FROM payments WHERE payment_id = ? LIMIT 1');
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    return $payment ?: null;
}


function deleteOrphanPayments(): void {
    global $pdo;

    $stmt = $pdo->prepare("
        DELETE p
        FROM payments p
        LEFT JOIN rentals r ON r.rental_id = REPLACE(p.payment_id, 'PAY', 'RENT')
        WHERE r.rental_id IS NULL
    ");
    $stmt->execute();
}

function syncPaymentsWithRentals(): void {
    global $pdo;

    deleteOrphanPayments();

    $stmt = $pdo->query("SELECT rental_id, rental_date, total_price FROM rentals ORDER BY CAST(SUBSTRING(rental_id, 5) AS UNSIGNED) ASC");
    $rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rentals as $rental) {
        $existing = getPaymentByRentalId($rental['rental_id']);
        $paymentType = $existing['payment_type'] ?? 'Cash';
        upsertPaymentForRental(
            $rental['rental_id'],
            $rental['rental_date'],
            (float)($rental['total_price'] ?? 0),
            $paymentType
        );
    }
}

function getPaymentsLinked(string $search = ''): array {
    global $pdo;

    $sql = "SELECT p.*, REPLACE(p.payment_id, 'PAY', 'RENT') AS rental_id,
                   r.customer_id, c.name AS customer_name, r.car_id,
                   CONCAT(ca.brand, ' ', ca.model) AS car_name,
                   r.total_price, r.status AS rental_status
            FROM payments p
            INNER JOIN rentals r ON r.rental_id = REPLACE(p.payment_id, 'PAY', 'RENT')
            LEFT JOIN customers c ON r.customer_id = c.customer_id
            LEFT JOIN cars ca ON r.car_id = ca.car_id";

    $params = [];
    if ($search !== '') {
        $sql .= " WHERE p.payment_id LIKE ?
                  OR p.payment_type LIKE ?
                  OR REPLACE(p.payment_id, 'PAY', 'RENT') LIKE ?
                  OR COALESCE(c.customer_id, '') LIKE ?
                  OR COALESCE(c.name, '') LIKE ?
                  OR COALESCE(r.car_id, '') LIKE ?";
        $needle = '%' . $search . '%';
        $params = [$needle, $needle, $needle, $needle, $needle, $needle];
    }

    $sql .= " ORDER BY CAST(SUBSTRING(p.payment_id, 4) AS UNSIGNED) DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTotalIncome(): float {
    global $pdo;
    $stmt = $pdo->query('SELECT COALESCE(SUM(amount), 0) as total FROM payments');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (float)($row['total'] ?? 0);
}

function getIncomeByType(): array {
    global $pdo;
    $stmt = $pdo->query('SELECT payment_type, COALESCE(SUM(amount), 0) as total FROM payments GROUP BY payment_type');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function upsertPaymentForRental(string $rentalId, string $paymentDate, float $amount, string $paymentType = 'Cash'): string {
    global $pdo;

    $paymentId = payment_id_from_rental_id($rentalId);
    $amount = round($amount, 2);

    if (payment_exists($paymentId)) {
        $stmt = $pdo->prepare('UPDATE payments SET payment_date = ?, amount = ?, payment_type = ? WHERE payment_id = ?');
        $stmt->execute([$paymentDate, $amount, $paymentType, $paymentId]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO payments (payment_id, payment_date, amount, payment_type) VALUES (?, ?, ?, ?)');
        $stmt->execute([$paymentId, $paymentDate, $amount, $paymentType]);
    }

    return $paymentId;
}

function deletePaymentByRentalId(string $rentalId): void {
    global $pdo;
    $paymentId = payment_id_from_rental_id($rentalId);
    $stmt = $pdo->prepare('DELETE FROM payments WHERE payment_id = ?');
    $stmt->execute([$paymentId]);
}
