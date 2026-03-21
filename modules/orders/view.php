<?php
/**
 * View / Edit Order
 */
require_once '../../config/database.php';
require_once '../../config/functions.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

// Fetch order
$stmt = $conn->prepare("
    SELECT o.*, c.name AS customer_name, c.phone AS customer_phone,
           b.name AS branch_name, u.full_name AS staff_name
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    JOIN branches b ON b.id = o.branch_id
    JOIN users u ON u.id = o.staff_id
    WHERE o.id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) { header('Location: index.php'); exit; }

// Fetch order items
$items = $conn->query("SELECT * FROM order_items WHERE order_id = $id")->fetch_all(MYSQLI_ASSOC);

// Fetch payments
$payments = $conn->query("
    SELECT p.*, u.full_name AS staff_name
    FROM payments p JOIN users u ON u.id = p.received_by
    WHERE p.order_id = $id
    ORDER BY p.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $conn->real_escape_string($_POST['new_status'] ?? '');
    $allowed    = ['received','washing','drying','ready','claimed'];
    if (in_array($new_status, $allowed, true)) {
        if ($new_status === 'claimed' && $order['payment_status'] !== 'paid') {
            header("Location: view.php?id=$id&err=unpaid");
            exit;
        } else {
            $claimed = $new_status === 'claimed' ? ', claimed_date = NOW()' : '';
            $conn->query("UPDATE orders SET status='$new_status'$claimed WHERE id=$id");
            logAction("Order $order[order_number] status changed to $new_status", 'orders', $id, $order['status'], $new_status);
            header("Location: view.php?id=$id");
            exit;
        }
    }
}

// Handle payment recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $pay_amount  = (float)($_POST['pay_amount'] ?? 0);
    $pay_method  = $conn->real_escape_string($_POST['pay_method'] ?? '');
    $gcash_ref   = $conn->real_escape_string(trim($_POST['gcash_reference'] ?? ''));
    $pay_notes   = $conn->real_escape_string(trim($_POST['pay_notes'] ?? ''));
    $pay_type    = 'partial';
    $staff_id    = (int)$_SESSION['user_id'];
    $branch_id   = (int)$order['branch_id'];

    if ($pay_amount > 0 && $pay_method) {
        $new_paid   = $order['paid_amount'] + $pay_amount;
        $pay_status = $new_paid >= $order['total_amount'] ? 'paid' : 'partial';
        if ($pay_amount >= $order['total_amount'] - $order['paid_amount']) $pay_type = 'full';

        $pStmt = $conn->prepare("INSERT INTO payments (order_id, branch_id, received_by, amount, payment_method, gcash_reference, payment_type, notes) VALUES (?,?,?,?,?,?,?,?)");
        $pStmt->bind_param('iiidssss', $id, $branch_id, $staff_id, $pay_amount, $pay_method, $gcash_ref, $pay_type, $pay_notes);
        $pStmt->execute();
        $pStmt->close();

        $conn->query("UPDATE orders SET paid_amount='$new_paid', payment_status='$pay_status', payment_method='$pay_method' WHERE id=$id");
        logAction("Payment recorded ₱$pay_amount via $pay_method for order $order[order_number]", 'payments', $id);

        header("Location: view.php?id=$id");
        exit;
    }
}

$statuses    = ['received','washing','drying','ready','claimed'];
$statusIndex = array_search($order['status'], $statuses);
$balance     = $order['total_amount'] - $order['paid_amount'];

$pageTitle = 'Order ' . $order['order_number'];
$navTitle  = 'Order Details';
require_once '../../includes/head.php';
?>

