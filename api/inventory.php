<?php
/**
 * API: Inventory — adjust | get_low_stock | create | update | delete
 */
require_once '../config/database.php';
require_once '../config/functions.php';
requireAuth();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'adjust': {
        $id      = (int)($_POST['id'] ?? 0);
        $adjType = $conn->real_escape_string($_POST['adjust_type'] ?? 'add'); // add|deduct|set
        $qty     = (float)($_POST['quantity'] ?? 0);
        $notes   = trim($_POST['notes'] ?? '');

        if (!$id || $qty < 0) jsonResponse(['success' => false, 'message' => 'Invalid parameters.'], 422);

        $item = $conn->query("SELECT * FROM inventory WHERE id=$id")->fetch_assoc();
        if (!$item) jsonResponse(['success' => false, 'message' => 'Item not found.'], 404);

        // Access check for staff
        if ($_SESSION['role'] === 'staff' && (int)$item['branch_id'] !== (int)$_SESSION['branch_id']) {
            jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $before = (float)$item['quantity'];
        if ($adjType === 'add')    $after = $before + $qty;
        elseif ($adjType === 'deduct') $after = max(0, $before - $qty);
        else $after = $qty; // set

        $stmt = $conn->prepare("UPDATE inventory SET quantity=? WHERE id=?");
        $stmt->bind_param('di', $after, $id);
        $stmt->execute();
        $stmt->close();

        // Log the inventory adjustment
        $uid = (int)$_SESSION['user_id'];
        $bid = (int)$item['branch_id'];
        $change = ($adjType === 'set') ? ($after - $before) : (($adjType === 'add') ? $qty : -$qty);
        $logStmt = $conn->prepare("INSERT INTO inventory_logs (inventory_id,branch_id,user_id,action,quantity_change,quantity_before,quantity_after,notes) VALUES (?,?,?,?,?,?,?,?)");
        $logStmt->bind_param('iiiiddds', $id, $bid, $uid, $adjType, $change, $before, $after, $notes);
        $logStmt->execute();
        $logStmt->close();

        logAction("Adjusted inventory #{$item['item_name']}: $before → $after ($adjType $qty)", 'inventory', $id);
        jsonResponse(['success' => true, 'new_quantity' => $after, 'message' => "Stock updated: {$item['item_name']} → $after {$item['unit']}"]);
    }

    case 'get_low_stock': {
        $bCond = getBranchFilter();
        $rows  = $conn->query("
            SELECT i.*, b.name AS branch_name
            FROM inventory i
            JOIN branches b ON b.id = i.branch_id
            WHERE i.$bCond AND i.quantity <= i.low_stock_threshold
            ORDER BY i.quantity ASC
        ")->fetch_all(MYSQLI_ASSOC);

        jsonResponse(['success' => true, 'count' => count($rows), 'data' => $rows]);
    }

    case 'create': {
        if (!in_array($_SESSION['role'], ['owner', 'admin'])) {
            jsonResponse(['success' => false, 'message' => 'Permission denied.'], 403);
        }
        $bid      = (int)($_POST['branch_id'] ?? 0);
        $name     = trim($_POST['item_name'] ?? '');
        $cat      = $conn->real_escape_string($_POST['category'] ?? 'other');
        $qty      = (float)($_POST['quantity'] ?? 0);
        $unit     = trim($_POST['unit'] ?? 'pcs');
        $thresh   = (float)($_POST['low_stock_threshold'] ?? 10);
        $cost     = (float)($_POST['cost_per_unit'] ?? 0);
        $supplier = trim($_POST['supplier'] ?? '');

        if (!$bid || !$name) jsonResponse(['success' => false, 'message' => 'Branch and item name required.'], 422);

        $stmt = $conn->prepare("INSERT INTO inventory (branch_id,item_name,category,quantity,unit,low_stock_threshold,cost_per_unit,supplier) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('issdsdds', $bid, $name, $cat, $qty, $unit, $thresh, $cost, $supplier);
        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            logAction("Created inventory item: $name (branch $bid)", 'inventory', $newId);
            $stmt->close();
            jsonResponse(['success' => true, 'id' => $newId, 'message' => "Item \"$name\" added."]);
        }
        jsonResponse(['success' => false, 'message' => $stmt->error], 500);
    }

    case 'delete': {
        if (!in_array($_SESSION['role'], ['owner', 'admin'])) {
            jsonResponse(['success' => false, 'message' => 'Permission denied.'], 403);
        }
        $id  = (int)($_POST['id'] ?? 0);
        $row = $conn->query("SELECT item_name FROM inventory WHERE id=$id")->fetch_assoc();
        if (!$row) jsonResponse(['success' => false, 'message' => 'Not found.'], 404);

        $conn->query("DELETE FROM inventory WHERE id=$id");
        logAction("Deleted inventory: {$row['item_name']}", 'inventory', $id);
        jsonResponse(['success' => true, 'message' => "Item deleted."]);
    }

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}
