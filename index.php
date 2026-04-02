<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/hero_media.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/site.php';

$frontend_menu = [];
$homepage_menu = [];
$frontend_gallery = [];
$frontend_hero = null;
$bookingStatus = $_GET['booking'] ?? '';
$bookingError = isset($_GET['booking_err']);
$bookingInvoice = trim((string) ($_GET['booking_invoice'] ?? ''));
$logoPath = __DIR__ . '/assets/logo.png';
$hasLogo = is_file($logoPath);
$styleVersion = @filemtime(__DIR__ . '/CSS/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/Script/script.js') ?: time();
$siteAdminId = site_admin_id($rootPdo);
$sitePdo = site_pdo($rootPdo);
$restaurantName = site_restaurant_name();
$menuPageUrl = app_url('menu.php');

try {
    $menuStmt = $sitePdo->prepare('SELECT id, name, description, price, img FROM menu WHERE active = 1 ORDER BY id ASC');
    $menuStmt->execute();
    $frontend_menu = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
    $homepage_menu = array_slice($frontend_menu, 0, 4);
} catch (Throwable $e) {
    $frontend_menu = [];
    $homepage_menu = [];
}

try {
    $frontend_hero = fetch_current_hero_media($sitePdo, $siteAdminId);
} catch (Throwable $e) {
    $frontend_hero = null;
}

