<?php
require_once '../config/db.php';
require_once '../includes/ui_helpers.php';
require_once '../includes/navbar.php';
require_once '../functions/rental_functions.php';

if (isset($_GET['delete'])) {
    $delete_id = trim($_GET['delete']);
    if ($delete_id !== '') {
        deleteRental($delete_id);
    }
}

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$result = getRentals($search, $statusFilter);
$totalRentals = getTotalRentals();
$activeRentals = countRentalsByStatus('Rented');
$bookedRentals = countRentalsByStatus('Booked');
$completedRentals = countRentalsByStatuses(['Completed', 'Returned']);
$filterLabelMap = [
    '' => 'ทั้งหมด',
    'Rented' => 'กำลังเช่า',
    'Booked' => 'จองล่วงหน้า',
    'completed' => 'เสร็จสิ้นแล้ว'
];
$currentFilterLabel = $filterLabelMap[$statusFilter] ?? 'ทั้งหมด';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>การเช่า - <?= e(app_name()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/design_tokens.css" rel="stylesheet">
    <link href="../assets/css/rental_actions.css" rel="stylesheet">
</head>
<body class="rentals-page">
<?php render_navbar('rentals'); ?>

<div class="container page-shell">
    <div class="page-header">
        <div>
            <div class="page-eyebrow"><i class="fa-solid fa-file-contract"></i> การจัดการรายการเช่า</div>
            <h1 class="page-title">จัดการข้อมูลการเช่า</h1>
        </div>
        <a href="add_rental.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> เพิ่มรายการเช่า</a>
    </div>

    <div class="rental-overview mb-4">
        <div class="rental-summary-card">
            <div class="rental-summary-icon"><i class="fa-solid fa-file-contract" aria-hidden="true"></i></div>
            <div><p>รายการเช่าที่แสดง</p><strong><?= e(count($result)) ?> รายการ</strong></div>
        </div>

        <div class="rental-filter-card">
            <form method="GET" class="rental-filter-form">
                <div class="rental-filter-grid">
                    <div>
                        <label class="form-label" for="searchInput">ค้นหารายการเช่า</label>
                        <input type="search" id="searchInput" name="search" class="form-control" placeholder="พิมพ์รหัสการเช่า รหัสหรือชื่อลูกค้า รหัสรถ ยี่ห้อ หรือรุ่นรถ" value="<?= e($search) ?>">
                    </div>
                    <div>
                        <label class="form-label" for="statusInput">กรองตามสถานะ</label>
                        <select id="statusInput" name="status" class="form-select">
                            <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>ทุกสถานะ</option>
                            <option value="Rented" <?= $statusFilter === 'Rented' ? 'selected' : '' ?>>กำลังเช่า</option>
                            <option value="Booked" <?= $statusFilter === 'Booked' ? 'selected' : '' ?>>จองล่วงหน้า</option>
                            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>เสร็จสิ้นแล้ว</option>
                        </select>
                    </div>
                </div>
                <div class="rental-filter-actions">
                    <button type="submit" class="btn rental-primary-button"><i class="fa-solid fa-magnifying-glass"></i> ค้นหา</button>
                    <a href="rentals.php" class="btn rental-secondary-button">ล้างค่า</a>
                </div>
            </form>
        </div>
    </div>

    <div class="table-card">
        <div class="rental-table-title"><h2>รายการเช่าทั้งหมด</h2><span>พบข้อมูล <?= e(count($result)) ?> รายการ</span></div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>รหัสการเช่า</th>
                        <th>รหัสลูกค้า</th>
                        <th>ชื่อลูกค้า</th>
                        <th>รหัสรถ</th>
                        <th>รถยนต์</th>
                        <th>วันที่เช่า</th>
                        <th>วันที่คืน</th>
                        <th>ราคารวม</th>
                        <th>สถานะ</th>
                        <th>การทำงาน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rental_count = 0; ?>
                    <?php foreach ($result as $rental): $rental_count++; ?>
                        <tr>
                            <td><?= e($rental['rental_id']) ?></td>
                            <td><?= e($rental['customer_id']) ?></td>
                            <td><?= e($rental['customer_name']) ?></td>
                            <td><?= e($rental['car_id']) ?></td>
                            <td><?= e(($rental['brand'] ?? '') . ' ' . ($rental['model'] ?? '')) ?></td>
                            <td><?= e(format_display_date($rental['rental_date'] ?? '')) ?></td>
                            <td><?= e(format_display_date($rental['return_date'] ?? '')) ?></td>
                            <td>฿<?= e(number_format((float)($rental['total_price'] ?? 0), 2)) ?></td>
                            <td><span class="status-badge <?= e(status_badge_class($rental['status'])) ?>"><?= e(status_label($rental['status'])) ?></span></td>
                            <td class="rental-action-cell">
                                <?php $menuId = 'rental-menu-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$rental['rental_id']); ?>
                                <button type="button" class="rental-more-button" aria-label="เมนูเพิ่มเติมสำหรับ <?= e($rental['rental_id']) ?>" aria-haspopup="menu" aria-expanded="false" aria-controls="<?= e($menuId) ?>">
                                    <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                </button>
                                <div id="<?= e($menuId) ?>" class="rental-action-menu" role="menu" hidden>
                                    <a role="menuitem" href="edit_rental.php?id=<?= urlencode($rental['rental_id']) ?>" class="rental-action-item"><i class="fa-solid fa-pen" aria-hidden="true"></i> แก้ไข</a>
                                    <?php if (in_array($rental['status'], ['Completed', 'Returned'], true)): ?>
                                        <a role="menuitem" href="return_inspections.php?rental_id=<?= urlencode($rental['rental_id']) ?>" class="rental-action-item"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> ตรวจรถคืน</a>
                                    <?php else: ?>
                                        <a role="menuitem" href="#" class="rental-action-item" aria-disabled="true" data-return-disabled="true" title="ตรวจรถคืนได้เมื่อรายการเช่าเสร็จสิ้นแล้ว"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> ตรวจรถคืน</a>
                                    <?php endif; ?>
                                    <div class="rental-action-divider" role="separator"></div>
                                    <button type="button" role="menuitem" class="rental-action-item rental-action-delete" data-rental-id="<?= e($rental['rental_id']) ?>" data-delete-url="?delete=<?= urlencode($rental['rental_id']) ?>"><i class="fa-solid fa-trash" aria-hidden="true"></i> ลบรายการ</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($rental_count === 0): ?>
                        <tr>
                            <td colspan="10" class="text-center empty-state">ไม่พบข้อมูลรายการเช่าตามเงื่อนไขที่เลือก</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="rentalDeleteBackdrop" class="rental-delete-backdrop" hidden>
    <section class="rental-delete-modal" role="dialog" aria-modal="true" aria-labelledby="rentalDeleteTitle" aria-describedby="rentalDeleteDescription">
        <div class="rental-modal-header"><div class="rental-warning-icon">!</div><div><h2 id="rentalDeleteTitle">ยืนยันการลบรายการ</h2><p>ตรวจสอบรหัสรายการก่อนยืนยัน</p></div></div>
        <p id="rentalDeleteDescription">ต้องการลบรายการเช่า <strong id="deleteRentalId">-</strong> หรือไม่?</p>
        <p>ข้อมูลการชำระเงินที่ผูกกับรายการนี้จะถูกลบตาม business logic เดิม และสถานะรถจะถูกอัปเดตตามเงื่อนไขของระบบ</p>
        <p class="rental-delete-warning">การลบรายการนี้ไม่สามารถย้อนกลับได้</p>
        <div class="rental-delete-actions">
            <button type="button" id="cancelRentalDelete" class="rental-delete-cancel">ยกเลิก</button>
            <button type="button" id="confirmRentalDelete" class="rental-delete-confirm">ลบรายการ</button>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/rental_actions.js"></script>
</body>
</html>
