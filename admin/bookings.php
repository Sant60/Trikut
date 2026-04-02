<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/invoice.php';
require_once __DIR__ . '/../includes/site.php';

$siteAdminId = site_admin_id($rootPdo);
$currentAdminId = (int) $_SESSION['admin'];
$canManageBookings = admin_can($pdo, $currentAdminId, 'manage_bookings');
$sitePdo = site_pdo($rootPdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManageBookings) {
        http_response_code(403);
        exit('You do not have permission to change booking data.');
    }

    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if (!verify_csrf_request()) {
        http_response_code(419);
        exit('Invalid request token.');
    }

    if ($id > 0 && $action === 'delete') {
        $stmt = $sitePdo->prepare('DELETE FROM bookings WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
    }

    header('Location: bookings.php');
    exit;
}

$stmt = $sitePdo->query('SELECT id, name, phone, date, size, created_at FROM bookings ORDER BY id DESC');
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
        <div class="admin-subtitle"><?php echo $canManageBookings ? 'All reserve-table requests are stored here for the restaurant team.' : 'Read-only booking view for employee access.'; ?></div>
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
                $invoiceUrl = app_url('download_invoice.php?type=booking&id=' . (int) $booking['id'] . '&admin_id=' . $siteAdminId);
                $invoiceFile = existing_invoice_path('booking', (int) $booking['id'], $siteAdminId);
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
                      <a class="admin-btn-secondary" href="<?php echo htmlspecialchars($invoiceUrl, ENT_QUOTES); ?>" target="_blank" rel="noopener">View Invoice</a>
                    <?php else: ?>
                      <span class="admin-muted">Not generated</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($canManageBookings): ?>
                      <form method="post" onsubmit="return confirm('Delete this booking?');">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $booking['id']; ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="admin-btn-danger" type="submit">Delete</button>
                      </form>
                    <?php else: ?>
                      <span class="admin-muted">View only</span>
                    <?php endif; ?>
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
