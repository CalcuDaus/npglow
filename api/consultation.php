<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/config.php';

// Ensure user is expert or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['expert', 'admin'])) {
    echo json_encode(['error' => 'Akses ditolak. Silakan login sebagai Tim Ahli atau Admin.']);
    exit();
}

$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'];
$action = $_GET['action'] ?? '';

// ============================================
// Save consultation log / diagnosis
// ============================================
if ($action === 'save_log' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUserId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $facePhotoId = !empty($_POST['face_photo_id']) ? (int)$_POST['face_photo_id'] : null;
    $skinCondition = trim($_POST['skin_condition'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $recommendation = trim($_POST['recommendation'] ?? '');
    $consultationDate = !empty($_POST['consultation_date']) ? $_POST['consultation_date'] : date('Y-m-d');

    if ($targetUserId <= 0) {
        echo json_encode(['error' => 'Customer tidak valid.']);
        exit();
    }

    if (empty($summary) && empty($skinCondition)) {
        echo json_encode(['error' => 'Catatan diagnosa atau kondisi kulit tidak boleh kosong.']);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO consultation_logs (user_id, expert_id, face_photo_id, skin_condition, summary, recommendation, consultation_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiissss", $targetUserId, $currentUserId, $facePhotoId, $skinCondition, $summary, $recommendation, $consultationDate);

    if ($stmt->execute()) {
        $logId = $conn->insert_id;
        
        // Fetch created log details with expert name
        $fetchStmt = $conn->prepare("
            SELECT cl.*, u.name as expert_name, u.role as expert_role, ufp.photo_path
            FROM consultation_logs cl
            LEFT JOIN users u ON u.id = cl.expert_id
            LEFT JOIN user_face_photos ufp ON ufp.id = cl.face_photo_id
            WHERE cl.id = ?
        ");
        $fetchStmt->bind_param("i", $logId);
        $fetchStmt->execute();
        $newLog = $fetchStmt->get_result()->fetch_assoc();

        echo json_encode([
            'success' => true,
            'message' => 'Catatan konsultasi berhasil disimpan.',
            'log' => $newLog
        ]);
    } else {
        echo json_encode(['error' => 'Gagal menyimpan catatan konsultasi: ' . $conn->error]);
    }
    exit();
}

// ============================================
// Get all consultation logs for a customer
// ============================================
if ($action === 'get_logs') {
    $targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if ($targetUserId <= 0) {
        echo json_encode(['error' => 'Customer ID diperlukan.']);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT cl.*, u.name as expert_name, u.role as expert_role, ufp.photo_path, ufp.photo_type
        FROM consultation_logs cl
        LEFT JOIN users u ON u.id = cl.expert_id
        LEFT JOIN user_face_photos ufp ON ufp.id = cl.face_photo_id
        WHERE cl.user_id = ?
        ORDER BY cl.consultation_date DESC, cl.created_at DESC
    ");
    $stmt->bind_param("i", $targetUserId);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'logs' => $logs
    ]);
    exit();
}

// ============================================
// Delete consultation log
// ============================================
if ($action === 'delete_log' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $logId = isset($_POST['log_id']) ? (int)$_POST['log_id'] : 0;
    if ($logId <= 0) {
        echo json_encode(['error' => 'ID catatan tidak valid.']);
        exit();
    }

    // Verify ownership or admin privilege
    if ($currentUserRole === 'admin') {
        $stmt = $conn->prepare("DELETE FROM consultation_logs WHERE id = ?");
        $stmt->bind_param("i", $logId);
    } else {
        $stmt = $conn->prepare("DELETE FROM consultation_logs WHERE id = ? AND expert_id = ?");
        $stmt->bind_param("ii", $logId, $currentUserId);
    }

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Catatan berhasil dihapus.']);
    } else {
        echo json_encode(['error' => 'Gagal menghapus catatan atau tidak memiliki hak akses.']);
    }
    exit();
}

echo json_encode(['error' => 'Aksi tidak valid.']);
exit();
