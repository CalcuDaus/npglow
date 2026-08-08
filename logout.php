<?php
session_start();
require_once 'includes/config.php';

if (isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("UPDATE users SET is_online = 0 WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }
}

session_unset();
session_destroy();
header("Location: index.php");
exit();
?>
