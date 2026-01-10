<?php
require __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
if (!$raw) {
    echo json_encode(['success' => false, 'message' => 'Empty request']);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$name = trim($data['name'] ?? '');
$phone = trim($data['phone'] ?? '');
$cart = $data['cart'] ?? [];

if ($name === '' || !preg_match('/^[\p{L} ]{2,}$/u', $name)) {
    echo json_encode(['success' => false, 'message' => 'Invalid name']);
    exit;
}
if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone']);
    exit;
}
if (!is_array($cart) || count($cart) === 0) {
    echo json_encode(['success' => false, 'message' => 'Cart empty']);
    exit;
}

// compute total from submitted cart (trust but sanitize)
$total = 0;
$items_summary = [];
foreach ($cart as $c) {
    $price = floatval($c['price'] ?? 0);
    $qty = intval($c['qty'] ?? 0);
    if ($qty <= 0) $qty = 1;
    $total += $price * $qty;
    $items_summary[] = ($c['name'] ?? 'Item') . " x{$qty}";
}

$items_json = json_encode($cart, JSON_UNESCAPED_UNICODE);

try {
    $stmt = $pdo->prepare("INSERT INTO orders (name, phone, total, items, created_at, status) VALUES (?,?,?,?,NOW(),?)");
    $stmt->execute([$name, $phone, $total, $items_json, 'new']);
    $orderId = $pdo->lastInsertId();
} catch (Throwable $e) {
    error_log('Order insert failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}

$msg  = "New Order (#{$orderId})%0A";
$msg .= "Name: " . rawurlencode($name) . "%0A";
$msg .= "Phone: " . rawurlencode($phone) . "%0A";
$msg .= "Items: " . rawurlencode(implode(', ', $items_summary)) . "%0A";
$msg .= "Total: ₹" . rawurlencode(number_format($total,2));

$whatsapp = "https://wa.me/91YOUR_NUMBER?text={$msg}"; // replace YOUR_NUMBER

echo json_encode(['success' => true, 'order_id' => $orderId, 'whatsapp' => $whatsapp]);
exit;
