<?php
/**
 * Audit Logs Viewer
 */
require_once '../../config/database.php';
require_once '../../config/functions.php';
requireAuth(['owner', 'admin']);

$search    = $conn->real_escape_string(trim($_GET['search']  ?? ''));
$selUser   = (int)($_GET['user_id']  ?? 0);
$selBranch = (int)($_GET['branch_id'] ?? 0);
$dateFrom  = $conn->real_escape_string($_GET['date_from'] ?? date('Y-m-01'));
$dateTo    = $conn->real_escape_string($_GET['date_to']   ?? date('Y-m-d'));
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 50;
$offset    = ($page - 1) * $perPage;

$where = ["DATE(l.created_at) BETWEEN '$dateFrom' AND '$dateTo'"];
if ($search)    $where[] = "(l.action LIKE '%$search%' OR u.username LIKE '%$search%' OR u.full_name LIKE '%$search%')";
if ($selUser)   $where[] = "l.user_id = $selUser";
if ($selBranch) $where[] = "l.branch_id = $selBranch";
$whereSql = implode(' AND ', $where);

$total = $conn->query("
    SELECT COUNT(*) AS cnt FROM logs l
    LEFT JOIN users u ON u.id = l.user_id
    WHERE $whereSql
")->fetch_assoc()['cnt'];

$logs = $conn->query("
    SELECT l.*, u.full_name, u.username, u.role, b.name AS branch_name
    FROM logs l
    LEFT JOIN users u ON u.id = l.user_id
    LEFT JOIN branches b ON b.id = l.branch_id
    WHERE $whereSql
    ORDER BY l.created_at DESC
    LIMIT $perPage OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

$totalPages = ceil($total / $perPage);

// Filter dropdowns
$allUsers   = $conn->query("SELECT id,full_name,username FROM users ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$allBranches = $conn->query("SELECT id,name FROM branches WHERE status='active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Audit Logs';
$navTitle  = 'Audit Logs';
require_once '../../includes/head.php';
?>

<div class="app-layout">
<?php require_once '../../includes/sidebar.php'; ?>
<div class="main-content">
<?php require_once '../../includes/navbar.php'; ?>
<div class="page-content">

  <div class="page-header">
    <div>
      <h4><i class="fas fa-clipboard-list text-purple me-2"></i>Audit Logs</h4>
      <nav><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Audit Logs</li>
      </ol></nav>
    </div>
    <span class="badge bg-purple-soft text-purple fs-6"><?= number_format($total) ?> Records</span>
  </div>

  <!-- Filters -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-2">
          <label class="form-label small">From</label>
          <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label small">To</label>
          <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label small">User</label>
          <select name="user_id" class="form-select form-select-sm">
            <option value="">All Users</option>
            <?php foreach ($allUsers as $u): ?>
              <option value="<?= $u['id'] ?>" <?= $selUser==$u['id']?'selected':'' ?>>
                <?= e($u['full_name']) ?> (<?= e($u['username']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small">Branch</label>
          <select name="branch_id" class="form-select form-select-sm">
            <option value="">All Branches</option>
            <?php foreach ($allBranches as $b): ?>
              <option value="<?= $b['id'] ?>" <?= $selBranch==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small">Search Action / User</label>
          <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-1">
          <button type="submit" class="btn btn-primary-grad btn-sm w-100"><i class="fas fa-search"></i></button>
        </div>
      </form>
    </div>
  </div>

  <div class="card table-card">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Date / Time</th>
            <th>User</th>
            <th>Role</th>
            <th>Branch</th>
            <th>Action</th>
            <th>Table</th>
            <th>Record</th>
            <th>IP Address</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $i => $log): ?>
          <tr>
            <td class="text-muted small"><?= $offset + $i + 1 ?></td>
            <td class="text-muted small text-nowrap"><?= date('M d, Y h:i A', strtotime($log['created_at'])) ?></td>
            <td>
              <div class="fw-600"><?= e($log['full_name'] ?? 'System') ?></div>
              <div class="text-muted small"><?= e($log['username'] ?? '') ?></div>
            </td>
            <td>
              <span class="badge <?= $log['role']==='owner'?'stat-purple':($log['role']==='admin'?'bg-warning text-dark':'bg-light text-dark border') ?> rounded-pill small">
                <?= ucfirst($log['role'] ?? 'system') ?>
              </span>
            </td>
            <td class="text-muted small"><?= e($log['branch_name'] ?? '—') ?></td>
            <td>
              <?php
              $actClass = 'bg-light text-dark border';
              if (str_contains(strtolower($log['action']), 'login'))   $actClass = 'bg-success bg-opacity-10 text-success border border-success';
              if (str_contains(strtolower($log['action']), 'logout'))  $actClass = 'bg-secondary bg-opacity-10 text-secondary border';
              if (str_contains(strtolower($log['action']), 'created')) $actClass = 'bg-primary bg-opacity-10 text-primary border border-primary';
              if (str_contains(strtolower($log['action']), 'updated')) $actClass = 'bg-warning bg-opacity-10 text-warning border border-warning';
              if (str_contains(strtolower($log['action']), 'deleted')) $actClass = 'bg-danger bg-opacity-10 text-danger border border-danger';
              if (str_contains(strtolower($log['action']), 'payment')) $actClass = 'bg-purple-soft text-purple border';
              ?>
              <span class="badge <?= $actClass ?> text-wrap text-start"><?= e($log['action']) ?></span>
            </td>
            <td class="text-muted small font-monospace"><?= e($log['table_name'] ?? '—') ?></td>
            <td class="text-muted small"><?= $log['record_id'] ? '#'.$log['record_id'] : '—' ?></td>
            <td class="text-muted small font-monospace"><?= e($log['ip_address'] ?? '—') ?></td>
            <td>
              <?php if ($log['old_value'] || $log['new_value']): ?>
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#detail<?= $offset+$i ?>">
                <i class="fas fa-chevron-down"></i>
              </button>
              <?php endif; ?>
            </td>
          </tr>
          <?php if ($log['old_value'] || $log['new_value']): ?>
          <tr class="collapse" id="detail<?= $offset+$i ?>">
            <td colspan="10" class="bg-light">
              <div class="row g-2 p-2">
                <?php if ($log['old_value']): ?>
                <div class="col-md-6">
                  <label class="small text-danger fw-600">Before:</label>
                  <pre class="bg-white border rounded p-2 small mb-0"><?= e($log['old_value']) ?></pre>
                </div>
                <?php endif; ?>
                <?php if ($log['new_value']): ?>
                <div class="col-md-6">
                  <label class="small text-success fw-600">After:</label>
                  <pre class="bg-white border rounded p-2 small mb-0"><?= e($log['new_value']) ?></pre>
                </div>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endif; ?>
          <?php endforeach; ?>
          <?php if (!$logs): ?><tr><td colspan="10" class="text-center text-muted py-4">No audit records found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <span class="text-muted small">
        Showing <?= $offset+1 ?>–<?= min($offset+$perPage, $total) ?> of <?= number_format($total) ?> records
      </span>
      <nav>
        <ul class="pagination pagination-sm mb-0">
          <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
            <li class="page-item <?= $p==$page?'active':'' ?>">
              <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    </div>
    <?php endif; ?>
  </div>

</div>
<?php require_once '../../includes/footer.php'; ?>
