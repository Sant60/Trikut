<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/security.php';

ensure_session_started();
ensure_multi_admin_schema($pdo);
$ownerAdminId = get_default_admin_id($pdo);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Recovery - Trikut Restaurant</title>
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
  <main class="admin-login-card" role="main" aria-labelledby="recoveryTitle">
    <button class="theme-toggle admin-theme-toggle" type="button" data-theme-toggle data-theme-storage="admin-theme" aria-pressed="false" aria-label="Switch to dark mode" title="Toggle admin light and dark mode">
      <i class="fa-solid fa-toggle-off" aria-hidden="true"></i>
      <i class="fa-solid fa-toggle-on" aria-hidden="true"></i>
    </button>
    <h1 id="recoveryTitle">Admin Recovery</h1>
    <p class="admin-subtitle">Direct password reset from the public login page has been disabled for security.</p>

    <div class="admin-card" style="margin-top:18px">
      <p style="margin:0 0 10px">If you forgot your password, contact the main owner/admin to reset it from the protected admin panel.</p>
      <p style="margin:0">Owner-managed password reset is now the only supported recovery flow.</p>
    </div>

    <div class="admin-form-actions" style="margin-top:18px">
      <a class="admin-btn-secondary" href="login.php">Back to Login</a>
      <?php if (!empty($_SESSION['admin']) && (int) $_SESSION['admin'] === $ownerAdminId): ?>
        <a class="admin-btn-primary" href="reset_admin_password.php">Reset Admin Password</a>
      <?php endif; ?>
    </div>
  </main>

  <script src="../Script/theme.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/theme.js') ?: time()); ?>"></script>
</body>
</html>
