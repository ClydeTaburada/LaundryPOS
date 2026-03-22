-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 22, 2026 at 12:22 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `laundry_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `location` varchar(255) NOT NULL,
  `contact` varchar(25) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `manager_name` varchar(120) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `name`, `location`, `contact`, `email`, `manager_name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Lavenderia 1st Branch', 'Lacson St. Bacolod', '09123456789', 'branch1@gmail.com', 'Branch Manager 1', 'active', '2026-03-21 13:22:51', '2026-03-21 13:22:51');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `phone` varchar(25) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `loyalty_points` int(11) DEFAULT 0,
  `total_orders` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `item_name` varchar(120) NOT NULL,
  `category` enum('detergent','fabric_conditioner','packaging','other') NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(25) NOT NULL DEFAULT 'pcs',
  `low_stock_threshold` decimal(10,2) DEFAULT 10.00,
  `cost_per_unit` decimal(10,2) DEFAULT 0.00,
  `supplier` varchar(120) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `branch_id`, `item_name`, `category`, `quantity`, `unit`, `low_stock_threshold`, `cost_per_unit`, `supplier`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'Ariel Powder Detergent', 'detergent', 50.00, 'kg', 5.00, 35.00, '', NULL, '2026-03-21 13:26:13', '2026-03-21 13:26:13'),
(2, 1, 'Downy Fabric Conditioner', 'fabric_conditioner', 18.00, 'L', 3.00, 65.00, '', NULL, '2026-03-21 13:26:52', '2026-03-21 13:26:52');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

CREATE TABLE `inventory_logs` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('add','deduct','adjust') NOT NULL,
  `quantity_change` decimal(10,2) NOT NULL,
  `quantity_before` decimal(10,2) NOT NULL,
  `quantity_after` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(220) NOT NULL,
  `table_name` varchar(60) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `branch_id`, `user_id`, `action`, `table_name`, `record_id`, `old_value`, `new_value`, `ip_address`, `created_at`) VALUES
