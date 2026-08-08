<?php
require_once 'includes/config.php';

echo "Setting up settings table...\n";

// 1. Create settings table
$sqlTable = "CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sqlTable) === TRUE) {
    echo "✓ Table `settings` ready.\n";
} else {
    echo "✗ Error creating `settings` table: " . $conn->error . "\n";
}

// 2. Ensure columns in `users` table for online status
$res = $conn->query("SHOW COLUMNS FROM users LIKE 'is_online'");
if ($res->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN is_online TINYINT(1) DEFAULT 0 AFTER role");
    echo "✓ Added column `is_online` to `users`.\n";
}

$res = $conn->query("SHOW COLUMNS FROM users LIKE 'last_active'");
if ($res->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN last_active DATETIME NULL AFTER is_online");
    echo "✓ Added column `last_active` to `users`.\n";
}

// 3. Seed default operational settings
$defaultSettings = [
    'expert_start_time' => '08:00',
    'expert_end_time' => '21:00',
    'expert_work_days' => '1,2,3,4,5,6,7', // 1=Senin, 7=Minggu
    'expert_auto_schedule' => '1',          // 1 = batasi sesuai jam kerja, 0 = non-stop 24/7
    'expert_offline_message' => 'Tim ahli melayani konsultasi setiap hari pukul 08:00 - 21:00 WIB. Gunakan AI Assistant untuk respon instan.'
];

foreach ($defaultSettings as $key => $val) {
    $check = $conn->prepare("SELECT id FROM settings WHERE setting_key = ?");
    $check->bind_param("s", $key);
    $check->execute();
    if ($check->get_result()->num_rows == 0) {
        $insert = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $insert->bind_param("ss", $key, $val);
        $insert->execute();
        echo "✓ Seeded setting: {$key} = {$val}\n";
    }
}

echo "Setup completed successfully!\n";
?>
