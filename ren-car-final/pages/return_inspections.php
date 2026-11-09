<?php
require_once '../config/db.php';
require_once '../includes/ui_helpers.php';
require_once '../includes/navbar.php';
require_once '../functions/rental_functions.php';

syncRentalStatuses();

function ensureReturnInspectionTable(mysqli $conn): void {
    $sql = "CREATE TABLE IF NOT EXISTS car_return_inspections (
        inspection_id VARCHAR(10) NOT NULL PRIMARY KEY,
        rental_id VARCHAR(10) NOT NULL,
        car_id VARCHAR(30) NOT NULL,
        customer_id VARCHAR(30) DEFAULT NULL,
        return_date DATE NOT NULL,
        has_damage VARCHAR(10) NOT NULL DEFAULT 'No',
        damage_detail VARCHAR(255) DEFAULT NULL,
        fine_amount DECIMAL(10,2) DEFAULT 0,
        inspector_name VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_rental_inspection (rental_id),
        INDEX idx_return_car (car_id),
        CONSTRAINT fk_return_inspection_rental FOREIGN KEY (rental_id) REFERENCES rentals(rental_id) ON DELETE CASCADE,
        CONSTRAINT fk_return_inspection_car FOREIGN KEY (car_id) REFERENCES cars(car_id) ON DELETE CASCADE,
        CONSTRAINT fk_return_inspection_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    $conn->query($sql);
}

function generateInspectionID(mysqli $conn): string {
    $result = $conn->query("SELECT inspection_id FROM car_return_inspections WHERE inspection_id LIKE 'RET%' ORDER BY CAST(SUBSTRING(inspection_id, 4) AS UNSIGNED) DESC LIMIT 1");
    if ($result && ($row = $result->fetch_assoc())) {
        $num = (int) substr($row['inspection_id'], 3) + 1;
    } else {
        $num = 1;
    }
    return 'RET' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
}

ensureReturnInspectionTable($conn);

