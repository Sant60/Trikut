<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/tenant.php';

ensure_multi_admin_schema($pdo);
require_owner_admin($pdo, (int) $_SESSION['admin']);

$error = '';
$success = '';
$admins = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetAdminId = (int) ($_POST['admin_id'] ?? 0);
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!verify_csrf_request()) {
        $error = 'Your session token is invalid. Please try again.';
    } elseif ($targetAdminId <= 0 || $newPassword === '' || $confirmPassword === '') {
        $error = 'Select an admin and enter the new password twice.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Password confirmation does not match.';
    } else {
        $passwordError = validate_new_password($newPassword);
        if ($passwordError !== null) {
            $error = $passwordError;
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE admins SET password = ? WHERE id = ? LIMIT 1');
                $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $targetAdminId]);
                if ($stmt->rowCount() > 0) {
                    $success = 'Admin password reset successfully.';
                } else {
                    $error = 'Selected admin could not be updated.';
                }
            } catch (Throwable $e) {
                $error = 'Could not reset the admin password right now.';
            }
        }
    }
}

try {
    $stmt = $pdo->query('SELECT id, username, display_name, mobile, is_owner, is_active, role, created_at FROM admins ORDER BY is_owner DESC, id ASC');
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
  <title>Reset Admin Password - Trikut Restaurant</title>
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
        <h1 class="admin-title">Reset Admin Password</h1>
        <div class="admin-subtitle">Only the main owner can reset passwords for admin accounts.</div>
      </div>
      <button class="theme-toggle admin-theme-toggle" type="button" data-theme-toggle data-theme-storage="admin-theme" aria-pressed="false" aria-label="Switch to dark mode" title="Toggle admin light and dark mode">
        <i class="fa-solid fa-toggle-off" aria-hidden="true"></i>
        <i class="fa-solid fa-toggle-on" aria-hidden="true"></i>
      </button>
    </div>

    <section class="admin-card">
      <h3 style="margin:0 0 12px">Reset Password</h3>
      <?php if ($error !== ''): ?>
        <div class="admin-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
      <?php endif; ?>
      <?php if ($success !== ''): ?>
        <div class="notice success" role="status"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div>
      <?php endif; ?>

      <form method="post" novalidate data-validate-form>
        <?php echo csrf_input(); ?>
        <div class="admin-form-grid">
          <div class="admin-field">
            <label for="admin_id">Admin Account</label>
            <select id="admin_id" name="admin_id" required>
              <option value="">Select admin</option>
              <?php foreach ($admins as $admin): ?>
                <option value="<?php echo (int) $admin['id']; ?>">
                  <?php
                  echo htmlspecialchars(
                      (($admin['display_name'] ?: $admin['username']) ?? 'Admin')
                      . ' (' . ($admin['mobile'] ?: $admin['username']) . ')'
                      . ' - ' . admin_role_label(normalize_admin_role((string) ($admin['role'] ?? ''), !empty($admin['is_owner']))),
                      ENT_QUOTES
                  );
                  ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="admin-field">
            <label for="new_password">New Password</label>
            <input id="new_password" name="new_password" type="password" data-rule="password" minlength="8" autocomplete="new-password" required>
          </div>
          <div class="admin-field">
            <label for="confirm_password">Confirm New Password</label>
            <input id="confirm_password" name="confirm_password" type="password" data-rule="password" data-match="new_password" minlength="8" autocomplete="new-password" required>
          </div>
        </div>
        <div class="admin-form-actions" style="margin-top:14px">
          <button class="admin-btn-primary" type="submit">Reset Password</button>
        </div>
      </form>
    </section>

    <section class="admin-card">
      <h3 style="margin:0 0 12px">Admin Accounts</h3>
      <?php if (empty($admins)): ?>
        <div class="admin-empty">No admin accounts found.</div>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr><th>ID</th><th>Name</th><th>Username</th><th>Mobile</th><th>Role</th><th>Status</th><th>Created</th></tr>
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
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <script src="../Script/theme.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/theme.js') ?: time()); ?>"></script>
  <script src="../Script/form-validation.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/form-validation.js') ?: time()); ?>"></script>
</body>
</html>
