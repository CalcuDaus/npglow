<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/settings-helper.php';
require_once '../includes/order-tracking-helper.php';
require_once '../includes/icon-helper.php';

// Auth Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$successMsg = '';
$errorMsg = '';

// Handle Approve Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_order') {
    $orderId = (int)$_POST['order_id'];
    
    // Get user id from order
    $stmt = $conn->prepare("SELECT user_id FROM orders WHERE id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $orderData = $stmt->get_result()->fetch_assoc();

    if ($orderData) {
        $userId = $orderData['user_id'];
        // Update order status to paid & processing (sedang dikemas)
        $updateStmt = $conn->prepare("UPDATE orders SET payment_status = 'paid', order_status = 'processing', status = 'completed' WHERE id = ?");
        $updateStmt->bind_param("i", $orderId);
        $updateStmt->execute();

        // Update user status
        $userStmt = $conn->prepare("UPDATE users SET has_purchased = 1 WHERE id = ?");
        $userStmt->bind_param("i", $userId);
        $userStmt->execute();

        // Log tracking timeline
        add_order_tracking_log(
            $conn, 
            $orderId, 
            'processing', 
            'Pembayaran Terverifikasi & Pesanan Sedang Dikemas', 
            'Pembayaran telah diverifikasi oleh Admin. Tim gudang NPGLOW sedang mengemas produk dan mempersiapkan pengiriman.', 
            'Gudang Pusat NPGLOW'
        );

        $successMsg = "Pembayaran pesanan berhasil disetujui! Status pesanan kini: Sedang Dikemas.";
    } else {
        $errorMsg = "Pesanan tidak ditemukan.";
    }
}

// Handle Ship Order (Input Tracking Number)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ship_order') {
    $orderId = (int)$_POST['order_id'];
    $trackingNumber = trim($_POST['tracking_number'] ?? '');
    $courier = trim($_POST['shipping_courier'] ?? 'J&T');
    $location = trim($_POST['location'] ?? 'Drop Point Jakarta');
    $note = trim($_POST['tracking_note'] ?? '');

    if (!empty($trackingNumber)) {
        $desc = "Paket telah diserahkan ke jasa ekspedisi {$courier} dengan nomor resi {$trackingNumber}. " . ($note ? $note : "Paket sedang dalam perjalanan menuju alamat penerima.");
        if (mark_order_as_shipped($conn, $orderId, $trackingNumber, $courier, $desc, $location)) {
            $successMsg = "Pesanan berhasil ditandai sebagai DIKIRIM dengan No. Resi {$trackingNumber}!";
        } else {
            $errorMsg = "Gagal memperbarui status pengiriman.";
        }
    } else {
        $errorMsg = "Nomor resi pengiriman wajib diisi.";
    }
}

// Handle Add Tracking Checkpoint Log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_tracking_log') {
    $orderId = (int)$_POST['order_id'];
    $title = trim($_POST['log_title'] ?? 'Update Perjalanan Paket');
    $description = trim($_POST['log_desc'] ?? '');
    $location = trim($_POST['log_location'] ?? '');

    if (!empty($description)) {
        if (add_order_tracking_log($conn, $orderId, 'shipped', $title, $description, $location)) {
            $successMsg = "Update perjalanan paket berhasil ditambahkan ke timeline pelacakan!";
        } else {
            $errorMsg = "Gagal menambahkan update pelacakan.";
        }
    } else {
        $errorMsg = "Keterangan perjalanan paket wajib diisi.";
    }
}

// Handle Mark as Delivered
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deliver_order') {
    $orderId = (int)$_POST['order_id'];
    $note = trim($_POST['deliver_note'] ?? 'Paket telah diterima di alamat tujuan.');

    if (mark_order_as_delivered($conn, $orderId, $note)) {
        $successMsg = "Pesanan berhasil ditandai SELESAI (Diterima Pembeli)!";
    } else {
        $errorMsg = "Gagal menyelesaikan pesanan.";
    }
}

