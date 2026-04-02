<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/tenant.php';

ensure_multi_admin_schema($pdo);
require_owner_admin($pdo, (int) $_SESSION['admin']);

$error = '';
$success = '';
$username = '';
$displayName = '';
$mobile = '';
$role = 'manager';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = normalize_text($_POST['username'] ?? '', 100);
    $displayName = normalize_text($_POST['display_name'] ?? '', 120);
    $mobile = trim((string) ($_POST['mobile'] ?? ''));
    $role = normalize_admin_role((string) ($_POST['role'] ?? 'manager'));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!verify_csrf_request()) {
        $error = 'Your session token is invalid. Please try again.';
    } elseif ($username === '' || $displayName === '' || $mobile === '' || $password === '' || $confirmPassword === '') {
        $error = 'All fields are required.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password confirmation does not match.';
    } else {
        $result = create_admin_account($pdo, $username, $displayName, $mobile, $password, $role);
        if (!empty($result['errors'])) {
            $error = implode(' ', $result['errors']);
        } else {
            $success = 'Admin account created successfully with the ' . admin_role_label($role) . ' role.';
            $username = '';
            $displayName = '';
            $mobile = '';
            $role = 'manager';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Admin - Trikut Restaurant</title>
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
<body class="admin-login-body">
  <main class="admin-login-card" role="main" aria-labelledby="registerTitle">
    <button class="theme-toggle admin-theme-toggle" type="button" data-theme-toggle data-theme-storage="admin-theme" aria-pressed="false" aria-label="Switch to dark mode" title="Toggle admin light and dark mode">
      <i class="fa-solid fa-toggle-off" aria-hidden="true"></i>
      <i class="fa-solid fa-toggle-on" aria-hidden="true"></i>
    </button>
    <h1 id="registerTitle">Add New Admin</h1>
    <p class="admin-subtitle">Only the main owner can create new admin accounts. Use roles to control what each person can update inside the restaurant panel.</p>

    <?php if ($error !== ''): ?>
      <div class="admin-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
      <div class="notice success" role="status"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <form method="post" novalidate data-validate-form>
      <?php echo csrf_input(); ?>
      <div class="admin-field">
        <label for="registerDisplayName">Display Name</label>
        <input id="registerDisplayName" name="display_name" data-rule="name" required value="<?php echo htmlspecialchars($displayName, ENT_QUOTES); ?>">
      </div>

      <div class="admin-field">
        <label for="registerUsername">Username</label>
        <input id="registerUsername" name="username" data-rule="username" maxlength="100" autocomplete="username" required value="<?php echo htmlspecialchars($username, ENT_QUOTES); ?>">
      </div>

      <div class="admin-field">
        <label for="registerMobile">Mobile Number</label>
        <input id="registerMobile" name="mobile" type="tel" data-rule="phone" inputmode="numeric" maxlength="13" placeholder="+91 9876543210" required value="<?php echo htmlspecialchars($mobile, ENT_QUOTES); ?>">
      </div>

      <div class="admin-field">
        <label for="registerRole">Role</label>
        <select id="registerRole" name="role" required>
          <option value="manager"<?php echo $role === 'manager' ? ' selected' : ''; ?>>Manager</option>
          <option value="employee"<?php echo $role === 'employee' ? ' selected' : ''; ?>>Employee</option>
        </select>
        <small>Managers can update restaurant data. Employees get read-only access to customer-facing records.</small>
      </div>
      <div class="admin-field">
        <label for="registerPassword">Password</label>
        <input id="registerPassword" name="password" type="password" data-rule="password" minlength="8" autocomplete="new-password" required>
      </div>

      <div class="admin-field">
        <label for="registerPasswordConfirm">Confirm Password</label>
        <input id="registerPasswordConfirm" name="confirm_password" type="password" data-rule="password" data-match="registerPassword" minlength="8" autocomplete="new-password" required>
      </div>

      <div class="admin-form-actions">
        <button class="admin-btn-primary" type="submit">Create Admin</button>
        <a class="admin-btn-secondary" href="dashboard.php">Back to Dashboard</a>
      </div>
    </form>
  </main>

  <script src="../Script/theme.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/theme.js') ?: time()); ?>"></script>
  <script src="../Script/form-validation.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/form-validation.js') ?: time()); ?>"></script>
</body>
</html>
