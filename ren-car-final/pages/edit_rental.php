<?php
require_once '../config/db.php';
require_once '../includes/ui_helpers.php';
require_once '../includes/navbar.php';
require_once '../functions/rental_functions.php';

$rental_id = $_GET['id'] ?? '';
if (!$rental_id) {
    header('Location: rentals.php');
    exit;
}

$rental = getRentalById($rental_id);
if (!$rental) {
    header('Location: rentals.php?error=notfound');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $return_date = trim($_POST['return_date'] ?? '');
    $status = trim($_POST['status'] ?? '');

    if ($return_date && $status) {
        try {
            updateRental($rental_id, $return_date, $status);
            header('Location: rentals.php?success=updated');
            exit;
        } catch (Exception $e) {
            $error_message = 'เกิดข้อผิดพลาดในการอัปเดตรายการเช่า: ' . $e->getMessage();
        }
    } else {
        $error_message = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
    }
}

$statusOptions = [
    'Booked' => 'จองล่วงหน้า',
    'Rented' => 'กำลังเช่า',
    'Completed' => 'เสร็จสิ้น',
];

$today = date('Y-m-d');
$currentStatusHint = ($rental['rental_date'] ?? '') > $today
    ? 'หากวันเริ่มเช่ายังมาไม่ถึง ระบบจะบันทึกเป็น “จองล่วงหน้า” อัตโนมัติ'
    : ((($rental['return_date'] ?? '') < $today)
        ? 'หากวันคืนเลยมาแล้ว ระบบจะบันทึกเป็น “เสร็จสิ้น” อัตโนมัติ'
        : 'หากวันที่เริ่มเช่ามาถึงแล้วและยังไม่เกินวันคืน ระบบจะบันทึกเป็น “กำลังเช่า” อัตโนมัติ');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขรายการเช่า - <?= e(app_name()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/design_tokens.css" rel="stylesheet">
</head>
<body>
<?php render_navbar('rentals'); ?>

<div class="container page-shell">
    <div class="page-header">
        <div>
            <div class="page-eyebrow"><i class="fa-solid fa-pen-to-square"></i> อัปเดตรายการเช่า</div>
            <h1 class="page-title">แก้ไขข้อมูลการเช่า</h1>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger mb-4"><?= e($error_message) ?></div>
    <?php endif; ?>

    <div class="form-card" style="max-width: 900px; margin: 0 auto;">
        <div class="info-list mb-4">
            <div class="info-item">
                <small>รหัสการเช่า</small>
                <strong><?= e($rental['rental_id']) ?></strong>
            </div>
            <div class="info-item">
                <small>รหัสลูกค้า</small>
                <strong><?= e($rental['customer_id']) ?> - <?= e($rental['customer_name']) ?></strong>
            </div>
            <div class="info-item">
                <small>รหัสรถยนต์</small>
                <strong><?= e($rental['car_id']) ?> - <?= e(($rental['brand'] ?? '') . ' ' . ($rental['model'] ?? '')) ?></strong>
            </div>
            <div class="info-item">
                <small>วันที่เริ่มเช่า</small>
                <strong><?= e(format_display_date($rental['rental_date'] ?? '')) ?></strong>
            </div>
        </div>

        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="return_date">วันที่คืนรถ</label>
                    <input type="date" name="return_date" id="return_date" class="form-control" value="<?= e($rental['return_date'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="status">สถานะ</label>
                    <select name="status" id="status" class="form-select" required>
                        <option value="">-- เลือกสถานะ --</option>
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($rental['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted d-block mt-2"><?= e($currentStatusHint) ?></small>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> อัปเดตรายการเช่า</button>
                <a href="rentals.php" class="btn btn-secondary">ยกเลิก</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
