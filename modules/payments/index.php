<?php
/**
 * Payments — History and Management
 */
require_once '../../config/database.php';
require_once '../../config/functions.php';
requireAuth();

$bf       = branchFilter('p');
$branches = getBranches();

$filter_method = $_GET['method']    ?? '';
$filter_branch = (int)($_GET['branch_id'] ?? 0);
$filter_date   = $_GET['date']      ?? '';
$filter_type   = $_GET['type']      ?? '';
$search        = trim($_GET['q']    ?? '');

$whereArr = ["$bf"];
if ($filter_method) $whereArr[] = "p.payment_method = '" . $conn->real_escape_string($filter_method) . "'";
if ($filter_branch && in_array($_SESSION['role'],['owner','admin'],true)) $whereArr[] = "p.branch_id = $filter_branch";
if ($filter_date)   $whereArr[] = "DATE(p.created_at) = '" . $conn->real_escape_string($filter_date) . "'";
if ($filter_type)   $whereArr[] = "p.payment_type = '" . $conn->real_escape_string($filter_type) . "'";
if ($search)        $whereArr[] = "(o.order_number LIKE '%" . $conn->real_escape_string($search) . "%' OR c.name LIKE '%" . $conn->real_escape_string($search) . "%')";

$whereSQL = implode(' AND ', $whereArr);