// Handle Reject Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_order') {
    $orderId = (int)$_POST['order_id'];
    $adminNote = trim($_POST['admin_note'] ?? 'Bukti pembayaran tidak valid / dana belum masuk.');

    $updateStmt = $conn->prepare("UPDATE orders SET payment_status = 'rejected', order_status = 'unpaid', admin_note = ? WHERE id = ?");
    $updateStmt->bind_param("si", $adminNote, $orderId);
    $updateStmt->execute();

    // Log rejection
    add_order_tracking_log(
        $conn, 
        $orderId, 
        'unpaid', 
        'Bukti Pembayaran Ditolak', 
        'Bukti pembayaran ditolak oleh Admin: ' . $adminNote . '. Silakan upload bukti yang sah.', 
        'Admin NPGLOW'
    );

    $successMsg = "Pembayaran pesanan ditolak dan catatan telah dikirim ke pembeli.";
}

// Filter Status & Search & Pagination Setup
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if (!in_array($limit, [5, 10, 25, 50, 100])) {
    $limit = 10;
}
$page = max(1, (int)($_GET['page'] ?? 1));

// Build dynamic WHERE clause
$whereClauses = ["o.reseller_store_id IS NULL"];
$params = [];
$paramTypes = "";

if ($filterStatus === 'waiting_verification') {
    $whereClauses[] = "o.payment_status = 'waiting_verification'";
} elseif ($filterStatus === 'processing') {
    $whereClauses[] = "o.order_status = 'processing'";
} elseif ($filterStatus === 'shipped') {
    $whereClauses[] = "o.order_status = 'shipped'";
} elseif ($filterStatus === 'delivered') {
    $whereClauses[] = "o.order_status = 'delivered'";
} elseif ($filterStatus === 'unpaid') {
    $whereClauses[] = "(o.order_status = 'unpaid' OR o.payment_status = 'pending')";
} elseif ($filterStatus === 'rejected') {
    $whereClauses[] = "o.payment_status = 'rejected'";
}

if (!empty($search)) {
    $whereClauses[] = "(o.order_number LIKE ? OR u.name LIKE ? OR u.phone LIKE ? OR o.recipient_name LIKE ? OR o.recipient_phone LIKE ? OR o.tracking_number LIKE ?)";
    $searchWildcard = "%{$search}%";
    for ($i = 0; $i < 6; $i++) {
        $params[] = $searchWildcard;
    }
    $paramTypes .= "ssssss";
}

$whereSql = !empty($whereClauses) ? " WHERE " . implode(" AND ", $whereClauses) : "";

// Count Total Filtered Records
$countQuery = "SELECT COUNT(*) as total
               FROM orders o
               JOIN users u ON o.user_id = u.id
               JOIN products p ON o.product_id = p.id
               LEFT JOIN payment_bank_accounts b ON o.payment_bank_id = b.id " . $whereSql;

if (!empty($params)) {
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param($paramTypes, ...$params);
    $countStmt->execute();
    $totalFiltered = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
} else {
    $totalFiltered = (int)($conn->query($countQuery)->fetch_assoc()['total'] ?? 0);
}

// Calculate Pagination Variables
$totalPages = max(1, (int)ceil($totalFiltered / $limit));
if ($page > $totalPages && $totalFiltered > 0) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;

// Fetch Paginated Orders
$query = "SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone, p.name as product_name, p.image_url as product_image, b.bank_name
          FROM orders o
          JOIN users u ON o.user_id = u.id
          JOIN products p ON o.product_id = p.id
          LEFT JOIN payment_bank_accounts b ON o.payment_bank_id = b.id " . $whereSql . " ORDER BY o.order_date DESC LIMIT ? OFFSET ?";

$paramsWithLimit = $params;
$paramsWithLimit[] = $limit;
$paramsWithLimit[] = $offset;
$paramTypesWithLimit = $paramTypes . "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($paramTypesWithLimit, ...$paramsWithLimit);
$stmt->execute();
$orders = $stmt->get_result();

