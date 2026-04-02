<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/admin_profile.php';
require_once __DIR__ . '/../includes/site.php';
require_once __DIR__ . '/../includes/tenant.php';

ensure_admin_profile_schema($pdo);
ensure_multi_admin_schema($pdo);
$isOwnerAdmin = is_owner_admin($pdo, (int) $_SESSION['admin']);
$currentRole = admin_role($pdo, (int) $_SESSION['admin']);

$errors = [];
$success = '';
$projectRoot = dirname(__DIR__);
$adminProfile = fetch_admin_profile($pdo, (int) $_SESSION['admin']);

if (!$adminProfile) {
    http_response_code(404);
    exit('Admin profile not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_request()) {
        $errors[] = 'Invalid request token.';
    } else {
        $validated = validate_admin_profile_payload($_POST);
        $errors = array_merge($errors, $validated['errors']);

        $username = $validated['username'];
        $displayName = $validated['display_name'];
        $mobile = $validated['mobile'];
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($newPassword !== '' || $confirmPassword !== '') {
            if ($currentPassword === '') {
                $errors[] = 'Current password is required to set a new password.';
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = 'New password and confirmation must match.';
            }

            $passwordError = validate_new_password($newPassword);
            if ($passwordError !== null) {
                $errors[] = $passwordError;
            }
        }

        $photoPath = normalize_storage_path($adminProfile['photo'] ?? '');
        $uploadedPhoto = upload_image_file($_FILES['photo'] ?? [], admin_photo_target_dir(), admin_photo_public_dir(), 'admin', $errors);
        if ($uploadedPhoto !== null) {
            $photoPath = $uploadedPhoto;
        }

        try {
            $stmt = $pdo->prepare('SELECT id FROM admins WHERE (username = ? OR mobile = ?) AND id != ? LIMIT 1');
            $stmt->execute([$username, $mobile ?: null, (int) $_SESSION['admin']]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = 'That username or mobile number is already in use.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Could not validate the requested username or mobile number.';
        }

        $storedPasswordHash = '';
        try {
            $stmt = $pdo->prepare('SELECT password FROM admins WHERE id = ? LIMIT 1');
            $stmt->execute([(int) $_SESSION['admin']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $storedPasswordHash = (string) ($row['password'] ?? '');
        } catch (Throwable $e) {
            $errors[] = 'Could not verify the current password.';
        }

        if ($newPassword !== '' && $storedPasswordHash !== '' && !password_verify($currentPassword, $storedPasswordHash)) {
            $errors[] = 'Current password is incorrect.';
        }

        if (empty($errors)) {
            try {
                $params = [$username, $displayName, $mobile ?: null, $photoPath ?: null];
                $sql = 'UPDATE admins SET username = ?, display_name = ?, mobile = ?, photo = ?';

                if ($newPassword !== '') {
                    $sql .= ', password = ?';
                    $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
                }

                $sql .= ' WHERE id = ? LIMIT 1';
                $params[] = (int) $_SESSION['admin'];

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                if ($uploadedPhoto !== null && !empty($adminProfile['photo']) && $adminProfile['photo'] !== $uploadedPhoto) {
                    remove_local_project_file($adminProfile['photo'], $projectRoot);
                }

                $success = 'Profile updated successfully.';
                $adminProfile = fetch_admin_profile($pdo, (int) $_SESSION['admin']) ?: $adminProfile;
            } catch (Throwable $e) {
                if ($uploadedPhoto !== null) {
                    remove_local_project_file($uploadedPhoto, $projectRoot);
                }
                $errors[] = 'Could not update the admin profile.';
            }
        } elseif ($uploadedPhoto !== null) {
            remove_local_project_file($uploadedPhoto, $projectRoot);
        }
    }
}

$adminName = $adminProfile['display_name'] ?: ($adminProfile['username'] ?? 'Admin');
$adminPhotoUrl = !empty($adminProfile['photo']) ? app_url($adminProfile['photo']) : '';
$publicProfileLink = app_url('index.php');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Profile - Trikut</title>
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
  <div class="admin-shell">
    <aside class="admin-sidebar" aria-label="Admin menu">
      <div class="admin-brand">Trikut - Admin</div>
      <button class="theme-toggle admin-theme-toggle" type="button" data-theme-toggle data-theme-storage="admin-theme" aria-pressed="false" aria-label="Switch to dark mode" title="Toggle admin light and dark mode">
        <i class="fa-solid fa-toggle-off" aria-hidden="true"></i>
        <i class="fa-solid fa-toggle-on" aria-hidden="true"></i>
      </button>
      <a class="admin-profile-card" href="profile.php">
        <?php if ($adminPhotoUrl !== ''): ?>
          <img class="admin-profile-photo" src="<?php echo htmlspecialchars($adminPhotoUrl, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($adminName, ENT_QUOTES); ?>">
        <?php else: ?>
          <div class="admin-profile-fallback"><?php echo htmlspecialchars(strtoupper(substr((string) $adminName, 0, 1)), ENT_QUOTES); ?></div>
        <?php endif; ?>
        <div class="admin-profile-copy">
          <strong><?php echo htmlspecialchars($adminName, ENT_QUOTES); ?></strong>
          <span class="admin-muted"><?php echo htmlspecialchars($adminProfile['mobile'] ?: 'No mobile set', ENT_QUOTES); ?></span>
        </div>
      </a>
      <nav class="admin-nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="menu.php">Menu</a>
        <a href="hero.php">Hero Image</a>
        <a href="gallery.php">Gallery</a>
        <a href="orders.php">Orders</a>
        <a href="bookings.php">Bookings</a>
        <?php if ($isOwnerAdmin): ?>
          <a href="register.php">Add Admin</a>
          <a href="manage_admins.php">Manage Admins</a>
          <a href="reset_admin_password.php">Reset Admin Password</a>
        <?php endif; ?>
        <a href="logout.php">Logout</a>
      </nav>
      <div style="margin-top:auto" class="admin-meta">Server Time: <?php echo date('Y-m-d H:i'); ?></div>
    </aside>

    <main class="admin-main">
      <header class="admin-header">
        <div>
          <h1 class="admin-heading">Admin Profile</h1>
          <div class="admin-subtitle">View your username and mobile number, update your profile photo, and change your password securely.</div>
        </div>
      </header>

      <section class="admin-card admin-profile-panel" id="profileEditorCard">
        <div class="admin-profile-panel-head">
          <div>
            <h3 style="margin:0 0 6px">Profile Details</h3>
            <div class="admin-subtitle">View your current profile information and switch into edit mode when you want to update it.</div>
          </div>
          <button class="admin-btn-secondary" id="profileEditToggle" type="button" aria-expanded="false">Edit</button>
        </div>

        <div class="admin-profile-summary">
          <div class="admin-profile-row">
            <div class="admin-profile-label">Username</div>
            <div class="admin-profile-value" data-profile-preview="username"><?php echo htmlspecialchars($adminProfile['username'] ?? '-', ENT_QUOTES); ?></div>
          </div>
          <div class="admin-profile-row">
            <div class="admin-profile-label">Display Name</div>
            <div class="admin-profile-value" data-profile-preview="display_name"><?php echo htmlspecialchars($adminProfile['display_name'] ?? '-', ENT_QUOTES); ?></div>
          </div>
          <div class="admin-profile-row">
            <div class="admin-profile-label">Mobile</div>
            <div class="admin-profile-value" data-profile-preview="mobile"><?php echo htmlspecialchars($adminProfile['mobile'] ?: 'Not set', ENT_QUOTES); ?></div>
          </div>
          <div class="admin-profile-row">
            <div class="admin-profile-label">Access Role</div>
            <div class="admin-profile-value"><?php echo htmlspecialchars($isOwnerAdmin ? 'Main Owner' : admin_role_label($currentRole), ENT_QUOTES); ?></div>
          </div>
          <div class="admin-profile-row">
            <div class="admin-profile-label">Website</div>
            <div class="admin-profile-value"><?php echo htmlspecialchars(site_restaurant_name(), ENT_QUOTES); ?></div>
          </div>
          <div class="admin-profile-row">
            <div class="admin-profile-label">Public Link</div>
            <div class="admin-profile-value"><a href="<?php echo htmlspecialchars($publicProfileLink, ENT_QUOTES); ?>" target="_blank" rel="noopener" data-profile-preview="public_link"><?php echo htmlspecialchars($publicProfileLink, ENT_QUOTES); ?></a></div>
          </div>
          <div class="admin-profile-row">
            <div class="admin-profile-label">Password</div>
            <div class="admin-profile-value">Hidden for security. Use edit mode below to change it.</div>
          </div>
        </div>

        <div class="admin-profile-edit" id="profileEditFields">
        <?php if ($success !== ''): ?>
          <div class="notice success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
          <div class="admin-error">
            <?php foreach ($errors as $error): ?>
              <?php echo htmlspecialchars($error, ENT_QUOTES); ?><br>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" novalidate data-validate-form>
          <?php echo csrf_input(); ?>
          <div class="admin-form-grid">
            <div class="admin-field">
              <label>Display Name</label>
              <input name="display_name" data-rule="name" maxlength="120" required data-profile-input="display_name" value="<?php echo htmlspecialchars($_POST['display_name'] ?? ($adminProfile['display_name'] ?? ''), ENT_QUOTES); ?>">
            </div>
            <div class="admin-field">
              <label>Username</label>
              <input name="username" data-rule="username" maxlength="100" required data-profile-input="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ($adminProfile['username'] ?? ''), ENT_QUOTES); ?>">
            </div>
            <div class="admin-field">
              <label>Mobile Number</label>
              <input name="mobile" type="tel" data-rule="phone" inputmode="numeric" maxlength="13" placeholder="+91 9876543210" data-profile-input="mobile" value="<?php echo htmlspecialchars($_POST['mobile'] ?? ($adminProfile['mobile'] ?? ''), ENT_QUOTES); ?>">
            </div>
            <div class="admin-field">
              <label>Profile Photo</label>
              <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
              <small>Upload a square photo for the sidebar identity.</small>
            </div>
            <div class="admin-field">
              <label>Current Password</label>
              <input name="current_password" type="password" autocomplete="current-password">
            </div>
            <div class="admin-field">
              <label>New Password</label>
              <input name="new_password" type="password" data-rule="password" minlength="8" autocomplete="new-password">
            </div>
            <div class="admin-field">
              <label>Confirm New Password</label>
              <input name="confirm_password" id="profile_confirm_password" type="password" data-rule="password" data-match="new_password" minlength="8" autocomplete="new-password">
            </div>
          </div>
          <div class="admin-form-actions" style="margin-top:14px">
            <button class="admin-btn-primary" type="submit">Save Profile</button>
          </div>
        </form>
        </div>
      </section>
    </main>
  </div>
  <script src="../Script/theme.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/theme.js') ?: time()); ?>"></script>
  <script src="../Script/form-validation.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../Script/form-validation.js') ?: time()); ?>"></script>
  <script>
    (function () {
      const profileCard = document.getElementById("profileEditorCard");
      const editToggle = document.getElementById("profileEditToggle");
      const editFields = document.getElementById("profileEditFields");

      if (!profileCard || !editToggle || !editFields) {
        return;
      }

      const startEditing = <?php echo (!empty($errors) || $success !== '') ? 'true' : 'false'; ?>;

      function setEditing(isEditing) {
        profileCard.classList.toggle("is-editing", isEditing);
        editToggle.setAttribute("aria-expanded", String(isEditing));
        editToggle.textContent = isEditing ? "Cancel" : "Edit";
      }

      setEditing(startEditing);

      editToggle.addEventListener("click", () => {
        const isEditing = editToggle.getAttribute("aria-expanded") === "true";
        setEditing(!isEditing);
      });

      const inputs = document.querySelectorAll("[data-profile-input]");
      inputs.forEach((input) => {
        input.addEventListener("input", () => {
          const key = input.getAttribute("data-profile-input");
          const preview = document.querySelector(`[data-profile-preview="${key}"]`);
          if (!preview) return;

          const value = input.value.trim();
          preview.textContent = value || (key === "mobile" ? "Not set" : "-");
        });
      });
    })();
  </script>
</body>
</html>
