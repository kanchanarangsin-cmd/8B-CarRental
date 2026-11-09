<?php
require_once '../config/db.php';
require_once '../includes/ui_helpers.php';
require_once '../includes/navbar.php';
require_once '../functions/rental_functions.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}

$error = '';
$customer_id = trim($_POST['customer_id'] ?? '');
$car_id = trim($_POST['car_id'] ?? '');
$selectedRentalDate = trim($_POST['rental_date'] ?? ($_GET['rental_date'] ?? ''));
$selectedReturnDate = trim($_POST['return_date'] ?? ($_GET['return_date'] ?? ''));
$payment_type = trim($_POST['payment_type'] ?? 'Cash');

$datesReady = !empty($selectedRentalDate) && !empty($selectedReturnDate)
    && $selectedReturnDate >= $selectedRentalDate;

$cars = $datesReady
    ? getAvailableCars($selectedRentalDate, $selectedReturnDate)
    : [];
$customers = $datesReady
    ? getAllCustomers($selectedRentalDate, $selectedReturnDate)
    : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datesReady = !empty($selectedRentalDate) && !empty($selectedReturnDate)
        && $selectedReturnDate >= $selectedRentalDate;
    $customers = $datesReady
        ? getAllCustomers($selectedRentalDate, $selectedReturnDate)
        : [];
    $cars = $datesReady
        ? getAvailableCars($selectedRentalDate, $selectedReturnDate)
        : [];

    if ($selectedRentalDate !== '' && $selectedReturnDate !== '' && $selectedReturnDate < $selectedRentalDate) {
        $error = 'วันที่คืนรถต้องไม่ก่อนวันที่เริ่มเช่า กรุณาเลือกวันที่คืนใหม่';
    } elseif (!$datesReady) {
        $error = 'กรุณาเลือกวันที่เริ่มเช่าและวันที่คืนรถก่อน';
    } elseif ($customer_id !== '' && $car_id !== '' && $selectedRentalDate !== '' && $selectedReturnDate !== '' && $payment_type !== '') {
        $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE customer_id = ?");
        $stmt->bind_param('s', $customer_id);
        $stmt->execute();
        $customerExists = $stmt->get_result()->fetch_assoc();

        if (!$customerExists) {
            $error = 'ไม่พบรหัสลูกค้าที่เลือก กรุณาเพิ่มลูกค้าก่อนทำรายการเช่า';
        } else {
            $activeCustomerStmt = $conn->prepare("SELECT rental_id FROM rentals WHERE customer_id = ? AND status NOT IN ('Completed', 'Returned') AND rental_date <= ? AND return_date >= ? LIMIT 1");
            $activeCustomerStmt->bind_param('sss', $customer_id, $selectedReturnDate, $selectedRentalDate);
            $activeCustomerStmt->execute();
            $activeCustomer = $activeCustomerStmt->get_result()->fetch_assoc();

            if ($activeCustomer) {
                $error = 'ลูกค้ารายนี้มีรายการเช่าทับช่วงวันที่เลือกอยู่แล้ว จึงยังไม่สามารถเลือกซ้ำได้';
            } else {
                try {
                    addRental($customer_id, $car_id, $selectedRentalDate, $selectedReturnDate, $payment_type);
                    header('Location: rentals.php');
                    exit;
                } catch (Exception $e) {
                    $error = $e->getMessage();
                    $cars = getAvailableCars($selectedRentalDate, $selectedReturnDate);
                }
            }
        }
    } else {
        $error = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มรายการเช่า - <?= e(app_name()) ?></title>
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
            <div class="page-eyebrow"><i class="fa-solid fa-plus"></i> สร้างรายการเช่าใหม่</div>
            <h1 class="page-title">เพิ่มรายการเช่า</h1>
            <p class="page-subtitle">เลือกลูกค้า ช่วงวันเช่า และรถที่ว่างจริงในระบบ</p>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger mb-4"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="form-card" style="max-width: 860px; margin: 0 auto;">
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="rental_date">วันที่เริ่มเช่า</label>
                    <input type="date" name="rental_date" id="rental_date" class="form-control" value="<?= e($selectedRentalDate) ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="return_date">วันที่กำหนดคืนรถ</label>
                    <input type="date" name="return_date" id="return_date" class="form-control" min="<?= e($selectedRentalDate) ?>" value="<?= e($selectedReturnDate) ?>" aria-describedby="rentalDateError" required>
                    <div id="rentalDateError" class="field-error" role="alert" hidden>วันที่คืนรถต้องไม่ก่อนวันที่เริ่มเช่า กรุณาเลือกวันที่คืนใหม่</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="customer_id">รหัสลูกค้า</label>
                    <select name="customer_id" id="customer_id" class="form-select" required <?= !$datesReady ? 'disabled' : '' ?>>
                        <option value=""><?= $datesReady ? '-- เลือกรหัสลูกค้า --' : '-- กรุณาเลือกวันก่อน --' ?></option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= e($customer['customer_id']) ?>" <?= $customer_id === $customer['customer_id'] ? 'selected' : '' ?>>
                                <?= e($customer['customer_id']) ?> - <?= e($customer['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">
                        <?= $datesReady
                            ? 'ระบบจะแสดงเฉพาะลูกค้าที่ไม่มีรายการเช่าทับช่วงวันที่เลือก'
                            : 'กรุณาเลือกวันที่เริ่มเช่าและวันที่คืนรถก่อน แล้วระบบจะแสดงเฉพาะลูกค้าที่ว่างในช่วงวันที่เลือก' ?>
                    </small>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="car_id">รหัสรถยนต์</label>
                    <select name="car_id" id="car_id" class="form-select" required <?= !$datesReady ? 'disabled' : '' ?>>
                        <option value=""><?= $datesReady ? '-- เลือกคันที่เช่าก่อน --' : '-- กรุณาเลือกวันก่อน --' ?></option>
                        <?php foreach ($cars as $car): ?>
                            <option value="<?= e($car['car_id']) ?>"
                                data-rate="<?= e($car['daily_rate']) ?>"
                                data-label="<?= e($car['brand'] . ' ' . $car['model']) ?>"
                                <?= $car_id === $car['car_id'] ? 'selected' : '' ?>>
                                <?= e($car['car_id']) ?> - <?= e($car['brand'] . ' ' . $car['model']) ?> (<?= e(number_format((float) $car['daily_rate'], 2)) ?> บาท/วัน)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="payment_type">ช่องทางการชำระเงิน</label>
                    <select name="payment_type" id="payment_type" class="form-select" required>
                        <option value="Cash" <?= $payment_type === 'Cash' ? 'selected' : '' ?>>เงินสด</option>
                        <option value="Credit Card" <?= $payment_type === 'Credit Card' ? 'selected' : '' ?>>บัตรเครดิต</option>
                        <option value="Bank Transfer" <?= $payment_type === 'Bank Transfer' ? 'selected' : '' ?>>โอนผ่านธนาคาร</option>
                        <option value="QR Payment" <?= $payment_type === 'QR Payment' ? 'selected' : '' ?>>ชำระผ่าน QR</option>
                    </select>
                </div>

                <div class="col-12">
                    <div class="info-note" id="availabilityNote" role="status" aria-live="polite">
                        <?= $datesReady
                            ? (count($cars) > 0
                                ? 'พบรถว่าง ' . count($cars) . ' คัน และแสดงเฉพาะลูกค้าที่ว่างในช่วงวันที่เลือก'
                                : 'ไม่พบรถว่างในช่วงวันที่เลือก กรุณาเปลี่ยนช่วงวันเช่า')
                            : 'กรุณาเลือกวันที่เริ่มเช่าและวันที่คืนรถก่อน ระบบจะแสดงเฉพาะลูกค้าและรถที่ว่างในช่วงวันที่เลือก' ?>
                    </div>
                </div>
            </div>

            <div class="section-card mt-4" id="rentalSummary">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h3 class="h5 mb-1">สรุปราคาเช่า</h3>
                        <p class="text-muted mb-0">ระบบจะคำนวณจากจำนวนวันเช่า × ราคา/วัน อัตโนมัติ</p>
                    </div>
                    <span class="status-badge status-completed">คำนวณอัตโนมัติ</span>
                </div>
                <div class="row g-3">
                    <div class="col-md-6 col-lg-3">
                        <div class="summary-chip h-100">
                            <small>รถที่เลือก</small>
                            <strong id="summaryCar">-</strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="summary-chip h-100">
                            <small>ราคา/วัน</small>
                            <strong id="summaryRate">฿0.00</strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="summary-chip h-100">
                            <small>จำนวนวันเช่า</small>
                            <strong id="summaryDays">0 วัน</strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="summary-chip h-100">
                            <small>ราคารวม</small>
                            <strong id="summaryTotal">฿0.00</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" id="saveRentalButton" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> บันทึกรายการเช่า</button>
                <a href="rentals.php" class="btn btn-secondary">ย้อนกลับ</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const carSelect = document.getElementById('car_id');
    const rentalDate = document.getElementById('rental_date');
    const returnDate = document.getElementById('return_date');
    const dateError = document.getElementById('rentalDateError');
    const saveButton = document.getElementById('saveRentalButton');
    const summaryCar = document.getElementById('summaryCar');
    const summaryRate = document.getElementById('summaryRate');
    const summaryDays = document.getElementById('summaryDays');
    const summaryTotal = document.getElementById('summaryTotal');
    const availabilityNote = document.getElementById('availabilityNote');
    const initialCarId = <?= json_encode($car_id) ?>;

    function formatMoney(value) {
        return new Intl.NumberFormat('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(value || 0);
    }

    function calculateDays(start, end) {
        if (!start || !end) return 0;
        const startDate = new Date(start + 'T00:00:00');
        const endDate = new Date(end + 'T00:00:00');
        const diffMs = endDate - startDate;
        if (isNaN(diffMs) || diffMs < 0) return 0;
        const diffDays = Math.ceil(diffMs / 86400000);
        return diffDays < 1 ? 1 : diffDays;
    }


    function refreshAvailabilityByDate() {
        const start = rentalDate.value;
        const end = returnDate.value;
        if (start && end && end < start) return;
        if (!start || !end) return;

        const url = new URL(window.location.href);
        url.searchParams.set('rental_date', start);
        url.searchParams.set('return_date', end);
        window.location.href = url.toString();
    }

    rentalDate.addEventListener('change', refreshAvailabilityByDate);
    returnDate.addEventListener('change', refreshAvailabilityByDate);

    function updateSummary() {
        const selected = carSelect.options[carSelect.selectedIndex];
        const label = selected && selected.dataset.label ? selected.dataset.label : '-';
        const rate = selected && selected.dataset.rate ? parseFloat(selected.dataset.rate) : 0;
        const days = calculateDays(rentalDate.value, returnDate.value);
        const total = rate * days;

        summaryCar.textContent = label;
        summaryRate.textContent = '฿' + formatMoney(rate);
        summaryDays.textContent = days > 0 ? days + ' วัน' : '0 วัน';
        summaryTotal.textContent = '฿' + formatMoney(total);
    }

    function setSelectPlaceholder(message) {
        carSelect.disabled = true;
        carSelect.innerHTML = '<option value="">' + message + '</option>';
        updateSummary();
    }

    function syncDateConstraint() {
        returnDate.min = rentalDate.value || '';
        const invalid = Boolean(rentalDate.value && returnDate.value && returnDate.value < rentalDate.value);
        dateError.hidden = !invalid;
        returnDate.classList.toggle('is-invalid', invalid);
        returnDate.setCustomValidity(invalid ? 'วันที่คืนรถต้องไม่ก่อนวันที่เริ่มเช่า' : '');
        saveButton.disabled = invalid;
        return !invalid;
    }

    async function loadAvailableCars() {
        if (!syncDateConstraint()) {
            availabilityNote.textContent = 'วันที่คืนต้องไม่ก่อนวันที่เริ่มเช่า';
            setSelectPlaceholder('-- วันที่คืนไม่ถูกต้อง --');
            return;
        }
        const start = rentalDate.value;
        const end = returnDate.value;

        if (!start || !end) {
            availabilityNote.textContent = ' ';
            setSelectPlaceholder('-- เลือกวันที่เช่าก่อน --');
            return;
        }

        if (end < start) {
            availabilityNote.textContent = 'วันที่คืนต้องไม่ก่อนวันที่เริ่มเช่า';
            setSelectPlaceholder('-- วันที่คืนไม่ถูกต้อง --');
            return;
        }

        availabilityNote.textContent = 'กำลังตรวจสอบรถว่างตามช่วงวันที่ที่เลือก...';
        setSelectPlaceholder('-- กำลังโหลดรถที่ว่าง --');

        try {
            const response = await fetch('../api/get_available_cars.php?start_date=' + encodeURIComponent(start) + '&end_date=' + encodeURIComponent(end));
            const cars = await response.json();

            if (!Array.isArray(cars) || cars.length === 0) {
                availabilityNote.textContent = 'ไม่พบรถที่ว่างในช่วงวันที่ที่เลือก กรุณาเปลี่ยนวันเช่าหรือวันคืน';
                setSelectPlaceholder('-- ไม่มีรถว่างในช่วงวันที่เลือก --');
                return;
            }

            carSelect.disabled = false;
            carSelect.innerHTML = '<option value="">-- เลือกรถยนต์ --</option>';

            cars.forEach(function (car) {
                const option = document.createElement('option');
                option.value = car.car_id;
                option.dataset.rate = car.daily_rate;
                option.dataset.label = car.brand + ' ' + car.model;
                option.textContent = car.car_id + ' - ' + car.brand + ' ' + car.model + ' (' + formatMoney(parseFloat(car.daily_rate)) + ' บาท/วัน)';
                if (initialCarId && initialCarId === car.car_id) {
                    option.selected = true;
                }
                carSelect.appendChild(option);
            });

            availabilityNote.textContent = 'พบรถว่าง ' + cars.length + ' คัน ในช่วงวันที่ที่เลือก';
            updateSummary();
        } catch (error) {
            availabilityNote.textContent = 'ไม่สามารถโหลดรายการรถที่ว่างได้ กรุณาลองใหม่อีกครั้ง';
            setSelectPlaceholder('-- โหลดรถไม่สำเร็จ --');
        }
    }

    [rentalDate, returnDate].forEach(function (element) {
        element.addEventListener('change', syncDateConstraint);
        element.addEventListener('input', syncDateConstraint);
        element.addEventListener('change', loadAvailableCars);
        element.addEventListener('input', loadAvailableCars);
    });
    syncDateConstraint();

    carSelect.addEventListener('change', updateSummary);
    carSelect.addEventListener('input', updateSummary);

    loadAvailableCars();
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
