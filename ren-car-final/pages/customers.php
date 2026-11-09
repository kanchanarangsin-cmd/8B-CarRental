<?php
require_once '../config/db.php';
require_once '../includes/ui_helpers.php';
require_once '../includes/navbar.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}

function generateCustomerID($conn) {
    $sql = "SELECT customer_id FROM customers WHERE customer_id LIKE 'CUST%' ORDER BY customer_id DESC LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $row = $result->fetch_assoc()) {
        $last = $row['customer_id'];
        $num = intval(substr($last, 4)) + 1;
    } else {
        $num = 1;
    }

    return 'CUST' . str_pad($num, 3, '0', STR_PAD_LEFT);
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get' && isset($_GET['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $id = trim($_GET['id']);

    $stmt = $conn->prepare("SELECT customer_id, name, phone, id_card FROM customers WHERE customer_id = ?");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();

    echo json_encode($customer ?: null, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = trim($_POST['customer_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $id_card = trim($_POST['id_card'] ?? '');

    if (isset($_POST['submit'])) {
        $new_id = generateCustomerID($conn);
        $stmt = $conn->prepare("INSERT INTO customers (customer_id, name, phone, id_card) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $new_id, $name, $phone, $id_card);
        $stmt->execute();
    }

    if (isset($_POST['update'])) {
        if ($customer_id === '') {
            die('Customer ID is required for update.');
        }

        $stmt = $conn->prepare("UPDATE customers SET name = ?, phone = ?, id_card = ? WHERE customer_id = ?");
        $stmt->bind_param('ssss', $name, $phone, $id_card, $customer_id);
        $stmt->execute();
    }
}

if (isset($_GET['delete'])) {
    $delete_id = trim($_GET['delete']);
    if ($delete_id !== '') {
        $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
        $stmt->bind_param('s', $delete_id);
        $stmt->execute();
    }
}

$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare("SELECT customer_id, name, phone, id_card FROM customers WHERE customer_id LIKE ? OR name LIKE ? OR phone LIKE ? OR id_card LIKE ? ORDER BY CAST(SUBSTRING(customer_id, 5) AS UNSIGNED) DESC");
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT customer_id, name, phone, id_card FROM customers ORDER BY CAST(SUBSTRING(customer_id, 5) AS UNSIGNED) DESC");
}

$total_customers_result = $conn->query("SELECT COUNT(*) AS total FROM customers");
$total_customers = $total_customers_result ? (int)($total_customers_result->fetch_assoc()['total'] ?? 0) : 0;
$nextCustomerId = generateCustomerID($conn);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลูกค้า - <?= e(app_name()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=dc36a003b825" rel="stylesheet">
    <link href="../assets/css/design_tokens.css?v=dc36a003b825" rel="stylesheet">
    <link href="../assets/css/iteration_actions.css?v=final4" rel="stylesheet">
</head>
<body>
<?php render_navbar('customers'); ?>

<div class="container page-shell">
    <div class="page-header">
        <div>
            <div class="page-eyebrow"><i class="fa-solid fa-users"></i> ฐานข้อมูลลูกค้า</div>
            <h1 class="page-title">จัดการข้อมูลลูกค้า</h1>
        </div>
    </div>

    <section class="section-card hts-task-panel hts-task-panel--customer" aria-labelledby="customerCreateTitle">
        <div>
            <h2 id="customerCreateTitle" class="hts-task-title"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> ลงทะเบียนลูกค้าใหม่</h2>
            <p id="customerCreateHelp" class="hts-task-help">เตรียมชื่อ-นามสกุล เบอร์โทร และเลขบัตรประชาชน เพื่อบันทึกข้อมูลสำหรับทำรายการเช่ารถ</p>
        </div>
        <button type="button" class="btn btn-primary hts-customer-create" aria-describedby="customerCreateHelp" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="openAddModal()">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> เพิ่มลูกค้าใหม่
        </button>
    </section>

    <div class="layout-grid two-one mb-4">
        <div class="hero-card">
            <div class="hero-label"><i class="fa-solid fa-address-book"></i> ภาพรวมลูกค้า</div>
            <div class="hero-value"><?= e($total_customers) ?></div>
            <p class="mb-0 mt-2">จำนวนลูกค้าที่บันทึกในระบบ</p>
        </div>

        <div class="section-card">
            <form method="GET">
                <label class="form-label" for="searchInput">ค้นหาข้อมูลลูกค้า</label>
                <input type="text" id="searchInput" name="search" class="form-control" placeholder="ค้นหาจากรหัสลูกค้า ชื่อ เบอร์โทร หรือเลขบัตรประชาชน" value="<?= e($search) ?>">
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-magnifying-glass"></i> ค้นหา</button>
                    <a href="customers.php" class="btn btn-secondary">รีเซ็ต</a>
                </div>
            </form>
        </div>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>รหัสลูกค้า</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>เบอร์โทร</th>
                        <th>เลขบัตรประชาชน</th>
                        <th>การทำงาน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $customer_count = 0; ?>
                    <?php if ($result): ?>
                        <?php while ($customer = $result->fetch_assoc()): $customer_count++; ?>
                            <tr>
                                <td><?= e($customer['customer_id']) ?></td>
                                <td><?= e($customer['name']) ?></td>
                                <td><?= e($customer['phone']) ?></td>
                                <td><?= e($customer['id_card']) ?></td>
                                <td>
                                    <div class="action-group">
                                        <button class="btn btn-primary btn-edit btn-sm" onclick='editCustomer(<?= json_encode($customer['customer_id'], JSON_UNESCAPED_UNICODE) ?>)'>
                                            <i class="fa-solid fa-pen"></i> แก้ไข
                                        </button>
                                        <a href="?delete=<?= urlencode($customer['customer_id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('ยืนยันการลบข้อมูลลูกค้ารายนี้หรือไม่?')">
                                            <i class="fa-solid fa-trash"></i> ลบ
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>

                    <?php if ($customer_count === 0): ?>
                        <tr>
                            <td colspan="5" class="text-center empty-state">ไม่พบข้อมูลลูกค้าที่ค้นหา</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true" aria-labelledby="modalTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" autocomplete="off">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">เพิ่มลูกค้าใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="customer_id" id="customer_id">

                    <div class="mb-3">
                        <label class="form-label" for="customer_code_display">รหัสลูกค้า</label>
                        <input type="text" id="customer_code_display" class="form-control" value="<?= e($nextCustomerId) ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="name">ชื่อ-นามสกุล</label>
                        <input type="text" name="name" id="name" class="form-control" autocomplete="off" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="phone">เบอร์โทร</label>
                        <input type="text" name="phone" id="phone" class="form-control" inputmode="tel" autocomplete="off" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="id_card">เลขบัตรประชาชน</label>
                        <input type="text" name="id_card" id="id_card" class="form-control" inputmode="numeric" autocomplete="off" required>
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
const nextCustomerId = <?= json_encode($nextCustomerId, JSON_UNESCAPED_UNICODE) ?>;

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'เพิ่มลูกค้าใหม่';
    document.getElementById('customer_id').value = '';
    document.getElementById('customer_code_display').value = nextCustomerId;
    document.getElementById('name').value = '';
    document.getElementById('phone').value = '';
    document.getElementById('id_card').value = '';

    const btn = document.getElementById('saveBtn');
    btn.name = 'submit';
    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> บันทึกข้อมูล';
}

function editCustomer(id) {
    fetch(`customers.php?ajax=get&id=${encodeURIComponent(id)}`)
        .then(response => response.json())
        .then(customer => {
            if (!customer) {
                alert('ไม่พบข้อมูลลูกค้า');
                return;
            }

            document.getElementById('modalTitle').textContent = 'แก้ไขข้อมูลลูกค้า';
            document.getElementById('customer_id').value = customer.customer_id;
            document.getElementById('customer_code_display').value = customer.customer_id;
            document.getElementById('name').value = customer.name;
            document.getElementById('phone').value = customer.phone;
            document.getElementById('id_card').value = customer.id_card;

            const btn = document.getElementById('saveBtn');
            btn.name = 'update';
            btn.innerHTML = '<i class="fa-solid fa-pen-to-square"></i> อัปเดตข้อมูล';

            new bootstrap.Modal(document.getElementById('customerModal')).show();
        })
        .catch(() => {
            alert('ไม่สามารถโหลดข้อมูลลูกค้าได้');
        });
}
</script>
</body>
</html>
