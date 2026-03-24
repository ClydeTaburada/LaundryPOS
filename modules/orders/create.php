<?php
/**
 * Create Order — POS-style form
 */
require_once '../../config/database.php';
require_once '../../config/functions.php';
requireAuth();

$branches  = getBranches();
$customers = $conn->query("SELECT id, name, phone FROM customers WHERE " . branchFilter() . " ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id    = (int) ($_POST['branch_id'] ?? $_SESSION['branch_id'] ?? 0);
    $customer_id  = (int) ($_POST['customer_id'] ?? 0) ?: null;
    $service_type = $conn->real_escape_string($_POST['service_type'] ?? '');
    $pricing_type = $conn->real_escape_string($_POST['pricing_type'] ?? 'per_kilo');
    $weight       = (float) ($_POST['weight'] ?? 0);
    $price_unit   = (float) ($_POST['price_per_unit'] ?? 0);
    $total        = (float) ($_POST['total_amount'] ?? 0);
    $rack         = $conn->real_escape_string(trim($_POST['rack_number'] ?? ''));
    $stain        = $conn->real_escape_string(trim($_POST['stain_notes'] ?? ''));
    $special      = $conn->real_escape_string(trim($_POST['special_instructions'] ?? ''));
    $due_date     = $conn->real_escape_string($_POST['due_date'] ?? '');
    $is_delivery  = isset($_POST['is_delivery']) ? 1 : 0;
    $pickup_date  = $conn->real_escape_string($_POST['pickup_date'] ?? '');
    $pickup_addr  = $conn->real_escape_string(trim($_POST['pickup_address'] ?? ''));
    $staff_id     = (int) $_SESSION['user_id'];

    if (!$branch_id || !$service_type || $total <= 0) {
        $error_msg = 'Please fill in all required fields.';
    } else {
        $order_number = generateOrderNumber($branch_id);
        $barcode      = $order_number;

        $stmt = $conn->prepare("
            INSERT INTO orders
              (branch_id, customer_id, staff_id, order_number, barcode,
               service_type, pricing_type, weight, price_per_unit, total_amount,
               rack_number, stain_notes, special_instructions, due_date,
               is_delivery, pickup_date, pickup_address)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param('iiissssdddssssiss',
            $branch_id, $customer_id, $staff_id, $order_number, $barcode,
            $service_type, $pricing_type, $weight, $price_unit, $total,
            $rack, $stain, $special, $due_date,
            $is_delivery, $pickup_date, $pickup_addr
        );

        if ($stmt->execute()) {
            $order_id = $conn->insert_id;

            // Order items (per_item mode)
            if ($pricing_type === 'per_item' && !empty($_POST['item_name'])) {
                $iStmt = $conn->prepare("INSERT INTO order_items (order_id, item_name, quantity, unit_price, subtotal, barcode, notes) VALUES (?,?,?,?,?,?,?)");
                foreach ($_POST['item_name'] as $idx => $iname) {
                    if (trim($iname) === '') continue;
                    $iqty  = (int)   ($_POST['item_qty'][$idx]   ?? 1);
                    $iprc  = (float) ($_POST['item_price'][$idx] ?? 0);
                    $isub  = round($iqty * $iprc, 2);
                    $inote = $conn->real_escape_string($_POST['item_notes'][$idx] ?? '');
                    $ibar  = $order_number . '-' . ($idx + 1);
                    $iStmt->bind_param('isiddss',
                        $order_id, $conn->real_escape_string($iname),
                        $iqty, $iprc, $isub, $ibar, $inote
                    );
                    $iStmt->execute();
                }
                $iStmt->close();
            }

            // Update customer loyalty & order count
            if ($customer_id) {
                $upCust = $conn->prepare("UPDATE customers SET total_orders=total_orders+1, loyalty_points=loyalty_points+? WHERE id=?");
                $pts = max(1, (int)($total / 100));
                $upCust->bind_param('ii', $pts, $customer_id);
                $upCust->execute();
                $upCust->close();
            }

            logAction("Created order $order_number", 'orders', $order_id);
            $stmt->close();

            header("Location: view.php?id=$order_id&new=1");
            exit;
        } else {
            $error_msg = 'Database error: ' . htmlspecialchars($stmt->error);
        }
        $stmt->close();
    }
}

$pageTitle = 'New Order';
$navTitle  = 'Create New Order';
require_once '../../includes/head.php';
?>

<div class="app-layout">
<?php require_once '../../includes/sidebar.php'; ?>
<div class="main-content">
<?php require_once '../../includes/navbar.php'; ?>
<div class="page-content">

  <div class="page-header">
    <div>
      <h4><i class="fas fa-plus-circle text-purple me-2"></i>New Order</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="../../modules/dashboard/index.php">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="index.php">Orders</a></li>
          <li class="breadcrumb-item active">New Order</li>
        </ol>
      </nav>
    </div>
    <a href="index.php" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>Back
    </a>
  </div>

  <?php if ($error_msg): ?>
    <div class="alert alert-danger alert-dismissible auto-dismiss"><i class="fas fa-times-circle me-2"></i><?= $error_msg ?></div>
  <?php endif; ?>

  <form method="POST" id="orderForm">
  <div class="row g-3">

    <!-- ── Left: Order Details ─────────────────────────────── -->
    <div class="col-lg-8">

      <!-- Service Selection -->
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-tshirt text-purple me-2"></i>Select Service</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-4">
              <div class="service-card" onclick="selectService('wash_fold', this)">
                <i class="fas fa-soap"></i>
                <div class="svc-name">Wash &amp; Fold</div>
                <div class="svc-price">₱55/kg · ₱0/item</div>
              </div>
            </div>
            <div class="col-4">
              <div class="service-card" onclick="selectService('dry_clean', this)">
                <i class="fas fa-shirt"></i>
                <div class="svc-name">Dry Clean</div>
                <div class="svc-price">₱130/kg · ₱120/item</div>
              </div>
            </div>
            <div class="col-4">
              <div class="service-card" onclick="selectService('ironing', this)">
                <i class="fas fa-fire"></i>
                <div class="svc-name">Ironing</div>
                <div class="svc-price">₱0/kg · ₱40/item</div>
              </div>
            </div>
          </div>
          <input type="hidden" name="service_type" id="service_type" required>
        </div>
      </div>

      <!-- Pricing Type -->
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-weight-hanging text-purple me-2"></i>Pricing &amp; Weight</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Pricing Type</label>
              <div class="d-flex gap-3 mt-1">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="pricing_type" id="ptKilo"
                         value="per_kilo" checked onchange="togglePricingMode()">
                  <label class="form-check-label" for="ptKilo">Per Kilo</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="pricing_type" id="ptItem"
                         value="per_item" onchange="togglePricingMode()">
                  <label class="form-check-label" for="ptItem">Per Item</label>
                </div>
              </div>
            </div>
            <div class="col-md-4" id="weightGroup">
              <label class="form-label">Weight (kg)</label>
              <input type="number" name="weight" id="weight" class="form-control"
                     step="0.1" min="0" placeholder="0.0" oninput="recalcOrderTotal()">
            </div>
            <div class="col-md-4">
              <label class="form-label">Price per Unit (₱)</label>
              <input type="number" name="price_per_unit" id="price_per_unit" class="form-control"
                     step="0.01" min="0" placeholder="0.00" oninput="recalcOrderTotal()">
            </div>
          </div>

          <!-- Per-item rows -->
          <div id="itemsSection" class="mt-3" style="display:none">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <label class="form-label mb-0 fw-600">Items</label>
              <button type="button" class="btn btn-sm btn-primary-grad" onclick="addItemRow()">
                <i class="fas fa-plus me-1"></i>Add Item
              </button>
            </div>
            <div id="itemsContainer"></div>
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-sticky-note text-purple me-2"></i>Notes &amp; Tags</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Rack / Bin Number</label>
              <input type="text" name="rack_number" class="form-control" placeholder="e.g. R1-05">
            </div>
            <div class="col-md-4">
              <label class="form-label">Stain Notes</label>
              <input type="text" name="stain_notes" class="form-control" placeholder="e.g. Red wine on collar">
            </div>
            <div class="col-md-4">
              <label class="form-label">Special Instructions</label>
              <input type="text" name="special_instructions" class="form-control" placeholder="e.g. Handle gently">
            </div>
          </div>
        </div>
      </div>

      <!-- Pickup / Delivery -->
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-truck text-purple me-2"></i>Pickup &amp; Delivery</div>
        <div class="card-body">
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="is_delivery" id="isDelivery"
                   onchange="toggleDelivery(this.checked)">
            <label class="form-check-label" for="isDelivery">Schedule for Delivery</label>
          </div>
          <div id="deliveryFields" style="display:none">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Pickup Date &amp; Time</label>
                <input type="datetime-local" name="pickup_date" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">Delivery Address</label>
                <input type="text" name="pickup_address" class="form-control" placeholder="Full address">
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /.col-lg-8 -->

    <!-- ── Right: Customer + Summary ─────────────────────── -->
    <div class="col-lg-4">

      <!-- Branch (admin/owner only) -->
      <?php if (in_array($_SESSION['role'], ['owner','admin'], true)): ?>
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-store text-purple me-2"></i>Branch</div>
        <div class="card-body">
          <select name="branch_id" id="branchSelect" class="form-select" required>
            <option value="">Select Branch</option>
            <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php else: ?>
        <input type="hidden" name="branch_id" value="<?= (int)$_SESSION['branch_id'] ?>">
      <?php endif; ?>

      <!-- Customer -->
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fas fa-user text-purple me-2"></i>Customer</span>
          <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
            <i class="fas fa-user-plus me-1"></i>New
          </button>
        </div>
        <div class="card-body">
          <select name="customer_id" id="customerSelect" class="form-select">
            <option value="">Walk-in Customer</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['name']) ?> · <?= e($c['phone'] ?? '') ?></option>
            <?php endforeach; ?>
          </select>
          <div class="mt-2 text-muted small">Leave blank for walk-in customers.</div>
        </div>
      </div>

      <!-- Due Date -->
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-calendar text-purple me-2"></i>Due Date</div>
        <div class="card-body">
          <input type="datetime-local" name="due_date" id="dueDate" class="form-control">
        </div>
      </div>

      <!-- Order Summary -->
      <div class="pos-summary">
        <div class="fw-600 mb-3 text-purple"><i class="fas fa-receipt me-2"></i>Order Summary</div>
        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted">Service:</span>
          <span id="summaryService" class="fw-600">—</span>
        </div>
        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted">Weight / Items:</span>
          <span id="summaryWeight" class="fw-600">—</span>
        </div>
        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted">Price/Unit:</span>
          <span id="summaryPrice" class="fw-600">—</span>
        </div>
        <hr>
        <div class="d-flex justify-content-between pos-total-row">
          <span>TOTAL</span>
          <span id="totalAmount">₱0.00</span>
        </div>
        <input type="hidden" name="total_amount" id="totalAmountHidden" value="0">

        <div class="d-grid mt-3">
          <button type="submit" class="btn btn-primary-grad btn-lg">
            <i class="fas fa-check-circle me-2"></i>Confirm Order
          </button>
        </div>
      </div>

    </div><!-- /.col-lg-4 -->
  </div><!-- /.row -->
  </form>

