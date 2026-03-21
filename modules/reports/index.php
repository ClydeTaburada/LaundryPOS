<?php
/**
 * Reports Module
 */
require_once '../../config/database.php';
require_once '../../config/functions.php';
requireAuth(['owner', 'admin', 'staff']);

$branches   = getBranches();
$branchFilter = getBranchFilter();

// Date range
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');
$selBranch = isset($_GET['branch_id']) && ($_SESSION['role'] ?? 'staff') !== 'staff'
             ? (int)$_GET['branch_id'] : null;

// Build dynamic branch condition
if ($selBranch) {
    $bCond  = "branch_id = $selBranch";
    $bCondO = "o.branch_id = $selBranch";
} else {
    $bCond  = $branchFilter;
    $bCondO = getBranchFilter('o');
}

$df = $conn->real_escape_string($dateFrom);
$dt = $conn->real_escape_string($dateTo);
$dateCond = "DATE(created_at) BETWEEN '$df' AND '$dt'";

// ── Summary KPIs ──────────────────────────────────────────────────────────────
$summary = $conn->query("
    SELECT
        COUNT(*) AS total_orders,
        SUM(total_amount) AS gross_revenue,
        SUM(paid_amount)  AS collected,
        SUM(total_amount - paid_amount) AS outstanding,
        SUM(CASE WHEN status='claimed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN payment_status='unpaid' THEN 1 ELSE 0 END) AS unpaid_orders,
        AVG(total_amount) AS avg_order_value
    FROM orders
    WHERE $bCond AND $dateCond
")->fetch_assoc();

// ── Daily Sales for Chart ─────────────────────────────────────────────────────
$dailySalesRows = $conn->query("
    SELECT DATE(created_at) AS day, SUM(total_amount) AS total, COUNT(*) AS orders
    FROM orders
    WHERE $bCond AND $dateCond
    GROUP BY DATE(created_at)
    ORDER BY day
")->fetch_all(MYSQLI_ASSOC);

$salesLabels = json_encode(array_column($dailySalesRows, 'day'));
$salesData   = json_encode(array_map('floatval', array_column($dailySalesRows, 'total')));
$orderCounts = json_encode(array_map('intval', array_column($dailySalesRows, 'orders')));

// ── Service Breakdown ─────────────────────────────────────────────────────────
$serviceRows = $conn->query("
    SELECT service_type, COUNT(*) AS cnt, SUM(total_amount) AS revenue
    FROM orders
    WHERE $bCond AND $dateCond
    GROUP BY service_type
")->fetch_all(MYSQLI_ASSOC);

$svcLabels  = json_encode(array_map(fn($r) => serviceLabel($r['service_type']), $serviceRows));
$svcRevenue = json_encode(array_map(fn($r) => (float)$r['revenue'], $serviceRows));
$svcCounts  = json_encode(array_map(fn($r) => (int)$r['cnt'], $serviceRows));

// ── Payment Methods ───────────────────────────────────────────────────────────
$payRows = $conn->query("
    SELECT p.payment_method, SUM(p.amount) AS total
    FROM payments p
    JOIN orders o ON o.id = p.order_id
    WHERE $bCondO AND DATE(p.created_at) BETWEEN '$df' AND '$dt'
    GROUP BY p.payment_method
")->fetch_all(MYSQLI_ASSOC);

$payLabels = json_encode(array_column($payRows, 'payment_method') ?: ['No Data']);
$payData   = json_encode(array_map(fn($r) => (float)$r['total'], $payRows) ?: [0]);

// ── Branch Performance ────────────────────────────────────────────────────────
$branchPerf = $conn->query("
    SELECT b.name, COUNT(o.id) AS orders, SUM(o.total_amount) AS revenue, SUM(o.paid_amount) AS collected
    FROM branches b
    LEFT JOIN orders o ON o.branch_id = b.id AND DATE(o.created_at) BETWEEN '$df' AND '$dt'
    WHERE b.status='active'
    GROUP BY b.id, b.name
    ORDER BY revenue DESC
")->fetch_all(MYSQLI_ASSOC);

$branchNames_chart   = json_encode(array_column($branchPerf, 'name'));
$branchRevenue_chart = json_encode(array_map(fn($r) => (float)$r['revenue'], $branchPerf));

// ── Top Staff ─────────────────────────────────────────────────────────────────
$topStaff = $conn->query("
    SELECT u.full_name, b.name AS branch,
           COUNT(o.id) AS orders,
           SUM(o.total_amount) AS revenue,
           SUM(CASE WHEN o.status='claimed' THEN 1 ELSE 0 END) AS completed
    FROM users u
    JOIN orders o ON o.staff_id = u.id
    LEFT JOIN branches b ON b.id = u.branch_id
    WHERE u.role = 'staff' AND $bCondO AND DATE(o.created_at) BETWEEN '$df' AND '$dt'
    GROUP BY u.id, u.full_name, b.name
    ORDER BY revenue DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// ── Status Breakdown ──────────────────────────────────────────────────────────
$statusRows = $conn->query("
    SELECT status, COUNT(*) AS cnt
    FROM orders
    WHERE $bCond AND $dateCond
    GROUP BY status
")->fetch_all(MYSQLI_ASSOC);

$statusLabels = json_encode(array_map(fn($r) => ucfirst($r['status']), $statusRows));
$statusData   = json_encode(array_map(fn($r) => (int)$r['cnt'], $statusRows));

$pageTitle = 'Reports';
$navTitle  = 'Reports & Analytics';
require_once '../../includes/head.php';
?>

<div class="app-layout">
<?php require_once '../../includes/sidebar.php'; ?>
<div class="main-content">
<?php require_once '../../includes/navbar.php'; ?>
<div class="page-content">

  <div class="page-header">
    <div>
      <h4><i class="fas fa-chart-bar text-purple me-2"></i>Reports &amp; Analytics</h4>
      <nav><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Reports</li>
      </ol></nav>
    </div>
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
      <i class="fas fa-print me-1"></i>Print Report
    </button>
  </div>

  <!-- Filters -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label small">From Date</label>
          <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label small">To Date</label>
          <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
        </div>
        <?php if (($_SESSION['role'] ?? 'staff') !== 'staff'): ?>
        <div class="col-md-3">
          <label class="form-label small">Branch</label>
          <select name="branch_id" class="form-select form-select-sm">
            <option value="">All Branches</option>
            <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>" <?= $selBranch==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary-grad btn-sm w-100">
            <i class="fas fa-search me-1"></i>Run Report
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- KPI Summary -->
  <div class="row g-3 mb-4">
    <div class="col-xl-2 col-md-4 col-6">
      <div class="stat-card stat-purple">
        <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
        <div class="stat-value"><?= number_format($summary['total_orders'] ?? 0) ?></div>
        <div class="stat-label">Total Orders</div>
      </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
      <div class="stat-card stat-cyan">
        <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
        <div class="stat-value"><?= formatCurrency($summary['gross_revenue'] ?? 0) ?></div>
        <div class="stat-label">Gross Revenue</div>
      </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
      <div class="stat-card stat-green">
        <div class="stat-icon"><i class="fas fa-hand-holding-dollar"></i></div>
        <div class="stat-value"><?= formatCurrency($summary['collected'] ?? 0) ?></div>
        <div class="stat-label">Collected</div>
      </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
      <div class="stat-card stat-orange">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-value"><?= formatCurrency($summary['outstanding'] ?? 0) ?></div>
        <div class="stat-label">Outstanding</div>
      </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
      <div class="stat-card stat-blue">
        <div class="stat-icon"><i class="fas fa-check-double"></i></div>
        <div class="stat-value"><?= number_format($summary['completed'] ?? 0) ?></div>
        <div class="stat-label">Completed</div>
      </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
      <div class="stat-card stat-pink">
        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        <div class="stat-value"><?= formatCurrency($summary['avg_order_value'] ?? 0) ?></div>
        <div class="stat-label">Avg Order Value</div>
      </div>
    </div>
  </div>

  <!-- Charts Row 1 -->
  <div class="row g-3 mb-4">
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0"><i class="fas fa-chart-line text-purple me-2"></i>Daily Sales Trend</h6>
        </div>
        <div class="card-body">
          <canvas id="salesChart" height="100"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <h6 class="mb-0"><i class="fas fa-chart-pie text-cyan me-2"></i>Order Status</h6>
        </div>
        <div class="card-body d-flex align-items-center justify-content-center">
          <canvas id="statusChart" height="200"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts Row 2 -->
  <div class="row g-3 mb-4">
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <h6 class="mb-0"><i class="fas fa-chart-donut text-purple me-2"></i>Payment Methods</h6>
        </div>
        <div class="card-body d-flex align-items-center justify-content-center">
          <canvas id="payChart" height="180"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <h6 class="mb-0"><i class="fas fa-soap text-cyan me-2"></i>Revenue by Service</h6>
        </div>
        <div class="card-body d-flex align-items-center justify-content-center">
          <canvas id="svcChart" height="180"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <h6 class="mb-0"><i class="fas fa-building text-purple me-2"></i>Revenue by Branch</h6>
        </div>
        <div class="card-body">
          <canvas id="branchChart" height="180"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <!-- Staff Performance -->
    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-header">
          <h6 class="mb-0"><i class="fas fa-users text-purple me-2"></i>Staff Performance</h6>
        </div>
        <div class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr><th>#</th><th>Staff</th><th>Branch</th><th>Orders</th><th>Revenue</th><th>Completed</th></tr>
            </thead>
            <tbody>
              <?php foreach ($topStaff as $i => $s): ?>
              <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td><?= e($s['full_name']) ?></td>
                <td class="text-muted small"><?= e($s['branch'] ?? '—') ?></td>
                <td><span class="badge bg-light text-dark border"><?= $s['orders'] ?></span></td>
                <td class="fw-600 text-purple"><?= formatCurrency($s['revenue']) ?></td>
                <td><span class="badge bg-success"><?= $s['completed'] ?></span></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$topStaff): ?><tr><td colspan="6" class="text-center text-muted">No data for selected period</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Branch Summary Table -->
    <div class="col-lg-5">
      <div class="card h-100">
        <div class="card-header">
          <h6 class="mb-0"><i class="fas fa-store text-cyan me-2"></i>Branch Summary</h6>
        </div>
        <div class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr><th>Branch</th><th>Orders</th><th>Revenue</th><th>Collected</th></tr>
            </thead>
            <tbody>
              <?php foreach ($branchPerf as $bp): ?>
              <tr>
                <td><?= e($bp['name']) ?></td>
                <td><span class="badge bg-light text-dark border"><?= $bp['orders'] ?? 0 ?></span></td>
                <td class="fw-600"><?= formatCurrency($bp['revenue']) ?></td>
                <td><?= formatCurrency($bp['collected']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<?php
$extraJs = [];
require_once '../../includes/footer.php';
?>
<script>
const PURPLE = '#8A2BE2', CYAN = '#00CED1', GREEN = '#28a745', ORANGE = '#fd7e14', BLUE = '#0d6efd';

// Sales Chart
new Chart(document.getElementById('salesChart'), {
    type: 'line',
    data: {
        labels: <?= $salesLabels ?>,
        datasets: [
            { label: 'Revenue (₱)', data: <?= $salesData ?>, borderColor: PURPLE, backgroundColor: 'rgba(138,43,226,0.08)', fill: true, tension: 0.4, yAxisID: 'y' },
            { label: 'Orders',      data: <?= $orderCounts ?>, borderColor: CYAN,   backgroundColor: 'transparent', tension: 0.4, yAxisID: 'y1', borderDash: [5,5] }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } },
        scales: {
            y:  { type: 'linear', display: true, position: 'left',  ticks: { callback: v => '₱'+v.toLocaleString() } },
            y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false } }
        }
    }
});

// Status Doughnut
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: <?= $statusLabels ?>,
        datasets: [{ data: <?= $statusData ?>, backgroundColor: ['#6c757d', PURPLE, CYAN, '#28a745', '#0d6efd'] }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } }, cutout: '65%' }
});

// Payment Doughnut
new Chart(document.getElementById('payChart'), {
    type: 'doughnut',
    data: {
        labels: <?= $payLabels ?>,
        datasets: [{ data: <?= $payData ?>, backgroundColor: [GREEN, CYAN, BLUE, ORANGE] }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } }, cutout: '65%' }
});

// Service Bar
new Chart(document.getElementById('svcChart'), {
    type: 'bar',
    data: {
        labels: <?= $svcLabels ?>,
        datasets: [{ label: 'Revenue (₱)', data: <?= $svcRevenue ?>, backgroundColor: [PURPLE, CYAN, ORANGE] }]
    },
    options: { responsive: true, plugins: { legend: { display: false } },
        scales: { y: { ticks: { callback: v => '₱'+v.toLocaleString() } } }
    }
});

// Branch Bar
new Chart(document.getElementById('branchChart'), {
    type: 'bar',
    data: {
        labels: <?= $branchNames_chart ?>,
        datasets: [{ label: 'Revenue (₱)', data: <?= $branchRevenue_chart ?>,
            backgroundColor: [PURPLE, CYAN, GREEN, ORANGE, BLUE, '#e83e8c'] }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, indexAxis: 'y',
        scales: { x: { ticks: { callback: v => '₱'+v.toLocaleString() } } }
    }
});
</script>
