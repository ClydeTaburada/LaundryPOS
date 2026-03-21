<?php
/**
 * Staff Management — Admin / Owner Only
 */
require_once '../../config/database.php';
require_once '../../config/functions.php';
requireAuth(['owner', 'admin']);

$branches = getBranches();
$msg      = '';
$msgType  = 'success';
$action   = $_POST['action'] ?? '';

if ($action === 'create') {
    $bid      = (int)($_POST['branch_id'] ?? 0) ?: null;
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $name     = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $role     = $conn->real_escape_string($_POST['role'] ?? 'staff');

    if ($username && $password && $name) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (branch_id,username,password,full_name,email,phone,role) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('issssss', $bid, $username, $hash, $name, $email, $phone, $role);
        if ($stmt->execute()) { logAction("Created user: $username ($role)", 'users', $conn->insert_id); $msg = "Staff account \"$username\" created."; }
        else { $msg = 'Error: ' . ($conn->error ?: $stmt->error); $msgType = 'danger'; }
        $stmt->close();
    } else {
        $msg = 'Name, username, and password are required.'; $msgType = 'warning';
    }
}

if ($action === 'update') {
    $uid      = (int)$_POST['id'];
    $bid      = (int)($_POST['branch_id'] ?? 0) ?: null;
    $name     = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $role     = $conn->real_escape_string($_POST['role'] ?? 'staff');
    $status   = $conn->real_escape_string($_POST['status'] ?? 'active');
    $newPass  = trim($_POST['new_password'] ?? '');

    if ($uid && $name) {
        if ($newPass) {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET branch_id=?,full_name=?,email=?,phone=?,role=?,status=?,password=? WHERE id=?");
            $stmt->bind_param('issssssi', $bid, $name, $email, $phone, $role, $status, $hash, $uid);
        } else {
            $stmt = $conn->prepare("UPDATE users SET branch_id=?,full_name=?,email=?,phone=?,role=?,status=? WHERE id=?");
            $stmt->bind_param('isssssi', $bid, $name, $email, $phone, $role, $status, $uid);
        }
        if ($stmt->execute()) { logAction("Updated user #$uid: $name", 'users', $uid); $msg = "User updated."; }
        else { $msg = 'Error: ' . $stmt->error; $msgType = 'danger'; }
        $stmt->close();
    }
}

if (isset($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];
    $cur = $conn->query("SELECT status FROM users WHERE id=$uid")->fetch_assoc()['status'];
    $new = $cur === 'active' ? 'inactive' : 'active';
    $conn->query("UPDATE users SET status='$new' WHERE id=$uid");
    logAction("Toggled user #$uid status to $new", 'users', $uid);
    header('Location: index.php'); exit;
}

