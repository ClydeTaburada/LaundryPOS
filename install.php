<?php
/**
 * Lavenderia Laundry Services — Installation Script
 * Run this ONCE to create the database, tables, and seed sample data.
 */

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_port = 3306;
$db_name = 'laundry_db';

$errors  = [];
$success = false;

if (isset($_POST['install'])) {
    $conn = new mysqli($db_host, $db_user, $db_pass, '', $db_port);

    if ($conn->connect_error) {
        $errors[] = 'Cannot connect to MySQL: ' . htmlspecialchars($conn->connect_error);
    } else {
        $conn->query("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->select_db($db_name);
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");

        $tables = [
            "DROP TABLE IF EXISTS `logs`",
            "DROP TABLE IF EXISTS `inventory_logs`",
            "DROP TABLE IF EXISTS `inventory`",
            "DROP TABLE IF EXISTS `payments`",
            "DROP TABLE IF EXISTS `order_items`",
            "DROP TABLE IF EXISTS `orders`",
            "DROP TABLE IF EXISTS `customers`",
            "DROP TABLE IF EXISTS `users`",
            "DROP TABLE IF EXISTS `branches`",

            "CREATE TABLE `branches` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(120) NOT NULL,
                `location` VARCHAR(255) NOT NULL,
                `contact` VARCHAR(25),
                `email` VARCHAR(120),
                `manager_name` VARCHAR(120),
                `status` ENUM('active','inactive') DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",

            "CREATE TABLE `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `branch_id` INT NULL,
                `username` VARCHAR(60) UNIQUE NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `full_name` VARCHAR(120) NOT NULL,
                `email` VARCHAR(120),
                `phone` VARCHAR(25),
                `role` ENUM('owner','admin','staff') NOT NULL DEFAULT 'staff',
                `status` ENUM('active','inactive') DEFAULT 'active',
                `last_login` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB",

            "CREATE TABLE `customers` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `branch_id` INT NULL,
                `name` VARCHAR(120) NOT NULL,
                `phone` VARCHAR(25),
                `email` VARCHAR(120),
                `address` TEXT,
                `loyalty_points` INT DEFAULT 0,
                `total_orders` INT DEFAULT 0,
                `notes` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB",

            "CREATE TABLE `orders` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `branch_id` INT NOT NULL,
                `customer_id` INT NULL,
                `staff_id` INT NOT NULL,
                `order_number` VARCHAR(40) UNIQUE NOT NULL,
                `barcode` VARCHAR(120),
                `status` ENUM('received','washing','drying','ready','claimed') DEFAULT 'received',
                `service_type` ENUM('wash_fold','dry_clean','ironing') NOT NULL,
                `pricing_type` ENUM('per_kilo','per_item') NOT NULL DEFAULT 'per_kilo',
                `weight` DECIMAL(8,2) NULL,
                `price_per_unit` DECIMAL(8,2) NOT NULL DEFAULT 0,
                `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `payment_status` ENUM('paid','unpaid','partial') DEFAULT 'unpaid',
                `payment_method` ENUM('cash','gcash') NULL,
                `gcash_reference` VARCHAR(60),
                `rack_number` VARCHAR(25),
                `stain_notes` TEXT,
                `special_instructions` TEXT,
                `is_delivery` TINYINT(1) DEFAULT 0,
                `pickup_date` DATETIME NULL,
                `pickup_address` TEXT,
                `due_date` DATETIME NULL,
                `claimed_date` DATETIME NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
                FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`)
            ) ENGINE=InnoDB",

            "CREATE TABLE `order_items` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `order_id` INT NOT NULL,
                `item_name` VARCHAR(120) NOT NULL,
                `quantity` INT NOT NULL DEFAULT 1,
                `unit_price` DECIMAL(8,2) NOT NULL DEFAULT 0,
                `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `barcode` VARCHAR(120),
                `notes` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB",

            "CREATE TABLE `payments` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `order_id` INT NOT NULL,
                `branch_id` INT NOT NULL,
                `received_by` INT NOT NULL,
                `amount` DECIMAL(10,2) NOT NULL,
                `payment_method` ENUM('cash','gcash') NOT NULL,
                `gcash_reference` VARCHAR(60),
                `payment_type` ENUM('full','partial','refund') DEFAULT 'full',
                `notes` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`),
                FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
                FOREIGN KEY (`received_by`) REFERENCES `users`(`id`)
            ) ENGINE=InnoDB",

            "CREATE TABLE `inventory` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `branch_id` INT NOT NULL,
                `item_name` VARCHAR(120) NOT NULL,
                `category` ENUM('detergent','fabric_conditioner','packaging','other') NOT NULL,
                `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `unit` VARCHAR(25) NOT NULL DEFAULT 'pcs',
                `low_stock_threshold` DECIMAL(10,2) DEFAULT 10,
                `cost_per_unit` DECIMAL(10,2) DEFAULT 0,
                `supplier` VARCHAR(120),
                `notes` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`)
            ) ENGINE=InnoDB",

            "CREATE TABLE `inventory_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `inventory_id` INT NOT NULL,
                `branch_id` INT NOT NULL,
                `user_id` INT NOT NULL,
                `action` ENUM('add','deduct','adjust') NOT NULL,
                `quantity_change` DECIMAL(10,2) NOT NULL,
                `quantity_before` DECIMAL(10,2) NOT NULL,
                `quantity_after` DECIMAL(10,2) NOT NULL,
                `notes` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`inventory_id`) REFERENCES `inventory`(`id`),
                FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
            ) ENGINE=InnoDB",

            "CREATE TABLE `logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `branch_id` INT NULL,
                `user_id` INT NULL,
                `action` VARCHAR(220) NOT NULL,
                `table_name` VARCHAR(60),
                `record_id` INT,
                `old_value` TEXT,
                `new_value` TEXT,
                `ip_address` VARCHAR(45),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB",
        ];

        foreach ($tables as $sql) {
            if (!$conn->query($sql)) {
                $errors[] = htmlspecialchars($conn->error) . ' — SQL: ' . htmlspecialchars(substr($sql, 0, 60));
            }
        }

        $conn->query("SET FOREIGN_KEY_CHECKS = 1");

        if (empty($errors)) {
            // ── Branches ────────────────────────────────────────────────────────────
            $branch_data = [
                ['Lavenderia Main Branch', 'Quezon City, Metro Manila', '09171234567', 'main@lavenderia.ph', 'Maria Santos'],
                ['Lavenderia Makati Branch', 'Makati City, Metro Manila', '09172345678', 'makati@lavenderia.ph', 'Jose Reyes'],
                ['Lavenderia BGC Branch', 'Taguig City, Metro Manila', '09173456789', 'bgc@lavenderia.ph', 'Ana Cruz'],
                ['Lavenderia Mandaluyong Branch', 'Mandaluyong City, Metro Manila', '09174567890', 'manda@lavenderia.ph', 'Pedro Lim'],
                ['Lavenderia Pasig Branch', 'Pasig City, Metro Manila', '09175678901', 'pasig@lavenderia.ph', 'Rosa Garcia'],
                ['Lavenderia Caloocan Branch', 'Caloocan City, Metro Manila', '09176789012', 'caloocan@lavenderia.ph', 'Carlos Tan'],
            ];
            $stmt = $conn->prepare("INSERT INTO `branches` (name, location, contact, email, manager_name) VALUES (?,?,?,?,?)");
            foreach ($branch_data as $b) {
                $stmt->bind_param('sssss', $b[0], $b[1], $b[2], $b[3], $b[4]);
                $stmt->execute();
            }
            $stmt->close();

            // ── Users ────────────────────────────────────────────────────────────────
            // branch_id | role | username | plain_password | full_name | email | phone
            $user_data = [
                [null, 'owner', 'owner',  'Owner@1234',  'System Owner',   'owner@lavenderia.ph',  '09170000001'],
                [null, 'admin', 'admin',  'Admin@1234',  'System Admin',   'admin@lavenderia.ph',  '09170000002'],
                [1,    'staff', 'staff1', 'Staff@1234',  'Maria Santos',   'staff1@lavenderia.ph', '09171111111'],
                [2,    'staff', 'staff2', 'Staff@1234',  'Jose Reyes',     'staff2@lavenderia.ph', '09172222222'],
                [3,    'staff', 'staff3', 'Staff@1234',  'Ana Cruz',       'staff3@lavenderia.ph', '09173333333'],
                [4,    'staff', 'staff4', 'Staff@1234',  'Pedro Lim',      'staff4@lavenderia.ph', '09174444444'],
                [5,    'staff', 'staff5', 'Staff@1234',  'Rosa Garcia',    'staff5@lavenderia.ph', '09175555555'],
                [6,    'staff', 'staff6', 'Staff@1234',  'Carlos Tan',     'staff6@lavenderia.ph', '09176666666'],
            ];
            $stmt = $conn->prepare("INSERT INTO `users` (branch_id, role, username, password, full_name, email, phone) VALUES (?,?,?,?,?,?,?)");
            foreach ($user_data as $u) {
                $hash = password_hash($u[3], PASSWORD_DEFAULT);
                $stmt->bind_param('issssss', $u[0], $u[1], $u[2], $hash, $u[4], $u[5], $u[6]);
                $stmt->execute();
            }
            $stmt->close();

            // ── Customers ─────────────────────────────────────────────────────────────
            $cust_data = [
                [1, 'Liza Soberano',    '09181234567', 'liza@email.com',    'Quezon City',   50, 5],
                [1, 'Alden Richards',   '09182345678', 'alden@email.com',   'Pasig City',    30, 3],
                [2, 'Anne Curtis',      '09183456789', 'anne@email.com',    'Makati City',   80, 8],
                [2, 'Piolo Pascual',    '09184567890', 'piolo@email.com',   'BGC',           20, 2],
                [3, 'Kathryn Bernardo', '09185678901', 'kathryn@email.com', 'Taguig City',   60, 6],
                [3, 'Daniel Padilla',   '09186789012', 'daniel@email.com',  'Mandaluyong',   40, 4],
                [4, 'Enrique Gil',      '09187890123', 'enrique@email.com', 'Caloocan City', 10, 1],
                [5, 'Rosa Mendoza',     '09188901234', 'rosa@email.com',    'Pasig City',    25, 2],
                [6, 'Carlos dela Cruz', '09189012345', 'carlos@email.com',  'Caloocan City', 15, 1],
            ];
            $stmt = $conn->prepare("INSERT INTO `customers` (branch_id, name, phone, email, address, loyalty_points, total_orders) VALUES (?,?,?,?,?,?,?)");
            foreach ($cust_data as $c) {
                $stmt->bind_param('issssii', $c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6]);
                $stmt->execute();
            }
            $stmt->close();

            // ── Inventory (per branch) ─────────────────────────────────────────────
            $inv_items = [
                ['Ariel Powder Detergent',       'detergent',         50, 'kg',   5,  35.00],
                ['Surf Powder Detergent',         'detergent',          8, 'kg',   5,  28.00],
                ['Tide Liquid Detergent',         'detergent',         20, 'L',    5,  55.00],
                ['Downy Fabric Conditioner',      'fabric_conditioner',18, 'L',    3,  65.00],
                ['Comfort Fabric Conditioner',    'fabric_conditioner', 4, 'L',    3,  55.00],
                ['Plastic Bags Small',            'packaging',        200, 'pcs', 50,   2.00],
                ['Plastic Bags Large',            'packaging',         60, 'pcs', 30,   3.50],
                ['Laundry Nets',                  'packaging',         25, 'pcs',  5,  45.00],
                ['Hangers',                       'packaging',        100, 'pcs', 20,   5.00],
                ['Stain Remover Spray',           'other',             12, 'pcs',  3, 120.00],
            ];
            $stmt = $conn->prepare("INSERT INTO `inventory` (branch_id, item_name, category, quantity, unit, low_stock_threshold, cost_per_unit) VALUES (?,?,?,?,?,?,?)");
            for ($bid = 1; $bid <= 6; $bid++) {
                foreach ($inv_items as $item) {
                    $qty = $item[2] + rand(-4, 8);
                    $stmt->bind_param('issdsdd', $bid, $item[0], $item[1], $qty, $item[3], $item[4], $item[5]);
                    $stmt->execute();
                }
            }
            $stmt->close();

            // ── Sample Orders ─────────────────────────────────────────────────────
            $statuses  = ['received', 'washing', 'drying', 'ready', 'claimed'];
            $services  = ['wash_fold', 'dry_clean', 'ironing'];
            $staffIds  = [3, 4, 5, 6, 7, 8];
            $custIds   = [1, 2, 3, 4, 5, 6, 7, 8, 9];
            $svc_price = ['wash_fold' => 55.00, 'dry_clean' => 130.00, 'ironing' => 40.00];

            $stmt = $conn->prepare(
                "INSERT INTO `orders`
                 (branch_id, customer_id, staff_id, order_number, status, service_type,
                  pricing_type, weight, price_per_unit, total_amount, paid_amount,
                  payment_status, payment_method, rack_number, due_date, created_at)
                 VALUES (?,?,?,?,?,?,'per_kilo',?,?,?,?,?,?,?,DATE_ADD(NOW(), INTERVAL 2 DAY),?)"
            );

            for ($i = 1; $i <= 30; $i++) {
                $bid      = (($i - 1) % 6) + 1;
                $sid      = $staffIds[$bid - 1];
                $cid      = $custIds[($i - 1) % 9];
                $stat     = $statuses[$i % 5];
                $svc      = $services[$i % 3];
                $ordNum   = 'ORD-' . str_pad($bid, 2, '0', STR_PAD_LEFT) . '-' . date('Ymd') . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                $wt       = round((rand(15, 80) / 10), 1);
                $price    = $svc_price[$svc];
                $total    = round($wt * $price, 2);
                $payS     = ($stat === 'claimed') ? 'paid' : (($i % 4 === 0) ? 'partial' : 'unpaid');
                $paid     = ($payS === 'paid') ? $total : (($payS === 'partial') ? round($total / 2, 2) : 0.00);
                $method   = ($payS === 'unpaid') ? null : (($i % 2 === 0) ? 'cash' : 'gcash');
                $rack     = 'R' . $bid . str_pad(($i % 12) + 1, 2, '0', STR_PAD_LEFT);
                $created  = date('Y-m-d H:i:s', strtotime("-" . (30 - $i) . " days"));

                $stmt->bind_param('iiisssddddssss',
                    $bid, $cid, $sid, $ordNum, $stat, $svc,
                    $wt, $price, $total, $paid,
                    $payS, $method, $rack, $created
                );
                $stmt->execute();
            }
            $stmt->close();
            $success = true;
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lavenderia — Install</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background: linear-gradient(135deg,#8A2BE2,#00CED1); min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:'Segoe UI',sans-serif; }
  .install-card { background:#fff; border-radius:20px; padding:2.5rem; max-width:560px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,.25); }
  .brand { text-align:center; margin-bottom:1.5rem; }
  .brand h2 { color:#8A2BE2; font-weight:700; }
  .brand p { color:#6c757d; font-size:.9rem; }
  .btn-install { background:linear-gradient(135deg,#8A2BE2,#00CED1); border:none; color:#fff; border-radius:50px; padding:.65rem 2rem; font-weight:600; width:100%; }
  .cred-table { font-size:.85rem; }
</style>
</head>
<body>
<div class="install-card">
  <div class="brand">
    <img src="assets/img/logo.png" alt="Logo" style="height:80px;margin-bottom:.5rem;" onerror="this.style.display='none'">
    <h2>Lavenderia Laundry Services</h2>
    <p>Database Installation Wizard</p>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><strong>✅ Installation complete!</strong></div>
    <h6 class="fw-bold mb-2">Default Login Credentials</h6>
    <table class="table table-sm table-bordered cred-table">
      <thead class="table-light"><tr><th>Role</th><th>Username</th><th>Password</th></tr></thead>
      <tbody>
        <tr><td>Owner</td><td>owner</td><td>Owner@1234</td></tr>
        <tr><td>Admin</td><td>admin</td><td>Admin@1234</td></tr>
        <tr><td>Staff (×6)</td><td>staff1 – staff6</td><td>Staff@1234</td></tr>
      </tbody>
    </table>
    <a href="auth/login.php" class="btn btn-install mt-2">Go to Login →</a>

  <?php elseif (!empty($errors)): ?>
    <div class="alert alert-danger">
      <strong>Installation errors:</strong>
      <ul class="mb-0 mt-1">
        <?php foreach ($errors as $e): ?>
          <li><?= $e ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <form method="POST">
      <button type="submit" name="install" class="btn btn-install">Retry Installation</button>
    </form>

  <?php else: ?>
    <p class="text-muted">This will create the <strong><?= htmlspecialchars($db_name) ?></strong> database, all tables, and seed 6 branches with sample data.</p>
    <div class="alert alert-warning">⚠️ <strong>Warning:</strong> Re-running will DROP and recreate all tables.</div>
    <form method="POST">
      <button type="submit" name="install" class="btn btn-install">▶ Run Installation</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
