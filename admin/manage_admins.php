<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/tenant.php';

ensure_multi_admin_schema($rootPdo);
require_owner_admin($rootPdo, (int) $_SESSION['admin']);

$error = '';
$success = '';
$editAdmin = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetAdminId = (int) ($_POST['admin_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    if (!verify_csrf_request()) {
        $error = 'Invalid request token.';
    } elseif ($targetAdminId <= 0 || !in_array($action, ['disable', 'enable', 'delete', 'update'], true)) {
        $error = 'Invalid admin action.';
    } elseif ($targetAdminId === (int) $_SESSION['admin'] && in_array($action, ['disable', 'delete'], true)) {
        $error = $action === 'delete' ? 'You cannot delete your own owner account.' : 'You cannot disable your own owner account.';
    } else {
        try {
            if ($action === 'enable' || $action === 'disable') {
                $value = $action === 'enable' ? 1 : 0;
                $stmt = $rootPdo->prepare('UPDATE admins SET is_active = ? WHERE id = ? LIMIT 1');
                $stmt->execute([$value, $targetAdminId]);
                $success = $action === 'enable' ? 'Admin enabled successfully.' : 'Admin disabled successfully.';
            } elseif ($action === 'delete') {
                $stmt = $rootPdo->prepare('DELETE FROM admins WHERE id = ? LIMIT 1');
                $stmt->execute([$targetAdminId]);
                $success = 'Admin account deleted successfully.';
            } else {
                $username = normalize_text($_POST['username'] ?? '', 100);
                $displayName = normalize_text($_POST['display_name'] ?? '', 120);
                $mobile = trim((string) ($_POST['mobile'] ?? ''));
                $role = normalize_admin_role((string) ($_POST['role'] ?? 'manager'));

                if ($username === '' || !preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username)) {
                    throw new RuntimeException('Username must be 3 to 100 characters and use letters, numbers, dots, underscores, or hyphens.');
                }

                if ($displayName === '' || !preg_match('/^[\p{L}\p{N}\s&\'().,-]{2,120}$/u', $displayName)) {
                    throw new RuntimeException('Display name must be between 2 and 120 valid characters.');
                }

                if ($mobile !== '' && !is_valid_indian_phone($mobile)) {
                    throw new RuntimeException('Enter a valid admin mobile number.');
                }

                $normalizedMobile = $mobile === '' ? null : normalize_indian_phone($mobile);
                $stmt = $rootPdo->prepare('SELECT id FROM admins WHERE (username = ? OR mobile = ?) AND id != ? LIMIT 1');
                $stmt->execute([$username, $normalizedMobile, $targetAdminId]);
                if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                    throw new RuntimeException('That username or mobile number is already in use.');
                }

                $stmt = $rootPdo->prepare('UPDATE admins SET username = ?, display_name = ?, mobile = ?, role = ? WHERE id = ? LIMIT 1');
                $stmt->execute([$username, $displayName, $normalizedMobile, $role, $targetAdminId]);
                $success = 'Admin details updated successfully.';
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            $error = 'Could not update admin data right now.';
        }
    }
}

if (isset($_GET['edit_id'])) {
    $editId = (int) ($_GET['edit_id'] ?? 0);
    if ($editId > 0) {
        try {
            $stmt = $rootPdo->prepare('SELECT id, username, display_name, mobile, is_owner, is_active, role, created_at FROM admins WHERE id = ? LIMIT 1');
            $stmt->execute([$editId]);
            $editAdmin = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            $editAdmin = null;
        }
    }
}

