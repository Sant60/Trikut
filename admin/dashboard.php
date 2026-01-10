<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";

$adminName = "Admin";
try {
    $stmt = $pdo->prepare("SELECT username FROM admins WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['admin']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['username'])) $adminName = $row['username'];
} catch (Throwable $e) {
    // ignore, keep default admin name
}

function safeCount($pdo, $table) {
    try {
        $q = $pdo->prepare("SELECT COUNT(*) as c FROM `$table`");
        $q->execute();
        $r = $q->fetch(PDO::FETCH_ASSOC);
        return (int)($r['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

$counts = [
    'orders' => safeCount($pdo, 'orders'),
    'bookings' => safeCount($pdo, 'bookings'),
    'menu' => safeCount($pdo, 'menu'),
    'admins' => safeCount($pdo, 'admins'),
];

// fetch recent orders
$recentOrders = [];
try {
    $q = $pdo->prepare("SELECT id, name, phone, total, created_at FROM orders ORDER BY id DESC LIMIT 6");
    $q->execute();
    $recentOrders = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $recentOrders = [];
}

// fetch recent bookings
$recentBookings = [];
try {
    $q = $pdo->prepare("SELECT id, name, phone, date, size, created_at FROM bookings ORDER BY id DESC LIMIT 6");
    $q->execute();
    $recentBookings = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $recentBookings = [];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin — Dashboard — Trikut Restaurant</title>
  <style>
    :root{--bg:#f6f7f9;--card:#fff;--accent:#c94f2c;--muted:#6b6b6b;--radius:10px}
    *{box-sizing:border-box}
    body{margin:0;font-family:Inter,system-ui,Segoe UI,Roboto,Arial;background:var(--bg);color:#222;min-height:100vh}
    .app{display:flex;min-height:100vh}
    aside{width:220px;background:#fff;border-right:1px solid #eee;padding:18px;display:flex;flex-direction:column;gap:12px}
    .brand{font-weight:700;color:var(--accent);font-size:18px;margin-bottom:6px}
    nav a{display:block;padding:10px;border-radius:8px;color:#333;text-decoration:none;font-weight:600}
    nav a:hover{background:#faf0ee}
    header{display:flex;justify-content:space-between;align-items:center;padding:18px;border-bottom:1px solid #eee;background:transparent}
    main{flex:1;padding:18px}
    .topbar-right{display:flex;gap:12px;align-items:center}
    .card{background:var(--card);padding:14px;border-radius:var(--radius);box-shadow:0 6px 18px rgba(0,0,0,0.04);margin-bottom:14px}
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px}
    .k{font-size:20px;font-weight:700}
    table{width:100%;border-collapse:collapse;font-size:14px}
    th,td{padding:8px;border-bottom:1px solid #f1f1f1;text-align:left}
    th{background:#fafafa;font-weight:700}
    .muted{color:var(--muted);font-size:13px}
    .btn{background:var(--accent);color:#fff;padding:8px 10px;border-radius:8px;border:0;cursor:pointer}
    .small-btn{background:#eee;color:#333;padding:6px 8px;border-radius:8px;border:0;cursor:pointer;font-size:13px}
    .meta{font-size:13px;color:var(--muted)}
    @media(max-width:900px){
      aside{display:none}
      .grid{grid-template-columns:repeat(2,1fr)}
    }
    @media(max-width:600px){
      .grid{grid-template-columns:1fr}
      header{flex-direction:column;align-items:flex-start;gap:8px}
    }
  </style>
</head>
<body>
  <div class="app">
    <aside role="navigation" aria-label="Admin menu">
      <div class="brand">Trikut — Admin</div>
      <div class="muted">Hello, <?php echo htmlspecialchars($adminName, ENT_QUOTES); ?></div>
      <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="menu.php">Menu</a>
        <a href="orders.php">Orders</a>
        <a href="bookings.php">Bookings</a>
        <a href="logout.php">Logout</a>
      </nav>
      <div style="margin-top:auto" class="meta">Server Time: <?php echo date('Y-m-d H:i'); ?></div>
    </aside>

    <div style="flex:1">
      <header>
        <div>
          <strong>Dashboard</strong>
          <div class="muted">Overview of recent activity</div>
        </div>
        <div class="topbar-right">
          <a class="btn" href="orders.php">View All Orders</a>
        </div>
      </header>

      <main>
        <section class="grid" aria-hidden="false">
          <div class="card" role="region" aria-label="Orders">
            <div class="muted">Orders</div>
            <div class="k"><?php echo $counts['orders']; ?></div>
            <div class="meta">Total orders received</div>
          </div>

          <div class="card" role="region" aria-label="Bookings">
            <div class="muted">Bookings</div>
            <div class="k"><?php echo $counts['bookings']; ?></div>
            <div class="meta">Table reservations</div>
          </div>

          <div class="card" role="region" aria-label="Menu items">
            <div class="muted">Menu Items</div>
            <div class="k"><?php echo $counts['menu']; ?></div>
            <div class="meta">Active menu entries</div>
          </div>
        </section>

        <section class="card" aria-labelledby="recentOrdersTitle">
          <h3 id="recentOrdersTitle" style="margin:0 0 8px">Recent Orders</h3>
          <?php if (count($recentOrders) === 0): ?>
            <div class="muted">No recent orders found.</div>
          <?php else: ?>
            <table>
              <thead>
                <tr><th>ID</th><th>Name</th><th>Phone</th><th>Total</th><th>When</th></tr>
              </thead>
              <tbody>
                <?php foreach ($recentOrders as $o): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($o['id'] ?? '-', ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($o['name'] ?? '-', ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($o['phone'] ?? '-', ENT_QUOTES); ?></td>
                    <td><?php echo isset($o['total']) ? '₹'.htmlspecialchars($o['total'], ENT_QUOTES) : '-'; ?></td>
                    <td><?php echo htmlspecialchars($o['created_at'] ?? '-', ENT_QUOTES); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </section>

        <section class="card" aria-labelledby="recentBookingsTitle">
          <h3 id="recentBookingsTitle" style="margin:0 0 8px">Recent Bookings</h3>
          <?php if (count($recentBookings) === 0): ?>
            <div class="muted">No recent bookings found.</div>
          <?php else: ?>
            <table>
              <thead>
                <tr><th>ID</th><th>Name</th><th>Phone</th><th>When</th><th>Party</th></tr>
              </thead>
              <tbody>
                <?php foreach ($recentBookings as $b): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($b['id'] ?? '-', ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($b['name'] ?? '-', ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($b['phone'] ?? '-', ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($b['date'] ?? ($b['created_at'] ?? '-'), ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($b['size'] ?? '-', ENT_QUOTES); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </section>

      </main>
    </div>
  </div>
</body>
</html>