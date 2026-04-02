<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../includes/security.php';

ensure_customer_schema($pdo);

if (customer_is_logged_in()) {
    header('Location: ' . customer_profile_url());
    exit;
}

$name = '';
$email = '';
$mobile = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = normalize_text($_POST['name'] ?? '', 120);
    $email = normalize_email($_POST['email'] ?? '');
    $mobile = trim((string) ($_POST['mobile'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!verify_csrf_request()) {
        $error = 'Your session token is invalid. Please try again.';
    } else {
        $result = create_customer_account($pdo, $name, $email, $mobile, $password, $confirmPassword);
        if (!empty($result['errors'])) {
            $error = implode(' ', $result['errors']);
        } else {
            $customer = current_customer($pdo);
            $createdCustomer = find_customer_by_identifier($pdo, $email);
            if ($createdCustomer) {
                customer_login($createdCustomer);
            }
            header('Location: ' . customer_profile_url());
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customer Sign Up - Trikut</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
  <link rel="stylesheet" href="../CSS/style.css?v=<?php echo (int) (@filemtime(__DIR__ . '/../CSS/style.css') ?: time()); ?>">
</head>
<body class="admin-login-body">
  <main class="admin-login-card" role="main">
    <h1>Create Customer Account</h1>
    <p class="admin-subtitle">Sign up once and keep your restaurant orders and bookings in one profile.</p>
    <?php if ($error !== ''): ?>
      <div class="admin-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
    <?php endif; ?>
    <form method="post" novalidate data-validate-form>
      <?php echo csrf_input(); ?>
      <div class="admin-field">
        <label for="customerName">Full Name</label>
        <input id="customerName" name="name" data-rule="name" required value="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>">
      </div>
      <div class="admin-field">
        <label for="customerEmail">Email</label>
        <input id="customerEmail" name="email" type="email" required value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>">
      </div>
      <div class="admin-field">
        <label for="customerMobile">Mobile Number</label>
        <input id="customerMobile" name="mobile" type="tel" data-rule="phone" inputmode="numeric" maxlength="13" placeholder="+91 9876543210" required value="<?php echo htmlspecialchars($mobile, ENT_QUOTES); ?>">
      </div>
      <div class="admin-field">
        <label for="customerPassword">Password</label>
        <input id="customerPassword" name="password" type="password" autocomplete="new-password" required>
      </div>
      <div class="admin-field">
        <label for="customerPasswordConfirm">Confirm Password</label>
        <input id="customerPasswordConfirm" name="confirm_password" type="password" autocomplete="new-password" required>
      </div>
      <div class="admin-form-actions">
        <button class="admin-btn-primary" type="submit">Create Account</button>
        <a class="admin-btn-secondary" href="login.php">Login</a>
      </div>
    </form>
  </main>
  <script src="../Script/form-validation.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/form-validation.js') ?: time()); ?>"></script>
</body>
</html>
