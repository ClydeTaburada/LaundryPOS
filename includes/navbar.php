<?php
/**
 * includes/navbar.php
 * Top navigation bar
 */
$role = $_SESSION['role'] ?? 'staff';
?>
<header class="top-navbar" id="topNavbar">
  <!-- Mobile toggle -->
  <button class="btn sidebar-toggle me-3" onclick="toggleSidebar(true)" aria-label="Toggle navigation">
    <i class="fas fa-bars fa-lg"></i>
  </button>

  <!-- Page heading slot (set $navTitle before include) -->
  <div class="nav-title">
    <h5 class="mb-0"><?= e($navTitle ?? 'Dashboard') ?></h5>
    <?php if (!empty($navSubtitle)): ?>
      <small class="text-muted"><?= e($navSubtitle) ?></small>
    <?php endif; ?>
  </div>

  <div class="nav-right ms-auto d-flex align-items-center gap-2">

    <!-- Branch selector (owner/admin) -->
    <?php if (in_array($role, ['owner', 'admin'], true)): ?>
    <select class="form-select form-select-sm branch-select d-none d-md-block"
            id="globalBranchFilter"
            onchange="applyBranchFilter(this.value)"
            style="width:auto;max-width:180px;">
      <option value="0">All Branches</option>
      <?php
        $navBranches = getBranches();
        foreach ($navBranches as $b):
      ?>
        <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>

    <!-- Low-stock alert bell -->
    <?php $lowStock = getLowStockCount(); ?>
    <div class="position-relative">
      <a href="<?= SITE_URL ?>/modules/inventory/index.php" class="nav-icon-btn" title="Inventory Alerts">
        <i class="fas fa-boxes-stacked"></i>
        <?php if ($lowStock > 0): ?>
          <span class="badge rounded-pill bg-danger notif-dot"><?= $lowStock ?></span>
        <?php endif; ?>
      </a>
    </div>

    <!-- Notifications (active orders) -->
    <?php
      global $conn;
      $bf   = branchFilter('o');
      $pend = $conn->query("SELECT COUNT(*) AS c FROM orders o WHERE o.status NOT IN ('claimed') AND $bf")->fetch_assoc()['c'] ?? 0;
    ?>
    <div class="position-relative">
      <button class="nav-icon-btn" id="notifBtn" title="Active Orders">
        <i class="fas fa-bell"></i>
        <?php if ($pend > 0): ?>
          <span class="badge rounded-pill bg-warning text-dark notif-dot"><?= $pend ?></span>
        <?php endif; ?>
      </button>
    </div>

    <!-- User dropdown -->
    <div class="dropdown">
      <button class="btn d-flex align-items-center gap-2 user-dropdown-btn" data-bs-toggle="dropdown">
        <div class="user-avatar-sm">
          <?= strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="d-none d-md-block text-start">
          <div class="fw-semibold lh-1" style="font-size:.85rem"><?= e($_SESSION['full_name'] ?? '') ?></div>
          <div class="text-muted" style="font-size:.72rem"><?= ucfirst($role) ?></div>
        </div>
        <i class="fas fa-chevron-down fa-xs text-muted"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm mt-1">
        <li><h6 class="dropdown-header">
          <i class="fas fa-user-circle me-1"></i><?= e($_SESSION['username'] ?? '') ?>
        </h6></li>
        <li><hr class="dropdown-divider my-1"></li>
        <li>
          <a class="dropdown-item" href="<?= SITE_URL ?>/modules/settings/index.php">
            <i class="fas fa-cog me-2 text-muted"></i>Settings
          </a>
        </li>
        <li>
          <a class="dropdown-item text-danger" href="<?= SITE_URL ?>/auth/logout.php">
            <i class="fas fa-sign-out-alt me-2"></i>Logout
          </a>
        </li>
      </ul>
    </div>

  </div>
</header>
