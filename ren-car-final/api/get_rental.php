<?php
function getRentals($search = '') {
    global $pdo;

    $sql = "
        SELECT 
            r.rental_id,
            r.rental_date,
            r.return_date,
            r.status,
            c.name AS customer_name,
            car.brand,
            car.model
        FROM rentals r
        JOIN customers c ON r.customer_id = c.customer_id
        JOIN cars car ON r.car_id = car.car_id
    ";

    // 🔍 search
    if ($search) {
        $sql .= " WHERE c.name LIKE :search OR r.rental_id LIKE :search";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['search' => "%$search%"]);
    } else {
        $stmt = $pdo->query($sql);
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>