<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../includes/restaurants.php';

ensure_customer_schema($pdo);
require_customer_login();

$customer = current_customer($pdo);
if (!$customer) {
    header('Location: ' . app_url('customer/login.php'));
    exit;
}

$orderHistory = fetch_customer_order_history($pdo, (int) $customer['id']);
$bookingHistory = fetch_customer_booking_history($pdo, (int) $customer['id']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile - Trikut</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
  <link rel="stylesheet" href="../CSS/style.css?v=<?php echo (int) (@filemtime(__DIR__ . '/../CSS/style.css') ?: time()); ?>">
</head>
<body>
  <header class="site-header">
    <div class="container topbar">
      <div class="brand">
        <div class="logo logo-fallback">TR</div>
        <div>
          <div class="eyebrow">Customer Profile</div>
        </div>
      </div>
      <div class="nav-shell">
        <nav class="main-nav is-open marketplace-nav" aria-label="Customer">
          <a href="<?php echo htmlspecialchars(app_url('restaurants.php'), ENT_QUOTES); ?>">Restaurants</a>
          <a href="<?php echo htmlspecialchars(app_url('index.php'), ENT_QUOTES); ?>">Home</a>
          <a class="cta" href="<?php echo htmlspecialchars(app_url('customer/logout.php'), ENT_QUOTES); ?>">Logout</a>
        </nav>
      </div>
    </div>
  </header>

  <main class="container">
    <section class="marketplace-head">
      <p class="eyebrow">Welcome back</p>
      <h1 class="section-title"><?php echo htmlspecialchars($customer['name'] ?? 'Customer', ENT_QUOTES); ?></h1>
      <p class="section-copy">Your restaurants, orders, and table bookings stay in one place while guest users still see the normal homepage.</p>
    </section>

    <section class="experience-grid customer-summary-grid">
      <article class="card">
        <h2>Email</h2>
        <p><?php echo htmlspecialchars($customer['email'] ?? '-', ENT_QUOTES); ?></p>
      </article>
      <article class="card">
        <h2>Mobile</h2>
        <p><?php echo htmlspecialchars($customer['mobile'] ?? '-', ENT_QUOTES); ?></p>
      </article>
      <article class="card">
        <h2>Total Orders</h2>
        <p><?php echo count($orderHistory); ?></p>
      </article>
      <article class="card">
        <h2>Total Bookings</h2>
        <p><?php echo count($bookingHistory); ?></p>
      </article>
    </section>

    <section class="customer-history-section">
      <div class="section-heading">
        <div>
          <p class="eyebrow">Orders</p>
          <h2 class="section-title">Your order history</h2>
        </div>
      </div>
      <div class="customer-history-list">
        <?php if (empty($orderHistory)): ?>
          <article class="card"><p>No orders yet.</p></article>
        <?php else: ?>
          <?php foreach ($orderHistory as $order): ?>
            <article class="card customer-history-card">
              <div>
                <strong><?php echo htmlspecialchars($order['restaurant_name'] ?? 'Restaurant', ENT_QUOTES); ?></strong>
                <div class="customer-history-meta">Order #<?php echo (int) ($order['tenant_order_id'] ?? 0); ?> • <?php echo htmlspecialchars($order['created_at'] ?? '-', ENT_QUOTES); ?></div>
              </div>
              <div class="customer-history-side">
                <span>&#8377;<?php echo htmlspecialchars((string) ($order['total'] ?? '0.00'), ENT_QUOTES); ?></span>
                <a href="<?php echo htmlspecialchars(app_url('download_invoice.php?type=order&id=' . (int) ($order['tenant_order_id'] ?? 0) . '&admin_id=' . (int) ($order['admin_id'] ?? 0)), ENT_QUOTES); ?>">Invoice</a>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="customer-history-section">
      <div class="section-heading">
        <div>
          <p class="eyebrow">Bookings</p>
          <h2 class="section-title">Your table bookings</h2>
        </div>
      </div>
      <div class="customer-history-list">
        <?php if (empty($bookingHistory)): ?>
          <article class="card"><p>No bookings yet.</p></article>
        <?php else: ?>
          <?php foreach ($bookingHistory as $booking): ?>
            <article class="card customer-history-card">
              <div>
                <strong><?php echo htmlspecialchars($booking['restaurant_name'] ?? 'Restaurant', ENT_QUOTES); ?></strong>
                <div class="customer-history-meta"><?php echo htmlspecialchars($booking['booking_date'] ?? '-', ENT_QUOTES); ?> • <?php echo (int) ($booking['guest_count'] ?? 0); ?> guests</div>
              </div>
              <div class="customer-history-side">
                <a href="<?php echo htmlspecialchars(app_url('download_invoice.php?type=booking&id=' . (int) ($booking['tenant_booking_id'] ?? 0) . '&admin_id=' . (int) ($booking['admin_id'] ?? 0)), ENT_QUOTES); ?>">Invoice</a>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  </main>
</body>
</html>
