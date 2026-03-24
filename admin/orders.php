<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/tenant.php';

ensure_multi_admin_schema($pdo);
$currentAdminId = (int) $_SESSION['admin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if (!verify_csrf_request()) {
        http_response_code(419);
        exit('Invalid request token.');
    }

    if ($id > 0 && in_array($action, ['complete', 'delete'], true)) {
        if ($action === 'complete') {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ? AND admin_id = ? LIMIT 1");
            $stmt->execute([$id, $currentAdminId]);
        } else {
            $stmt = $pdo->prepare('DELETE FROM orders WHERE id = ? AND admin_id = ? LIMIT 1');
            $stmt->execute([$id, $currentAdminId]);
        }
    }

    header('Location: orders.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, phone, total, items, status, created_at FROM orders WHERE admin_id = ? ORDER BY id DESC');
$stmt->execute([$currentAdminId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function decode_order_items(?string $raw): array
{
    $decoded = json_decode((string) $raw, true);

    if (is_array($decoded) && isset($decoded['items']) && is_array($decoded['items'])) {
        return [
            'delivery_type' => (string) ($decoded['delivery_type'] ?? 'Not specified'),
            'items' => $decoded['items'],
        ];
    }

    if (is_array($decoded)) {
        return [
            'delivery_type' => 'Not specified',
            'items' => $decoded,
        ];
    }

    return [
        'delivery_type' => 'Not specified',
        'items' => [],
        'fallback' => trim((string) $raw),
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Orders</title>
  <script>
    (function () {
      try {
        var savedTheme = localStorage.getItem("admin-theme");
        if (savedTheme === "dark" || savedTheme === "light") {
          document.documentElement.setAttribute("data-theme", savedTheme);
        }
      } catch (e) {}
    })();
  </script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
  <link rel="stylesheet" href="../CSS/style.css?v=<?php echo (int) (@filemtime(__DIR__ . '/../CSS/style.css') ?: time()); ?>">
</head>
<body class="admin-body">
  <div class="admin-page">
    <div class="admin-page-head">
      <div>
        <a class="admin-back-link" href="dashboard.php">&larr; Back to Dashboard</a>
        <h1 class="admin-title">Orders</h1>
        <div class="admin-subtitle">Review new customer orders, delivery preference, item lines, and current status.</div>
      </div>
      <button class="theme-toggle admin-theme-toggle" type="button" data-theme-toggle data-theme-storage="admin-theme" aria-pressed="false" aria-label="Switch to dark mode" title="Toggle admin light and dark mode">
        <i class="fa-solid fa-toggle-off" aria-hidden="true"></i>
        <i class="fa-solid fa-toggle-on" aria-hidden="true"></i>
      </button>
    </div>

    <div class="admin-card">
      <?php if (empty($orders)): ?>
        <div class="admin-empty">No orders have been placed yet.</div>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>When</th>
                <th>Customer</th>
                <th>Order Details</th>
                <th>Total</th>
                <th>Invoice</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $order): ?>
                <?php $parsed = decode_order_items($order['items'] ?? ''); ?>
                <?php
                $invoiceRelative = 'assets/invoices/order_' . (int) $order['id'] . '.pdf';
                $invoiceFile = dirname(__DIR__) . '/assets/invoices/order_' . (int) $order['id'] . '.pdf';
                ?>
                <tr>
                  <td>#<?php echo (int) $order['id']; ?></td>
                  <td><?php echo htmlspecialchars($order['created_at'] ?? '-', ENT_QUOTES); ?></td>
                  <td>
                    <strong><?php echo htmlspecialchars($order['name'] ?? '-', ENT_QUOTES); ?></strong><br>
                    <span class="admin-muted"><?php echo htmlspecialchars($order['phone'] ?? '-', ENT_QUOTES); ?></span>
                  </td>
                  <td>
                    <div style="margin-bottom:10px;font-size:.92rem"><strong>Delivery:</strong> <?php echo htmlspecialchars($parsed['delivery_type'], ENT_QUOTES); ?></div>
                    <?php if (!empty($parsed['items'])): ?>
                      <ul class="admin-list">
                        <?php foreach ($parsed['items'] as $item): ?>
                          <?php
                          $itemName = (string) ($item['name'] ?? 'Item');
                          $qty = (int) ($item['qty'] ?? 0);
                          $price = number_format((float) ($item['price'] ?? 0), 2);
                          ?>
                          <li><?php echo htmlspecialchars($itemName, ENT_QUOTES); ?> x <?php echo $qty; ?> at &#8377;<?php echo $price; ?></li>
                        <?php endforeach; ?>
                      </ul>
                    <?php elseif (!empty($parsed['fallback'])): ?>
                      <div class="admin-muted"><?php echo htmlspecialchars($parsed['fallback'], ENT_QUOTES); ?></div>
                    <?php else: ?>
                      <div class="admin-muted">No line items available.</div>
                    <?php endif; ?>
                  </td>
                  <td style="font-weight:700">&#8377;<?php echo htmlspecialchars((string) ($order['total'] ?? '0.00'), ENT_QUOTES); ?></td>
                  <td>
                    <?php if (is_file($invoiceFile)): ?>
                      <a class="admin-btn-secondary" href="<?php echo htmlspecialchars(app_url($invoiceRelative), ENT_QUOTES); ?>" target="_blank" rel="noopener">View Invoice</a>
                    <?php else: ?>
                      <span class="admin-muted">Not generated</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="admin-status<?php echo (($order['status'] ?? '') === 'completed') ? ' completed' : ''; ?>">
                      <?php echo htmlspecialchars(ucfirst((string) ($order['status'] ?? 'new')), ENT_QUOTES); ?>
                    </span>
                  </td>
                  <td>
                    <div class="admin-inline-actions">
                      <?php if (($order['status'] ?? '') !== 'completed'): ?>
                        <form method="post">
                          <?php echo csrf_input(); ?>
                          <input type="hidden" name="id" value="<?php echo (int) $order['id']; ?>">
                          <input type="hidden" name="action" value="complete">
                          <button class="admin-btn-primary" type="submit">Mark Complete</button>
                        </form>
                      <?php endif; ?>
                      <form method="post" onsubmit="return confirm('Delete this order?');">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $order['id']; ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="admin-btn-danger" type="submit">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <script src="../Script/theme.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/theme.js') ?: time()); ?>"></script>
</body>
</html>
