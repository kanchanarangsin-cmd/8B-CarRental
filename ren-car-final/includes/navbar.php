<?php
require_once __DIR__ . '/ui_helpers.php';
require_once __DIR__ . '/auth.php';

require_login();

function render_navbar(string $active = ''): void {
    $items = [
        'dashboard' => ['label' => 'แดชบอร์ด', 'href' => 'dashboard.php', 'icon' => 'fa-gauge-high'],
        'cars' => ['label' => 'รถยนต์', 'href' => 'cars.php', 'icon' => 'fa-car-side'],
        'customers' => ['label' => 'ลูกค้า', 'href' => 'customers.php', 'icon' => 'fa-users'],
        'rentals' => ['label' => 'การเช่า', 'href' => 'rentals.php', 'icon' => 'fa-file-contract'],
        'return_inspections' => ['label' => 'ตรวจรถคืน', 'href' => 'return_inspections.php', 'icon' => 'fa-clipboard-check'],
        'employees' => ['label' => 'พนักงาน', 'href' => 'employees.php', 'icon' => 'fa-user-tie'],
        'payments' => ['label' => 'การชำระเงิน', 'href' => 'payments.php', 'icon' => 'fa-wallet'],
        'analytics' => ['label' => 'วิเคราะห์', 'href' => 'analytics.php', 'icon' => 'fa-chart-column'],
        'reviews' => ['label' => 'รีวิว', 'href' => 'reviews.php', 'icon' => 'fa-star'],
    ];
    ?>
    <nav class="navbar navbar-expand-lg app-navbar navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <span class="brand-mark"><i class="fa-solid fa-car-side"></i></span>
                <span><?= e(app_name()) ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="สลับเมนู">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNavbar">
                <div class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <?php foreach ($items as $key => $item): ?>
                        <a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
                            <i class="fa-solid <?= e($item['icon']) ?>"></i>
                            <span><?= e($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                    <a class="nav-link nav-link-logout" href="../logout.php">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>ออกจากระบบ</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>
    <?php
}

