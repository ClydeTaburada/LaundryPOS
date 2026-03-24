<?php
/**
 * Orders — List Page
 */
require_once '../../config/database.php';
require_once '../../config/functions.php';
requireAuth();

$bf = branchFilter('o');

// Filters
$filter_status   = $_GET['status']    ?? '';
$filter_branch   = (int)($_GET['branch_id'] ?? 0);
$filter_date     = $_GET['date']      ?? '';
$filter_payment  = $_GET['payment']   ?? '';
$filter_service  = $_GET['service']   ?? '';
$search          = trim($_GET['q']    ?? '');

$where = ["$bf"];
if ($filter_status)  $where[] = "o.status = '" . $conn->real_escape_string($filter_status) . "'";
if ($filter_branch && in_array($_SESSION['role'], ['owner','admin'], true)) $where[] = "o.branch_id = $filter_branch";
if ($filter_date)    $where[] = "DATE(o.created_at) = '" . $conn->real_escape_string($filter_date) . "'";
if ($filter_payment) $where[] = "o.payment_status = '" . $conn->real_escape_string($filter_payment) . "'";
if ($filter_service) $where[] = "o.service_type = '" . $conn->real_escape_string($filter_service) . "'";
if ($search)         $where[] = "(o.order_number LIKE '%" . $conn->real_escape_string($search) . "%' OR c.name LIKE '%" . $conn->real_escape_string($search) . "%')";

