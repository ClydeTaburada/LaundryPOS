<?php
/**
 * API: Orders — update_status | delete | get_list | get_detail
 */
require_once '../config/database.php';
require_once '../config/functions.php';
requireAuth();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'update_status': {
        $id     = (int)($_POST['id'] ?? 0);
        $status = $conn->real_escape_string($_POST['status'] ?? '');
        $allowed = ['received','washing','drying','ready','claimed'];

        if (!$id || !in_array($status, $allowed)) {
            jsonResponse(['success' => false, 'message' => 'Invalid parameters.'], 422);
        }

        // Staff can only update orders in their branch
        $order = $conn->query("SELECT id,status,order_number,branch_id,payment_status FROM orders WHERE id=$id")->fetch_assoc();
        if (!$order) jsonResponse(['success' => false, 'message' => 'Order not found.'], 404);

        if ($_SESSION['role'] === 'staff' && (int)$order['branch_id'] !== (int)$_SESSION['branch_id']) {
            jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
        }

        // Block claiming if payment is not fully paid
        if ($status === 'claimed' && $order['payment_status'] !== 'paid') {
            jsonResponse(['success' => false, 'message' => 'Cannot mark as Claimed — payment must be fully paid first.'], 422);
        }

        $extra = '';
        if ($status === 'claimed') $extra = ", claimed_date = NOW()";

        $conn->query("UPDATE orders SET status='$status'$extra WHERE id=$id");
        logAction("Updated order #{$order['order_number']} status: {$order['status']} → $status", 'orders', $id);
        jsonResponse(['success' => true, 'message' => "Status updated to " . ucfirst($status) . ".", 'status' => $status]);
    }

    case 'delete': {
        if (!in_array($_SESSION['role'], ['owner', 'admin'])) {
            jsonResponse(['success' => false, 'message' => 'Permission denied.'], 403);
        }
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'message' => 'Invalid ID.'], 422);

        $order = $conn->query("SELECT order_number FROM orders WHERE id=$id")->fetch_assoc();
        if (!$order) jsonResponse(['success' => false, 'message' => 'Order not found.'], 404);

        $conn->query("DELETE FROM orders WHERE id=$id");
        logAction("Deleted order #{$order['order_number']}", 'orders', $id);
        jsonResponse(['success' => true, 'message' => "Order #{$order['order_number']} deleted."]);
    }

    case 'get_list': {
        $bCond  = getBranchFilter();
        $limit  = min(200, (int)($_GET['limit'] ?? 50));
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $status = $conn->real_escape_string($_GET['status'] ?? '');
        $extra  = $status ? "AND o.status='$status'" : '';

        $rows = $conn->query("
            SELECT o.id, o.order_number, o.status, o.service_type,
                   o.total_amount, o.payment_status, o.created_at,
                   c.name AS customer_name, b.name AS branch_name
            FROM orders o
            LEFT JOIN customers c ON c.id = o.customer_id
            LEFT JOIN branches b ON b.id = o.branch_id
            WHERE o.$bCond $extra
            ORDER BY o.created_at DESC
            LIMIT $limit OFFSET $offset
        ")->fetch_all(MYSQLI_ASSOC);

        jsonResponse(['success' => true, 'data' => $rows]);
    }

    case 'get_detail': {
        $id    = (int)($_GET['id'] ?? 0);
        $order = $conn->query("
            SELECT o.*,
                   c.name AS customer_name, c.phone AS customer_phone,
                   b.name AS branch_name,
                   u.full_name AS staff_name
            FROM orders o
            LEFT JOIN customers c ON c.id = o.customer_id
            LEFT JOIN branches b ON b.id = o.branch_id
            LEFT JOIN users u ON u.id = o.staff_id
            WHERE o.id=$id
        ")->fetch_assoc();

        if (!$order) jsonResponse(['success' => false, 'message' => 'Not found.'], 404);

        if ($_SESSION['role'] === 'staff' && (int)$order['branch_id'] !== (int)$_SESSION['branch_id']) {
            jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $items = $conn->query("SELECT * FROM order_items WHERE order_id=$id")->fetch_all(MYSQLI_ASSOC);
        $order['items'] = $items;
        jsonResponse(['success' => true, 'data' => $order]);
    }

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}
