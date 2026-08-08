<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth-helper.php';
require_once 'includes/order-tracking-helper.php';
require_once 'includes/icon-helper.php';

// Auth Check - Buyer only
guard_buyer_only();

$userId = (int)$_SESSION['user_id'];

// Handle Action: Mark Delivered from Customer Side
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_received') {
    $orderId = (int)$_POST['order_id'];
    
    // Validate order ownership
    $checkStmt = $conn->prepare("SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ? AND o.user_id = ?");
    $checkStmt->bind_param("ii", $orderId, $userId);
    $checkStmt->execute();
    $orderToConfirm = $checkStmt->get_result()->fetch_assoc();

    if ($orderToConfirm && $orderToConfirm['order_status'] === 'shipped') {
        mark_order_delivered($conn, $orderId, $orderToConfirm['recipient_name'] ?: $orderToConfirm['customer_name']);
        header("Location: my-orders.php?status=delivered&msg=received_success");
        exit();
    }
}

// Handle Action: Cancel Unpaid Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $orderId = (int)$_POST['order_id'];
    
    $checkStmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $checkStmt->bind_param("ii", $orderId, $userId);
    $checkStmt->execute();
    $orderToCancel = $checkStmt->get_result()->fetch_assoc();

    if ($orderToCancel && $orderToCancel['order_status'] === 'unpaid' && $orderToCancel['payment_status'] === 'pending') {
        $stmt = $conn->prepare("UPDATE orders SET order_status = 'cancelled' WHERE id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();

        add_order_tracking_log($conn, $orderId, 'cancelled', 'Pesanan Dibatalkan', 'Pesanan telah dibatalkan oleh pembeli.', 'Customer App');
        header("Location: my-orders.php?status=cancelled&msg=cancel_success");
        exit();
    }
}

// Filter Status
$activeTab = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';

// Base Query
$sql = "
SELECT o.*, 
       p.name as product_name, 
       p.price as original_product_price, 
       p.image_url as product_image,
       b.bank_name
FROM orders o
JOIN products p ON o.product_id = p.id
LEFT JOIN payment_bank_accounts b ON o.payment_bank_id = b.id
WHERE o.user_id = ?
";

$params = [$userId];
$types = "i";

// Filter Tab
if ($activeTab === 'unpaid') {
    $sql .= " AND (o.order_status = 'unpaid' AND o.payment_status != 'rejected')";
} elseif ($activeTab === 'processing') {
    $sql .= " AND o.order_status = 'processing'";
} elseif ($activeTab === 'shipped') {
    $sql .= " AND o.order_status = 'shipped'";
} elseif ($activeTab === 'delivered') {
    $sql .= " AND o.order_status = 'delivered'";
} elseif ($activeTab === 'cancelled') {
    $sql .= " AND (o.order_status = 'cancelled' OR o.payment_status = 'rejected')";
}

// Search
if (!empty($searchQuery)) {
    $sql .= " AND (o.order_number LIKE ? OR p.name LIKE ? OR o.shipping_address LIKE ?)";
    $likeQ = "%" . $searchQuery . "%";
    $params[] = $likeQ;
    $params[] = $likeQ;
    $params[] = $likeQ;
    $types .= "sss";
}

$sql .= " ORDER BY o.order_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$ordersResult = $stmt->get_result();
$orders = $ordersResult->fetch_all(MYSQLI_ASSOC);

// Calculate status counts for user
$countAll = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id = {$userId}")->fetch_assoc()['c'] ?? 0;
$countUnpaid = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id = {$userId} AND (order_status = 'unpaid' AND payment_status != 'rejected')")->fetch_assoc()['c'] ?? 0;
$countProcessing = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id = {$userId} AND order_status = 'processing'")->fetch_assoc()['c'] ?? 0;
$countShipped = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id = {$userId} AND order_status = 'shipped'")->fetch_assoc()['c'] ?? 0;
$countDelivered = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id = {$userId} AND order_status = 'delivered'")->fetch_assoc()['c'] ?? 0;
$countCancelled = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id = {$userId} AND (order_status = 'cancelled' OR payment_status = 'rejected')")->fetch_assoc()['c'] ?? 0;

