<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/icon-helper.php';

// Verify Admin Role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Global Photo Metrics
$totalPhotos = $conn->query("SELECT COUNT(*) as count FROM user_face_photos")->fetch_assoc()['count'] ?? 0;
$initialPhotosCount = $conn->query("SELECT COUNT(*) as count FROM user_face_photos WHERE photo_type = 'initial'")->fetch_assoc()['count'] ?? 0;
$progressPhotosCount = $conn->query("SELECT COUNT(*) as count FROM user_face_photos WHERE photo_type = 'progress'")->fetch_assoc()['count'] ?? 0;
$customersWithPhotosCount = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM user_face_photos")->fetch_assoc()['count'] ?? 0;
$totalLogsCount = $conn->query("SELECT COUNT(*) as count FROM consultation_logs")->fetch_assoc()['count'] ?? 0;

// Search & Filter parameters
$search = trim($_GET['search'] ?? '');
$typeFilter = trim($_GET['type'] ?? 'all');
$customerFilter = (int)($_GET['customer_id'] ?? 0);

// Build query for photos
$whereClauses = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $whereClauses[] = "(u.name LIKE ? OR u.email LIKE ? OR ufp.notes LIKE ?)";
    $likeSearch = "%{$search}%";
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $types .= "sss";
}

if ($typeFilter === 'initial' || $typeFilter === 'progress') {
    $whereClauses[] = "ufp.photo_type = ?";
    $params[] = $typeFilter;
    $types .= "s";
}

if ($customerFilter > 0) {
    $whereClauses[] = "ufp.user_id = ?";
    $params[] = $customerFilter;
    $types .= "i";
}

$whereSql = implode(" AND ", $whereClauses);

$photosQuery = "
    SELECT 
        ufp.*, 
        u.name as user_name, 
        u.email as user_email,
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status = 'paid') as has_purchased
    FROM user_face_photos ufp
    JOIN users u ON u.id = ufp.user_id
    WHERE {$whereSql}
    ORDER BY ufp.taken_at DESC, ufp.created_at DESC
";

