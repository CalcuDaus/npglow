<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// For normal user: user_id is their own id. For admin: user_id is the target user they are chatting with.
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
$user_id = $is_admin && isset($_GET['target_user_id']) ? (int)$_GET['target_user_id'] : $_SESSION['user_id'];

if ($action == 'fetch') {
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    
    $stmt = $conn->prepare("SELECT * FROM chats WHERE user_id = ? AND id > ? ORDER BY id ASC");
    $stmt->bind_param("ii", $user_id, $last_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    echo json_encode(['messages' => $messages]);
    exit();
} elseif ($action == 'send' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $message = trim($_POST['message'] ?? '');
    if (empty($message)) {
        echo json_encode(['error' => 'Empty message']);
        exit();
    }
    
    $sender = $is_admin ? 'admin' : 'user';
    
    $stmt = $conn->prepare("INSERT INTO chats (user_id, sender, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $sender, $message);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        echo json_encode(['error' => 'Failed to send']);
    }
    exit();
}

echo json_encode(['error' => 'Invalid action']);