</div><!-- /.page-content -->

<!-- ── New Customer Modal ──────────────────────────────────────── -->
<div class="modal fade" id="newCustomerModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New Customer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Full Name *</label>
          <input type="text" id="newCustName" class="form-control" placeholder="Customer name">
        </div>
        <div class="mb-3">
          <label class="form-label">Phone Number</label>
          <input type="text" id="newCustPhone" class="form-control" placeholder="09XXXXXXXXX">
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" id="newCustEmail" class="form-control" placeholder="email@example.com">
        </div>
        <div class="mb-3">
          <label class="form-label">Address</label>
          <input type="text" id="newCustAddress" class="form-control" placeholder="Street, City">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary-grad btn-sm" onclick="saveNewCustomer()">Save Customer</button>
      </div>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
<script>
// ── Service selector ─────────────────────────────────────────────
function selectService(type, el) {
  document.querySelectorAll('.service-card').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('service_type').value = type;
  onServiceChange(type);
  // Set due date
  document.getElementById('dueDate').value = calcDueDate(type);
  // Update summary
  document.getElementById('summaryService').textContent = el.querySelector('.svc-name').textContent;
}

// ── Pricing mode toggle ──────────────────────────────────────────
function togglePricingMode() {
  const isKilo = document.getElementById('ptKilo').checked;
  document.getElementById('weightGroup').style.display   = isKilo ? '' : 'none';
  document.getElementById('itemsSection').style.display  = isKilo ? 'none' : '';
  const svc = document.getElementById('service_type').value;
  if (svc) onServiceChange(svc);
  recalcOrderTotal();
}