if (!empty($params)) {
    $stmt = $conn->prepare($photosQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $photosResult = $stmt->get_result();
} else {
    $photosResult = $conn->query($photosQuery);
}

// Fetch all customers for filter dropdown
$customersList = $conn->query("
    SELECT u.id, u.name, u.email, COUNT(ufp.id) as photo_count
    FROM users u
    JOIN user_face_photos ufp ON ufp.user_id = u.id
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY u.name ASC
");

// Fetch recent consultation logs
$recentLogs = $conn->query("
    SELECT cl.*, u_user.name as user_name, u_user.email as user_email, u_expert.name as expert_name, ufp.photo_path
    FROM consultation_logs cl
    JOIN users u_user ON u_user.id = cl.user_id
    LEFT JOIN users u_expert ON u_expert.id = cl.expert_id
    LEFT JOIN user_face_photos ufp ON ufp.id = cl.face_photo_id
    ORDER BY cl.consultation_date DESC, cl.created_at DESC
    LIMIT 15
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Data Foto & Histori Konsultasi - Admin NPGLOW</title>
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
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-50 text-slate-800 antialiased font-sans flex min-h-screen">
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 min-h-screen overflow-x-hidden">
        <!-- Topbar -->
        <?php include 'topbar.php'; ?>

        <!-- Main Container -->
        <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
        <!-- Page Title & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-extrabold text-gray-900 flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </span>
                    Bank Data Foto & Histori Konsultasi Customer
                </h2>
                <p class="text-sm text-gray-500 mt-1">Pusat dokumentasi evolusi kulit pengguna, foto terkompresi WebP, dan log diagnosa tim ahli.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="../expert/photos.php" class="bg-primary hover:bg-primary-dark text-white px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold shadow-md transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                    Buka Analysis & Slider Tim Ahli
                </a>
            </div>
        </div>

        <!-- 1. Global Metrics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-gray-500 font-semibold block">Total Foto Wajah</span>
                    <span class="text-2xl font-black text-gray-900"><?= number_format($totalPhotos) ?></span>
                    <span class="text-[11px] text-purple-600 font-medium flex items-center gap-1.5 mt-0.5">
                        <span class="inline-flex items-center gap-0.5"><?= npglow_icon('camera', 'w-3 h-3 text-primary') ?> <?= $initialPhotosCount ?> Awal</span>
                        <span>•</span>
                        <span class="inline-flex items-center gap-0.5"><?= npglow_icon('sparkles', 'w-3 h-3 text-purple-500') ?> <?= $progressPhotosCount ?> Progress</span>
                    </span>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-100 text-primary flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-gray-500 font-semibold block">Customer Berfoto</span>
                    <span class="text-2xl font-black text-gray-900"><?= number_format($customersWithPhotosCount) ?></span>
                    <span class="text-[11px] text-blue-600 font-medium block mt-0.5">User memiliki journal kulit</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-gray-500 font-semibold block">Total Catatan Diagnosa</span>
                    <span class="text-2xl font-black text-gray-900"><?= number_format($totalLogsCount) ?></span>
                    <span class="text-[11px] text-emerald-600 font-medium block mt-0.5">Tercatat oleh Tim Ahli</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-gray-500 font-semibold block">Optimasi Storage WebP</span>
                    <span class="text-2xl font-black text-emerald-600 font-mono">~88%</span>
                    <span class="text-[11px] text-slate-500 font-medium flex items-center gap-1 mt-0.5">
                        Auto-Convert Aktif <?= npglow_icon('lightning', 'w-3 h-3 text-amber-500') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- 2. Search & Filter Bar -->
        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-sm">
            <form method="GET" action="photos.php" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                <!-- Search Input -->
                <div class="sm:col-span-5 relative">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama customer, email, catatan..." class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>

                <!-- Type Filter -->
                <div class="sm:col-span-3">
                    <select name="type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary font-medium text-slate-700">
                        <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>Semua Jenis Foto</option>
                        <option value="initial" <?= $typeFilter === 'initial' ? 'selected' : '' ?>>Foto Awal (Initial)</option>
                        <option value="progress" <?= $typeFilter === 'progress' ? 'selected' : '' ?>>Foto Progress Perawatan</option>
                    </select>
                </div>

                <!-- Customer Filter -->
                <div class="sm:col-span-3">
                    <select name="customer_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary font-medium text-slate-700">
                        <option value="0">Semua Customer</option>
                        <?php if ($customersList): while($cl = $customersList->fetch_assoc()): ?>
                            <option value="<?= $cl['id'] ?>" <?= $customerFilter == $cl['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cl['name']) ?> (<?= $cl['photo_count'] ?> Foto)
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>

                <!-- Submit Button -->
                <div class="sm:col-span-1 flex gap-2">
                    <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white font-bold py-2 px-3 rounded-xl text-xs transition flex items-center justify-center">
                        Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- 3. Photo Gallery Grid -->
        <div class="bg-white rounded-3xl p-5 sm:p-7 border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-base font-extrabold text-gray-900 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </span>
                        Galeri Bank Data Foto Customer (<?= $photosResult->num_rows ?> Foto Ditampilkan)
                    </h3>
                </div>
            </div>

            <?php if ($photosResult->num_rows === 0): ?>
                <div class="p-12 text-center text-slate-400 text-sm border border-dashed border-slate-200 rounded-2xl bg-slate-50">
                    Tidak ada foto yang cocok dengan kriteria pencarian atau filter yang dipilih.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php while ($p = $photosResult->fetch_assoc()): 
                        $isInitial = ($p['photo_type'] === 'initial');
                    ?>
                    <div class="bg-slate-50 rounded-2xl p-3 border border-slate-200 hover:border-primary/40 hover:shadow-md transition duration-200 flex flex-col group">
                        <!-- Photo Thumbnail -->
                        <div class="relative w-full aspect-square rounded-xl overflow-hidden bg-slate-200 mb-2.5 cursor-pointer" onclick="openLightbox('../<?= htmlspecialchars($p['photo_path']) ?>', '<?= htmlspecialchars($p['user_name']) ?>', '<?= date('d M Y', strtotime($p['taken_at'])) ?>', '<?= addslashes(htmlspecialchars($p['notes'] ?? '')) ?>')">
                            <img src="../<?= htmlspecialchars($p['photo_path']) ?>" alt="Foto" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                            <div class="absolute top-2 left-2 shadow-sm">
                                <?= npglow_photo_badge($p['photo_type']) ?>
                            </div>
                            <span class="absolute bottom-2 right-2 text-[10px] bg-black/60 backdrop-blur-sm text-white px-2 py-0.5 rounded font-mono">
                                <?= date('d M Y', strtotime($p['taken_at'])) ?>
                            </span>
                        </div>

                        <!-- Customer & Notes info -->
                        <div class="flex-1 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <h4 class="font-bold text-gray-900 text-xs truncate"><?= htmlspecialchars($p['user_name']) ?></h4>
                                    <?php if ($p['has_purchased']): ?>
                                        <span class="inline-flex items-center gap-1 text-[9px] bg-emerald-50 text-emerald-700 font-bold px-1.5 py-0.5 rounded border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Buyer
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-[10px] text-slate-500 truncate mb-1.5"><?= htmlspecialchars($p['user_email']) ?></p>

                                <?php if (!empty($p['notes'])): ?>
                                    <p class="text-[11px] text-slate-600 italic bg-white p-2 rounded-lg border border-slate-200 mb-2 line-clamp-2">
                                        "<?= htmlspecialchars($p['notes']) ?>"
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="pt-2 border-t border-slate-200 flex items-center justify-between text-[11px]">
                                <span class="text-[10px] text-slate-400 font-mono">WebP Optimized</span>
                                <a href="../expert/photos.php?user_id=<?= $p['user_id'] ?>" class="font-bold text-primary hover:text-primary-dark transition flex items-center gap-1">
                                    Dossier ➔
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 4. Recent Consultation Logs by Experts -->
        <div class="bg-white rounded-3xl p-5 sm:p-7 border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-base font-extrabold text-gray-900 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </span>
                        Log Hasil Diagnosa & Catatan Konsultasi Tim Ahli Terbaru
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Histori rekomendasi medis dan diagnosa yang dicatat oleh tim ahli kepada customer.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-600 font-bold">
                            <th class="py-3 px-4">Tanggal</th>
                            <th class="py-3 px-4">Customer</th>
                            <th class="py-3 px-4">Tim Ahli</th>
                            <th class="py-3 px-4">Kondisi Kulit</th>
                            <th class="py-3 px-4">Ringkasan Evaluasi</th>
                            <th class="py-3 px-4">Rekomendasi</th>
                            <th class="py-3 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if ($recentLogs && $recentLogs->num_rows > 0): ?>
                            <?php while ($log = $recentLogs->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-4 font-semibold text-slate-700 whitespace-nowrap">
                                    <?= date('d M Y', strtotime($log['consultation_date'])) ?>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-gray-900"><?= htmlspecialchars($log['user_name']) ?></div>
                                    <div class="text-[10px] text-slate-400"><?= htmlspecialchars($log['user_email']) ?></div>
                                </td>
                                <td class="py-3 px-4 text-slate-700 font-medium">
                                    <?= htmlspecialchars($log['expert_name'] ?? 'Tim Ahli') ?>
                                </td>
                                <td class="py-3 px-4 text-slate-800 font-medium max-w-xs truncate">
                                    <?= htmlspecialchars($log['skin_condition'] ?: '-') ?>
                                </td>
                                <td class="py-3 px-4 text-slate-600 max-w-sm">
                                    <?= htmlspecialchars($log['summary'] ?: '-') ?>
                                </td>
                                <td class="py-3 px-4 text-emerald-700 font-medium max-w-xs">
                                    <?= htmlspecialchars($log['recommendation'] ?: '-') ?>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <a href="../expert/photos.php?user_id=<?= $log['user_id'] ?>" class="text-primary hover:underline font-bold">
                                        Lihat Dossier ➔
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="py-6 text-center text-slate-400">Belum ada catatan konsultasi.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Lightbox Modal -->
    <div id="lightbox-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md hidden flex items-center justify-center p-4">
        <div class="relative max-w-3xl w-full bg-slate-900 rounded-3xl overflow-hidden border border-white/20 shadow-2xl flex flex-col max-h-[90vh]">
            <div class="p-4 bg-slate-800 text-white flex items-center justify-between border-b border-slate-700">
                <div>
                    <h4 class="font-bold text-sm" id="lightbox-title">Foto Wajah</h4>
                    <p class="text-xs text-slate-400" id="lightbox-date"></p>
                </div>
                <button onclick="closeLightbox()" class="text-slate-400 hover:text-white p-2 rounded-xl hover:bg-slate-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="flex-1 overflow-auto p-4 flex items-center justify-center bg-black/40">
                <img id="lightbox-img" src="" alt="Zoom" class="max-h-[65vh] object-contain rounded-xl shadow-lg">
            </div>
            <div class="p-4 bg-slate-800 text-slate-300 text-xs border-t border-slate-700" id="lightbox-notes-wrap">
                <p id="lightbox-notes"></p>
            </div>
        </div>
    </div>

    <script>
        function openLightbox(src, user, date, notes) {
            document.getElementById('lightbox-img').src = src;
            document.getElementById('lightbox-title').textContent = 'Foto Customer: ' + user;
            document.getElementById('lightbox-date').textContent = 'Tanggal: ' + date;
            const notesEl = document.getElementById('lightbox-notes');
            const wrapEl = document.getElementById('lightbox-notes-wrap');
            if (notes && notes.trim() !== '') {
                notesEl.textContent = 'Catatan Customer: ' + notes;
                wrapEl.classList.remove('hidden');
            } else {
                wrapEl.classList.add('hidden');
            }
            document.getElementById('lightbox-modal').classList.remove('hidden');
        }

        function closeLightbox() {
            document.getElementById('lightbox-modal').classList.add('hidden');
        }
    </script>
</body>
</html>
