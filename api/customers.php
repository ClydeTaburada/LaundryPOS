<?php
/**
 * API: Customers — create | update | delete | search
 */
require_once '../config/database.php';
require_once '../config/functions.php';
requireAuth();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'create': {
        $name    = trim($_POST['name']    ?? '');
        $phone   = trim($_POST['phone']   ?? '');
        $email   = trim($_POST['email']   ?? '');
        $address = trim($_POST['address'] ?? '');
        $bid     = (int)($_POST['branch_id'] ?? $_SESSION['branch_id'] ?? 0) ?: null;

        if (!$name) jsonResponse(['success' => false, 'message' => 'Name is required.'], 422);

        $stmt = $conn->prepare("INSERT INTO customers (branch_id, name, phone, email, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('issss', $bid, $name, $phone, $email, $address);
        if ($stmt->execute()) {
            $id = $conn->insert_id;
            logAction("Created customer: $name", 'customers', $id);
            $stmt->close();
            jsonResponse(['success' => true, 'id' => $id, 'name' => $name, 'phone' => $phone, 'message' => "Customer \"$name\" added."]);
        }
        jsonResponse(['success' => false, 'message' => $conn->error], 500);
    }

    case 'update': {
        $id      = (int)($_POST['id'] ?? 0);
        $name    = trim($_POST['name']    ?? '');
        $phone   = trim($_POST['phone']   ?? '');
        $email   = trim($_POST['email']   ?? '');
        $address = trim($_POST['address'] ?? '');
        $notes   = trim($_POST['notes']   ?? '');

        if (!$id || !$name) jsonResponse(['success' => false, 'message' => 'ID and name required.'], 422);

        $stmt = $conn->prepare("UPDATE customers SET name=?,phone=?,email=?,address=?,notes=? WHERE id=?");
        $stmt->bind_param('sssssi', $name, $phone, $email, $address, $notes, $id);
        if ($stmt->execute()) {
            logAction("Updated customer #$id: $name", 'customers', $id);
            $stmt->close();
            jsonResponse(['success' => true, 'message' => 'Customer updated.']);
        }
        jsonResponse(['success' => false, 'message' => $stmt->error], 500);
    }

    case 'delete': {
        if (!in_array($_SESSION['role'], ['owner', 'admin'])) {
            jsonResponse(['success' => false, 'message' => 'Permission denied.'], 403);
        }
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'message' => 'Invalid ID.'], 422);

        $row = $conn->query("SELECT name FROM customers WHERE id=$id")->fetch_assoc();
        if (!$row) jsonResponse(['success' => false, 'message' => 'Not found.'], 404);

        $conn->query("DELETE FROM customers WHERE id=$id");
        logAction("Deleted customer #{$row['name']}", 'customers', $id);
        jsonResponse(['success' => true, 'message' => "Customer deleted."]);
    }

    case 'search': {
        $q    = $conn->real_escape_string(trim($_GET['q'] ?? ''));
        $bCond = getBranchFilter();
        $rows = $conn->query("
            SELECT id, name, phone, email, loyalty_points
            FROM customers
            WHERE $bCond AND (name LIKE '%$q%' OR phone LIKE '%$q%')
            ORDER BY name LIMIT 20
        ")->fetch_all(MYSQLI_ASSOC);

        jsonResponse(['success' => true, 'data' => $rows]);
    }

    case 'get': {
        $id = (int)($_GET['id'] ?? 0);
        $row = $conn->query("
            SELECT c.*,
                   COUNT(o.id) AS total_orders,
                   COALESCE(SUM(o.total_amount), 0) AS lifetime_value
            FROM customers c
            LEFT JOIN orders o ON o.customer_id = c.id
            WHERE c.id=$id
            GROUP BY c.id
        ")->fetch_assoc();

        if (!$row) jsonResponse(['success' => false, 'message' => 'Not found.'], 404);
        jsonResponse(['success' => true, 'data' => $row]);
    }

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}
