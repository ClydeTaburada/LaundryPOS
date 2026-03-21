<?php
/**
 * API: Audit Logs — get_list | get_recent
 */
require_once '../config/database.php';
require_once '../config/functions.php';
requireAuth(['owner', 'admin']);

$action = $_GET['action'] ?? 'get_list';

switch ($action) {

    case 'get_list': {
        $search    = $conn->real_escape_string(trim($_GET['search']    ?? ''));
        $user_id   = (int)($_GET['user_id']   ?? 0);
        $branch_id = (int)($_GET['branch_id'] ?? 0);
        $dateFrom  = $conn->real_escape_string($_GET['date_from'] ?? date('Y-m-01'));
        $dateTo    = $conn->real_escape_string($_GET['date_to']   ?? date('Y-m-d'));
        $limit     = min(200, (int)($_GET['limit']  ?? 100));
        $offset    = max(0,   (int)($_GET['offset'] ?? 0));

        $where = ["DATE(l.created_at) BETWEEN '$dateFrom' AND '$dateTo'"];
        if ($search)    $where[] = "(l.action LIKE '%$search%' OR u.username LIKE '%$search%')";
        if ($user_id)   $where[] = "l.user_id = $user_id";
        if ($branch_id) $where[] = "l.branch_id = $branch_id";
        $whereSql = implode(' AND ', $where);

        $total = $conn->query("
            SELECT COUNT(*) AS cnt FROM logs l
            LEFT JOIN users u ON u.id = l.user_id
            WHERE $whereSql
        ")->fetch_assoc()['cnt'];

        $rows = $conn->query("
            SELECT l.*, u.full_name, u.username, u.role, b.name AS branch_name
            FROM logs l
            LEFT JOIN users u ON u.id = l.user_id
            LEFT JOIN branches b ON b.id = l.branch_id
            WHERE $whereSql
            ORDER BY l.created_at DESC
            LIMIT $limit OFFSET $offset
        ")->fetch_all(MYSQLI_ASSOC);

        jsonResponse(['success' => true, 'total' => (int)$total, 'data' => $rows]);
    }

    case 'get_recent': {
        $limit = min(50, (int)($_GET['limit'] ?? 20));
        $rows  = $conn->query("
            SELECT l.id, l.action, l.created_at,
                   u.full_name, u.username, u.role,
                   b.name AS branch_name
            FROM logs l
            LEFT JOIN users u ON u.id = l.user_id
            LEFT JOIN branches b ON b.id = l.branch_id
            ORDER BY l.created_at DESC
            LIMIT $limit
        ")->fetch_all(MYSQLI_ASSOC);

        jsonResponse(['success' => true, 'data' => $rows]);
    }

    case 'get_user_actions': {
        $uid = (int)($_GET['user_id'] ?? 0);
        if (!$uid) jsonResponse(['success' => false, 'message' => 'user_id required.'], 422);

        $rows = $conn->query("
            SELECT l.action, l.table_name, l.created_at, b.name AS branch_name
            FROM logs l
            LEFT JOIN branches b ON b.id = l.branch_id
            WHERE l.user_id = $uid
            ORDER BY l.created_at DESC
            LIMIT 50
        ")->fetch_all(MYSQLI_ASSOC);

        jsonResponse(['success' => true, 'data' => $rows]);
    }

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}
