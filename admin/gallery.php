<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/media.php';

$errors = [];
$projectRoot = dirname(__DIR__);
$galleryDir = $projectRoot . '/assets/gallery';
$galleryPublicDir = '/assets/gallery';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $caption = trim($_POST['caption'] ?? '');
        $imgUrl = normalize_storage_path($_POST['img'] ?? '');
        $uploadedCount = 0;

        if ($imgUrl !== '') {
            try {
                $stmt = $pdo->prepare('INSERT INTO gallery (img, caption, created_at) VALUES (?, ?, NOW())');
                $stmt->execute([$imgUrl, $caption ?: null]);
                $uploadedCount++;
            } catch (Throwable $e) {
                $errors[] = 'Database error while saving URL image.';
            }
        }

        if (isset($_FILES['images']) && is_array($_FILES['images']['name'] ?? null)) {
            $fileCount = count($_FILES['images']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                $file = [
                    'name' => $_FILES['images']['name'][$i] ?? '',
                    'type' => $_FILES['images']['type'][$i] ?? '',
                    'tmp_name' => $_FILES['images']['tmp_name'][$i] ?? '',
                    'error' => $_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES['images']['size'][$i] ?? 0,
                ];

                $storedPath = upload_image_file($file, $galleryDir, $galleryPublicDir, 'gallery', $errors);
                if ($storedPath === null) {
                    continue;
                }

                try {
                    $stmt = $pdo->prepare('INSERT INTO gallery (img, caption, created_at) VALUES (?, ?, NOW())');
                    $stmt->execute([$storedPath, $caption ?: null]);
                    $uploadedCount++;
                } catch (Throwable $e) {
                    $errors[] = 'Database error while saving uploaded image.';
                    remove_local_project_file($storedPath, $projectRoot);
                }
            }
        }

        if ($uploadedCount === 0 && empty($errors)) {
            $errors[] = 'Provide an image URL or upload one or more files.';
        }

        if (empty($errors)) {
            header('Location: gallery.php');
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare('SELECT img FROM gallery WHERE id = ? LIMIT 1');
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    remove_local_project_file($row['img'] ?? '', $projectRoot);
                }

                $stmt = $pdo->prepare('DELETE FROM gallery WHERE id = ? LIMIT 1');
                $stmt->execute([$id]);
            } catch (Throwable $e) {
                $errors[] = 'Failed to delete gallery image.';
            }
        }

        header('Location: gallery.php');
        exit;
    }
}

$items = [];
try {
    $stmt = $pdo->query('SELECT id, img, caption, created_at FROM gallery ORDER BY id DESC');
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $items = [];
}

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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Gallery - Trikut</title>
  <link rel="stylesheet" href="../CSS/style.css">
</head>
<body class="admin-body">
  <div class="admin-shell">
    <aside class="admin-sidebar" aria-label="Admin menu">
      <div class="admin-brand">Trikut - Admin</div>
      <div class="admin-greeting">Hello, <?php echo htmlspecialchars($adminName, ENT_QUOTES); ?></div>
      <nav class="admin-nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="menu.php">Menu</a>
        <a href="orders.php">Orders</a>
        <a href="bookings.php">Bookings</a>
        <a class="is-active" href="gallery.php">Gallery</a>
        <a href="logout.php">Logout</a>
      </nav>
      <div style="margin-top:auto" class="admin-meta">Server Time: <?php echo date('Y-m-d H:i'); ?></div>
    </aside>

    <main class="admin-main">
      <header class="admin-header">
        <div>
          <h1 class="admin-heading">Gallery</h1>
          <div class="admin-subtitle">Upload many photos at once or add one image URL independently.</div>
        </div>
      </header>

      <section class="admin-card">
        <h3 style="margin:0 0 10px">Add Photos</h3>
        <?php if (!empty($errors)): ?>
          <div class="admin-error">
            <?php foreach ($errors as $error): ?>
              <?php echo htmlspecialchars($error, ENT_QUOTES); ?><br>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add">
          <div class="admin-form-grid">
          <div class="admin-field">
            <label>Caption (optional)</label>
            <input name="caption">
          </div>
          <div class="admin-field">
            <label>Image URL (optional)</label>
            <input name="img" placeholder="https://... or /assets/gallery/...">
            <small>You can save one URL image without uploading a file.</small>
          </div>
          <div class="admin-field">
            <label>Upload Gallery Images</label>
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
            <small>Select as many images as you want. Each image will be saved as its own gallery item.</small>
          </div>
          </div>
          <div class="admin-form-actions">
            <button class="admin-btn-primary" type="submit">Add Photos</button>
          </div>
        </form>
      </section>

      <section class="admin-card">
        <h3 style="margin:0 0 10px">Existing Photos</h3>
        <?php if (empty($items)): ?>
          <div class="admin-empty">No gallery photos yet.</div>
        <?php else: ?>
          <div class="admin-tile-grid">
            <?php foreach ($items as $it): ?>
              <?php $img = !empty($it['img']) ? app_url($it['img']) : ''; ?>
              <div class="admin-tile">
                <?php if ($img !== ''): ?>
                  <img src="<?php echo htmlspecialchars($img, ENT_QUOTES); ?>" alt="">
                <?php else: ?>
                  <div class="admin-photo-placeholder" style="width:100%;height:130px;border-radius:12px"></div>
                <?php endif; ?>
                <div style="margin-top:8px"><?php echo htmlspecialchars($it['caption'] ?? '', ENT_QUOTES); ?></div>
                <div class="admin-muted" style="font-size:12px;margin-top:4px"><?php echo htmlspecialchars($it['created_at'] ?? '-', ENT_QUOTES); ?></div>
                <div style="margin-top:8px">
                  <form method="post" style="display:inline" onsubmit="return confirm('Delete this photo?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $it['id']; ?>">
                    <button class="admin-btn-danger" type="submit">Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>
