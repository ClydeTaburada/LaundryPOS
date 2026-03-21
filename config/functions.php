<?php
/**
 * Helper / Utility Functions
 * Requires $conn (MySQLi) to be available via database.php
 */

/* ── Authentication ─────────────────────────────────────────────────────────── */

function requireAuth(array $roles = []): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . '/auth/login.php');
        exit;
    }
    if (!empty($roles) && !in_array($_SESSION['role'], $roles, true)) {
        header('Location: ' . SITE_URL . '/modules/dashboard/index.php');
        exit;
    }
}

/* ── Input sanitisation ─────────────────────────────────────────────────────── */

function e(string $val): string
{
    return htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitize(string $input): string
{
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/* ── JSON response helper ───────────────────────────────────────────────────── */

function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/* ── Currency formatting ────────────────────────────────────────────────────── */

function formatCurrency(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

/* ── Order number generator ─────────────────────────────────────────────────── */

function generateOrderNumber(int $branch_id): string
{
    return 'ORD-' . str_pad($branch_id, 2, '0', STR_PAD_LEFT)
         . '-' . date('Ymd')
         . '-' . strtoupper(substr(uniqid(), -5));
}

/* ── Audit logger ───────────────────────────────────────────────────────────── */

function logAction(
    string $action,
    ?string $table_name = null,
    ?int    $record_id  = null,
    ?string $old_value  = null,
    ?string $new_value  = null
): void {
    global $conn;
    if (empty($_SESSION['user_id'])) return;

    $user_id   = (int) $_SESSION['user_id'];
    $branch_id = isset($_SESSION['branch_id']) ? (int) $_SESSION['branch_id'] : null;
    $ip        = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = $conn->prepare(
        "INSERT INTO logs (branch_id, user_id, action, table_name, record_id, old_value, new_value, ip_address)
         VALUES (?,?,?,?,?,?,?,?)"
    );
    $stmt->bind_param('iississs', $branch_id, $user_id, $action, $table_name, $record_id, $old_value, $new_value, $ip);
    $stmt->execute();
    $stmt->close();
}

/* ── Status badges ──────────────────────────────────────────────────────────── */

function statusBadge(string $status): string
{
    $map = [
        'received' => ['Received', 'badge-received'],
        'washing'  => ['Washing',  'badge-washing'],
        'drying'   => ['Drying',   'badge-drying'],
        'ready'    => ['Ready',    'badge-ready'],
        'claimed'  => ['Claimed',  'badge-claimed'],
    ];
    [$label, $cls] = $map[$status] ?? [ucfirst($status), 'bg-secondary'];
    return "<span class=\"badge $cls\">$label</span>";
}

function paymentBadge(string $status): string
{
    $map = [
        'paid'    => ['Paid',    'bg-success'],
        'unpaid'  => ['Unpaid',  'bg-danger'],
        'partial' => ['Partial', 'bg-warning text-dark'],
    ];
    [$label, $cls] = $map[$status] ?? [ucfirst($status), 'bg-secondary'];
    return "<span class=\"badge $cls\">$label</span>";
}

function methodBadge(?string $method): string
{
    if (!$method) return '<span class="badge bg-secondary">—</span>';
    $cls = $method === 'gcash' ? 'badge-gcash' : 'badge-cash';
    return "<span class=\"badge $cls\">" . strtoupper($method) . "</span>";
}

/* ── Branch helpers ─────────────────────────────────────────────────────────── */

function getBranches(): array
{
    global $conn;
    if (in_array($_SESSION['role'] ?? '', ['owner', 'admin'], true)) {
        $res = $conn->query("SELECT * FROM branches WHERE status='active' ORDER BY name");
    } else {
        $bid  = (int) ($_SESSION['branch_id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM branches WHERE id=? AND status='active'");
        $stmt->bind_param('i', $bid);
        $stmt->execute();
        $res  = $stmt->get_result();
        $stmt->close();
    }
    return $res->fetch_all(MYSQLI_ASSOC);
}

function branchFilter(string $alias = ''): string
{
    $col = $alias ? "$alias.branch_id" : 'branch_id';
    if (in_array($_SESSION['role'] ?? '', ['owner', 'admin'], true)) return '1=1';
    return "$col = " . (int) ($_SESSION['branch_id'] ?? 0);
}

function getBranchName(int $id): string
{
    global $conn;
    $stmt = $conn->prepare("SELECT name FROM branches WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['name'] ?? 'N/A';
}

/* ── Service label helpers ──────────────────────────────────────────────────── */

function serviceLabel(string $type): string
{
    return match($type) {
        'wash_fold'  => 'Wash & Fold',
        'dry_clean'  => 'Dry Clean',
        'ironing'    => 'Ironing',
        default      => ucfirst(str_replace('_', ' ', $type)),
    };
}

/* ── Low-stock check ────────────────────────────────────────────────────────── */

function getLowStockCount(?int $branch_id = null): int
{
    global $conn;
    $filter = '';
    if ($branch_id) {
        $filter = "AND branch_id = $branch_id";
    } elseif (!in_array($_SESSION['role'] ?? '', ['owner', 'admin'], true)) {
        $bid    = (int) ($_SESSION['branch_id'] ?? 0);
        $filter = "AND branch_id = $bid";
    }
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM inventory WHERE quantity <= low_stock_threshold $filter");
    return (int) ($res->fetch_assoc()['cnt'] ?? 0);
}

/* ── getBranchFilter alias (for API files) ──────────────────────────────────── */

function getBranchFilter(string $alias = ''): string
{
    return branchFilter($alias);
}
