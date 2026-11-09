<?php
require_once '../config/db.php';

header('Content-Type: application/json');

$search = $_GET['search'] ?? '';

if ($search) {
    // Get filtered reviews
    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE reviews_id LIKE ? OR comment LIKE ? ORDER BY reviews_id ASC");
    $like = "%$search%";
    $stmt->execute([$like, $like]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get average rating for filtered results
    $stmt = $pdo->prepare("SELECT AVG(rating) as average FROM reviews WHERE reviews_id LIKE ? OR comment LIKE ?");
    $stmt->execute([$like, $like]);
    $averageRating = $stmt->fetch()['average'] ?? 0;
    
    // Get rating counts for filtered results
    $stmt = $pdo->prepare("SELECT rating, COUNT(*) as count FROM reviews WHERE reviews_id LIKE ? OR comment LIKE ? GROUP BY rating ORDER BY rating");
    $stmt->execute([$like, $like]);
    $ratingCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Get all reviews
    $stmt = $pdo->query("SELECT * FROM reviews ORDER BY reviews_id ASC");
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get average rating
    $stmt = $pdo->query("SELECT AVG(rating) as average FROM reviews");
    $averageRating = $stmt->fetch()['average'] ?? 0;
    
    // Get rating counts
    $stmt = $pdo->query("SELECT rating, COUNT(*) as count FROM reviews GROUP BY rating ORDER BY rating");
    $ratingCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode([
    'reviews' => $reviews,
    'averageRating' => floatval($averageRating),
    'ratingCounts' => $ratingCounts
]);
?>
