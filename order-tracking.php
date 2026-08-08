<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth-helper.php';
require_once 'includes/order-tracking-helper.php';
require_once 'includes/icon-helper.php';

// Auth Check - Buyer only
guard_buyer_only();

$userId = (int)$_SESSION['user_id'];
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($orderId <= 0) {
    header("Location: my-orders.php");
    exit();
}

// Fetch Order Details
$stmt = $conn->prepare("
    SELECT o.*, 
           p.name as product_name, 
           p.price as original_product_price, 
           p.image_url as product_image,
           p.description as product_desc,
           b.bank_name, 
           b.account_number, 
           b.account_holder
    FROM orders o
    JOIN products p ON o.product_id = p.id
    LEFT JOIN payment_bank_accounts b ON o.payment_bank_id = b.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: my-orders.php");
    exit();
}

// Fetch tracking logs
$trackingLogs = get_order_tracking_logs($conn, $orderId, 'DESC');

// Status Info
$statusMeta = get_order_status_info($order['order_status'], $order['payment_status']);
$orderNum = $order['order_number'] ?: ('NP-#' . $order['id']);
$courierName = $order['shipping_courier'] ?: 'J&T';
$trackingNum = $order['tracking_number'] ?: '';
$finalTotal = (float)($order['total_amount'] ?: $order['price']);
$productPrice = (float)($order['product_price'] ?: $order['original_product_price']);
$shippingCost = (float)($order['shipping_cost'] ?? 0);
$discountAmount = (float)($order['discount_amount'] ?? 0);