// Fetch latest tracking log for each order
$orderLatestLogs = [];
if (!empty($orders)) {
    $orderIds = array_column($orders, 'id');
    $inStr = implode(',', $orderIds);
    $logsRes = $conn->query("
        SELECT l.* 
        FROM order_tracking_logs l
        INNER JOIN (
            SELECT order_id, MAX(id) as max_id 
            FROM order_tracking_logs 
            WHERE order_id IN ({$inStr}) 
            GROUP BY order_id
        ) m ON l.id = m.max_id
    ");
    if ($logsRes) {
        while ($lRow = $logsRes->fetch_assoc()) {
            $orderLatestLogs[$lRow['order_id']] = $lRow;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pesanan Saya - NPGLOW Official</title>
    <?php include 'includes/pwa-head.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3ca6f2',
                        'primary-dark': '#2e8ccf',
                        shopee: '#ee4d2d',
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .tab-active {
            color: #3ca6f2;
            border-bottom: 2.5px solid #3ca6f2;
            font-weight: 700;
        }
    </style>
</head>
<body class="bg-gray-100 text-slate-800 antialiased min-h-screen pb-24 sm:pb-12">

    <!-- Top Sticky Header -->
    <header class="sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-2xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="profile.php" class="p-1.5 -ml-1.5 rounded-full hover:bg-gray-100 transition text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <h1 class="text-base sm:text-lg font-extrabold text-gray-900 tracking-tight">Pesanan Saya</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="https://wa.me/6281234567890?text=Halo%20Admin%20NPGLOW,%20saya%20ingin%20tanya%20mengenai%20pesanan%20saya" target="_blank" class="p-2 text-gray-500 hover:text-primary transition" title="Bantuan CS">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </a>
                <a href="dashboard.php" class="p-2 text-gray-500 hover:text-primary transition" title="Dashboard">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                </a>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="max-w-2xl mx-auto px-4 pb-2.5">
            <form method="GET" class="relative">
                <?php if ($activeTab !== 'all'): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($activeTab) ?>">
                <?php endif; ?>
                <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Cari berdasarkan No. Pesanan atau Nama Produk..." class="w-full pl-9 pr-8 py-2 bg-gray-100 hover:bg-gray-150 focus:bg-white rounded-xl text-xs sm:text-sm text-gray-800 placeholder-gray-400 border border-transparent focus:border-primary focus:outline-none transition">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <?php if (!empty($searchQuery)): ?>
                    <a href="my-orders.php?status=<?= urlencode($activeTab) ?>" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-sm font-bold">&times;</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Horizontal Scrollable Tabs (Shopee Style) -->
        <div class="max-w-2xl mx-auto flex items-center overflow-x-auto no-scrollbar border-t border-gray-100 text-xs sm:text-sm font-medium text-gray-500 whitespace-nowrap px-2">
            <a href="my-orders.php?status=all<?= $searchQuery ? '&q='.urlencode($searchQuery) : '' ?>" class="px-3.5 py-3 flex items-center gap-1.5 transition flex-shrink-0 <?= $activeTab === 'all' ? 'tab-active' : 'hover:text-gray-800' ?>">
                <span>Semua</span>
                <?php if ($countAll > 0): ?>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full <?= $activeTab === 'all' ? 'bg-blue-100 text-primary font-bold' : 'bg-gray-100 text-gray-500' ?>"><?= $countAll ?></span>
                <?php endif; ?>
            </a>

            <a href="my-orders.php?status=unpaid<?= $searchQuery ? '&q='.urlencode($searchQuery) : '' ?>" class="px-3.5 py-3 flex items-center gap-1.5 transition flex-shrink-0 <?= $activeTab === 'unpaid' ? 'tab-active' : 'hover:text-gray-800' ?>">
                <span>Belum Bayar</span>
                <?php if ($countUnpaid > 0): ?>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-amber-100 text-amber-700 font-bold"><?= $countUnpaid ?></span>
                <?php endif; ?>
            </a>

            <a href="my-orders.php?status=processing<?= $searchQuery ? '&q='.urlencode($searchQuery) : '' ?>" class="px-3.5 py-3 flex items-center gap-1.5 transition flex-shrink-0 <?= $activeTab === 'processing' ? 'tab-active' : 'hover:text-gray-800' ?>">
                <span>Sedang Dikemas</span>
                <?php if ($countProcessing > 0): ?>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-blue-100 text-primary font-bold"><?= $countProcessing ?></span>
                <?php endif; ?>
            </a>

            <a href="my-orders.php?status=shipped<?= $searchQuery ? '&q='.urlencode($searchQuery) : '' ?>" class="px-3.5 py-3 flex items-center gap-1.5 transition flex-shrink-0 <?= $activeTab === 'shipped' ? 'tab-active' : 'hover:text-gray-800' ?>">
                <span>Dikirim</span>
                <?php if ($countShipped > 0): ?>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-indigo-100 text-indigo-700 font-bold"><?= $countShipped ?></span>
                <?php endif; ?>
            </a>

            <a href="my-orders.php?status=delivered<?= $searchQuery ? '&q='.urlencode($searchQuery) : '' ?>" class="px-3.5 py-3 flex items-center gap-1.5 transition flex-shrink-0 <?= $activeTab === 'delivered' ? 'tab-active' : 'hover:text-gray-800' ?>">
                <span>Selesai</span>
                <?php if ($countDelivered > 0): ?>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-emerald-100 text-emerald-700 font-bold"><?= $countDelivered ?></span>
                <?php endif; ?>
            </a>

            <a href="my-orders.php?status=cancelled<?= $searchQuery ? '&q='.urlencode($searchQuery) : '' ?>" class="px-3.5 py-3 flex items-center gap-1.5 transition flex-shrink-0 <?= $activeTab === 'cancelled' ? 'tab-active' : 'hover:text-gray-800' ?>">
                <span>Dibatalkan</span>
                <?php if ($countCancelled > 0): ?>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-gray-200 text-gray-600 font-bold"><?= $countCancelled ?></span>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-2xl mx-auto p-3 sm:p-4 space-y-3.5">

        <?php if (empty($orders)): ?>
            <!-- Empty State -->
            <div class="bg-white rounded-3xl p-8 sm:p-12 text-center border border-gray-200 shadow-sm mt-4">
                <div class="w-20 h-20 mx-auto rounded-full bg-blue-50 text-primary flex items-center justify-center mb-4">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                </div>
                <h3 class="text-base font-bold text-gray-800 mb-1">Belum Ada Pesanan</h3>
                <p class="text-xs text-gray-500 max-w-sm mx-auto mb-6">
                    <?= !empty($searchQuery) ? "Tidak ditemukan pesanan dengan kata kunci '{$searchQuery}'." : "Kamu belum memiliki riwayat pesanan pada kategori ini. Yuk mulai belanja skincare terbaikmu!" ?>
                </p>
                <a href="index.php#marketplace" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-xl font-bold text-xs shadow-md transition">
                    Belanja Sekarang
                </a>
            </div>
        <?php else: ?>
            <!-- Order Cards List -->
            <?php foreach ($orders as $order): ?>
                <?php
                    $statusMeta = get_order_status_info($order['order_status'], $order['payment_status']);
                    $finalTotal = (float)($order['total_amount'] ?: $order['price']);
                    $origPrice = (float)($order['product_price'] ?: $order['original_product_price']);
                    $courierName = $order['shipping_courier'] ?: 'J&T';
                    $trackingNum = $order['tracking_number'] ?: '';
                    $orderNum = $order['order_number'] ?: ('NP-#' . $order['id']);
                    $latestLog = $orderLatestLogs[$order['id']] ?? null;
                ?>
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden transition hover:shadow-md">
                    
                    <!-- Card Header (Store & Status) -->
                    <div class="px-3.5 sm:px-4 py-2.5 bg-white border-b border-gray-100 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="bg-gradient-to-r from-red-500 to-orange-500 text-white text-[9px] font-extrabold px-1.5 py-0.5 rounded shadow-xs flex-shrink-0">Star+</span>
                            <span class="font-bold text-xs sm:text-sm text-gray-900 truncate">NPGLOW Official</span>
                            <a href="https://wa.me/6281234567890?text=Halo%20Admin%20NPGLOW,%20saya%20ingin%20tanya%20tentang%20pesanan%20<?= urlencode($orderNum) ?>" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-semibold text-primary bg-blue-50 hover:bg-blue-100 px-2 py-0.5 rounded-full transition flex-shrink-0 ml-1">
                                <?= npglow_icon('chat', 'w-3 h-3 text-primary') ?>
                                <span>Chat</span>
                            </a>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full <?= $statusMeta['badge_class'] ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?= $statusMeta['dot_class'] ?>"></span>
                                <?= $statusMeta['label'] ?>
                            </span>
                        </div>
                    </div>

                    <!-- Live Shipping Progress Banner Highlight (Shopee Style) -->
                    <?php if ($order['order_status'] === 'shipped' || $order['order_status'] === 'processing' || $latestLog): ?>
                        <a href="order-tracking.php?order_id=<?= $order['id'] ?>" class="bg-gradient-to-r from-emerald-50/70 via-teal-50/60 to-blue-50/70 px-3.5 py-2 border-b border-gray-100 flex items-center justify-between gap-2.5 text-xs hover:bg-teal-100/40 transition group">
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <span class="p-1 rounded-lg bg-teal-100 text-teal-700 flex-shrink-0">
                                    <?= npglow_icon('truck', 'w-3.5 h-3.5 text-teal-700') ?>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <?php if ($order['order_status'] === 'shipped'): ?>
                                        <p class="text-teal-950 font-medium truncate text-[11px]">
                                            <span class="font-bold text-teal-800">[<?= htmlspecialchars($courierName) ?>]</span> <?= $latestLog ? htmlspecialchars($latestLog['description']) : "Paket sedang dalam perjalanan ekspedisi" ?>
                                        </p>
                                    <?php elseif ($order['order_status'] === 'processing'): ?>
                                        <p class="text-amber-950 font-medium truncate text-[11px]">
                                            <span class="font-bold text-amber-800">[NPGLOW]</span> Pembayaran terverifikasi, pesanan sedang dikemas.
                                        </p>
                                    <?php elseif ($latestLog): ?>
                                        <p class="text-slate-800 font-medium truncate text-[11px]">
                                            <span class="font-bold text-slate-900"><?= htmlspecialchars($latestLog['title']) ?>:</span> <?= htmlspecialchars($latestLog['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-teal-600 group-hover:translate-x-0.5 transition flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </a>
                    <?php endif; ?>

                    <!-- Product Item Detail -->
                    <a href="order-tracking.php?order_id=<?= $order['id'] ?>" class="p-3.5 sm:p-4 flex gap-3 sm:gap-4 hover:bg-slate-50/60 transition block">
                        <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl overflow-hidden bg-slate-100 border border-slate-200/80 flex-shrink-0 shadow-xs relative">
                            <?php if (!empty($order['product_image'])): ?>
                                <img src="<?= htmlspecialchars($order['product_image']) ?>" alt="<?= htmlspecialchars($order['product_name']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-blue-50 text-primary">
                                    <?= npglow_icon('cart', 'w-8 h-8 text-primary') ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex-1 min-w-0 flex flex-col justify-between py-0.5">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <span class="font-mono text-[10px] text-gray-500 font-bold bg-gray-100 px-1.5 py-0.5 rounded"><?= htmlspecialchars($orderNum) ?></span>
                                    <span class="text-[10px] text-gray-400 font-medium whitespace-nowrap"><?= date('d M Y, H:i', strtotime($order['order_date'])) ?></span>
                                </div>
                                <h4 class="text-xs sm:text-sm font-bold text-gray-900 line-clamp-2 leading-snug">
                                    <?= htmlspecialchars($order['product_name']) ?>
                                </h4>
                                <p class="text-[11px] text-gray-500 mt-1 flex items-center gap-1.5 flex-wrap">
                                    <span class="inline-block bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded text-[10px] font-medium">Standar</span>
                                    <span class="text-slate-300">•</span>
                                    <span class="text-slate-500 font-medium">Kurir: <?= htmlspecialchars($courierName) ?></span>
                                </p>
                            </div>

                            <div class="flex items-center justify-between mt-2 pt-1.5 border-t border-dashed border-gray-100">
                                <span class="text-[11px] text-gray-500 font-medium">x1 barang</span>
                                <div class="text-right">
                                    <?php if ($origPrice > $finalTotal): ?>
                                        <span class="text-[10px] text-gray-400 line-through mr-1">Rp <?= number_format($origPrice, 0, ',', '.') ?></span>
                                    <?php endif; ?>
                                    <span class="text-xs sm:text-sm font-bold text-gray-900">Rp <?= number_format($finalTotal, 0, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>
                    </a>

                    <!-- Card Summary & Dynamic Action Buttons -->
                    <div class="px-3.5 sm:px-4 py-2.5 sm:py-3 bg-gray-50/70 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                        <div class="flex items-center justify-between sm:justify-start gap-1">
                            <span class="text-[11px] text-gray-500">Total Pesanan:</span>
                            <span class="text-sm font-extrabold text-primary sm:ml-1">Rp <?= number_format($finalTotal, 0, ',', '.') ?></span>
                        </div>

                        <div class="flex items-center justify-end gap-2 flex-wrap">
                            <?php if ($order['order_status'] === 'unpaid' && $order['payment_status'] !== 'rejected'): ?>
                                <button onclick="cancelOrder(<?= $order['id'] ?>)" class="px-3 py-1.5 rounded-xl border border-gray-300 text-gray-600 hover:bg-gray-100 text-xs font-semibold transition">
                                    Batalkan
                                </button>
                                <a href="payment.php?order_id=<?= $order['id'] ?>" class="px-4 py-1.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-sm transition inline-flex items-center gap-1">
                                    <span><?= $order['payment_status'] === 'waiting_verification' ? 'Cek Status Bayar' : 'Bayar Sekarang' ?></span>
                                </a>
                            <?php elseif ($order['order_status'] === 'processing'): ?>
                                <a href="https://wa.me/6281234567890?text=Halo%20Admin%20NPGLOW,%20mohon%20info%20update%20pesanan%20<?= urlencode($orderNum) ?>" target="_blank" class="px-3 py-1.5 rounded-xl border border-gray-300 text-gray-600 hover:bg-gray-100 text-xs font-semibold transition">
                                    Hubungi Penjual
                                </a>
                                <a href="order-tracking.php?order_id=<?= $order['id'] ?>" class="px-4 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-sm transition">
                                    Lihat Rincian
                                </a>
                            <?php elseif ($order['order_status'] === 'shipped'): ?>
                                <a href="order-tracking.php?order_id=<?= $order['id'] ?>" class="px-3 py-1.5 rounded-xl border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-bold transition">
                                    Lacak Resi
                                </a>
                                <button onclick="confirmReceived(<?= $order['id'] ?>)" class="px-4 py-1.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-sm transition">
                                    Pesanan Diterima
                                </button>
                            <?php elseif ($order['order_status'] === 'delivered'): ?>
                                <a href="konsultasi.php" class="px-3 py-1.5 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-100 text-xs font-semibold transition">
                                    Konsultasi
                                </a>
                                <a href="index.php#marketplace" class="px-4 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition">
                                    Beli Lagi
                                </a>
                            <?php else: ?>
                                <a href="order-tracking.php?order_id=<?= $order['id'] ?>" class="px-3.5 py-1.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-100 text-xs font-semibold transition">
                                    Rincian
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </main>

    <!-- Hidden Form for Actions -->
    <form id="actionForm" method="POST" class="hidden">
        <input type="hidden" name="action" id="formAction">
        <input type="hidden" name="order_id" id="formOrderId">
    </form>

    <!-- Notification Toast Alerts -->
    <?php if (isset($_GET['msg'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                <?php if ($_GET['msg'] === 'received_success'): ?>
                    Swal.fire({
                        icon: 'success',
                        title: 'Pesanan Selesai!',
                        text: 'Terima kasih telah mengonfirmasi penerimaan barang. Jangan lupa rawat kulitmu secara rutin bersama NPGLOW!',
                        confirmButtonColor: '#3ca6f2'
                    });
                <?php elseif ($_GET['msg'] === 'cancel_success'): ?>
                    Swal.fire({
                        icon: 'info',
                        title: 'Pesanan Dibatalkan',
                        text: 'Pesanan Anda telah berhasil dibatalkan.',
                        confirmButtonColor: '#3ca6f2'
                    });
                <?php endif; ?>
            });
        </script>
    <?php endif; ?>

    <script>
        function confirmReceived(orderId) {
            Swal.fire({
                title: 'Konfirmasi Pesanan Diterima?',
                text: 'Pastikan produk telah sampai dengan baik sebelum menyelesaikan pesanan ini.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3ca6f2',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Sudah Diterima',
                cancelButtonText: 'Batal'
            }).then((res) => {
                if (res.isConfirmed) {
                    document.getElementById('formAction').value = 'confirm_received';
                    document.getElementById('formOrderId').value = orderId;
                    document.getElementById('actionForm').submit();
                }
            });
        }

        function cancelOrder(orderId) {
            Swal.fire({
                title: 'Batalkan Pesanan Ini?',
                text: 'Pesanan yang dibatalkan tidak dapat dipulihkan kembali.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Batalkan',
                cancelButtonText: 'Tutup'
            }).then((res) => {
                if (res.isConfirmed) {
                    document.getElementById('formAction').value = 'cancel_order';
                    document.getElementById('formOrderId').value = orderId;
                    document.getElementById('actionForm').submit();
                }
            });
        }
    </script>

    <?php 
    $bottomNavActive = 'pesanan';
    include 'includes/bottom-nav.php'; 
    ?>

</body>
<?php include 'includes/pwa-sw.php'; ?>
</html>
