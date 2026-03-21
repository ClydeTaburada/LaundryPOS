<?php
/**
 * Dashboard — Lavenderia Laundry Services
 */
require_once '../../config/database.php';
require_once '../../config/functions.php';
requireAuth();

$role     = $_SESSION['role'] ?? 'staff';
$bf       = branchFilter('o');
$bf_inv   = branchFilter('i');
$bf_pay   = branchFilter('p');
$today    = date('Y-m-d');

/* ── KPI Stats ──────────────────────────────────────────────────── */
// Today sales
$row = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM payments p WHERE DATE(p.created_at)='$today' AND $bf_pay")->fetch_assoc();
$today_sales = $row['total'];

// Active orders (not claimed)
$row = $conn->query("SELECT COUNT(*) AS c FROM orders o WHERE o.status NOT IN ('claimed') AND $bf")->fetch_assoc();
$active_orders = $row['c'];

// Delayed orders (due_date < now, not claimed)
$row = $conn->query("SELECT COUNT(*) AS c FROM orders o WHERE o.due_date < NOW() AND o.status NOT IN ('claimed') AND $bf")->fetch_assoc();
$delayed = $row['c'];

// Unpaid orders
$row = $conn->query("SELECT COUNT(*) AS c FROM orders o WHERE o.payment_status='unpaid' AND o.status NOT IN ('claimed') AND $bf")->fetch_assoc();
$unpaid_orders = $row['c'];

// Total orders today
$row = $conn->query("SELECT COUNT(*) AS c FROM orders o WHERE DATE(o.created_at)='$today' AND $bf")->fetch_assoc();
$today_orders = $row['c'];

// Total customers
$bf_cust = branchFilter('c');
$row = $conn->query("SELECT COUNT(*) AS c FROM customers c WHERE $bf_cust")->fetch_assoc();
$total_customers = $row['c'];

/* ── Payment breakdown (all time / today) ───────────────────────── */
$row = $conn->query("SELECT
    COALESCE(SUM(CASE WHEN payment_method='cash'  AND $bf_pay THEN amount END),0) AS cash_total,
    COALESCE(SUM(CASE WHEN payment_method='gcash' AND $bf_pay THEN amount END),0) AS gcash_total
  FROM payments p WHERE DATE(p.created_at)='$today'")->fetch_assoc();
$cash_today  = $row['cash_total'];
$gcash_today = $row['gcash_total'];

$row2 = $conn->query("SELECT COALESCE(SUM(total_amount - paid_amount),0) AS unpaid_amt FROM orders o WHERE o.payment_status IN ('unpaid','partial') AND $bf")->fetch_assoc();
$unpaid_amt = $row2['unpaid_amt'];

/* ── 7-day sales trend ──────────────────────────────────────────── */
$sales_labels = [];
$sales_data   = [];
for ($i = 6; $i >= 0; $i--) {
    $dt   = date('Y-m-d', strtotime("-$i days"));
    $sales_labels[] = date('M d', strtotime($dt));
    $res  = $conn->query("SELECT COALESCE(SUM(amount),0) AS s FROM payments p WHERE DATE(p.created_at)='$dt' AND $bf_pay");
    $sales_data[] = (float) $res->fetch_assoc()['s'];
}

/* ── Orders by branch ───────────────────────────────────────────── */
$branch_names  = [];
$branch_counts = [];
$res = $conn->query("SELECT b.name, COUNT(*) AS c FROM orders o JOIN branches b ON b.id=o.branch_id WHERE DATE(o.created_at)='$today' GROUP BY o.branch_id ORDER BY c DESC LIMIT 6");
while ($r = $res->fetch_assoc()) {
    $branch_names[]  = $r['name'];
    $branch_counts[] = (int)$r['c'];
}

/* ── Orders by service ──────────────────────────────────────────── */
$svc_labels = ['Wash & Fold', 'Dry Clean', 'Ironing'];
$svc_data   = [];
foreach (['wash_fold', 'dry_clean', 'ironing'] as $s) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM orders o WHERE o.service_type='$s' AND DATE(o.created_at)='$today' AND $bf")->fetch_assoc();
    $svc_data[] = (int)$r['c'];
}

