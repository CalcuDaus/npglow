<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reseller-helper.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 1. Validate referral code
if ($action === 'validate') {
    $code = trim($_POST['code'] ?? $_GET['code'] ?? '');
    if (empty($code)) {
        echo json_encode(['valid' => false, 'error' => 'Kode referral tidak boleh kosong']);
        exit();
    }
    $store = validate_referral_code($conn, $code);
    if ($store) {
        echo json_encode([
            'valid' => true,
            'store' => [
                'id' => (int)$store['id'],
                'store_name' => $store['store_name'],
                'referral_code' => $store['referral_code'],
                'city' => $store['city'],
                'province' => $store['province'],
                'whatsapp' => $store['whatsapp'],
                'store_logo' => $store['store_logo']
            ]
        ]);
    } else {
        echo json_encode(['valid' => false, 'error' => 'Kode referral tidak ditemukan atau tidak aktif']);
    }
    exit();
}

// 2. Set / Change referral for logged in user
if ($action === 'set_referral') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Silakan login terlebih dahulu']);
        exit();
    }
    $userId = (int)$_SESSION['user_id'];
    $code = trim($_POST['code'] ?? '');

    $store = validate_referral_code($conn, $code);
    if (!$store) {
        echo json_encode(['success' => false, 'error' => 'Kode referral tidak valid atau toko mitra tidak aktif']);
        exit();
    }

    $resellerUserId = (int)$store['user_id'];
    if ($resellerUserId === $userId) {
        echo json_encode(['success' => false, 'error' => 'Anda tidak dapat memilih toko Anda sendiri sebagai referral']);
        exit();
    }

    $ok = set_user_referral($conn, $userId, $resellerUserId);
    echo json_encode([
        'success' => $ok,
        'store' => [
            'store_name' => $store['store_name'],
            'referral_code' => $store['referral_code'],
            'city' => $store['city']
        ]
    ]);
    exit();
}

// 3. Clear referral (revert to official store)
if ($action === 'clear_referral') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Silakan login terlebih dahulu']);
        exit();
    }
    $userId = (int)$_SESSION['user_id'];
    $ok = set_user_referral($conn, $userId, null);
    echo json_encode(['success' => $ok]);
    exit();
}

// 4. Get nearest stores by GPS coordinates
if ($action === 'get_nearest') {
    $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : (isset($_POST['lat']) ? (float)$_POST['lat'] : null);
    $lng = isset($_GET['lng']) ? (float)$_GET['lng'] : (isset($_POST['lng']) ? (float)$_POST['lng'] : null);

    if ($lat === null || $lng === null) {
        // Return all active stores without distance calculation
        $res = $conn->query("
            SELECT id, store_name, store_slug, referral_code, description, phone, whatsapp,
                   address, province, city, district, postal_code, latitude, longitude, store_logo
            FROM reseller_stores
            WHERE is_active = 1
            ORDER BY id DESC
        ");
        $stores = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        echo json_encode(['success' => true, 'stores' => $stores]);
        exit();
    }

    $stores = get_nearest_resellers($conn, $lat, $lng, 20);
    echo json_encode(['success' => true, 'stores' => $stores]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Aksi tidak dikenali']);