try {
    $galleryStmt = $sitePdo->prepare('SELECT id, img, caption FROM gallery ORDER BY id DESC');
    $galleryStmt->execute();
    $frontend_gallery = $galleryStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $frontend_gallery = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($restaurantName, ENT_QUOTES); ?></title>
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
<body<?php echo $bookingInvoice !== '' ? ' data-booking-invoice="' . htmlspecialchars(app_url($bookingInvoice), ENT_QUOTES) . '"' : ''; ?> data-csrf-token="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
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
          <div class="eyebrow"><?php echo htmlspecialchars($restaurantName, ENT_QUOTES); ?></div>
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
        <nav id="primaryNav" class="main-nav" aria-label="Primary">
          <a href="<?php echo htmlspecialchars($menuPageUrl, ENT_QUOTES); ?>">Menu</a>
          <a href="#gallery">Gallery</a>
          <a href="#experience">Experience</a>
          <a class="cta" href="#book">Reserve</a>
          <a href="#location">Location</a>
        </nav>
      </div>
      <div class="nav-backdrop" aria-hidden="true"></div>
    </div>
  </header>

  <main class="container">
    <section class="hero-shell">
      <div class="hero-frame">
        <div class="hero-copy-card">
          <p class="eyebrow"><?php echo htmlspecialchars($restaurantName, ENT_QUOTES); ?></p>
          <h1>Order food or reserve a table in one simple flow.</h1>
          <p class="hero-copy">Browse the menu, preview the space, and place your order without leaving the page.</p>
          <div class="hero-actions">
            <a class="hero-btn primary" href="#menu">Explore Menu</a>
            <a class="hero-btn secondary" href="#book">Book a Table</a>
          </div>
          <div class="hero-stats">
            <div class="stat-chip">
              <strong><?php echo count($frontend_menu); ?></strong>
              <span>Menu items</span>
            </div>
            <div class="stat-chip">
              <strong><?php echo count($frontend_gallery); ?></strong>
              <span>Photos</span>
            </div>
          </div>
        </div>

        <div class="hero-visual">
          <?php if ($frontend_hero && !empty($frontend_hero['img'])): ?>
            <img class="hero-image" src="<?php echo htmlspecialchars(app_url($frontend_hero['img']), ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($frontend_hero['caption'] ?? ($restaurantName . ' hero image'), ENT_QUOTES); ?>">
          <?php elseif (!empty($frontend_gallery)): ?>
            <?php $heroImage = app_url($frontend_gallery[0]['img'] ?? ''); ?>
            <img class="hero-image" src="<?php echo htmlspecialchars($heroImage, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($frontend_gallery[0]['caption'] ?? 'Restaurant interior', ENT_QUOTES); ?>">
          <?php else: ?>
            <div class="hero-image hero-placeholder"><?php echo htmlspecialchars(substr($restaurantName, 0, 24), ENT_QUOTES); ?></div>
          <?php endif; ?>
          <div class="hero-overlay">
            <p>Warm lights, crafted plates, and a dining space designed for both families and cafe guests.</p>
          </div>
        </div>
      </div>
    </section>

    <?php if ($bookingStatus === 'success'): ?>
      <div class="notice success">
        Your table booking was saved successfully.
        <?php if ($bookingInvoice !== ''): ?>
          <a href="<?php echo htmlspecialchars(app_url($bookingInvoice), ENT_QUOTES); ?>" target="_blank" rel="noopener">Download your receipt</a>
        <?php endif; ?>
      </div>
    <?php elseif ($bookingError): ?>
      <div class="notice error">We could not save your booking. Please check your details and try again.</div>
    <?php endif; ?>

    <div class="layout">
      <section class="content">
        <section id="menu">
          <div class="section-heading">
            <div>
              <p class="eyebrow">Menu</p>
              <h2 class="section-title">Popular items</h2>
              <p class="section-copy">A quick preview of the menu. Open the full menu page to see everything.</p>
            </div>
            <a class="restaurant-strip-link" href="<?php echo htmlspecialchars($menuPageUrl, ENT_QUOTES); ?>">Full menu</a>
          </div>

          <div class="menu-list">
            <?php if (empty($homepage_menu)): ?>
              <div class="card muted">No menu items are available right now.</div>
            <?php else: ?>
              <?php foreach ($homepage_menu as $item): ?>
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
                    <div class="menu-meta">Chef recommendation</div>
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

          <?php if (!empty($frontend_menu)): ?>
            <div class="section-footer-action">
              <a class="hero-btn secondary section-link" href="<?php echo htmlspecialchars($menuPageUrl, ENT_QUOTES); ?>">See More Items</a>
            </div>
          <?php endif; ?>
        </section>

        <section id="gallery">
          <div class="section-heading">
            <div>
              <p class="eyebrow">Gallery</p>
              <h2 class="section-title">Inside the restaurant</h2>
            </div>
            <p class="section-copy">A few photos of the space.</p>
          </div>

          <?php if (empty($frontend_gallery)): ?>
            <div class="card muted">No gallery photos have been added yet.</div>
          <?php else: ?>
            <div class="gallery-carousel">
              <div class="gallery-track">
                <?php for ($loop = 0; $loop < 2; $loop++): ?>
                  <?php foreach ($frontend_gallery as $pic): ?>
                    <div class="gallery-item">
                      <img class="gallery-thumb" src="<?php echo htmlspecialchars(app_url($pic['img'] ?? ''), ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($pic['caption'] ?: 'Restaurant photo', ENT_QUOTES); ?>">
                      <?php if (!empty($pic['caption'])): ?>
                        <div class="caption"><?php echo htmlspecialchars($pic['caption'], ENT_QUOTES); ?></div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                <?php endfor; ?>
              </div>
            </div>
          <?php endif; ?>
        </section>

      </section>

      <aside class="sidebar">
        <div class="card" id="book">
          <h3>Reserve a Table</h3>
          <form id="bookingForm" method="post" action="includes/save_booking.php" novalidate data-validate-form>
            <?php echo csrf_input(); ?>
            <div class="field-group">
              <label for="bookName">Name</label>
              <input id="bookName" name="name" data-rule="name" maxlength="100" placeholder="Your name" autocomplete="name" required>
            </div>

            <div class="field-group">
              <label for="bookPhone">Phone</label>
              <input id="bookPhone" name="phone" type="tel" data-rule="phone" autocomplete="tel" inputmode="numeric" maxlength="13" placeholder="+91 9876543210" required>
            </div>

            <div class="field-group">
              <label for="bookDate">Date and Time</label>
              <input id="bookDate" type="datetime-local" name="date" step="60" required>
            </div>

            <div class="field-group">
              <label for="bookSize">Guests</label>
              <input id="bookSize" type="number" name="size" value="2" min="1" max="20" required>
            </div>

            <button type="submit">Reserve Table</button>
          </form>
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
            <input id="custName" type="text" data-rule="name" maxlength="100" autocomplete="name" placeholder="Your name" required>
          </div>

          <div class="field-group">
            <label for="custPhone">Mobile Number</label>
            <input id="custPhone" type="tel" data-rule="phone" autocomplete="tel" inputmode="numeric" maxlength="13" placeholder="+91 9876543210" required>
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

    <section id="location" class="location-section">
      <div class="section-heading">
        <div>
          <p class="eyebrow">Location</p>
          <h2 class="section-title">Visit <?php echo htmlspecialchars($restaurantName, ENT_QUOTES); ?></h2>
        </div>
        <p class="section-copy">Use the map below for directions.</p>
      </div>

      <div class="map-card">
        <iframe
          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3557.2554614245146!2d80.89594594270733!3d26.92711468726206!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39995500612c5155%3A0x808b5050bf281b5e!2sTrikut%20restaurant%20and%20cafe!5e0!3m2!1sen!2sin!4v1774257026657!5m2!1sen!2sin"
          width="600"
          height="450"
          style="border:0;"
          allowfullscreen=""
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          title="<?php echo htmlspecialchars($restaurantName, ENT_QUOTES); ?> Google Map"
        ></iframe>
      </div>
    </section>
  </main>

  <footer class="site-footer">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($restaurantName, ENT_QUOTES); ?></footer>

  <div id="imgModal" class="img-modal" aria-hidden="true">
    <button class="modal-close" type="button" aria-label="Close preview">&times;</button>
    <img id="modalImg" alt="">
  </div>

  <script src="Script/theme.js?v=<?php echo (int) (@filemtime(__DIR__ . '/Script/theme.js') ?: time()); ?>"></script>
  <script src="Script/form-validation.js?v=<?php echo (int) (@filemtime(__DIR__ . '/Script/form-validation.js') ?: time()); ?>"></script>
  <script src="Script/script.js?v=<?php echo (int) $scriptVersion; ?>"></script>
</body>
</html>
