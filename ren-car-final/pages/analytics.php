<?php
require_once '../config/db.php';
require_once '../includes/ui_helpers.php';
require_once '../includes/navbar.php';
require_once '../functions/rental_functions.php';
require_once '../functions/payment_functions.php';

syncRentalStatuses();
syncPaymentsWithRentals();

function fetch_scalar_value(PDO $pdo, string $sql): int|float|string|null {
    $stmt = $pdo->query($sql);
    if (!$stmt) {
        return null;
    }
    return $stmt->fetchColumn();
}

function translate_payment_types(?string $types): string {
    if ($types === null || trim($types) === '') {
        return '-';
    }

    $items = array_filter(array_map('trim', explode(',', $types)));
    if (empty($items)) {
        return '-';
    }

    return implode(', ', array_map('payment_type_label', $items));
}

$customerOptionsStmt = $pdo->query("SELECT customer_id, name FROM customers ORDER BY customer_id ASC");
$customerOptions = $customerOptionsStmt ? $customerOptionsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$search = trim($_GET['search'] ?? '');
$selectedCustomer = trim($_GET['customer_id'] ?? 'all');
$statusFilter = trim($_GET['status'] ?? 'all');
$paymentTypeFilter = trim($_GET['payment_type'] ?? 'all');
$selectedMonth = trim($_GET['month'] ?? 'all');
$minRentalsRaw = trim($_GET['min_rentals'] ?? 'all');
$minRentals = 'all';
if ($minRentalsRaw !== 'all' && $minRentalsRaw !== '') {
    $minRentals = (int) $minRentalsRaw;
    if ($minRentals < 1) {
        $minRentals = 1;
    }
    if ($minRentals > 4) {
        $minRentals = 4;
    }
}

