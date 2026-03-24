<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/app.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($id > 0 && $action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM bookings WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
    }

    header('Location: bookings.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, phone, date, size, created_at FROM bookings ORDER BY id DESC');
$stmt->execute();
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Bookings</title>
  <link rel="stylesheet" href="../CSS/style.css">
</head>
<body class="admin-body">
  <div class="admin-page">
    <div class="admin-page-head">
      <div>
        <a class="admin-back-link" href="dashboard.php">&larr; Back to Dashboard</a>
        <h1 class="admin-title">Bookings</h1>
        <div class="admin-subtitle">All reserve-table requests are stored here for the restaurant team.</div>
      </div>
    </div>

    <div class="admin-card">
      <?php if (empty($bookings)): ?>
        <div class="admin-empty">No bookings have been received yet.</div>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Created</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Reservation Time</th>
                <th>Guests</th>
                <th>Invoice</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bookings as $booking): ?>
                <?php
                $invoiceRelative = 'assets/invoices/booking_' . (int) $booking['id'] . '.pdf';
                $invoiceFile = dirname(__DIR__) . '/assets/invoices/booking_' . (int) $booking['id'] . '.pdf';
                ?>
                <tr>
                  <td>#<?php echo (int) $booking['id']; ?></td>
                  <td><?php echo htmlspecialchars($booking['created_at'] ?? '-', ENT_QUOTES); ?></td>
                  <td><?php echo htmlspecialchars($booking['name'] ?? '-', ENT_QUOTES); ?></td>
                  <td><?php echo htmlspecialchars($booking['phone'] ?? '-', ENT_QUOTES); ?></td>
                  <td><?php echo htmlspecialchars($booking['date'] ?? '-', ENT_QUOTES); ?></td>
                  <td><?php echo htmlspecialchars((string) ($booking['size'] ?? '-'), ENT_QUOTES); ?></td>
                  <td>
                    <?php if (is_file($invoiceFile)): ?>
                      <a class="admin-btn-secondary" href="<?php echo htmlspecialchars(app_url($invoiceRelative), ENT_QUOTES); ?>" target="_blank" rel="noopener">View Invoice</a>
                    <?php else: ?>
                      <span class="admin-muted">Not generated</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <form method="post" onsubmit="return confirm('Delete this booking?');">
                      <input type="hidden" name="id" value="<?php echo (int) $booking['id']; ?>">
                      <input type="hidden" name="action" value="delete">
                      <button class="admin-btn-danger" type="submit">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
