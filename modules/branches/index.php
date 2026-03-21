<?php
/**
 * Branch Management — Admin / Owner Only
 */
require_once '../../config/database.php';
require_once '../../config/functions.php';
requireAuth(['owner', 'admin']);

$msg     = '';
$msgType = 'success';

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $name    = trim($_POST['name']    ?? '');
    $loc     = trim($_POST['location']  ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $email   = trim($_POST['email']   ?? '');
    $manager = trim($_POST['manager_name'] ?? '');

    if ($name && $loc) {
        $stmt = $conn->prepare("INSERT INTO branches (name,location,contact,email,manager_name) VALUES (?,?,?,?,?)");
        $stmt->bind_param('sssss', $name, $loc, $contact, $email, $manager);
        if ($stmt->execute()) { logAction("Created branch: $name", 'branches', $conn->insert_id); $msg = "Branch \"$name\" created."; }
        else { $msg = $stmt->error; $msgType = 'danger'; }
        $stmt->close();
    }
}

if ($action === 'update') {
    $bid     = (int)$_POST['id'];
    $name    = trim($_POST['name']    ?? '');
    $loc     = trim($_POST['location'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $email   = trim($_POST['email']  ?? '');
    $manager = trim($_POST['manager_name'] ?? '');
    $status  = $_POST['status'] ?? 'active';

    $stmt = $conn->prepare("UPDATE branches SET name=?,location=?,contact=?,email=?,manager_name=?,status=? WHERE id=?");
    $stmt->bind_param('ssssssi', $name, $loc, $contact, $email, $manager, $status, $bid);
    if ($stmt->execute()) { logAction("Updated branch #$bid: $name", 'branches', $bid); $msg = "Branch updated."; }
    else { $msg = $stmt->error; $msgType = 'danger'; }
    $stmt->close();
}

if (isset($_GET['delete'])) {
    $bid = (int)$_GET['delete'];
    $conn->query("UPDATE branches SET status='inactive' WHERE id=$bid");
    logAction("Deactivated branch #$bid", 'branches', $bid);
    header('Location: index.php?msg=deactivated'); exit;
}

$branches = $conn->query("
    SELECT b.*,
      (SELECT COUNT(*) FROM users u WHERE u.branch_id=b.id AND u.status='active') AS staff_count,
      (SELECT COUNT(*) FROM orders o WHERE o.branch_id=b.id AND DATE(o.created_at)=CURDATE()) AS today_orders,
      (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.branch_id=b.id AND DATE(p.created_at)=CURDATE()) AS today_sales
    FROM branches b
    ORDER BY b.name
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Branches';
$navTitle  = 'Branch Management';
require_once '../../includes/head.php';
?>

<div class="app-layout">
<?php require_once '../../includes/sidebar.php'; ?>
<div class="main-content">
<?php require_once '../../includes/navbar.php'; ?>
<div class="page-content">

  <div class="page-header">
    <div>
      <h4><i class="fas fa-store text-purple me-2"></i>Branches</h4>
      <nav><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Branches</li>
      </ol></nav>
    </div>
    <button class="btn btn-primary-grad" data-bs-toggle="modal" data-bs-target="#branchModal">
      <i class="fas fa-plus me-1"></i>Add Branch
    </button>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible auto-dismiss">
      <i class="fas fa-check-circle me-2"></i><?= e($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Branch Cards -->
  <div class="row g-3 mb-4">
    <?php foreach ($branches as $b): ?>
    <div class="col-md-6 col-xl-4">
      <div class="card h-100 <?= $b['status']==='inactive'?'opacity-60':'' ?>">
        <div class="card-body">
          <div class="d-flex align-items-start justify-content-between mb-3">
            <div>
              <h6 class="fw-700 mb-0"><?= e($b['name']) ?></h6>
              <div class="text-muted small"><i class="fas fa-map-marker-alt me-1"></i><?= e($b['location']) ?></div>
            </div>
            <span class="badge <?= $b['status']==='active'?'bg-success':'bg-secondary' ?>">
              <?= ucfirst($b['status']) ?>
            </span>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-4 text-center">
              <div class="fw-700 text-purple"><?= $b['staff_count'] ?? 0 ?></div>
              <div class="text-muted" style="font-size:.7rem">Staff</div>
            </div>
            <div class="col-4 text-center">
              <div class="fw-700 text-cyan"><?= $b['today_orders'] ?? 0 ?></div>
              <div class="text-muted" style="font-size:.7rem">Orders Today</div>
            </div>
            <div class="col-4 text-center">
              <div class="fw-700 text-success" style="font-size:.8rem"><?= formatCurrency((float)($b['today_sales'] ?? 0)) ?></div>
              <div class="text-muted" style="font-size:.7rem">Sales Today</div>
            </div>
          </div>
          <div class="row g-1 mb-2">
            <?php if ($b['contact']): ?>
            <div class="col-12 small text-muted"><i class="fas fa-phone me-1"></i><?= e($b['contact']) ?></div>
            <?php endif; ?>
            <?php if ($b['email']): ?>
            <div class="col-12 small text-muted"><i class="fas fa-envelope me-1"></i><?= e($b['email']) ?></div>
            <?php endif; ?>
            <?php if ($b['manager_name']): ?>
            <div class="col-12 small text-muted"><i class="fas fa-user-tie me-1"></i><?= e($b['manager_name']) ?></div>
            <?php endif; ?>
          </div>
          <div class="d-flex gap-2 mt-2">
            <button class="btn btn-sm btn-outline-primary flex-fill"
                    onclick='editBranch(<?= json_encode($b) ?>)'
                    data-bs-toggle="modal" data-bs-target="#branchModal">
              <i class="fas fa-edit me-1"></i>Edit
            </button>
            <a href="<?= SITE_URL ?>/modules/orders/index.php?branch_id=<?= $b['id'] ?>"
               class="btn btn-sm btn-outline-secondary flex-fill">
              <i class="fas fa-eye me-1"></i>Orders
            </a>
            <?php if ($b['status'] === 'active'): ?>
            <a href="?delete=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger"
               onclick="return confirm('Deactivate branch <?= e(addslashes($b['name'])) ?>?')">
              <i class="fas fa-ban"></i>
            </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Table summary -->
  <div class="card table-card">
    <div class="card-header"><i class="fas fa-table text-purple me-2"></i>Branch Summary Table</div>
    <div class="table-responsive">
      <table class="table mb-0">
        <thead>
          <tr><th>Branch</th><th>Location</th><th>Manager</th><th>Contact</th><th>Staff</th><th>Today Orders</th><th>Today Sales</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($branches as $b): ?>
          <tr>
            <td class="fw-600"><?= e($b['name']) ?></td>
            <td class="text-muted small"><?= e($b['location']) ?></td>
            <td><?= e($b['manager_name'] ?? '—') ?></td>
            <td><?= e($b['contact'] ?? '—') ?></td>
            <td><span class="badge bg-purple-soft text-purple"><?= $b['staff_count'] ?? 0 ?></span></td>
            <td><?= $b['today_orders'] ?? 0 ?></td>
            <td class="fw-600"><?= formatCurrency((float)($b['today_sales'] ?? 0)) ?></td>
            <td><span class="badge <?= $b['status']==='active'?'bg-success':'bg-secondary' ?>"><?= ucfirst($b['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Branch Modal -->
<div class="modal fade" id="branchModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" id="branchAction" value="create">
        <input type="hidden" name="id" id="branchId">
        <div class="modal-header">
          <h5 class="modal-title" id="branchModalTitle"><i class="fas fa-plus me-2"></i>Add Branch</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Branch Name *</label>
              <input type="text" name="name" id="bName" class="form-control" required placeholder="e.g. Lavenderia Main Branch">
            </div>
            <div class="col-md-6">
              <label class="form-label">Location / Address *</label>
              <input type="text" name="location" id="bLoc" class="form-control" required placeholder="Street, City">
            </div>
            <div class="col-md-4">
              <label class="form-label">Contact Number</label>
              <input type="text" name="contact" id="bContact" class="form-control" placeholder="09XX XXX XXXX">
            </div>
            <div class="col-md-4">
              <label class="form-label">Email</label>
              <input type="email" name="email" id="bEmail" class="form-control" placeholder="branch@lavenderia.ph">
            </div>
            <div class="col-md-4">
              <label class="form-label">Manager Name</label>
              <input type="text" name="manager_name" id="bManager" class="form-control" placeholder="Full name">
            </div>
            <div class="col-md-4" id="bStatusGroup" style="display:none">
              <label class="form-label">Status</label>
              <select name="status" id="bStatus" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-grad btn-sm"><i class="fas fa-save me-1"></i>Save Branch</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
<script>
function editBranch(b) {
  document.getElementById('branchAction').value    = 'update';
  document.getElementById('branchId').value        = b.id;
  document.getElementById('bName').value           = b.name;
  document.getElementById('bLoc').value            = b.location;
  document.getElementById('bContact').value        = b.contact || '';
  document.getElementById('bEmail').value          = b.email || '';
  document.getElementById('bManager').value        = b.manager_name || '';
  document.getElementById('bStatus').value         = b.status;
  document.getElementById('bStatusGroup').style.display  = '';
  document.getElementById('branchModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Branch';
}
document.getElementById('branchModal').addEventListener('hidden.bs.modal', function() {
  document.getElementById('branchAction').value = 'create';
  document.getElementById('bStatusGroup').style.display = 'none';
  document.getElementById('branchModalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Add Branch';
  this.querySelector('form').reset();
});
</script>
