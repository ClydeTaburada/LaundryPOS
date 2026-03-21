<?php
/**
 * API: Dashboard Stats — real-time AJAX refresh
 */
require_once '../config/database.php';
require_once '../config/functions.php';
requireAuth();

$action = $_GET['action'] ?? 'stats';
$bCond  = getBranchFilter();
$today  = date('Y-m-d');

switch ($action) {

    case 'stats': {
        // Today's sales
        $todaySales = $conn->query("
            SELECT COALESCE(SUM(paid_amount), 0) AS total
            FROM orders
            WHERE $bCond AND DATE(created_at) = '$today'
        ")->fetch_assoc()['total'];

        // Active orders (not claimed)
        $activeOrders = $conn->query("
            SELECT COUNT(*) AS cnt
            FROM orders
            WHERE $bCond AND status NOT IN ('claimed')
        ")->fetch_assoc()['cnt'];

        // Delayed orders (past due date, not claimed)
        $delayed = $conn->query("
            SELECT COUNT(*) AS cnt
            FROM orders
            WHERE $bCond AND due_date < NOW() AND status NOT IN ('claimed')
        ")->fetch_assoc()['cnt'];

        // Unpaid orders
        $unpaid = $conn->query("
            SELECT COUNT(*) AS cnt
            FROM orders
            WHERE $bCond AND payment_status = 'unpaid'
        ")->fetch_assoc()['cnt'];

        // Total customers
        $totalCustomers = $conn->query("
            SELECT COUNT(*) AS cnt FROM customers WHERE $bCond
        ")->fetch_assoc()['cnt'];

        // GCash today
        $gcashToday = $conn->query("
            SELECT COALESCE(SUM(p.amount), 0) AS total
            FROM payments p
            JOIN orders o ON o.id = p.order_id
            WHERE o.$bCond AND p.payment_method = 'gcash' AND DATE(p.created_at) = '$today'
        ")->fetch_assoc()['total'];

        // Low stock count
        $lowStock = getLowStockCount();

        jsonResponse([
            'success'        => true,
            'today_sales'    => (float)$todaySales,
            'active_orders'  => (int)$activeOrders,
            'delayed_orders' => (int)$delayed,
            'unpaid_orders'  => (int)$unpaid,
            'total_customers'=> (int)$totalCustomers,
            'gcash_today'    => (float)$gcashToday,
            'low_stock'      => (int)$lowStock,
        ]);
    }

    case 'sales_trend': {
        $days = min(30, (int)($_GET['days'] ?? 7));
        $rows = $conn->query("
            SELECT DATE(created_at) AS day,
                   SUM(total_amount) AS revenue,
                   COUNT(*) AS orders
            FROM orders
            WHERE $bCond
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
            GROUP BY DATE(created_at)
            ORDER BY day
        ")->fetch_all(MYSQLI_ASSOC);

        jsonResponse(['success' => true, 'data' => $rows]);
    }

    case 'order_status_counts': {
        $rows = $conn->query("
            SELECT status, COUNT(*) AS cnt
            FROM orders
            WHERE $bCond AND status != 'claimed'
            GROUP BY status
        ")->fetch_all(MYSQLI_ASSOC);

        jsonResponse(['success' => true, 'data' => $rows]);
    }

    case 'recent_orders': {
        $rows = $conn->query("
            SELECT o.id, o.order_number, o.status, o.service_type, o.total_amount,
                   o.payment_status, o.created_at,
                   c.name AS customer_name,
                   b.name AS branch_name
            FROM orders o
            LEFT JOIN customers c ON c.id = o.customer_id
            LEFT JOIN branches b ON b.id = o.branch_id
            WHERE o.$bCond
            ORDER BY o.created_at DESC
            LIMIT 10
        ")->fetch_all(MYSQLI_ASSOC);

        jsonResponse(['success' => true, 'data' => $rows]);
    }

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}
