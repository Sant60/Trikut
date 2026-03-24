<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/media.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/admin_profile.php';
require_once __DIR__ . '/../includes/tenant.php';

$errors = [];
$projectRoot = dirname(__DIR__);
$menuDir = $projectRoot . '/assets/menu';
$menuPublicDir = '/assets/menu';
$currentAdminId = (int) $_SESSION['admin'];
ensure_multi_admin_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_request()) {
        $errors[] = 'Invalid request token.';
    }

    $action = $_POST['action'] ?? '';

    if (empty($errors) && ($action === 'add' || $action === 'update')) {
        $name = normalize_text($_POST['name'] ?? '', 120);
        $desc = normalize_text($_POST['description'] ?? '', 1500);
        $price = trim($_POST['price'] ?? '');
        $img = normalize_storage_path($_POST['img'] ?? '');
        $keepExisting = isset($_POST['keep_existing_image']) ? (bool) $_POST['keep_existing_image'] : false;
        $currentImage = normalize_storage_path($_POST['current_img'] ?? '');

        if ($name === '' || !preg_match('/^[\p{L}\p{N}\s&\'().,-]{2,120}$/u', $name)) {
            $errors[] = 'Name must be between 2 and 120 valid characters.';
        }

        if (!is_valid_positive_money($price)) {
            $errors[] = 'Valid price is required.';
        }

        if ($img !== '' && !is_valid_storage_or_http_path($img)) {
            $errors[] = 'Image URL or path is invalid.';
        }

        $uploadedImage = upload_image_file($_FILES['image'] ?? [], $menuDir, $menuPublicDir, 'menu', $errors);

        if ($uploadedImage !== null) {
            $img = $uploadedImage;
        } elseif ($action === 'update' && $keepExisting && $img === '') {
            $img = $currentImage;
        }

        if (empty($errors)) {
            if ($action === 'add') {
                $stmt = $pdo->prepare('INSERT INTO menu (admin_id, name, description, price, img, active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())');
                $stmt->execute([$currentAdminId, $name, $desc ?: null, number_format((float) $price, 2, '.', ''), $img ?: null]);
            } else {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE menu SET name = ?, description = ?, price = ?, img = ? WHERE id = ? AND admin_id = ? LIMIT 1');
                    $stmt->execute([$name, $desc ?: null, number_format((float) $price, 2, '.', ''), $img ?: null, $id, $currentAdminId]);

                    if ($uploadedImage !== null && $currentImage !== '' && $currentImage !== $uploadedImage) {
                        remove_local_project_file($currentImage, $projectRoot);
                    }

                    if (!$keepExisting && $img === '' && $currentImage !== '') {
                        remove_local_project_file($currentImage, $projectRoot);
                    }
                }
            }

            header('Location: menu.php');
            exit;
        }
    } elseif (empty($errors) && $action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare('SELECT img FROM menu WHERE id = ? AND admin_id = ? LIMIT 1');
                $stmt->execute([$id, $currentAdminId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    remove_local_project_file($row['img'] ?? '', $projectRoot);
                }

                $stmt = $pdo->prepare('DELETE FROM menu WHERE id = ? AND admin_id = ? LIMIT 1');
                $stmt->execute([$id, $currentAdminId]);
            } catch (Throwable $e) {
                $errors[] = 'Failed to delete menu item.';
            }
        }

        header('Location: menu.php');
        exit;
    } elseif (empty($errors) && $action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE menu SET active = 1 - active WHERE id = ? AND admin_id = ? LIMIT 1');
            $stmt->execute([$id, $currentAdminId]);
        }

        header('Location: menu.php');
        exit;
    }
}

