<?php
require_once __DIR__ . '/../config/db.php';

function getEmployees($search = '', $position_filter = '') {
    global $pdo;

    $sql = "SELECT * FROM employees";
    $params = [];
    $conditions = [];

    if (!empty($search)) {
        $conditions[] = "(name LIKE :search OR position LIKE :search)";
        $params['search'] = "%$search%";
    }

    if (!empty($position_filter)) {
        $conditions[] = "position = :position";
        $params['position'] = $position_filter;
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }

    $sql .= " ORDER BY employee_id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addEmployee($employee_id, $name, $position, $status) {
    global $pdo;

    $stmt = $pdo->prepare(
        "INSERT INTO employees (employee_id, name, position, status)
         VALUES (:employee_id, :name, :position, :status)"
    );

    return $stmt->execute([
        'employee_id' => $employee_id,
        'name' => $name,
        'position' => $position,
        'status' => $status,
    ]);
}

function updateEmployee($employee_id, $name, $position, $status) {
    global $pdo;

    $stmt = $pdo->prepare(
        "UPDATE employees
         SET name = :name,
             position = :position,
             status = :status
         WHERE employee_id = :employee_id"
    );

    return $stmt->execute([
        'name' => $name,
        'position' => $position,
        'status' => $status,
        'employee_id' => $employee_id,
    ]);
}

function deleteEmployee($employee_id) {
    global $pdo;

    $stmt = $pdo->prepare("DELETE FROM employees WHERE employee_id = :employee_id");
    return $stmt->execute(['employee_id' => $employee_id]);
}

function getEmployeeById($employee_id) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_id = :employee_id");
    $stmt->execute(['employee_id' => $employee_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getPositions() {
    global $pdo;

    $stmt = $pdo->query("SELECT DISTINCT position FROM employees ORDER BY position ASC");
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'position');
}

function getEmployeesByStatus() {
    global $pdo;

    $stmt = $pdo->query(
        "SELECT status, COUNT(*) as total
         FROM employees
         GROUP BY status
         ORDER BY status ASC"
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>