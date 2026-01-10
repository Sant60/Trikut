<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// If already logged in, redirect to dashboard
if (!empty($_SESSION['admin'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Username और Password दोनों आवश्यक हैं।";
    } else {
        try {
            $q = $pdo->prepare("SELECT id, username, password FROM admins WHERE username = ? LIMIT 1");
            $q->execute([$username]);
            $u = $q->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log("Admin login query failed: " . $e->getMessage());
            $u = false;
        }

        if ($u && isset($u['password']) && password_verify($password, $u['password'])) {
            // successful login
            session_regenerate_id(true);
            $_SESSION['admin'] = (int)$u['id'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "गलत username या password।";
        }
    }
}
?>
<!doctype html>
<html lang="hi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin Login — Trikut Restaurant</title>
  <style>
    :root{--bg:#f6f6f8;--card:#fff;--accent:#c94f2c;--muted:#666}
    *{box-sizing:border-box}
    body{margin:0;font-family:Inter,system-ui,Segoe UI,Roboto,Arial;background:var(--bg);color:#222;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .login-card{width:100%;max-width:420px;background:var(--card);padding:28px;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.08)}
    h1{margin:0 0 8px;font-size:20px}
    p.lead{margin:0 0 18px;color:var(--muted);font-size:14px}
    .field{margin-bottom:12px}
    .field label{display:block;font-size:13px;margin-bottom:6px;color:#333}
    .field input{width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:14px}
    .btn{display:inline-block;background:var(--accent);color:#fff;padding:10px 14px;border-radius:8px;border:0;cursor:pointer;font-weight:600}
    .error{background:#fff4f4;border:1px solid #f3c2c2;color:#b93b3b;padding:8px;border-radius:8px;margin-bottom:12px;font-size:14px}
    .muted{color:var(--muted);font-size:13px;margin-top:8px}
    @media(max-width:460px){.login-card{padding:18px}}
  </style>
</head>
<body>
  <main class="login-card" role="main" aria-labelledby="loginTitle">
    <h1 id="loginTitle">Admin Login</h1>
    <p class="lead">Trikut Restaurant — Admin panel में प्रवेश करें</p>

    <?php if ($error): ?>
      <div class="error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <form method="post" id="adminLoginForm" novalidate>
      <div class="field">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" autocomplete="username" required value="<?php echo isset($username) ? htmlspecialchars($username, ENT_QUOTES) : ''; ?>">
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
      </div>

      <div style="display:flex;gap:10px;align-items:center">
        <button class="btn" type="submit">Login</button>
        <div class="muted">यह पृष्ठ सुरक्षित है।</div>
      </div>
    </form>
  </main>

  <script>
    (function(){
      const form = document.getElementById('adminLoginForm');
      form.addEventListener('submit', function(e){
        const user = document.getElementById('username');
        const pass = document.getElementById('password');
        [user,pass].forEach(i => {
          const next = i.nextElementSibling;
          if(next && next.classList && next.classList.contains('field-error')) next.remove();
          i.style.borderColor = '';
        });

        let ok = true;
        if(!user.value.trim()){ showFieldError(user, 'Username आवश्यक है'); ok = false; }
        if(!pass.value){ showFieldError(pass, 'Password आवश्यक है'); ok = false; }

        if(!ok){ e.preventDefault(); }
      });

      function showFieldError(el, msg){
        const d = document.createElement('div');
        d.className = 'field-error';
        d.style.color = '#b93b3b';
        d.style.marginTop = '6px';
        d.style.fontSize = '13px';
        d.textContent = msg;
        el.parentNode.insertBefore(d, el.nextSibling);
        el.style.borderColor = '#f3c2c2';
      }
    })();
  </script>
</body>
</html>