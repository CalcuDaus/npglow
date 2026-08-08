<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth-helper.php';
require_once 'includes/order-tracking-helper.php';
require_once 'includes/icon-helper.php';
require_once 'includes/reseller-helper.php';

// Customer only guard
guard_customer_only();

$userId = $_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch referred store if any
$referredStore = get_user_reseller_store($conn, $userId);

// Order history
$stmt = $conn->prepare("SELECT o.*, p.name as product_name, p.price, p.image_url FROM orders o JOIN products p ON o.product_id = p.id WHERE o.user_id = ? ORDER BY o.order_date DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Face photos count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM user_face_photos WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$photoCount = $stmt->get_result()->fetch_assoc()['total'];

// Initial face photo
$stmt = $conn->prepare("SELECT * FROM user_face_photos WHERE user_id = ? AND photo_type = 'initial' LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$initialPhoto = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - NPGLOW</title>
    <?php include 'includes/pwa-head.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3ca6f2',
                        'primary-dark': '#2e8ccf',
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        .glass-card { background: rgba(255,255,255,0.85); backdrop-filter: blur(16px); }
        .gradient-mesh {
            background: 
                radial-gradient(at 20% 30%, rgba(251,146,60,0.08) 0px, transparent 50%),
                radial-gradient(at 80% 70%, rgba(60,166,242,0.08) 0px, transparent 50%);
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen gradient-mesh pb-20 sm:pb-8">

    <!-- Navbar -->
    <nav class="sticky top-0 z-50 glass-card border-b border-white/30 shadow-sm">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
            <a href="dashboard.php" class="flex items-center gap-2 text-gray-500 hover:text-primary transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                <span class="text-sm font-medium">Dashboard</span>
            </a>
            <h1 class="text-base font-extrabold text-gray-800 flex items-center gap-1.5">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-blue-50 text-primary">
                    <?= npglow_icon('user', 'w-4 h-4') ?>
                </span>
                Profil Saya
            </h1>
            <div class="w-8"></div>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 py-8">

        <!-- Profile Card -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-md mb-8" data-aos="fade-up">
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6">
                <!-- Avatar -->
                <div class="relative">
                    <?php if ($initialPhoto): ?>
                    <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-full overflow-hidden shadow-lg ring-4 ring-primary/20">
                        <img src="<?= htmlspecialchars($initialPhoto['photo_path']) ?>" alt="Foto Profil" class="w-full h-full object-cover">
                    </div>
                    <?php else: ?>
                    <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-gradient-to-br from-primary to-blue-400 flex items-center justify-center shadow-lg ring-4 ring-primary/20">
                        <span class="text-3xl sm:text-4xl font-extrabold text-white"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="absolute -bottom-1 -right-1 w-8 h-8 rounded-full bg-emerald-500 flex items-center justify-center shadow-md border-2 border-white">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                </div>

                <!-- Info -->
                <div class="flex-1 text-center sm:text-left">
                    <div class="flex items-center gap-2 justify-center sm:justify-start mb-1">
                        <h2 class="text-xl sm:text-2xl font-extrabold text-gray-800" id="user-name-display"><?= htmlspecialchars($user['name']) ?></h2>
                        <button onclick="editName()" class="text-gray-400 hover:text-primary transition-colors p-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 mb-3"><?= htmlspecialchars($user['email']) ?></p>
                    <div class="flex flex-wrap gap-2 justify-center sm:justify-start">
                        <span class="inline-flex items-center gap-1.5 text-xs px-3 py-1 rounded-full font-semibold <?= $user['has_purchased'] ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' ?>">
                            <?= $user['has_purchased'] ? npglow_icon('check', 'w-3 h-3 text-emerald-600') . ' Pelanggan Aktif' : 'Belum Beli Produk' ?>
                        </span>
                        <?php if ($user['google_id']): ?>
                        <span class="text-xs px-3 py-1 rounded-full bg-blue-100 text-blue-700 font-semibold flex items-center gap-1">
                            <svg class="w-3 h-3" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                            Google
                        </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-gray-400 mt-3">Bergabung <?= date('d M Y', strtotime($user['created_at'])) ?></p>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4 mb-8" data-aos="fade-up" data-aos-delay="100">
            <!-- Pesanan -->
            <div class="bg-white rounded-2xl p-4 sm:p-5 border border-gray-100/80 shadow-sm hover:shadow-md hover:border-blue-200 transition text-center flex flex-col justify-center items-center group">
                <div class="w-10 h-10 rounded-2xl bg-blue-50 text-primary flex items-center justify-center mb-2.5 shadow-xs group-hover:scale-105 transition-transform">
                    <?= npglow_icon('package', 'w-5 h-5') ?>
                </div>
                <p class="text-xl sm:text-2xl font-extrabold text-gray-800 leading-tight"><?= count($orders) ?></p>
                <p class="text-xs font-semibold text-gray-500 mt-1">Pesanan</p>
            </div>

            <!-- Foto Journal -->
            <div class="bg-white rounded-2xl p-4 sm:p-5 border border-gray-100/80 shadow-sm hover:shadow-md hover:border-purple-200 transition text-center flex flex-col justify-center items-center group">
                <div class="w-10 h-10 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center mb-2.5 shadow-xs group-hover:scale-105 transition-transform">
           <?= npglow_icon('camera', 'w-5 h-5') ?>
    </div>
    <p class="text-xl sm:text-2xl font-extrabold text-gray-800 leading-tight"><?= $photoCount ?></p>
    <p class="text-xs font-semibold text-gray-500 mt-1">Foto Journal</p>
</div>

<!-- Total Belanja -->
<div
    class="bg-white rounded-2xl p-4 sm:p-5 border border-gray-100/80 shadow-sm hover:shadow-md hover:border-emerald-200 transition text-center col-span-2 sm:col-span-1 flex flex-col justify-center items-center group">
    <div
        class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2.5 shadow-xs group-hover:scale-105 transition-transform">
        <?= npglow_icon('wallet', 'w-5 h-5') ?>
    </div>
    <p class="text-xl sm:text-2xl font-extrabold text-gray-800 leading-tight">
                    Rp <?= number_format(array_sum(array_column($orders, 'price')), 0, ',', '.') ?>
                </p>
                <p class="text-xs font-semibold text-gray-500 mt-1">Total Belanja</p>
            </div>
        </div>

        <!-- Order History -->
        <div class="bg-white rounded-3xl p-5 sm:p-7 border border-gray-100 shadow-md mb-8" data-aos="fade-up" data-aos-delay="200">
            <div class="flex items-center justify-between gap-3 mb-5">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-blue-50 text-primary flex items-center justify-center flex-shrink-0">
                        <?= npglow_icon('cart', 'w-4 h-4') ?>
                    </div>
                    <h2 class="text-base sm:text-lg font-extrabold text-gray-800 truncate">
                        Riwayat Pesanan
                    </h2>
                </div>
                <a href="my-orders.php" class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:text-primary-dark transition px-2.5 py-1.5 rounded-xl hover:bg-blue-50/80 flex-shrink-0 whitespace-nowrap">
                    <span>Lihat Semua</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                </a>
            </div>

            <?php if (empty($orders)): ?>
            <div class="text-center py-8 text-gray-400">
                <p class="text-sm mb-3">Belum ada pembelian.</p>
                <a href="index.php#marketplace" class="text-primary font-semibold text-sm hover:underline">Belanja Sekarang →</a>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach (array_slice($orders, 0, 4) as $order): ?>
                    <?php
                        $statusMeta = get_order_status_info($order['order_status'] ?? 'unpaid', $order['payment_status'] ?? 'pending');
                        $finalTotal = (float)($order['total_amount'] ?? $order['price']);
                    ?>
                                <a href="order-tracking.php?order_id=<?= $order['id'] ?>"
                            class="flex items-center gap-3 sm:gap-4 p-3 sm:p-3.5 rounded-2xl bg-gray-50/80 hover:bg-blue-50/60 border border-transparent hover:border-blue-200 transition group block">
                    <?php if (!empty($order['image_url'])): ?>
                    <div
                        class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl overflow-hidden bg-gray-200 flex-shrink-0 shadow-sm border border-gray-100">
                        <img src="<?= htmlspecialchars($order['image_url']) ?>" alt="" class="w-full h-full object-cover">
                    </div>
                    <?php else: ?>
                    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                    </div>
                    <?php endif; ?>
                    <div class="flex-1 min-w-0">
                        <span class="text-[10px] sm:text-[11px] font-mono font-bold text-gray-400 block"><?= htmlspecialchars($order['order_number'] ?? ('NP-#' . $order['id'])) ?></span>
                        <p class="text-xs sm:text-sm font-bold text-gray-800 truncate group-hover:text-primary transition"><?= htmlspecialchars($order['product_name']) ?></p>
                        <p class="text-[10px] sm:text-[11px] text-gray-400 mt-0.5"><?= date('d M Y, H:i', strtotime($order['order_date'])) ?> •
                            <?= htmlspecialchars($order['shipping_courier'] ?? 'J&T') ?>
                        </p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-xs sm:text-sm font-extrabold text-gray-900">Rp <?= number_format($finalTotal, 0, ',', '.') ?></p>
                        <div class="mt-1">
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full <?= $statusMeta['badge_class'] ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?= $statusMeta['dot_class'] ?>"></span>
                                <?= $statusMeta['label'] ?>
                            </span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
<?php if (count($orders) > 4): ?>
    <div class="mt-4 pt-3 border-t border-gray-100 text-center">
        <a href="my-orders.php"
            class="inline-flex items-center justify-center gap-1 text-xs font-bold text-primary hover:text-primary-dark transition py-1">
            Lihat <?= count($orders) - 4 ?> Pesanan Lainnya
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        </a>
    </div>
<?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Mitra Toko / Referral Card -->
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-md mb-8" data-aos="fade-up" data-aos-delay="220">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-base font-extrabold text-gray-800">Toko Skincare Anda</h2>
                        <p class="text-xs text-gray-400">Toko yang melayani pesanan skincare Anda</p>
                    </div>
                </div>
                <a href="find-reseller.php" class="px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-xs font-bold rounded-xl transition flex items-center gap-1">
                    <span>Cari Mitra</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </a>
            </div>

            <?php if ($referredStore): ?>
            <div class="p-4 rounded-2xl bg-gradient-to-br from-emerald-50/70 to-teal-50/50 border border-emerald-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500 text-white flex items-center justify-center font-black text-lg flex-shrink-0 shadow-sm">
                        <?php if (!empty($referredStore['store_logo'])): ?>
                            <img src="<?= htmlspecialchars($referredStore['store_logo']) ?>" alt="" class="w-full h-full object-cover rounded-xl">
                        <?php else: ?>
                            <?= strtoupper(substr($referredStore['store_name'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-extrabold text-gray-800"><?= htmlspecialchars($referredStore['store_name']) ?></span>
                            <span class="text-[10px] font-mono font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full"><?= htmlspecialchars($referredStore['referral_code']) ?></span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">📍 <?= htmlspecialchars($referredStore['city'] ?: 'Indonesia') ?> <?= $referredStore['district'] ? ', ' . htmlspecialchars($referredStore['district']) : '' ?></p>
                        <?php if ($referredStore['whatsapp']): ?>
                        <p class="text-xs text-emerald-600 font-semibold mt-0.5">WA: <?= htmlspecialchars($referredStore['whatsapp']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="changeReferralModal()" class="px-3 py-2 bg-white hover:bg-emerald-50 text-emerald-700 text-xs font-bold rounded-xl border border-emerald-200 transition shadow-xs flex-1 sm:flex-none">
                        Ganti Kode
                    </button>
                    <button onclick="clearReferralStore()" class="px-3 py-2 bg-white/80 hover:bg-white text-gray-500 hover:text-gray-700 text-xs font-semibold rounded-xl border border-gray-200 transition flex-1 sm:flex-none">
                        Ke Pusat
                    </button>
                </div>
            </div>
            <?php else: ?>
            <div class="p-4 rounded-2xl bg-blue-50/60 border border-blue-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-primary text-white flex items-center justify-center flex-shrink-0">
                        <?= npglow_icon('shop-bag', 'w-5 h-5') ?>
                    </div>
                    <div>
                        <p class="text-xs font-extrabold text-gray-800">NPGLOW Official Store (Pusat)</p>
                        <p class="text-xs text-gray-500">Anda saat ini berbelanja langsung dari Pusat.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="changeReferralModal()" class="px-3.5 py-2 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-xl transition shadow-xs">
                        + Masukkan Kode Mitra
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Menu & Fitur -->
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-md mb-8" data-aos="fade-up" data-aos-delay="250">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400 mb-4">Fitur & Layanan</h2>
            <div class="space-y-2">
                <a href="journal.php" class="flex items-center justify-between p-3.5 rounded-2xl bg-purple-50/60 hover:bg-purple-100/70 border border-purple-100 transition group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-500 text-white flex items-center justify-center shadow-sm">
                            <?= npglow_icon('camera', 'w-5 h-5') ?>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-800 group-hover:text-purple-700 transition">Journal Progress Kulit</p>
                            <p class="text-xs text-gray-500">Upload dan pantau perubahan wajahmu</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400 group-hover:text-purple-600 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </div>
                </a>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-md mb-8" data-aos="fade-up" data-aos-delay="300">
            <a href="logout.php" class="flex items-center justify-center gap-2 w-full py-3 text-red-500 hover:text-red-600 hover:bg-red-50 rounded-xl transition-colors font-semibold text-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                Keluar dari Akun
            </a>
        </div>
    </main>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>AOS.init({ duration: 600, once: true });</script>
    <script>
        async function changeReferralModal() {
            const { value: code } = await Swal.fire({
                title: 'Kode Referral Mitra',
                input: 'text',
                inputLabel: 'Masukkan kode referral reseller (Contoh: NP-BDG01)',
                inputPlaceholder: 'NP-XXXXX',
                showCancelButton: true,
                cancelButtonText: 'Batal',
                confirmButtonText: 'Terapkan',
                confirmButtonColor: '#10b981',
                showDenyButton: true,
                denyButtonText: 'Cari Toko Terdekat 📍',
                denyButtonColor: '#3ca6f2',
                inputValidator: (v) => { if (!v) return 'Kode referral tidak boleh kosong.'; }
            });

            if (code) {
                const fd = new FormData();
                fd.append('action', 'set_referral');
                fd.append('code', code.trim().toUpperCase());
                try {
                    const res = await fetch('api/referral.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil Terhubung!',
                            text: `Toko berhasil diatur ke ${data.store.store_name} (${data.store.city}).`,
                            confirmButtonColor: '#10b981'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: data.error || 'Kode tidak ditemukan.', confirmButtonColor: '#3ca6f2' });
                    }
                } catch(e) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal menghubungi server.', confirmButtonColor: '#3ca6f2' });
                }
            } else if (code === false && Swal.getDenyButton() && Swal.getDenyButton().classList.contains('swal2-deny')) {
                // If clicked 'Cari Toko Terdekat'
            }
        }

        // Handle deny button click (Cari Toko Terdekat)
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('swal2-deny')) {
                window.location.href = 'find-reseller.php';
            }
        });

        async function editName() {
            const { value: name } = await Swal.fire({
                title: 'Edit Nama',
                input: 'text',
                inputLabel: 'Nama baru',
                inputValue: document.getElementById('user-name-display').textContent,
                showCancelButton: true,
                cancelButtonText: 'Batal',
                confirmButtonText: 'Simpan',
                confirmButtonColor: '#3ca6f2',
                inputValidator: (v) => { if (!v || v.length < 2) return 'Nama harus minimal 2 karakter.'; }
            });

            if (name) {
                const formData = new FormData();
                formData.append('name', name);
                try {
                    const res = await fetch('api/profile.php?action=update_name', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        document.getElementById('user-name-display').textContent = data.name;
                        Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Nama berhasil diperbarui.', confirmButtonColor: '#3ca6f2', timer: 1500, showConfirmButton: false });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: data.error, confirmButtonColor: '#3ca6f2' });
                    }
                } catch(err) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal menghubungi server.', confirmButtonColor: '#3ca6f2' });
                }
            }
        }
    </script>

    <?php 
    $bottomNavActive = 'profil';
    include 'includes/bottom-nav.php'; 
    ?>

</body>
<?php include 'includes/pwa-sw.php'; ?>
</html>