$payments = $conn->query("
    SELECT p.*, o.order_number, o.total_amount, o.payment_status,
           c.name AS customer_name, b.name AS branch_name, u.full_name AS staff_name
    FROM payments p
    JOIN orders o ON o.id = p.order_id
    LEFT JOIN customers c ON c.id = o.customer_id
    JOIN branches b ON b.id = p.branch_id
    JOIN users u ON u.id = p.received_by
    WHERE $whereSQL
    ORDER BY p.created_at DESC LIMIT 300
")->fetch_all(MYSQLI_ASSOC);

// Summary stats
$today = date('Y-m-d');
$stats = $conn->query("
    SELECT
      COALESCE(SUM(CASE WHEN payment_method='cash'  AND $bf THEN amount END),0) AS cash,
      COALESCE(SUM(CASE WHEN payment_method='gcash' AND $bf THEN amount END),0) AS gcash,
      COALESCE(SUM(CASE WHEN payment_type='refund'  AND $bf THEN amount END),0) AS refunds,
      COALESCE(SUM(CASE WHEN $bf THEN amount END),0)                             AS total
    FROM payments p
    WHERE DATE(p.created_at)='$today'
")->fetch_assoc();

$pageTitle = 'Payments';
$navTitle  = 'Payment Management';
require_once '../../includes/head.php';
?>

<div class="app-layout">
<?php require_once '../../includes/sidebar.php'; ?>
<div class="main-content">
<?php require_once '../../includes/navbar.php'; ?>
<div class="page-content">

  <div class="page-header">
    <div>
      <h4><i class="fas fa-credit-card text-purple me-2"></i>Payments</h4>
      <nav><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Payments</li>
      </ol></nav>
    </div>
    <a href="<?= SITE_URL ?>/modules/orders/index.php?payment=unpaid" class="btn btn-primary-grad">
      <i class="fas fa-money-bill me-1"></i>Collect Payment
    </a>
  </div>

  <!-- Today stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat-card stat-green">
        <div class="stat-icon"><i class="fas fa-money-bill"></i></div>
        <div class="stat-label">Cash Today</div>
        <div class="stat-value"><?= formatCurrency($stats['cash']) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card stat-indigo">
        <div class="stat-icon"><i class="fas fa-mobile-alt"></i></div>
        <div class="stat-label">GCash Today</div>
        <div class="stat-value"><?= formatCurrency($stats['gcash']) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card stat-orange">
        <div class="stat-icon"><i class="fas fa-undo"></i></div>
        <div class="stat-label">Refunds Today</div>
        <div class="stat-value"><?= formatCurrency($stats['refunds']) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card stat-purple">
        <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
        <div class="stat-label">Total Today</div>
        <div class="stat-value"><?= formatCurrency($stats['total']) ?></div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <form method="GET" class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2">
      <input type="text" name="q" class="form-control" placeholder="🔍 Order #, customer..." value="<?= e($search) ?>" style="max-width:220px">
      <select name="method" class="form-select" style="max-width:140px">
        <option value="">All Methods</option>
        <option value="cash"  <?= $filter_method==='cash'  ?'selected':'' ?>>Cash</option>
        <option value="gcash" <?= $filter_method==='gcash' ?'selected':'' ?>>GCash</option>
      </select>
      <select name="type" class="form-select" style="max-width:140px">
        <option value="">All Types</option>
        <option value="full"    <?= $filter_type==='full'    ?'selected':'' ?>>Full</option>
        <option value="partial" <?= $filter_type==='partial' ?'selected':'' ?>>Partial</option>
        <option value="refund"  <?= $filter_type==='refund'  ?'selected':'' ?>>Refund</option>
      </select>
      <?php if (in_array($_SESSION['role'],['owner','admin'],true)): ?>
      <select name="branch_id" class="form-select" style="max-width:200px">
        <option value="0">All Branches</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $filter_branch===$b['id'] ?'selected':'' ?>><?= e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <input type="date" name="date" class="form-control" value="<?= e($filter_date) ?>" style="max-width:160px">
      <button type="submit" class="btn btn-outline-primary"><i class="fas fa-filter"></i></button>
      <a href="?" class="btn btn-outline-secondary"><i class="fas fa-undo"></i></a>
    </div>
  </form>

  <div class="card table-card">
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
      <span class="fw-600 text-muted small"><?= count($payments) ?> records</span>
    </div>
    <div class="table-responsive">
      <table class="table mb-0" id="mainTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Date &amp; Time</th>
            <th>Order #</th>
            <th>Customer</th>
            <th>Amount</th>
            <th>Method</th>
            <th>Type</th>
            <th>GCash Ref #</th>
            <th>Received By</th>
            <th>Branch</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($payments)): ?>
            <tr><td colspan="11" class="text-center text-muted py-5">
              <i class="fas fa-credit-card fa-2x d-block mb-2 opacity-25"></i>No payments found.
            </td></tr>
          <?php else: foreach ($payments as $i => $p): ?>
          <tr>
            <td class="text-muted small"><?= $i + 1 ?></td>
            <td><?= date('M d, Y h:i A', strtotime($p['created_at'])) ?></td>
            <td>
              <a href="<?= SITE_URL ?>/modules/orders/view.php?id=<?= $p['order_id'] ?>" class="fw-600 text-purple text-decoration-none">
                <?= e($p['order_number']) ?>
              </a>
            </td>
            <td><?= e($p['customer_name'] ?? 'Walk-in') ?></td>
            <td class="fw-700 <?= $p['payment_type']==='refund'?'text-danger':'text-success' ?>">
              <?= $p['payment_type']==='refund'?'-':'' ?><?= formatCurrency($p['amount']) ?>
            </td>
            <td><?= methodBadge($p['payment_method']) ?></td>
            <td>
              <span class="badge <?= $p['payment_type']==='full'?'bg-success':($p['payment_type']==='partial'?'bg-warning text-dark':'bg-danger') ?>">
                <?= ucfirst($p['payment_type']) ?>
              </span>
            </td>
            <td class="font-monospace small"><?= e($p['gcash_reference'] ?? '—') ?></td>
            <td><?= e($p['staff_name']) ?></td>
            <td><span class="badge bg-purple-soft text-purple"><?= e($p['branch_name']) ?></span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary" onclick="printReceipt(<?= $p['order_id'] ?>)"
                      title="Print Receipt"><i class="fas fa-receipt"></i></button>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php require_once '../../includes/footer.php'; ?>
