<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/admin_profile.php';

ensure_admin_profile_schema($pdo);

$error = '';
$success = '';
$username = '';
$mobile = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = normalize_text($_POST['username'] ?? '', 100);
    $mobile = trim((string) ($_POST['mobile'] ?? ''));
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!verify_csrf_request()) {
        $error = 'Your session token is invalid. Please try again.';
    } elseif ($username === '' || $mobile === '' || $newPassword === '' || $confirmPassword === '') {
        $error = 'All fields are required.';
    } elseif (!is_valid_indian_phone($mobile)) {
        $error = 'Enter a valid registered mobile number.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Password confirmation does not match.';
    } else {
        $passwordError = validate_new_password($newPassword);
        if ($passwordError !== null) {
            $error = $passwordError;
        } else {
            try {
                $stmt = $pdo->prepare('SELECT id, mobile FROM admins WHERE username = ? LIMIT 1');
                $stmt->execute([$username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                $normalizedMobile = normalize_indian_phone($mobile);
                $storedMobile = normalize_indian_phone((string) ($admin['mobile'] ?? ''));

                if (!$admin || $storedMobile === null || $storedMobile !== $normalizedMobile) {
                    $error = 'Username and mobile number do not match our records.';
                } else {
                    $stmt = $pdo->prepare('UPDATE admins SET password = ? WHERE id = ? LIMIT 1');
                    $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $admin['id']]);
                    $success = 'Password updated successfully. You can now log in.';
                }
            } catch (Throwable $e) {
                $error = 'Could not reset the password right now.';
            }
        }
    }
}
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
    <h1 id="recoveryTitle">Recover Admin Access</h1>
    <p class="admin-subtitle">Use the current username and registered mobile number to reset the password.</p>

    <?php if ($error !== ''): ?>
      <div class="admin-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
      <div class="notice success" role="status"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <form method="post" novalidate data-validate-form>
      <?php echo csrf_input(); ?>
      <div class="admin-field">
        <label for="recoveryUsername">Username</label>
        <input id="recoveryUsername" name="username" required value="<?php echo htmlspecialchars($username, ENT_QUOTES); ?>">
      </div>

      <div class="admin-field">
        <label for="recoveryMobile">Registered Mobile Number</label>
        <input id="recoveryMobile" name="mobile" type="tel" data-rule="phone" inputmode="numeric" maxlength="13" placeholder="+91 9876543210" required value="<?php echo htmlspecialchars($mobile, ENT_QUOTES); ?>">
      </div>

      <div class="admin-field">
        <label for="recoveryPassword">New Password</label>
        <input id="recoveryPassword" name="new_password" type="password" autocomplete="new-password" required>
      </div>

      <div class="admin-field">
        <label for="recoveryPasswordConfirm">Confirm New Password</label>
        <input id="recoveryPasswordConfirm" name="confirm_password" type="password" autocomplete="new-password" required>
      </div>

      <div class="admin-form-actions">
        <button class="admin-btn-primary" type="submit">Reset Password</button>
        <a class="admin-btn-secondary" href="login.php">Back to Login</a>
      </div>
    </form>
  </main>

  <script src="../Script/theme.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/theme.js') ?: time()); ?>"></script>
  <script src="../Script/form-validation.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/form-validation.js') ?: time()); ?>"></script>
</body>
</html>
