<?php
require_once '../config/db.php';
require_once '../includes/ui_helpers.php';
require_once '../includes/navbar.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}

function generateEmployeeID($conn) {
    $sql = "SELECT employee_id FROM employees WHERE employee_id LIKE 'EMP%' ORDER BY employee_id DESC LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $row = $result->fetch_assoc()) {
        $last = $row['employee_id'];
        $num = intval(substr($last, 3)) + 1;
    } else {
        $num = 1;
    }

    return 'EMP' . str_pad($num, 3, '0', STR_PAD_LEFT);
}

function getPositionsLocal($conn) {
    $positions = [];
    $result = $conn->query("SELECT DISTINCT position FROM employees WHERE position IS NOT NULL AND position <> '' ORDER BY position ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $positions[] = $row['position'];
        }
    }
    return $positions;
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

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get' && isset($_GET['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $id = trim($_GET['id']);

    $stmt = $conn->prepare("SELECT employee_id, name, position, status FROM employees WHERE employee_id = ?");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();

    echo json_encode($employee ?: null, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = trim($_POST['employee_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');

    if (isset($_POST['submit'])) {
        $new_id = generateEmployeeID($conn);
        $stmt = $conn->prepare("INSERT INTO employees (employee_id, name, position, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $new_id, $name, $position, $status);
        $stmt->execute();
    }

    if (isset($_POST['update'])) {
        if ($employee_id === '') {
            die('Employee ID is required for update.');
        }

        $stmt = $conn->prepare("UPDATE employees SET name = ?, position = ?, status = ? WHERE employee_id = ?");
        $stmt->bind_param('ssss', $name, $position, $status, $employee_id);
        $stmt->execute();
    }
}


$search = trim($_GET['search'] ?? '');
$position_filter = trim($_GET['position'] ?? '');
$positions = getPositionsLocal($conn);

$sql = 'SELECT employee_id, name, position, status FROM employees WHERE 1=1';
$params = [];
$types = '';

if ($search !== '') {
    $sql .= ' AND (employee_id LIKE ? OR name LIKE ? OR position LIKE ? OR status LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssss';
}

if ($position_filter !== '') {
    $sql .= ' AND position = ?';
    $params[] = $position_filter;
    $types .= 's';
}

$sql .= ' ORDER BY CAST(SUBSTRING(employee_id, 4) AS UNSIGNED) DESC';

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    bindParamsDynamic($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$count_result = $conn->query("SELECT status, COUNT(*) AS total FROM employees GROUP BY status");
$employeeTotals = ['Active' => 0, 'Inactive' => 0];
$totalEmployees = 0;
if ($count_result) {
    while ($row = $count_result->fetch_assoc()) {
        $employeeTotals[$row['status']] = (int)$row['total'];
        $totalEmployees += (int)$row['total'];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>พนักงาน - <?= e(app_name()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=dc36a003b825" rel="stylesheet">
    <link href="../assets/css/design_tokens.css?v=dc36a003b825" rel="stylesheet">
</head>
<body>
<?php render_navbar('employees'); ?>

<div class="container page-shell">
    <div class="page-header">
        <div>
            <div class="page-eyebrow"><i class="fa-solid fa-user-tie"></i> ทีมงานและบุคลากร</div>
            <h1 class="page-title">จัดการข้อมูลพนักงาน</h1>
            <p class="page-subtitle"></p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#employeeModal" onclick="openAddModal()">
            <i class="fa-solid fa-user-plus"></i> เพิ่มพนักงาน
        </button>
    </div>

    <div class="layout-grid two-one mb-4">
        <div class="hero-card">
            <div class="hero-label"><i class="fa-solid fa-users-gear"></i> ภาพรวมพนักงาน</div>
            <div class="hero-value"><?= e($totalEmployees) ?></div>
            <p class="mb-0 mt-2">จำนวนพนักงานทั้งหมดในระบบ</p>
            <div class="summary-list">
                <div class="summary-chip">
                    <small>ปฏิบัติงาน</small>
                    <strong><?= e($employeeTotals['Active'] ?? 0) ?></strong>
                </div>
                <div class="summary-chip">
                    <small>ไม่ปฏิบัติงาน</small>
                    <strong><?= e($employeeTotals['Inactive'] ?? 0) ?></strong>
                </div>
            </div>
        </div>

        <div class="section-card">
            <form method="GET" class="toolbar-row" style="grid-template-columns: 1fr 220px auto;">
                <div>
                    <label class="form-label" for="search">ค้นหาพนักงาน</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="ค้นหาจากรหัส ชื่อ ตำแหน่ง หรือสถานะ" value="<?= e($search) ?>">
                </div>
                <div>
                    <label class="form-label" for="position">กรองตามตำแหน่ง</label>
                    <select name="position" id="position" class="form-select">
                        <option value="">ทุกตำแหน่ง</option>
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?= e($pos) ?>" <?= $position_filter === $pos ? 'selected' : '' ?>><?= e($pos) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-grid">
                    <label class="form-label d-none d-lg-block">&nbsp;</label>
                    <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-filter"></i> กรองข้อมูล</button>
                </div>
            </form>
        </div>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>รหัสพนักงาน</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>ตำแหน่ง</th>
                        <th>สถานะ</th>
                        <th>การทำงาน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $employee_count = 0; ?>
                    <?php if ($result): ?>
                        <?php while ($employee = $result->fetch_assoc()): $employee_count++; ?>
                            <tr>
                                <td><?= e($employee['employee_id']) ?></td>
                                <td><?= e($employee['name']) ?></td>
                                <td><?= e($employee['position']) ?></td>
                                <td><span class="status-badge <?= e(status_badge_class($employee['status'])) ?>"><?= e(status_label($employee['status'])) ?></span></td>
                                <td>
                                    <div class="action-group">
                                        <button class="btn btn-primary btn-edit btn-sm" onclick='editEmployee(<?= json_encode($employee['employee_id'], JSON_UNESCAPED_UNICODE) ?>)'>
                                            <i class="fa-solid fa-pen"></i> แก้ไข
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>

                    <?php if ($employee_count === 0): ?>
                        <tr>
                            <td colspan="5" class="text-center empty-state">ไม่พบข้อมูลพนักงานตามเงื่อนไขที่เลือก</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="employeeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">เพิ่มพนักงาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="employee_id" id="employee_id">
                    <div class="mb-3">
                        <label class="form-label" for="name">ชื่อ-นามสกุล</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="positionField">ตำแหน่ง</label>
                        <input type="text" name="position" id="positionField" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="status">สถานะ</label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="Active">ปฏิบัติงาน</option>
                            <option value="Inactive">ไม่ปฏิบัติงาน</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="submit" id="saveBtn" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'เพิ่มพนักงาน';
    document.getElementById('employee_id').value = '';
    document.getElementById('name').value = '';
    document.getElementById('positionField').value = '';
    document.getElementById('status').value = 'Active';

    const btn = document.getElementById('saveBtn');
    btn.name = 'submit';
    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> บันทึกข้อมูล';
}

function editEmployee(id) {
    fetch(`employees.php?ajax=get&id=${encodeURIComponent(id)}`)
        .then(response => response.json())
        .then(employee => {
            if (!employee) {
                alert('ไม่พบข้อมูลพนักงาน');
                return;
            }

            document.getElementById('modalTitle').textContent = 'แก้ไขข้อมูลพนักงาน';
            document.getElementById('employee_id').value = employee.employee_id;
            document.getElementById('name').value = employee.name;
            document.getElementById('positionField').value = employee.position;
            document.getElementById('status').value = employee.status;

            const btn = document.getElementById('saveBtn');
            btn.name = 'update';
            btn.innerHTML = '<i class="fa-solid fa-pen-to-square"></i> อัปเดตข้อมูล';

            new bootstrap.Modal(document.getElementById('employeeModal')).show();
        })
        .catch(() => {
            alert('ไม่สามารถโหลดข้อมูลพนักงานได้');
        });
}
</script>
</body>
</html>
