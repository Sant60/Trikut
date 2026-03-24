<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/tenant.php';

if (!empty($_SESSION['admin'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = normalize_text($_POST['identifier'] ?? '', 100);
    $password = $_POST['password'] ?? '';

    if (!verify_csrf_request()) {
        $error = 'Your session token is invalid. Please try again.';
    } elseif ($identifier === '' || $password === '') {
        $error = 'Mobile number or username and password are both required.';
    } else {
        try {
            $admin = find_admin_by_identifier($pdo, $identifier);
        } catch (Throwable $e) {
            error_log('Admin login query failed: ' . $e->getMessage());
            $admin = false;
        }

        if ($admin && isset($admin['password']) && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin'] = (int) $admin['id'];
            $_SESSION['public_admin_id'] = (int) $admin['id'];
            header('Location: dashboard.php');
            exit;
        }

        $error = 'Invalid mobile number, username, or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login - Trikut Restaurant</title>
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
  <main class="admin-login-card" role="main" aria-labelledby="loginTitle">
    <button class="theme-toggle admin-theme-toggle" type="button" data-theme-toggle data-theme-storage="admin-theme" aria-pressed="false" aria-label="Switch to dark mode" title="Toggle admin light and dark mode">
      <i class="fa-solid fa-toggle-off" aria-hidden="true"></i>
      <i class="fa-solid fa-toggle-on" aria-hidden="true"></i>
    </button>
    <h1 id="loginTitle">Admin Login</h1>
    <p class="admin-subtitle">Sign in with your username or registered mobile number to manage your own menu, gallery, bookings, and orders.</p>

    <?php if ($error): ?>
      <div class="admin-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <form method="post" id="adminLoginForm" novalidate data-validate-form>
      <?php echo csrf_input(); ?>
      <div class="admin-field">
        <label for="identifier">Username or Mobile Number</label>
        <input id="identifier" name="identifier" type="text" autocomplete="username" required value="<?php echo htmlspecialchars($identifier, ENT_QUOTES); ?>">
      </div>

      <div class="admin-field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
      </div>

      <div class="admin-form-actions">
        <button class="admin-btn-primary" type="submit">Login</button>
        <div class="admin-muted">This page is protected.</div>
      </div>
      <div style="margin-top:12px">
        <a class="admin-back-link" href="forgot_password.php">Forgot password?</a>
      </div>
    </form>
  </main>

  <script src="../Script/theme.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/theme.js') ?: time()); ?>"></script>
  <script src="../Script/form-validation.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/form-validation.js') ?: time()); ?>"></script>
  <script>
    (function () {
      const form = document.getElementById("adminLoginForm");
      const user = document.getElementById("identifier");
      const pass = document.getElementById("password");

      form.addEventListener("submit", function (event) {
        [user, pass].forEach(function (input) {
          input.style.borderColor = "";
          input.removeAttribute("aria-invalid");
          input.removeAttribute("aria-describedby");
          const next = input.nextElementSibling;
          if (next && next.classList.contains("field-error")) {
            next.remove();
          }
        });

        let valid = true;
        if (!user.value.trim()) {
          showFieldError(user, "Username or mobile number is required.");
          valid = false;
        }
        if (!pass.value) {
          showFieldError(pass, "Password is required.");
          valid = false;
        }

        if (!valid) {
          event.preventDefault();
        }
      });

      function showFieldError(input, message) {
        const error = document.createElement("div");
        error.className = "admin-error field-error";
        error.id = input.id + "-error";
        error.setAttribute("role", "alert");
        error.textContent = message;
        input.insertAdjacentElement("afterend", error);
        input.style.borderColor = "#f3c2c2";
        input.setAttribute("aria-invalid", "true");
        input.setAttribute("aria-describedby", error.id);
      }
    })();
  </script>
</body>
</html>
