<?php
// start session only when not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// protect admin pages
if (empty($_SESSION['admin'])) {
    // build a safe redirect target
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $inAdmin = strpos($script, '/admin/') !== false;
    // if the current script is inside /admin/, redirect to relative login.php otherwise root-relative
    $redirect = $inAdmin ? 'login.php' : '/admin/login.php';
    header('Location: ' . $redirect);
    exit;
}