<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/settings-helper.php';

// Auth Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$successMsg = '';
$errorMsg = '';

// Handle saving operational settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_operational_settings') {
    $startTime = trim($_POST['expert_start_time'] ?? '08:00');
    $endTime = trim($_POST['expert_end_time'] ?? '21:00');
    $autoSchedule = isset($_POST['expert_auto_schedule']) ? '1' : '0';
    $offlineMsg = trim($_POST['expert_offline_message'] ?? '');
    
    $workDaysArr = isset($_POST['expert_work_days']) && is_array($_POST['expert_work_days']) ? $_POST['expert_work_days'] : [1,2,3,4,5,6,7];
    $workDaysStr = implode(',', array_map('intval', $workDaysArr));
    
    save_setting($conn, 'expert_start_time', $startTime);
    save_setting($conn, 'expert_end_time', $endTime);
    save_setting($conn, 'expert_work_days', $workDaysStr);
    save_setting($conn, 'expert_auto_schedule', $autoSchedule);
    save_setting($conn, 'expert_offline_message', $offlineMsg);
    
    $successMsg = "Pengaturan jam operasional berhasil disimpan!";
}

// Handle adding new expert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expert') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (!empty($name) && !empty($email) && !empty($password)) {
        // Check if email exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errorMsg = "Email sudah terdaftar!";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'expert')");
            $stmt->bind_param("sss", $name, $email, $hashed);
            if ($stmt->execute()) {
                $successMsg = "Akun Tim Ahli berhasil ditambahkan!";
            } else {
                $errorMsg = "Gagal menambahkan akun.";
            }
        }
    } else {
        $errorMsg = "Harap isi semua kolom.";
    }
}

