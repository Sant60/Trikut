<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/invoice.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/tenant.php';

header('Content-Type: application/json; charset=utf-8');

$adminId = resolve_public_admin_id($pdo);

$raw = file_get_contents('php://input');
if (!$raw) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Empty request.']);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$csrfToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_request($csrfToken)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid request token.']);
    exit;
}

$name = normalize_text($data['name'] ?? '', 100);
$phone = trim($data['phone'] ?? '');
$deliveryType = trim((string) ($data['delivery_type'] ?? ''));
$cart = $data['cart'] ?? [];

if ($name === '' || !is_valid_person_name($name)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid name.']);
    exit;
}

if (!is_valid_indian_phone($phone)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid phone number.']);
    exit;
}

$normalizedPhone = normalize_indian_phone($phone);

$deliveryTypeMap = [
    'dine_in' => 'Dine In',
    'home_delivery' => 'Home Delivery',
];

if (!isset($deliveryTypeMap[$deliveryType])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please select a valid delivery type.']);
    exit;
}

if (!is_array($cart) || count($cart) === 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
    exit;
}

$normalizedCart = [];
$total = 0.0;

foreach ($cart as $item) {
    $itemName = normalize_text((string) ($item['name'] ?? ''), 120);
    $price = (float) ($item['price'] ?? 0);
    $qty = (int) ($item['qty'] ?? 0);
    $id = (int) ($item['id'] ?? 0);

    if ($itemName === '' || $price < 0 || $price > 100000 || $qty < 1 || $qty > 99) {
        continue;
    }

    $normalizedCart[] = [
        'id' => $id,
        'name' => $itemName,
        'price' => round($price, 2),
        'qty' => $qty,
    ];

    $lineTotal = round($price, 2) * $qty;
    $total += $lineTotal;
}

if (empty($normalizedCart)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Cart items are invalid.']);
    exit;
}

$orderPayload = [
    'delivery_type' => $deliveryTypeMap[$deliveryType],
    'items' => $normalizedCart,
];

try {
    $stmt = $pdo->prepare('INSERT INTO orders (admin_id, name, phone, total, items, created_at, status) VALUES (?, ?, ?, ?, ?, NOW(), ?)');
    $stmt->execute([
        $adminId,
        $name,
        $normalizedPhone,
        number_format($total, 2, '.', ''),
        json_encode($orderPayload, JSON_UNESCAPED_UNICODE),
        'new',
    ]);
    $orderId = (int) $pdo->lastInsertId();
} catch (Throwable $e) {
    error_log('Order insert failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save the order.']);
    exit;
}

$invoiceDir = __DIR__ . '/assets/invoices';
if (!is_dir($invoiceDir)) {
    @mkdir($invoiceDir, 0777, true);
}

$createdAt = date('Y-m-d H:i:s');
try {
    $stmt = $pdo->prepare('SELECT created_at FROM orders WHERE id = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['created_at'])) {
        $createdAt = (string) $row['created_at'];
    }
} catch (Throwable $e) {
}

$invoicePath = $invoiceDir . '/order_' . $orderId . '.pdf';
generate_order_invoice_pdf([
    'order_id' => $orderId,
    'created_at' => $createdAt,
    'name' => $name,
    'phone' => $normalizedPhone,
    'delivery_type' => $deliveryTypeMap[$deliveryType],
    'items' => $normalizedCart,
    'total' => $total,
], $invoicePath);

$payload = [
    'success' => true,
    'order_id' => $orderId,
    'invoice_url' => 'assets/invoices/order_' . $orderId . '.pdf',
    'message' => 'Order placed successfully. The restaurant team can now view it in the admin panel.',
];

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
