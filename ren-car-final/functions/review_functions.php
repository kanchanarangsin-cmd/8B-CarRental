<?php
require_once __DIR__ . '/../config/db.php';

function getReviews() {
    global $pdo;
    $stmt = $pdo->query('SELECT * FROM reviews ORDER BY review_date DESC');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAverageRating() {
    global $pdo;
    $stmt = $pdo->query('SELECT AVG(rating) as average FROM reviews');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['average'] ?? 0;
}

function getRatingCounts() {
    global $pdo;
    $stmt = $pdo->query('SELECT rating, COUNT(*) as count FROM reviews GROUP BY rating ORDER BY rating');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
