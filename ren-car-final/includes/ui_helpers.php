<?php

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function app_name(): string {
    return 'ระบบเช่ารถยนต์';
}

function status_label(string $status): string {
    $map = [
        'Available' => 'พร้อมให้เช่า',
        'Rented' => 'กำลังเช่า',
        'Maintenance' => 'ซ่อมบำรุง',
        'Booked' => 'จองล่วงหน้า',
        'Completed' => 'เสร็จสิ้น',
        'Returned' => 'เสร็จสิ้น',
        'Active' => 'ปฏิบัติงาน',
        'Inactive' => 'ไม่ปฏิบัติงาน',
    ];

    return $map[$status] ?? $status;
}

function status_badge_class(string $status): string {
    $map = [
        'Available' => 'status-available',
        'Rented' => 'status-rented',
        'Maintenance' => 'status-maintenance',
        'Booked' => 'status-default',
        'Completed' => 'status-completed',
        'Returned' => 'status-completed',
        'Active' => 'status-active',
        'Inactive' => 'status-inactive',
    ];

    return $map[$status] ?? 'status-default';
}

function payment_type_label(string $type): string {
    $map = [
        'Bank Transfer' => 'โอนผ่านธนาคาร',
        'Cash' => 'เงินสด',
        'Credit Card' => 'บัตรเครดิต',
        'QR Payment' => 'ชำระผ่าน QR',
    ];

    return $map[$type] ?? $type;
}

function format_display_date(?string $date): string {
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return e($date);
    }

    return date('d/m/Y', $timestamp);
}

function format_money($amount, string $prefix = '฿'): string {
    return $prefix . number_format((float)$amount, 2);
}

function payment_type_color_map(): array {
    return [
        'Bank Transfer' => '#4f46e5',
        'Cash' => '#3b82f6',
        'Credit Card' => '#8b5cf6',
        'QR Payment' => '#f59e0b',
    ];
}

function car_chart_palette(): array {
    return ['#4f46e5', '#3b82f6', '#cbd5e1'];
}