// ── Price update hook ────────────────────────────────────────────
const _origOnSvc = window.onServiceChange;
window.onServiceChange = function(svc) {
  const pricingType = document.querySelector('input[name="pricing_type"]:checked')?.value || 'per_kilo';
  const prices = SERVICE_PRICES[svc] || {};
  const pi = document.getElementById('price_per_unit');
  if (pi && prices[pricingType] !== undefined) {
    pi.value = prices[pricingType];
  }
  document.getElementById('summaryPrice').textContent = '₱' + (prices[pricingType] || 0);
  recalcOrderTotal();
};

// Recalc override for summary update
const _origRecalc = window.recalcOrderTotal;
window.recalcOrderTotal = function() {
  const pricingType = document.querySelector('input[name="pricing_type"]:checked')?.value || 'per_kilo';
  const weight  = parseFloat(document.getElementById('weight')?.value || 0);
  const priceU  = parseFloat(document.getElementById('price_per_unit')?.value || 0);
  let total = 0;
  if (pricingType === 'per_kilo') {
    total = weight * priceU;
    document.getElementById('summaryWeight').textContent = weight + ' kg';
  } else {
    let itemCount = 0;
    document.querySelectorAll('.item-row').forEach(row => {
      const qty   = parseFloat(row.querySelector('.item-qty').value  || 0);
      const price = parseFloat(row.querySelector('.item-price').value || 0);
      total += qty * price;
      itemCount += qty;
    });
    document.getElementById('summaryWeight').textContent = itemCount + ' items';
  }
  document.getElementById('totalAmount').textContent = formatCurrency(total);
  document.getElementById('totalAmountHidden').value = total.toFixed(2);
  document.getElementById('summaryPrice').textContent = formatCurrency(priceU);
};

