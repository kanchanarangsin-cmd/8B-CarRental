<?php
require_once '../config/db.php';
require_once '../includes/ui_helpers.php';
require_once '../includes/navbar.php';
require_once '../functions/rental_functions.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}

$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$status_filter = is_string($_GET['status_filter'] ?? null) ? trim($_GET['status_filter']) : '';
$rate_query = http_build_query(array_filter(
    ['search' => $search, 'status_filter' => $status_filter],
    static function ($value) { return $value !== ''; }
));
$rate_return_url = 'cars.php' . ($rate_query !== '' ? '?' . $rate_query : '') . '#car-rate-list';
$rate_error = '';
$is_rate_update = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rate']);

if ($is_rate_update) {
    $rate_car_id = is_string($_POST['car_id'] ?? null) ? trim($_POST['car_id']) : '';
    $new_daily_rate = is_string($_POST['daily_rate'] ?? null) ? trim($_POST['daily_rate']) : '';

    if ($rate_car_id === '' || strlen($rate_car_id) > 30) {
        $rate_error = 'ไม่พบรหัสรถ กรุณาเลือกรถอีกครั้ง';
    } elseif (!preg_match('/\A[0-9]{1,8}(?:\.[0-9]{1,2})?\z/', $new_daily_rate)) {
        $rate_error = 'กรุณากรอกค่าเช่าตั้งแต่ 0 ถึง 99,999,999.99 บาท โดยมีทศนิยมไม่เกิน 2 ตำแหน่ง';
    } else {
        try {
            $rate_stmt = $conn->prepare("UPDATE cars SET daily_rate = ? WHERE car_id = ?");
            if ($rate_stmt && $rate_stmt->bind_param('ss', $new_daily_rate, $rate_car_id) && $rate_stmt->execute()) {
                header('Location: ' . $rate_return_url, true, 303);
                exit;
            }
        } catch (mysqli_sql_exception $exception) {
            error_log($exception->getMessage());
        }
        $rate_error = 'บันทึกค่าเช่าไม่สำเร็จ กรุณาลองอีกครั้ง';
    }
}

if (!$is_rate_update) {
    ensureMaintenanceTable($conn);
    syncRentalStatuses();
}

function generateCarID($conn) {
    $sql = "SELECT car_id FROM cars WHERE car_id LIKE 'CAR%' ORDER BY car_id DESC LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $row = $result->fetch_assoc()) {
        $last = $row['car_id'];
        $num = intval(substr($last, 3)) + 1;
    } else {
        $num = 1;
    }

    return 'CAR' . str_pad($num, 3, '0', STR_PAD_LEFT);
}

function bindParamsDynamic($stmt, $types, $params) {
    $bind_names = [];
    $bind_names[] = $types;

    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }

    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

function maintenanceTableExists($conn) {
    $check = $conn->query("SHOW TABLES LIKE 'maintenance_history'");
    return $check && $check->num_rows > 0;
}

function ensureMaintenanceTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS maintenance_history (
        repair_id VARCHAR(10) NOT NULL PRIMARY KEY,
        car_id VARCHAR(30) NOT NULL,
        repair_date DATE NOT NULL,
        repair_detail VARCHAR(255) NOT NULL,
        repair_cost DECIMAL(10,2) DEFAULT 0,
        repair_status VARCHAR(30) DEFAULT 'Completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (car_id),
        CONSTRAINT fk_maintenance_car FOREIGN KEY (car_id) REFERENCES cars(car_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    $conn->query($sql);
}

function generateRepairID($conn) {
    $result = $conn->query("SELECT repair_id FROM maintenance_history WHERE repair_id LIKE 'REP%' ORDER BY CAST(SUBSTRING(repair_id, 4) AS UNSIGNED) DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $num = intval(substr($row['repair_id'], 3)) + 1;
    } else {
        $num = 1;
    }
    return 'REP' . str_pad($num, 3, '0', STR_PAD_LEFT);
}

function logMaintenanceStart($conn, $car_id) {
    ensureMaintenanceTable($conn);

syncRentalStatuses();
    if (!maintenanceTableExists($conn)) {
        return;
    }
    $repair_id = generateRepairID($conn);
    $repair_date = date('Y-m-d');
    $repair_detail = 'บันทึกอัตโนมัติจากการเปลี่ยนสถานะเป็นซ่อมบำรุง';
    $repair_cost = 0;
    $repair_status = 'Completed';

    $stmt = $conn->prepare("INSERT INTO maintenance_history (repair_id, car_id, repair_date, repair_detail, repair_cost, repair_status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssis', $repair_id, $car_id, $repair_date, $repair_detail, $repair_cost, $repair_status);
    $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_rate_update) {
    $car_id = trim($_POST['car_id'] ?? '');
    $license_plate = trim($_POST['license_plate'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $seats = trim($_POST['seats'] ?? '');
    $daily_rate = trim($_POST['daily_rate'] ?? '');
    $status = trim($_POST['status'] ?? 'Available');
    $mileage = trim($_POST['mileage'] ?? '');

    if (isset($_POST['submit'])) {
        $new_id = generateCarID($conn);
        $stmt = $conn->prepare("INSERT INTO cars (car_id, license_plate, brand, model, year, color, seats, daily_rate, status, mileage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssss", $new_id, $license_plate, $brand, $model, $year, $color, $seats, $daily_rate, $status, $mileage);
        $stmt->execute();
    }

    if (isset($_POST['update'])) {
        if ($car_id === '') {
            die('Car ID is required for update.');
        }

        $old_status = null;
        $old_stmt = $conn->prepare("SELECT status FROM cars WHERE car_id = ? LIMIT 1");
        $old_stmt->bind_param("s", $car_id);
        $old_stmt->execute();
        $old_result = $old_stmt->get_result();
        if ($old_row = $old_result->fetch_assoc()) {
            $old_status = $old_row['status'];
        }

        $stmt = $conn->prepare("UPDATE cars SET license_plate = ?, brand = ?, model = ?, year = ?, color = ?, seats = ?, daily_rate = ?, status = ?, mileage = ? WHERE car_id = ?");
        $stmt->bind_param("ssssssssss", $license_plate, $brand, $model, $year, $color, $seats, $daily_rate, $status, $mileage, $car_id);
        $stmt->execute();

        if ($old_status !== 'Maintenance' && $status === 'Maintenance') {
            logMaintenanceStart($conn, $car_id);
        }
    }
}

if (isset($_GET['delete']) && !$is_rate_update) {
    $delete_id = trim($_GET['delete']);
    if ($delete_id !== '') {
        $stmt = $conn->prepare("DELETE FROM cars WHERE car_id = ?");
        $stmt->bind_param("s", $delete_id);
        $stmt->execute();
    }
}

if (maintenanceTableExists($conn)) {
    $sql = "SELECT c.car_id, c.license_plate, c.brand, c.model, c.year, c.color, c.seats, c.daily_rate, c.status, c.mileage,
                   COALESCE(m.repair_count, 0) AS repair_count
            FROM cars c
            LEFT JOIN (
                SELECT car_id, COUNT(*) AS repair_count
                FROM maintenance_history
                GROUP BY car_id
            ) m ON c.car_id = m.car_id
            WHERE 1=1";
} else {
    $sql = "SELECT c.car_id, c.license_plate, c.brand, c.model, c.year, c.color, c.seats, c.daily_rate, c.status, c.mileage,
                   0 AS repair_count
            FROM cars c
            WHERE 1=1";
}

$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (c.car_id LIKE ? OR c.license_plate LIKE ? OR c.brand LIKE ? OR c.model LIKE ? OR c.year LIKE ? OR c.status LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssssss';
}

if ($status_filter !== '') {
    $sql .= ' AND c.status = ?';
    $params[] = $status_filter;
    $types .= 's';
}

$sql .= ' ORDER BY CAST(SUBSTRING(c.car_id, 4) AS UNSIGNED) DESC';

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    bindParamsDynamic($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$total = 0;
$available = 0;
$rented = 0;
$maintenance = 0;

$chart_result = $conn->query("SELECT status, COUNT(*) AS total FROM cars GROUP BY status ORDER BY status ASC");
if ($chart_result) {
    while ($row = $chart_result->fetch_assoc()) {
        $total += (int)$row['total'];
        if ($row['status'] === 'Available') { $available = (int)$row['total']; }
        if ($row['status'] === 'Rented') { $rented = (int)$row['total']; }
        if ($row['status'] === 'Maintenance') { $maintenance = (int)$row['total']; }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รถยนต์ - <?= e(app_name()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=dc36a003b825" rel="stylesheet">
    <link href="../assets/css/design_tokens.css?v=dc36a003b825" rel="stylesheet">
    <link href="../assets/css/iteration_actions.css?v=final4" rel="stylesheet">
</head>
<body>
<?php render_navbar('cars'); ?>

<div class="container page-shell">
    <div class="page-header">
        <div>
            <div class="page-eyebrow"><i class="fa-solid fa-car-side"></i> จัดการข้อมูลรถ</div>
            <h1 class="page-title">จัดการรถยนต์</h1>
            <p class="page-subtitle"></p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#carModal" onclick="openAdd()">
            <i class="fa-solid fa-plus"></i> เพิ่มรถใหม่
        </button>
    </div>

    <div class="layout-grid two-one mb-4">
        <div class="hero-card equal-card-block">
            <div class="hero-label"><i class="fa-solid fa-car-side"></i> ภาพรวมรถยนต์</div>
            <div class="hero-value"><?= e($total) ?></div>
            <p class="mb-0 mt-2">จำนวนรถทั้งหมดในระบบเช่ารถยนต์</p>
            <div class="summary-list">
                <div class="summary-chip">
                    <small>พร้อมให้เช่า</small>
                    <strong><?= e($available) ?></strong>
                </div>
                <div class="summary-chip">
                    <small>กำลังเช่า</small>
                    <strong><?= e($rented) ?></strong>
                </div>
                <div class="summary-chip">
                    <small>ซ่อมบำรุง</small>
                    <strong><?= e($maintenance) ?></strong>
                </div>
            </div>
        </div>

        <div class="chart-card chart-panel-md equal-card-block">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title">สถานะรถยนต์</h3>
                </div>
                <span class="quick-stat"><i class="fa-solid fa-chart-column"></i> อัปเดตล่าสุด</span>
            </div>
            <canvas id="carStatusChart"></canvas>
        </div>
    </div>

    <section class="table-card" id="car-rate-list" tabindex="-1" aria-label="รายการรถยนต์">
        <?php if ($rate_error !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= e($rate_error) ?></div>
        <?php endif; ?>
        <form method="GET" action="cars.php#car-rate-list" class="toolbar-row hts-car-filters">
            <div>
                <label class="form-label" for="search">ค้นหารถยนต์</label>
                <input type="text" id="search" name="search" class="form-control" placeholder="รหัสรถ ทะเบียน ยี่ห้อ หรือรุ่น" value="<?= e($search) ?>">
            </div>
            <div>
                <label class="form-label" for="status_filter">กรองตามสถานะ</label>
                <select id="status_filter" name="status_filter" class="form-select">
                    <option value="">ทุกสถานะ</option>
                    <option value="Available" <?= $status_filter === 'Available' ? 'selected' : '' ?>>พร้อมให้เช่า</option>
                    <option value="Rented" <?= $status_filter === 'Rented' ? 'selected' : '' ?>>กำลังเช่า</option>
                    <option value="Maintenance" <?= $status_filter === 'Maintenance' ? 'selected' : '' ?>>ซ่อมบำรุง</option>
                </select>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> ค้นหา</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>รหัสรถ</th>
                        <th>ทะเบียน</th>
                        <th scope="col">
                            ค่าเช่าต่อวัน
                            <small class="hts-rate-hint">กดราคาเพื่อแก้ไข</small>
                        </th>
                        <th>ยี่ห้อ</th>
                        <th>รุ่น</th>
                        <th>ปี</th>
                        <th>สี</th>
                        <th>ที่นั่ง</th>
                        <th>สถานะ</th>
                        <th>เลขไมล์</th>
                        <th>จำนวนครั้งที่ซ่อม</th>
                        <th>การทำงาน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $car_count = 0; ?>
                    <?php if ($result): ?>
                        <?php while ($car = $result->fetch_assoc()): $car_count++; ?>
                            <tr>
                                <td><?= e($car['car_id']) ?></td>
                                <td><?= e($car['license_plate']) ?></td>
                                <td class="text-currency">
                                    <button type="button" class="hts-rate-edit" aria-label="<?= e(format_money($car['daily_rate'])) ?> — แก้ไขค่าเช่ารถ <?= e($car['car_id']) ?>"
                                            data-bs-toggle="modal" data-bs-target="#carRateModal"
                                            data-car-id="<?= e($car['car_id']) ?>" data-plate="<?= e($car['license_plate']) ?>"
                                            data-daily-rate="<?= e($car['daily_rate']) ?>">
                                        <span><?= e(format_money($car['daily_rate'])) ?></span>
                                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                    </button>
                                </td>
                                <td><?= e($car['brand']) ?></td>
                                <td><?= e($car['model']) ?></td>
                                <td><?= e($car['year']) ?></td>
                                <td><?= e($car['color']) ?></td>
                                <td><?= e($car['seats']) ?></td>
                                <td><span class="status-badge <?= e(status_badge_class($car['status'])) ?>"><?= e(status_label($car['status'])) ?></span></td>
                                <td><?= e(number_format((float)$car['mileage'])) ?> กม.</td>
                                <td><?= e((int)($car['repair_count'] ?? 0)) ?> ครั้ง</td>
                                <td>
                                    <div class="action-group">
                                        <button type="button" class="btn btn-primary btn-edit btn-sm" onclick='editCar(<?= e(json_encode($car, JSON_UNESCAPED_UNICODE)) ?>)'>
                                            <i class="fa-solid fa-pen"></i> แก้ไข
                                        </button>
                                        <a href="?delete=<?= urlencode($car['car_id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('ยืนยันการลบข้อมูลรถคันนี้หรือไม่?')">
                                            <i class="fa-solid fa-trash"></i> ลบ
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>

                    <?php if ($car_count === 0): ?>
                        <tr>
                            <td colspan="12" class="text-center empty-state">ไม่พบข้อมูลรถยนต์ตามเงื่อนไขที่ค้นหา</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="modal fade" id="carModal" tabindex="-1" aria-hidden="true" aria-labelledby="modalTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">เพิ่มรถใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="car_id" id="car_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="license_plate">ทะเบียนรถ</label>
                            <input type="text" name="license_plate" id="license_plate" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="brand">ยี่ห้อ</label>
                            <input type="text" name="brand" id="brand" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="model">รุ่น</label>
                            <input type="text" name="model" id="model" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="year">ปี</label>
                            <input type="number" name="year" id="year" class="form-control" min="1900" max="2100" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="color">สี</label>
                            <input type="text" name="color" id="color" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="seats">จำนวนที่นั่ง</label>
                            <input type="number" name="seats" id="seats" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="daily_rate">ค่าเช่าต่อวัน</label>
                            <input type="number" step="0.01" name="daily_rate" id="daily_rate" class="form-control" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="status">สถานะ</label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="Available">พร้อมให้เช่า</option>
                                <option value="Rented">กำลังเช่า</option>
                                <option value="Maintenance">ซ่อมบำรุง</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="mileage">เลขไมล์</label>
                            <input type="number" name="mileage" id="mileage" class="form-control" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="submit" id="submitBtn" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="carRateModal" tabindex="-1" aria-hidden="true" aria-labelledby="carRateModalTitle" aria-describedby="carRateVehicle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= e($rate_return_url) ?>">
                <input type="hidden" name="update_rate" value="1">
                <input type="hidden" name="car_id" id="car_rate_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="carRateModalTitle">แก้ไขค่าเช่าต่อวัน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    <p id="carRateVehicle" class="mb-3"></p>
                    <label class="form-label" for="car_rate_amount">ค่าเช่าต่อวัน (บาท)</label>
                    <input type="number" id="car_rate_amount" name="daily_rate" class="form-control"
                           inputmode="decimal" min="0" max="99999999.99" step="0.01" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกค่าเช่า</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const carChartColors = <?= json_encode(car_chart_palette(), JSON_UNESCAPED_UNICODE) ?>;

Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
new Chart(document.getElementById('carStatusChart'), {
    type: 'bar',
    data: {
        labels: ['พร้อมให้เช่า', 'กำลังเช่า', 'ซ่อมบำรุง'],
        datasets: [{
            label: 'จำนวนรถ',
            data: [<?= (int)$available ?>, <?= (int)$rented ?>, <?= (int)$maintenance ?>],
            backgroundColor: carChartColors,
            borderRadius: 12,
            borderSkipped: false,
            maxBarThickness: 42
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
                ticks: { precision: 0 },
                grid: { color: 'rgba(111, 102, 122, 0.12)' }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});

function openAdd() {
    document.getElementById('modalTitle').innerText = 'เพิ่มรถใหม่';
    document.getElementById('submitBtn').name = 'submit';
    document.getElementById('submitBtn').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> บันทึกข้อมูล';

    document.getElementById('car_id').value = '';
    document.getElementById('license_plate').value = '';
    document.getElementById('brand').value = '';
    document.getElementById('model').value = '';
    document.getElementById('year').value = '';
    document.getElementById('color').value = '';
    document.getElementById('seats').value = '';
    document.getElementById('daily_rate').value = '';
    document.getElementById('status').value = 'Available';
    document.getElementById('mileage').value = '';
}

function editCar(car) {
    document.getElementById('modalTitle').textContent = 'แก้ไขข้อมูลรถ';
    document.getElementById('submitBtn').name = 'update';
    document.getElementById('submitBtn').innerHTML = '<i class="fa-solid fa-pen-to-square"></i> อัปเดตข้อมูล';

    document.getElementById('car_id').value = car.car_id;
    document.getElementById('license_plate').value = car.license_plate;
    document.getElementById('brand').value = car.brand;
    document.getElementById('model').value = car.model;
    document.getElementById('year').value = car.year;
    document.getElementById('color').value = car.color;
    document.getElementById('seats').value = car.seats;
    document.getElementById('daily_rate').value = car.daily_rate;
    document.getElementById('status').value = car.status;
    document.getElementById('mileage').value = car.mileage;

    bootstrap.Modal.getOrCreateInstance(document.getElementById('carModal')).show();
}

const carRateModal = document.getElementById('carRateModal');
carRateModal.addEventListener('show.bs.modal', (event) => {
    if (!event.relatedTarget) {
        event.preventDefault();
        return;
    }
    const car = event.relatedTarget.dataset;
    document.getElementById('car_rate_id').value = car.carId;
    document.getElementById('carRateVehicle').textContent = `${car.carId} · ทะเบียน ${car.plate}`;
    document.getElementById('car_rate_amount').value = car.dailyRate;
});
carRateModal.addEventListener('shown.bs.modal', () => {
    document.getElementById('car_rate_amount').focus();
});
</script>
</body>
</html>