$users = $conn->query("
    SELECT u.*,
           b.name AS branch_name,
           (SELECT COUNT(*) FROM orders o WHERE o.staff_id=u.id AND DATE(o.created_at)=CURDATE()) AS today_orders,
           (SELECT COUNT(*) FROM orders o WHERE o.staff_id=u.id) AS total_orders
    FROM users u
    LEFT JOIN branches b ON b.id=u.branch_id
    ORDER BY u.role, u.full_name
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Staff';
$navTitle  = 'Staff Management';
require_once '../../includes/head.php';
?>

<div class="app-layout">
<?php require_once '../../includes/sidebar.php'; ?>
<div class="main-content">
<?php require_once '../../includes/navbar.php'; ?>
<div class="page-content">

  <div class="page-header">
    <div>
      <h4><i class="fas fa-id-badge text-purple me-2"></i>Staff Management</h4>
      <nav><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Staff</li>
      </ol></nav>
    </div>
    <button class="btn btn-primary-grad" data-bs-toggle="modal" data-bs-target="#staffModal">
      <i class="fas fa-user-plus me-1"></i>Add Staff
    </button>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible auto-dismiss">
      <i class="fas fa-check-circle me-2"></i><?= e($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="card table-card">
    <div class="table-responsive">
      <table class="table mb-0" id="mainTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Username</th>
            <th>Role</th>
            <th>Branch</th>
            <th>Phone</th>
            <th>Today Orders</th>
            <th>Total Orders</th>
            <th>Last Login</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $i => $u): ?>
          <tr>
            <td class="text-muted small"><?= $i + 1 ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="user-avatar-sm"><?= strtoupper(substr($u['full_name'],0,1)) ?></div>
                <div>
                  <div class="fw-600"><?= e($u['full_name']) ?></div>
                  <div class="text-muted small"><?= e($u['email'] ?? '') ?></div>
                </div>
              </div>
            </td>
            <td class="font-monospace small"><?= e($u['username']) ?></td>
            <td>
              <span class="badge <?= $u['role']==='owner'?'stat-purple':($u['role']==='admin'?'bg-warning text-dark':'bg-light text-dark border') ?> rounded-pill">
                <?= ucfirst($u['role']) ?>
              </span>
            </td>
            <td><?= $u['branch_name'] ? '<span class="badge bg-purple-soft text-purple">'.e($u['branch_name']).'</span>' : '<span class="text-muted small">All Branches</span>' ?></td>
            <td><?= e($u['phone'] ?? '—') ?></td>
            <td><span class="badge bg-light text-dark border"><?= $u['today_orders'] ?></span></td>
            <td><span class="badge bg-light text-dark border"><?= $u['total_orders'] ?></span></td>
            <td class="text-muted small"><?= $u['last_login'] ? date('M d, Y h:i A', strtotime($u['last_login'])) : 'Never' ?></td>
            <td>
              <span class="badge <?= $u['status']==='active'?'bg-success':'bg-danger' ?>"><?= ucfirst($u['status']) ?></span>
            </td>
            <td>
              <div class="d-flex gap-1">
                <button class="btn btn-sm btn-outline-primary"
                        onclick='editStaff(<?= json_encode($u) ?>)'
                        data-bs-toggle="modal" data-bs-target="#staffModal">
                  <i class="fas fa-edit"></i>
                </button>
                <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                <a href="?toggle=<?= $u['id'] ?>" class="btn btn-sm <?= $u['status']==='active'?'btn-outline-danger':'btn-outline-success' ?>"
                   onclick="return confirm('<?= $u['status']==='active'?'Deactivate':'Activate' ?> this user?')"
                   title="<?= $u['status']==='active'?'Deactivate':'Activate' ?>">
                  <i class="fas fa-<?= $u['status']==='active'?'ban':'check' ?>"></i>
                </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Staff Modal -->
<div class="modal fade" id="staffModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" id="staffAction" value="create">
        <input type="hidden" name="id" id="staffId">
        <div class="modal-header">
          <h5 class="modal-title" id="staffModalTitle"><i class="fas fa-user-plus me-2"></i>Add Staff Account</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full Name *</label>
              <input type="text" name="full_name" id="sName" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Username *</label>
              <input type="text" name="username" id="sUsername" class="form-control" required>
            </div>
            <div class="col-md-6" id="sPasswordGroup">
              <label class="form-label">Password *</label>
              <input type="password" name="password" id="sPassword" class="form-control" required placeholder="Min 6 characters">
            </div>
            <div class="col-md-6" id="sNewPasswordGroup" style="display:none">
              <label class="form-label">New Password <span class="text-muted small">(leave blank to keep)</span></label>
              <input type="password" name="new_password" class="form-control" placeholder="Enter new password">
            </div>
            <div class="col-md-4">
              <label class="form-label">Role *</label>
              <select name="role" id="sRole" class="form-select" required>
                <option value="staff">Staff</option>
                <option value="admin">Admin</option>
                <option value="owner">Owner</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Branch</label>
              <select name="branch_id" id="sBranch" class="form-select">
                <option value="">All Branches</option>
                <?php foreach ($branches as $b): ?>
                  <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4" id="sStatusGroup" style="display:none">
              <label class="form-label">Status</label>
              <select name="status" id="sStatus" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" id="sPhone" class="form-control" placeholder="09XX XXX XXXX">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" id="sEmail" class="form-control" placeholder="email@lavenderia.ph">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-grad btn-sm"><i class="fas fa-save me-1"></i>Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
<script>
function editStaff(u) {
  document.getElementById('staffAction').value = 'update';
  document.getElementById('staffId').value     = u.id;
  document.getElementById('sName').value       = u.full_name;
  document.getElementById('sUsername').value   = u.username;
  document.getElementById('sRole').value       = u.role;
  document.getElementById('sBranch').value     = u.branch_id || '';
  document.getElementById('sPhone').value      = u.phone || '';
  document.getElementById('sEmail').value      = u.email || '';
  document.getElementById('sStatus').value     = u.status;
  document.getElementById('sPasswordGroup').style.display    = 'none';
  document.getElementById('sNewPasswordGroup').style.display = '';
  document.getElementById('sStatusGroup').style.display      = '';
  document.getElementById('staffModalTitle').innerHTML = '<i class="fas fa-user-edit me-2"></i>Edit Staff Account';
}
document.getElementById('staffModal').addEventListener('hidden.bs.modal', function() {
  document.getElementById('staffAction').value = 'create';
  document.getElementById('staffId').value     = '';
  document.getElementById('sPasswordGroup').style.display    = '';
  document.getElementById('sNewPasswordGroup').style.display = 'none';
  document.getElementById('sStatusGroup').style.display      = 'none';
  document.getElementById('staffModalTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i>Add Staff Account';
  this.querySelector('form').reset();
});
</script>
