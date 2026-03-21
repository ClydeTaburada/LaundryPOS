<?php
/**
 * includes/sidebar.php
 * Requires: $_SESSION['role'], $_SESSION['full_name'], SITE_URL
 */
$currentPath = $_SERVER['PHP_SELF'] ?? '';
$role        = $_SESSION['role'] ?? 'staff';

function sideActive(string $path, string $current): string {
    return str_contains($current, $path) ? 'active' : '';
}
?>
<!-- ═══ Sidebar ═══════════════════════════════════════════════════════════════ -->
<nav class="sidebar" id="sidebar">

  <!-- Brand -->
  <div class="sidebar-brand">
    <a href="<?= SITE_URL ?>/modules/dashboard/index.php" class="text-decoration-none">
      <img src="<?= SITE_URL ?>/assets/img/logo.png" alt="Logo" class="sidebar-logo"
           onerror="this.style.display='none'">
      <div class="sidebar-brand-text">
        <span class="brand-name">Lavenderia</span>
        <span class="brand-sub">Laundry Services</span>
      </div>
    </a>
  </div>

  <!-- Branch badge -->
  <?php if ($role === 'staff' && !empty($_SESSION['branch_id'])): ?>
  <div class="sidebar-branch-badge">
    <i class="fas fa-map-marker-alt me-1"></i>
    <?= e(getBranchName((int) $_SESSION['branch_id'])) ?>
  </div>
  <?php endif; ?>

  <!-- Nav links -->
  <ul class="sidebar-nav">

    <li class="nav-label">Main</li>

    <li class="<?= sideActive('dashboard', $currentPath) ?>">
      <a href="<?= SITE_URL ?>/modules/dashboard/index.php">
        <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
      </a>
    </li>

    <li class="<?= sideActive('orders', $currentPath) ?>">
      <a href="<?= SITE_URL ?>/modules/orders/index.php">
        <i class="fas fa-shopping-bag"></i><span>Orders</span>
        <?php
          global $conn;
          $bf  = branchFilter();
          $cnt = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status NOT IN ('claimed') AND $bf")->fetch_assoc()['c'] ?? 0;
          if ($cnt > 0) echo "<span class='badge pill-badge'>$cnt</span>";
        ?>
      </a>
    </li>

    <li class="<?= sideActive('customers', $currentPath) ?>">
      <a href="<?= SITE_URL ?>/modules/customers/index.php">
        <i class="fas fa-users"></i><span>Customers</span>
      </a>
    </li>

    <li class="nav-label">Finance</li>

    <li class="<?= sideActive('payments', $currentPath) ?>">
      <a href="<?= SITE_URL ?>/modules/payments/index.php">
        <i class="fas fa-credit-card"></i><span>Payments</span>
      </a>
    </li>

    <li class="nav-label">Operations</li>

    <li class="<?= sideActive('inventory', $currentPath) ?>">
      <a href="<?= SITE_URL ?>/modules/inventory/index.php">
        <i class="fas fa-boxes-stacked"></i><span>Inventory</span>
        <?php
          $low = getLowStockCount();
          if ($low > 0) echo "<span class='badge pill-badge-danger'>$low</span>";
        ?>
      </a>
    </li>

    <li class="<?= sideActive('reports', $currentPath) ?>">
      <a href="<?= SITE_URL ?>/modules/reports/index.php">
        <i class="fas fa-chart-bar"></i><span>Reports</span>
      </a>
    </li>

    <?php if (in_array($role, ['owner', 'admin'], true)): ?>
    <li class="nav-label">Admin</li>

    <li class="<?= sideActive('branches', $currentPath) ?>">
      <a href="<?= SITE_URL ?>/modules/branches/index.php">
        <i class="fas fa-store"></i><span>Branches</span>
      </a>
    </li>

    <li class="<?= sideActive('staff', $currentPath) ?>">
      <a href="<?= SITE_URL ?>/modules/staff/index.php">
        <i class="fas fa-id-badge"></i><span>Staff</span>
      </a>
    </li>

    <li class="<?= sideActive('logs', $currentPath) ?>">
      <a href="<?= SITE_URL ?>/modules/logs/index.php">
        <i class="fas fa-clipboard-list"></i><span>Audit Logs</span>
      </a>
    </li>
    <?php endif; ?>

  </ul>

  <!-- Sidebar footer -->
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar">
        <?= strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)) ?>
      </div>
      <div class="user-info">
        <span class="user-name"><?= e($_SESSION['full_name'] ?? '') ?></span>
        <span class="user-role"><?= ucfirst($role) ?></span>
      </div>
      <a href="<?= SITE_URL ?>/auth/logout.php" class="logout-btn" title="Logout">
        <i class="fas fa-sign-out-alt"></i>
      </a>
    </div>
  </div>

</nav>
<!-- ═══ Sidebar overlay (mobile) ════════════════════════════════════════════ -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar(false)"></div>