// Determine Stepper Active Step (1 to 5)
$currentStep = 1;
if ($order['order_status'] === 'delivered') {
    $currentStep = 5;
} elseif ($order['order_status'] === 'shipped') {
    $currentStep = 4;
} elseif ($order['order_status'] === 'processing' || $order['payment_status'] === 'paid') {
    $currentStep = 3;
} elseif ($order['payment_status'] === 'waiting_verification') {
    $currentStep = 2;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Lacak Pesanan <?= htmlspecialchars($orderNum) ?> - NPGLOW</title>
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
        .timeline-line::before {
            content: '';
            position: absolute;
            top: 14px;
            bottom: 0;
            left: 11px;
            width: 2px;
            background: #e2e8f0;
        }
        @keyframes pulse-ring {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(60, 166, 242, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(60, 166, 242, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(60, 166, 242, 0); }
        }
        .pulse-active { animation: pulse-ring 2s infinite; }
    </style>
</head>
<body class="bg-gray-100 text-slate-800 antialiased min-h-screen pb-24 sm:pb-12">

    <!-- Top Sticky Header -->
    <header class="sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-2xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="my-orders.php" class="p-1.5 -ml-1.5 rounded-full hover:bg-gray-100 transition text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <div>
                    <h1 class="text-sm sm:text-base font-extrabold text-gray-900 tracking-tight">Rincian & Status Pesanan</h1>
                    <span class="text-[11px] font-mono text-gray-500 font-semibold"><?= htmlspecialchars($orderNum) ?></span>
                </div>
            </div>
            <a href="https://wa.me/6281234567890?text=Halo%20Admin%20NPGLOW,%20mohon%20bantuan%20untuk%20pesanan%20<?= urlencode($orderNum) ?>" target="_blank" class="p-2 text-gray-500 hover:text-primary transition" title="Bantuan CS">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </a>
        </div>
    </header>

    <main class="max-w-2xl mx-auto p-3 sm:p-4 space-y-3.5">

        <!-- Status Summary Header Banner -->
        <div class="bg-gradient-to-r from-blue-600 via-primary to-sky-400 text-white rounded-3xl p-5 shadow-lg shadow-primary/20 relative overflow-hidden">
            <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
            
            <div class="flex items-center justify-between gap-3 relative z-10">
                <div>
                    <span class="inline-flex items-center gap-1 text-[11px] font-bold bg-white/20 backdrop-blur-md text-white px-3 py-1 rounded-full mb-2">
                        <?= $statusMeta['icon'] ?>
                        <span><?= $statusMeta['label'] ?></span>
                    </span>
                    <h2 class="text-lg sm:text-xl font-extrabold leading-snug"><?= $statusMeta['summary'] ?></h2>
                    <p class="text-xs text-blue-50/90 mt-1">Dipesan pada <?= date('d F Y, H:i', strtotime($order['order_date'])) ?> WIB</p>
                </div>
            </div>
        </div>

        <!-- Visual Stepper Progress Bar (Shopee Style) -->
        <?php if ($order['order_status'] !== 'cancelled' && $order['payment_status'] !== 'rejected'): ?>
        <div class="bg-white rounded-3xl p-4 sm:p-5 border border-gray-200 shadow-sm">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                <span>Status Perkembangan Pesanan</span>
            </h3>

            <div class="relative flex items-center justify-between">
                <!-- Connecting Line Background -->
                <div class="absolute left-4 right-4 top-4 h-1 bg-gray-200 -z-0"></div>
                <!-- Connecting Line Active -->
                <div class="absolute left-4 top-4 h-1 bg-primary -z-0 transition-all duration-500" style="width: <?= max(0, min(100, ($currentStep - 1) * 25)) ?>%;"></div>

                <!-- Step 1: Pesanan Dibuat -->
                <div class="flex flex-col items-center text-center z-10">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition shadow-sm <?= $currentStep >= 1 ? 'bg-primary text-white shadow-primary/30' : 'bg-gray-200 text-gray-500' ?>">
                        <?php if ($currentStep > 1): ?><?= npglow_icon('check', 'w-3.5 h-3.5') ?><?php else: ?>1<?php endif; ?>
                    </div>
                    <span class="text-[10px] sm:text-[11px] font-semibold mt-1.5 <?= $currentStep >= 1 ? 'text-gray-900 font-bold' : 'text-gray-400' ?>">Dibuat</span>
                </div>

                <!-- Step 2: Bayar/Verif -->
                <div class="flex flex-col items-center text-center z-10">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition shadow-sm <?= $currentStep >= 2 ? 'bg-primary text-white shadow-primary/30' : 'bg-gray-200 text-gray-500' ?>">
                        <?php if ($currentStep > 2): ?><?= npglow_icon('check', 'w-3.5 h-3.5') ?><?php else: ?>2<?php endif; ?>
                    </div>
                    <span class="text-[10px] sm:text-[11px] font-semibold mt-1.5 <?= $currentStep >= 2 ? 'text-gray-900 font-bold' : 'text-gray-400' ?>">Bayar</span>
                </div>

                <!-- Step 3: Dikemas -->
                <div class="flex flex-col items-center text-center z-10">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition shadow-sm <?= $currentStep >= 3 ? 'bg-primary text-white shadow-primary/30' : 'bg-gray-200 text-gray-500' ?>">
                        <?php if ($currentStep > 3): ?><?= npglow_icon('check', 'w-3.5 h-3.5') ?><?php else: ?>3<?php endif; ?>
                    </div>
                    <span class="text-[10px] sm:text-[11px] font-semibold mt-1.5 <?= $currentStep >= 3 ? 'text-gray-900 font-bold' : 'text-gray-400' ?>">Dikemas</span>
                </div>

                <!-- Step 4: Dikirim -->
                <div class="flex flex-col items-center text-center z-10">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition shadow-sm <?= $currentStep >= 4 ? 'bg-primary text-white shadow-primary/30' : 'bg-gray-200 text-gray-500' ?>">
                        <?php if ($currentStep > 4): ?><?= npglow_icon('check', 'w-3.5 h-3.5') ?><?php else: ?>4<?php endif; ?>
                    </div>
                    <span class="text-[10px] sm:text-[11px] font-semibold mt-1.5 <?= $currentStep >= 4 ? 'text-gray-900 font-bold' : 'text-gray-400' ?>">Dikirim</span>
                </div>

                <!-- Step 5: Selesai -->
                <div class="flex flex-col items-center text-center z-10">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition shadow-sm <?= $currentStep >= 5 ? 'bg-emerald-500 text-white shadow-emerald-500/30' : 'bg-gray-200 text-gray-500' ?>">
                        <?php if ($currentStep >= 5): ?><?= npglow_icon('check', 'w-3.5 h-3.5') ?><?php else: ?>5<?php endif; ?>
                    </div>
                    <span class="text-[10px] sm:text-[11px] font-semibold mt-1.5 <?= $currentStep >= 5 ? 'text-emerald-700 font-bold' : 'text-gray-400' ?>">Selesai</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Shipping Courier & Tracking Number Card -->
        <div class="bg-white rounded-3xl p-4 sm:p-5 border border-gray-200 shadow-sm space-y-3">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path></svg>
                    </div>
                    <div>
                        <h4 class="text-xs sm:text-sm font-extrabold text-gray-900"><?= htmlspecialchars($courierName) ?> (<?= htmlspecialchars($order['shipping_service'] ?: 'Reguler') ?>)</h4>
                        <p class="text-[11px] text-gray-500">Estimasi tiba: 1-3 hari kerja</p>
                    </div>
                </div>
                <?php if (!empty($trackingNum)): ?>
                    <div class="flex items-center gap-1.5 bg-gray-50 border border-gray-200 px-3 py-1.5 rounded-xl">
                        <span class="font-mono text-xs font-bold text-gray-800" id="resiText"><?= htmlspecialchars($trackingNum) ?></span>
                        <button onclick="copyResi()" class="text-primary hover:text-primary-dark font-bold text-xs ml-1" title="Salin No Resi">
                            Salin
                        </button>
                    </div>
                <?php else: ?>
                    <span class="text-[11px] text-gray-400 font-medium italic">Resi Belum Terbit</span>
                <?php endif; ?>
            </div>

            <!-- Detailed Vertical Timeline Log -->
            <div class="pt-2">
                <h4 class="text-xs font-bold text-gray-700 mb-3 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Histori Perjalanan Paket</span>
                </h4>

                <?php if (empty($trackingLogs)): ?>
                    <div class="text-center py-6 text-gray-400 text-xs">
                        Belum ada aktivitas pelacakan untuk pesanan ini.
                    </div>
                <?php else: ?>
                    <div class="space-y-4 relative pl-7 timeline-line">
                        <?php foreach ($trackingLogs as $idx => $log): ?>
                            <?php $isLatest = ($idx === 0); ?>
                            <div class="relative">
                                <!-- Marker Dot -->
                                <div class="absolute -left-7 top-1 w-5 h-5 rounded-full flex items-center justify-center <?= $isLatest ? 'bg-primary text-white pulse-active' : 'bg-gray-300 text-white' ?>">
                                    <div class="w-2 h-2 bg-white rounded-full"></div>
                                </div>

                                <div>
                                    <div class="flex items-baseline justify-between gap-2">
                                        <h5 class="text-xs font-bold <?= $isLatest ? 'text-primary' : 'text-gray-800' ?>">
                                            <?= htmlspecialchars($log['title']) ?>
                                        </h5>
                                        <span class="text-[10px] text-gray-400 font-medium whitespace-nowrap">
                                            <?= date('d M, H:i', strtotime($log['created_at'])) ?> WIB
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-600 mt-0.5 leading-relaxed">
                                        <?= htmlspecialchars($log['description']) ?>
                                    </p>
                                    <?php if (!empty($log['location'])): ?>
                                        <span class="inline-flex items-center gap-1 mt-1 text-[10px] font-semibold text-gray-500 bg-gray-50 border border-gray-200/60 px-2 py-0.5 rounded-md">
                                            <?= npglow_icon('pin', 'w-3 h-3 text-slate-400') ?> <?= htmlspecialchars($log['location']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recipient & Shipping Address -->
        <div class="bg-white rounded-3xl p-4 sm:p-5 border border-gray-200 shadow-sm space-y-2">
            <h4 class="text-xs font-bold text-gray-700 flex items-center gap-1.5 mb-2">
                <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span>Alamat Pengiriman</span>
            </h4>
            <div class="text-xs space-y-1">
                <p class="font-bold text-gray-900"><?= htmlspecialchars($order['recipient_name'] ?: 'Penerima') ?> <span class="text-gray-500 font-normal">(<?= htmlspecialchars($order['recipient_phone'] ?: '-') ?>)</span></p>
                <p class="text-gray-600 leading-relaxed"><?= htmlspecialchars($order['shipping_address'] ?: 'Alamat belum diinput') ?></p>
                <p class="text-gray-500"><?= htmlspecialchars($order['shipping_district'] ?: '') ?><?= $order['shipping_city'] ? ', ' . htmlspecialchars($order['shipping_city']) : '' ?><?= $order['shipping_province'] ? ', ' . htmlspecialchars($order['shipping_province']) : '' ?> <?= htmlspecialchars($order['shipping_postal_code'] ?: '') ?></p>
            </div>
        </div>

        <!-- Product Information Card -->
        <div class="bg-white rounded-3xl p-4 sm:p-5 border border-gray-200 shadow-sm space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                <div class="flex items-center gap-1.5">
                    <span class="bg-gradient-to-r from-red-500 to-orange-500 text-white text-[9px] font-extrabold px-1.5 py-0.5 rounded shadow-xs">Star+</span>
                    <span class="font-bold text-xs text-gray-900">NPGLOW Official Store</span>
                </div>
                <a href="index.php#marketplace" class="text-xs font-semibold text-primary hover:underline">Kunjungi Toko →</a>
            </div>

            <div class="flex gap-3.5 py-1">
                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl overflow-hidden bg-slate-100 border border-slate-200/80 flex-shrink-0 shadow-xs">
                    <?php if (!empty($order['product_image'])): ?>
                        <img src="<?= htmlspecialchars($order['product_image']) ?>" alt="<?= htmlspecialchars($order['product_name']) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-blue-50 text-primary">
                            <?= npglow_icon('cart', 'w-7 h-7 text-primary') ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0 flex flex-col justify-between py-0.5">
                    <div>
                        <h4 class="text-xs sm:text-sm font-bold text-gray-900 line-clamp-2 leading-snug"><?= htmlspecialchars($order['product_name']) ?></h4>
                        <p class="text-[11px] text-gray-400 mt-1">Varian: Original Skin Glow • Qty: 1</p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs sm:text-sm font-extrabold text-gray-900">Rp <?= number_format($productPrice, 0, ',', '.') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Breakdown Summary -->
        <div class="bg-white rounded-3xl p-4 sm:p-5 border border-gray-200 shadow-sm space-y-2.5">
            <h4 class="text-xs font-bold text-gray-700 flex items-center gap-1.5 pb-2 border-b border-gray-100">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path></svg>
                <span>Rincian Pembayaran</span>
            </h4>

            <div class="space-y-1.5 text-xs">
                <div class="flex justify-between text-gray-600">
                    <span>Metode Pembayaran</span>
                    <span class="font-semibold text-gray-800">
                        <?= $order['payment_method'] === 'qris' ? 'QRIS Instant' : ('Transfer Bank ' . htmlspecialchars($order['bank_name'] ?: 'BCA')) ?>
                    </span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal Produk</span>
                    <span>Rp <?= number_format($productPrice, 0, ',', '.') ?></span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>Ongkos Kirim (<?= htmlspecialchars($courierName) ?>)</span>
                    <span>Rp <?= number_format($shippingCost, 0, ',', '.') ?></span>
                </div>
                <?php if ($discountAmount > 0): ?>
                <div class="flex justify-between text-emerald-600">
                    <span>Diskon / Subsidi Ongkir</span>
                    <span>-Rp <?= number_format($discountAmount, 0, ',', '.') ?></span>
                </div>
                <?php endif; ?>
                <div class="flex justify-between items-center text-sm font-extrabold text-gray-900 pt-2 border-t border-dashed border-gray-200">
                    <span>Total Pembayaran</span>
                    <span class="text-primary text-base">Rp <?= number_format($finalTotal, 0, ',', '.') ?></span>
                </div>
            </div>
        </div>

    </main>

    <!-- Bottom Sticky Action Bar -->
    <div class="fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 shadow-[0_-4px_12px_rgba(0,0,0,0.06)] p-3 sm:p-4">
        <div class="max-w-2xl mx-auto flex items-center justify-between gap-3">
            <a href="my-orders.php" class="px-4 py-2.5 rounded-2xl border border-gray-300 text-gray-700 hover:bg-gray-100 text-xs font-bold transition">
                ← Kembali
            </a>

            <div class="flex items-center gap-2">
                <?php if ($order['order_status'] === 'unpaid' && $order['payment_status'] !== 'rejected'): ?>
                    <a href="payment.php?order_id=<?= $order['id'] ?>" class="px-5 py-2.5 rounded-2xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-md transition">
                        Bayar Sekarang
                    </a>
                <?php elseif ($order['order_status'] === 'shipped'): ?>
                    <button onclick="confirmReceived(<?= $order['id'] ?>)" class="px-5 py-2.5 rounded-2xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-md transition">
                        Pesanan Diterima
                    </button>
                <?php elseif ($order['order_status'] === 'delivered'): ?>
                    <a href="index.php#marketplace" class="px-5 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-md transition">
                        Beli Lagi
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Hidden Form for Actions -->
    <form id="actionForm" method="POST" action="my-orders.php" class="hidden">
        <input type="hidden" name="action" id="formAction">
        <input type="hidden" name="order_id" id="formOrderId">
    </form>

    <script>
        function copyResi() {
            const resi = document.getElementById('resiText').innerText;
            navigator.clipboard.writeText(resi).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Nomor Resi Disalin!',
                    text: resi,
                    timer: 1500,
                    showConfirmButton: false
                });
            });
        }

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
    </script>

    <?php 
    $bottomNavActive = 'pesanan';
    include 'includes/bottom-nav.php'; 
    ?>

</body>
<?php include 'includes/pwa-sw.php'; ?>
</html>
