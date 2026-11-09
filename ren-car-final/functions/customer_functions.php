<?php
require_once __DIR__ . '/../config/db.php';

function getCustomers($search = '') {
    global $pdo;

    if (!empty($search)) {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE name LIKE :search ORDER BY customer_id ASC");
        $stmt->execute(['search' => "%$search%"]); 
    } else {
        $stmt = $pdo->query("SELECT * FROM customers ORDER BY customer_id ASC");
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addCustomer($name, $phone, $id_card, $bill) {
    global $pdo;

    $stmt = $pdo->prepare(
        "INSERT INTO customers (name, phone, id_card, bill)
         VALUES (:name, :phone, :id_card, :bill)"
    );

    return $stmt->execute([
        'name' => $name,
        'phone' => $phone,
        'id_card' => $id_card,
        'bill' => $bill,
    ]);
}

function updateCustomer($customer_id, $name, $phone, $id_card, $bill) {
    global $pdo;

    $stmt = $pdo->prepare(
        "UPDATE customers
         SET name = :name,
             phone = :phone,
             id_card = :id_card,
             bill = :bill
         WHERE customer_id = :customer_id"
    );

    return $stmt->execute([
        'name' => $name,
        'phone' => $phone,
        'id_card' => $id_card,
        'bill' => $bill,
        'customer_id' => $customer_id,
    ]);
}

function deleteCustomer($customer_id) {
    global $pdo;

    $stmt = $pdo->prepare("DELETE FROM customers WHERE customer_id = :customer_id");
    return $stmt->execute(['customer_id' => $customer_id]);
}

function getCustomerById($customer_id) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = :customer_id");
    $stmt->execute(['customer_id' => $customer_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getCustomerCount() {
    global $pdo;

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM customers");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['total'] ?? 0;
}
?>