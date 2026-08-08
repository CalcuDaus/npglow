<?php
/**
 * NPGLOW Reseller System Migration
 * Run this script once to set up the reseller database structure.
 * 
 * Usage: php migrate_reseller.php  (CLI)
 *    or: http://localhost/npglow/migrate_reseller.php  (Browser)
 */
require_once 'includes/config.php';

echo "=== Starting NPGLOW Reseller Migration ===\n";

// 1. Update users table: add 'reseller' to role ENUM + referral columns
$conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('user','admin','expert','reseller') DEFAULT 'user'");
echo "✓ Updated users.role ENUM to include 'reseller'\n";

// Add referral columns to users
$userCols = [
    'referral_code_used' => "VARCHAR(20) NULL AFTER role",
    'referred_by'        => "INT NULL AFTER referral_code_used",
];
foreach ($userCols as $col => $definition) {
    $res = $conn->query("SHOW COLUMNS FROM users LIKE '{$col}'");
    if ($res && $res->num_rows == 0) {
        if ($conn->query("ALTER TABLE users ADD COLUMN {$col} {$definition}")) {
            echo "✓ Added column `{$col}` to `users`\n";
        } else {
            echo "✗ Error adding `{$col}` to `users`: " . $conn->error . "\n";
        }
    }
}

// 2. Create reseller_stores table
$sql = "CREATE TABLE IF NOT EXISTS `reseller_stores` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `store_name` VARCHAR(150) NOT NULL,
    `store_slug` VARCHAR(100) NOT NULL UNIQUE,
    `referral_code` VARCHAR(20) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `store_logo` VARCHAR(500) NULL,
    `store_banner` VARCHAR(500) NULL,

    -- Address & Location
    `address` TEXT NULL,
    `province` VARCHAR(100) NULL,
    `city` VARCHAR(100) NULL,
    `district` VARCHAR(100) NULL,
    `postal_code` VARCHAR(10) NULL,
    `latitude` DECIMAL(10, 8) NULL,
    `longitude` DECIMAL(11, 8) NULL,

    -- Contact
    `phone` VARCHAR(30) NULL,
    `whatsapp` VARCHAR(30) NULL,

    -- Status
    `is_active` TINYINT(1) DEFAULT 1,
    `is_verified` TINYINT(1) DEFAULT 0,

    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql)) {
    echo "✓ Table `reseller_stores` ready\n";
} else {
    echo "✗ Error creating `reseller_stores`: " . $conn->error . "\n";
}

// 3. Create reseller_products table
$sql = "CREATE TABLE IF NOT EXISTS `reseller_products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reseller_store_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `custom_price` DECIMAL(10,2) NULL,
    `stock` INT DEFAULT 0,
    `is_available` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`reseller_store_id`) REFERENCES `reseller_stores`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_store_product` (`reseller_store_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql)) {
    echo "✓ Table `reseller_products` ready\n";
} else {
    echo "✗ Error creating `reseller_products`: " . $conn->error . "\n";
}

// 4. Add reseller_store_id to orders table
$orderCols = [
    'reseller_store_id' => "INT NULL AFTER user_id",
];
foreach ($orderCols as $col => $definition) {
    $res = $conn->query("SHOW COLUMNS FROM orders LIKE '{$col}'");
    if ($res && $res->num_rows == 0) {
        if ($conn->query("ALTER TABLE orders ADD COLUMN {$col} {$definition}")) {
            echo "✓ Added column `{$col}` to `orders`\n";
        } else {
            echo "✗ Error adding `{$col}` to `orders`: " . $conn->error . "\n";
        }
    }
}

// 5. Add minimum_price column to products table (for reseller floor price)
$res = $conn->query("SHOW COLUMNS FROM products LIKE 'minimum_price'");
if ($res && $res->num_rows == 0) {
    if ($conn->query("ALTER TABLE products ADD COLUMN `minimum_price` DECIMAL(10,2) NULL AFTER `price`")) {
        echo "✓ Added column `minimum_price` to `products`\n";
        // Set minimum_price = price for existing products
        $conn->query("UPDATE products SET minimum_price = price WHERE minimum_price IS NULL");
        echo "✓ Set minimum_price = price for existing products\n";
    } else {
        echo "✗ Error adding `minimum_price` to `products`: " . $conn->error . "\n";
    }
}

// 6. Create upload directories for reseller assets
$dirs = [
    'uploads/reseller',
    'uploads/reseller/logos',
    'uploads/reseller/banners',
];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "✓ Created directory `{$dir}`\n";
    }
}

echo "\n=== Reseller Migration Finished Successfully! ===\n";
echo "Next steps:\n";
echo "  1. Go to Admin Dashboard → Kelola Reseller to create reseller accounts.\n";
echo "  2. Reseller can then login and manage their store.\n";
