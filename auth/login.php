<?php
/**
 * Login Page — Lavenderia Laundry Services
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: ../modules/dashboard/index.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $conn->prepare(
            "SELECT id, username, password, full_name, role, branch_id, status
               FROM users WHERE username = ? LIMIT 1"
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = 'Invalid username or password.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Your account has been deactivated. Please contact the administrator.';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Invalid username or password.';
        } else {
            // Successful login
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['branch_id'] = $user['branch_id'];

            // Update last login
            $up = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $up->bind_param('i', $user['id']);
            $up->execute();
            $up->close();

            // Log
            $action = 'User logged in';
            $ip     = $_SERVER['REMOTE_ADDR'] ?? null;
            $uid    = $user['id'];
            $bid    = $user['branch_id'];
            $ins = $conn->prepare("INSERT INTO logs (branch_id,user_id,action,ip_address) VALUES (?,?,?,?)");
            $ins->bind_param('iiss', $bid, $uid, $action, $ip);
            $ins->execute();
            $ins->close();

            header('Location: ../modules/dashboard/index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Lavenderia Laundry Services</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/style.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #8A2BE2 0%, #6A0DAD 40%, #00CED1 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Segoe UI', sans-serif;
      overflow: hidden;
    }
    .bubbles {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      pointer-events: none; overflow: hidden; z-index: 0;
    }
    .bubble {
      position: absolute; bottom: -80px;
      border-radius: 50%;
      background: rgba(255,255,255,.12);
      animation: rise linear infinite;
    }
    @keyframes rise {
      0%   { transform: translateY(0) scale(1);   opacity: .7; }
      100% { transform: translateY(-110vh) scale(1.3); opacity: 0; }
    }
    .login-wrapper { position: relative; z-index: 1; width: 100%; max-width: 440px; padding: 1rem; }
    .login-card {
      background: rgba(255,255,255,.97);
      border-radius: 24px;
      padding: 2.5rem 2.2rem;
      box-shadow: 0 24px 64px rgba(0,0,0,.28);
    }
    .brand-logo { text-align: center; margin-bottom: 1.5rem; }
    .brand-logo img { height: 90px; }
    .brand-logo h4 { color: #8A2BE2; font-weight: 700; margin-top: .5rem; font-size: 1.15rem; }
    .brand-logo p  { color: #6c757d; font-size: .82rem; margin: 0; }
    .form-floating label { color: #8A2BE2; font-size: .9rem; }
    .form-floating .form-control:focus { border-color: #8A2BE2; box-shadow: 0 0 0 .2rem rgba(138,43,226,.2); }
    .btn-login {
      background: linear-gradient(135deg, #8A2BE2, #00CED1);
      border: none; color: #fff; border-radius: 50px;
      padding: .7rem; font-weight: 600; font-size: 1rem;
      transition: opacity .2s;
    }
    .btn-login:hover { opacity: .88; color: #fff; }
    .divider { text-align: center; color: #aaa; font-size: .8rem; margin: .8rem 0; }
    .default-creds { background: #F3E5F5; border-radius: 12px; padding: .8rem 1rem; font-size: .8rem; }
    .default-creds table td { padding: 1px 6px; }
    .input-group-text { background: #f8f4ff; border-color: #dee2e6; color: #8A2BE2; }
    .toggle-pw { cursor: pointer; }
  </style>
</head>
<body>

<!-- Animated bubbles -->
<div class="bubbles" id="bubblesContainer"></div>

<div class="login-wrapper">
  <div class="login-card">
    <div class="brand-logo">
      <img src="../assets/img/logo.png" alt="Lavenderia Logo" onerror="this.style.display='none'">
      <h4>Lavenderia Laundry Services</h4>
      <p>Management System</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center py-2" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <div><?= e($error) ?></div>
      </div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
      <div class="mb-3">
        <label class="form-label text-muted small fw-semibold"><i class="fas fa-user me-1"></i>Username</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-user"></i></span>
          <input type="text" name="username" class="form-control rounded-end"
                 placeholder="Enter username"
                 value="<?= e($_POST['username'] ?? '') ?>"
                 autocomplete="username" required>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label text-muted small fw-semibold"><i class="fas fa-lock me-1"></i>Password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-lock"></i></span>
          <input type="password" name="password" id="passInput" class="form-control"
                 placeholder="Enter password" autocomplete="current-password" required>
          <span class="input-group-text toggle-pw" onclick="togglePw()">
            <i class="fas fa-eye" id="eyeIcon"></i>
          </span>
        </div>
      </div>

      <div class="d-grid mt-4">
        <button type="submit" class="btn btn-login">
          <i class="fas fa-sign-in-alt me-2"></i>Sign In
        </button>
      </div>
    </form>

    <div class="divider">— default credentials —</div>
    <div class="default-creds">
      <table>
        <tr><td><strong>Owner</strong></td><td>owner</td><td>Owner@1234</td></tr>
        <tr><td><strong>Admin</strong></td><td>admin</td><td>Admin@1234</td></tr>
        <tr><td><strong>Staff</strong></td><td>staff1–staff6</td><td>Staff@1234</td></tr>
      </table>
    </div>
  </div>
</div>

<script>
function togglePw() {
  const el  = document.getElementById('passInput');
  const ico = document.getElementById('eyeIcon');
  if (el.type === 'password') { el.type = 'text'; ico.className = 'fas fa-eye-slash'; }
  else                        { el.type = 'password'; ico.className = 'fas fa-eye'; }
}

// Generate bubbles
(function() {
  const c = document.getElementById('bubblesContainer');
  for (let i = 0; i < 18; i++) {
    const b  = document.createElement('div');
    b.classList.add('bubble');
    const sz = 20 + Math.random() * 60;
    b.style.cssText = [
      `width:${sz}px`, `height:${sz}px`,
      `left:${Math.random()*100}%`,
      `animation-duration:${6 + Math.random()*10}s`,
      `animation-delay:${Math.random()*10}s`
    ].join(';');
    c.appendChild(b);
  }
})();
</script>
</body>
</html>
