<?php
/**
 * Inventory Management
 */
require_once '../../config/database.php';
require_once '../../config/functions.php';
requireAuth();

$bf       = branchFilter('i');
$branches = getBranches();
$msg      = '';
$msgType  = 'success';

// ── CRUD handlers ────────────────────────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $bid   = (int)($_POST['branch_id'] ?? $_SESSION['branch_id'] ?? 0);
    $name  = trim($_POST['item_name'] ?? '');
    $cat   = $conn->real_escape_string($_POST['category'] ?? '');
    $qty   = (float)($_POST['quantity'] ?? 0);
    $unit  = $conn->real_escape_string(trim($_POST['unit'] ?? 'pcs'));
    $thresh = (float)($_POST['low_stock_threshold'] ?? 10);
    $cost  = (float)($_POST['cost_per_unit'] ?? 0);
    $supp  = trim($_POST['supplier'] ?? '');

    if ($bid && $name) {
        $stmt = $conn->prepare("INSERT INTO inventory (branch_id,item_name,category,quantity,unit,low_stock_threshold,cost_per_unit,supplier) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('issdsdds', $bid, $name, $cat, $qty, $unit, $thresh, $cost, $supp);
        if ($stmt->execute()) { logAction("Added inventory: $name", 'inventory', $conn->insert_id); $msg = "Item \"$name\" added."; }
        else { $msg = $stmt->error; $msgType = 'danger'; }
        $stmt->close();
    }
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $iid   = (int)$_POST['id'];
    $name  = trim($_POST['item_name'] ?? '');
    $cat   = $conn->real_escape_string($_POST['category'] ?? '');
    $thresh = (float)($_POST['low_stock_threshold'] ?? 10);
    $cost  = (float)($_POST['cost_per_unit'] ?? 0);
    $supp  = $conn->real_escape_string(trim($_POST['supplier'] ?? ''));
    $bid   = (int)$_POST['branch_id'];

    $stmt = $conn->prepare("UPDATE inventory SET branch_id=?,item_name=?,category=?,low_stock_threshold=?,cost_per_unit=?,supplier=? WHERE id=?");
    $stmt->bind_param('issddsi', $bid, $name, $cat, $thresh, $cost, $supp, $iid);
    if ($stmt->execute()) { logAction("Updated inventory #$iid", 'inventory', $iid); $msg = "Item updated."; }
    else { $msg = $stmt->error; $msgType = 'danger'; }
    $stmt->close();
}

if ($action === 'adjust' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $iid        = (int)$_POST['id'];
    $adjAction  = $conn->real_escape_string($_POST['adj_action'] ?? 'add');
    $adjQty     = (float)($_POST['adj_qty'] ?? 0);
    $adjNotes   = $conn->real_escape_string(trim($_POST['adj_notes'] ?? ''));
    $staff_id   = (int)$_SESSION['user_id'];

    $current = $conn->query("SELECT quantity, branch_id FROM inventory WHERE id=$iid")->fetch_assoc();
    if ($current) {
        $before = $current['quantity'];
        if ($adjAction === 'add')    $after = $before + $adjQty;
        elseif ($adjAction === 'deduct') $after = max(0, $before - $adjQty);
        else                         $after = $adjQty; // adjust = set absolute

        $conn->query("UPDATE inventory SET quantity=$after WHERE id=$iid");
        $change = $after - $before;
        $bid    = $current['branch_id'];
        $stmt2  = $conn->prepare("INSERT INTO inventory_logs (inventory_id,branch_id,user_id,action,quantity_change,quantity_before,quantity_after,notes) VALUES (?,?,?,?,?,?,?,?)");
        $stmt2->bind_param('iiisddds', $iid, $bid, $staff_id, $adjAction, $change, $before, $after, $adjNotes);
        $stmt2->execute();
        $stmt2->close();
        logAction("Adjusted inventory #$iid: $adjAction $adjQty", 'inventory', $iid);
        $msg = "Inventory updated.";
    }
}

if ($action === 'delete' && isset($_GET['id'])) {
    $iid = (int)$_GET['id'];
    $conn->query("DELETE FROM inventory WHERE id=$iid");
    logAction("Deleted inventory #$iid", 'inventory', $iid);
    header('Location: index.php?msg=deleted'); exit;
}

$filter_cat    = $_GET['cat']       ?? '';
$filter_branch = (int)($_GET['branch_id'] ?? 0);
$filter_low    = isset($_GET['low_stock']);
$search        = trim($_GET['q']    ?? '');

$whereArr = ["$bf"];
if ($filter_cat)    $whereArr[] = "i.category = '" . $conn->real_escape_string($filter_cat) . "'";
if ($filter_branch && in_array($_SESSION['role'],['owner','admin'],true)) $whereArr[] = "i.branch_id = $filter_branch";
if ($filter_low)    $whereArr[] = "i.quantity <= i.low_stock_threshold";
if ($search)        $whereArr[] = "i.item_name LIKE '%" . $conn->real_escape_string($search) . "%'";