$defaultInspectorName = 'Malee Thongdee';
$defaultInspectorDisplay = 'EMP004 - Malee Thongdee';
$message = '';
$error = '';
$selectedRentalId = trim($_GET['rental_id'] ?? ($_POST['rental_id'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_inspection'])) {
    $rentalId = trim($_POST['rental_id'] ?? '');
    $hasDamage = trim($_POST['has_damage'] ?? 'No');
    $damageDetail = trim($_POST['damage_detail'] ?? '');
    $fineAmount = (float) ($_POST['fine_amount'] ?? 0);
    $inspectorName = $defaultInspectorName;

    if ($rentalId === '') {
        $error = 'กรุณาเลือกรายการเช่าที่ต้องการตรวจสภาพรถ';
    } elseif ($hasDamage === 'Yes' && $damageDetail === '') {
        $error = 'กรุณาระบุรายละเอียดตำหนิก่อนบันทึกผลตรวจรถคืน';
    } elseif ($fineAmount < 0) {
        $error = 'ค่าปรับต้องไม่ต่ำกว่า 0 บาท';
    } else {
        $checkStmt = $conn->prepare("SELECT inspection_id FROM car_return_inspections WHERE rental_id = ? LIMIT 1");
        $checkStmt->bind_param('s', $rentalId);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();

        if ($existing) {
            $error = 'รายการเช่านี้ถูกตรวจสภาพแล้ว จึงไม่สามารถแก้ไขได้อีก';
        } else {
            $rentalStmt = $conn->prepare("SELECT rental_id, car_id, customer_id, return_date, status FROM rentals WHERE rental_id = ? LIMIT 1");
            $rentalStmt->bind_param('s', $rentalId);
            $rentalStmt->execute();
            $rental = $rentalStmt->get_result()->fetch_assoc();

            if (!$rental) {
                $error = 'ไม่พบรายการเช่าที่เลือก';
            } elseif (!in_array($rental['status'], ['Completed', 'Returned'], true)) {
                $error = 'ตรวจรถคืนได้เฉพาะรายการเช่าที่เสร็จสิ้นแล้ว';
            } else {
                $returnDate = !empty($rental['return_date']) ? $rental['return_date'] : date('Y-m-d');
                if ($hasDamage === 'No') {
                    $damageDetail = '';
                    $fineAmount = 0;
                }

                $inspectionId = generateInspectionID($conn);
                $insertStmt = $conn->prepare("INSERT INTO car_return_inspections (inspection_id, rental_id, car_id, customer_id, return_date, has_damage, damage_detail, fine_amount, inspector_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->bind_param('sssssssds', $inspectionId, $rentalId, $rental['car_id'], $rental['customer_id'], $returnDate, $hasDamage, $damageDetail, $fineAmount, $inspectorName);
                if ($insertStmt->execute()) {
                    $message = 'บันทึกการตรวจสภาพรถคืนเรียบร้อยแล้ว';
                    $selectedRentalId = '';
                } else {
                    $error = 'ไม่สามารถบันทึกข้อมูลได้';
                }
            }
        }
    }
}

$rentalOptions = [];
$rentalQuery = $conn->query("SELECT r.rental_id, r.car_id, c.name AS customer_name
    FROM rentals r
    JOIN customers c ON c.customer_id = r.customer_id
    LEFT JOIN car_return_inspections cri ON cri.rental_id = r.rental_id
    WHERE r.status IN ('Completed', 'Returned') AND cri.inspection_id IS NULL
    ORDER BY CAST(SUBSTRING(r.rental_id, 5) AS UNSIGNED) DESC");
if ($rentalQuery) {
    while ($row = $rentalQuery->fetch_assoc()) {
        $rentalOptions[] = $row;
    }
}

$currentInspection = null;
if ($selectedRentalId !== '') {
    $detailStmt = $conn->prepare("SELECT r.rental_id, r.car_id, r.customer_id, c.name AS customer_name, r.return_date
        FROM rentals r
        JOIN customers c ON c.customer_id = r.customer_id
        LEFT JOIN car_return_inspections cri ON cri.rental_id = r.rental_id
        WHERE r.rental_id = ? AND cri.inspection_id IS NULL LIMIT 1");
    $detailStmt->bind_param('s', $selectedRentalId);
    $detailStmt->execute();
    $currentInspection = $detailStmt->get_result()->fetch_assoc();
}

$history = [];
$historyQuery = $conn->query("SELECT cri.*, ca.brand, ca.model
    FROM car_return_inspections cri
    JOIN cars ca ON ca.car_id = cri.car_id
    ORDER BY CAST(SUBSTRING(cri.inspection_id, 4) AS UNSIGNED) DESC, cri.return_date DESC");
if ($historyQuery) {
    while ($row = $historyQuery->fetch_assoc()) {
        $history[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสภาพรถคืน - <?= e(app_name()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/design_tokens.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/fix_result_column_v37.css">

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
<?php render_navbar('return_inspections'); ?>
<div class="container page-shell return-page">
    <div class="page-header">
        <div>
            <div class="page-eyebrow"><i class="fa-solid fa-clipboard-check"></i> ตรวจสอบรถที่คืนแล้ว</div>
            <h1 class="page-title">ตรวจสภาพรถคืน</h1>
        </div>
    </div>

    <?php $pendingCount = count($rentalOptions); ?>
    <div class="return-page-grid mb-4">
        <div class="section-card return-form-panel">
            <div class="return-topbar">
                <div>
                    <div class="section-heading">บันทึกผลการตรวจรถคืน</div>
                    <p class="section-copy">เลือกรายการเช่าที่คืนแล้วและบันทึกผลได้เพียง 1 ครั้งต่อบิล</p>
                </div>
                <div class="return-count-chip"><i class="fa-solid fa-hourglass-half"></i> รอตรวจ <?= e((string)$pendingCount) ?> รายการ</div>
            </div>

            <?php if ($message !== ''): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label" for="rental_id">เลือกรายการเช่าที่คืนแล้ว</label>
                    <select class="form-select" id="rental_id" name="rental_id" onchange="this.form.submit()">
                        <option value="">-- เลือกรายการเช่า --</option>
                        <?php foreach ($rentalOptions as $item): ?>
                            <option value="<?= e($item['rental_id']) ?>" <?= $selectedRentalId === (string) $item['rental_id'] ? 'selected' : '' ?>>
                                <?= e($item['rental_id']) ?> | <?= e($item['car_id']) ?> | <?= e($item['customer_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">แสดงเฉพาะบิลที่ยังไม่เคยตรวจสภาพรถคืน</div>
                    <div class="form-text" role="status">รายการที่กำลังเช่าและรายการที่ตรวจแล้วจะไม่ปรากฏในตัวเลือก</div>
                </div>

                <?php if ($currentInspection): ?>
                    <div class="return-summary-grid">
                        <div class="return-summary-item"><small>รหัสรถ</small><strong><?= e($currentInspection['car_id']) ?></strong></div>
                        <div class="return-summary-item"><small>ลูกค้า</small><strong><?= e($currentInspection['customer_name']) ?></strong></div>
                        <div class="return-summary-item"><small>วันที่คืนรถ</small><strong><?= e(format_display_date($currentInspection['return_date'] ?? '')) ?></strong></div>
                        <div class="return-summary-item"><small>ผู้ตรวจ</small><strong><?= e($defaultInspectorDisplay) ?></strong></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="has_damage">มีตำหนิหรือไม่</label>
                            <select class="form-select" name="has_damage" id="has_damage">
                                <option value="No">ไม่มีตำหนิ</option>
                                <option value="Yes">มีตำหนิ</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="fine_amount">ค่าปรับ / ค่าเสียหาย</label>
                            <input type="number" min="0" step="0.01" class="form-control" id="fine_amount" name="fine_amount" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="damage_detail">รายละเอียดตำหนิ</label>
                            <textarea class="form-control" id="damage_detail" name="damage_detail" rows="4" aria-describedby="damageDetailError" placeholder="เช่น มีรอยขีดข่วนบริเวณกันชนหน้า หรือมีรอยบุบด้านขวา"></textarea>
                            <div id="damageDetailError" class="field-error" role="alert" hidden>กรุณาระบุรายละเอียดตำหนิก่อนบันทึกผลตรวจรถคืน</div>
                        </div>
                    </div>
                    <input type="hidden" name="inspector_name" value="<?= e($defaultInspectorName) ?>">
                    <div class="return-form-actions">
                        <button type="submit" name="save_inspection" value="1" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> บันทึกผลตรวจรถคืน</button>
                    </div>
                <?php else: ?>
                    <div class="return-help"><i class="fa-solid fa-circle-info me-2"></i>เลือกรายการเช่าที่คืนแล้วจากช่องด้านบน เพื่อแสดงข้อมูลรถ ลูกค้า และแบบฟอร์มบันทึกผลการตรวจ</div>
                <?php endif; ?>
            </form>
        </div>

        <div class="section-card return-history-panel">
            <div class="section-heading-row is-compact mb-3">
                <div>
                    <div class="section-heading">สรุปประวัติการตรวจรถคืน</div>
                    <p class="section-copy">ดูรายการล่าสุดได้ทันที พร้อมสถานะรถและค่าปรับ</p>
                </div>
                <span class="section-chip">ประวัติ <?= e((string)count($history)) ?> รายการ</span>
            </div>
            <div class="return-table-wrap table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>รหัสตรวจ</th>
                            <th>การเช่า</th>
                            <th>รถ</th>
                            <th>วันที่คืน</th>
                            <th>สภาพรถ</th>
                            <th>ค่าปรับ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$history): ?>
                            <tr><td colspan="6" class="text-center empty-state">ยังไม่มีประวัติการตรวจรถคืน</td></tr>
                        <?php endif; ?>
                        <?php foreach ($history as $row): ?>
                            <tr>
                                <td><?= e($row['inspection_id']) ?></td>
                                <td><?= e($row['rental_id']) ?></td>
                                <td><?= e($row['car_id']) ?> - <?= e($row['brand'] . ' ' . $row['model']) ?></td>
                                <td><?= e(format_display_date($row['return_date'] ?? '')) ?></td>
                                <td>
                                    <span class="status-badge <?= ($row['has_damage'] ?? 'No') === 'Yes' ? 'inspection-damage-yes' : 'inspection-damage-no' ?>"><?= ($row['has_damage'] ?? 'No') === 'Yes' ? 'มีตำหนิ' : 'ไม่มีตำหนิ' ?></span>
                                    <?php if (!empty($row['damage_detail'])): ?><div class="small text-muted mt-2"><?= e($row['damage_detail']) ?></div><?php endif; ?>
                                </td>
                                <td><?= e(format_money($row['fine_amount'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const hasDamageSelect = document.getElementById('has_damage');
const damageDetail = document.getElementById('damage_detail');
const fineAmount = document.getElementById('fine_amount');
const damageDetailError = document.getElementById('damageDetailError');
const inspectionSaveButton = document.querySelector('button[name="save_inspection"]');
function syncDamageFields() {
    if (!hasDamageSelect || !damageDetail || !fineAmount) return;
    const disabled = hasDamageSelect.value !== 'Yes';
    damageDetail.disabled = disabled;
    fineAmount.disabled = disabled;
    if (disabled) {
        damageDetail.value = '';
        fineAmount.value = '0';
    }
    const missingDetail = !disabled && !damageDetail.value.trim();
    damageDetail.required = !disabled;
    damageDetailError.hidden = !missingDetail;
    damageDetail.classList.toggle('is-invalid', missingDetail);
    if (inspectionSaveButton) inspectionSaveButton.disabled = missingDetail;
}
if (hasDamageSelect) {
    hasDamageSelect.addEventListener('change', syncDamageFields);
    damageDetail.addEventListener('input', syncDamageFields);
    syncDamageFields();
}
</script>
</body>
</html>
