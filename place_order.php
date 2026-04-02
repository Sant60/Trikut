<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/app.php';
require __DIR__ . '/includes/invoice.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/site.php';

header('Content-Type: application/json; charset=utf-8');

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

$siteAdminId = site_admin_id($rootPdo);
$sitePdo = site_pdo($rootPdo);

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

$requestedQtyById = [];
foreach ($cart as $item) {
    $id = (int) ($item['id'] ?? 0);
    $qty = (int) ($item['qty'] ?? 0);

    if ($id <= 0 || $qty < 1 || $qty > 99) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Cart items are invalid.']);
        exit;
    }

    $requestedQtyById[$id] = ($requestedQtyById[$id] ?? 0) + $qty;
    if ($requestedQtyById[$id] > 99) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Cart items are invalid.']);
        exit;
    }
}

if (empty($requestedQtyById)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Cart items are invalid.']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($requestedQtyById), '?'));
try {
    $stmt = $sitePdo->prepare("SELECT id, name, price FROM menu WHERE active = 1 AND id IN ({$placeholders})");
    $stmt->execute(array_keys($requestedQtyById));
    $menuRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Menu verification failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not verify the menu items.']);
    exit;
}

$menuById = [];
foreach ($menuRows as $row) {
    $menuById[(int) $row['id']] = $row;
}

if (count($menuById) !== count($requestedQtyById)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'One or more items are no longer available.']);
    exit;
}

$normalizedCart = [];
$total = 0.0;
foreach ($requestedQtyById as $id => $qty) {
    $menuItem = $menuById[$id] ?? null;
    if (!$menuItem) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'One or more items are no longer available.']);
        exit;
    }

    $price = round((float) ($menuItem['price'] ?? 0), 2);
    $normalizedCart[] = [
        'id' => $id,
        'name' => normalize_text((string) ($menuItem['name'] ?? 'Item'), 120),
        'price' => $price,
        'qty' => $qty,
    ];

    $total += $price * $qty;
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
    $stmt = $sitePdo->prepare('INSERT INTO orders (name, phone, total, items, created_at, status) VALUES (?, ?, ?, ?, NOW(), ?)');
    $stmt->execute([
        $name,
        $normalizedPhone,
        number_format($total, 2, '.', ''),
        json_encode($orderPayload, JSON_UNESCAPED_UNICODE),
        'new',
    ]);
    $orderId = (int) $sitePdo->lastInsertId();
} catch (Throwable $e) {
    error_log('Order insert failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save the order.']);
    exit;
}

$createdAt = date('Y-m-d H:i:s');
try {
    $stmt = $sitePdo->prepare('SELECT created_at FROM orders WHERE id = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['created_at'])) {
        $createdAt = (string) $row['created_at'];
    }
} catch (Throwable $e) {
}

$invoicePath = invoice_storage_path('order', $orderId, $siteAdminId);
generate_order_invoice_pdf([
    'order_id' => $orderId,
    'created_at' => $createdAt,
    'name' => $name,
    'phone' => $normalizedPhone,
    'delivery_type' => $deliveryTypeMap[$deliveryType],
    'items' => $normalizedCart,
    'total' => $total,
], $invoicePath);

$invoiceToken = generate_invoice_access_token('order', $orderId, $siteAdminId, $normalizedPhone);

$payload = [
    'success' => true,
    'order_id' => $orderId,
    'invoice_url' => app_url('download_invoice.php?type=order&id=' . $orderId . '&admin_id=' . $siteAdminId . '&token=' . rawurlencode($invoiceToken)),
    'message' => 'Order placed successfully. The restaurant team can now view it in the admin panel.',
];

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