// Counts for filter pills
$countTotal = $conn->query("SELECT COUNT(*) as c FROM orders WHERE reseller_store_id IS NULL")->fetch_assoc()['c'] ?? 0;
$countWaiting = $conn->query("SELECT COUNT(*) as c FROM orders WHERE payment_status = 'waiting_verification' AND reseller_store_id IS NULL")->fetch_assoc()['c'] ?? 0;
$countProcessing = $conn->query("SELECT COUNT(*) as c FROM orders WHERE order_status = 'processing' AND reseller_store_id IS NULL")->fetch_assoc()['c'] ?? 0;
$countShipped = $conn->query("SELECT COUNT(*) as c FROM orders WHERE order_status = 'shipped' AND reseller_store_id IS NULL")->fetch_assoc()['c'] ?? 0;
$countDelivered = $conn->query("SELECT COUNT(*) as c FROM orders WHERE order_status = 'delivered' AND reseller_store_id IS NULL")->fetch_assoc()['c'] ?? 0;
$countUnpaid = $conn->query("SELECT COUNT(*) as c FROM orders WHERE (order_status = 'unpaid' OR payment_status = 'pending') AND reseller_store_id IS NULL")->fetch_assoc()['c'] ?? 0;

// Helper to generate pagination URLs
function buildPaginationUrl($targetPage, $status, $search, $limit) {
    $params = ['page' => $targetPage];
    if ($status && $status !== 'all') {
        $params['status'] = $status;
    }
    if (!empty($search)) {
        $params['search'] = $search;
    }
    if ($limit && $limit != 10) {
        $params['limit'] = $limit;
    }
    return 'orders.php?' . http_build_query($params);
}

