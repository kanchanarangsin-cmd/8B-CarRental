<?php
require_once '../config/db.php';
require_once '../includes/ui_helpers.php';
require_once '../includes/navbar.php';
require_once '../functions/payment_functions.php';
require_once '../functions/rental_functions.php';

syncRentalStatuses();
syncPaymentsWithRentals();
$totalIncome = getTotalIncome();
$incomeByType = getIncomeByType();
$search = trim($_GET['search'] ?? '');
$payments = getPaymentsLinked($search);
$paymentCount = count($payments);
$latestPaymentDate = $paymentCount > 0
    ? max(array_map(static fn($payment) => $payment['payment_date'] ?? '', $payments))
    : null;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>การชำระเงิน - <?= e(app_name()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/design_tokens.css" rel="stylesheet">
</head>
<body class="page-payments">
<?php render_navbar('payments'); ?>

<div class="container page-shell">
    <div class="page-header">
        <div>
            <div class="page-eyebrow"><i class="fa-solid fa-wallet"></i> การรับชำระเงิน</div>
            <h1 class="page-title">จัดการข้อมูลการชำระเงิน</h1>
        </div>
    </div>

    <div class="layout-grid two-one mb-4">
        <div class="hero-card equal-card-block">
            <div class="hero-label"><i class="fa-solid fa-coins"></i> รายได้รวม</div>
            <div class="hero-value"><span id="totalIncomeValue"><?= e(number_format((float)$totalIncome, 2)) ?></span></div>
            <p class="mb-0 mt-2">ยอดรับชำระสะสมจากทุกช่องทาง</p>
            <div class="summary-list mt-3">
                <div class="summary-chip">
                    <small>จำนวนรายการ</small>
                    <strong><?= e(number_format($paymentCount)) ?></strong>
                </div>
                <div class="summary-chip">
                    <small>ชำระล่าสุด</small>
                    <strong id="latestPaymentDateValue"><?= e($latestPaymentDate ? format_display_date($latestPaymentDate) : '-') ?></strong>
                </div>
            </div>
            <div class="quick-stat-row">
                <span class="quick-stat"><i class="fa-solid fa-link"></i> อัปเดตจากยอดรวมรายการเช่าอัตโนมัติ</span>
            </div>
        </div>

        <div class="chart-card chart-panel-md equal-card-block">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title">ช่องทางการชำระเงิน</h3>
                    <div class="chart-caption">สัดส่วนรายได้แยกตามประเภทการชำระ</div>
                </div>
            </div>
            <canvas id="paymentChart"></canvas>
        </div>
    </div>

    <div class="section-card mb-4">
        <label class="form-label" for="paymentSearch">ค้นหารายการชำระเงิน</label>
        <input type="text" id="paymentSearch" class="form-control" placeholder="ค้นหาจากรหัสการชำระ รหัสการเช่า รหัสลูกค้า ชื่อลูกค้า หรือประเภทรายการชำระเงิน" value="<?= e($search) ?>">
        <span id="searchLoading" class="muted-text" style="display:none; margin-top: 0.75rem;">กำลังค้นหา...</span>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>รหัสการชำระ</th>
                        <th>รหัสการเช่า</th>
                        <th>วันที่ชำระ</th>
                        <th>จำนวนเงิน</th>
                        <th>ประเภทการชำระ</th>
                        <th>ลูกค้า</th>
                        <th>รถยนต์</th>
                    </tr>
                </thead>
                <tbody id="paymentTableBody">
                    <?php if (count($payments) > 0): ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= e($payment['payment_id']) ?></td>
                                <td><?= e($payment['rental_id'] ?? '-') ?></td>
                                <td><?= e(format_display_date($payment['payment_date'])) ?></td>
                                <td class="text-currency"><?= e(format_money($payment['amount'])) ?></td>
                                <td><?= e(payment_type_label($payment['payment_type'])) ?></td>
                                <td><?= e(($payment['customer_id'] ?? '-') . ((isset($payment['customer_name']) && $payment['customer_name'] !== '') ? ' - ' . $payment['customer_name'] : '')) ?></td>
                                <td><?= e(($payment['car_id'] ?? '-') . ((isset($payment['car_name']) && $payment['car_name'] !== '') ? ' - ' . $payment['car_name'] : '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center empty-state">ไม่พบข้อมูลการชำระเงิน</td>
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
window.paymentTypeColorMap = {
    'Bank Transfer': '#6B55B4',
    'Cash': '#6B55B4',
    'Credit Card': '#6B55B4',
    'QR Payment': '#EEE9FA'
};
window.paymentTypeLabelMap = {
    'Bank Transfer': 'โอนผ่านธนาคาร',
    'Cash': 'เงินสด',
    'Credit Card': 'บัตรเครดิต',
    'QR Payment': 'ชำระผ่าน QR'
};

const incomeData = <?= json_encode($incomeByType, JSON_UNESCAPED_UNICODE) ?>;
const initialLabels = incomeData.map(item => window.paymentTypeLabelMap[item.payment_type] || item.payment_type);
const initialColors = incomeData.map(item => window.paymentTypeColorMap[item.payment_type] || '#6F667A');

Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
window.paymentChart = new Chart(document.getElementById('paymentChart'), {
    type: 'pie',
    data: {
        labels: initialLabels,
        datasets: [{
            label: 'รายได้ตามประเภท',
            data: incomeData.map(item => parseFloat(item.total)),
            backgroundColor: initialColors,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: { usePointStyle: true, boxWidth: 10, color: '#342D40', padding: 12, font: { size: 11 } }
            }
        }
    }
});
</script>
<script src="../assets/js/payments.js"></script>
</body>
</html>
