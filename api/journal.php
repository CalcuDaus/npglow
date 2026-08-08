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

require_once '../includes/image-helper.php';

// ============================================
// Upload progress photo
// ============================================
if ($action === 'upload_photo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'Foto tidak ditemukan atau gagal diunggah.']);
        exit();
    }

    $file = $_FILES['photo'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp'];
    $maxSize = 10 * 1024 * 1024; // Support up to 10MB camera uploads since we will compress to ~200KB WebP

    if (!in_array($file['type'], $allowedTypes) && !str_starts_with($file['type'], 'image/')) {
        echo json_encode(['error' => 'Format foto tidak valid. Gunakan JPG, PNG, atau WebP.']);
        exit();
    }
    if ($file['size'] > $maxSize) {
        echo json_encode(['error' => 'Ukuran foto terlalu besar. Maksimal 10MB.']);
        exit();
    }

    $userDir = "../uploads/faces/{$userId}";
    if (!is_dir($userDir)) {
        mkdir($userDir, 0755, true);
    }

    // Auto-generate clean WebP filename
    $filename = generate_unique_webp_filename('progress');
    $filepath = "uploads/faces/{$userId}/{$filename}"; // relative from root
    $fullpath = "../{$filepath}";

    // Convert & Compress directly to WebP
    $convertResult = convert_image_to_webp($file['tmp_name'], $fullpath, 82, 1600, 1600);

    if ($convertResult['success']) {
        $notes = trim($_POST['notes'] ?? '');
        $takenAt = !empty($_POST['taken_at']) ? $_POST['taken_at'] : date('Y-m-d');

        $stmt = $conn->prepare("INSERT INTO user_face_photos (user_id, photo_path, photo_type, notes, taken_at) VALUES (?, ?, 'progress', ?, ?)");
        $stmt->bind_param("isss", $userId, $filepath, $notes, $takenAt);

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'photo' => [
                    'id' => $conn->insert_id,
                    'photo_path' => $filepath,
                    'photo_type' => 'progress',
                    'notes' => $notes,
                    'taken_at' => $takenAt,
                    'file_size' => $convertResult['compressed_size'] ?? 0,
                    'savings_percent' => $convertResult['savings_percent'] ?? 0
                ]
            ]);
        } else {
            echo json_encode(['error' => 'Gagal menyimpan data foto.']);
        }
    } else {
        echo json_encode(['error' => 'Gagal memproses dan mengonversi foto: ' . ($convertResult['error'] ?? 'Unknown error')]);
    }
    exit();
}

// ============================================
// Get all journal entries
// ============================================
if ($action === 'get_entries') {
    $stmt = $conn->prepare("
        SELECT ufp.*, 
               cl.summary, cl.skin_condition, cl.recommendation, cl.consultation_date
        FROM user_face_photos ufp
        LEFT JOIN consultation_logs cl ON cl.face_photo_id = ufp.id
        WHERE ufp.user_id = ?
        ORDER BY ufp.taken_at DESC, ufp.created_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $entries[] = $row;
    }

    echo json_encode(['entries' => $entries]);
    exit();
}

// ============================================
// Get comparison (initial vs latest)
// ============================================
if ($action === 'get_comparison') {
    // Initial photo
    $stmt = $conn->prepare("SELECT * FROM user_face_photos WHERE user_id = ? AND photo_type = 'initial' ORDER BY created_at ASC LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $initial = $stmt->get_result()->fetch_assoc();

    // Latest photo
    $stmt = $conn->prepare("SELECT * FROM user_face_photos WHERE user_id = ? ORDER BY taken_at DESC, created_at DESC LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $latest = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        'initial' => $initial,
        'latest' => $latest
    ]);
    exit();
}

// ============================================
// Delete a photo entry
// ============================================
if ($action === 'delete_photo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $photoId = (int)($_POST['photo_id'] ?? 0);
    if ($photoId <= 0) {
        echo json_encode(['error' => 'ID foto tidak valid.']);
        exit();
    }

    // Verify ownership and not initial
    $stmt = $conn->prepare("SELECT * FROM user_face_photos WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $photoId, $userId);
    $stmt->execute();
    $photo = $stmt->get_result()->fetch_assoc();

    if (!$photo) {
        echo json_encode(['error' => 'Foto tidak ditemukan.']);
        exit();
    }
    if ($photo['photo_type'] === 'initial') {
        echo json_encode(['error' => 'Foto awal tidak bisa dihapus.']);
        exit();
    }

    // Delete file
    $fullpath = "../" . $photo['photo_path'];
    if (file_exists($fullpath)) {
        unlink($fullpath);
    }

    // Delete from DB
    $stmt = $conn->prepare("DELETE FROM user_face_photos WHERE id = ?");
    $stmt->bind_param("i", $photoId);
    $stmt->execute();

    echo json_encode(['success' => true]);
    exit();
}

echo json_encode(['error' => 'Invalid action']);
