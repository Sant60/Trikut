<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!empty($_SESSION['admin'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username and password are both required.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, username, password FROM admins WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Admin login query failed: ' . $e->getMessage());
            $admin = false;
        }

        if ($admin && isset($admin['password']) && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin'] = (int) $admin['id'];
            header('Location: dashboard.php');
            exit;
        }

        $error = 'Invalid username or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login - Trikut Restaurant</title>
  <link rel="stylesheet" href="../CSS/style.css">
</head>
<body class="admin-login-body">
  <main class="admin-login-card" role="main" aria-labelledby="loginTitle">
    <h1 id="loginTitle">Admin Login</h1>
    <p class="admin-subtitle">Sign in to manage menu items, gallery photos, bookings, and orders.</p>

    <?php if ($error): ?>
      <div class="admin-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <form method="post" id="adminLoginForm" novalidate>
      <div class="admin-field">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" autocomplete="username" required value="<?php echo htmlspecialchars($username, ENT_QUOTES); ?>">
      </div>

      <div class="admin-field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
      </div>

      <div class="admin-form-actions">
        <button class="admin-btn-primary" type="submit">Login</button>
        <div class="admin-muted">This page is protected.</div>
      </div>
    </form>
  </main>

  <script>
    (function () {
      const form = document.getElementById("adminLoginForm");
      const user = document.getElementById("username");
      const pass = document.getElementById("password");

      form.addEventListener("submit", function (event) {
        [user, pass].forEach(function (input) {
          input.style.borderColor = "";
          const next = input.nextElementSibling;
          if (next && next.classList.contains("field-error")) {
            next.remove();
          }
        });

        let valid = true;
        if (!user.value.trim()) {
          showFieldError(user, "Username is required.");
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
        error.className = "admin-error";
        error.textContent = message;
        input.insertAdjacentElement("afterend", error);
        input.style.borderColor = "#f3c2c2";
      }
    })();
  </script>
</body>
</html>
