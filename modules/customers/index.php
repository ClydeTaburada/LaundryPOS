<?php
/**
 * Customers — CRUD Management
 */
require_once '../../config/database.php';
require_once '../../config/functions.php';
requireAuth();

$bf       = branchFilter('c');
$branches = getBranches();
$msg      = '';
$msgType  = 'success';

// ── CREATE ──────────────────────────────────────────────────────
if ($_POST['action'] ?? '' === 'create') {
    $bid   = (int)($_POST['branch_id'] ?? $_SESSION['branch_id'] ?? 0);
    $name  = trim($_POST['name']    ?? '');
    $phone = trim($_POST['phone']   ?? '');
    $email = trim($_POST['email']   ?? '');
    $addr  = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes']   ?? '');

    if ($name) {
        $stmt = $conn->prepare("INSERT INTO customers (branch_id,name,phone,email,address,notes) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('isssss', $bid, $name, $phone, $email, $addr, $notes);
        if ($stmt->execute()) { logAction("Created customer: $name", 'customers', $conn->insert_id); $msg = "Customer \"$name\" added."; }
        else { $msg = 'Error: ' . $stmt->error; $msgType = 'danger'; }
        $stmt->close();
    }
}

// ── UPDATE ──────────────────────────────────────────────────────
if ($_POST['action'] ?? '' === 'update') {
    $cid   = (int)$_POST['id'];
    $bid   = (int)($_POST['branch_id'] ?? 0);
    $name  = trim($_POST['name']    ?? '');
    $phone = trim($_POST['phone']   ?? '');
    $email = trim($_POST['email']   ?? '');
    $addr  = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes']   ?? '');

    if ($cid && $name) {
        $stmt = $conn->prepare("UPDATE customers SET branch_id=?,name=?,phone=?,email=?,address=?,notes=? WHERE id=?");
        $stmt->bind_param('isssssi', $bid, $name, $phone, $email, $addr, $notes, $cid);
        if ($stmt->execute()) { logAction("Updated customer: $name", 'customers', $cid); $msg = "Customer updated."; }
        else { $msg = 'Error: ' . $stmt->error; $msgType = 'danger'; }
        $stmt->close();
    }
}

// ── DELETE ──────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $cid = (int)$_GET['delete'];
    $conn->query("DELETE FROM customers WHERE id=$cid");
    logAction("Deleted customer #$cid", 'customers', $cid);
    header('Location: index.php?msg=deleted'); exit;
}

$search   = trim($_GET['q'] ?? '');
$whereArr = ["$bf"];
if ($search) $whereArr[] = "(c.name LIKE '%" . $conn->real_escape_string($search) . "%' OR c.phone LIKE '%" . $conn->real_escape_string($search) . "%')";

