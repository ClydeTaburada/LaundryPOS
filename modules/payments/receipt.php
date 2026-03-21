<?php
/**
 * Receipt — Printable
 */
require_once '../../config/database.php';
require_once '../../config/functions.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo 'Invalid order.'; exit; }

$stmt = $conn->prepare("
    SELECT o.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address,
           b.name AS branch_name, b.location AS branch_location, b.contact AS branch_contact,
           u.full_name AS staff_name
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

if (!$order) { echo 'Order not found.'; exit; }

$items    = $conn->query("SELECT * FROM order_items WHERE order_id=$id")->fetch_all(MYSQLI_ASSOC);
$payments = $conn->query("SELECT * FROM payments WHERE order_id=$id ORDER BY created_at ASC")->fetch_all(MYSQLI_ASSOC);
$balance  = $order['total_amount'] - $order['paid_amount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Receipt — <?= e($order['order_number']) ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Courier New',monospace; background:#eee; display:flex; justify-content:center; padding:20px; }
.receipt {
  background:#fff; width:360px; padding:20px;
  box-shadow:0 4px 24px rgba(0,0,0,.2);
}
.receipt-header { text-align:center; border-bottom:2px dashed #aaa; padding-bottom:12px; margin-bottom:12px; }
.receipt-header h3 { font-size:18px; color:#8A2BE2; font-family:sans-serif; font-weight:700; }
.receipt-header p  { font-size:11px; color:#555; margin-top:2px; }
.receipt-header .logo { height:60px; margin-bottom:6px; }
.section { margin-bottom:12px; }
.section-title { font-size:10px; text-transform:uppercase; letter-spacing:.08em; color:#888; border-bottom:1px dashed #ddd; padding-bottom:3px; margin-bottom:6px; }
.row-pair { display:flex; justify-content:space-between; font-size:12px; padding:2px 0; }
.row-pair.indent { padding-left:10px; font-size:11px; color:#555; }
.total-line { border-top:2px dashed #aaa; padding-top:8px; margin-top:8px; display:flex; justify-content:space-between; font-size:16px; font-weight:700; color:#8A2BE2; }
.balance-line { display:flex; justify-content:space-between; font-size:13px; color:#dc3545; font-weight:700; margin-top:4px; }
.status-pill { display:inline-block; background:#8A2BE2; color:#fff; border-radius:50px; padding:2px 10px; font-size:11px; font-family:sans-serif; }
.receipt-footer { text-align:center; border-top:2px dashed #aaa; padding-top:12px; margin-top:12px; font-size:10px; color:#888; }
.barcode-area { text-align:center; margin:10px 0; }
.no-print { display:flex; gap:8px; justify-content:center; margin-top:16px; }
.btn-print { background:linear-gradient(135deg,#8A2BE2,#00CED1); color:#fff; border:none; border-radius:50px; padding:8px 20px; cursor:pointer; font-size:13px; font-family:sans-serif; }
.btn-close2 { background:#6c757d; color:#fff; border:none; border-radius:50px; padding:8px 20px; cursor:pointer; font-size:13px; font-family:sans-serif; }
@media print {
  body { background:#fff; padding:0; }
  .receipt { box-shadow:none; width:100%; }
  .no-print { display:none; }
}
</style>
</head>
<body>

<div>
  <div class="receipt">
    <div class="receipt-header">
      <img src="<?= SITE_URL ?>/assets/img/logo.png" alt="Logo" class="logo"
           onerror="this.style.display='none'">
      <h3>Lavenderia Laundry Services</h3>
      <p><?= e($order['branch_name']) ?></p>
      <p><?= e($order['branch_location']) ?></p>
      <?php if ($order['branch_contact']): ?>
        <p>Tel: <?= e($order['branch_contact']) ?></p>
      <?php endif; ?>
      <div style="margin-top:6px">
        <div class="status-pill"><?= strtoupper($order['status']) ?></div>
      </div>
    </div>

    <!-- Order Info -->
    <div class="section">
      <div class="section-title">Order Info</div>
      <div class="row-pair"><span>Order #</span><span><strong><?= e($order['order_number']) ?></strong></span></div>
      <div class="row-pair"><span>Date</span><span><?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></span></div>
      <div class="row-pair"><span>Due Date</span><span><?= $order['due_date'] ? date('M d, Y', strtotime($order['due_date'])) : '—' ?></span></div>
      <div class="row-pair"><span>Service</span><span><?= serviceLabel($order['service_type']) ?></span></div>
      <div class="row-pair"><span>Pricing</span><span><?= ucfirst(str_replace('_',' ',$order['pricing_type'])) ?></span></div>
      <?php if ($order['weight']): ?>
      <div class="row-pair"><span>Weight</span><span><?= $order['weight'] ?> kg</span></div>
      <?php endif; ?>
      <?php if ($order['rack_number']): ?>
      <div class="row-pair"><span>Rack</span><span><?= e($order['rack_number']) ?></span></div>
      <?php endif; ?>
      <?php if ($order['special_instructions']): ?>
      <div class="row-pair"><span>Note</span><span style="max-width:180px;text-align:right;font-size:11px"><?= e($order['special_instructions']) ?></span></div>
      <?php endif; ?>
      <div class="row-pair"><span>Staff</span><span><?= e($order['staff_name']) ?></span></div>
    </div>

    <!-- Customer -->
    <div class="section">
      <div class="section-title">Customer</div>
      <div class="row-pair"><span>Name</span><span><?= e($order['customer_name'] ?? 'Walk-in') ?></span></div>
      <?php if ($order['customer_phone']): ?>
      <div class="row-pair"><span>Phone</span><span><?= e($order['customer_phone']) ?></span></div>
      <?php endif; ?>
    </div>

    <!-- Items (per-item orders) -->
    <?php if (!empty($items)): ?>
    <div class="section">
      <div class="section-title">Items</div>
      <?php foreach ($items as $item): ?>
      <div class="row-pair">
        <span><?= e($item['item_name']) ?> ×<?= $item['quantity'] ?></span>
        <span><?= formatCurrency($item['subtotal']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Payment -->
    <div class="section">
      <div class="section-title">Payment</div>
      <div class="row-pair"><span>Total Amount</span><span><?= formatCurrency($order['total_amount']) ?></span></div>
      <?php foreach ($payments as $p): ?>
      <div class="row-pair indent">
        <span><?= date('M d', strtotime($p['created_at'])) ?> — <?= strtoupper($p['payment_method']) ?></span>
        <span><?= formatCurrency($p['amount']) ?></span>
      </div>
      <?php if ($p['gcash_reference']): ?>
        <div class="row-pair indent"><span>Ref #</span><span><?= e($p['gcash_reference']) ?></span></div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <div class="total-line">
      <span>TOTAL PAID</span>
      <span><?= formatCurrency($order['paid_amount']) ?></span>
    </div>
    <?php if ($balance > 0): ?>
    <div class="balance-line">
      <span>BALANCE DUE</span>
      <span><?= formatCurrency($balance) ?></span>
    </div>
    <?php else: ?>
    <div style="text-align:center;color:#28C76F;font-size:13px;margin-top:6px;font-family:sans-serif;font-weight:700">
      ✓ FULLY PAID
    </div>
    <?php endif; ?>

    <!-- Barcode -->
    <div class="barcode-area">
      <canvas id="rcBarcode" style="max-width:100%"></canvas>
    </div>

    <div class="receipt-footer">
      <p>Thank you for choosing Lavenderia!</p>
      <p>Please present this receipt when claiming your laundry.</p>
      <p style="margin-top:6px">Printed: <?= date('M d, Y h:i A') ?></p>
    </div>
  </div>

  <div class="no-print">
    <button class="btn-print" onclick="window.print()">
      <i class="fas fa-print me-1"></i>Print Receipt
    </button>
    <button class="btn-close2" onclick="window.close()">Close</button>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
window.addEventListener('load', function() {
  try {
    JsBarcode(document.getElementById('rcBarcode'), '<?= e($order['order_number']) ?>', {
      format:'CODE128', width:1.5, height:40, displayValue:true, fontSize:10
    });
  } catch(e) {}
});
</script>
</body>
</html>