$whereSQL = implode(' AND ', $where);
$orders = $conn->query("
    SELECT o.*, c.name AS customer_name, b.name AS branch_name, u.full_name AS staff_name
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    JOIN branches b ON b.id = o.branch_id
    JOIN users u ON u.id = o.staff_id
    WHERE $whereSQL
    ORDER BY o.created_at DESC
    LIMIT 200
")->fetch_all(MYSQLI_ASSOC);

$branches = getBranches();

$pageTitle  = 'Orders';
$navTitle   = 'Order Management';
require_once '../../includes/head.php';
?>

<div class="app-layout">
<?php require_once '../../includes/sidebar.php'; ?>
<div class="main-content">
<?php require_once '../../includes/navbar.php'; ?>
<div class="page-content">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h4><i class="fas fa-shopping-bag text-purple me-2"></i>Orders</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Orders</li>
        </ol>
      </nav>
    </div>
    <a href="<?= SITE_URL ?>/modules/orders/create.php" class="btn btn-primary-grad">
      <i class="fas fa-plus me-1"></i>New Order
    </a>
  </div>

  <!-- Filters -->
  <form method="GET" class="card mb-3">
    <div class="card-body">
      <div class="row g-2">
        <div class="col-12 col-md-3">
          <input type="text" name="q" class="form-control" placeholder="🔍 Search order #, customer..."
                 value="<?= e($search) ?>">
        </div>
        <div class="col-6 col-md-2">
          <select name="status" class="form-select">
            <option value="">All Status</option>
            <?php foreach (['received','washing','drying','ready','claimed'] as $s): ?>
              <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select name="payment" class="form-select">
            <option value="">All Payment</option>
            <option value="paid"    <?= $filter_payment === 'paid'    ? 'selected' : '' ?>>Paid</option>
            <option value="unpaid"  <?= $filter_payment === 'unpaid'  ? 'selected' : '' ?>>Unpaid</option>
            <option value="partial" <?= $filter_payment === 'partial' ? 'selected' : '' ?>>Partial</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select name="service" class="form-select">
            <option value="">All Services</option>
            <option value="wash_fold" <?= $filter_service==='wash_fold' ? 'selected' : '' ?>>Wash & Fold</option>
            <option value="dry_clean" <?= $filter_service==='dry_clean' ? 'selected' : '' ?>>Dry Clean</option>
            <option value="ironing"   <?= $filter_service==='ironing'   ? 'selected' : '' ?>>Ironing</option>
          </select>
        </div>
        <?php if (in_array($_SESSION['role'], ['owner','admin'],true)): ?>
        <div class="col-6 col-md-2">
          <select name="branch_id" class="form-select">
            <option value="0">All Branches</option>
            <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>" <?= $filter_branch === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="col-6 col-md-1">
          <input type="date" name="date" class="form-control" value="<?= e($filter_date) ?>">
        </div>
        <div class="col-6 col-md-auto d-flex gap-2">
          <button type="submit" class="btn btn-primary-grad"><i class="fas fa-filter"></i></button>
          <a href="?" class="btn btn-outline-secondary"><i class="fas fa-undo"></i></a>
        </div>
      </div>
    </div>
  </form>

  <!-- Status count pills -->
  <?php
    $status_counts = ['received'=>0,'washing'=>0,'drying'=>0,'ready'=>0,'claimed'=>0];
    foreach ($orders as $o) { if (isset($status_counts[$o['status']])) $status_counts[$o['status']]++; }
  ?>
  <div class="d-flex gap-2 flex-wrap mb-3">
    <?php foreach ($status_counts as $s => $c): ?>
      <a href="?status=<?= $s ?>" class="text-decoration-none">
        <?= statusBadge($s) ?> <span class="text-muted small">(<?= $c ?>)</span>
      </a>
    <?php endforeach; ?>
    <span class="text-muted ms-auto small align-self-center"><?= count($orders) ?> orders found</span>
  </div>

  <!-- Table -->
  <div class="card table-card">
    <div class="table-responsive">
      <table class="table mb-0" id="mainTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Order #</th>
            <th>Customer</th>
            <th>Service</th>
            <th>Amount</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Due Date</th>
            <th>Staff</th>
            <th>Branch</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr><td colspan="11" class="text-center text-muted py-5">
              <i class="fas fa-shopping-bag fa-2x mb-2 d-block opacity-25"></i>No orders found.
            </td></tr>
          <?php else: foreach ($orders as $i => $o): ?>
          <tr>
            <td class="text-muted small"><?= $i + 1 ?></td>
            <td>
              <a href="<?= SITE_URL ?>/modules/orders/view.php?id=<?= $o['id'] ?>" class="fw-600 text-purple text-decoration-none">
                <?= e($o['order_number']) ?>
              </a>
              <?php if ($o['is_delivery']): ?>
                <span class="badge bg-info ms-1" style="font-size:.65rem">Delivery</span>
              <?php endif; ?>
            </td>
            <td><?= e($o['customer_name'] ?? 'Walk-in') ?></td>
            <td><?= serviceLabel($o['service_type']) ?></td>
            <td>
              <span class="fw-600"><?= formatCurrency($o['total_amount']) ?></span>
              <?php if ($o['paid_amount'] > 0 && $o['payment_status'] === 'partial'): ?>
                <div class="text-success small">Paid: <?= formatCurrency($o['paid_amount']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?= paymentBadge($o['payment_status']) ?>
              <?= methodBadge($o['payment_method']) ?>
            </td>
            <td><?= statusBadge($o['status']) ?></td>
            <td>
              <?php if ($o['due_date']): ?>
                <?php $isLate = strtotime($o['due_date']) < time() && $o['status'] !== 'claimed'; ?>
                <span class="<?= $isLate ? 'text-danger fw-600' : '' ?>">
                  <?= date('M d, h:i A', strtotime($o['due_date'])) ?>
                  <?php if ($isLate): ?><i class="fas fa-exclamation-circle ms-1"></i><?php endif; ?>
                </span>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
            <td><?= e($o['staff_name']) ?></td>
            <td><span class="badge bg-purple-soft text-purple"><?= e($o['branch_name']) ?></span></td>
            <td>
              <div class="d-flex gap-1">
                <a href="<?= SITE_URL ?>/modules/orders/view.php?id=<?= $o['id'] ?>"
                   class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="View">
                  <i class="fas fa-eye"></i>
                </a>
                <button class="btn btn-sm btn-outline-secondary"
                        onclick="printReceipt(<?= $o['id'] ?>)"
                        data-bs-toggle="tooltip" title="Receipt">
                  <i class="fas fa-receipt"></i>
                </button>
                <?php if (in_array($_SESSION['role'],['admin','owner'],true)): ?>
                <button class="btn btn-sm btn-outline-danger"
                        onclick="ajaxDelete(SITE_URL+'/api/orders.php',{id:<?= $o['id'] ?>}, ()=>location.reload())"
                        data-bs-toggle="tooltip" title="Delete">
                  <i class="fas fa-trash"></i>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php require_once '../../includes/footer.php'; ?>