$admins = [];
try {
    $stmt = $rootPdo->query('SELECT id, username, display_name, mobile, is_owner, is_active, role, created_at FROM admins ORDER BY is_owner DESC, id ASC');
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $admins = [];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Admins - Trikut Restaurant</title>
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
  <div class="admin-page">
    <div class="admin-page-head">
      <div>
        <a class="admin-back-link" href="dashboard.php">&larr; Back to Dashboard</a>
        <h1 class="admin-title">Manage Admins</h1>
        <div class="admin-subtitle">Owner-only admin access control for the shared restaurant website.</div>
      </div>
      <button class="theme-toggle admin-theme-toggle" type="button" data-theme-toggle data-theme-storage="admin-theme" aria-pressed="false" aria-label="Switch to dark mode" title="Toggle admin light and dark mode">
        <i class="fa-solid fa-toggle-off" aria-hidden="true"></i>
        <i class="fa-solid fa-toggle-on" aria-hidden="true"></i>
      </button>
    </div>

    <section class="admin-card">
      <div class="admin-form-actions" style="margin-bottom:14px">
        <a class="admin-btn-primary" href="register.php">Add Admin</a>
        <a class="admin-btn-secondary" href="reset_admin_password.php">Reset Admin Password</a>
      </div>

      <?php if ($error !== ''): ?>
        <div class="admin-error"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
      <?php endif; ?>
      <?php if ($success !== ''): ?>
        <div class="notice success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div>
      <?php endif; ?>

      <?php if ($editAdmin): ?>
        <section class="admin-card" style="margin-bottom:18px">
          <h3 style="margin:0 0 12px">Edit Admin #<?php echo (int) $editAdmin['id']; ?></h3>
          <form method="post" novalidate data-validate-form>
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="admin_id" value="<?php echo (int) $editAdmin['id']; ?>">
            <div class="admin-form-grid">
              <div class="admin-field">
                <label>Display Name</label>
                <input name="display_name" data-rule="name" maxlength="120" value="<?php echo htmlspecialchars($editAdmin['display_name'] ?: '', ENT_QUOTES); ?>" required>
              </div>
              <div class="admin-field">
                <label>Username</label>
                <input name="username" data-rule="username" maxlength="100" value="<?php echo htmlspecialchars($editAdmin['username'], ENT_QUOTES); ?>" required>
              </div>
              <div class="admin-field">
                <label>Mobile Number</label>
                <input name="mobile" type="tel" value="<?php echo htmlspecialchars($editAdmin['mobile'] ?: '', ENT_QUOTES); ?>" placeholder="+91 9876543210">
              </div>
              <div class="admin-field">
                <label>Role</label>
                <select name="role" <?php echo !empty($editAdmin['is_owner']) ? 'disabled' : ''; ?>>
                  <option value="manager"<?php echo (($editAdmin['role'] ?? 'manager') === 'manager') ? ' selected' : ''; ?>>Manager</option>
                  <option value="employee"<?php echo (($editAdmin['role'] ?? '') === 'employee') ? ' selected' : ''; ?>>Employee</option>
                </select>
                <?php if (!empty($editAdmin['is_owner'])): ?>
                  <small>The owner role is fixed for the main administrator.</small>
                <?php endif; ?>
              </div>
            </div>
            <div class="admin-form-actions" style="margin-top:14px">
              <button class="admin-btn-primary" type="submit">Save Changes</button>
              <a class="admin-btn-secondary" href="manage_admins.php">Cancel</a>
            </div>
          </form>
        </section>
      <?php endif; ?>

      <?php if (empty($admins)): ?>
        <div class="admin-empty">No admins found.</div>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr><th>ID</th><th>Name</th><th>Username</th><th>Mobile</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($admins as $admin): ?>
                <tr>
                  <td>#<?php echo (int) $admin['id']; ?></td>
                  <td><?php echo htmlspecialchars($admin['display_name'] ?: $admin['username'], ENT_QUOTES); ?></td>
                  <td><?php echo htmlspecialchars($admin['username'], ENT_QUOTES); ?></td>
                  <td><?php echo htmlspecialchars($admin['mobile'] ?: '-', ENT_QUOTES); ?></td>
                  <td><?php echo htmlspecialchars(admin_role_label(normalize_admin_role((string) ($admin['role'] ?? ''), !empty($admin['is_owner']))), ENT_QUOTES); ?></td>
                  <td><?php echo !empty($admin['is_active']) ? 'Active' : 'Disabled'; ?></td>
                  <td><?php echo htmlspecialchars($admin['created_at'] ?? '-', ENT_QUOTES); ?></td>
                  <td>
                    <?php if ((int) $admin['id'] === (int) $_SESSION['admin']): ?>
                      <span class="admin-muted">Current owner</span>
                    <?php else: ?>
                      <div class="admin-inline-actions">
                        <a class="admin-btn-secondary" href="manage_admins.php?edit_id=<?php echo (int) $admin['id']; ?>">Edit</a>
                        <form method="post">
                          <?php echo csrf_input(); ?>
                          <input type="hidden" name="admin_id" value="<?php echo (int) $admin['id']; ?>">
                          <input type="hidden" name="action" value="<?php echo !empty($admin['is_active']) ? 'disable' : 'enable'; ?>">
                          <button class="<?php echo !empty($admin['is_active']) ? 'admin-btn-danger' : 'admin-btn-primary'; ?>" type="submit">
                            <?php echo !empty($admin['is_active']) ? 'Disable' : 'Enable'; ?>
                          </button>
                        </form>
                        <form method="post" onsubmit="return confirm('Delete this admin account?');">
                          <?php echo csrf_input(); ?>
                          <input type="hidden" name="admin_id" value="<?php echo (int) $admin['id']; ?>">
                          <input type="hidden" name="action" value="delete">
                          <button class="admin-btn-danger" type="submit">Delete Account</button>
                        </form>
                      </div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <script src="../Script/theme.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/theme.js') ?: time()); ?>"></script>
</body>
</html>
