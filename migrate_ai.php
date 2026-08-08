<?php
/**
 * Migration script to add AI chat support and online status tracking.
 * Run this once: http://localhost/npglow/migrate_ai.php
 */
require_once 'includes/config.php';

$queries = [
    // AI chat messages table
    "CREATE TABLE IF NOT EXISTS ai_chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        sender ENUM('user', 'ai') NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    // Add online status columns to users (will error and skip if already exists)
    "ALTER TABLE users ADD COLUMN is_online BOOLEAN DEFAULT FALSE",
    "ALTER TABLE users ADD COLUMN last_active TIMESTAMP NULL"
];

$success = true;
foreach ($queries as $sql) {
    try {
        $conn->query($sql);
    } catch (Exception $e) {
        // Ignore duplicate column errors
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            echo "❌ Error: " . $e->getMessage() . "<br>SQL: " . $sql . "<br><br>";
            $success = false;
        }
    }
}

if ($success) {
    echo "✅ Migration completed successfully!<br>";
    echo "- Table 'ai_chats' created<br>";
    echo "- Columns 'is_online' and 'last_active' added to 'users' table<br>";
    echo "<br><a href='dashboard.php'>← Kembali ke Dashboard</a>";
} else {
    echo "<br>⚠️ Some queries failed. Check errors above.";
}
?>
