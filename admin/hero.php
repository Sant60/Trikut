<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/media.php';
require_once __DIR__ . '/../includes/hero_media.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/admin_profile.php';
require_once __DIR__ . '/../includes/tenant.php';

$errors = [];
$projectRoot = dirname(__DIR__);
$heroDir = $projectRoot . '/assets/hero';
$heroPublicDir = '/assets/hero';
$currentAdminId = (int) $_SESSION['admin'];

ensure_hero_media_table($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_request()) {
        $errors[] = 'Invalid request token.';
    }

    $action = $_POST['action'] ?? '';

    if (empty($errors) && $action === 'save') {
        $caption = normalize_text($_POST['caption'] ?? '', 255);
        $imgUrl = normalize_storage_path($_POST['img'] ?? '');
        $storedPath = $imgUrl;

        if ($imgUrl !== '' && !is_valid_storage_or_http_path($imgUrl)) {
            $errors[] = 'Hero image URL or path is invalid.';
        }

        $uploadedImage = upload_image_file($_FILES['image'] ?? [], $heroDir, $heroPublicDir, 'hero', $errors);
        if ($uploadedImage !== null) {
            $storedPath = $uploadedImage;
        }

        if ($storedPath === '') {
            $errors[] = 'Provide a hero image URL or upload an image file.';
        }

        if (empty($errors)) {
            try {
                $current = fetch_current_hero_media($pdo, $currentAdminId);

                $pdo->beginTransaction();
                $stmt = $pdo->prepare('DELETE FROM hero_media WHERE admin_id = ?');
                $stmt->execute([$currentAdminId]);
                $stmt = $pdo->prepare('INSERT INTO hero_media (admin_id, img, caption, created_at) VALUES (?, ?, ?, NOW())');
                $stmt->execute([$currentAdminId, $storedPath, $caption ?: null]);
                $pdo->commit();

                if ($current && !empty($current['img']) && $current['img'] !== $storedPath) {
                    remove_local_project_file($current['img'], $projectRoot);
                }

                header('Location: hero.php');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($uploadedImage !== null) {
                    remove_local_project_file($uploadedImage, $projectRoot);
                }

                $errors[] = 'Failed to save hero image.';
            }
        }
    } elseif (empty($errors) && $action === 'delete') {
        try {
            $current = fetch_current_hero_media($pdo, $currentAdminId);
            $stmt = $pdo->prepare('DELETE FROM hero_media WHERE admin_id = ?');
            $stmt->execute([$currentAdminId]);

            if ($current) {
                remove_local_project_file($current['img'] ?? '', $projectRoot);
            }
        } catch (Throwable $e) {
            $errors[] = 'Failed to delete hero image.';
        }

        header('Location: hero.php');
        exit;
    }
}

$heroItem = null;
try {
    $heroItem = fetch_current_hero_media($pdo, $currentAdminId);
} catch (Throwable $e) {
    $heroItem = null;
    $errors[] = 'Hero image data could not be loaded.';
}

ensure_admin_profile_schema($pdo);
$adminProfile = fetch_admin_profile($pdo, (int) $_SESSION['admin']) ?? [];
$adminName = $adminProfile['display_name'] ?? ($adminProfile['username'] ?? 'Admin');
$adminMobile = $adminProfile['mobile'] ?? '';
$adminPhotoUrl = !empty($adminProfile['photo']) ? app_url($adminProfile['photo']) : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Hero Image - Trikut</title>
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
        <a href="dashboard.php">Dashboard</a>
        <a href="menu.php">Menu</a>
        <a class="is-active" href="hero.php">Hero Image</a>
        <a href="gallery.php">Gallery</a>
        <a href="orders.php">Orders</a>
        <a href="bookings.php">Bookings</a>
        <a href="logout.php">Logout</a>
      </nav>
      <div style="margin-top:auto" class="admin-meta">Server Time: <?php echo date('Y-m-d H:i'); ?></div>
    </aside>

    <main class="admin-main">
      <header class="admin-header">
        <div>
          <h1 class="admin-heading">Hero Image</h1>
          <div class="admin-subtitle">Upload one dedicated homepage hero image without affecting the gallery.</div>
        </div>
      </header>

      <section class="admin-card">
        <h3 style="margin:0 0 10px">Current Hero</h3>
        <?php if ($heroItem && !empty($heroItem['img'])): ?>
          <div class="admin-preview">
            <img src="<?php echo htmlspecialchars(app_url($heroItem['img']), ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($heroItem['caption'] ?: 'Hero image', ENT_QUOTES); ?>">
          </div>
          <div style="margin-top:10px"><?php echo htmlspecialchars($heroItem['caption'] ?? '', ENT_QUOTES); ?></div>
          <div class="admin-muted" style="margin-top:4px"><?php echo htmlspecialchars($heroItem['created_at'] ?? '-', ENT_QUOTES); ?></div>
          <div class="admin-form-actions" style="margin-top:14px">
            <form method="post" onsubmit="return confirm('Delete the current hero image?');">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="action" value="delete">
              <button class="admin-btn-danger" type="submit">Delete Hero Image</button>
            </form>
          </div>
        <?php else: ?>
          <div class="admin-empty">No dedicated hero image has been set yet.</div>
        <?php endif; ?>
      </section>

      <section class="admin-card">
        <h3 style="margin:0 0 10px">Upload / Replace Hero</h3>
        <?php if (!empty($errors)): ?>
          <div class="admin-error">
            <?php foreach ($errors as $error): ?>
              <?php echo htmlspecialchars($error, ENT_QUOTES); ?><br>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" novalidate data-validate-form>
          <?php echo csrf_input(); ?>
          <input type="hidden" name="action" value="save">
          <div class="admin-form-grid">
            <div class="admin-field">
              <label>Caption (optional)</label>
              <input name="caption" maxlength="255" value="<?php echo htmlspecialchars($heroItem['caption'] ?? '', ENT_QUOTES); ?>">
            </div>
            <div class="admin-field">
              <label>Hero Image URL (optional)</label>
              <input name="img" type="url" data-require-one="hero-source" placeholder="https://... or /assets/hero/...">
              <small>Use a URL if the hero image is hosted elsewhere.</small>
            </div>
            <div class="admin-field">
              <label>Upload Hero Image</label>
              <input type="file" name="image" data-require-one="hero-source" accept="image/jpeg,image/png,image/webp">
              <small>Uploading a new file replaces the current hero image.</small>
            </div>
          </div>
          <div class="admin-form-actions">
            <button class="admin-btn-primary" type="submit">Save Hero Image</button>
          </div>
        </form>
      </section>
    </main>
  </div>
  <script src="../Script/theme.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/theme.js') ?: time()); ?>"></script>
  <script src="../Script/form-validation.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/form-validation.js') ?: time()); ?>"></script>
</body>
</html>
