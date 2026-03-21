<?php
/**
 * API: Payments — record | get_by_order | summary
 */
require_once '../config/database.php';
require_once '../config/functions.php';
requireAuth();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'record': {
        $order_id  = (int)($_POST['order_id'] ?? 0);
        $amount    = (float)($_POST['amount'] ?? 0);
        $method    = $conn->real_escape_string($_POST['payment_method'] ?? '');
        $gcash_ref = trim($_POST['gcash_reference'] ?? '');
        $pay_type  = $conn->real_escape_string($_POST['payment_type'] ?? 'partial');
        $notes     = trim($_POST['notes'] ?? '');

        if (!$order_id || $amount <= 0 || !in_array($method, ['cash','gcash'])) {
            jsonResponse(['success' => false, 'message' => 'Invalid payment data.'], 422);
        }

        $order = $conn->query("SELECT * FROM orders WHERE id=$order_id")->fetch_assoc();
        if (!$order) jsonResponse(['success' => false, 'message' => 'Order not found.'], 404);

        if ($_SESSION['role'] === 'staff' && (int)$order['branch_id'] !== (int)$_SESSION['branch_id']) {
            jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $staff_id  = (int)$_SESSION['user_id'];
        $branch_id = (int)$order['branch_id'];
        $new_paid  = $order['paid_amount'] + $amount;
        $pay_status = $new_paid >= $order['total_amount'] ? 'paid' : 'partial';
        if ($amount >= $order['total_amount'] - $order['paid_amount']) $pay_type = 'full';

        $stmt = $conn->prepare("INSERT INTO payments (order_id,branch_id,received_by,amount,payment_method,gcash_reference,payment_type,notes) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('iiidssss', $order_id, $branch_id, $staff_id, $amount, $method, $gcash_ref, $pay_type, $notes);
        if (!$stmt->execute()) {
            jsonResponse(['success' => false, 'message' => $stmt->error], 500);
        }
        $pay_id = $conn->insert_id;
        $stmt->close();

        // Update order paid amount and status
        $conn->query("UPDATE orders SET paid_amount=$new_paid, payment_status='$pay_status', payment_method='$method' WHERE id=$order_id");
        logAction("Payment ₱$amount via $method on order #{$order['order_number']}", 'payments', $pay_id);

        jsonResponse([
            'success'      => true,
            'payment_id'   => $pay_id,
            'new_paid'     => $new_paid,
            'balance'      => $order['total_amount'] - $new_paid,
            'pay_status'   => $pay_status,
            'message'      => "Payment of ₱" . number_format($amount, 2) . " recorded via $method."
        ]);
    }

    case 'get_by_order': {
        $order_id = (int)($_GET['order_id'] ?? 0);
        if (!$order_id) jsonResponse(['success' => false, 'message' => 'Order ID required.'], 422);

        $rows = $conn->query("
            SELECT p.*, u.full_name AS received_by_name
            FROM payments p
            JOIN users u ON u.id = p.received_by
            WHERE p.order_id = $order_id
            ORDER BY p.created_at DESC
        ")->fetch_all(MYSQLI_ASSOC);

        jsonResponse(['success' => true, 'data' => $rows]);
    }

    case 'summary': {
        $date  = $conn->real_escape_string($_GET['date'] ?? date('Y-m-d'));
        $bCond = getBranchFilter('o');

        $summary = $conn->query("
            SELECT
                SUM(CASE WHEN p.payment_method='cash' THEN p.amount ELSE 0 END) AS cash_total,
                SUM(CASE WHEN p.payment_method='gcash' THEN p.amount ELSE 0 END) AS gcash_total,
                SUM(CASE WHEN p.payment_type='refund' THEN p.amount ELSE 0 END) AS refund_total,
                SUM(CASE WHEN p.payment_type != 'refund' THEN p.amount ELSE 0 END) AS gross_total,
                COUNT(*) AS transaction_count
            FROM payments p
            JOIN orders o ON o.id = p.order_id
            WHERE o.$bCond AND DATE(p.created_at) = '$date'
        ")->fetch_assoc();

        jsonResponse(['success' => true, 'data' => $summary]);
    }

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}
