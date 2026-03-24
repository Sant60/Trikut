<?php
require __DIR__ . '/db.php';
require __DIR__ . '/app.php';
require __DIR__ . '/invoice.php';
require __DIR__ . '/security.php';
require __DIR__ . '/tenant.php';

$name = normalize_text($_POST['name'] ?? '', 100);
$phone = trim((string) ($_POST['phone'] ?? ''));
$date = trim((string) ($_POST['date'] ?? ''));
$size = (int) ($_POST['size'] ?? 0);
$redirectBase = app_url('index.php');
$adminId = resolve_public_admin_id($pdo);

$errors = [];

if (!verify_csrf_request()) {
    $errors[] = 'Invalid request token';
}

if ($name === '' || !is_valid_person_name($name)) {
    $errors[] = 'Invalid name';
}

if (!is_valid_indian_phone($phone)) {
    $errors[] = 'Invalid Indian mobile number';
}

$normalizedPhone = normalize_indian_phone($phone);

$timestamp = strtotime($date);
if ($timestamp === false || $timestamp < time() - 60) {
    $errors[] = 'Invalid date';
}

if ($size < 1 || $size > 50) {
    $errors[] = 'Invalid guest count';
}

if (!empty($errors)) {
    header('Location: ' . $redirectBase . '?booking_err=1');
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO bookings (admin_id, name, phone, date, size, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$adminId, $name, $normalizedPhone, date('Y-m-d H:i:s', $timestamp), $size]);
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
    'phone' => $normalizedPhone,
    'date' => date('Y-m-d H:i:s', $timestamp),
    'size' => $size,
], $invoicePath);

header('Location: ' . $redirectBase . '?booking=success&booking_invoice=' . rawurlencode($invoiceRelative));
exit;