/* ── Recent orders ──────────────────────────────────────────────── */
$recent_orders = $conn->query("
    SELECT o.id, o.order_number, o.status, o.service_type,
           o.total_amount, o.payment_status, o.due_date, o.created_at,
           c.name AS customer_name, b.name AS branch_name, u.full_name AS staff_name
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    JOIN branches b ON b.id = o.branch_id
    JOIN users u ON u.id = o.staff_id
    WHERE $bf
    ORDER BY o.created_at DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

/* ── Low stock alerts ───────────────────────────────────────────── */
$low_stock = $conn->query("
    SELECT i.item_name, i.quantity, i.unit, i.low_stock_threshold, b.name AS branch_name
    FROM inventory i JOIN branches b ON b.id = i.branch_id
    WHERE i.quantity <= i.low_stock_threshold AND $bf_inv
    ORDER BY i.quantity ASC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

/* ── Staff performance (today) ──────────────────────────────────── */
$staff_perf = $conn->query("
    SELECT u.full_name, COUNT(o.id) AS order_count,
           COALESCE(SUM(o.total_amount),0) AS total_sales
    FROM users u
    LEFT JOIN orders o ON o.staff_id = u.id AND DATE(o.created_at)='$today'
    WHERE u.role='staff' AND ($bf OR u.branch_id IS NULL)
    GROUP BY u.id ORDER BY order_count DESC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Dashboard';
$navTitle  = 'Dashboard';
$navSubtitle = 'Welcome back, ' . ($_SESSION['full_name'] ?? '') . ' — ' . date('l, F j, Y');
require_once '../../includes/head.php';
?>

<div class="app-layout">
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-content">
<?php require_once '../../includes/navbar.php'; ?>

<div class="page-content">

  <!-- ── KPI Cards ────────────────────────────────────────────── -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-2">
      <div class="stat-card stat-purple">
        <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
        <div class="stat-label">Today's Sales</div>
        <div class="stat-value"><?= formatCurrency($today_sales) ?></div>
        <div class="stat-sub"><?= $today_orders ?> orders today</div>
      </div>
    </div>
    <div class="col-6 col-xl-2">
      <div class="stat-card stat-cyan">
        <div class="stat-icon"><i class="fas fa-spinner"></i></div>
        <div class="stat-label">Active Orders</div>
        <div class="stat-value"><?= $active_orders ?></div>
        <div class="stat-sub">In progress</div>
      </div>
    </div>
    <div class="col-6 col-xl-2">
      <div class="stat-card stat-orange">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-label">Delayed Orders</div>
        <div class="stat-value"><?= $delayed ?></div>
        <div class="stat-sub">Past due date</div>
      </div>
    </div>
    <div class="col-6 col-xl-2">
      <div class="stat-card stat-pink">
        <div class="stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="stat-label">Unpaid Orders</div>
        <div class="stat-value"><?= $unpaid_orders ?></div>
        <div class="stat-sub"><?= formatCurrency($unpaid_amt) ?> balance</div>
      </div>
    </div>
    <div class="col-6 col-xl-2">
      <div class="stat-card stat-green">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-label">Customers</div>
        <div class="stat-value"><?= $total_customers ?></div>
        <div class="stat-sub">Total registered</div>
      </div>
    </div>
    <div class="col-6 col-xl-2">
      <div class="stat-card stat-indigo">
        <div class="stat-icon"><i class="fas fa-mobile-alt"></i></div>
        <div class="stat-label">GCash Today</div>
        <div class="stat-value"><?= formatCurrency($gcash_today) ?></div>
        <div class="stat-sub">Cash: <?= formatCurrency($cash_today) ?></div>
      </div>
    </div>
  </div>

  <!-- ── Charts Row ───────────────────────────────────────────── -->
  <div class="row g-3 mb-4">
    <!-- Sales Trend -->
    <div class="col-lg-5">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fas fa-chart-line text-purple me-2"></i>7-Day Sales Trend</span>
          <a href="<?= SITE_URL ?>/modules/reports/index.php" class="btn btn-sm btn-outline-primary">View Full</a>
        </div>
        <div class="card-body">
          <div class="chart-container" style="height:210px">
            <canvas id="salesChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment Breakdown -->
    <div class="col-lg-3">
      <div class="card h-100">
        <div class="card-header">
          <i class="fas fa-chart-pie text-purple me-2"></i>Payment Methods (Today)
        </div>
        <div class="card-body d-flex align-items-center justify-content-center">
          <div class="chart-container" style="height:210px;max-width:220px">
            <canvas id="paymentChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Services -->
    <div class="col-lg-2">
      <div class="card h-100">
        <div class="card-header">
          <i class="fas fa-tshirt text-purple me-2"></i>Services
        </div>
        <div class="card-body d-flex align-items-center justify-content-center">
          <div class="chart-container" style="height:210px;max-width:200px">
            <canvas id="serviceChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Branch Orders -->
    <div class="col-lg-2">
      <div class="card h-100">
        <div class="card-header">
          <i class="fas fa-store text-purple me-2"></i>Orders/Branch
        </div>
        <div class="card-body">
          <div class="chart-container" style="height:210px">
            <canvas id="branchChart"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Bottom Row ────────────────────────────────────────────── -->
  <div class="row g-3">

    <!-- Recent Orders -->
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fas fa-shopping-bag text-purple me-2"></i>Recent Orders</span>
          <a href="<?= SITE_URL ?>/modules/orders/index.php" class="btn btn-sm btn-primary-grad">All Orders</a>
        </div>
        <div class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Service</th>
                <th>Amount</th>
                <th>Payment</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recent_orders)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No orders found.</td></tr>
              <?php else: foreach ($recent_orders as $o): ?>
              <tr>
                <td>
                  <a href="<?= SITE_URL ?>/modules/orders/view.php?id=<?= $o['id'] ?>"
                     class="fw-600 text-purple"><?= e($o['order_number']) ?></a>
                  <div class="text-muted" style="font-size:.72rem"><?= e($o['branch_name']) ?></div>
                </td>
                <td><?= e($o['customer_name'] ?? 'Walk-in') ?></td>
                <td><?= serviceLabel($o['service_type']) ?></td>
                <td class="fw-600"><?= formatCurrency($o['total_amount']) ?></td>
                <td><?= paymentBadge($o['payment_status']) ?></td>
                <td><?= statusBadge($o['status']) ?></td>
                <td>
                  <a href="<?= SITE_URL ?>/modules/orders/view.php?id=<?= $o['id'] ?>"
                     class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-eye"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Right column -->
    <div class="col-lg-4">

      <!-- Low Stock Alerts -->
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fas fa-triangle-exclamation text-warning me-2"></i>Low Stock Alerts</span>
          <span class="badge bg-danger"><?= count($low_stock) ?></span>
        </div>
        <div class="card-body p-2">
          <?php if (empty($low_stock)): ?>
            <div class="text-center text-muted py-3 small">All inventory levels are OK.</div>
          <?php else: foreach ($low_stock as $item): ?>
          <div class="d-flex justify-content-between align-items-center px-2 py-1 border-bottom">
            <div>
              <div class="fw-600 small"><?= e($item['item_name']) ?></div>
              <div class="text-muted" style="font-size:.72rem"><?= e($item['branch_name']) ?></div>
            </div>
            <span class="badge bg-danger"><?= $item['quantity'] . ' ' . $item['unit'] ?></span>
          </div>
          <?php endforeach; endif; ?>
          <div class="text-center pt-2">
            <a href="<?= SITE_URL ?>/modules/inventory/index.php" class="btn btn-sm btn-primary-grad">Manage Inventory</a>
          </div>
        </div>
      </div>

      <!-- Staff Performance -->
      <div class="card">
        <div class="card-header">
          <i class="fas fa-medal text-purple me-2"></i>Staff Performance (Today)
        </div>
        <div class="card-body p-2">
          <?php if (empty($staff_perf)): ?>
            <div class="text-center text-muted py-3 small">No data.</div>
          <?php else: foreach ($staff_perf as $idx => $s): ?>
          <div class="d-flex align-items-center px-2 py-1 border-bottom gap-2">
            <div class="user-avatar-sm flex-shrink-0">
              <?= strtoupper(substr($s['full_name'], 0, 1)) ?>
            </div>
            <div class="flex-grow-1">
              <div class="fw-600 small"><?= e($s['full_name']) ?></div>
              <div class="text-muted" style="font-size:.72rem"><?= $s['order_count'] ?> orders · <?= formatCurrency($s['total_sales']) ?></div>
            </div>
            <?php if ($idx === 0 && $s['order_count'] > 0): ?>
              <i class="fas fa-crown text-warning"></i>
            <?php endif; ?>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

    </div>
  </div><!-- /.row -->

</div><!-- /.page-content -->
<?php require_once '../../includes/footer.php'; ?>

<?php
$salesLabelsJson  = json_encode($sales_labels);
$salesDataJson    = json_encode($sales_data);
$branchNamesJson  = json_encode($branch_names);
$branchCountsJson = json_encode($branch_counts);
$svcLabelsJson    = json_encode($svc_labels);
$svcDataJson      = json_encode($svc_data);
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  buildSalesChart('salesChart', <?= $salesLabelsJson ?>, <?= $salesDataJson ?>);
  buildPaymentChart('paymentChart', <?= json_encode((float)$cash_today) ?>, <?= json_encode((float)$gcash_today) ?>, <?= json_encode((float)$unpaid_amt) ?>);
  buildBranchChart('branchChart', <?= $branchNamesJson ?>, <?= $branchCountsJson ?>);
  buildServiceChart('serviceChart', <?= $svcLabelsJson ?>, <?= $svcDataJson ?>);
});
</script>