$startItem = $totalFiltered > 0 ? $offset + 1 : 0;
$endItem = min($offset + $limit, $totalFiltered);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pesanan & Resi - Admin NPGLOW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3ca6f2',
                        'primary-dark': '#2e8ccf',
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-slate-800 antialiased font-sans flex min-h-screen">
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 min-h-screen overflow-x-hidden">
        <!-- Topbar -->
        <?php include 'topbar.php'; ?>

        <!-- Content Body -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="max-w-7xl mx-auto space-y-6 pb-12">
            
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                        <span class="p-2 bg-blue-100/70 text-primary rounded-xl inline-flex">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                        </span>
                        Manajemen Pesanan & Pelacakan Resi
                    </h2>
                    <p class="text-gray-500 text-sm mt-1">Verifikasi pembayaran, input resi ekspedisi, update pergerakan transit, dan pantau status pengiriman.</p>
                </div>
            </div>

            <!-- Notifications -->
            <?php if (!empty($successMsg)): ?>
                <div class="bg-emerald-50 text-emerald-700 p-4 rounded-2xl border border-emerald-200 flex items-center gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span class="text-sm font-semibold"><?= htmlspecialchars($successMsg) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($errorMsg)): ?>
                <div class="bg-red-50 text-red-700 p-4 rounded-2xl border border-red-200 flex items-center gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    <span class="text-sm font-semibold"><?= htmlspecialchars($errorMsg) ?></span>
                </div>
            <?php endif; ?>

            <!-- Status Filter Tabs -->
            <div class="flex flex-wrap gap-2 pb-1">
                <a href="<?= buildPaginationUrl(1, 'all', $search, $limit) ?>" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $filterStatus === 'all' ? 'bg-slate-800 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' ?>">
                    Semua
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] <?= $filterStatus === 'all' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700' ?>"><?= $countTotal ?></span>
                </a>
                <a href="<?= buildPaginationUrl(1, 'waiting_verification', $search, $limit) ?>" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $filterStatus === 'waiting_verification' ? 'bg-amber-500 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-amber-50 border border-gray-200' ?>">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    Menunggu Verifikasi
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] <?= $filterStatus === 'waiting_verification' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-700' ?>"><?= $countWaiting ?></span>
                </a>
                <a href="<?= buildPaginationUrl(1, 'processing', $search, $limit) ?>" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $filterStatus === 'processing' ? 'bg-primary text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-blue-50 border border-gray-200' ?>">
                    <span class="w-2 h-2 rounded-full bg-sky-400"></span>
                    Sedang Dikemas
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] <?= $filterStatus === 'processing' ? 'bg-white/20 text-white' : 'bg-blue-100 text-primary' ?>"><?= $countProcessing ?></span>
                </a>
                <a href="<?= buildPaginationUrl(1, 'shipped', $search, $limit) ?>" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $filterStatus === 'shipped' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-indigo-50 border border-gray-200' ?>">
                    <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
                    Sedang Dikirim
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] <?= $filterStatus === 'shipped' ? 'bg-white/20 text-white' : 'bg-indigo-100 text-indigo-700' ?>"><?= $countShipped ?></span>
                </a>
                <a href="<?= buildPaginationUrl(1, 'delivered', $search, $limit) ?>" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $filterStatus === 'delivered' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-emerald-50 border border-gray-200' ?>">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    Selesai
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] <?= $filterStatus === 'delivered' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-700' ?>"><?= $countDelivered ?></span>
                </a>
                <a href="<?= buildPaginationUrl(1, 'unpaid', $search, $limit) ?>" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $filterStatus === 'unpaid' ? 'bg-slate-600 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' ?>">
                    Belum Bayar
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] <?= $filterStatus === 'unpaid' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700' ?>"><?= $countUnpaid ?></span>
                </a>
            </div>

            <!-- Search and Per-Page Control Bar -->
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex flex-col md:flex-row items-center justify-between gap-3">
                <!-- Search Box -->
                <form method="GET" class="w-full md:w-auto flex-1 flex items-center gap-2">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
                    <input type="hidden" name="limit" value="<?= $limit ?>">
                    <div class="relative flex-1 max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari No. Order, Nama, No. HP, atau Resi..." class="w-full pl-9 pr-8 py-2 rounded-xl border border-gray-200 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition">
                        <?php if (!empty($search)): ?>
                            <a href="<?= buildPaginationUrl(1, $filterStatus, '', $limit) ?>" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600" title="Hapus Pencarian">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </a>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-1.5 shadow-sm">
                        <span>Cari</span>
                    </button>
                </form>

                <!-- Limit & Info Selector -->
                <div class="w-full md:w-auto flex items-center justify-between md:justify-end gap-3 text-xs text-gray-500">
                    <div class="flex items-center gap-2">
                        <span>Tampilkan:</span>
                        <select onchange="window.location.href=this.value" class="px-2.5 py-1.5 rounded-xl border border-gray-200 bg-white text-xs font-bold text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer">
                            <?php foreach ([5, 10, 25, 50, 100] as $optLimit): ?>
                                <option value="<?= buildPaginationUrl(1, $filterStatus, $search, $optLimit) ?>" <?= $limit === $optLimit ? 'selected' : '' ?>><?= $optLimit ?> baris</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Orders Table Container -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-50 text-gray-700 uppercase font-semibold text-xs border-b border-gray-100">
                            <tr>
                                <th class="p-4">No. Order & Waktu</th>
                                <th class="p-4">Penerima & Alamat</th>
                                <th class="p-4">Produk & Ekspedisi</th>
                                <th class="p-4">Total Bayar</th>
                                <th class="p-4 text-center">Bukti Bayar</th>
                                <th class="p-4 text-center">Status Pesanan</th>
                                <th class="p-4 text-center">Aksi Manajemen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if ($orders && $orders->num_rows > 0): ?>
                                <?php while ($o = $orders->fetch_assoc()): ?>
                                    <?php
                                        $meta = get_order_status_info($o['order_status'], $o['payment_status']);
                                        $orderNum = $o['order_number'] ?: ('NP-#' . $o['id']);
                                        $methodLabel = $o['payment_method'] === 'qris' ? 'QRIS Instant' : 'Transfer ' . ($o['bank_name'] ?: 'BCA');
                                        $finalTotal = (float)($o['total_amount'] ?: $o['price']);
                                    ?>
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <!-- Order Number & Date -->
                                        <td class="p-4 align-top">
                                            <span class="font-mono font-bold text-gray-900 block"><?= htmlspecialchars($orderNum) ?></span>
                                            <span class="text-xs text-gray-400"><?= date('d M Y, H:i', strtotime($o['order_date'])) ?></span>
                                            <div class="mt-1">
                                                <span class="text-[10px] text-gray-500 bg-gray-100 px-2 py-0.5 rounded font-mono">ID: #<?= $o['id'] ?></span>
                                            </div>
                                        </td>

                                        <!-- Recipient Info -->
                                        <td class="p-4 align-top">
                                            <p class="font-bold text-gray-900 text-sm"><?= htmlspecialchars($o['recipient_name'] ?: $o['customer_name']) ?></p>
                                            <p class="text-xs text-gray-500 mt-0.5 flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                                <?= htmlspecialchars($o['recipient_phone'] ?: ($o['customer_phone'] ?: '-')) ?>
                                            </p>
                                            <p class="text-[11px] text-gray-400 mt-1 max-w-[200px] leading-tight">
                                                <?= htmlspecialchars($o['shipping_address'] ?: ($o['shipping_city'] ? $o['shipping_city'] . ', ' . $o['shipping_province'] : '-')) ?>
                                            </p>
                                        </td>

                                        <!-- Product & Courier -->
                                        <td class="p-4 align-top">
                                            <div class="flex items-center gap-2.5">
                                                <?php if (!empty($o['product_image'])): ?>
                                                    <img src="../<?= htmlspecialchars($o['product_image']) ?>" class="w-10 h-10 rounded-xl object-cover border border-gray-100 shadow-sm" alt="Produk">
                                                <?php endif; ?>
                                                <div>
                                                    <p class="font-semibold text-gray-800 text-xs leading-snug"><?= htmlspecialchars($o['product_name']) ?></p>
                                                    <span class="inline-flex items-center gap-1 text-[11px] text-primary font-medium mt-0.5">
                                                        <?= npglow_icon('truck', 'w-3 h-3 text-primary') ?> <?= htmlspecialchars($o['shipping_courier'] ?: 'J&T') ?> <?= htmlspecialchars($o['shipping_service'] ?: 'Reguler') ?>
                                                    </span>
                                                    <?php if (!empty($o['tracking_number'])): ?>
                                                        <div class="mt-1 flex items-center gap-1 font-mono text-[11px] font-bold text-gray-700 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                                                            <span>Resi: <?= htmlspecialchars($o['tracking_number']) ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Total & Payment Method -->
                                        <td class="p-4 align-top">
                                            <p class="font-extrabold text-gray-900 text-sm">Rp <?= number_format($finalTotal, 0, ',', '.') ?></p>
                                            <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($methodLabel) ?></p>
                                        </td>

                                        <!-- Payment Proof Preview -->
                                        <td class="p-4 align-top text-center">
                                            <?php if (!empty($o['payment_proof']) && file_exists('../' . $o['payment_proof'])): ?>
                                                <button onclick="previewProof('../<?= htmlspecialchars($o['payment_proof']) ?>', '<?= htmlspecialchars($orderNum) ?>')" class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-50 text-primary hover:bg-blue-100 rounded-xl text-xs font-semibold transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                    Lihat Foto
                                                </button>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-300 italic">Belum ada</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Order & Payment Status Badge -->
                                        <td class="p-4 align-top text-center">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold <?= $meta['badge_class'] ?>">
                                                <span class="w-1.5 h-1.5 rounded-full <?= $meta['dot_class'] ?>"></span>
                                                <?= $meta['label'] ?>
                                            </span>
                                        </td>

                                        <!-- Actions -->
                                        <td class="p-4 align-top text-center">
                                            <div class="flex flex-col gap-1.5 items-center justify-center">
                                                <!-- Action 1: Approve Payment -->
                                                <?php if ($o['payment_status'] === 'waiting_verification' || ($o['payment_status'] === 'pending' && !empty($o['payment_proof']))): ?>
                                                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin pembayaran sudah masuk dan menyetujui pesanan ini?');" class="w-full">
                                                        <input type="hidden" name="action" value="approve_order">
                                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                        <button type="submit" class="w-full px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm flex items-center justify-center gap-1" title="Verifikasi Pembayaran">
                                                            <?= npglow_icon('check', 'w-3.5 h-3.5') ?> Setujui Pembayaran
                                                        </button>
                                                    </form>
                                                    <button onclick="openRejectModal(<?= $o['id'] ?>)" class="w-full px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-600 rounded-xl text-[11px] font-bold transition" title="Tolak Bukti Pembayaran">
                                                        Tolak Bukti
                                                    </button>
                                                <?php endif; ?>

                                                <!-- Action 2: Input Resi / Kirim Paket -->
                                                <?php if ($o['order_status'] === 'processing' || ($o['payment_status'] === 'paid' && $o['order_status'] !== 'shipped' && $o['order_status'] !== 'delivered')): ?>
                                                    <button onclick="openShipModal(<?= $o['id'] ?>, '<?= htmlspecialchars($orderNum) ?>', '<?= htmlspecialchars($o['shipping_courier'] ?: 'J&T') ?>')" class="w-full px-3 py-1.5 bg-primary hover:bg-primary-dark text-white rounded-xl text-xs font-bold transition shadow-sm flex items-center justify-center gap-1">
                                                        <?= npglow_icon('truck', 'w-3.5 h-3.5') ?>
                                                        Kirim (Input Resi)
                                                    </button>
                                                <?php endif; ?>

                                                <!-- Action 3: Add Tracking Transit / Update Hub -->
                                                <?php if ($o['order_status'] === 'shipped'): ?>
                                                    <button onclick="openAddLogModal(<?= $o['id'] ?>, '<?= htmlspecialchars($orderNum) ?>')" class="w-full px-2.5 py-1 bg-sky-50 hover:bg-sky-100 text-primary border border-sky-200 rounded-xl text-[11px] font-bold transition">
                                                        + Update Hub/Transit
                                                    </button>
                                                    <form method="POST" onsubmit="return confirm('Apakah pesanan ini sudah sampai dan diterima pembeli?');" class="w-full">
                                                        <input type="hidden" name="action" value="deliver_order">
                                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                        <button type="submit" class="w-full px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-xl text-[11px] font-bold transition flex items-center justify-center gap-1">
                                                            <?= npglow_icon('check', 'w-3.5 h-3.5') ?> Tandai Selesai
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ($o['order_status'] === 'delivered'): ?>
                                                    <span class="inline-flex items-center gap-1 text-[11px] text-emerald-600 font-bold">
                                                        <?= npglow_icon('check-circle', 'w-3.5 h-3.5 text-emerald-600') ?> Pesanan Selesai
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="p-12 text-center text-gray-400">
                                        <div class="max-w-xs mx-auto space-y-2">
                                            <div class="w-12 h-12 rounded-2xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                            </div>
                                            <p class="font-bold text-gray-700 text-sm">Tidak ada pesanan ditemukan</p>
                                            <p class="text-xs text-gray-400"><?= !empty($search) ? 'Coba ubah kata kunci pencarian atau ganti filter status.' : 'Belum ada pesanan pada status filter ini.' ?></p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Modern Bottom Pagination & Info Bar -->
                <?php if ($totalFiltered > 0): ?>
                    <div class="px-6 py-4 bg-gray-50/70 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <!-- Summary info -->
                        <p class="text-xs text-gray-500 font-medium text-center sm:text-left">
                            Menampilkan <span class="font-bold text-gray-800"><?= $startItem ?> - <?= $endItem ?></span> dari <span class="font-bold text-gray-800"><?= $totalFiltered ?></span> pesanan
                            <?php if (!empty($search)): ?>
                                (pencarian: "<span class="font-semibold text-primary"><?= htmlspecialchars($search) ?></span>")
                            <?php endif; ?>
                        </p>

                        <!-- Pagination Navigation Pills -->
                        <?php if ($totalPages > 1): ?>
                            <div class="flex items-center gap-1.5">
                                <!-- Previous Button -->
                                <?php if ($page > 1): ?>
                                    <a href="<?= buildPaginationUrl($page - 1, $filterStatus, $search, $limit) ?>" class="px-3 py-1.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-100 text-gray-700 text-xs font-bold transition flex items-center gap-1 shadow-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                                        Sebelumnya
                                    </a>
                                <?php else: ?>
                                    <span class="px-3 py-1.5 rounded-xl border border-gray-100 bg-gray-50 text-gray-300 text-xs font-bold cursor-not-allowed flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                                        Sebelumnya
                                    </span>
                                <?php endif; ?>

                                <!-- Page Number Buttons with Smart Windowing -->
                                <?php
                                    $range = 2; // Number of pages before and after current
                                    $showEllipsisStart = false;
                                    $showEllipsisEnd = false;

                                    for ($p = 1; $p <= $totalPages; $p++):
                                        if ($p == 1 || $p == $totalPages || ($p >= $page - $range && $p <= $page + $range)):
                                            $isActive = ($p == $page);
                                ?>
                                            <a href="<?= buildPaginationUrl($p, $filterStatus, $search, $limit) ?>" class="min-w-[32px] h-8 flex items-center justify-center px-2.5 rounded-xl text-xs font-bold transition <?= $isActive ? 'bg-primary text-white shadow-md shadow-blue-500/20' : 'bg-white hover:bg-gray-100 text-gray-700 border border-gray-200 shadow-sm' ?>">
                                                <?= $p ?>
                                            </a>
                                <?php
                                        elseif ($p < $page - $range && !$showEllipsisStart):
                                            $showEllipsisStart = true;
                                            echo '<span class="px-1 text-xs text-gray-400 font-bold">...</span>';
                                        elseif ($p > $page + $range && !$showEllipsisEnd):
                                            $showEllipsisEnd = true;
                                            echo '<span class="px-1 text-xs text-gray-400 font-bold">...</span>';
                                        endif;
                                    endfor;
                                ?>

                                <!-- Next Button -->
                                <?php if ($page < $totalPages): ?>
                                    <a href="<?= buildPaginationUrl($page + 1, $filterStatus, $search, $limit) ?>" class="px-3 py-1.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-100 text-gray-700 text-xs font-bold transition flex items-center gap-1 shadow-sm">
                                        Berikutnya
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    </a>
                                <?php else: ?>
                                    <span class="px-3 py-1.5 rounded-xl border border-gray-100 bg-gray-50 text-gray-300 text-xs font-bold cursor-not-allowed flex items-center gap-1">
                                        Berikutnya
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            </div>
        </main>
    </div>

    <!-- Modal Preview Proof -->
    <div id="proofModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl p-6 max-w-lg w-full shadow-2xl border border-gray-100">
            <div class="flex justify-between items-center pb-3 border-b border-gray-100 mb-4">
                <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Bukti Pembayaran: <span id="proofOrderNum" class="font-mono text-primary"></span>
                </h3>
                <button onclick="closeProofModal()" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
            </div>
            <div class="rounded-2xl overflow-hidden bg-slate-100 border border-gray-200 max-h-[70vh] flex items-center justify-center p-2">
                <img id="proofImg" src="" alt="Bukti Transfer" class="max-w-full max-h-[65vh] object-contain rounded-xl">
            </div>
            <div class="mt-4 flex justify-end">
                <button onclick="closeProofModal()" class="px-5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl text-sm transition">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Input Resi / Ship Order -->
    <div id="shipModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl p-6 max-w-md w-full shadow-2xl border border-gray-100">
            <h3 class="text-base font-bold text-gray-800 mb-1 flex items-center gap-2">
                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path></svg>
                Kirim Paket & Input Nomor Resi
            </h3>
            <p class="text-xs text-gray-500 mb-4 font-mono">Pesanan: <span id="shipOrderNum" class="font-bold text-primary"></span></p>
            
            <form method="POST" class="space-y-3.5">
                <input type="hidden" name="action" value="ship_order">
                <input type="hidden" name="order_id" id="ship_order_id">
                
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Kurir Ekspedisi</label>
                    <select name="shipping_courier" id="ship_courier" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 font-semibold">
                        <option value="J&T Express">J&T Express</option>
                        <option value="JNE Express">JNE Express</option>
                        <option value="SiCepat Express">SiCepat Express</option>
                        <option value="ID Express">ID Express</option>
                        <option value="Anteraja">Anteraja</option>
                        <option value="Shopee Express">Shopee Express (SPX)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Nomor Resi / AWB <span class="text-red-500">*</span></label>
                    <input type="text" name="tracking_number" required placeholder="Cth: JP9283741829 / JT89201823" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 font-mono font-bold">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Lokasi Drop Point Awal</label>
                    <input type="text" name="location" value="Drop Point Pusat Jakarta" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Catatan Tambahan (Opsional)</label>
                    <input type="text" name="tracking_note" placeholder="Cth: Paket telah di-pickup oleh kurir" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeShipModal()" class="px-4 py-2 text-sm text-gray-500 hover:bg-gray-100 rounded-xl font-medium">Batal</button>
                    <button type="submit" class="px-5 py-2 text-sm bg-primary hover:bg-primary-dark text-white rounded-xl font-bold shadow-md">Simpan & Kirim Paket</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Add Transit Checkpoint Log -->
    <div id="addLogModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl p-6 max-w-md w-full shadow-2xl border border-gray-100">
            <h3 class="text-base font-bold text-gray-800 mb-1 flex items-center gap-2">
                <svg class="w-5 h-5 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                Tambah Update Hub / Perjalanan Transit
            </h3>
            <p class="text-xs text-gray-500 mb-4 font-mono">Pesanan: <span id="addLogOrderNum" class="font-bold text-primary"></span></p>
            
            <form method="POST" class="space-y-3.5">
                <input type="hidden" name="action" value="add_tracking_log">
                <input type="hidden" name="order_id" id="add_log_order_id">
                
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Judul Status</label>
                    <input type="text" name="log_title" required value="Paket Telah Tiba di Hub Transit" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 font-semibold">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Keterangan Detail <span class="text-red-500">*</span></label>
                    <textarea name="log_desc" required rows="2" placeholder="Cth: Paket telah tiba di DC Hub Kota Tujuan dan sedang disortir untuk pengantaran kurir." class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Lokasi Hub / Titik Transit</label>
                    <input type="text" name="log_location" placeholder="Cth: Sorting Hub Surabaya / Hub DC Medan" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeAddLogModal()" class="px-4 py-2 text-sm text-gray-500 hover:bg-gray-100 rounded-xl font-medium">Batal</button>
                    <button type="submit" class="px-5 py-2 text-sm bg-sky-600 hover:bg-sky-700 text-white rounded-xl font-bold">Simpan Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Reject Order -->
    <div id="rejectModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl p-6 max-w-md w-full shadow-2xl border border-gray-100">
            <h3 class="text-base font-bold text-gray-800 mb-2 flex items-center gap-2 text-red-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                Tolak Pembayaran Pesanan
            </h3>
            <p class="text-xs text-gray-500 mb-4">Berikan catatan alasan penolakan agar pembeli dapat mengupload ulang bukti yang sesuai.</p>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="reject_order">
                <input type="hidden" name="order_id" id="reject_order_id">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Alasan Penolakan</label>
                    <textarea name="admin_note" rows="3" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-red-200 focus:border-red-400 resize-none" placeholder="Cth: Foto bukti transfer buram / nominal tidak sesuai / dana belum masuk di rekening."></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeRejectModal()" class="px-4 py-2 text-sm text-gray-500 hover:bg-gray-100 rounded-xl font-medium">Batal</button>
                    <button type="submit" class="px-5 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold">Tolak Pesanan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function previewProof(url, orderNum) {
            document.getElementById('proofImg').src = url;
            document.getElementById('proofOrderNum').textContent = orderNum;
            document.getElementById('proofModal').classList.remove('hidden');
        }
        function closeProofModal() {
            document.getElementById('proofModal').classList.add('hidden');
        }

        function openShipModal(id, orderNum, courier) {
            document.getElementById('ship_order_id').value = id;
            document.getElementById('shipOrderNum').textContent = orderNum;
            if (courier) {
                document.getElementById('ship_courier').value = courier;
            }
            document.getElementById('shipModal').classList.remove('hidden');
        }
        function closeShipModal() {
            document.getElementById('shipModal').classList.add('hidden');
        }

        function openAddLogModal(id, orderNum) {
            document.getElementById('add_log_order_id').value = id;
            document.getElementById('addLogOrderNum').textContent = orderNum;
            document.getElementById('addLogModal').classList.remove('hidden');
        }
        function closeAddLogModal() {
            document.getElementById('addLogModal').classList.add('hidden');
        }

        function openRejectModal(id) {
            document.getElementById('reject_order_id').value = id;
            document.getElementById('rejectModal').classList.remove('hidden');
        }
        function closeRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
        }
    </script>
</body>
</html>