// Handle deleting expert
if (isset($_GET['delete_expert'])) {
    $delId = (int)$_GET['delete_expert'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'expert'");
    $stmt->bind_param("i", $delId);
    if ($stmt->execute()) {
        header("Location: index.php?msg=deleted");
        exit();
    }
}

// Fetch current settings & operational status
$settings = get_all_settings($conn);
$opStatus = get_expert_operational_status($conn);
$activeDays = array_filter(array_map('intval', explode(',', $settings['expert_work_days'] ?? '1,2,3,4,5,6,7')));

$stats = [
    'users' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'")->fetch_assoc()['count'],
    'resellers' => $conn->query("SELECT COUNT(*) as count FROM reseller_stores WHERE is_active = 1")->fetch_assoc()['count'] ?? 0,
    'experts' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'expert'")->fetch_assoc()['count'],
    'products' => $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'],
    'orders' => $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'] ?? 0,
    'photos' => $conn->query("SELECT COUNT(*) as count FROM user_face_photos")->fetch_assoc()['count'] ?? 0
];

// Fetch experts with online status
$experts = $conn->query("SELECT id, name, email, is_online, last_active, created_at FROM users WHERE role = 'expert' ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NPGLOW</title>
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
    <style>
        body { font-family: 'Inter', sans-serif; }
        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.6; }
            100% { transform: scale(0.95); opacity: 1; }
        }
        .pulse-ring { animation: pulse-ring 2s ease-in-out infinite; }
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
            <div class="max-w-6xl mx-auto space-y-8 pb-12">
            
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
            <?php if (isset($_GET['notice']) && $_GET['notice'] === 'buyer_only'): ?>
                <div class="bg-amber-50 text-amber-800 p-4 rounded-2xl border border-amber-200 flex items-center gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="text-sm font-semibold">Akun Administrator tidak diperuntukkan untuk membeli produk (hanya untuk akun Pelanggan/Customer).</span>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                <div class="bg-emerald-50 text-emerald-700 p-4 rounded-2xl border border-emerald-200 flex items-center gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span class="text-sm font-semibold">Akun berhasil dihapus.</span>
                </div>
            <?php endif; ?>

            <!-- Header section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Ringkasan Sistem</h2>
                    <p class="text-gray-500 text-sm mt-1">Pantau performa, kelola tim ahli, dan atur jam kerja konsultasi.</p>
                </div>
                <div class="inline-flex items-center gap-2 bg-white px-4 py-2 rounded-2xl border border-gray-200 text-xs font-semibold text-gray-600 shadow-sm">
                    <span class="w-2.5 h-2.5 rounded-full <?= $opStatus['is_online'] ? 'bg-emerald-500 pulse-ring' : ($opStatus['is_in_hours'] ? 'bg-gray-400' : 'bg-amber-500') ?>"></span>
                    Status Live: <strong class="<?= $opStatus['is_online'] ? 'text-emerald-600' : ($opStatus['is_in_hours'] ? 'text-gray-700' : 'text-amber-600') ?>"><?= $opStatus['status_label'] ?></strong>
                    <span class="text-gray-400 ml-1">(<?= date('H:i') ?> WIB)</span>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 sm:gap-6">
                <!-- Stat Card -->
                <div class="bg-white rounded-2xl p-5 shadow-[0_2px_10px_rgba(0,0,0,0.04)] border border-gray-100 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Pelanggan</p>
                        <h3 class="text-xl font-bold text-gray-900"><?= number_format($stats['users']) ?></h3>
                    </div>
                </div>
                <!-- Stat Card - Reseller -->
                <a href="resellers.php" class="bg-white rounded-2xl p-5 shadow-[0_2px_10px_rgba(0,0,0,0.04)] border border-gray-100 flex items-center gap-3 hover:border-teal-300 transition group">
                    <div class="w-12 h-12 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Mitra Reseller</p>
                        <h3 class="text-xl font-bold text-teal-700"><?= number_format($stats['resellers']) ?></h3>
                    </div>
                </a>
                <!-- Stat Card -->
                <div class="bg-white rounded-2xl p-5 shadow-[0_2px_10px_rgba(0,0,0,0.04)] border border-gray-100 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-500 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Tim Ahli</p>
                        <h3 class="text-xl font-bold text-gray-900"><?= number_format($stats['experts']) ?></h3>
                    </div>
                </div>
                <!-- Stat Card -->
                <div class="bg-white rounded-2xl p-5 shadow-[0_2px_10px_rgba(0,0,0,0.04)] border border-gray-100 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Total Produk</p>
                        <h3 class="text-xl font-bold text-gray-900"><?= number_format($stats['products']) ?></h3>
                    </div>
                </div>
                <!-- Stat Card -->
                <div class="bg-white rounded-2xl p-5 shadow-[0_2px_10px_rgba(0,0,0,0.04)] border border-gray-100 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Total Pesanan</p>
                        <h3 class="text-xl font-bold text-gray-900"><?= number_format($stats['orders']) ?></h3>
                    </div>
                </div>
                <!-- Stat Card - Bank Data Foto -->
                <a href="photos.php" class="bg-white rounded-2xl p-5 shadow-[0_2px_10px_rgba(0,0,0,0.04)] border border-gray-100 flex items-center gap-3 hover:border-primary/50 transition group">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Bank Data Foto</p>
                        <h3 class="text-xl font-bold text-gray-900"><?= number_format($stats['photos']) ?></h3>
                    </div>
                </a>
            </div>

            <!-- Operational Hours Settings Card -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-[0_2px_10px_rgba(0,0,0,0.04)] border border-gray-100">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 mb-6 border-b border-gray-100 gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-blue-50 text-primary flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Pengaturan Jam Operasional Tim Ahli</h3>
                            <p class="text-gray-500 text-xs mt-0.5">Tentukan jam kerja di mana Tim Ahli melayani konsultasi. Di luar jam ini, status otomatis dinyatakan offline.</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold <?= $opStatus['is_in_hours'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?>">
                            <span class="w-2 h-2 rounded-full <?= $opStatus['is_in_hours'] ? 'bg-emerald-500' : 'bg-amber-500' ?>"></span>
                            <?= $opStatus['is_in_hours'] ? 'Dalam Jam Kerja' : 'Di Luar Jam Kerja' ?>
                        </span>
                    </div>
                </div>

                <form method="POST" action="index.php" class="space-y-6">
                    <input type="hidden" name="action" value="save_operational_settings">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Start Time -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Jam Buka / Mulai</label>
                            <input type="time" name="expert_start_time" value="<?= htmlspecialchars($settings['expert_start_time'] ?? '08:00') ?>" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-semibold text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary focus:bg-white transition-all">
                        </div>

                        <!-- End Time -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Jam Tutup / Selesai</label>
                            <input type="time" name="expert_end_time" value="<?= htmlspecialchars($settings['expert_end_time'] ?? '21:00') ?>" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-semibold text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary focus:bg-white transition-all">
                        </div>

                        <!-- Auto Schedule Switch -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Status Otomatis</label>
                            <label class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 cursor-pointer hover:bg-gray-100 transition-colors">
                                <input type="checkbox" name="expert_auto_schedule" value="1" <?= ($settings['expert_auto_schedule'] ?? '1') === '1' ? 'checked' : '' ?> class="w-4 h-4 text-primary rounded border-gray-300 focus:ring-primary">
                                <span class="text-xs font-semibold text-gray-700">Terapkan Batasan Jam Kerja</span>
                            </label>
                            <p class="text-[11px] text-gray-400 mt-1.5 leading-tight">Jika dinonaktifkan, status hanya bergantung pada login Tim Ahli (24 jam).</p>
                        </div>
                    </div>

                    <!-- Work Days Selection -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Hari Operasional Aktif</label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-2.5">
                            <?php
                            $daysMap = [
                                1 => 'Senin',
                                2 => 'Selasa',
                                3 => 'Rabu',
                                4 => 'Kamis',
                                5 => 'Jumat',
                                6 => 'Sabtu',
                                7 => 'Minggu'
                            ];
                            foreach ($daysMap as $dayNum => $dayLabel):
                                $isChecked = in_array($dayNum, $activeDays);
                            ?>
                            <label class="flex items-center justify-between p-3 rounded-xl border text-xs font-semibold cursor-pointer transition-all <?= $isChecked ? 'bg-blue-50/80 border-primary text-primary shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-500 hover:bg-gray-100' ?>">
                                <span><?= $dayLabel ?></span>
                                <input type="checkbox" name="expert_work_days[]" value="<?= $dayNum ?>" <?= $isChecked ? 'checked' : '' ?> class="w-3.5 h-3.5 text-primary rounded border-gray-300 focus:ring-primary">
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Offline Custom Message -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Pesan Notifikasi Saat Offline / Di Luar Jam Kerja</label>
                        <input type="text" name="expert_offline_message" value="<?= htmlspecialchars($settings['expert_offline_message'] ?? '') ?>" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary focus:bg-white transition-all" placeholder="Contoh: Tim ahli melayani konsultasi setiap hari pukul 08:00 - 21:00 WIB.">
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end pt-2">
                        <button type="submit" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white font-bold px-6 py-3 rounded-xl text-sm transition-all shadow-md hover:shadow-lg">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Simpan Pengaturan Operasional
                        </button>
                    </div>
                </form>
            </div>

            <!-- Manage Experts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Left: List -->
                <div class="lg:col-span-2 bg-white rounded-3xl p-6 shadow-[0_2px_10px_rgba(0,0,0,0.04)] border border-gray-100">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Daftar Akun Tim Ahli</h3>
                            <p class="text-gray-400 text-xs mt-0.5">Status aktivitas online terkini dari masing-masing staf ahli.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 rounded-t-xl">
                                <tr>
                                    <th class="px-6 py-3 rounded-tl-xl">Nama & Status</th>
                                    <th class="px-6 py-3">Email</th>
                                    <th class="px-6 py-3">Aktivitas Terakhir</th>
                                    <th class="px-6 py-3 rounded-tr-xl">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($experts && $experts->num_rows > 0): ?>
                                    <?php while($exp = $experts->fetch_assoc()): 
                                        $isOnlineNow = ($exp['is_online'] == 1 && !empty($exp['last_active']) && (time() - strtotime($exp['last_active'])) <= 90);
                                    ?>
                                    <tr class="border-b last:border-0 hover:bg-gray-50">
                                        <td class="px-6 py-4 font-medium text-gray-900 flex items-center gap-3">
                                            <div class="relative">
                                                <div class="w-9 h-9 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center font-bold">
                                                    <?= strtoupper(substr($exp['name'], 0, 1)) ?>
                                                </div>
                                                <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full border-2 border-white <?= $isOnlineNow ? 'bg-emerald-500' : 'bg-gray-400' ?>"></span>
                                            </div>
                                            <div>
                                                <div class="font-bold text-gray-800"><?= htmlspecialchars($exp['name']) ?></div>
                                                <div class="text-[11px] <?= $isOnlineNow ? 'text-emerald-600 font-semibold' : 'text-gray-400' ?>">
                                                    <?= $isOnlineNow ? '● Sedang Online' : '○ Offline' ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4"><?= htmlspecialchars($exp['email']) ?></td>
                                        <td class="px-6 py-4 text-xs">
                                            <?php if (!empty($exp['last_active'])): ?>
                                                <?= date('d M Y, H:i', strtotime($exp['last_active'])) ?> WIB
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <a href="index.php?delete_expert=<?= $exp['id'] ?>" onclick="return confirm('Yakin ingin menghapus akun Tim Ahli ini?')" class="text-red-500 hover:text-red-700 font-medium text-xs bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg transition-colors">Hapus</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-8 text-center text-gray-400">Belum ada akun Tim Ahli.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Right: Form -->
                <div class="bg-slate-800 rounded-3xl p-6 shadow-xl border border-slate-700 text-white h-fit">
                    <h3 class="text-lg font-bold mb-2 flex items-center gap-2">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Tambah Tim Ahli
                    </h3>
                    <p class="text-slate-400 text-xs mb-6">Buat akun baru untuk staf ahli yang akan menangani konsultasi pengguna.</p>

                    <form method="POST" action="index.php" class="space-y-4">
                        <input type="hidden" name="action" value="add_expert">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Lengkap</label>
                            <input type="text" name="name" required class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-primary transition-colors" placeholder="dr. / Ahli Skincare">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Email</label>
                            <input type="email" name="email" required class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-primary transition-colors" placeholder="expert@npglow.com">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Password</label>
                            <input type="password" name="password" required class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-primary transition-colors" placeholder="••••••••">
                        </div>
                        <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-2.5 rounded-xl text-sm transition-colors mt-2 shadow-lg shadow-primary/20">
                            Buat Akun Tim Ahli
                        </button>
                    </form>
                </div>

            </div>
        </main>
    </div>
</body>
</html>
