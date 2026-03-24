<?php
// start session only when not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// protect admin pages
if (empty($_SESSION['admin'])) {
    require_once __DIR__ . '/app.php';
    $redirect = app_url('admin/login.php');
    header('Location: ' . $redirect);
    exit;
}
