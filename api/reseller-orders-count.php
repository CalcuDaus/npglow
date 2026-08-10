<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reseller-helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$store = get_reseller_store_by_user($conn, $userId);

if (!$store) {
    echo json_encode(['success' => false]);
    exit();
}

// Count active orders (not delivered and not cancelled)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE reseller_store_id = ? AND order_status NOT IN ('delivered', 'cancelled')");
$stmt->bind_param("i", $store['id']);
$stmt->execute();
$count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

echo json_encode(['success' => true, 'count' => (int)$count]);