(1, NULL, 2, 'User logged in', NULL, NULL, NULL, NULL, '::1', '2026-03-21 13:19:35'),
(2, NULL, 2, 'Created branch: Lavenderia 1st Branch', 'branches', 1, NULL, NULL, '::1', '2026-03-21 13:22:51'),
(3, NULL, 2, 'Updated branch #1: Lavenderia 1st Branch', 'branches', 1, NULL, NULL, '::1', '2026-03-21 13:23:02'),
(4, NULL, 2, 'Created user: branchstaff1 (staff)', 'users', 9, NULL, NULL, '::1', '2026-03-21 13:24:51'),
(5, 1, 9, 'User logged in', NULL, NULL, NULL, NULL, '::1', '2026-03-21 13:25:22'),
(6, 1, 9, 'Added inventory: Ariel Powder Detergent', 'inventory', 1, NULL, NULL, '::1', '2026-03-21 13:26:13'),
(7, 1, 9, 'Added inventory: Downy Fabric Conditioner', 'inventory', 2, NULL, NULL, '::1', '2026-03-21 13:26:52'),
(8, 1, 9, 'Created order ORD-01-20260321-A8D71', 'orders', 1, NULL, NULL, '::1', '2026-03-21 13:37:17'),
(9, 1, 9, 'Payment recorded ₱300 via cash for order ORD-01-20260321-A8D71', 'payments', 1, NULL, NULL, '::1', '2026-03-21 13:37:46'),
(10, NULL, 2, 'Order ORD-01-20260321-A8D71 status changed to washing', 'orders', 1, 'received', 'washing', '::1', '2026-03-21 13:53:04'),
(11, NULL, 2, 'Order ORD-01-20260321-A8D71 status changed to drying', 'orders', 1, 'washing', 'drying', '::1', '2026-03-21 13:53:07'),
(12, NULL, 2, 'Order ORD-01-20260321-A8D71 status changed to ready', 'orders', 1, 'drying', 'ready', '::1', '2026-03-21 13:53:09'),
(13, NULL, 2, 'Order ORD-01-20260321-A8D71 status changed to claimed', 'orders', 1, 'ready', 'claimed', '::1', '2026-03-21 13:53:10'),
(14, NULL, 2, 'Created order ORD-01-20260321-3B6CD', 'orders', 2, NULL, NULL, '::1', '2026-03-21 14:12:18'),
(15, 1, 9, 'Order ORD-01-20260321-3B6CD status changed to washing', 'orders', 2, 'received', 'washing', '::1', '2026-03-21 14:12:32'),
(16, 1, 9, 'Order ORD-01-20260321-3B6CD status changed to drying', 'orders', 2, 'washing', 'drying', '::1', '2026-03-21 14:12:35'),
(17, 1, 9, 'Order ORD-01-20260321-3B6CD status changed to ready', 'orders', 2, 'drying', 'ready', '::1', '2026-03-21 14:12:37'),
(18, 1, 9, 'Payment recorded ₱88 via gcash for order ORD-01-20260321-3B6CD', 'payments', 2, NULL, NULL, '::1', '2026-03-21 14:12:49'),
(19, 1, 9, 'Order ORD-01-20260321-3B6CD status changed to claimed', 'orders', 2, 'ready', 'claimed', '::1', '2026-03-21 14:12:54');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `staff_id` int(11) NOT NULL,
  `order_number` varchar(40) NOT NULL,
  `barcode` varchar(120) DEFAULT NULL,
  `status` enum('received','washing','drying','ready','claimed') DEFAULT 'received',
  `service_type` enum('wash_fold','dry_clean','ironing') NOT NULL,
  `pricing_type` enum('per_kilo','per_item') NOT NULL DEFAULT 'per_kilo',
  `weight` decimal(8,2) DEFAULT NULL,
  `price_per_unit` decimal(8,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('paid','unpaid','partial') DEFAULT 'unpaid',
  `payment_method` enum('cash','gcash') DEFAULT NULL,
  `gcash_reference` varchar(60) DEFAULT NULL,
  `rack_number` varchar(25) DEFAULT NULL,
  `stain_notes` text DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `is_delivery` tinyint(1) DEFAULT 0,
  `pickup_date` datetime DEFAULT NULL,
  `pickup_address` text DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `claimed_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `branch_id`, `customer_id`, `staff_id`, `order_number`, `barcode`, `status`, `service_type`, `pricing_type`, `weight`, `price_per_unit`, `total_amount`, `paid_amount`, `payment_status`, `payment_method`, `gcash_reference`, `rack_number`, `stain_notes`, `special_instructions`, `is_delivery`, `pickup_date`, `pickup_address`, `due_date`, `claimed_date`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 9, 'ORD-01-20260321-A8D71', 'ORD-01-20260321-A8D71', 'claimed', 'wash_fold', 'per_kilo', 10.00, 60.00, 600.00, 300.00, 'partial', 'cash', NULL, '', '', '', 0, '0000-00-00 00:00:00', '', '2026-03-22 21:37:00', '2026-03-21 21:53:10', '2026-03-21 13:37:17', '2026-03-21 13:53:10'),
(2, 1, NULL, 2, 'ORD-01-20260321-3B6CD', 'ORD-01-20260321-3B6CD', 'claimed', 'wash_fold', 'per_kilo', 8.00, 11.00, 88.00, 88.00, 'paid', 'gcash', NULL, '1', '', '', 0, '0000-00-00 00:00:00', '', '2026-03-21 14:16:00', '2026-03-21 22:12:54', '2026-03-21 14:12:18', '2026-03-21 14:12:54');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_name` varchar(120) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(8,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `barcode` varchar(120) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `received_by` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','gcash') NOT NULL,
  `gcash_reference` varchar(60) DEFAULT NULL,
  `payment_type` enum('full','partial','refund') DEFAULT 'full',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `branch_id`, `received_by`, `amount`, `payment_method`, `gcash_reference`, `payment_type`, `notes`, `created_at`) VALUES
(1, 1, 1, 9, 300.00, 'cash', '', 'partial', '', '2026-03-21 13:37:46'),
(2, 2, 1, 9, 88.00, 'gcash', '42121212', 'full', '', '2026-03-21 14:12:49');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `username` varchar(60) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `phone` varchar(25) DEFAULT NULL,
  `role` enum('owner','admin','staff') NOT NULL DEFAULT 'staff',
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `branch_id`, `username`, `password`, `full_name`, `email`, `phone`, `role`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(2, NULL, 'admin', '$2y$10$j/kjHq.3oS8f0EFtDnciO.vmNRTMeRCT1lcM4HPjuBPIFXyXLwu7m', 'System Admin', 'admin@lavenderia.ph', '09170000002', 'admin', 'active', '2026-03-21 13:19:35', '2026-03-19 12:37:53', '2026-03-21 13:19:35'),
(9, 1, 'branchstaff1', '$2y$10$G863vVh/x5rEpp06sJg5oeDumAgk5ci.toBXUonH2.E6QHq.Lj/yC', 'Staff 1', 'branchstaff1@gmail.com', '0912345678', 'staff', 'active', '2026-03-21 13:25:22', '2026-03-21 13:24:51', '2026-03-21 13:25:22');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `branch_id` (`branch_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

--
-- Constraints for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD CONSTRAINT `inventory_logs_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`),
  ADD CONSTRAINT `inventory_logs_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `inventory_logs_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