// ── Add item row ─────────────────────────────────────────────────
let itemIdx = 0;
function addItemRow() {
  const idx = itemIdx++;
  const container = document.getElementById('itemsContainer');
  const row = document.createElement('div');
  row.className = 'item-row row g-2 mb-2 align-items-center';
  row.innerHTML = `
    <div class="col-5">
      <input type="text" name="item_name[]" class="form-control form-control-sm" placeholder="Item name" required>
    </div>
    <div class="col-2">
      <input type="number" name="item_qty[]" class="form-control form-control-sm item-qty"
             placeholder="Qty" min="1" value="1" oninput="recalcOrderTotal()">
    </div>
    <div class="col-2">
      <input type="number" name="item_price[]" class="form-control form-control-sm item-price"
             placeholder="₱" step="0.01" min="0" oninput="recalcOrderTotal()">
    </div>
    <div class="col-2">
      <input type="text" name="item_notes[]" class="form-control form-control-sm" placeholder="Note">
    </div>
    <div class="col-1">
      <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.item-row').remove();recalcOrderTotal()">
        <i class="fas fa-times"></i>
      </button>
    </div>`;
  container.appendChild(row);
}

function toggleDelivery(on) {
  document.getElementById('deliveryFields').style.display = on ? '' : 'none';
}

// ── Quick add customer via AJAX ──────────────────────────────────
function saveNewCustomer() {
  const name  = document.getElementById('newCustName').value.trim();
  const phone = document.getElementById('newCustPhone').value.trim();
  const email = document.getElementById('newCustEmail').value.trim();
  const addr  = document.getElementById('newCustAddress').value.trim();
  const bid   = document.getElementById('branchSelect')?.value ||
                '<?= (int)$_SESSION['branch_id'] ?>';

  if (!name) { showToast('Name is required', 'warning'); return; }

  ajax(SITE_URL + '/api/customers.php', { action: 'create', name, phone, email, address: addr, branch_id: bid })
    .then(res => {
      if (res.success) {
        const sel = document.getElementById('customerSelect');
        const opt = new Option(name + ' · ' + phone, res.id, true, true);
        sel.add(opt);
        bootstrap.Modal.getInstance(document.getElementById('newCustomerModal')).hide();
        showToast('Customer added!', 'success');
      } else showToast(res.message || 'Failed to add customer', 'danger');
    });
}
</script>
