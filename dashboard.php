<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth-helper.php';
require_once 'includes/order-tracking-helper.php';
require_once 'includes/icon-helper.php';

// Customer only guard
guard_customer_only();

$userId = $_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Stats: total orders
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ? AND (status = 'completed' OR order_status = 'delivered')");
$stmt->bind_param("i", $userId);
$stmt->execute();
$totalOrders = $stmt->get_result()->fetch_assoc()['total'];

// Active / Pending Order check (unpaid, processing, shipped)
$stmt = $conn->prepare("
    SELECT o.*, p.name as product_name, p.image_url as product_image 
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    WHERE o.user_id = ? 
      AND (o.order_status IN ('unpaid', 'processing', 'shipped') OR o.payment_status IN ('pending', 'waiting_verification'))
    ORDER BY o.order_date DESC 
    LIMIT 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$activeOrder = $stmt->get_result()->fetch_assoc();

// Stats: total consultations
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM consultation_logs WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$totalConsultations = $stmt->get_result()->fetch_assoc()['total'];

// Stats: last consultation date
$stmt = $conn->prepare("SELECT consultation_date FROM consultation_logs WHERE user_id = ? ORDER BY consultation_date DESC LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$lastConsultation = $stmt->get_result()->fetch_assoc();
$lastConsultDate = $lastConsultation ? $lastConsultation['consultation_date'] : null;

// Stats: total face photos
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM user_face_photos WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$totalPhotos = $stmt->get_result()->fetch_assoc()['total'];

// Recent journal entries (last 3)
$stmt = $conn->prepare("SELECT ufp.*, cl.summary, cl.skin_condition FROM user_face_photos ufp LEFT JOIN consultation_logs cl ON cl.face_photo_id = ufp.id WHERE ufp.user_id = ? ORDER BY ufp.created_at DESC LIMIT 3");
$stmt->bind_param("i", $userId);
$stmt->execute();
$recentPhotos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Initial face photo
$stmt = $conn->prepare("SELECT * FROM user_face_photos WHERE user_id = ? AND photo_type = 'initial' ORDER BY created_at ASC LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$initialPhoto = $stmt->get_result()->fetch_assoc();

// Latest face photo
$stmt = $conn->prepare("SELECT * FROM user_face_photos WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$latestPhoto = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - NPGLOW</title>
    <?php include 'includes/pwa-head.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3ca6f2',
                        'primary-dark': '#2e8ccf',
                        'primary-light': '#66bcf5',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        .gradient-mesh {
            background: 
                radial-gradient(at 20% 20%, rgba(60, 166, 242, 0.12) 0px, transparent 50%),
                radial-gradient(at 80% 80%, rgba(60, 166, 242, 0.08) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(147, 197, 253, 0.06) 0px, transparent 50%);
        }
        .nav-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .nav-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px -12px rgba(60, 166, 242, 0.25);
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }
        .float-anim { animation: float 3s ease-in-out infinite; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen gradient-mesh pb-20 sm:pb-0">

    <!-- Navbar -->
    <nav class="sticky top-0 z-50 glass-card border-b border-white/30 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2.5">
                <img class="h-9 w-auto object-contain rounded-lg" src="assets/images/logo_np_glow.jpeg" alt="NPGLOW">
                <span class="font-extrabold text-lg text-gray-800 tracking-tight">NPGLOW</span>
            </a>
            <div class="flex items-center gap-3">
                <a href="index.php" class="text-sm text-gray-500 hover:text-primary transition-colors font-medium hidden sm:block">Landing Page</a>
                <div class="h-4 w-px bg-gray-300 hidden sm:block"></div>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary to-blue-400 flex items-center justify-center text-white text-xs font-bold shadow-md">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <span class="text-sm font-semibold text-gray-700 hidden sm:block"><?= htmlspecialchars($user['name']) ?></span>
                </div>
                <a href="logout.php" class="text-xs text-gray-400 hover:text-red-500 transition-colors ml-1" title="Logout">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </a>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8">

        <!-- Greeting -->
        <div class="mb-6" data-aos="fade-up">
            <div class="flex items-center gap-2 mb-1">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-800">Halo, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!</h1>
            </div>
            <p class="text-gray-500 text-sm sm:text-base">Selamat datang kembali di NPGLOW. Apa yang ingin kamu lakukan hari ini?</p>
        </div>

        <?php if ($activeOrder): ?>
            <?php 
                $actMeta = get_order_status_info($activeOrder['order_status'], $activeOrder['payment_status']);
                $actOrderNum = $activeOrder['order_number'] ?: ('NP-#' . $activeOrder['id']);
                $actTotal = (float)($activeOrder['total_amount'] ?: $activeOrder['price']);
            ?>
            <!-- Active Order Reminder Card (Shopee Style) -->
            <div class="bg-gradient-to-r from-blue-500/10 via-indigo-500/10 to-primary/10 border border-blue-200 rounded-3xl p-4 sm:p-5 mb-8 shadow-sm" data-aos="fade-up">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-white text-primary flex items-center justify-center shadow-sm flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-bold text-gray-500"><?= htmlspecialchars($actOrderNum) ?></span>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full <?= $actMeta['badge_class'] ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $actMeta['dot_class'] ?>"></span>
                                    <?= $actMeta['label'] ?>
                                </span>
                            </div>
                            <h4 class="text-sm font-bold text-gray-800 mt-0.5"><?= htmlspecialchars($activeOrder['product_name']) ?> (Rp <?= number_format($actTotal, 0, ',', '.') ?>)</h4>
                            <p class="text-[11px] text-gray-500"><?= $actMeta['summary'] ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if ($activeOrder['order_status'] === 'unpaid' && $activeOrder['payment_status'] !== 'rejected'): ?>
                            <a href="payment.php?order_id=<?= $activeOrder['id'] ?>" class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-2xl shadow-sm transition">
                                <span>Bayar Sekarang</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                            </a>
                        <?php else: ?>
                            <a href="order-tracking.php?order_id=<?= $activeOrder['id'] ?>" class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-2xl shadow-sm transition">
                                <span>Lacak Status Pesanan</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8" data-aos="fade-up" data-aos-delay="100">
            <div class="glass-card rounded-2xl p-4 sm:p-5 border border-white/40 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                </div>
                <p class="text-2xl font-extrabold text-gray-800"><?= $totalOrders ?></p>
                <p class="text-xs text-gray-500 font-medium mt-0.5">Pesanan Selesai</p>
            </div>
            <div class="glass-card rounded-2xl p-4 sm:p-5 border border-white/40 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                </div>
                <p class="text-2xl font-extrabold text-gray-800"><?= $totalConsultations ?></p>
                <p class="text-xs text-gray-500 font-medium mt-0.5">Sesi Konsultasi</p>
            </div>
            <div class="glass-card rounded-2xl p-4 sm:p-5 border border-white/40 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </div>
                <p class="text-2xl font-extrabold text-gray-800"><?= $totalPhotos ?></p>
                <p class="text-xs text-gray-500 font-medium mt-0.5">Foto Journal</p>
            </div>
            <div class="glass-card rounded-2xl p-4 sm:p-5 border border-white/40 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <p class="text-2xl font-extrabold text-gray-800"><?= $lastConsultDate ? date('d M', strtotime($lastConsultDate)) : '-' ?></p>
                <p class="text-xs text-gray-500 font-medium mt-0.5">Konsultasi Terakhir</p>
            </div>
        </div>

        <!-- Navigation Cards (Desktop) -->
        <div class="hidden sm:grid sm:grid-cols-2 lg:grid-cols-5 gap-4 sm:gap-5 mb-10" data-aos="fade-up" data-aos-delay="200">
            <!-- Belanja Produk -->
            <a href="index.php#marketplace" class="nav-card bg-white rounded-2xl sm:rounded-3xl p-5 border border-gray-100 shadow-md flex flex-col items-center text-center group">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-400 to-primary flex items-center justify-center mb-4 group-hover:shadow-lg group-hover:shadow-blue-200 transition-shadow float-anim">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                </div>
                <h3 class="font-bold text-sm text-gray-800 mb-1">Belanja Produk</h3>
                <p class="text-[11px] text-gray-400 leading-relaxed">Beli skincare NPGLOW</p>
            </a>

            <!-- Pesanan Saya -->
            <a href="my-orders.php" class="nav-card bg-white rounded-2xl sm:rounded-3xl p-5 border border-gray-100 shadow-md flex flex-col items-center text-center group">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-400 to-indigo-600 flex items-center justify-center mb-4 group-hover:shadow-lg group-hover:shadow-indigo-200 transition-shadow float-anim" style="animation-delay: 0.3s;">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                </div>
                <h3 class="font-bold text-sm text-gray-800 mb-1">Pesanan Saya</h3>
                <p class="text-[11px] text-gray-400 leading-relaxed">Status & tracking resi</p>
            </a>

            <!-- Konsultasi -->
            <a href="konsultasi.php" class="nav-card bg-white rounded-2xl sm:rounded-3xl p-5 border border-gray-100 shadow-md flex flex-col items-center text-center group">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center mb-4 group-hover:shadow-lg group-hover:shadow-emerald-200 transition-shadow float-anim" style="animation-delay: 0.6s;">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                </div>
                <h3 class="font-bold text-sm text-gray-800 mb-1">Konsultasi</h3>
                <p class="text-[11px] text-gray-400 leading-relaxed">Chat AI / Tim Ahli</p>
            </a>

            <!-- Skincare Journal -->
            <a href="journal.php" class="nav-card bg-white rounded-2xl sm:rounded-3xl p-5 border border-gray-100 shadow-md flex flex-col items-center text-center group">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center mb-4 group-hover:shadow-lg group-hover:shadow-purple-200 transition-shadow float-anim" style="animation-delay: 0.9s;">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </div>
                <h3 class="font-bold text-sm text-gray-800 mb-1">Skincare Journal</h3>
                <p class="text-[11px] text-gray-400 leading-relaxed">Progress kulitmu</p>
            </a>

            <!-- Profil Saya -->
            <a href="profile.php" class="nav-card bg-white rounded-2xl sm:rounded-3xl p-5 border border-gray-100 shadow-md flex flex-col items-center text-center group">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center mb-4 group-hover:shadow-lg group-hover:shadow-amber-200 transition-shadow float-anim" style="animation-delay: 1.2s;">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <h3 class="font-bold text-sm text-gray-800 mb-1">Profil Saya</h3>
                <p class="text-[11px] text-gray-400 leading-relaxed">Kelola akun kamu</p>
            </a>
        </div>

        <!-- Before-After Preview (if photos exist) -->
        <?php if ($initialPhoto && $latestPhoto && $initialPhoto['id'] !== $latestPhoto['id']): ?>
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-md mb-8" data-aos="fade-up" data-aos-delay="300">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="text-lg sm:text-xl font-extrabold text-gray-800 flex items-center gap-2">
                        Progress Kulitmu <?= npglow_icon('sparkles', 'w-5 h-5 text-amber-500') ?>
                    </h2>
                    <p class="text-xs text-gray-400 mt-0.5">Perbandingan foto awal dan terbaru</p>
                </div>
                <a href="journal.php" class="text-xs text-primary font-semibold hover:underline">Lihat Semua →</a>
            </div>
            <div class="grid grid-cols-2 gap-4 sm:gap-6">
                <div class="text-center">
                    <div class="aspect-square rounded-2xl overflow-hidden bg-gray-100 mb-2 shadow-inner">
                        <img src="<?= htmlspecialchars($initialPhoto['photo_path']) ?>" alt="Foto Awal" class="w-full h-full object-cover">
                    </div>
                    <span class="text-xs font-bold text-gray-600">Foto Awal</span>
                    <p class="text-[10px] text-gray-400"><?= date('d M Y', strtotime($initialPhoto['taken_at'])) ?></p>
                </div>
                <div class="text-center">
                    <div class="aspect-square rounded-2xl overflow-hidden bg-gray-100 mb-2 shadow-inner">
                        <img src="<?= htmlspecialchars($latestPhoto['photo_path']) ?>" alt="Foto Terbaru" class="w-full h-full object-cover">
                    </div>
                    <span class="text-xs font-bold text-gray-600">Foto Terbaru</span>
                    <p class="text-[10px] text-gray-400"><?= date('d M Y', strtotime($latestPhoto['taken_at'])) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Journal Entries -->
        <?php if (!empty($recentPhotos)): ?>
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-md mb-8" data-aos="fade-up" data-aos-delay="400">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-lg sm:text-xl font-extrabold text-gray-800">Aktivitas Terbaru</h2>
                <a href="journal.php" class="text-xs text-primary font-semibold hover:underline">Lihat Semua →</a>
            </div>
            <div class="space-y-4">
                <?php foreach ($recentPhotos as $entry): ?>
                <div class="flex items-center gap-4 p-3 rounded-xl bg-gray-50 hover:bg-blue-50/50 transition-colors">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl overflow-hidden bg-gray-200 flex-shrink-0 shadow-sm">
                        <img src="<?= htmlspecialchars($entry['photo_path']) ?>" alt="Progress" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="mb-0.5">
                            <?= npglow_photo_badge($entry['photo_type'], $entry['photo_type'] === 'initial' ? 'Foto Wajah Awal' : 'Foto Progress') ?>
                        </div>
                        <p class="text-xs text-gray-400 truncate"><?= $entry['notes'] ? htmlspecialchars($entry['notes']) : 'Tidak ada catatan' ?></p>
                    </div>
                    <span class="text-[11px] text-gray-400 font-medium whitespace-nowrap"><?= date('d M Y', strtotime($entry['taken_at'])) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <!-- Empty state for new users -->
        <div class="bg-white rounded-3xl p-8 sm:p-12 border border-gray-100 shadow-md mb-8 text-center" data-aos="fade-up" data-aos-delay="300">
            <div class="w-20 h-20 mx-auto rounded-full bg-blue-50 flex items-center justify-center mb-4">
                <svg class="w-10 h-10 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800 mb-2">Belum Ada Aktivitas</h3>
            <p class="text-sm text-gray-500 mb-6 max-w-md mx-auto">Mulai perjalanan skincare-mu dengan membeli produk NPGLOW. Foto wajahmu akan dicatat sebagai titik awal!</p>
            <a href="index.php#marketplace" class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-xl font-bold text-sm hover:bg-primary-dark transition-colors shadow-md hover:shadow-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                Belanja Sekarang
            </a>
        </div>
        <?php endif; ?>

    </main>

    <!-- Payment Success Alert -->
    <?php if (isset($_GET['payment']) && $_GET['payment'] === 'success'): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Pembayaran Berhasil!',
            text: 'Produk telah dibeli. Kamu sekarang bisa mengakses fitur konsultasi!',
            confirmButtonColor: '#3ca6f2',
            confirmButtonText: 'Mulai Konsultasi',
            showCancelButton: true,
            cancelButtonText: 'Nanti Saja'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'chat.php';
            }
        });
    </script>
    <?php endif; ?>

    <!-- Bottom Navbar (Shared Component) -->
    <?php 
    $bottomNavActive = 'beranda';
    include 'includes/bottom-nav.php'; 
    ?>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>AOS.init({ duration: 600, once: true });</script>
</body>
<?php include 'includes/pwa-sw.php'; ?>
</html>
