<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Handle actions (delete) via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0 && $action === 'delete') {
        $q = $pdo->prepare("DELETE FROM bookings WHERE id = ? LIMIT 1");
        $q->execute([$id]);
    }
    header('Location: bookings.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, name, phone, date, size, created_at FROM bookings ORDER BY id DESC");
$stmt->execute();
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin — Bookings</title>
  <style>
    :root{--bg:#f6f7f9;--card:#fff;--accent:#c94f2c;--muted:#6b6b6b;--radius:10px}
    *{box-sizing:border-box}
    body{margin:0;font-family:Inter,system-ui,Segoe UI,Roboto,Arial;background:var(--bg);color:#222;padding:18px}
    .card{background:var(--card);padding:14px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.04)}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{padding:10px;border-bottom:1px solid #f1f1f1;text-align:left}
    th{background:#fafafa}
    .small{background:#eee;color:#333;padding:6px 10px;border-radius:8px;border:0}
  </style>
</head>
<body>
  <a href="dashboard.php">← Back to Dashboard</a>
  <h2>Bookings</h2>
  <div class="card">
    <?php if (empty($bookings)): ?>
      <div class="muted">No bookings yet.</div>
    <?php else: ?>
      <table>
        <thead><tr><th>ID</th><th>When</th><th>Name</th><th>Phone</th><th>Date</th><th>Party</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($bookings as $b): ?>
            <tr>
              <td><?php echo (int)$b['id']; ?></td>
              <td><?php echo htmlspecialchars($b['created_at'] ?? '-', ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($b['name'] ?? '-', ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($b['phone'] ?? '-', ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($b['date'] ?? '-', ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($b['size'] ?? '-', ENT_QUOTES); ?></td>
              <td>
                <form method="post" onsubmit="return confirm('Delete this booking?');">
                  <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
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