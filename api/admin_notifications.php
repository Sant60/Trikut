<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$latestOrder = null;
$latestBooking = null;

try {
    $stmt = $pdo->query("SELECT id, name, total, created_at FROM orders ORDER BY id DESC LIMIT 1");
    $latestOrder = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    $latestOrder = null;
}

try {
    $stmt = $pdo->query("SELECT id, name, date, created_at FROM bookings ORDER BY id DESC LIMIT 1");
    $latestBooking = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    $latestBooking = null;
}

echo json_encode([
    'success' => true,
    'latest_order' => $latestOrder,
    'latest_booking' => $latestBooking,
], JSON_UNESCAPED_UNICODE);
