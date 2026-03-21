<?php
/**
 * API: Staff — create | update | toggle_status | get_list
 */
require_once '../config/database.php';
require_once '../config/functions.php';
requireAuth(['owner', 'admin']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'get_list': {
        $rows = $conn->query("
            SELECT u.id, u.username, u.full_name, u.email, u.phone, u.role, u.status,
                   u.last_login, u.branch_id, b.name AS branch_name,
                   COUNT(o.id) AS total_orders
            FROM users u
            LEFT JOIN branches b ON b.id = u.branch_id
            LEFT JOIN orders o ON o.staff_id = u.id
            GROUP BY u.id
            ORDER BY u.role, u.full_name
        ")->fetch_all(MYSQLI_ASSOC);
        jsonResponse(['success' => true, 'data' => $rows]);
    }

    case 'create': {
        $bid      = (int)($_POST['branch_id'] ?? 0) ?: null;
        $username = trim($_POST['username']  ?? '');
        $password = trim($_POST['password']  ?? '');
        $name     = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email']     ?? '');
        $phone    = trim($_POST['phone']     ?? '');
        $role     = $conn->real_escape_string($_POST['role'] ?? 'staff');

        if (!$username || !$password || !$name) {
            jsonResponse(['success' => false, 'message' => 'Username, password, and name are required.'], 422);
        }

        // Check username uniqueness
        $check = $conn->query("SELECT id FROM users WHERE username='$username'")->fetch_assoc();
        if ($check) jsonResponse(['success' => false, 'message' => "Username \"$username\" is already taken."], 409);

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (branch_id,username,password,full_name,email,phone,role) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('issssss', $bid, $username, $hash, $name, $email, $phone, $role);
        if ($stmt->execute()) {
            $id = $conn->insert_id;
            logAction("Created user $username ($role)", 'users', $id);
            $stmt->close();
            jsonResponse(['success' => true, 'id' => $id, 'message' => "Account \"$username\" created."]);
        }
        jsonResponse(['success' => false, 'message' => $stmt->error], 500);
    }

    case 'update': {
        $id       = (int)($_POST['id']         ?? 0);
        $bid      = (int)($_POST['branch_id']  ?? 0) ?: null;
        $name     = trim($_POST['full_name']   ?? '');
        $email    = trim($_POST['email']       ?? '');
        $phone    = trim($_POST['phone']       ?? '');
        $role     = $conn->real_escape_string($_POST['role']   ?? 'staff');
        $status   = $conn->real_escape_string($_POST['status'] ?? 'active');
        $newPass  = trim($_POST['new_password'] ?? '');

        if (!$id || !$name) jsonResponse(['success' => false, 'message' => 'ID and name required.'], 422);

        if ($newPass) {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET branch_id=?,full_name=?,email=?,phone=?,role=?,status=?,password=? WHERE id=?");
            $stmt->bind_param('issssssi', $bid, $name, $email, $phone, $role, $status, $hash, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET branch_id=?,full_name=?,email=?,phone=?,role=?,status=? WHERE id=?");
            $stmt->bind_param('isssssi', $bid, $name, $email, $phone, $role, $status, $id);
        }

        if ($stmt->execute()) {
            logAction("Updated user #$id: $name ($role)", 'users', $id);
            $stmt->close();
            jsonResponse(['success' => true, 'message' => 'Staff account updated.']);
        }
        jsonResponse(['success' => false, 'message' => $stmt->error], 500);
    }

    case 'toggle_status': {
        $id  = (int)($_POST['id'] ?? 0);
        $row = $conn->query("SELECT id, status, username FROM users WHERE id=$id")->fetch_assoc();
        if (!$row) jsonResponse(['success' => false, 'message' => 'User not found.'], 404);
        if ($id === (int)$_SESSION['user_id']) jsonResponse(['success' => false, 'message' => 'Cannot deactivate your own account.'], 403);

        $new = $row['status'] === 'active' ? 'inactive' : 'active';
        $conn->query("UPDATE users SET status='$new' WHERE id=$id");
        logAction("Toggled user {$row['username']} → $new", 'users', $id);
        jsonResponse(['success' => true, 'new_status' => $new, 'message' => "Account is now $new."]);
    }

    case 'delete': {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$_SESSION['user_id']) jsonResponse(['success' => false, 'message' => 'Cannot delete your own account.'], 403);
        $row = $conn->query("SELECT username FROM users WHERE id=$id")->fetch_assoc();
        if (!$row) jsonResponse(['success' => false, 'message' => 'Not found.'], 404);

        // Check if user has orders - soft delete (deactivate) instead
        $cnt = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE staff_id=$id")->fetch_assoc()['c'];
        if ($cnt > 0) {
            $conn->query("UPDATE users SET status='inactive' WHERE id=$id");
            logAction("Deactivated user {$row['username']} (has $cnt orders)", 'users', $id);
            jsonResponse(['success' => true, 'message' => "User has $cnt existing orders — account deactivated (not deleted)."]);
        } else {
            $conn->query("DELETE FROM users WHERE id=$id");
            logAction("Deleted user {$row['username']}", 'users', $id);
            jsonResponse(['success' => true, 'message' => "User deleted."]);
        }
    }

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}
