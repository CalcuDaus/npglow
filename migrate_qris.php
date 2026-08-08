<?php
require_once 'includes/config.php';

echo "=== Creating payment_qris_accounts table ===\n";

$sql = "CREATE TABLE IF NOT EXISTS payment_qris_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_name VARCHAR(100) NOT NULL DEFAULT 'NPGLOW BEAUTY OFFICIAL',
    nmid VARCHAR(50) NULL,
    image_path VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql)) {
    echo "✓ Table `payment_qris_accounts` ready.\n";
} else {
    echo "✗ Error creating table: " . $conn->error . "\n";
}

// Seed default QRIS if empty
$check = $conn->query("SELECT COUNT(*) as c FROM payment_qris_accounts")->fetch_assoc()['c'] ?? 0;
if ($check == 0) {
    // Check if we have an existing setting image
    $settingImg = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'payment_qris_image'")->fetch_assoc()['setting_value'] ?? 'assets/images/qris-sample.png';
    $merchant = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'payment_qris_merchant'")->fetch_assoc()['setting_value'] ?? 'NPGLOW BEAUTY OFFICIAL';

    $stmt = $conn->prepare("INSERT INTO payment_qris_accounts (merchant_name, nmid, image_path, is_active, is_primary) VALUES (?, 'ID1020000000000', ?, 1, 1)");
    $stmt->bind_param("ss", $merchant, $settingImg);
    $stmt->execute();
    echo "✓ Default QRIS Statis seeded.\n";
}

echo "=== Done ===\n";