$customers = $conn->query("
    SELECT c.*, b.name AS branch_name,
           (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS order_count,
           (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.customer_id = c.id) AS lifetime_value
    FROM customers c
    LEFT JOIN branches b ON b.id = c.branch_id
    WHERE " . implode(' AND ', $whereArr) . "
    ORDER BY c.name ASC
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Customers';
$navTitle  = 'Customer Management';
require_once '../../includes/head.php';
?>

<div class="app-layout">
<?php require_once '../../includes/sidebar.php'; ?>
<div class="main-content">
<?php require_once '../../includes/navbar.php'; ?>
<div class="page-content">

  <div class="page-header">
    <div>
      <h4><i class="fas fa-users text-purple me-2"></i>Customers</h4>
      <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Customers</li>
      </ol></nav>
    </div>
    <button class="btn btn-primary-grad" data-bs-toggle="modal" data-bs-target="#custModal">
      <i class="fas fa-user-plus me-1"></i>Add Customer
    </button>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible auto-dismiss fade show">
      <i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?> me-2"></i><?= e($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if (isset($_GET['msg']) && $_GET['msg']==='deleted'): ?>
    <div class="alert alert-warning alert-dismissible auto-dismiss fade show"><i class="fas fa-trash me-2"></i>Customer deleted.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <!-- Search -->
  <form method="GET" class="mb-3 d-flex gap-2">
    <input type="text" name="q" class="form-control" placeholder="🔍 Search by name or phone..."
           value="<?= e($search) ?>" style="max-width:320px">
    <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i></button>
    <a href="?" class="btn btn-outline-secondary"><i class="fas fa-undo"></i></a>
  </form>

  <div class="card table-card">
    <div class="table-responsive">
      <table class="table mb-0" id="mainTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Branch</th>
            <th>Orders</th>
            <th>Lifetime Value</th>
            <th>Loyalty Pts</th>
            <th>Since</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($customers)): ?>
            <tr><td colspan="10" class="text-center text-muted py-5">
              <i class="fas fa-users fa-2x mb-2 d-block opacity-25"></i>No customers found.
            </td></tr>
          <?php else: foreach ($customers as $i => $c): ?>
          <tr>
            <td class="text-muted small"><?= $i + 1 ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="user-avatar-sm flex-shrink-0"><?= strtoupper(substr($c['name'],0,1)) ?></div>
                <div>
                  <div class="fw-600"><?= e($c['name']) ?></div>
                  <?php if ($c['notes']): ?>
                    <div class="text-muted" style="font-size:.72rem"><?= e(substr($c['notes'],0,40)) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td><?= e($c['phone'] ?? '—') ?></td>
            <td><?= e($c['email'] ?? '—') ?></td>
            <td><span class="badge bg-purple-soft text-purple"><?= e($c['branch_name'] ?? 'All') ?></span></td>
            <td><span class="badge bg-light text-dark border"><?= $c['order_count'] ?></span></td>
            <td class="fw-600"><?= formatCurrency($c['lifetime_value']) ?></td>
            <td>
              <span class="badge <?= $c['loyalty_points'] > 50 ? 'stat-purple text-white' : 'bg-light text-dark border' ?>">
                <i class="fas fa-star me-1"></i><?= $c['loyalty_points'] ?>
              </span>
            </td>
            <td class="text-muted small"><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
            <td>
              <div class="d-flex gap-1">
                <button class="btn btn-sm btn-outline-primary"
                        onclick='editCustomer(<?= json_encode($c) ?>)'
                        data-bs-toggle="modal" data-bs-target="#custModal">
                  <i class="fas fa-edit"></i>
                </button>
                <a href="?delete=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Delete customer <?= e(addslashes($c['name'])) ?>?')">
                  <i class="fas fa-trash"></i>
                </a>
                <a href="<?= SITE_URL ?>/modules/orders/index.php?q=<?= urlencode($c['name']) ?>"
                   class="btn btn-sm btn-outline-secondary" title="View Orders">
                  <i class="fas fa-shopping-bag"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Customer Modal -->
<div class="modal fade" id="custModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" id="custAction" value="create">
        <input type="hidden" name="id" id="custId">
        <div class="modal-header">
          <h5 class="modal-title" id="custModalTitle"><i class="fas fa-user-plus me-2"></i>Add Customer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full Name *</label>
              <input type="text" name="name" id="custName" class="form-control" required placeholder="Full name">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" id="custPhone" class="form-control" placeholder="09XXXXXXXXX">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" id="custEmail" class="form-control" placeholder="email@example.com">
            </div>
            <div class="col-md-6">
              <label class="form-label">Branch</label>
              <select name="branch_id" id="custBranch" class="form-select">
                <?php foreach ($branches as $b): ?>
                  <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Address</label>
              <input type="text" name="address" id="custAddr" class="form-control" placeholder="Street, City">
            </div>
            <div class="col-12">
              <label class="form-label">Notes</label>
              <textarea name="notes" id="custNotes" class="form-control" rows="2" placeholder="Special notes..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-grad btn-sm">
            <i class="fas fa-save me-1"></i>Save Customer
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
<script>
function editCustomer(c) {
  document.getElementById('custAction').value = 'update';
  document.getElementById('custId').value    = c.id;
  document.getElementById('custName').value  = c.name;
  document.getElementById('custPhone').value = c.phone || '';
  document.getElementById('custEmail').value = c.email || '';
  document.getElementById('custAddr').value  = c.address || '';
  document.getElementById('custNotes').value = c.notes || '';
  document.getElementById('custBranch').value = c.branch_id || '';
  document.getElementById('custModalTitle').innerHTML = '<i class="fas fa-user-edit me-2"></i>Edit Customer';
}
document.getElementById('custModal').addEventListener('hidden.bs.modal', function() {
  document.getElementById('custAction').value = 'create';
  document.getElementById('custId').value = '';
  document.getElementById('custModalTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i>Add Customer';
  this.querySelector('form').reset();
});
</script>
