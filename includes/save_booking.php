<?php
require __DIR__ . '/db.php';
require __DIR__ . '/app.php';
require __DIR__ . '/invoice.php';
require __DIR__ . '/security.php';
require __DIR__ . '/site.php';

$name = normalize_text($_POST['name'] ?? '', 100);
$phone = trim((string) ($_POST['phone'] ?? ''));
$date = trim((string) ($_POST['date'] ?? ''));
$size = (int) ($_POST['size'] ?? 0);
$siteAdminId = site_admin_id($rootPdo);
$redirectBase = app_url('index.php');
$sitePdo = site_pdo($rootPdo);

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

if ($size < 1 || $size > 20) {
    $errors[] = 'Invalid guest count';
}

if (!empty($errors)) {
    header('Location: ' . $redirectBase . '?booking_err=1');
    exit;
}

try {
    $bookingDate = date('Y-m-d H:i:s', $timestamp);
    $stmt = $sitePdo->prepare('INSERT INTO bookings (name, phone, date, size, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$name, $normalizedPhone, $bookingDate, $size]);
    $bookingId = (int) $sitePdo->lastInsertId();
} catch (Throwable $e) {
    error_log('Booking insert failed: ' . $e->getMessage());
    header('Location: ' . $redirectBase . '?booking_err=1');
    exit;
}

$invoicePath = invoice_storage_path('booking', $bookingId, $siteAdminId);
generate_booking_invoice_pdf([
    'booking_id' => $bookingId,
    'created_at' => date('Y-m-d H:i:s'),
    'name' => $name,
    'phone' => $normalizedPhone,
    'date' => date('Y-m-d H:i:s', $timestamp),
    'size' => $size,
], $invoicePath);

$invoiceToken = generate_invoice_access_token('booking', $bookingId, $siteAdminId, $normalizedPhone);
$invoiceRelative = 'download_invoice.php?type=booking&id=' . $bookingId . '&admin_id=' . $siteAdminId . '&token=' . rawurlencode($invoiceToken);

header('Location: ' . $redirectBase . '?booking=success&booking_invoice=' . rawurlencode($invoiceRelative));
exit;
