<?php
// api/expert-heartbeat.php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'expert') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$expertId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'ping';

if ($action === 'ping') {
    // Normal heartbeat: expert is actively viewing dashboard
    $stmt = $conn->prepare("UPDATE users SET is_online = 1, last_active = NOW() WHERE id = ?");
    $stmt->bind_param("i", $expertId);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'action' => 'ping',
        'is_online' => 1,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

if ($action === 'toggle') {
    // Toggle between online & offline manually
    $status = isset($_POST['is_online']) ? (int)$_POST['is_online'] : 1;
    $stmt = $conn->prepare("UPDATE users SET is_online = ?, last_active = NOW() WHERE id = ?");
    $stmt->bind_param("ii", $status, $expertId);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'action' => 'toggle',
        'is_online' => $status
    ]);
    exit();
}

if ($action === 'offline' || $action === 'away') {
    // Tab closed or expert set to away/idle
    $stmt = $conn->prepare("UPDATE users SET is_online = 0 WHERE id = ?");
    $stmt->bind_param("i", $expertId);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'action' => 'offline',
        'is_online' => 0
    ]);
    exit();
}

if ($action === 'get_status') {
    $stmt = $conn->prepare("SELECT is_online, last_active FROM users WHERE id = ?");
    $stmt->bind_param("i", $expertId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'is_online' => (int)($row['is_online'] ?? 0),
        'last_active' => $row['last_active'] ?? null
    ]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>
