<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function app_login_username(): string {
    return 'Mona Jaidee';
}

function app_login_password(): string {
    return 'P@ssw0rd';
}

function is_logged_in(): bool {
    return !empty($_SESSION['logged_in']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ../login.php');
        exit;
    }
}
?>
