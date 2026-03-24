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

    if ($id > 0 && $action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM bookings WHERE id = ? AND admin_id = ? LIMIT 1');
        $stmt->execute([$id, $currentAdminId]);
    }

    header('Location: bookings.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, phone, date, size, created_at FROM bookings WHERE admin_id = ? ORDER BY id DESC');
$stmt->execute([$currentAdminId]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Bookings</title>
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
        <h1 class="admin-title">Bookings</h1>
        <div class="admin-subtitle">All reserve-table requests are stored here for the restaurant team.</div>
      </div>
      <button class="theme-toggle admin-theme-toggle" type="button" data-theme-toggle data-theme-storage="admin-theme" aria-pressed="false" aria-label="Switch to dark mode" title="Toggle admin light and dark mode">
        <i class="fa-solid fa-toggle-off" aria-hidden="true"></i>
        <i class="fa-solid fa-toggle-on" aria-hidden="true"></i>
      </button>
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
                      <?php echo csrf_input(); ?>
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
  <script src="../Script/theme.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/theme.js') ?: time()); ?>"></script>
</body>
</html>
