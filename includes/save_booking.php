<?php
require __DIR__ . '/db.php';

$name  = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$date  = trim($_POST['date'] ?? '');
$size  = intval($_POST['size'] ?? 0);

// server-side validation (basic)
$errors = [];
if ($name === '' || mb_strlen(preg_replace('/\s+/', '', $name)) < 2 || !preg_match('/^[\p{L} ]+$/u', $name)) {
    $errors[] = 'Invalid name';
}
if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
    $errors[] = 'Invalid Indian mobile number';
}
$dt = strtotime($date);
if ($dt === false) {
    $errors[] = 'Invalid date';
}
if ($size < 1) $size = 1;

if (!empty($errors)) {
    // simple fallback: redirect back to homepage with error flag (UI can show message)
    header('Location: /index.php?booking_err=1');
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO bookings (name, phone, date, size, created_at) VALUES (?,?,?,?,NOW())");
    $stmt->execute([$name, $phone, date('Y-m-d H:i:s', $dt), $size]);
} catch (Throwable $e) {
    error_log('Booking insert failed: ' . $e->getMessage());
    header('Location: /index.php?booking_err=1');
    exit;
}

$msg  = "New Table Booking%0A";
$msg .= "Name: " . rawurlencode($name) . "%0A";
$msg .= "Phone: " . rawurlencode($phone) . "%0A";
$msg .= "Date: " . rawurlencode(date('Y-m-d H:i', $dt)) . "%0A";
$msg .= "Guests: " . rawurlencode((string)$size);

$wa = "https://wa.me/91YOUR_NUMBER?text={$msg}"; // replace YOUR_NUMBER
header("Location: $wa");
exit;