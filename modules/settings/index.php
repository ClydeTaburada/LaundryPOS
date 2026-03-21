<?php
/**
 * User Settings — Change password and profile info
 */
require_once '../../config/database.php';
require_once '../../config/functions.php';
requireAuth();

$msg     = '';
$msgType = 'success';
$action  = $_POST['action'] ?? '';

// Load current user
$uid  = (int)$_SESSION['user_id'];
$user = $conn->query("SELECT u.*,b.name AS branch_name FROM users u LEFT JOIN branches b ON b.id=u.branch_id WHERE u.id=$uid")->fetch_assoc();

if ($action === 'update_profile') {
    $name  = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email']     ?? '');
    $phone = trim($_POST['phone']     ?? '');
    if ($name) {
        $stmt = $conn->prepare("UPDATE users SET full_name=?,email=?,phone=? WHERE id=?");
        $stmt->bind_param('sssi', $name, $email, $phone, $uid);
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $name;
            logAction("Updated profile", 'users', $uid);
            $msg = 'Profile updated successfully.';
            $user['full_name'] = $name;
            $user['email']     = $email;
            $user['phone']     = $phone;
        } else {
            $msg = 'Error: ' . $stmt->error; $msgType = 'danger';
        }
        $stmt->close();
    } else {
        $msg = 'Full name is required.'; $msgType = 'warning';
    }
}

if ($action === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new1    = $_POST['new_password']     ?? '';
    $new2    = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password'])) {
        $msg = 'Current password is incorrect.'; $msgType = 'danger';
    } elseif (strlen($new1) < 6) {
        $msg = 'New password must be at least 6 characters.'; $msgType = 'warning';
    } elseif ($new1 !== $new2) {
        $msg = 'Passwords do not match.'; $msgType = 'danger';
    } else {
        $hash = password_hash($new1, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param('si', $hash, $uid);
        if ($stmt->execute()) {
            logAction("Changed password", 'users', $uid);
            $msg = 'Password changed successfully.';
        } else {
            $msg = 'Error: ' . $stmt->error; $msgType = 'danger';
        }
        $stmt->close();
    }
}

$pageTitle = 'Settings';
$navTitle  = 'My Settings';
require_once '../../includes/head.php';
?>

<div class="app-layout">
<?php require_once '../../includes/sidebar.php'; ?>
<div class="main-content">
<?php require_once '../../includes/navbar.php'; ?>
<div class="page-content">

  <div class="page-header">
    <div>
      <h4><i class="fas fa-gear text-purple me-2"></i>My Settings</h4>
      <nav><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Settings</li>
      </ol></nav>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible auto-dismiss">
      <i class="fas fa-check-circle me-2"></i><?= e($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Profile card -->
    <div class="col-xl-4">
      <div class="card text-center p-4">
        <div class="user-avatar-lg mx-auto mb-3"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
        <h5 class="fw-700 mb-0"><?= e($user['full_name']) ?></h5>
        <p class="text-muted mb-0"><?= e($user['username']) ?></p>
        <span class="badge rounded-pill mt-2 <?= $user['role']==='owner'?'stat-purple':($user['role']==='admin'?'bg-warning text-dark':'bg-light text-dark border') ?>">
          <?= ucfirst($user['role']) ?>
        </span>
        <hr>
        <div class="text-start small">
          <div class="mb-2"><i class="fas fa-store text-muted me-2"></i><?= e($user['branch_name'] ?? 'All Branches') ?></div>
          <div class="mb-2"><i class="fas fa-envelope text-muted me-2"></i><?= e($user['email'] ?? '—') ?></div>
          <div class="mb-2"><i class="fas fa-phone text-muted me-2"></i><?= e($user['phone'] ?? '—') ?></div>
          <div class="mb-2"><i class="fas fa-calendar text-muted me-2"></i>Member since <?= date('M Y', strtotime($user['created_at'])) ?></div>
          <div><i class="fas fa-clock text-muted me-2"></i>Last login: <?= $user['last_login'] ? date('M d, Y h:i A', strtotime($user['last_login'])) : 'N/A' ?></div>
        </div>
      </div>
    </div>

    <div class="col-xl-8">
      <!-- Update Profile -->
      <div class="card mb-4">
        <div class="card-header">
          <h6 class="mb-0"><i class="fas fa-user-edit text-purple me-2"></i>Update Profile</h6>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Username <span class="text-muted small">(cannot change)</span></label>
                <input type="text" class="form-control" value="<?= e($user['username']) ?>" disabled>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($user['email'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" placeholder="09XX XXX XXXX">
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary-grad btn-sm">
                  <i class="fas fa-save me-1"></i>Save Profile
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Change Password -->
      <div class="card">
        <div class="card-header">
          <h6 class="mb-0"><i class="fas fa-lock text-purple me-2"></i>Change Password</h6>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="row g-3">
              <div class="col-md-12">
                <label class="form-label">Current Password *</label>
                <div class="input-group">
                  <input type="password" name="current_password" id="curPw" class="form-control" required>
                  <button class="btn btn-outline-secondary" type="button" onclick="togglePw('curPw',this)">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">New Password *</label>
                <div class="input-group">
                  <input type="password" name="new_password" id="newPw" class="form-control" required minlength="6" placeholder="Min 6 characters">
                  <button class="btn btn-outline-secondary" type="button" onclick="togglePw('newPw',this)">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Confirm New Password *</label>
                <div class="input-group">
                  <input type="password" name="confirm_password" id="cnfPw" class="form-control" required placeholder="Repeat new password">
                  <button class="btn btn-outline-secondary" type="button" onclick="togglePw('cnfPw',this)">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-danger btn-sm">
                  <i class="fas fa-key me-1"></i>Change Password
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

</div>
<?php require_once '../../includes/footer.php'; ?>
<script>
function togglePw(id, btn) {
    const el = document.getElementById(id);
    const showing = el.type === 'text';
    el.type = showing ? 'password' : 'text';
    btn.innerHTML = showing ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
}
</script>