<div class="app-layout">
<?php require_once '../../includes/sidebar.php'; ?>
<div class="main-content">
<?php require_once '../../includes/navbar.php'; ?>
<div class="page-content">

  <?php if (isset($_GET['new'])): ?>
    <div class="alert alert-success alert-dismissible auto-dismiss">
      <i class="fas fa-check-circle me-2"></i>
      Order <strong><?= e($order['order_number']) ?></strong> created successfully!
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['err']) && $_GET['err'] === 'unpaid'): ?>
    <div class="alert alert-danger alert-dismissible auto-dismiss">
      <i class="fas fa-exclamation-circle me-2"></i>
      Cannot mark as <strong>Claimed</strong> — payment must be fully paid first.
    </div>
  <?php endif; ?>

  <div class="page-header">
    <div>
      <h4><i class="fas fa-file-alt text-purple me-2"></i>Order <?= e($order['order_number']) ?></h4>
      <div class="d-flex gap-2 align-items-center mt-1">
        <?= statusBadge($order['status']) ?>
        <?= paymentBadge($order['payment_status']) ?>
        <?= methodBadge($order['payment_method']) ?>
        <span class="badge bg-light text-dark border"><?= e($order['branch_name']) ?></span>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary btn-sm" onclick="printReceipt(<?= $id ?>)">
        <i class="fas fa-print me-1"></i>Print
      </button>
      <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back
      </a>
    </div>
  </div>

  <!-- Status Progress -->
  <div class="card mb-3">
    <div class="card-header"><i class="fas fa-tasks text-purple me-2"></i>Order Progress</div>
    <div class="card-body">
      <div class="status-steps">
        <?php foreach ($statuses as $idx => $s): ?>
          <div class="status-step <?= $idx < $statusIndex ? 'done' : ($idx === $statusIndex ? 'active' : '') ?>">
            <div class="step-dot"><?= $idx < $statusIndex ? '<i class="fas fa-check" style="font-size:.6rem"></i>' : ($idx + 1) ?></div>
            <div class="step-label"><?= ucfirst($s) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($order['status'] !== 'claimed'): ?>
      <div class="text-center mt-3">
        <form method="POST" class="d-inline-flex gap-2 align-items-center">
          <input type="hidden" name="update_status" value="1">
          <label class="form-label mb-0 fw-600 me-2">Move to:</label>
          <select name="new_status" class="form-select form-select-sm" style="width:auto">
            <?php foreach ($statuses as $s): ?>
              <option value="<?= $s ?>"
                <?= $s === $order['status'] ? 'selected' : '' ?>
                <?= $s === 'claimed' && $order['payment_status'] !== 'paid' ? 'disabled title="Payment must be fully paid first"' : '' ?>>
                <?= ucfirst($s) ?><?= $s === 'claimed' && $order['payment_status'] !== 'paid' ? ' (unpaid)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary-grad btn-sm">Update</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="row g-3">
    <!-- Order Info -->
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-info-circle text-purple me-2"></i>Order Information</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <div class="text-muted small">Customer</div>
              <div class="fw-600"><?= e($order['customer_name'] ?? 'Walk-in') ?></div>
              <?php if ($order['customer_phone']): ?>
                <div class="text-muted small"><?= e($order['customer_phone']) ?></div>
              <?php endif; ?>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Service</div>
              <div class="fw-600"><?= serviceLabel($order['service_type']) ?></div>
              <div class="text-muted small"><?= ucfirst(str_replace('_',' ',$order['pricing_type'])) ?></div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Handled by</div>
              <div class="fw-600"><?= e($order['staff_name']) ?></div>
              <div class="text-muted small"><?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Weight</div>
              <div class="fw-600"><?= $order['weight'] ? $order['weight'] . ' kg' : '—' ?></div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Price per Unit</div>
              <div class="fw-600"><?= formatCurrency($order['price_per_unit']) ?></div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Due Date</div>
              <?php $isLate = $order['due_date'] && strtotime($order['due_date']) < time() && $order['status'] !== 'claimed'; ?>
              <div class="fw-600 <?= $isLate ? 'text-danger' : '' ?>">
                <?= $order['due_date'] ? date('M d, Y h:i A', strtotime($order['due_date'])) : '—' ?>
                <?php if ($isLate): ?><i class="fas fa-exclamation-circle ms-1"></i><?php endif; ?>
              </div>
            </div>
            <?php if ($order['rack_number']): ?>
            <div class="col-md-4">
              <div class="text-muted small">Rack / Bin</div>
              <div class="fw-600 text-purple"><i class="fas fa-map-pin me-1"></i><?= e($order['rack_number']) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($order['stain_notes']): ?>
            <div class="col-md-4">
              <div class="text-muted small">Stain Notes</div>
              <div class="fw-600"><?= e($order['stain_notes']) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($order['special_instructions']): ?>
            <div class="col-md-4">
              <div class="text-muted small">Special Instructions</div>
              <div class="fw-600"><?= e($order['special_instructions']) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($order['is_delivery']): ?>
            <div class="col-12">
              <div class="alert alert-info py-2 mb-0">
                <i class="fas fa-truck me-2"></i>
                <strong>Delivery Scheduled:</strong>
                <?= $order['pickup_date'] ? date('M d, Y h:i A', strtotime($order['pickup_date'])) : '' ?>
                — <?= e($order['pickup_address'] ?? '') ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Order Items (per-item) -->
      <?php if (!empty($items)): ?>
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-list text-purple me-2"></i>Order Items</div>
        <div class="table-responsive">
          <table class="table mb-0">
            <thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th><th>Notes</th></tr></thead>
            <tbody>
              <?php foreach ($items as $item): ?>
              <tr>
                <td><?= e($item['item_name']) ?></td>
                <td><?= $item['quantity'] ?></td>
                <td><?= formatCurrency($item['unit_price']) ?></td>
                <td class="fw-600"><?= formatCurrency($item['subtotal']) ?></td>
                <td class="text-muted"><?= e($item['notes'] ?? '') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- Payment History -->
      <div class="card">
        <div class="card-header"><i class="fas fa-history text-purple me-2"></i>Payment History</div>
        <div class="table-responsive">
          <table class="table mb-0">
            <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Type</th><th>Ref #</th><th>Staff</th></tr></thead>
            <tbody>
              <?php if (empty($payments)): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">No payments recorded.</td></tr>
              <?php else: foreach ($payments as $p): ?>
              <tr>
                <td><?= date('M d, Y h:i A', strtotime($p['created_at'])) ?></td>
                <td class="fw-600 text-success"><?= formatCurrency($p['amount']) ?></td>
                <td><?= methodBadge($p['payment_method']) ?></td>
                <td><?= ucfirst($p['payment_type']) ?></td>
                <td><?= e($p['gcash_reference'] ?? '—') ?></td>
                <td><?= e($p['staff_name']) ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /.col-lg-8 -->

    <!-- ── Right: Summary + Payment Form ──────────────────── -->
    <div class="col-lg-4">

      <!-- Barcode & QR -->
      <div class="card mb-3 text-center">
        <div class="card-header"><i class="fas fa-barcode text-purple me-2"></i>Barcode / QR</div>
        <div class="card-body px-2">
          <div class="w-100 overflow-hidden text-center">
            <canvas id="orderBarcode" style="max-width:100%; height:auto;"></canvas>
          </div>
          <div id="orderQR" class="d-flex justify-content-center mt-2"></div>
          <div class="text-muted small mt-1"><?= e($order['order_number']) ?></div>
        </div>
      </div>

      <!-- Payment Summary -->
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-peso-sign text-purple me-2"></i>Payment Summary</div>
        <div class="card-body">
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Total Amount</span>
            <span class="fw-600 fs-5"><?= formatCurrency($order['total_amount']) ?></span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Paid Amount</span>
            <span class="fw-600 text-success"><?= formatCurrency($order['paid_amount']) ?></span>
          </div>
          <hr>
          <div class="d-flex justify-content-between">
            <span class="fw-600 text-purple">Balance</span>
            <span class="fw-700 fs-5 <?= $balance > 0 ? 'text-danger' : 'text-success' ?>">
              <?= formatCurrency($balance) ?>
            </span>
          </div>
        </div>
      </div>

      <!-- Record Payment -->
      <?php if ($balance > 0 && $order['status'] !== 'claimed'): ?>
      <div class="card">
        <div class="card-header"><i class="fas fa-hand-holding-dollar text-purple me-2"></i>Record Payment</div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="record_payment" value="1">
            <div class="mb-3">
              <label class="form-label">Amount (₱) *</label>
              <input type="number" name="pay_amount" class="form-control" step="0.01" min="0.01"
                     max="<?= $balance ?>" value="<?= $balance ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Payment Method *</label>
              <div class="d-flex gap-3">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="pay_method" id="pmCash" value="cash" checked onchange="toggleGcashRef(false)">
                  <label class="form-check-label" for="pmCash"><i class="fas fa-money-bill text-success me-1"></i>Cash</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="pay_method" id="pmGcash" value="gcash" onchange="toggleGcashRef(true)">
                  <label class="form-check-label" for="pmGcash"><i class="fas fa-mobile-alt text-primary me-1"></i>GCash</label>
                </div>
              </div>
            </div>
            <div class="mb-3" id="gcashRefGroup" style="display:none">
              <label class="form-label">GCash Reference # <span class="text-muted">(optional)</span></label>
              <input type="text" name="gcash_reference" class="form-control" placeholder="Reference number">
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <input type="text" name="pay_notes" class="form-control" placeholder="Optional note">
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-primary-grad">
                <i class="fas fa-check me-2"></i>Record Payment
              </button>
            </div>
          </form>
        </div>
      </div>
      <?php elseif ($order['payment_status'] === 'paid'): ?>
        <div class="alert alert-success text-center"><i class="fas fa-check-circle me-2"></i>Order is fully paid!</div>
      <?php endif; ?>

    </div>
  </div><!-- /.row -->

</div>

<?php require_once '../../includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  generateBarcode('<?= e($order['order_number']) ?>', 'orderBarcode');
  generateQR('<?= e($order['order_number']) ?>', 'orderQR');
});
function toggleGcashRef(show) {
  document.getElementById('gcashRefGroup').style.display = show ? '' : 'none';
}
</script>
