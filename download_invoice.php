<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/invoice.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/site.php';

ensure_session_started();

$type = ($_GET['type'] ?? '') === 'booking' ? 'booking' : 'order';
$id = (int) ($_GET['id'] ?? 0);
$adminId = (int) ($_GET['admin_id'] ?? 0);
$token = trim((string) ($_GET['token'] ?? ''));
$siteAdminId = site_admin_id($rootPdo);

if ($id <= 0 || $adminId <= 0 || $adminId !== $siteAdminId) {
    http_response_code(400);
    exit('Invalid invoice request.');
}

try {
    $sitePdo = site_pdo($rootPdo);
} catch (Throwable $e) {
    http_response_code(404);
    exit('Invoice not found.');
}

$table = $type === 'booking' ? 'bookings' : 'orders';

try {
    $stmt = $sitePdo->prepare("SELECT id, phone FROM `{$table}` WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    $record = null;
}

if (!$record) {
    http_response_code(404);
    exit('Invoice not found.');
}

$phone = (string) ($record['phone'] ?? '');
$canAccess = !empty($_SESSION['admin']);
if (!$canAccess) {
    $canAccess = verify_invoice_access_token($token, $type, $id, $adminId, $phone);
}

if (!$canAccess) {
    http_response_code(403);
    exit('Access denied.');
}

$invoicePath = existing_invoice_path($type, $id, $adminId);
if (!is_file($invoicePath)) {
    http_response_code(404);
    exit('Invoice file not found.');
}

$downloadName = $type . '_invoice_' . $id . '.pdf';
header('Content-Type: application/pdf');
header('Content-Length: ' . (string) filesize($invoicePath));
header('Content-Disposition: inline; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');

readfile($invoicePath);
exit;
