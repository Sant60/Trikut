<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/app.php';

$adminName = 'Admin';
try {
    $stmt = $pdo->prepare('SELECT username FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['admin']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['username'])) {
        $adminName = $row['username'];
    }
} catch (Throwable $e) {
}

function safe_count(PDO $pdo, string $table): int
{
    try {
        $stmt = $pdo->query("SELECT COUNT(*) AS c FROM `$table`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

$counts = [
    'orders' => safe_count($pdo, 'orders'),
    'bookings' => safe_count($pdo, 'bookings'),
    'menu' => safe_count($pdo, 'menu'),
    'gallery' => safe_count($pdo, 'gallery'),
];

$recentOrders = [];
$recentBookings = [];
$recentMenu = [];
$recentGallery = [];

try {
    $stmt = $pdo->query('SELECT id, name, phone, total, status, created_at FROM orders ORDER BY id DESC LIMIT 6');
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

try {
    $stmt = $pdo->query('SELECT id, name, phone, date, size, created_at FROM bookings ORDER BY id DESC LIMIT 6');
    $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

try {
    $stmt = $pdo->query('SELECT id, name, price, img, active FROM menu ORDER BY id DESC LIMIT 6');
    $recentMenu = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

try {
    $stmt = $pdo->query('SELECT id, img, caption, created_at FROM gallery ORDER BY id DESC LIMIT 6');
    $recentGallery = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

$latestOrderId = isset($recentOrders[0]['id']) ? (int) $recentOrders[0]['id'] : 0;
$latestBookingId = isset($recentBookings[0]['id']) ? (int) $recentBookings[0]['id'] : 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Dashboard - Trikut Restaurant</title>
  <link rel="stylesheet" href="../CSS/style.css">
</head>
<body class="admin-body">
  <div class="admin-shell">
    <aside class="admin-sidebar" aria-label="Admin menu">
      <div class="admin-brand">Trikut - Admin</div>
      <div class="admin-greeting">Hello, <?php echo htmlspecialchars($adminName, ENT_QUOTES); ?></div>
      <nav class="admin-nav">
        <a class="is-active" href="dashboard.php">Dashboard</a>
        <a href="menu.php">Menu</a>
        <a href="gallery.php">Gallery</a>
        <a href="orders.php">Orders</a>
        <a href="bookings.php">Bookings</a>
        <a href="logout.php">Logout</a>
      </nav>
      <div class="admin-meta" style="margin-top:auto">Server Time: <?php echo date('Y-m-d H:i'); ?></div>
    </aside>

    <div class="admin-panel">
      <div class="admin-panel-header admin-header">
        <div>
          <h1 class="admin-heading">Dashboard</h1>
          <div class="admin-subtitle">Overview of current menu, gallery, orders, and reservations.</div>
        </div>
        <div class="admin-header-actions">
          <a class="admin-btn-primary" href="orders.php">View Orders</a>
          <a class="admin-btn-secondary" href="menu.php">Manage Menu</a>
          <a class="admin-btn-secondary" href="gallery.php">Manage Gallery</a>
          <button class="admin-btn-alert" id="enableNotificationsBtn" type="button">Enable Alerts</button>
        </div>
      </div>

      <div class="admin-notify-status" id="notifyStatus">Owner alerts are available in this browser after notification permission is granted.</div>

      <main class="admin-main">
        <section class="admin-stats">
          <div class="admin-card">
            <div class="admin-muted">Orders</div>
            <div class="admin-stat-number"><?php echo $counts['orders']; ?></div>
            <div class="admin-muted">Total orders received</div>
          </div>
          <div class="admin-card">
            <div class="admin-muted">Bookings</div>
            <div class="admin-stat-number"><?php echo $counts['bookings']; ?></div>
            <div class="admin-muted">Table reservations</div>
          </div>
          <div class="admin-card">
            <div class="admin-muted">Menu Items</div>
            <div class="admin-stat-number"><?php echo $counts['menu']; ?></div>
            <div class="admin-muted">Active dish entries</div>
          </div>
          <div class="admin-card">
            <div class="admin-muted">Gallery Photos</div>
            <div class="admin-stat-number"><?php echo $counts['gallery']; ?></div>
            <div class="admin-muted">Uploaded image count</div>
          </div>
        </section>

        <section class="admin-card">
          <h3 style="margin:0 0 12px">Recent Orders</h3>
          <?php if (empty($recentOrders)): ?>
            <div class="admin-empty">No recent orders found.</div>
          <?php else: ?>
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr><th>ID</th><th>Name</th><th>Phone</th><th>Total</th><th>Invoice</th><th>Status</th><th>Created</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($recentOrders as $order): ?>
                    <?php
                    $orderInvoiceRelative = 'assets/invoices/order_' . (int) $order['id'] . '.pdf';
                    $orderInvoiceFile = dirname(__DIR__) . '/assets/invoices/order_' . (int) $order['id'] . '.pdf';
                    ?>
                    <tr>
                      <td>#<?php echo (int) $order['id']; ?></td>
                      <td><?php echo htmlspecialchars($order['name'] ?? '-', ENT_QUOTES); ?></td>
                      <td><?php echo htmlspecialchars($order['phone'] ?? '-', ENT_QUOTES); ?></td>
                      <td>&#8377;<?php echo htmlspecialchars((string) ($order['total'] ?? '0.00'), ENT_QUOTES); ?></td>
                      <td>
                        <?php if (is_file($orderInvoiceFile)): ?>
                          <a class="admin-btn-secondary" href="<?php echo htmlspecialchars(app_url($orderInvoiceRelative), ENT_QUOTES); ?>" target="_blank" rel="noopener">Invoice</a>
                        <?php else: ?>
                          <span class="admin-muted">-</span>
                        <?php endif; ?>
                      </td>
                      <td><?php echo htmlspecialchars(ucfirst((string) ($order['status'] ?? 'new')), ENT_QUOTES); ?></td>
                      <td><?php echo htmlspecialchars($order['created_at'] ?? '-', ENT_QUOTES); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>

        <section class="admin-card">
          <h3 style="margin:0 0 12px">Recent Bookings</h3>
          <?php if (empty($recentBookings)): ?>
            <div class="admin-empty">No recent bookings found.</div>
          <?php else: ?>
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr><th>ID</th><th>Name</th><th>Phone</th><th>Reservation Time</th><th>Guests</th><th>Invoice</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($recentBookings as $booking): ?>
                    <?php
                    $bookingInvoiceRelative = 'assets/invoices/booking_' . (int) $booking['id'] . '.pdf';
                    $bookingInvoiceFile = dirname(__DIR__) . '/assets/invoices/booking_' . (int) $booking['id'] . '.pdf';
                    ?>
                    <tr>
                      <td>#<?php echo (int) $booking['id']; ?></td>
                      <td><?php echo htmlspecialchars($booking['name'] ?? '-', ENT_QUOTES); ?></td>
                      <td><?php echo htmlspecialchars($booking['phone'] ?? '-', ENT_QUOTES); ?></td>
                      <td><?php echo htmlspecialchars($booking['date'] ?? ($booking['created_at'] ?? '-'), ENT_QUOTES); ?></td>
                      <td><?php echo htmlspecialchars((string) ($booking['size'] ?? '-'), ENT_QUOTES); ?></td>
                      <td>
                        <?php if (is_file($bookingInvoiceFile)): ?>
                          <a class="admin-btn-secondary" href="<?php echo htmlspecialchars(app_url($bookingInvoiceRelative), ENT_QUOTES); ?>" target="_blank" rel="noopener">Invoice</a>
                        <?php else: ?>
                          <span class="admin-muted">-</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>

        <section class="admin-card">
          <h3 style="margin:0 0 12px">Recent Menu Items</h3>
          <?php if (empty($recentMenu)): ?>
            <div class="admin-empty">No menu items found.</div>
          <?php else: ?>
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr><th>ID</th><th>Image</th><th>Name</th><th>Price</th><th>Active</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($recentMenu as $item): ?>
                    <tr>
                      <td>#<?php echo (int) $item['id']; ?></td>
                      <td>
                        <?php if (!empty($item['img'])): ?>
                          <img class="admin-thumb" src="<?php echo htmlspecialchars(app_url($item['img']), ENT_QUOTES); ?>" alt="">
                        <?php else: ?>
                          <div class="admin-thumb-placeholder"></div>
                        <?php endif; ?>
                      </td>
                      <td><?php echo htmlspecialchars($item['name'] ?? '-', ENT_QUOTES); ?></td>
                      <td>&#8377;<?php echo htmlspecialchars((string) ($item['price'] ?? '0.00'), ENT_QUOTES); ?></td>
                      <td><?php echo !empty($item['active']) ? 'Yes' : 'No'; ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>

        <section class="admin-card">
          <h3 style="margin:0 0 12px">Recent Gallery Photos</h3>
          <?php if (empty($recentGallery)): ?>
            <div class="admin-empty">No gallery photos yet.</div>
          <?php else: ?>
            <div class="admin-gallery-grid">
              <?php foreach ($recentGallery as $image): ?>
                <?php $img = !empty($image['img']) ? app_url($image['img']) : ''; ?>
                <div>
                  <?php if ($img !== ''): ?>
                    <img src="<?php echo htmlspecialchars($img, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($image['caption'] ?? '', ENT_QUOTES); ?>">
                  <?php else: ?>
                    <div class="admin-photo-placeholder" style="height:88px;border-radius:10px"></div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </main>
    </div>
  </div>

  <script>
    (function () {
      let latestOrderId = <?php echo $latestOrderId; ?>;
      let latestBookingId = <?php echo $latestBookingId; ?>;
      const notifyBtn = document.getElementById("enableNotificationsBtn");
      const notifyStatus = document.getElementById("notifyStatus");

      function updateStatus(text) {
        if (notifyStatus) {
          notifyStatus.textContent = text;
        }
      }

      async function requestPermission() {
        if (!("Notification" in window)) {
          updateStatus("This browser does not support notifications.");
          return;
        }

        const permission = await Notification.requestPermission();
        if (permission === "granted") {
          updateStatus("Notifications enabled. New orders and reservations will alert the owner here.");
        } else {
          updateStatus("Notifications were not allowed. New orders will still be stored in the admin panel.");
        }
      }

      async function pollNotifications() {
        try {
          const response = await fetch("../api/admin_notifications.php", {
            cache: "no-store",
            headers: { "X-Requested-With": "XMLHttpRequest" }
          });
          const data = await response.json();
          if (!response.ok || !data.success) return;

          if (data.latest_order && Number(data.latest_order.id) > latestOrderId) {
            latestOrderId = Number(data.latest_order.id);
            if ("Notification" in window && Notification.permission === "granted") {
              new Notification("New Food Order", {
                body: `${data.latest_order.name} placed order #${data.latest_order.id}.`
              });
            }
            updateStatus(`New order received: #${data.latest_order.id}`);
          }

          if (data.latest_booking && Number(data.latest_booking.id) > latestBookingId) {
            latestBookingId = Number(data.latest_booking.id);
            if ("Notification" in window && Notification.permission === "granted") {
              new Notification("New Table Reservation", {
                body: `${data.latest_booking.name} reserved table #${data.latest_booking.id}.`
              });
            }
            updateStatus(`New table reservation received: #${data.latest_booking.id}`);
          }
        } catch (error) {
        }
      }

      if (notifyBtn) {
        notifyBtn.addEventListener("click", requestPermission);
      }

      if ("Notification" in window && Notification.permission === "granted") {
        updateStatus("Notifications enabled. New orders and reservations will alert the owner here.");
      }

      setInterval(pollNotifications, 15000);
    })();
  </script>
</body>
</html>
