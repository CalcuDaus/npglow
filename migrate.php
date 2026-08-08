<?php
require_once 'includes/config.php';

// 1. Add role column and drop is_admin
$conn->query("ALTER TABLE users ADD COLUMN role ENUM('user', 'admin', 'expert') DEFAULT 'user' AFTER has_purchased");
$conn->query("UPDATE users SET role = 'admin' WHERE is_admin = 1");
$conn->query("ALTER TABLE users DROP COLUMN is_admin");
echo "Users table updated.\n";

// 2. Update chats sender enum
$conn->query("ALTER TABLE chats MODIFY COLUMN sender ENUM('user', 'admin', 'expert') NOT NULL");
echo "Chats table updated.\n";

// 3. Update consultation_logs. We'll drop the FK, rename column, add FK back.
// First get the FK name
$res = $conn->query("
    SELECT CONSTRAINT_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'npglow' AND TABLE_NAME = 'consultation_logs' AND COLUMN_NAME = 'admin_id'
");
if ($row = $res->fetch_assoc()) {
    $fk_name = $row['CONSTRAINT_NAME'];
    $conn->query("ALTER TABLE consultation_logs DROP FOREIGN KEY `$fk_name`");
}
$conn->query("ALTER TABLE consultation_logs CHANGE admin_id expert_id INT DEFAULT NULL");
$conn->query("ALTER TABLE consultation_logs ADD FOREIGN KEY (expert_id) REFERENCES users(id) ON DELETE SET NULL");
echo "Consultation logs table updated.\n";

// 4. Insert dummy expert
$conn->query("INSERT INTO users (name, email, password, role) VALUES ('Tim Ahli NPGLOW', 'expert@npglow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'expert')");
echo "Dummy expert inserted.\n";

echo "Migration complete.\n";
?>
