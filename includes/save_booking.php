<?php
require __DIR__ . '/db.php';
require __DIR__ . '/app.php';
require __DIR__ . '/invoice.php';

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$date = trim($_POST['date'] ?? '');
$size = (int) ($_POST['size'] ?? 0);
$redirectBase = app_url('index.php');

$errors = [];

if ($name === '' || !preg_match('/^[\p{L} ]{2,}$/u', $name)) {
    $errors[] = 'Invalid name';
}

if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
    $errors[] = 'Invalid Indian mobile number';
}

$timestamp = strtotime($date);
if ($timestamp === false || $timestamp < time() - 60) {
    $errors[] = 'Invalid date';
}

if ($size < 1) {
    $size = 1;
}

if (!empty($errors)) {
    header('Location: ' . $redirectBase . '?booking_err=1');
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO bookings (name, phone, date, size, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$name, $phone, date('Y-m-d H:i:s', $timestamp), $size]);
    $bookingId = (int) $pdo->lastInsertId();
} catch (Throwable $e) {
    error_log('Booking insert failed: ' . $e->getMessage());
    header('Location: ' . $redirectBase . '?booking_err=1');
    exit;
}

$invoiceDir = dirname(__DIR__) . '/assets/invoices';
if (!is_dir($invoiceDir)) {
    @mkdir($invoiceDir, 0777, true);
}

$invoiceRelative = 'assets/invoices/booking_' . $bookingId . '.pdf';
$invoicePath = dirname(__DIR__) . '/assets/invoices/booking_' . $bookingId . '.pdf';
generate_booking_invoice_pdf([
    'booking_id' => $bookingId,
    'created_at' => date('Y-m-d H:i:s'),
    'name' => $name,
    'phone' => $phone,
    'date' => date('Y-m-d H:i:s', $timestamp),
    'size' => $size,
], $invoicePath);

header('Location: ' . $redirectBase . '?booking=success&booking_invoice=' . rawurlencode($invoiceRelative));
exit;
