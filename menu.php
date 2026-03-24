<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/tenant.php';

$frontend_menu = [];
$logoPath = __DIR__ . '/assets/logo.png';
$hasLogo = is_file($logoPath);
$styleVersion = @filemtime(__DIR__ . '/CSS/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/Script/script.js') ?: time();
$publicAdminId = resolve_public_admin_id($pdo);

try {
    $menuStmt = $pdo->prepare('SELECT id, name, description, price, img FROM menu WHERE admin_id = ? AND active = 1 ORDER BY id ASC');
    $menuStmt->execute([$publicAdminId]);
    $frontend_menu = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $frontend_menu = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Menu - Trikut Restaurant &amp; Cafe</title>
  <script>
    (function () {
      try {
        var savedTheme = localStorage.getItem("trikut-theme");
        if (savedTheme === "dark" || savedTheme === "light") {
          document.documentElement.setAttribute("data-theme", savedTheme);
        }
      } catch (e) {}
    })();
  </script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
  <link rel="stylesheet" href="CSS/style.css?v=<?php echo (int) $styleVersion; ?>">
</head>
<body data-csrf-token="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
  <header class="site-header">
    <div class="container topbar">
      <div class="brand">
        <?php if ($hasLogo): ?>
          <div class="logo">
            <img src="<?php echo htmlspecialchars(app_url('assets/logo.png'), ENT_QUOTES); ?>" alt="Trikut logo">
          </div>
        <?php else: ?>
          <div class="logo logo-fallback">TR</div>
        <?php endif; ?>
        <div>
          <div class="eyebrow">Trikut Restaurant &amp; Cafe</div>
        </div>
      </div>

      <div class="nav-shell">
        <button
          class="theme-toggle"
          id="themeToggle"
          type="button"
          data-theme-toggle
          data-theme-storage="trikut-theme"
          aria-pressed="false"
          aria-label="Switch to dark mode"
          title="Toggle light and dark mode"
        >
          <i class="fa-solid fa-toggle-off" aria-hidden="true"></i>
          <i class="fa-solid fa-toggle-on" aria-hidden="true"></i>
        </button>
        <button
          class="nav-toggle"
          type="button"
          aria-expanded="false"
          aria-controls="primaryNav"
          aria-label="Toggle navigation"
        >
          <i class="fa-solid fa-bars" aria-hidden="true"></i>
          <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <button class="nav-close" type="button" aria-label="Close navigation">&times;</button>
        <nav id="primaryNav" class="main-nav" aria-label="Primary">
          <a href="<?php echo htmlspecialchars(app_url('index.php'), ENT_QUOTES); ?>">Home</a>
          <a href="<?php echo htmlspecialchars(app_url('index.php#gallery'), ENT_QUOTES); ?>">Gallery</a>
          <a href="<?php echo htmlspecialchars(app_url('index.php#book'), ENT_QUOTES); ?>">Reserve</a>
          <a class="cta" href="#menuOrderArea">Order Here</a>
        </nav>
      </div>
      <div class="nav-backdrop" aria-hidden="true"></div>
    </div>
  </header>

  <main class="container">
    <section class="menu-page-hero card">
      <div class="menu-page-copy">
        <p class="eyebrow">Full Menu</p>
        <h1 class="section-title">Every live dish in one customer-friendly ordering page.</h1>
        <p class="section-copy">Scroll through the full restaurant menu, add items directly to your cart, and place the order from this page without going back to the homepage.</p>
        <div class="hero-actions">
          <a class="hero-btn primary" href="#menuGrid">Browse Dishes</a>
          <a class="hero-btn secondary" href="<?php echo htmlspecialchars(app_url('index.php'), ENT_QUOTES); ?>">Back to Home</a>
        </div>
      </div>
      <div class="menu-page-meta">
        <div class="stat-chip">
          <strong><?php echo count($frontend_menu); ?></strong>
          <span>Available dishes</span>
        </div>
        <div class="stat-chip">
          <strong>Live</strong>
          <span>Cart ordering enabled</span>
        </div>
      </div>
    </section>

    <div class="layout menu-page-layout">
      <section class="content">
        <section id="menuGrid">
          <div class="section-heading">
            <div>
              <p class="eyebrow">All Dishes</p>
              <h2 class="section-title">Choose what you want to order</h2>
            </div>
            <p class="section-copy">All active menu items are listed here. Each card uses the same add-to-cart flow as the homepage.</p>
          </div>

          <div class="menu-list">
            <?php if (empty($frontend_menu)): ?>
              <div class="card muted">No menu items are available right now.</div>
            <?php else: ?>
              <?php foreach ($frontend_menu as $item): ?>
                <?php $img = app_url($item['img'] ?? ''); ?>
                <article
                  class="menu-item"
                  data-id="<?php echo (int) $item['id']; ?>"
                  data-name="<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>"
                  data-price="<?php echo htmlspecialchars((string) $item['price'], ENT_QUOTES); ?>"
                >
                  <?php if (($item['img'] ?? '') !== ''): ?>
                    <div class="menu-thumb-wrap">
                      <img class="menu-thumb" src="<?php echo htmlspecialchars($img, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>">
                    </div>
                  <?php endif; ?>

                  <div class="menu-content">
                    <div class="menu-meta">Freshly prepared</div>
                    <h3 class="menu-name"><?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?></h3>
                    <p class="desc"><?php echo htmlspecialchars($item['description'] ?? '', ENT_QUOTES); ?></p>
                    <div class="menu-bottom">
                      <div class="price">&#8377;<?php echo number_format((float) $item['price'], 2); ?></div>
                      <button class="add-to-cart" type="button">Add to Cart</button>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>
      </section>

      <aside class="sidebar" id="menuOrderArea">
        <div class="card spotlight-card">
          <p class="eyebrow">Quick Order</p>
          <h3>Everything you add here stays in the live cart.</h3>
          <p>Choose dishes from the list, confirm your details, and place your order directly to the restaurant admin panel.</p>
        </div>

        <div class="card" id="cartBox">
          <h3>Your Cart</h3>
          <div id="cartItems"><p class="muted">No items added.</p></div>
          <div class="cart-total">
            <span>Total</span>
            <strong id="cartTotal">&#8377;0.00</strong>
          </div>

          <div class="field-group">
            <label for="custName">Customer Name</label>
            <input id="custName" data-rule="name" placeholder="Your name" required>
          </div>

          <div class="field-group">
            <label for="custPhone">Mobile Number</label>
            <input id="custPhone" data-rule="phone" inputmode="numeric" maxlength="10" placeholder="10-digit mobile number" required>
          </div>

          <div class="field-group">
            <label for="deliveryType">Delivery Type</label>
            <select id="deliveryType" required>
              <option value="">Select delivery type</option>
              <option value="dine_in">Dine In</option>
              <option value="home_delivery">Home Delivery</option>
            </select>
          </div>

          <button id="orderNowBtn" type="button">Order Now</button>
        </div>
      </aside>
    </div>
  </main>

  <footer class="site-footer">&copy; <?php echo date('Y'); ?> Trikut Restaurant &amp; Cafe</footer>

  <div id="imgModal" class="img-modal" aria-hidden="true">
    <button class="modal-close" type="button" aria-label="Close preview">&times;</button>
    <img id="modalImg" alt="">
  </div>

  <script src="Script/theme.js?v=<?php echo (int) $scriptVersion; ?>"></script>
  <script src="Script/script.js?v=<?php echo (int) $scriptVersion; ?>"></script>
</body>
</html>
