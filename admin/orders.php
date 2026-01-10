<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Handle actions (complete / delete) via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0 && in_array($action, ['complete','delete'])) {
        if ($action === 'complete') {
            $q = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ? LIMIT 1");
            $q->execute([$id]);
        } else {
            $q = $pdo->prepare("DELETE FROM orders WHERE id = ? LIMIT 1");
            $q->execute([$id]);
        }
    }
    header('Location: orders.php');
    exit;
}

// fetch orders
$stmt = $pdo->prepare("SELECT id, name, phone, total, items, status, created_at FROM orders ORDER BY id DESC");
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin — Orders</title>
  <style>
    :root{--bg:#f6f7f9;--card:#fff;--accent:#c94f2c;--muted:#6b6b6b;--radius:10px}
    *{box-sizing:border-box}
    body{margin:0;font-family:Inter,system-ui,Segoe UI,Roboto,Arial;background:var(--bg);color:#222;padding:18px}
    .card{background:var(--card);padding:14px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.04)}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{padding:10px;border-bottom:1px solid #f1f1f1;text-align:left}
    th{background:#fafafa}
    .btn{background:var(--accent);color:#fff;padding:6px 10px;border-radius:8px;border:0;cursor:pointer}
    .small{background:#eee;color:#333;padding:6px 10px;border-radius:8px;border:0}
    pre{white-space:pre-wrap;font-family:inherit}
  </style>
</head>
<body>
  <a href="dashboard.php">← Back to Dashboard</a>
  <h2>Orders</h2>
  <div class="card">
    <?php if (empty($orders)): ?>
      <div class="muted">No orders yet.</div>
    <?php else: ?>
      <table>
        <thead><tr><th>ID</th><th>When</th><th>Name</th><th>Phone</th><th>Items</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><?php echo (int)$o['id']; ?></td>
              <td><?php echo htmlspecialchars($o['created_at'] ?? '-', ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($o['name'] ?? '-', ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($o['phone'] ?? '-', ENT_QUOTES); ?></td>
              <td><pre><?php echo htmlspecialchars(json_encode(json_decode($o['items'], true), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)); ?></pre></td>
              <td>₹<?php echo htmlspecialchars($o['total'] ?? '0', ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($o['status'] ?? 'new', ENT_QUOTES); ?></td>
              <td>
                <?php if (($o['status'] ?? '') !== 'completed'): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="id" value="<?php echo (int)$o['id']; ?>">
                    <input type="hidden" name="action" value="complete">
                    <button class="btn" type="submit">Mark Complete</button>
                  </form>
                <?php endif; ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this order?');">
                  <input type="hidden" name="id" value="<?php echo (int)$o['id']; ?>">
                  <input type="hidden" name="action" value="delete">
                  <button class="small" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>