<?php
/**
 * API: Branches — get_all | create | update | toggle_status
 */
require_once '../config/database.php';
require_once '../config/functions.php';
requireAuth();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'get_all': {
        $rows = $conn->query("
            SELECT b.*,
                   COUNT(DISTINCT u.id) AS staff_count,
                   COUNT(DISTINCT o.id) AS total_orders,
                   COALESCE(SUM(o.total_amount), 0) AS total_revenue
            FROM branches b
            LEFT JOIN users u ON u.branch_id = b.id AND u.role = 'staff' AND u.status = 'active'
            LEFT JOIN orders o ON o.branch_id = b.id
            WHERE b.status = 'active'
            GROUP BY b.id
            ORDER BY b.name
        ")->fetch_all(MYSQLI_ASSOC);

        jsonResponse(['success' => true, 'data' => $rows]);
    }

    case 'get': {
        $id = (int)($_GET['id'] ?? 0);
        $row = $conn->query("SELECT * FROM branches WHERE id=$id")->fetch_assoc();
        if (!$row) jsonResponse(['success' => false, 'message' => 'Not found.'], 404);
        jsonResponse(['success' => true, 'data' => $row]);
    }

    case 'create': {
        requireAuth(['owner', 'admin']);
        $name    = trim($_POST['name']         ?? '');
        $loc     = trim($_POST['location']     ?? '');
        $contact = trim($_POST['contact']      ?? '');
        $email   = trim($_POST['email']        ?? '');
        $manager = trim($_POST['manager_name'] ?? '');

        if (!$name || !$loc) jsonResponse(['success' => false, 'message' => 'Name and location required.'], 422);

        $stmt = $conn->prepare("INSERT INTO branches (name,location,contact,email,manager_name) VALUES (?,?,?,?,?)");
        $stmt->bind_param('sssss', $name, $loc, $contact, $email, $manager);
        if ($stmt->execute()) {
            $id = $conn->insert_id;
            logAction("Created branch: $name", 'branches', $id);
            $stmt->close();
            jsonResponse(['success' => true, 'id' => $id, 'message' => "Branch \"$name\" created."]);
        }
        jsonResponse(['success' => false, 'message' => $stmt->error], 500);
    }

    case 'update': {
        requireAuth(['owner', 'admin']);
        $id      = (int)($_POST['id'] ?? 0);
        $name    = trim($_POST['name']         ?? '');
        $loc     = trim($_POST['location']     ?? '');
        $contact = trim($_POST['contact']      ?? '');
        $email   = trim($_POST['email']        ?? '');
        $manager = trim($_POST['manager_name'] ?? '');

        if (!$id || !$name) jsonResponse(['success' => false, 'message' => 'ID and name required.'], 422);

        $stmt = $conn->prepare("UPDATE branches SET name=?,location=?,contact=?,email=?,manager_name=? WHERE id=?");
        $stmt->bind_param('sssssi', $name, $loc, $contact, $email, $manager, $id);
        if ($stmt->execute()) {
            logAction("Updated branch #$id: $name", 'branches', $id);
            $stmt->close();
            jsonResponse(['success' => true, 'message' => 'Branch updated.']);
        }
        jsonResponse(['success' => false, 'message' => $stmt->error], 500);
    }

    case 'toggle_status': {
        requireAuth(['owner', 'admin']);
        $id  = (int)($_POST['id'] ?? 0);
        $row = $conn->query("SELECT status,name FROM branches WHERE id=$id")->fetch_assoc();
        if (!$row) jsonResponse(['success' => false, 'message' => 'Not found.'], 404);

        $new = $row['status'] === 'active' ? 'inactive' : 'active';
        $conn->query("UPDATE branches SET status='$new' WHERE id=$id");
        logAction("Branch {$row['name']} status → $new", 'branches', $id);
        jsonResponse(['success' => true, 'new_status' => $new, 'message' => "Branch \"({$row['name']})\" is now $new."]);
    }

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}
