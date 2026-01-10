
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Handle POST actions: add, update, delete, toggle
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $img = trim($_POST['img'] ?? '');
        if ($name === '') $errors[] = 'Name is required';
        if ($price === '' || !is_numeric($price)) $errors[] = 'Valid price is required';
        if (empty($errors)) {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO menu (name, description, price, img, active, created_at) VALUES (?,?,?,?,1,NOW())");
                $stmt->execute([$name, $desc, number_format((float)$price, 2, '.', ''), $img ?: null]);
            } else {
                $id = intval($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE menu SET name = ?, description = ?, price = ?, img = ? WHERE id = ? LIMIT 1");
                    $stmt->execute([$name, $desc, number_format((float)$price, 2, '.', ''), $img ?: null, $id]);
                }
            }
            header('Location: menu.php');
            exit;
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM menu WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
        }
        header('Location: menu.php');
        exit;
    } elseif ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE menu SET active = 1 - active WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
        }
        header('Location: menu.php');
        exit;
    }
}

// For edit form (prefill)
$editItem = null;
if (isset($_GET['edit_id'])) {
    $eid = intval($_GET['edit_id']);
    if ($eid > 0) {
        $q = $pdo->prepare("SELECT * FROM menu WHERE id = ? LIMIT 1");
        $q->execute([$eid]);
        $editItem = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

// fetch all menu items
$items = [];
try {
    $q = $pdo->query("SELECT id, name, description, price, img, active, created_at FROM menu ORDER BY id DESC");
    $items = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // ignore, $items stays empty
}

$adminName = 'Admin';
try {
    $s = $pdo->prepare("SELECT username FROM admins WHERE id = ? LIMIT 1");
    $s->execute([$_SESSION['admin']]);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    if ($r && !empty($r['username'])) $adminName = $r['username'];
} catch (Throwable $e) {}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin — Menu — Trikut</title>
  <link rel="stylesheet" href="../CSS/style.css">
  <style>
    :root{--admin-aside-width:220px}
    body{margin:0;font-family:Inter,system-ui,Segoe UI,Roboto,Arial;background:var(--bg);color:#222}
    .app{display:flex;min-height:100vh}
    aside{width:var(--admin-aside-width);background:var(--card);border-right:1px solid #eee;padding:18px;display:flex;flex-direction:column;gap:12px}
    main{flex:1;padding:18px}
    .card{background:var(--card);padding:14px;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,0.04);margin-bottom:14px}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px;border-bottom:1px solid #f1f1f1;text-align:left;vertical-align:middle}
    th{background:#fafafa}
    img.thumb{width:64px;height:48px;object-fit:cover;border-radius:6px}
    .actions form{display:inline-block;margin:0 4px}
    .btn{background:var(--accent);color:#fff;padding:8px 10px;border-radius:8px;border:0;cursor:pointer;text-decoration:none}
    .small{background:#eee;color:#333;padding:6px 8px;border-radius:8px;border:0;cursor:pointer}
    .field{margin-bottom:10px}
    .field input, .field textarea{width:100%;padding:8px;border:1px solid #ddd;border-radius:8px}
    .muted{color:var(--muted)}
  </style>
</head>
<body>
  <div class="app">
    <aside aria-label="Admin menu">
      <div style="font-weight:700;color:var(--accent)">Trikut — Admin</div>
      <div class="muted">Hello, <?php echo htmlspecialchars($adminName, ENT_QUOTES); ?></div>
      <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="menu.php">Menu</a>
        <a href="orders.php">Orders</a>
        <a href="bookings.php">Bookings</a>
        <a href="logout.php">Logout</a>
      </nav>
      <div style="margin-top:auto" class="muted">Server Time: <?php echo date('Y-m-d H:i'); ?></div>
    </aside>

    <main>
      <header style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <h2 style="margin:0">Menu Management</h2>
          <div class="muted">Add, edit or remove menu items</div>
        </div>
        <div>
          <a class="btn" href="#addForm">Add New Item</a>
        </div>
      </header>

      <section class="card" aria-labelledby="itemsTable">
        <h3 id="itemsTable" style="margin:0 0 10px">Menu Items (<?php echo count($items); ?>)</h3>

        <?php if (empty($items)): ?>
          <div class="muted">No menu items yet.</div>
        <?php else: ?>
          <table>
            <thead>
              <tr><th>ID</th><th>Image</th><th>Name</th><th>Price</th><th>Active</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <tr>
                  <td><?php echo (int)$it['id']; ?></td>
                  <td>
                    <?php if (!empty($it['img'])): ?>
                      <img class="thumb" src="<?php echo htmlspecialchars($it['img'], ENT_QUOTES); ?>" alt="">
                    <?php else: ?>
                      <div style="width:64px;height:48px;border-radius:6px;background:#f3f3f3;display:flex;align-items:center;justify-content:center;color:#999">—</div>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($it['name'], ENT_QUOTES); ?><br><small class="muted"><?php echo htmlspecialchars(mb_strimwidth($it['description'] ?? '', 0, 80, '...'), ENT_QUOTES); ?></small></td>
                  <td>₹<?php echo htmlspecialchars($it['price'], ENT_QUOTES); ?></td>
                  <td><?php echo $it['active'] ? 'Yes' : 'No'; ?></td>
                  <td><?php echo htmlspecialchars($it['created_at'] ?? '-', ENT_QUOTES); ?></td>
                  <td class="actions">
                    <a class="small" href="menu.php?edit_id=<?php echo (int)$it['id']; ?>">Edit</a>
                    <form method="post" onsubmit="return confirm('Toggle active state?');" style="display:inline">
                      <input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">
                      <input type="hidden" name="action" value="toggle">
                      <button class="small" type="submit">Toggle</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Delete this item?');" style="display:inline">
                      <input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">
                      <input type="hidden" name="action" value="delete">
                      <button class="small" type="submit">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>

      <section class="card" id="addForm" aria-labelledby="addTitle">
        <h3 id="addTitle" style="margin:0 0 10px"><?php echo $editItem ? 'Edit Item' : 'Add New Item'; ?></h3>

        <?php if (!empty($errors)): ?>
          <div class="muted" style="color:#b93b3b;margin-bottom:10px">
            <?php foreach ($errors as $er) echo htmlspecialchars($er, ENT_QUOTES) . '<br>'; ?>
          </div>
        <?php endif; ?>

        <form method="post">
          <?php if ($editItem): ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo (int)$editItem['id']; ?>">
          <?php else: ?>
            <input type="hidden" name="action" value="add">
          <?php endif; ?>

          <div class="field">
            <label>Name</label>
            <input name="name" required value="<?php echo htmlspecialchars($editItem['name'] ?? '', ENT_QUOTES); ?>">
          </div>

          <div class="field">
            <label>Description</label>
            <textarea name="description" rows="3"><?php echo htmlspecialchars($editItem['description'] ?? '', ENT_QUOTES); ?></textarea>
          </div>

          <div class="field" style="max-width:220px">
            <label>Price (INR)</label>
            <input name="price" required value="<?php echo htmlspecialchars($editItem['price'] ?? '', ENT_QUOTES); ?>">
          </div>

          <div class="field">
            <label>Image URL</label>
            <input name="img" value="<?php echo htmlspecialchars($editItem['img'] ?? '', ENT_QUOTES); ?>" placeholder="https://...">
          </div>

          <div style="display:flex;gap:10px;align-items:center">
            <button class="btn" type="submit"><?php echo $editItem ? 'Update' : 'Add Item'; ?></button>
            <?php if ($editItem): ?>
              <a class="small" href="menu.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>
    </main>
  </div>
</body>
</html>