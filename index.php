<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/app.php';

$frontend_menu = [];
$frontend_gallery = [];
$bookingStatus = $_GET['booking'] ?? '';
$bookingError = isset($_GET['booking_err']);
$bookingInvoice = trim((string) ($_GET['booking_invoice'] ?? ''));
$logoPath = __DIR__ . '/assets/logo.png';
$hasLogo = is_file($logoPath);

try {
    $menuStmt = $pdo->prepare('SELECT id, name, description, price, img FROM menu WHERE active = 1 ORDER BY id ASC');
    $menuStmt->execute();
    $frontend_menu = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $frontend_menu = [];
}

try {
    $galleryStmt = $pdo->prepare('SELECT id, img, caption FROM gallery ORDER BY id DESC');
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
  <title>Trikut Restaurant &amp; Cafe</title>
  <link rel="stylesheet" href="CSS/style.css">
</head>
<body<?php echo $bookingInvoice !== '' ? ' data-booking-invoice="' . htmlspecialchars(app_url($bookingInvoice), ENT_QUOTES) . '"' : ''; ?>>
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
          <strong class="brand-title">Mountain-side food with a modern cafe feel.</strong>
        </div>
      </div>

      <div class="nav-shell">
        <button
          class="nav-toggle"
          type="button"
          aria-expanded="false"
          aria-controls="primaryNav"
          aria-label="Toggle navigation"
        >
          <span></span>
          <span></span>
          <span></span>
        </button>
        <nav id="primaryNav" class="main-nav" aria-label="Primary">
          <a href="#menu">Menu</a>
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
          <p class="eyebrow">Fresh kitchen. Quick reservations. Admin-ready orders.</p>
          <h1>Food that looks premium, feels local, and reaches the table fast.</h1>
          <p class="hero-copy">Customers can browse live menu items from the database, preview your space, reserve a table, and place an order in one smooth flow.</p>
          <div class="hero-actions">
            <a class="hero-btn primary" href="#menu">Explore Menu</a>
            <a class="hero-btn secondary" href="#book">Book a Table</a>
          </div>
          <div class="hero-stats">
            <div class="stat-chip">
              <strong><?php echo count($frontend_menu); ?></strong>
              <span>Live dishes</span>
            </div>
            <div class="stat-chip">
              <strong><?php echo count($frontend_gallery); ?></strong>
              <span>Gallery shots</span>
            </div>
            <div class="stat-chip">
              <strong>Live</strong>
              <span>Admin panel sync</span>
            </div>
          </div>
        </div>

        <div class="hero-visual">
          <?php if (!empty($frontend_gallery)): ?>
            <?php $heroImage = app_url($frontend_gallery[0]['img'] ?? ''); ?>
            <img class="hero-image" src="<?php echo htmlspecialchars($heroImage, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($frontend_gallery[0]['caption'] ?? 'Restaurant interior', ENT_QUOTES); ?>">
          <?php else: ?>
            <div class="hero-image hero-placeholder">Trikut</div>
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

    <section id="experience" class="experience-grid">
      <article class="experience-card card">
        <h2>Chef-led menu</h2>
        <p>Each card below is loaded from your database, so the homepage stays in sync with admin updates.</p>
      </article>
      <article class="experience-card card">
        <h2>Instant ordering</h2>
        <p>Guests can build a cart, submit the order, and send it straight into the live admin panel for the restaurant team.</p>
      </article>
      <article class="experience-card card">
        <h2>Simple booking</h2>
        <p>Reservations are validated on the page, saved in MySQL, and available in the admin dashboard immediately.</p>
      </article>
    </section>

    <div class="layout">
      <section class="content">
        <section id="menu">
          <div class="section-heading">
            <div>
              <p class="eyebrow">Menu</p>
              <h2 class="section-title">Signature dishes and cafe favorites</h2>
            </div>
            <p class="section-copy">A modern card-based layout with larger visuals, clearer prices, and better scanability on mobile.</p>
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
        </section>

        <section id="gallery">
          <div class="section-heading">
            <div>
              <p class="eyebrow">Gallery</p>
              <h2 class="section-title">Inside the restaurant</h2>
            </div>
            <p class="section-copy">Tap any image to preview it larger. Uploaded gallery images now resolve correctly under your project URL.</p>
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
        <div class="card spotlight-card">
          <p class="eyebrow">Today&apos;s vibe</p>
          <h3>Cozy cafe energy with full restaurant service.</h3>
          <p>Use the booking panel for dine-in and the cart for takeaway or direct order requests.</p>
        </div>

        <div class="card" id="book">
          <h3>Reserve a Table</h3>
          <form id="bookingForm" method="post" action="includes/save_booking.php" novalidate>
            <div class="field-group">
              <label for="bookName">Name</label>
              <input id="bookName" name="name" data-rule="name" placeholder="Your name" required>
            </div>

            <div class="field-group">
              <label for="bookPhone">Phone</label>
              <input id="bookPhone" name="phone" data-rule="phone" inputmode="numeric" maxlength="10" placeholder="10-digit mobile number" required>
            </div>

            <div class="field-group">
              <label for="bookDate">Date and Time</label>
              <input id="bookDate" type="datetime-local" name="date" required>
            </div>

            <div class="field-group">
              <label for="bookSize">Guests</label>
              <input id="bookSize" type="number" name="size" value="2" min="1" required>
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

    <section id="location" class="location-section">
      <div class="section-heading">
        <div>
          <p class="eyebrow">Location</p>
          <h2 class="section-title">Visit Trikut Restaurant &amp; Cafe</h2>
        </div>
        <p class="section-copy">Find the cafe directly on Google Maps and use the embedded map below for quick directions.</p>
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
          title="Trikut Restaurant and Cafe Google Map"
        ></iframe>
      </div>
    </section>
  </main>

  <footer class="site-footer">&copy; <?php echo date('Y'); ?> Trikut Restaurant &amp; Cafe</footer>

  <div id="imgModal" class="img-modal" aria-hidden="true">
    <button class="modal-close" type="button" aria-label="Close preview">&times;</button>
    <img id="modalImg" alt="">
  </div>

  <script src="Script/script.js"></script>
</body>
</html>
