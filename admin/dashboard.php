<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/hero_media.php';
require_once __DIR__ . '/../includes/admin_profile.php';
require_once __DIR__ . '/../includes/tenant.php';

ensure_admin_profile_schema($pdo);
ensure_multi_admin_schema($pdo);
$currentAdminId = (int) $_SESSION['admin'];
$isOwnerAdmin = is_owner_admin($pdo, $currentAdminId);
$adminProfile = fetch_admin_profile($pdo, (int) $_SESSION['admin']) ?? [];
$adminName = $adminProfile['display_name'] ?? ($adminProfile['username'] ?? 'Admin');
$adminMobile = $adminProfile['mobile'] ?? '';
$adminPhotoUrl = !empty($adminProfile['photo']) ? app_url($adminProfile['photo']) : '';

function safe_count(PDO $pdo, string $table, int $adminId): int
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM `$table` WHERE admin_id = ?");
        $stmt->execute([$adminId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

$counts = [
    'orders' => safe_count($pdo, 'orders', $currentAdminId),
    'bookings' => safe_count($pdo, 'bookings', $currentAdminId),
    'menu' => safe_count($pdo, 'menu', $currentAdminId),
    'gallery' => safe_count($pdo, 'gallery', $currentAdminId),
    'hero' => 0,
];

$recentOrders = [];
$recentBookings = [];
$recentMenu = [];
$recentGallery = [];
$currentHero = null;

try {
    $stmt = $pdo->prepare('SELECT id, name, phone, total, status, created_at FROM orders WHERE admin_id = ? ORDER BY id DESC LIMIT 6');
    $stmt->execute([$currentAdminId]);
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

try {
    $stmt = $pdo->prepare('SELECT id, name, phone, date, size, created_at FROM bookings WHERE admin_id = ? ORDER BY id DESC LIMIT 6');
    $stmt->execute([$currentAdminId]);
    $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

try {
    $stmt = $pdo->prepare('SELECT id, name, price, img, active FROM menu WHERE admin_id = ? ORDER BY id DESC LIMIT 6');
    $stmt->execute([$currentAdminId]);
    $recentMenu = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

try {
    $stmt = $pdo->prepare('SELECT id, img, caption, created_at FROM gallery WHERE admin_id = ? ORDER BY id DESC LIMIT 6');
    $stmt->execute([$currentAdminId]);
    $recentGallery = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

try {
    $currentHero = fetch_current_hero_media($pdo, $currentAdminId);
    $counts['hero'] = $currentHero ? 1 : 0;
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
  <div class="admin-shell">
    <aside class="admin-sidebar" aria-label="Admin menu">
      <div class="admin-brand">Trikut - Admin</div>
      <button class="theme-toggle admin-theme-toggle" type="button" data-theme-toggle data-theme-storage="admin-theme" aria-pressed="false" aria-label="Switch to dark mode" title="Toggle admin light and dark mode">
        <i class="fa-solid fa-toggle-off" aria-hidden="true"></i>
        <i class="fa-solid fa-toggle-on" aria-hidden="true"></i>
      </button>
      <a class="admin-profile-card" href="profile.php">
        <?php if ($adminPhotoUrl !== ''): ?>
          <img class="admin-profile-photo" src="<?php echo htmlspecialchars($adminPhotoUrl, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($adminName, ENT_QUOTES); ?>">
        <?php else: ?>
          <div class="admin-profile-fallback"><?php echo htmlspecialchars(strtoupper(substr((string) $adminName, 0, 1)), ENT_QUOTES); ?></div>
        <?php endif; ?>
        <div class="admin-profile-copy">
          <strong><?php echo htmlspecialchars($adminName, ENT_QUOTES); ?></strong>
          <span class="admin-muted"><?php echo htmlspecialchars($adminMobile ?: 'No mobile set', ENT_QUOTES); ?></span>
        </div>
      </a>
      <nav class="admin-nav">
        <a class="is-active" href="dashboard.php">Dashboard</a>
        <a href="menu.php">Menu</a>
        <a href="hero.php">Hero Image</a>
        <a href="gallery.php">Gallery</a>
        <a href="orders.php">Orders</a>
        <a href="bookings.php">Bookings</a>
        <?php if ($isOwnerAdmin): ?>
          <a href="register.php">Add Admin</a>
        <?php endif; ?>
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
          <a class="admin-btn-secondary" href="hero.php">Manage Hero</a>
          <a class="admin-btn-secondary" href="gallery.php">Manage Gallery</a>
          <a class="admin-btn-secondary" href="profile.php">Manage Profile</a>
          <?php if ($isOwnerAdmin): ?>
            <a class="admin-btn-secondary" href="register.php">Add Admin</a>
          <?php endif; ?>
          <button class="admin-btn-alert" id="enableNotificationsBtn" type="button" aria-pressed="false">Alerts Off</button>
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
            <div class="admin-muted">Hero Image</div>
            <div class="admin-stat-number"><?php echo $counts['hero']; ?></div>
            <div class="admin-muted">Homepage banner image</div>
          </div>
          <div class="admin-card">
            <div class="admin-muted">Gallery Photos</div>
            <div class="admin-stat-number"><?php echo $counts['gallery']; ?></div>
            <div class="admin-muted">Uploaded image count</div>
          </div>
        </section>

        <section class="admin-card">
          <h3 style="margin:0 0 12px">Current Hero Image</h3>
          <?php if (!$currentHero): ?>
            <div class="admin-empty">No dedicated hero image set.</div>
          <?php else: ?>
            <div class="admin-preview">
              <img src="<?php echo htmlspecialchars(app_url($currentHero['img']), ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($currentHero['caption'] ?: 'Hero image', ENT_QUOTES); ?>">
            </div>
            <div style="margin-top:10px"><?php echo htmlspecialchars($currentHero['caption'] ?? '', ENT_QUOTES); ?></div>
            <div class="admin-form-actions" style="margin-top:12px">
              <a class="admin-btn-secondary" href="hero.php">Update Hero Image</a>
            </div>
          <?php endif; ?>
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

  <script src="../Script/theme.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/theme.js') ?: time()); ?>"></script>
  <script>
    (function () {
      let latestOrderId = <?php echo $latestOrderId; ?>;
      let latestBookingId = <?php echo $latestBookingId; ?>;
      const notifyBtn = document.getElementById("enableNotificationsBtn");
      const notifyStatus = document.getElementById("notifyStatus");
      const alertStorageKey = "admin-alerts-enabled";
      let alertsEnabled = false;

      function updateStatus(text) {
        if (notifyStatus) {
          notifyStatus.textContent = text;
        }
      }

      function syncAlertUi() {
        if (!notifyBtn) return;
        notifyBtn.textContent = alertsEnabled ? "Alerts On" : "Alerts Off";
        notifyBtn.setAttribute("aria-pressed", String(alertsEnabled));
      }

      function setAlertsEnabled(nextState) {
        alertsEnabled = Boolean(nextState);
        try {
          localStorage.setItem(alertStorageKey, alertsEnabled ? "true" : "false");
        } catch (error) {
        }
        syncAlertUi();
      }

      async function toggleAlerts() {
        if (!("Notification" in window)) {
          setAlertsEnabled(false);
          updateStatus("This browser does not support notifications.");
          return;
        }

        if (alertsEnabled) {
          setAlertsEnabled(false);
          updateStatus("Alerts turned off for this browser. Orders and bookings will still be stored in the admin panel.");
          return;
        }

        let permission = Notification.permission;
        if (permission !== "granted") {
          permission = await Notification.requestPermission();
        }

        if (permission === "granted") {
          setAlertsEnabled(true);
          updateStatus("Alerts turned on. New orders and reservations will notify you here.");
          return;
        }

        setAlertsEnabled(false);
        updateStatus("Browser notification permission was not allowed. Alerts remain off.");
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
            if (alertsEnabled && "Notification" in window && Notification.permission === "granted") {
              new Notification("New Food Order", {
                body: `${data.latest_order.name} placed order #${data.latest_order.id}.`
              });
            }
            updateStatus(`New order received: #${data.latest_order.id}`);
          }

          if (data.latest_booking && Number(data.latest_booking.id) > latestBookingId) {
            latestBookingId = Number(data.latest_booking.id);
            if (alertsEnabled && "Notification" in window && Notification.permission === "granted") {
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
        notifyBtn.addEventListener("click", toggleAlerts);
      }

      try {
        alertsEnabled = localStorage.getItem(alertStorageKey) === "true";
      } catch (error) {
        alertsEnabled = false;
      }

      if (!("Notification" in window)) {
        alertsEnabled = false;
        updateStatus("This browser does not support notifications.");
      } else if (alertsEnabled && Notification.permission === "granted") {
        updateStatus("Alerts are on. New orders and reservations will notify you here.");
      } else if (alertsEnabled && Notification.permission !== "granted") {
        alertsEnabled = false;
        updateStatus("Alerts are off until browser notification permission is granted again.");
      } else {
        updateStatus("Alerts are off. Turn them on when you want browser notifications.");
      }

      syncAlertUi();
      setInterval(pollNotifications, 15000);
    })();
  </script>
</body>
</html>
