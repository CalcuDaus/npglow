<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth-helper.php';
require_once __DIR__ . '/../includes/reseller-helper.php';
require_once __DIR__ . '/../includes/icon-helper.php';
require_once __DIR__ . '/../includes/settings-helper.php';

guard_reseller_only();

$userId = (int)$_SESSION['user_id'];
$store = get_reseller_store_by_user($conn, $userId);

if (!$store) {
    echo "<p>Toko belum dikonfigurasi. Hubungi admin.</p>";
    exit();
}

$stats = get_reseller_stats($conn, $store['id']);

// Recent orders
$recentOrders = [];
$stmt = $conn->prepare("
    SELECT o.*, p.name as product_name, p.image_url, u.name as customer_name
    FROM orders o
    JOIN products p ON o.product_id = p.id
    JOIN users u ON o.user_id = u.id
    WHERE o.reseller_store_id = ?
    ORDER BY o.order_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $store['id']);
$stmt->execute();
$recentOrders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Reseller - NPGLOW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: { primary: '#10b981', 'primary-dark': '#059669' },
                fontFamily: { sans: ['Inter', 'sans-serif'] }
            }}
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 flex flex-col min-w-0">
            <?php $pageTitle = 'Dashboard Ringkasan'; include 'topbar.php'; ?>

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <!-- Welcome Banner -->
                <div class="bg-gradient-to-r from-emerald-500 to-teal-500 rounded-2xl p-6 sm:p-8 text-white mb-8 shadow-lg shadow-emerald-500/20">
                    <h2 class="text-xl sm:text-2xl font-extrabold mb-1">Halo, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Reseller') ?>! 👋</h2>
                    <p class="text-emerald-100 text-sm sm:text-base">Selamat datang di dashboard reseller <strong><?= htmlspecialchars($store['store_name']) ?></strong>.</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <div class="bg-white/20 backdrop-blur-sm rounded-xl px-4 py-2 text-sm font-semibold">
                            Kode Referral: <span class="font-mono font-bold"><?= htmlspecialchars($store['referral_code']) ?></span>
                        </div>
                        <?php if ($store['city']): ?>
                        <div class="bg-white/20 backdrop-blur-sm rounded-xl px-4 py-2 text-sm font-semibold">
                            📍 <?= htmlspecialchars($store['city']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <!-- Total Pesanan -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm hover:shadow-md transition group">
                        <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                            <?= npglow_icon('package', 'w-5 h-5') ?>
                        </div>
                        <p class="text-2xl font-extrabold text-gray-800"><?= $stats['total_orders'] ?></p>
                        <p class="text-xs font-semibold text-gray-500 mt-1">Total Pesanan</p>
                    </div>

                    <!-- Pendapatan -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm hover:shadow-md transition group">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                            <?= npglow_icon('wallet', 'w-5 h-5') ?>
                        </div>
                        <p class="text-2xl font-extrabold text-gray-800">Rp <?= number_format($stats['total_revenue'], 0, ',', '.') ?></p>
                        <p class="text-xs font-semibold text-gray-500 mt-1">Pendapatan</p>
                    </div>

                    <!-- Pelanggan -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm hover:shadow-md transition group">
                        <div class="w-10 h-10 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                            <?= npglow_icon('user', 'w-5 h-5') ?>
                        </div>
                        <p class="text-2xl font-extrabold text-gray-800"><?= $stats['customer_count'] ?></p>
                        <p class="text-xs font-semibold text-gray-500 mt-1">Pelanggan Terhubung</p>
                    </div>

                    <!-- Produk Aktif -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm hover:shadow-md transition group">
                        <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                            <?= npglow_icon('cart', 'w-5 h-5') ?>
                        </div>
                        <p class="text-2xl font-extrabold text-gray-800"><?= $stats['product_count'] ?></p>
                        <p class="text-xs font-semibold text-gray-500 mt-1">Produk Aktif</p>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
                    <a href="products.php" class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm hover:shadow-md hover:border-emerald-200 transition group flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-800 group-hover:text-emerald-700 transition">Kelola Produk</p>
                            <p class="text-xs text-gray-500">Tambah, edit harga & stok</p>
                        </div>
                    </a>
                    <a href="orders.php" class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm hover:shadow-md hover:border-amber-200 transition group flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-800 group-hover:text-amber-700 transition">Lihat Pesanan</p>
                            <p class="text-xs text-gray-500">Kelola pesanan masuk</p>
                        </div>
                    </a>
                    <a href="store-settings.php" class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm hover:shadow-md hover:border-indigo-200 transition group flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-100 text-indigo-600 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-800 group-hover:text-indigo-700 transition">Pengaturan Toko</p>
                            <p class="text-xs text-gray-500">Alamat, kontak, profil toko</p>
                        </div>
                    </a>
                </div>

                <!-- Recent Orders -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-base font-bold text-gray-800">Pesanan Terbaru</h3>
                        <a href="orders.php" class="text-xs font-bold text-emerald-600 hover:text-emerald-700 transition">Lihat Semua →</a>
                    </div>
                    <?php if (empty($recentOrders)): ?>
                    <div class="p-8 text-center text-gray-400">
                        <p class="text-sm">Belum ada pesanan masuk.</p>
                        <p class="text-xs mt-1">Bagikan kode referral <strong class="text-emerald-600"><?= htmlspecialchars($store['referral_code']) ?></strong> ke calon pelanggan!</p>
                    </div>
                    <?php else: ?>
                    <div class="divide-y divide-gray-50">
                        <?php foreach ($recentOrders as $order): ?>
                        <?php
                            $orderStatus = $order['order_status'] ?? 'unpaid';
                            $paymentStatus = $order['payment_status'] ?? 'pending';
                            if ($paymentStatus === 'paid') {
                                $badgeClass = 'bg-emerald-100 text-emerald-700';
                                $statusLabel = 'Lunas';
                            } elseif ($paymentStatus === 'waiting_verification') {
                                $badgeClass = 'bg-amber-100 text-amber-700';
                                $statusLabel = 'Menunggu Verifikasi';
                            } else {
                                $badgeClass = 'bg-gray-100 text-gray-600';
                                $statusLabel = 'Belum Bayar';
                            }
                        ?>
                        <div class="flex items-center gap-4 p-4 sm:px-6 hover:bg-gray-50/50 transition">
                            <?php if (!empty($order['image_url'])): ?>
                            <div class="w-11 h-11 rounded-xl overflow-hidden bg-gray-200 flex-shrink-0 border border-gray-100">
                                <img src="../<?= htmlspecialchars($order['image_url']) ?>" alt="" class="w-full h-full object-cover">
                            </div>
                            <?php else: ?>
                            <div class="w-11 h-11 rounded-xl bg-emerald-100 flex items-center justify-center flex-shrink-0">
                                <?= npglow_icon('package', 'w-5 h-5 text-emerald-600') ?>
                            </div>
                            <?php endif; ?>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-800 truncate"><?= htmlspecialchars($order['product_name']) ?></p>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars($order['customer_name']) ?> • <?= date('d M Y', strtotime($order['order_date'])) ?></p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="text-sm font-extrabold text-gray-900">Rp <?= number_format((float)($order['total_amount'] ?? $order['price'] ?? 0), 0, ',', '.') ?></p>
                                <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full mt-1 <?= $badgeClass ?>"><?= $statusLabel ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
