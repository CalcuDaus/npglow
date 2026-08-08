<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$userId = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ============================================
// Update user name
// ============================================
if ($action === 'update_name' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if (empty($name) || strlen($name) < 2) {
        echo json_encode(['error' => 'Nama harus minimal 2 karakter.']);
        exit();
    }

    $stmt = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
    $stmt->bind_param("si", $name, $userId);
    if ($stmt->execute()) {
        $_SESSION['user_name'] = $name;
        echo json_encode(['success' => true, 'name' => $name]);
    } else {
        echo json_encode(['error' => 'Gagal memperbarui nama.']);
    }
    exit();
}

// ============================================
// Get order history
// ============================================
if ($action === 'get_orders') {
    $stmt = $conn->prepare("
        SELECT o.*, p.name as product_name, p.price, p.image_url 
        FROM orders o 
        JOIN products p ON o.product_id = p.id 
        WHERE o.user_id = ? 
        ORDER BY o.order_date DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    echo json_encode(['orders' => $orders]);
    exit();
}

echo json_encode(['error' => 'Invalid action']);
