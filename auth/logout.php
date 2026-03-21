<?php
require_once '../config/database.php';

if (!empty($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $bid = isset($_SESSION['branch_id']) ? (int) $_SESSION['branch_id'] : null;
    $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
    $act = 'User logged out';
    try {
        $ins = $conn->prepare("INSERT INTO logs (branch_id, user_id, action, ip_address) VALUES (?,?,?,?)");
        $ins->bind_param('iiss', $bid, $uid, $act, $ip);
        $ins->execute();
        $ins->close();
    } catch (\mysqli_sql_exception $e) {
        // Non-critical — proceed with logout even if log insert fails
    }
}

session_unset();
session_destroy();

header('Location: ../auth/login.php');
exit;
