<?php
require_once '../config/db.php';
require_once '../includes/ui_helpers.php';
require_once '../includes/navbar.php';
require_once '../functions/review_functions.php';

$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare('SELECT * FROM reviews WHERE reviews_id LIKE ? OR comment LIKE ? ORDER BY reviews_id ASC');
    $like = "%$search%";
    $stmt->execute([$like, $like]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM reviews ORDER BY reviews_id ASC');
    $stmt->execute();
}
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
$ratingCounts = getRatingCounts();
$avgRating = getAverageRating();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รีวิว - <?= e(app_name()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/design_tokens.css" rel="stylesheet">
</head>
<body class="reviews-page">
<?php render_navbar('reviews'); ?>

<div class="container page-shell">
    <div class="page-header">
        <div>
            <div class="page-eyebrow"><i class="fa-solid fa-star"></i> เสียงตอบรับจากลูกค้า</div>
            <h1 class="page-title">จัดการรีวิวและคะแนน</h1>
            <p class="page-subtitle"></p>
        </div>
    </div>

    <div class="layout-grid two-one compact-overview mb-4">
        <div class="hero-card equal-card-block">
            <div class="hero-label"><i class="fa-solid fa-star-half-stroke"></i> คะแนนเฉลี่ย</div>
            <div class="hero-value"><span id="averageRatingValue"><?= e(number_format((float)$avgRating, 1)) ?></span>/5</div>
            <p class="mb-0 mt-2">ค่าเฉลี่ยคะแนนรีวิวทั้งหมดในระบบ</p>
            <div class="quick-stat-row">
                <span class="quick-stat"><i class="fa-solid fa-message"></i> คอมเมนต์และคำติชมแบบเรียลไทม์</span>
            </div>
        </div>

        <div class="chart-card chart-panel-md equal-card-block">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title">จำนวนรีวิวตามคะแนน</h3>
                </div>
            </div>
            <canvas id="ratingChart"></canvas>
        </div>
    </div>

    <div class="section-card mb-4">
        <label class="form-label" for="reviewSearch">ค้นหารีวิว</label>
        <input type="text" id="reviewSearch" class="form-control" placeholder="ค้นหาจากรหัสรีวิวหรือข้อความคอมเมนต์" value="<?= e($search) ?>">
        <span id="searchLoading" class="muted-text" style="display:none; margin-top: 0.75rem;">กำลังค้นหา...</span>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>รหัสรีวิว</th>
                        <th>ความคิดเห็น</th>
                        <th>คะแนน</th>
                        <th>วันที่รีวิว</th>
                    </tr>
                </thead>
                <tbody id="reviewTableBody">
                    <?php if (count($reviews) > 0): ?>
                        <?php foreach ($reviews as $row): ?>
                            <tr>
                                <td><?= e($row['reviews_id']) ?></td>
                                <td><?= e($row['comment']) ?></td>
                                <td><?= e($row['rating']) ?>/5</td>
                                <td><?= e(format_display_date($row['review_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center empty-state">ไม่พบข้อมูลรีวิว</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ratingData = <?= json_encode($ratingCounts, JSON_UNESCAPED_UNICODE) ?>;
Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
window.ratingChart = new Chart(document.getElementById('ratingChart'), {
    type: 'bar',
    data: {
        labels: ratingData.map(item => `คะแนน ${item.rating}`),
        datasets: [{
            label: 'จำนวนรีวิว',
            data: ratingData.map(item => parseInt(item.count, 10)),
            backgroundColor: '#6B55B4',
            borderRadius: 12,
            borderSkipped: false,
            maxBarThickness: 38
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { color: '#342D40', precision: 0 },
                grid: { color: 'rgba(111, 102, 122, 0.12)' }
            },
            x: {
                ticks: { color: '#342D40' },
                grid: { display: false }
            }
        }
    }
});
</script>
<script src="../assets/js/reviews.js"></script>
</body>
</html>