$editItem = null;
if (isset($_GET['edit_id'])) {
    $editId = (int) $_GET['edit_id'];
    if ($editId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM menu WHERE id = ? AND admin_id = ? LIMIT 1');
        $stmt->execute([$editId, $currentAdminId]);
        $editItem = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

$items = [];
try {
    $stmt = $pdo->prepare('SELECT id, name, description, price, img, active, created_at FROM menu WHERE admin_id = ? ORDER BY id DESC');
    $stmt->execute([$currentAdminId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $items = [];
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
  <title>Admin - Menu - Trikut</title>
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
        <a class="is-active" href="menu.php">Menu</a>
        <a href="hero.php">Hero Image</a>
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
          <h1 class="admin-heading">Menu Management</h1>
          <div class="admin-subtitle">Add dish images directly or use an external image URL.</div>
        </div>
        <div>
          <a class="admin-btn-primary" href="#addForm">Add New Dish</a>
        </div>
      </header>

      <section class="admin-card" aria-labelledby="itemsTable">
        <h3 id="itemsTable" style="margin:0 0 10px">Menu Items (<?php echo count($items); ?>)</h3>

        <?php if (empty($items)): ?>
          <div class="admin-empty">No menu items yet.</div>
        <?php else: ?>
          <div class="admin-table-wrap"><table class="admin-table">
            <thead>
              <tr><th>ID</th><th>Image</th><th>Name</th><th>Price</th><th>Active</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <tr>
                  <td><?php echo (int) $it['id']; ?></td>
                  <td>
                    <?php if (!empty($it['img'])): ?>
                      <img class="admin-thumb" src="<?php echo htmlspecialchars(app_url($it['img']), ENT_QUOTES); ?>" alt="">
                    <?php else: ?>
                      <div class="admin-thumb-placeholder">-</div>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($it['name'], ENT_QUOTES); ?><br><small class="admin-muted"><?php echo htmlspecialchars(mb_strimwidth($it['description'] ?? '', 0, 80, '...'), ENT_QUOTES); ?></small></td>
                  <td>&#8377;<?php echo htmlspecialchars($it['price'], ENT_QUOTES); ?></td>
                  <td><?php echo $it['active'] ? 'Yes' : 'No'; ?></td>
                  <td><?php echo htmlspecialchars($it['created_at'] ?? '-', ENT_QUOTES); ?></td>
                  <td>
                    <div class="admin-inline-actions">
                    <a class="admin-btn-secondary" href="menu.php?edit_id=<?php echo (int) $it['id']; ?>">Edit</a>
                    <form method="post" onsubmit="return confirm('Toggle active state?');" style="display:inline">
                      <?php echo csrf_input(); ?>
                      <input type="hidden" name="id" value="<?php echo (int) $it['id']; ?>">
                      <input type="hidden" name="action" value="toggle">
                      <button class="admin-btn-secondary" type="submit">Toggle</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Delete this item?');" style="display:inline">
                      <?php echo csrf_input(); ?>
                      <input type="hidden" name="id" value="<?php echo (int) $it['id']; ?>">
                      <input type="hidden" name="action" value="delete">
                      <button class="admin-btn-danger" type="submit">Delete</button>
                    </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      </section>

      <section class="admin-card" id="addForm" aria-labelledby="addTitle">
        <h3 id="addTitle" style="margin:0 0 10px"><?php echo $editItem ? 'Edit Dish' : 'Add New Dish'; ?></h3>

        <?php if (!empty($errors)): ?>
          <div class="admin-error">
            <?php foreach ($errors as $error): ?>
              <?php echo htmlspecialchars($error, ENT_QUOTES); ?><br>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" novalidate data-validate-form>
          <?php echo csrf_input(); ?>
          <?php if ($editItem): ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo (int) $editItem['id']; ?>">
            <input type="hidden" name="current_img" value="<?php echo htmlspecialchars($editItem['img'] ?? '', ENT_QUOTES); ?>">
          <?php else: ?>
            <input type="hidden" name="action" value="add">
          <?php endif; ?>

          <div class="admin-form-grid">
          <div class="admin-field">
            <label>Name</label>
            <input name="name" data-rule="name" maxlength="120" required value="<?php echo htmlspecialchars($editItem['name'] ?? '', ENT_QUOTES); ?>">
          </div>

          <div class="admin-field">
            <label>Description</label>
            <textarea name="description" rows="3"><?php echo htmlspecialchars($editItem['description'] ?? '', ENT_QUOTES); ?></textarea>
          </div>

          <div class="admin-field is-compact">
            <label>Price (INR)</label>
            <input name="price" type="text" inputmode="decimal" data-rule="price" required value="<?php echo htmlspecialchars($editItem['price'] ?? '', ENT_QUOTES); ?>">
          </div>

          <div class="admin-field">
            <label>Upload Dish Image</label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
            <small>This works independently. Uploading a file saves it in the project and uses it for the dish.</small>
          </div>

          <div class="admin-field">
            <label>Image URL (optional)</label>
            <input name="img" type="url" value="<?php echo htmlspecialchars($editItem['img'] ?? '', ENT_QUOTES); ?>" placeholder="https://... or /assets/menu/...">
            <small>Use this only if you want an external image or already have a local path.</small>
          </div>
          </div>

          <?php if ($editItem && !empty($editItem['img'])): ?>
            <label class="admin-checkbox">
              <input type="checkbox" name="keep_existing_image" value="1" checked>
              <span>Keep current image if no new file is uploaded</span>
            </label>
            <div class="admin-preview">
              <img src="<?php echo htmlspecialchars(app_url($editItem['img']), ENT_QUOTES); ?>" alt="">
            </div>
          <?php endif; ?>

          <div class="admin-form-actions" style="margin-top:14px">
            <button class="admin-btn-primary" type="submit"><?php echo $editItem ? 'Update Dish' : 'Add Dish'; ?></button>
            <?php if ($editItem): ?>
              <a class="admin-btn-secondary" href="menu.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>
    </main>
  </div>
  <script src="../Script/theme.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/theme.js') ?: time()); ?>"></script>
  <script src="../Script/form-validation.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/form-validation.js') ?: time()); ?>"></script>
</body>
</html>