$allowedStatuses = ['all', 'Booked', 'Rented', 'Completed'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

$allowedPaymentTypes = ['all', 'Cash', 'Bank Transfer', 'Credit Card', 'QR Payment'];
if (!in_array($paymentTypeFilter, $allowedPaymentTypes, true)) {
    $paymentTypeFilter = 'all';
}

$allowedMonths = ['all', '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];
if (!in_array($selectedMonth, $allowedMonths, true)) {
    $selectedMonth = 'all';
}

$availableCustomerIds = array_map(static fn(array $row): string => (string) ($row['customer_id'] ?? ''), $customerOptions);
if ($selectedCustomer === '') {
    $selectedCustomer = 'all';
}

$where = [];
$params = [];

if ($selectedCustomer !== 'all') {
    if (in_array($selectedCustomer, $availableCustomerIds, true)) {
        $where[] = 'c.customer_id = :customer_id';
        $params[':customer_id'] = $selectedCustomer;
    } else {
        $where[] = '(c.customer_id LIKE :customer_id_lookup OR c.name LIKE :customer_name_lookup)';
        $params[':customer_id_lookup'] = '%' . $selectedCustomer . '%';
        $params[':customer_name_lookup'] = '%' . $selectedCustomer . '%';
    }
}

if ($selectedMonth !== 'all') {
    $where[] = 'DATE_FORMAT(r.rental_date, "%m") = :selected_month';
    $params[':selected_month'] = $selectedMonth;
}

if ($statusFilter === 'Completed') {
    $where[] = "r.status IN ('Completed', 'Returned')";
} elseif ($statusFilter !== 'all') {
    $where[] = 'r.status = :status';
    $params[':status'] = $statusFilter;
}

if ($paymentTypeFilter !== 'all') {
    $where[] = 'p.payment_type = :payment_type';
    $params[':payment_type'] = $paymentTypeFilter;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$havingSql = $minRentals === 'all' ? '' : ' HAVING COUNT(r.rental_id) = :min_rentals';

$analysisSql = "SELECT c.customer_id, c.name AS customer_name,
                       COUNT(r.rental_id) AS rental_count,
                       COALESCE(SUM(r.total_price), 0) AS total_revenue,
                       MIN(r.rental_date) AS first_rental_date,
                       MAX(r.return_date) AS last_return_date,
                       GROUP_CONCAT(DISTINCT p.payment_type ORDER BY p.payment_type SEPARATOR ', ') AS payment_types
                FROM customers c
                INNER JOIN rentals r ON c.customer_id = r.customer_id
                LEFT JOIN payments p ON p.payment_id = REPLACE(r.rental_id, 'RENT', 'PAY')
                $whereSql
                GROUP BY c.customer_id, c.name" . $havingSql . "
                ORDER BY c.customer_id ASC";

$analysisStmt = $pdo->prepare($analysisSql);
foreach ($params as $key => $value) {
    $analysisStmt->bindValue($key, $value, PDO::PARAM_STR);
}
if ($minRentals !== 'all') {
    $analysisStmt->bindValue(':min_rentals', $minRentals, PDO::PARAM_INT);
}
$analysisStmt->execute();
$analysisRows = $analysisStmt->fetchAll(PDO::FETCH_ASSOC);

$chartSql = "SELECT DATE_FORMAT(r.rental_date, '%Y-%m') AS month_key,
                    DATE_FORMAT(r.rental_date, '%m/%Y') AS month_label,
                    COUNT(r.rental_id) AS rental_count,
                    COALESCE(SUM(r.total_price), 0) AS total_revenue
             FROM rentals r
             INNER JOIN customers c ON c.customer_id = r.customer_id
             LEFT JOIN payments p ON p.payment_id = REPLACE(r.rental_id, 'RENT', 'PAY')
             $whereSql
             GROUP BY DATE_FORMAT(r.rental_date, '%Y-%m'), DATE_FORMAT(r.rental_date, '%m/%Y')
             ORDER BY month_key ASC";

$chartStmt = $pdo->prepare($chartSql);
foreach ($params as $key => $value) {
    $chartStmt->bindValue($key, $value, PDO::PARAM_STR);
}
$chartStmt->execute();
$chartRows = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

$matchedCustomers = count($analysisRows);
$matchedRentals = array_sum(array_map(static fn(array $row): int => (int) ($row['rental_count'] ?? 0), $analysisRows));
$matchedRevenue = array_sum(array_map(static fn(array $row): float => (float) ($row['total_revenue'] ?? 0), $analysisRows));
$bookingRevenue = $matchedRevenue;

$totalCars = (int) (fetch_scalar_value($pdo, 'SELECT COUNT(*) FROM cars') ?? 0);
$totalCustomers = (int) (fetch_scalar_value($pdo, 'SELECT COUNT(*) FROM customers') ?? 0);
$totalEmployees = (int) (fetch_scalar_value($pdo, 'SELECT COUNT(*) FROM employees') ?? 0);
$totalRentals = (int) (fetch_scalar_value($pdo, 'SELECT COUNT(*) FROM rentals') ?? 0);
$totalPayments = (int) (fetch_scalar_value($pdo, 'SELECT COUNT(*) FROM payments') ?? 0);
$totalReviews = (int) (fetch_scalar_value($pdo, 'SELECT COUNT(*) FROM reviews') ?? 0);

$chartLabels = array_map(static fn(array $row): string => $row['month_label'], $chartRows);
$chartRevenue = array_map(static fn(array $row): float => (float) $row['total_revenue'], $chartRows);
$chartRentalCounts = array_map(static fn(array $row): int => (int) $row['rental_count'], $chartRows);
$activeTopic = 'customer_id';
foreach (['customer_id' => $selectedCustomer, 'month' => $selectedMonth, 'status' => $statusFilter, 'payment_type' => $paymentTypeFilter, 'min_rentals' => $minRentals] as $topic => $value) {
    if ($value !== 'all') {
        $activeTopic = $topic;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>วิเคราะห์ข้อมูล - <?= e(app_name()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/design_tokens.css" rel="stylesheet">
    <link href="../assets/css/analytics_hts.css?v=icon-refresh-2" rel="stylesheet">
</head>
<body class="analytics-page">
<?php render_navbar('analytics'); ?>

<div class="container page-shell analytics-page">
    <div class="page-header analytics-page-header mb-3">
        <div>
            <div class="page-eyebrow"><i class="fa-solid fa-chart-column"></i> การจัดการข้อมูลเชิงวิเคราะห์</div>
            <h1 class="page-title">วิเคราะห์ข้อมูลการเช่า</h1>
            <p class="analytics-page-description">เลือกหัวข้อที่ต้องการเพื่อค้นหาและกรองข้อมูลได้รวดเร็ว</p>
        </div>
    </div>

    <section class="hts-topic-card" aria-labelledby="analysisTopicTitle">
        <h2 id="analysisTopicTitle" class="hts-topic-heading">เลือกหัวข้อการวิเคราะห์</h2>
        <div class="hts-topic-list" role="tablist" aria-label="หัวข้อการวิเคราะห์">
            <?php foreach ([
                'customer_id' => ['fa-user', 'ลูกค้า'],
                'month' => ['fa-calendar-days', 'รายเดือน'],
                'status' => ['fa-list-check', 'สถานะการเช่า'],
                'payment_type' => ['fa-credit-card', 'ช่องทางชำระเงิน'],
                'min_rentals' => ['fa-arrow-trend-up', 'จำนวนครั้งการจอง']
            ] as $topicKey => [$topicIcon, $topicLabel]): ?>
                <button type="button" role="tab" class="hts-topic-button <?= $activeTopic === $topicKey ? 'is-active' : '' ?>" data-topic="<?= e($topicKey) ?>" aria-selected="<?= $activeTopic === $topicKey ? 'true' : 'false' ?>">
                    <i class="fa-solid <?= e($topicIcon) ?>" aria-hidden="true"></i> <?= e($topicLabel) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="hts-filter-card" aria-labelledby="contextFilterTitle">
        <div class="hts-filter-header">
            <div><h2 id="contextFilterTitle" class="hts-topic-heading">ตัวกรองที่เกี่ยวข้อง</h2><p>แสดงเฉพาะตัวกรองของหัวข้อที่เลือก เพื่อลดข้อมูลที่ไม่จำเป็น</p></div>
        </div>
        <form method="get" id="analyticsFilterForm" class="hts-context-row" novalidate>
            <div class="hts-filter-group" data-filter="customer_id" <?= $activeTopic !== 'customer_id' ? 'hidden' : '' ?>>
                <label class="form-label" for="customer_id">ค้นหาลูกค้า</label>
                <div class="hts-customer-search-wrap">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input class="form-control" type="text" id="customer_id" name="customer_id" list="customerSuggestions" data-label="รหัสหรือชื่อลูกค้า" value="<?= $selectedCustomer === 'all' ? '' : e($selectedCustomer) ?>" placeholder="พิมพ์รหัสหรือชื่อลูกค้า" autocomplete="off">
                </div>
                <datalist id="customerSuggestions">
                    <?php foreach ($customerOptions as $customer): ?>
                        <option value="<?= e($customer['customer_id']) ?>" label="<?= e($customer['name']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <small class="hts-filter-explanation">พิมพ์ชื่อหรือรหัสลูกค้าได้ โดยไม่ต้องเลื่อนหารายชื่อ</small>
            </div>

            <div class="hts-filter-group" data-filter="month" <?= $activeTopic !== 'month' ? 'hidden' : '' ?>>
                <label class="form-label" for="month">ค้นหารายเดือน</label>
                <select class="form-select" id="month" name="month" data-label="เดือน">
                    <option value="all" <?= $selectedMonth === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                    <option value="01" <?= $selectedMonth === '01' ? 'selected' : '' ?>>มกราคม</option>
                    <option value="02" <?= $selectedMonth === '02' ? 'selected' : '' ?>>กุมภาพันธ์</option>
                    <option value="03" <?= $selectedMonth === '03' ? 'selected' : '' ?>>มีนาคม</option>
                    <option value="04" <?= $selectedMonth === '04' ? 'selected' : '' ?>>เมษายน</option>
                    <option value="05" <?= $selectedMonth === '05' ? 'selected' : '' ?>>พฤษภาคม</option>
                    <option value="06" <?= $selectedMonth === '06' ? 'selected' : '' ?>>มิถุนายน</option>
                    <option value="07" <?= $selectedMonth === '07' ? 'selected' : '' ?>>กรกฎาคม</option>
                    <option value="08" <?= $selectedMonth === '08' ? 'selected' : '' ?>>สิงหาคม</option>
                    <option value="09" <?= $selectedMonth === '09' ? 'selected' : '' ?>>กันยายน</option>
                    <option value="10" <?= $selectedMonth === '10' ? 'selected' : '' ?>>ตุลาคม</option>
                    <option value="11" <?= $selectedMonth === '11' ? 'selected' : '' ?>>พฤศจิกายน</option>
                    <option value="12" <?= $selectedMonth === '12' ? 'selected' : '' ?>>ธันวาคม</option>
                </select>
                <small class="hts-filter-explanation">กรองข้อมูลด้วยเดือนของวันที่เริ่มเช่า</small>
            </div>

            <div class="hts-filter-group" data-filter="status" <?= $activeTopic !== 'status' ? 'hidden' : '' ?>>
                <label class="form-label" for="status">สถานะการเช่า</label>
                <select class="form-select" id="status" name="status" data-label="สถานะการเช่า">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                    <option value="Booked" <?= $statusFilter === 'Booked' ? 'selected' : '' ?>>จองล่วงหน้า</option>
                    <option value="Rented" <?= $statusFilter === 'Rented' ? 'selected' : '' ?>>กำลังเช่า</option>
                    <option value="Completed" <?= $statusFilter === 'Completed' ? 'selected' : '' ?>>เสร็จสิ้น</option>
                </select>
                <small class="hts-filter-explanation">แสดงรายการตามสถานะที่ระบบรองรับเท่านั้น</small>
            </div>

            <div class="hts-filter-group" data-filter="payment_type" <?= $activeTopic !== 'payment_type' ? 'hidden' : '' ?>>
                <label class="form-label" for="payment_type">ช่องทางชำระเงิน</label>
                <select class="form-select" id="payment_type" name="payment_type" data-label="ช่องทางชำระเงิน">
                    <option value="all" <?= $paymentTypeFilter === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                    <option value="Cash" <?= $paymentTypeFilter === 'Cash' ? 'selected' : '' ?>>เงินสด</option>
                    <option value="Bank Transfer" <?= $paymentTypeFilter === 'Bank Transfer' ? 'selected' : '' ?>>โอนผ่านธนาคาร</option>
                    <option value="Credit Card" <?= $paymentTypeFilter === 'Credit Card' ? 'selected' : '' ?>>บัตรเครดิต</option>
                    <option value="QR Payment" <?= $paymentTypeFilter === 'QR Payment' ? 'selected' : '' ?>>ชำระผ่าน QR</option>
                </select>
                <small class="hts-filter-explanation">แสดงเฉพาะช่องทางชำระเงินที่ฐานข้อมูลรองรับ</small>
            </div>

            <div class="hts-filter-group" data-filter="min_rentals" <?= $activeTopic !== 'min_rentals' ? 'hidden' : '' ?>>
                <label class="form-label" for="min_rentals">จำนวนครั้งการจอง</label>
                <select class="form-select" id="min_rentals" name="min_rentals" data-label="จำนวนครั้งการจอง">
                    <option value="all" <?= $minRentals === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                    <option value="1" <?= $minRentals === 1 ? 'selected' : '' ?>>1 ครั้ง</option>
                    <option value="2" <?= $minRentals === 2 ? 'selected' : '' ?>>2 ครั้ง</option>
                    <option value="3" <?= $minRentals === 3 ? 'selected' : '' ?>>3 ครั้ง</option>
                    <option value="4" <?= $minRentals === 4 ? 'selected' : '' ?>>4 ครั้ง</option>
                </select>
                <small class="hts-filter-explanation">กรองลูกค้าที่มีจำนวนการจองตรงกับค่าที่เลือก</small>
            </div>

            <div class="hts-actions">
                <button type="submit" id="analyticsSearchButton" class="btn btn-primary hts-search-button"><i class="fa-solid fa-magnifying-glass me-2"></i>ค้นหา</button>
                <button type="button" id="analyticsResetButton" class="btn btn-outline-secondary">ล้างค่า</button>
            </div>
        </form>
        <div id="analyticsFilterError" class="hts-inline-error" role="alert" hidden></div>
    </section>

    <div id="analyticsResultState" class="hts-result-state" role="status" aria-live="polite"><strong>พบ <?= e(number_format($matchedCustomers)) ?> ลูกค้า</strong> จากเงื่อนไขปัจจุบัน รวม <?= e(number_format($matchedRentals)) ?> รายการเช่า</div>

    <div class="analytics-hero-row mb-4">
        <div class="hero-card analytics-summary-panel">
            <h3 class="chart-title">ภาพรวมผลการค้นหา</h3>
            <div class="analytics-meta analytics-meta-compact">
                <div class="analytics-metric"><i class="fa-solid fa-user-group" aria-hidden="true"></i><span>ลูกค้าทั้งหมด</span><strong><?= e(number_format($matchedCustomers)) ?> ราย</strong></div>
                <div class="analytics-metric"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i><span>รายการจองทั้งหมด</span><strong><?= e(number_format($matchedRentals)) ?> รายการ</strong></div>
                <div class="analytics-metric"><i class="fa-solid fa-coins" aria-hidden="true"></i><span>รายได้รวม</span><strong><?= e(format_money($bookingRevenue)) ?></strong></div>
            </div>
        </div>
        <div class="chart-card analytics-chart-panel">
            <div class="chart-header"><div><h3 class="chart-title">รายได้รายเดือน</h3><div class="chart-caption">หน่วย: บาท</div></div></div>
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <div id="analyticsResults" class="table-card analytics-results-table">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <h3 class="chart-title mb-1">ผลลัพธ์รายได้ของระบบการจอง</h3>
                <div class="chart-caption"></div>
            </div>
            
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>รหัสลูกค้า</th>
                        <th>ชื่อลูกค้า</th>
                        <th>จำนวนการจอง</th>
                        <th>รายได้รวม</th>
                        <th>จองครั้งแรก</th>
                        <th>คืนล่าสุด</th>
                        <th>ช่องทางชำระที่ใช้</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($analysisRows)): ?>
                        <?php foreach ($analysisRows as $row): ?>
                            <tr>
                                <td><?= e($row['customer_id']) ?></td>
                                <td><?= e($row['customer_name']) ?></td>
                                <td><?= e(number_format((int) $row['rental_count'])) ?></td>
                                <td class="text-currency"><?= e(format_money($row['total_revenue'])) ?></td>
                                <td><?= e(format_display_date($row['first_rental_date'])) ?></td>
                                <td><?= e(format_display_date($row['last_return_date'])) ?></td>
                                <td><?= e(translate_payment_types($row['payment_types'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center empty-state">ไม่พบผลลัพธ์ที่ตรงกับเงื่อนไขที่เลือก</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="analyticsToast" class="hts-toast" role="status" aria-live="polite" hidden></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/analytics_hts.js"></script>
<script>
const revenueLabels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
const revenueData = <?= json_encode($chartRevenue, JSON_UNESCAPED_UNICODE) ?>;
const rentalCountData = <?= json_encode($chartRentalCounts, JSON_UNESCAPED_UNICODE) ?>;

const chartCanvas = document.getElementById('revenueChart');
if (chartCanvas) {
    const designStyles = getComputedStyle(document.body);
    const chartPrimary = designStyles.getPropertyValue('--hts-primary').trim();
    const chartMuted = designStyles.getPropertyValue('--hts-muted').trim();
    const chartText = designStyles.getPropertyValue('--hts-text').trim();
Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    new Chart(chartCanvas, {
        type: 'bar',
        data: {
            labels: revenueLabels,
            datasets: [{
                label: 'รายได้รวม (บาท)',
                borderColor: chartPrimary,
                data: revenueData,
                backgroundColor: chartPrimary,
                borderRadius: 12,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        usePointStyle: true,
                        boxWidth: 10,
                        color: chartText
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const amount = Number(context.raw || 0).toLocaleString('th-TH', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                            const rentals = rentalCountData[context.dataIndex] || 0;
                            return `รายได้ ${amount} บาท | จำนวนเช่า ${rentals} รายการ`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: chartMuted,
                        callback: value => Number(value).toLocaleString('th-TH')
                    },
                    grid: {
                        color: designStyles.getPropertyValue('--hts-border').trim()
                    }
                },
                x: {
                    ticks: {
                        color: chartPrimary
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}
</script>
</body>
</html>
