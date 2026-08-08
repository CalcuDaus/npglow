<?php
require_once 'includes/config.php';

echo "=== Starting NPGLOW Checkout & Payment Migration ===\n";

// 1. Ensure columns in `users` table for address
$userCols = [
    'phone' => "VARCHAR(30) NULL AFTER email",
    'province' => "VARCHAR(100) NULL AFTER phone",
    'city' => "VARCHAR(100) NULL AFTER province",
    'district' => "VARCHAR(100) NULL AFTER city",
    'postal_code' => "VARCHAR(10) NULL AFTER district",
    'address' => "TEXT NULL AFTER postal_code"
];

foreach ($userCols as $col => $definition) {
    $res = $conn->query("SHOW COLUMNS FROM users LIKE '{$col}'");
    if ($res && $res->num_rows == 0) {
        $sql = "ALTER TABLE users ADD COLUMN {$col} {$definition}";
        if ($conn->query($sql)) {
            echo "✓ Added column `{$col}` to `users`\n";
        } else {
            echo "✗ Error adding `{$col}` to `users`: " . $conn->error . "\n";
        }
    }
}

// 2. Create `payment_bank_accounts` table
$sqlBankTable = "CREATE TABLE IF NOT EXISTS `payment_bank_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bank_name` VARCHAR(50) NOT NULL,
    `bank_code` VARCHAR(20) NOT NULL,
    `account_number` VARCHAR(50) NOT NULL,
    `account_holder` VARCHAR(100) NOT NULL,
    `bank_logo` VARCHAR(255) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sqlBankTable)) {
    echo "✓ Table `payment_bank_accounts` ready\n";
} else {
    echo "✗ Error creating `payment_bank_accounts`: " . $conn->error . "\n";
}

// Seed default bank accounts if empty
$checkBank = $conn->query("SELECT COUNT(*) as count FROM payment_bank_accounts");
$bankCount = $checkBank ? $checkBank->fetch_assoc()['count'] : 0;
if ($bankCount == 0) {
    $banks = [
        ['BCA', 'BCA', '8280912345', 'NPGLOW INDONESIA', 'assets/images/banks/bca.png'],
        ['Mandiri', 'MANDIRI', '1370019283746', 'NPGLOW INDONESIA', 'assets/images/banks/mandiri.png'],
        ['BRI', 'BRI', '012301098765501', 'NPGLOW INDONESIA', 'assets/images/banks/bri.png']
    ];
    $stmt = $conn->prepare("INSERT INTO payment_bank_accounts (bank_name, bank_code, account_number, account_holder, bank_logo, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    foreach ($banks as $b) {
        $stmt->bind_param("sssss", $b[0], $b[1], $b[2], $b[3], $b[4]);
        $stmt->execute();
    }
    echo "✓ Seeded default bank accounts (BCA, Mandiri, BRI)\n";
}

// 3. Ensure columns in `orders` table
$orderCols = [
    'order_number' => "VARCHAR(50) NULL UNIQUE AFTER id",
    'recipient_name' => "VARCHAR(100) NULL AFTER product_id",
    'recipient_phone' => "VARCHAR(30) NULL AFTER recipient_name",
    'shipping_province' => "VARCHAR(100) NULL AFTER recipient_phone",
    'shipping_city' => "VARCHAR(100) NULL AFTER shipping_province",
    'shipping_district' => "VARCHAR(100) NULL AFTER shipping_city",
    'shipping_postal_code' => "VARCHAR(10) NULL AFTER shipping_district",
    'shipping_address' => "TEXT NULL AFTER shipping_postal_code",
    'shipping_courier' => "VARCHAR(50) DEFAULT 'J&T' AFTER shipping_address",
    'shipping_service' => "VARCHAR(50) DEFAULT 'Reguler' AFTER shipping_courier",
    'shipping_cost' => "DECIMAL(10,2) DEFAULT 0.00 AFTER shipping_service",
    'product_price' => "DECIMAL(10,2) DEFAULT 0.00 AFTER shipping_cost",
    'discount_amount' => "DECIMAL(10,2) DEFAULT 0.00 AFTER product_price",
    'total_amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_amount",
    'payment_method' => "ENUM('qris', 'bank_transfer') NOT NULL DEFAULT 'qris' AFTER total_amount",
    'payment_bank_id' => "INT NULL AFTER payment_method",
    'payment_proof' => "VARCHAR(500) NULL AFTER payment_bank_id",
    'payment_status' => "ENUM('pending', 'waiting_verification', 'paid', 'rejected') DEFAULT 'pending' AFTER payment_proof",
    'admin_note' => "TEXT NULL AFTER payment_status",
    'customer_note' => "TEXT NULL AFTER admin_note"
];

foreach ($orderCols as $col => $definition) {
    $res = $conn->query("SHOW COLUMNS FROM orders LIKE '{$col}'");
    if ($res && $res->num_rows == 0) {
        $sql = "ALTER TABLE orders ADD COLUMN {$col} {$definition}";
        if ($conn->query($sql)) {
            echo "✓ Added column `{$col}` to `orders`\n";
        } else {
            echo "✗ Error adding `{$col}` to `orders`: " . $conn->error . "\n";
        }
    }
}

// 4. Ensure settings table and seed QRIS & Shipping settings
$conn->query("CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$checkoutSettings = [
    'payment_qris_image' => 'assets/images/qris-sample.png',
    'payment_qris_merchant' => 'NPGLOW BEAUTY OFFICIAL',
    'payment_qris_active' => '1',
    'payment_bank_active' => '1',
    'shipping_origin_city' => 'Jakarta Barat',
    'shipping_origin_province' => 'DKI Jakarta',
    'shipping_flat_rate' => '15000',
    'shipping_free_min_order' => '100000', // Belanja di atas 100k gratis ongkir
    'shipping_provider' => 'tiered_dynamic' // tiered_dynamic / rajaongkir / biteship
];

foreach ($checkoutSettings as $key => $val) {
    $check = $conn->prepare("SELECT id FROM settings WHERE setting_key = ?");
    $check->bind_param("s", $key);
    $check->execute();
    if ($check->get_result()->num_rows == 0) {
        $insert = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $insert->bind_param("ss", $key, $val);
        $insert->execute();
        echo "✓ Seeded setting: {$key}\n";
    }
}

// 5. Ensure upload directory for payment proofs and QRIS exists
$dirs = [
    'uploads/payments',
    'uploads/payments/proofs',
    'uploads/payments/qris'
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "✓ Created directory `{$dir}`\n";
    }
}

echo "=== Migration Finished Successfully! ===\n";