$items = $conn->query("
    SELECT i.*, b.name AS branch_name
    FROM inventory i JOIN branches b ON b.id = i.branch_id
    WHERE " . implode(' AND ', $whereArr) . "
    ORDER BY i.branch_id, i.item_name
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Inventory';
$navTitle  = 'Inventory Management';
require_once '../../includes/head.php';
?>

<div class="app-layout">
<?php require_once '../../includes/sidebar.php'; ?>
<div class="main-content">
<?php require_once '../../includes/navbar.php'; ?>
<div class="page-content">

  <div class="page-header">
    <div>
      <h4><i class="fas fa-boxes-stacked text-purple me-2"></i>Inventory</h4>
      <nav><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Inventory</li>
      </ol></nav>
    </div>
    <button class="btn btn-primary-grad" data-bs-toggle="modal" data-bs-target="#invModal">
      <i class="fas fa-plus me-1"></i>Add Item
    </button>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible auto-dismiss">
      <i class="fas fa-check-circle me-2"></i><?= e($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if (isset($_GET['msg']) && $_GET['msg']==='deleted'): ?>
    <div class="alert alert-warning alert-dismissible auto-dismiss">Item deleted.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <!-- Low-stock summary cards -->
  <?php
    $lowItems = array_filter($items, fn($i) => $i['quantity'] <= $i['low_stock_threshold']);
    if (!empty($lowItems)):
  ?>
  <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <i class="fas fa-triangle-exclamation fa-lg"></i>
    <div><strong><?= count($lowItems) ?> low-stock item(s)</strong> need restocking.
      <a href="?low_stock=1" class="alert-link ms-1">View all</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Filters -->
  <form method="GET" class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2">
      <input type="text" name="q" class="form-control" placeholder="🔍 Search item..." value="<?= e($search) ?>" style="max-width:220px">
      <select name="cat" class="form-select" style="max-width:180px">
        <option value="">All Categories</option>
        <option value="detergent"          <?= $filter_cat==='detergent'          ? 'selected':'' ?>>Detergent</option>
        <option value="fabric_conditioner" <?= $filter_cat==='fabric_conditioner' ? 'selected':'' ?>>Fabric Conditioner</option>
        <option value="packaging"          <?= $filter_cat==='packaging'          ? 'selected':'' ?>>Packaging</option>
        <option value="other"              <?= $filter_cat==='other'              ? 'selected':'' ?>>Other</option>
      </select>
      <?php if (in_array($_SESSION['role'],['owner','admin'],true)): ?>
      <select name="branch_id" class="form-select" style="max-width:200px">
        <option value="0">All Branches</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $filter_branch===$b['id'] ?'selected':'' ?>><?= e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <div class="form-check align-self-center">
        <input class="form-check-input" type="checkbox" name="low_stock" id="cbLow" <?= $filter_low?'checked':'' ?>>
        <label class="form-check-label" for="cbLow">Low Stock Only</label>
      </div>
      <button type="submit" class="btn btn-outline-primary"><i class="fas fa-filter"></i></button>
      <a href="?" class="btn btn-outline-secondary"><i class="fas fa-undo"></i></a>
    </div>
  </form>

  <div class="card table-card">
    <div class="table-responsive">
      <table class="table mb-0" id="mainTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Item Name</th>
            <th>Category</th>
            <th>Quantity</th>
            <th>Unit</th>
            <th>Low Stock Threshold</th>
            <th>Cost/Unit</th>
            <th>Supplier</th>
            <th>Branch</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="11" class="text-center text-muted py-5">
              <i class="fas fa-boxes-stacked fa-2x d-block mb-2 opacity-25"></i>No inventory items found.
            </td></tr>
          <?php else: foreach ($items as $i => $item): ?>
          <?php $isLow = $item['quantity'] <= $item['low_stock_threshold']; ?>
          <tr class="<?= $isLow ? 'table-warning' : '' ?>">
            <td class="text-muted small"><?= $i + 1 ?></td>
            <td class="fw-600"><?= e($item['item_name']) ?></td>
            <td><?= ucfirst(str_replace('_', ' ', $item['category'])) ?></td>
            <td>
              <span class="fw-700 <?= $isLow ? 'text-danger' : 'text-success' ?>">
                <?= $item['quantity'] ?>
              </span>
            </td>
            <td><?= e($item['unit']) ?></td>
            <td><?= $item['low_stock_threshold'] . ' ' . e($item['unit']) ?></td>
            <td><?= formatCurrency($item['cost_per_unit']) ?></td>
            <td><?= e($item['supplier'] ?? '—') ?></td>
            <td><span class="badge bg-purple-soft text-purple"><?= e($item['branch_name']) ?></span></td>
            <td>
              <?php if ($isLow): ?>
                <span class="badge bg-danger"><i class="fas fa-exclamation me-1"></i>Low Stock</span>
              <?php else: ?>
                <span class="badge bg-success">OK</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex gap-1">
                <button class="btn btn-sm btn-outline-primary"
                        onclick='editInvItem(<?= json_encode($item) ?>)'
                        data-bs-toggle="modal" data-bs-target="#invModal"
                        title="Edit"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-outline-success"
                        onclick='adjustInvItem(<?= $item['id'] ?>, "<?= e($item['item_name']) ?>", <?= $item['quantity'] ?>, "<?= e($item['unit']) ?>")'
                        data-bs-toggle="modal" data-bs-target="#adjModal"
                        title="Adjust"><i class="fas fa-sliders"></i></button>
                <a href="?action=delete&id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Delete this item?')"
                   title="Delete"><i class="fas fa-trash"></i></a>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="invModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" id="invAction" value="create">
        <input type="hidden" name="id" id="invId">
        <div class="modal-header">
          <h5 class="modal-title" id="invModalTitle"><i class="fas fa-plus me-2"></i>Add Inventory Item</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Item Name *</label>
              <input type="text" name="item_name" id="invName" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Category *</label>
              <select name="category" id="invCat" class="form-select" required>
                <option value="detergent">Detergent</option>
                <option value="fabric_conditioner">Fabric Conditioner</option>
                <option value="packaging">Packaging</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-4" id="invQtyGroup">
              <label class="form-label">Initial Quantity *</label>
              <input type="number" name="quantity" id="invQty" class="form-control" step="0.01" min="0" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">Unit</label>
              <input type="text" name="unit" id="invUnit" class="form-control" placeholder="kg, L, pcs...">
            </div>
            <div class="col-md-4">
              <label class="form-label">Low Stock Threshold</label>
              <input type="number" name="low_stock_threshold" id="invThresh" class="form-control" step="0.01" min="0" value="10">
            </div>
            <div class="col-md-4">
              <label class="form-label">Cost per Unit (₱)</label>
              <input type="number" name="cost_per_unit" id="invCost" class="form-control" step="0.01" min="0" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">Supplier</label>
              <input type="text" name="supplier" id="invSupp" class="form-control" placeholder="Supplier name">
            </div>
            <div class="col-md-4">
              <label class="form-label">Branch *</label>
              <select name="branch_id" id="invBranch" class="form-select" required>
                <?php foreach ($branches as $b): ?>
                  <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                <?php endforeach; ?>
              </select>
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

<!-- Adjust Modal -->
<div class="modal fade" id="adjModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="adjust">
        <input type="hidden" name="id" id="adjId">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-sliders me-2"></i>Adjust Stock — <span id="adjItemName"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info py-2 mb-3">
            Current stock: <strong id="adjCurrentQty"></strong> <span id="adjUnit"></span>
          </div>
          <div class="mb-3">
            <label class="form-label">Adjustment Type</label>
            <div class="d-flex gap-3">
              <div class="form-check"><input class="form-check-input" type="radio" name="adj_action" value="add" id="adjAdd" checked><label class="form-check-label text-success" for="adjAdd"><i class="fas fa-plus me-1"></i>Add Stock</label></div>
              <div class="form-check"><input class="form-check-input" type="radio" name="adj_action" value="deduct" id="adjDed"><label class="form-check-label text-danger" for="adjDed"><i class="fas fa-minus me-1"></i>Deduct</label></div>
              <div class="form-check"><input class="form-check-input" type="radio" name="adj_action" value="adjust" id="adjSet"><label class="form-check-label text-warning" for="adjSet"><i class="fas fa-equals me-1"></i>Set Exact</label></div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Quantity</label>
            <input type="number" name="adj_qty" class="form-control" step="0.01" min="0" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Notes</label>
            <input type="text" name="adj_notes" class="form-control" placeholder="Reason for adjustment">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-grad btn-sm"><i class="fas fa-check me-1"></i>Adjust Stock</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
<script>
function editInvItem(item) {
  document.getElementById('invAction').value  = 'update';
  document.getElementById('invId').value      = item.id;
  document.getElementById('invName').value    = item.item_name;
  document.getElementById('invCat').value     = item.category;
  document.getElementById('invUnit').value    = item.unit;
  document.getElementById('invThresh').value  = item.low_stock_threshold;
  document.getElementById('invCost').value    = item.cost_per_unit;
  document.getElementById('invSupp').value    = item.supplier || '';
  document.getElementById('invBranch').value  = item.branch_id;
  document.getElementById('invQtyGroup').style.display = 'none';
  document.getElementById('invModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Item';
}
function adjustInvItem(id, name, qty, unit) {
  document.getElementById('adjId').value = id;
  document.getElementById('adjItemName').textContent = name;
  document.getElementById('adjCurrentQty').textContent = qty;
  document.getElementById('adjUnit').textContent = unit;
}
document.getElementById('invModal').addEventListener('hidden.bs.modal', function() {
  document.getElementById('invAction').value = 'create';
  document.getElementById('invQtyGroup').style.display = '';
  document.getElementById('invModalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Add Inventory Item';
  this.querySelector('form').reset();
});
</script>
