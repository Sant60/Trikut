<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../includes/security.php';

ensure_customer_schema($pdo);

if (customer_is_logged_in()) {
    header('Location: ' . customer_profile_url());
    exit;
}

$identifier = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = normalize_text($_POST['identifier'] ?? '', 190);
    $password = (string) ($_POST['password'] ?? '');

    if (!verify_csrf_request()) {
        $error = 'Your session token is invalid. Please try again.';
    } elseif ($identifier === '' || $password === '') {
        $error = 'Email or mobile number and password are both required.';
    } else {
        $customer = find_customer_by_identifier($pdo, $identifier);
        if ($customer && (int) ($customer['is_active'] ?? 1) !== 1) {
            $error = 'This customer account is inactive.';
        } elseif ($customer && password_verify($password, (string) ($customer['password'] ?? ''))) {
            customer_login($customer);
            try {
                $stmt = $pdo->prepare('UPDATE customer_users SET last_login_at = NOW() WHERE id = ? LIMIT 1');
                $stmt->execute([(int) $customer['id']]);
            } catch (Throwable $e) {
            }
            header('Location: ' . customer_profile_url());
            exit;
        } else {
            $error = 'Invalid email, mobile number, or password.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customer Login - Trikut</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
  <link rel="stylesheet" href="../CSS/style.css?v=<?php echo (int) (@filemtime(__DIR__ . '/../CSS/style.css') ?: time()); ?>">
</head>
<body class="admin-login-body">
  <main class="admin-login-card" role="main">
    <h1>Customer Login</h1>
    <p class="admin-subtitle">Sign in to see your restaurant profile, orders, and bookings.</p>
    <?php if ($error !== ''): ?>
      <div class="admin-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
    <?php endif; ?>
    <form method="post" novalidate data-validate-form>
      <?php echo csrf_input(); ?>
      <div class="admin-field">
        <label for="customerIdentifier">Email or Mobile Number</label>
        <input id="customerIdentifier" name="identifier" required value="<?php echo htmlspecialchars($identifier, ENT_QUOTES); ?>">
      </div>
      <div class="admin-field">
        <label for="customerLoginPassword">Password</label>
        <input id="customerLoginPassword" name="password" type="password" autocomplete="current-password" required>
      </div>
      <div class="admin-form-actions">
        <button class="admin-btn-primary" type="submit">Login</button>
        <a class="admin-btn-secondary" href="register.php">Sign Up</a>
      </div>
    </form>
  </main>
  <script src="../Script/form-validation.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/form-validation.js') ?: time()); ?>"></script>
</body>
</html>
