<?php
// Migration Script for Order Tracking & Shopee-Style Order Status
require_once 'includes/config.php';

echo "=== Running Migration: Order Tracking & Status System ===\n\n";

// 1. Add columns to `orders` table if not exists
$columns = [
    'order_status' => "ENUM('unpaid', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'unpaid' AFTER payment_status",
    'tracking_number' => "VARCHAR(100) NULL AFTER shipping_service",
    'shipped_at' => "DATETIME NULL AFTER tracking_number",
    'delivered_at' => "DATETIME NULL AFTER shipped_at"
];

foreach ($columns as $col => $definition) {
    $checkCol = $conn->query("SHOW COLUMNS FROM orders LIKE '{$col}'");
    if ($checkCol && $checkCol->num_rows == 0) {
        $sql = "ALTER TABLE orders ADD COLUMN {$col} {$definition}";
        if ($conn->query($sql)) {
            echo "✓ Added column `{$col}` to `orders` table.\n";
        } else {
            echo "✗ Failed to add column `{$col}`: " . $conn->error . "\n";
        }
    } else {
        echo "ℹ Column `{$col}` already exists in `orders`.\n";
    }
}

// 2. Create `order_tracking_logs` table
$createLogsTableSql = "
CREATE TABLE IF NOT EXISTS `order_tracking_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `status_key` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `location` VARCHAR(150) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($createLogsTableSql)) {
    echo "✓ Table `order_tracking_logs` is ready.\n";
} else {
    echo "✗ Failed to create table `order_tracking_logs`: " . $conn->error . "\n";
}

// 3. Sync existing orders
$ordersRes = $conn->query("SELECT id, order_date, payment_status, order_status, shipping_courier, shipping_city, recipient_name FROM orders");
if ($ordersRes) {
    while ($row = $ordersRes->fetch_assoc()) {
        $orderId = $row['id'];
        $pStatus = $row['payment_status'];
        $currentOrdStatus = $row['order_status'];

        $newOrdStatus = $currentOrdStatus;
        if ($pStatus === 'paid' && ($currentOrdStatus === 'unpaid' || empty($currentOrdStatus))) {
            $newOrdStatus = 'processing';
        } elseif ($pStatus === 'rejected') {
            $newOrdStatus = 'cancelled';
        } elseif ($pStatus === 'pending' || $pStatus === 'waiting_verification') {
            $newOrdStatus = 'unpaid';
        }

        $conn->query("UPDATE orders SET order_status = '{$newOrdStatus}' WHERE id = {$orderId}");

        // Check if logs already exist for this order
        $logCheck = $conn->query("SELECT COUNT(*) as c FROM order_tracking_logs WHERE order_id = {$orderId}");
        $hasLogs = $logCheck ? (int)$logCheck->fetch_assoc()['c'] : 0;

        if ($hasLogs === 0) {
            $orderDate = $row['order_date'] ?: date('Y-m-d H:i:s');
            // Log 1: Order Created
            $stmt = $conn->prepare("INSERT INTO order_tracking_logs (order_id, status_key, title, description, location, created_at) VALUES (?, 'created', 'Pesanan Berhasil Dibuat', 'Menunggu pembayaran dari pembeli.', 'NPGLOW System', ?)");
            $stmt->bind_param("is", $orderId, $orderDate);
            $stmt->execute();

            if ($pStatus === 'waiting_verification' || $pStatus === 'paid') {
                $payDate = date('Y-m-d H:i:s', strtotime($orderDate . ' +10 minutes'));
                $stmt = $conn->prepare("INSERT INTO order_tracking_logs (order_id, status_key, title, description, location, created_at) VALUES (?, 'payment_uploaded', 'Bukti Pembayaran Diunggah', 'Bukti pembayaran telah dikirim dan menunggu verifikasi admin.', 'NPGLOW System', ?)");
                $stmt->bind_param("is", $orderId, $payDate);
                $stmt->execute();
            }

            if ($pStatus === 'paid') {
                $procDate = date('Y-m-d H:i:s', strtotime($orderDate . ' +30 minutes'));
                $stmt = $conn->prepare("INSERT INTO order_tracking_logs (order_id, status_key, title, description, location, created_at) VALUES (?, 'processing', 'Pembayaran Terverifikasi & Pesanan Sedang Dikemas', 'Pesanan sedang disiapkan dan dipacking dengan aman oleh tim gudang.', 'Gudang Pusat NPGLOW Jakarta', ?)");
                $stmt->bind_param("is", $orderId, $procDate);
                $stmt->execute();
            }
        }
    }
    echo "✓ Existing orders synced with tracking logs.\n";
}

echo "\n=== Migration Completed Successfully! ===\n";
