<?php
require_once '../config/db.php';
require_once '../includes/ui_helpers.php';
require_once '../includes/navbar.php';
require_once '../functions/rental_functions.php';

syncRentalStatuses();
syncPaymentsWithRentals();

function fetch_scalar(mysqli $conn, string $sql) {
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_row();
    return $row[0] ?? 0;
}

function fetch_rows(mysqli $conn, string $sql): array {
    $rows = [];
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

$availableCars = (int) fetch_scalar($conn, "SELECT COUNT(*) FROM cars WHERE status = 'Available'");
$rentedCars = (int) fetch_scalar($conn, "SELECT COUNT(*) FROM cars WHERE status = 'Rented'");
$maintenanceCars = (int) fetch_scalar($conn, "SELECT COUNT(*) FROM cars WHERE status = 'Maintenance'");
$bookedRentals = (int) fetch_scalar($conn, "SELECT COUNT(*) FROM rentals WHERE status = 'Booked'");
$activeRentals = $rentedCars + $bookedRentals;
$totalIncome = (float) fetch_scalar($conn, "SELECT COALESCE(SUM(amount), 0) FROM payments");

$nearDueRentals = (int) fetch_scalar($conn, "SELECT COUNT(*) FROM rentals WHERE status = 'Rented' AND return_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)");
$overdueRentals = (int) fetch_scalar($conn, "SELECT COUNT(*) FROM rentals WHERE status = 'Rented' AND return_date < CURDATE()");
$damageCases = (int) fetch_scalar($conn, "SELECT COUNT(*) FROM car_return_inspections WHERE has_damage = 'Yes'");

$monthlyRows = fetch_rows($conn, "SELECT DATE_FORMAT(payment_date, '%m/%Y') AS month_label, COALESCE(SUM(amount),0) AS total_amount
    FROM payments
    WHERE payment_date IS NOT NULL
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m'), DATE_FORMAT(payment_date, '%m/%Y')
    ORDER BY DATE_FORMAT(payment_date, '%Y-%m') ASC");

$recentRentals = fetch_rows($conn, "SELECT r.rental_id, c.name AS customer_name, r.car_id, r.return_date, r.status
    FROM rentals r
    LEFT JOIN customers c ON c.customer_id = r.customer_id
    ORDER BY r.rental_date DESC, r.rental_id DESC
    LIMIT 5");

$recentReturns = fetch_rows($conn, "SELECT cri.inspection_id, cri.rental_id, cri.car_id, cri.return_date, cri.has_damage, cri.fine_amount
    FROM car_return_inspections cri
    ORDER BY cri.return_date DESC, cri.inspection_id DESC
    LIMIT 5");

$monthlyLabels = array_map(static fn($row) => $row['month_label'], $monthlyRows);
$monthlyAmounts = array_map(static fn($row) => (float) $row['total_amount'], $monthlyRows);
$fleetTotal = max(1, $availableCars + $rentedCars + $maintenanceCars);
$fleetItems = [
    ['label' => 'พร้อมให้เช่า', 'value' => $availableCars, 'icon' => 'fa-circle-check'],
    ['label' => 'กำลังเช่า', 'value' => $rentedCars, 'icon' => 'fa-key'],
    ['label' => 'ซ่อมบำรุง', 'value' => $maintenanceCars, 'icon' => 'fa-screwdriver-wrench'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ด - <?= e(app_name()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/design_tokens.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root{
            --bg:#F6F2FC;
            --panel:#FFFDFF;
            --line:rgba(111, 102, 122, .18);
            --text:#332A46;
            --muted:#342D40;
            --soft:#6F667A;
            --accent:#6B55B4;
            --accent-2:#A53D3D;
            --shadow:0 10px 28px rgba(51, 42, 70, .05);
            --shadow-hover:0 16px 32px rgba(51, 42, 70, .08);
            --radius:26px;
        }
        *{box-sizing:border-box}
        body{background:linear-gradient(180deg,#F6F2FC 0%, #F6F2FC 100%); color:var(--text);}
        .dashboard-page{background:transparent;}
        .dashboard-shell{max-width:1380px; margin:0 auto; padding:26px 0 42px;}
        .dashboard-hero{display:flex; justify-content:space-between; align-items:flex-end; gap:18px; margin-bottom:20px;}
        .dashboard-hero h1{margin:10px 0 6px; font-size:2.2rem; font-weight:800; letter-spacing:-.02em;}
        .dashboard-hero p{margin:0; color:var(--muted);}
        .page-eyebrow{display:inline-flex; align-items:center; gap:8px; background:#F6F2FC; color:var(--accent); border:1px solid rgba(107, 85, 180, .10); padding:8px 12px; border-radius:999px; font-size:.85rem; font-weight:700;}
        .dashboard-date-pill{background:rgba(255, 253, 255, .92); border:1px solid var(--line); border-radius:18px; padding:12px 16px; color:#342D40; font-weight:600; box-shadow:var(--shadow); white-space:nowrap;}

        .top-dashboard-grid{display:grid; grid-template-columns:1fr; gap:20px; align-items:start; margin-bottom:20px;}
        .summary-grid{display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:18px; margin-bottom:0;}
        .summary-card,.panel-card{background:var(--panel); border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow);}
        .summary-card{padding:20px 22px; min-height:142px; display:flex; flex-direction:column; justify-content:space-between;}
        .summary-link,.panel-link{display:block; color:inherit; text-decoration:none;}
        .summary-link .summary-card,.panel-link .panel-card,.insight-item,.alert-item,.manager-mini-table tbody tr{transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease, background-color .18s ease;}
        .summary-link:hover .summary-card,.panel-link:hover .panel-card.clickable{transform:translateY(-2px); box-shadow:var(--shadow-hover); border-color:rgba(107, 85, 180, .18);}
        .summary-top{display:flex; align-items:center; justify-content:space-between; gap:10px;}
        .summary-label{color:var(--muted); font-size:.96rem; font-weight:700;}
        .summary-icon{width:48px; height:48px; border-radius:16px; display:inline-flex; align-items:center; justify-content:center; background:linear-gradient(135deg, rgba(107, 85, 180, .11), rgba(165, 61, 61, .08)); color:var(--accent); font-size:1rem;}
        .summary-value{font-size:2.1rem; line-height:1; font-weight:800; color:var(--text); margin:12px 0 0;}
        .summary-value.money{font-size:1.82rem;}
        .summary-desc{margin-top:10px; color:var(--soft); font-size:.9rem;}

        .manager-overview-panel{padding:22px 22px 20px; min-height:100%;}
        .manager-overview-row{display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin-top:10px;}
        .manager-overview-card{display:flex; align-items:flex-start; gap:12px; padding:16px; border-radius:20px; background:linear-gradient(180deg,#FFFDFF 0%, #F6F2FC 100%); border:1px solid rgba(111, 102, 122, .16); min-height:110px; text-decoration:none; color:inherit; transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;}
        .manager-overview-card:hover{transform:translateY(-2px); box-shadow:var(--shadow-hover); border-color:rgba(107, 85, 180, .20); color:inherit;}
        .manager-overview-icon{width:46px; height:46px; border-radius:16px; display:inline-flex; align-items:center; justify-content:center; color:#FFFDFF; background:linear-gradient(135deg, #6B55B4, #6B55B4); flex-shrink:0; font-size:.96rem; box-shadow:0 12px 22px rgba(107, 85, 180, .20);}
        .manager-overview-meta{display:flex; flex-direction:column; gap:4px;}
        .manager-overview-tag{display:inline-flex; width:max-content; padding:4px 10px; border-radius:999px; background:#F6F2FC; color:var(--accent); font-size:.74rem; font-weight:800;}
        .manager-overview-value{font-size:1.28rem; font-weight:800; line-height:1.2; color:var(--text);}
        .manager-overview-text{font-size:.84rem; line-height:1.45; color:var(--muted);}

        .main-grid{display:grid; grid-template-columns:minmax(0,1fr); gap:20px; align-items:start;}
        .left-column,.right-column{display:grid; gap:20px; align-items:start;}
        .sub-grid{display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px; align-items:stretch;}
        .panel-card{padding:22px 22px 20px;}
        .panel-card.compact{padding:20px;}
        .panel-card.fill-height{height:100%;}
        .panel-head{display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:15px;}
        .panel-title{font-size:1.08rem; font-weight:800; color:#332A46; margin:0;}
        .panel-subtitle{margin:4px 0 0; color:var(--soft); font-size:.89rem;}
        .panel-chip{display:inline-flex; align-items:center; gap:8px; border-radius:999px; background:#F6F2FC; border:1px solid var(--line); padding:7px 12px; color:#342D40; font-size:.82rem; font-weight:700;}
        .chart-box{height:250px; padding-top:4px;}
        .chart-box.small{height:250px;}
        .chart-box.bar-small{height:245px;}
        .click-hint{font-size:.81rem; color:var(--accent); font-weight:700; margin-top:12px;}

        .fleet-list{display:grid; gap:12px;}
        .overview-grid{display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin-top:8px;}
        .insight-item{position:relative; display:flex; align-items:flex-start; gap:12px; padding:16px; border-radius:20px; background:linear-gradient(180deg,#FFFDFF 0%, #F6F2FC 100%); border:1px solid rgba(111, 102, 122, .16); min-height:114px; overflow:hidden;}
        .insight-item::after{content:''; position:absolute; right:-28px; bottom:-28px; width:92px; height:92px; background:radial-gradient(circle, rgba(107, 85, 180, .10) 0%, rgba(107, 85, 180, 0) 70%);}
        .insight-item:hover{background:#FFFDFF; border-color:rgba(107, 85, 180, .20);}
        .insight-icon{width:46px; height:46px; border-radius:16px; display:inline-flex; align-items:center; justify-content:center; color:#FFFDFF; background:linear-gradient(135deg, #6B55B4, #6B55B4); flex-shrink:0; font-size:.96rem; box-shadow:0 12px 22px rgba(107, 85, 180, .20);}
        .insight-meta{display:flex; flex-direction:column; gap:6px; position:relative; z-index:1;}
        .insight-kicker{display:inline-flex; align-items:center; width:max-content; padding:4px 10px; border-radius:999px; background:#F6F2FC; color:var(--accent); font-size:.74rem; font-weight:800; letter-spacing:.01em;}
        .insight-meta strong{display:block; color:var(--text); font-size:.98rem; line-height:1.35;}
        .insight-meta span{color:var(--muted); font-size:.84rem; line-height:1.45;}
        .overview-link{display:block; color:inherit; text-decoration:none;}
        .overview-link:hover .insight-item{transform:translateY(-2px); box-shadow:var(--shadow-hover);}

        .fleet-list{margin-top:4px;}
        .fleet-item{display:grid; grid-template-columns:48px 1fr auto; gap:12px; align-items:center;}
        .fleet-icon{width:48px; height:48px; border-radius:16px; background:#F6F2FC; border:1px solid var(--line); color:#342D40; display:inline-flex; align-items:center; justify-content:center; font-size:1.08rem;}
        .fleet-name{font-weight:700; color:#342D40; display:block;}
        .fleet-note{font-size:.84rem; color:var(--soft);}
        .fleet-value{font-weight:800; color:var(--text); min-width:24px; text-align:right;}
        .progress-line{margin-top:8px; width:100%; height:8px; border-radius:999px; background:#F6F2FC; overflow:hidden;}
        .progress-line span{display:block; height:100%; border-radius:999px; background:linear-gradient(90deg, #6B55B4, #A53D3D);}

        .tables-pair{display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px; margin-top:20px; align-items:stretch;}
        .tables-pair .panel-card{display:flex; flex-direction:column; min-height:430px;}
        .manager-mini-table{width:100%; border-collapse:separate; border-spacing:0; table-layout:fixed; margin-top:2px;}
        .manager-mini-table th,.manager-mini-table td{padding:14px 12px; border-bottom:1px solid #F6F2FC; font-size:.93rem; vertical-align:middle; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
        .manager-mini-table th{color:var(--muted); font-weight:700; background:#FFFDFF; text-align:left; padding-left:22px;}
        .manager-mini-table tbody tr{height:64px;}
        .manager-mini-table th:first-child{border-top-left-radius:16px;}
        .manager-mini-table th:last-child{border-top-right-radius:16px;}
        .manager-mini-table tbody tr:hover{background:#FFFDFF;}
        .empty-row{text-align:center; color:var(--soft); padding:34px 0 !important;}
        .table-footer{margin-top:auto; padding-top:10px;}
        .badge-soft{display:inline-flex; align-items:center; justify-content:center; min-width:66px; padding:7px 12px; border-radius:999px; background:rgba(107, 85, 180, .10); color:var(--accent); font-weight:700; font-size:.84rem;}
        .text-ok{color:#6B55B4; font-weight:800;}
        .text-danger-soft{color:#A53D3D; font-weight:800;}

        @media (max-width:1199px){
            .top-dashboard-grid{grid-template-columns:1fr;}
            .summary-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
            .main-grid{grid-template-columns:1fr;}
            .sub-grid{grid-template-columns:1fr;}
        }
        @media (max-width:1199px){
            .overview-grid,.manager-overview-row{grid-template-columns:repeat(2,minmax(0,1fr));}
        }
        @media (max-width:899px){
            .overview-grid,.manager-overview-row,.sub-grid{grid-template-columns:1fr;}
        }
        @media (max-width:767px){
            .dashboard-hero{flex-direction:column; align-items:flex-start;}
            .summary-grid,.tables-pair{grid-template-columns:1fr;}
        }
    </style>
    <link rel="stylesheet" href="../../assets/css/fix_result_column_v37.css">

<style>
table th:last-child,
table td:last-child,
.table th:last-child,
.table td:last-child {
    text-align: center !important;
    vertical-align: middle !important;
}
</style>

</head>
<body>
<?php render_navbar('dashboard'); ?>
<div class="container page-shell dashboard-page">
    <div class="dashboard-shell">
        <div class="dashboard-hero">
            <div>
                <span class="page-eyebrow"><i class="fa-solid fa-chart-line"></i> Dashboard overview</span>
                <h1>แดชบอร์ด</h1>
            </div>
            <div class="dashboard-date-pill"><i class="fa-regular fa-calendar"></i> ข้อมูลล่าสุดจากระบบเช่ารถ</div>
        </div>

        <div class="top-dashboard-grid">
            <div class="summary-grid">
                <a class="summary-link" href="payments.php">
                    <div class="summary-card">
                        <div class="summary-top">
                            <span class="summary-label">รายได้รวม</span>
                            <span class="summary-icon"><i class="fa-solid fa-wallet"></i></span>
                        </div>
                        <div class="summary-value money"><?= e(format_money($totalIncome)) ?></div>
                        <div class="summary-desc">ยอดชำระสะสมทั้งหมดของระบบ</div>
                    </div>
                </a>
                <a class="summary-link" href="rentals.php">
                    <div class="summary-card">
                        <div class="summary-top">
                            <span class="summary-label">กำลังดำเนินการ</span>
                            <span class="summary-icon"><i class="fa-solid fa-hourglass-half"></i></span>
                        </div>
                        <div class="summary-value"><?= e(number_format($activeRentals)) ?></div>
                        <div class="summary-desc">รวมรายการกำลังเช่าและจองล่วงหน้า</div>
                    </div>
                </a>
                <a class="summary-link" href="cars.php">
                    <div class="summary-card">
                        <div class="summary-top">
                            <span class="summary-label">พร้อมให้เช่า</span>
                            <span class="summary-icon"><i class="fa-solid fa-circle-check"></i></span>
                        </div>
                        <div class="summary-value"><?= e(number_format($availableCars)) ?></div>
                        <div class="summary-desc">รถที่พร้อมปล่อยเช่าได้ทันที</div>
                    </div>
                </a>
                <a class="summary-link" href="rentals.php?filter=overdue">
                    <div class="summary-card">
                        <div class="summary-top">
                            <span class="summary-label">คืนเกินกำหนด</span>
                            <span class="summary-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
                        </div>
                        <div class="summary-value"><?= e(number_format($overdueRentals)) ?></div>
                        <div class="summary-desc">รายการที่ผู้จัดการควรตรวจสอบทันที</div>
                    </div>
                </a>
            </div>

            <div class="panel-card manager-overview-panel">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">ภาพรวมงานที่ต้องติดตาม</h2>
                        <p class="panel-subtitle">กดเพื่อดูภาพรวม</p>
                    </div>
                </div>
                <div class="manager-overview-row">
                    <a class="manager-overview-card" href="rentals.php?filter=rented">
                        <span class="manager-overview-icon"><i class="fa-solid fa-key"></i></span>
                        <div class="manager-overview-meta">
                            <span class="manager-overview-tag">กำลังเช่า</span>
                            <span class="manager-overview-value"><?= e(number_format($rentedCars)) ?> คัน</span>
                            <span class="manager-overview-text">รถที่ลูกค้ารับรถไปใช้งานอยู่ในขณะนี้</span>
                        </div>
                    </a>
                    <a class="manager-overview-card" href="rentals.php?filter=booked">
                        <span class="manager-overview-icon"><i class="fa-solid fa-calendar-check"></i></span>
                        <div class="manager-overview-meta">
                            <span class="manager-overview-tag">จองล่วงหน้า</span>
                            <span class="manager-overview-value"><?= e(number_format($bookedRentals)) ?> รายการ</span>
                            <span class="manager-overview-text">รายการที่ยังไม่ถึงวันรับรถตามกำหนด</span>
                        </div>
                    </a>
                    <a class="manager-overview-card" href="rentals.php">
                        <span class="manager-overview-icon"><i class="fa-solid fa-clock"></i></span>
                        <div class="manager-overview-meta">
                            <span class="manager-overview-tag">ใกล้ครบกำหนด</span>
                            <span class="manager-overview-value"><?= e(number_format($nearDueRentals)) ?> รายการ</span>
                            <span class="manager-overview-text">รายการเช่าที่ควรติดตามภายใน 3 วันข้างหน้า</span>
                        </div>
                    </a>
                    <a class="manager-overview-card" href="return_inspections.php">
                        <span class="manager-overview-icon"><i class="fa-solid fa-car-burst"></i></span>
                        <div class="manager-overview-meta">
                            <span class="manager-overview-tag">พบความเสียหาย</span>
                            <span class="manager-overview-value"><?= e(number_format($damageCases)) ?> รายการ</span>
                            <span class="manager-overview-text">อ้างอิงจากผลตรวจสภาพรถคืนล่าสุดในระบบ</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <div class="main-grid">
            <div class="left-column">
                <div class="sub-grid">
                    <a class="panel-link" href="analytics.php">
                        <div class="panel-card clickable fill-height">
                            <div class="panel-head">
                                <div>
                                    <h2 class="panel-title">สรุปรายได้รายเดือน</h2>
                                </div>
                                <span class="panel-chip"><i class="fa-solid fa-chart-column"></i> Monthly revenue</span>
                            </div>
                            <div class="chart-box"><canvas id="monthlyRevenueChart"></canvas></div>
                            <div class="click-hint">กดเพื่อดูหน้าวิเคราะห์ข้อมูล</div>
                        </div>
                    </a>
                    <a class="panel-link" href="cars.php">
                        <div class="panel-card clickable fill-height">
                            <div class="panel-head">
                                <div>
                                    <h2 class="panel-title">สัดส่วนสถานะรถ</h2>
                                </div>
                                <span class="panel-chip"><i class="fa-solid fa-car-side"></i> Fleet status</span>
                            </div>
                            <div class="chart-box small"><canvas id="carStatusChart"></canvas></div>
                            <div class="click-hint">กดเพื่อดูหน้าจัดการรถยนต์</div>
                        </div>
                    </a>
                </div>
            </div>

        </div>

        <div class="tables-pair">
            <div class="panel-card">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">รายการเช่าล่าสุด</h2>
                    </div>
                </div>
                <table class="manager-mini-table">
                    <thead>
                        <tr>
                            <th>รหัสเช่า</th>
                            <th>ลูกค้า</th>
                            <th>รถ</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentRentals)): ?>
                            <tr><td colspan="4" class="empty-row">ไม่มีข้อมูล</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentRentals as $row): ?>
                                <tr onclick="window.location.href='rentals.php'" style="cursor:pointer;">
                                    <td><?= e($row['rental_id']) ?></td>
                                    <td><?= e($row['customer_name'] ?: '-') ?></td>
                                    <td><?= e($row['car_id']) ?></td>
                                    <td><span class="badge-soft"><?= e($row['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="table-footer"><div class="click-hint">กดที่รายการเพื่อเปิดหน้าจัดการการเช่า</div></div>
            </div>

            <div class="panel-card">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">สรุปตรวจรถคืนล่าสุด</h2>
                    </div>
                </div>
                <table class="manager-mini-table">
                    <thead>
                        <tr>
                            <th>รหัสตรวจ</th>
                            <th>รหัสเช่า</th>
                            <th>รถ</th>
                            <th>ผลตรวจ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentReturns)): ?>
                            <tr><td colspan="4" class="empty-row">ไม่มีข้อมูล</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentReturns as $row): ?>
                                <tr onclick="window.location.href='return_inspections.php'" style="cursor:pointer;">
                                    <td><?= e($row['inspection_id']) ?></td>
                                    <td><?= e($row['rental_id']) ?></td>
                                    <td><?= e($row['car_id']) ?></td>
                                    <td>
                                        <?php if (($row['has_damage'] ?? '') === 'Yes'): ?>
                                            <span class="text-danger-soft">เสียหาย <?= e(format_money((float) $row['fine_amount'])) ?></span>
                                        <?php else: ?>
                                            <span class="text-ok">ปกติ</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="table-footer"><div class="click-hint">กดที่รายการเพื่อเปิดหน้าตรวจสภาพรถคืน</div></div>
            </div>
        </div>
    </div>
</div>
<script>
const monthlyRevenueLabels = <?= json_encode($monthlyLabels, JSON_UNESCAPED_UNICODE) ?>;
const monthlyRevenueValues = <?= json_encode($monthlyAmounts, JSON_NUMERIC_CHECK) ?>;
const carStatusLabels = ['พร้อมให้เช่า', 'กำลังเช่า', 'ซ่อมบำรุง'];
const carStatusValues = [<?= $availableCars ?>, <?= $rentedCars ?>, <?= $maintenanceCars ?>];

document.getElementById('monthlyRevenueChart').style.cursor = 'pointer';
document.getElementById('monthlyRevenueChart').addEventListener('click', function(){ window.location.href='analytics.php'; });

Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
new Chart(document.getElementById('monthlyRevenueChart'), {
    type: 'bar',
    data: {
        labels: monthlyRevenueLabels,
        datasets: [{
            label: 'รายได้รายเดือน (บาท)',
            data: monthlyRevenueValues,
            backgroundColor: ['rgba(107, 85, 180, 0.88)','rgba(107, 85, 180, 0.9)','rgba(107, 85, 180, 0.86)','rgba(238, 233, 250, 0.82)','rgba(107, 85, 180, 0.8)','rgba(107, 85, 180, 0.9)'],
            borderRadius: 10,
            borderSkipped: false,
            maxBarThickness: 46
        }]
    },
    options: {
        responsive:true,
        maintainAspectRatio:false,
        plugins:{legend:{display:false}},
        scales:{
            x:{grid:{display:false}, ticks:{color:'#342D40'}},
            y:{beginAtZero:true, ticks:{color:'#342D40'}, grid:{color:'rgba(111, 102, 122, .16)'}}
        }
    }
});

document.getElementById('carStatusChart').style.cursor = 'pointer';
document.getElementById('carStatusChart').addEventListener('click', function(){ window.location.href='cars.php'; });

new Chart(document.getElementById('carStatusChart'), {
    type: 'bar',
    data: {
        labels: carStatusLabels,
        datasets: [{
            data: carStatusValues,
            backgroundColor: [
                'rgba(107, 85, 180, 0.88)',
                'rgba(107, 85, 180, 0.9)',
                'rgba(238, 233, 250, 0.82)'
            ],
            borderRadius: 12,
            borderSkipped: false,
            maxBarThickness: 56
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { color: '#342D40' } },
            y: { beginAtZero: true, grid: { color: 'rgba(111, 102, 122, .16)' }, ticks: { color: '#342D40', precision: 0 } }
        }
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
