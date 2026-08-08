<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/settings-helper.php';
require_once '../includes/icon-helper.php';

// Verify Expert or Admin Role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['expert', 'admin'])) {
    header("Location: ../login.php");
    exit();
}

$expertId = $_SESSION['user_id'];
$expertRole = $_SESSION['role'];

// Fetch settings & operational status
$settings = get_all_settings($conn);
$opStatus = get_expert_operational_status($conn);

// Fetch all customers who have uploaded face photos OR have consultation chats
$customersQuery = "
    SELECT 
        u.id, 
        u.name, 
        u.email, 
        u.created_at as register_date,
        COUNT(DISTINCT ufp.id) as total_photos,
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status = 'paid') as has_purchased,
        (SELECT photo_path FROM user_face_photos WHERE user_id = u.id AND photo_type = 'initial' ORDER BY taken_at ASC, id ASC LIMIT 1) as initial_photo,
        (SELECT photo_path FROM user_face_photos WHERE user_id = u.id ORDER BY taken_at DESC, id DESC LIMIT 1) as latest_photo,
        (SELECT taken_at FROM user_face_photos WHERE user_id = u.id ORDER BY taken_at DESC, id DESC LIMIT 1) as last_photo_date,
        (SELECT taken_at FROM user_face_photos WHERE user_id = u.id AND photo_type = 'initial' ORDER BY taken_at ASC, id ASC LIMIT 1) as first_photo_date
    FROM users u
    LEFT JOIN user_face_photos ufp ON ufp.user_id = u.id
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY total_photos DESC, last_photo_date DESC, u.created_at DESC
";
$customersResult = $conn->query($customersQuery);

$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$selectedCustomer = null;
$customerPhotos = [];
$consultationLogs = [];

if ($selectedUserId > 0) {
    // Fetch customer profile
    $stmt = $conn->prepare("
        SELECT u.*, 
        (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND status = 'paid') as has_purchased
        FROM users u 
        WHERE u.id = ? AND u.role = 'user'
    ");
    $stmt->bind_param("i", $selectedUserId);
    $stmt->execute();
    $selectedCustomer = $stmt->get_result()->fetch_assoc();

    if ($selectedCustomer) {
        // Fetch all face photos for this customer
        $photoStmt = $conn->prepare("
            SELECT * FROM user_face_photos 
            WHERE user_id = ? 
            ORDER BY taken_at ASC, created_at ASC
        ");
        $photoStmt->bind_param("i", $selectedUserId);
        $photoStmt->execute();
        $customerPhotos = $photoStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Fetch consultation logs
        $logStmt = $conn->prepare("
            SELECT cl.*, u.name as expert_name, ufp.photo_path, ufp.photo_type
            FROM consultation_logs cl
            LEFT JOIN users u ON u.id = cl.expert_id
            LEFT JOIN user_face_photos ufp ON ufp.id = cl.face_photo_id
            WHERE cl.user_id = ?
            ORDER BY cl.consultation_date DESC, cl.created_at DESC
        ");
        $logStmt->bind_param("i", $selectedUserId);
        $logStmt->execute();
        $consultationLogs = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Data Foto & Histori Konsultasi - Tim Ahli NPGLOW</title>
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
        
        /* Interactive Before-After Slider */
        .comparison-container {
            position: relative;
            overflow: hidden;
            border-radius: 1.5rem;
            user-select: none;
            touch-action: pan-y;
            background-color: #f1f5f9;
        }
        .comparison-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            pointer-events: none;
        }
        .comparison-before {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 50%;
            overflow: hidden;
            z-index: 10;
        }
        .comparison-before .comparison-image {
            width: 100%;
            height: 100%;
            max-width: none;
            object-fit: cover;
        }
        .comparison-handle {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50%;
            width: 4px;
            background: white;
            z-index: 20;
            transform: translateX(-50%);
            cursor: ew-resize;
            box-shadow: 0 0 12px rgba(0,0,0,0.35);
        }
        .comparison-handle-btn {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3ca6f2;
            pointer-events: none;
        }
        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.6; }
            100% { transform: scale(0.95); opacity: 1; }
        }
        .pulse-ring { animation: pulse-ring 2s ease-in-out infinite; }
    </style>
</head>
<body class="bg-slate-50 h-screen flex flex-col overflow-hidden">
    <!-- Navbar Header -->
    <header class="bg-[#3ca6f2] text-white p-3 sm:p-4 flex flex-wrap justify-between items-center shadow-md z-20 gap-3 flex-shrink-0">
        <div class="flex items-center gap-4 sm:gap-6">
            <h1 class="font-bold text-lg sm:text-xl flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                NPGLOW Tim Ahli
            </h1>
            <nav class="flex gap-2">
                <a href="index.php" class="hover:bg-white/20 px-3.5 py-1.5 rounded-lg text-sm font-medium transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    Konsultasi Chat
                </a>
                <a href="photos.php" class="bg-white/20 px-3.5 py-1.5 rounded-lg text-sm font-bold transition flex items-center gap-1.5 shadow-inner">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Bank Data Foto
                </a>
                <?php if ($expertRole === 'admin'): ?>
                <a href="../admin/index.php" class="hover:bg-white/20 px-3.5 py-1.5 rounded-lg text-sm font-medium transition">Admin Portal</a>
                <?php endif; ?>
            </nav>
        </div>

        <div class="flex items-center gap-3 ml-auto">
            <!-- Online status badge -->
            <div class="hidden lg:flex items-center gap-2 bg-white/10 px-3 py-1 rounded-full text-xs font-medium border border-white/20">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>Halo, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Tim Ahli') ?></span>
            </div>
            <a href="../logout.php" class="bg-red-500/20 hover:bg-red-500/30 text-white px-3 py-1.5 rounded-full font-medium text-xs transition-colors border border-red-300/30">Logout</a>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar Customer List -->
        <div class="w-full sm:w-80 lg:w-96 bg-white border-r border-slate-200 flex flex-col flex-shrink-0 z-10 shadow-sm">
            <div class="p-4 border-b border-slate-100 bg-slate-50">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-extrabold text-gray-800 text-sm flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Daftar Customer Foto
                    </h2>
                    <span class="text-xs bg-blue-50 text-primary font-bold px-2 py-0.5 rounded-full"><?= $customersResult->num_rows ?> User</span>
                </div>
                <!-- Search input -->
                <div class="relative">
                    <input type="text" id="search-customer" placeholder="Cari nama atau email..." class="w-full bg-white border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto divide-y divide-slate-100" id="customer-list-container">
                <?php if ($customersResult->num_rows === 0): ?>
                    <div class="p-8 text-center text-slate-400 text-xs">
                        Belum ada data customer atau foto yang tercatat.
                    </div>
                <?php else: ?>
                    <?php while ($c = $customersResult->fetch_assoc()): 
                        $isSelected = ($c['id'] == $selectedUserId);
                        $hasPhotos = ($c['total_photos'] > 0);
                    ?>
                    <a href="photos.php?user_id=<?= $c['id'] ?>" class="customer-item block p-3.5 hover:bg-blue-50/70 transition <?= $isSelected ? 'bg-blue-50 border-l-4 border-primary shadow-sm' : '' ?>" data-name="<?= strtolower(htmlspecialchars($c['name'])) ?>" data-email="<?= strtolower(htmlspecialchars($c['email'])) ?>">
                        <div class="flex items-start gap-3">
                            <!-- Thumbnail / Avatar -->
                            <div class="relative flex-shrink-0">
                                <?php if (!empty($c['latest_photo'])): ?>
                                    <img src="../<?= htmlspecialchars($c['latest_photo']) ?>" alt="Foto" class="w-11 h-11 rounded-xl object-cover border border-slate-200 shadow-sm">
                                <?php else: ?>
                                    <div class="w-11 h-11 rounded-xl bg-blue-100 text-primary flex items-center justify-center font-extrabold text-sm border border-blue-200">
                                        <?= strtoupper(substr($c['name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($hasPhotos): ?>
                                    <span class="absolute -top-1 -right-1 bg-purple-600 text-white text-[9px] font-bold px-1.5 py-0.2 rounded-full shadow">
                                        <?= $c['total_photos'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-0.5">
                                    <h3 class="font-bold text-gray-800 text-xs truncate"><?= htmlspecialchars($c['name']) ?></h3>
                                    <?php if (!empty($c['last_photo_date'])): ?>
                                        <span class="text-[10px] text-slate-400 whitespace-nowrap"><?= date('d M', strtotime($c['last_photo_date'])) ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-[11px] text-slate-500 truncate mb-1"><?= htmlspecialchars($c['email']) ?></p>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <?php if ($c['has_purchased']): ?>
                                        <span class="inline-flex items-center gap-1 text-[9px] font-semibold bg-emerald-50 text-emerald-700 px-1.5 py-0.5 rounded border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Pelanggan
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[9px] font-semibold bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded">Calon Pelanggan</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($hasPhotos): ?>
                                        <span class="inline-flex items-center gap-1 text-[9px] font-semibold bg-purple-50 text-purple-700 px-1.5 py-0.5 rounded border border-purple-200">
                                            <?= npglow_icon('camera', 'w-3 h-3 text-purple-600') ?> <?= $c['total_photos'] ?> Foto
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[9px] text-slate-400">Belum ada foto</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </a>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col bg-slate-100 overflow-y-auto">
            <?php if ($selectedCustomer): 
                $photoCount = count($customerPhotos);
                $initialPhoto = null;
                $latestPhoto = null;
                foreach ($customerPhotos as $cp) {
                    if ($cp['photo_type'] === 'initial' && !$initialPhoto) $initialPhoto = $cp;
                    $latestPhoto = $cp;
                }
                if (!$initialPhoto && !empty($customerPhotos)) $initialPhoto = $customerPhotos[0];

                // Calculate duration of skincare journey
                $journeyDays = 0;
                if ($initialPhoto && $latestPhoto) {
                    $d1 = new DateTime($initialPhoto['taken_at']);
                    $d2 = new DateTime($latestPhoto['taken_at']);
                    $journeyDays = $d1->diff($d2)->days;
                }
            ?>
            <!-- Customer Banner Header -->
            <div class="bg-white p-5 sm:p-6 border-b border-slate-200 shadow-sm flex-shrink-0">
                <div class="max-w-7xl mx-auto flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-primary to-blue-400 text-white flex items-center justify-center font-black text-xl shadow-md flex-shrink-0">
                            <?= strtoupper(substr($selectedCustomer['name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg sm:text-xl font-extrabold text-gray-900"><?= htmlspecialchars($selectedCustomer['name']) ?></h2>
                                <?php if ($selectedCustomer['has_purchased']): ?>
                                    <span class="text-xs bg-emerald-100 text-emerald-800 font-bold px-2.5 py-0.5 rounded-full border border-emerald-200">Pelanggan Aktif</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-slate-500 flex items-center gap-3 mt-1 flex-wrap">
                                <span class="inline-flex items-center gap-1"><?= npglow_icon('mail', 'w-3.5 h-3.5 text-slate-400') ?> <?= htmlspecialchars($selectedCustomer['email']) ?></span>
                                <span class="inline-flex items-center gap-1"><?= npglow_icon('calendar', 'w-3.5 h-3.5 text-slate-400') ?> Bergabung: <?= date('d M Y', strtotime($selectedCustomer['created_at'])) ?></span>
                                <?php if ($photoCount > 0): ?>
                                    <span class="inline-flex items-center gap-1 font-semibold text-purple-700 bg-purple-50 px-2 py-0.5 rounded border border-purple-200/60">
                                        <?= npglow_icon('sparkles', 'w-3.5 h-3.5 text-purple-600') ?> Perawatan: Hari ke-<?= $journeyDays ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <!-- Direct Actions -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="index.php?target_user_id=<?= $selectedCustomer['id'] ?>" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-xl text-xs sm:text-sm font-bold shadow-md shadow-blue-200 transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                            Buka Chat Konsultasi
                        </a>
                        <a href="#diagnosis-section" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition flex items-center gap-2">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            Tulis Catatan Tim Ahli
                        </a>
                    </div>
                </div>
            </div>

            <!-- Content Body -->
            <div class="p-4 sm:p-6 max-w-7xl mx-auto w-full space-y-6">
                <?php if ($photoCount === 0): ?>
                    <!-- No photos state -->
                    <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-sm text-center">
                        <div class="w-16 h-16 rounded-2xl bg-amber-50 text-amber-500 mx-auto flex items-center justify-center mb-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                        <h3 class="text-base font-bold text-gray-800 mb-1">Customer Belum Memiliki Foto Wajah</h3>
                        <p class="text-xs text-slate-500 max-w-md mx-auto mb-4">Customer ini belum mengunggah foto awal atau foto progress perawatan kulit.</p>
                        <a href="index.php?target_user_id=<?= $selectedCustomer['id'] ?>" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl text-xs font-bold shadow hover:bg-primary-dark transition">
                            Ingatkan Customer Lewat Chat
                        </a>
                    </div>
                <?php else: ?>

                    <!-- 1. Interactive Before-After Split Slider Card -->
                    <div class="bg-white rounded-3xl p-5 sm:p-7 border border-slate-200 shadow-sm">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
                            <div>
                                <h3 class="text-base font-extrabold text-gray-900 flex items-center gap-2">
                                    <span class="w-7 h-7 rounded-lg bg-blue-100 text-primary flex items-center justify-center">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                    </span>
                                    Interactive Before & After Comparison
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5">Geser handle putih di tengah gambar untuk membandingkan perubahan kulit customer secara presisi.</p>
                            </div>

                            <!-- Selector for Before & After photos -->
                            <div class="flex items-center gap-2 flex-wrap">
                                <div class="flex items-center gap-1.5 text-xs bg-slate-50 p-1.5 rounded-xl border border-slate-200">
                                    <span class="font-bold text-slate-600 pl-1">Before:</span>
                                    <select id="select-before" class="bg-white border border-slate-200 rounded-lg px-2 py-1 text-xs font-semibold text-gray-700 focus:outline-none focus:ring-1 focus:ring-primary" onchange="updateComparison()">
                                        <?php foreach ($customerPhotos as $idx => $p): ?>
                                            <option value="../<?= htmlspecialchars($p['photo_path']) ?>" data-date="<?= date('d M Y', strtotime($p['taken_at'])) ?>" data-type="<?= $p['photo_type'] ?>" <?= ($idx === 0) ? 'selected' : '' ?>>
                                                <?= ($p['photo_type'] === 'initial' ? 'Foto Awal' : 'Progress') ?> - <?= date('d/m/Y', strtotime($p['taken_at'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="flex items-center gap-1.5 text-xs bg-slate-50 p-1.5 rounded-xl border border-slate-200">
                                    <span class="font-bold text-slate-600 pl-1">After:</span>
                                    <select id="select-after" class="bg-white border border-slate-200 rounded-lg px-2 py-1 text-xs font-semibold text-gray-700 focus:outline-none focus:ring-1 focus:ring-primary" onchange="updateComparison()">
                                        <?php foreach (array_reverse($customerPhotos) as $idx => $p): ?>
                                            <option value="../<?= htmlspecialchars($p['photo_path']) ?>" data-date="<?= date('d M Y', strtotime($p['taken_at'])) ?>" data-type="<?= $p['photo_type'] ?>" <?= ($idx === 0) ? 'selected' : '' ?>>
                                                <?= ($p['photo_type'] === 'initial' ? 'Foto Awal' : 'Progress') ?> - <?= date('d/m/Y', strtotime($p['taken_at'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Split Comparison Visual Area -->
                        <div class="relative w-full aspect-[4/3] sm:aspect-[16/9] max-h-[500px] comparison-container shadow-inner border border-slate-200" id="comparison-box">
                            <!-- After Image (Background) -->
                            <img id="img-after" src="../<?= htmlspecialchars($latestPhoto['photo_path']) ?>" alt="After Photo" class="comparison-image">
                            <div class="absolute top-4 right-4 z-10 bg-black/60 backdrop-blur-md text-white text-xs font-extrabold px-3 py-1.5 rounded-full border border-white/20 shadow">
                                AFTER: <span id="label-after-date"><?= date('d M Y', strtotime($latestPhoto['taken_at'])) ?></span>
                            </div>

                            <!-- Before Image (Clipped Overlay) -->
                            <div class="comparison-before" id="comparison-before-wrap">
                                <img id="img-before" src="../<?= htmlspecialchars($initialPhoto['photo_path']) ?>" alt="Before Photo" class="comparison-image" style="width: 100%; height: 100%;">
                                <div class="absolute top-4 left-4 z-10 bg-primary/90 backdrop-blur-md text-white text-xs font-extrabold px-3 py-1.5 rounded-full border border-white/20 shadow">
                                    BEFORE: <span id="label-before-date"><?= date('d M Y', strtotime($initialPhoto['taken_at'])) ?></span>
                                </div>
                            </div>

                            <!-- Draggable Split Handle -->
                            <div class="comparison-handle" id="comparison-handle">
                                <div class="comparison-handle-btn">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Chronological Photo Gallery Timeline -->
                    <div class="bg-white rounded-3xl p-5 sm:p-7 border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <h3 class="text-base font-extrabold text-gray-900 flex items-center gap-2">
                                    <span class="w-7 h-7 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </span>
                                    Timeline Foto Wajah Customer (<?= $photoCount ?> Foto)
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5">Semua foto otomatis tersimpan dalam format WebP berkualitas tinggi & terkompresi hemat kuota.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <?php foreach ($customerPhotos as $index => $photo): 
                                $isInitial = ($photo['photo_type'] === 'initial');
                            ?>
                            <div class="bg-slate-50 rounded-2xl p-3 border border-slate-200 hover:border-primary/40 hover:shadow-md transition duration-200 flex flex-col group">
                                <div class="relative w-full aspect-square rounded-xl overflow-hidden bg-slate-200 mb-2.5 cursor-pointer" onclick="openLightbox('../<?= htmlspecialchars($photo['photo_path']) ?>', '<?= date('d M Y', strtotime($photo['taken_at'])) ?>', '<?= addslashes(htmlspecialchars($photo['notes'] ?? '')) ?>')">
                                    <img src="../<?= htmlspecialchars($photo['photo_path']) ?>" alt="Foto" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                    <div class="absolute top-2 left-2 shadow-sm">
                                        <?= npglow_photo_badge($photo['photo_type'], $index) ?>
                                    </div>
                                    <span class="absolute bottom-2 right-2 text-[10px] bg-black/60 backdrop-blur-sm text-white px-2 py-0.5 rounded font-mono">
                                        <?= date('d M Y', strtotime($photo['taken_at'])) ?>
                                    </span>
                                </div>

                                <div class="flex-1 flex flex-col justify-between">
                                    <?php if (!empty($photo['notes'])): ?>
                                        <p class="text-[11px] text-slate-600 italic bg-white p-2 rounded-lg border border-slate-200 mb-2 line-clamp-2">
                                            "<?= htmlspecialchars($photo['notes']) ?>"
                                        </p>
                                    <?php else: ?>
                                        <p class="text-[11px] text-slate-400 italic mb-2">Tidak ada catatan.</p>
                                    <?php endif; ?>

                                    <div class="flex items-center gap-1.5 pt-2 border-t border-slate-200 text-[10px]">
                                        <button onclick="setComparePhoto('before', '../<?= htmlspecialchars($photo['photo_path']) ?>', '<?= date('d M Y', strtotime($photo['taken_at'])) ?>')" class="flex-1 bg-white hover:bg-blue-50 text-primary border border-primary/30 py-1 px-1.5 rounded font-bold text-center transition">
                                            Set Before
                                        </button>
                                        <button onclick="setComparePhoto('after', '../<?= htmlspecialchars($photo['photo_path']) ?>', '<?= date('d M Y', strtotime($photo['taken_at'])) ?>')" class="flex-1 bg-white hover:bg-purple-50 text-purple-700 border border-purple-300 py-1 px-1.5 rounded font-bold text-center transition">
                                            Set After
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 3. Consultation History & Expert Diagnosis Form -->
                    <div id="diagnosis-section" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        <!-- Left: Form Tambah Diagnosa -->
                        <div class="lg:col-span-5 bg-white rounded-3xl p-5 sm:p-7 border border-slate-200 shadow-sm">
                            <h3 class="text-base font-extrabold text-gray-900 flex items-center gap-2 mb-1">
                                <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </span>
                                Catat Hasil Diagnosa / Konsultasi
                            </h3>
                            <p class="text-xs text-slate-500 mb-4">Catatan ini akan tersimpan pada histori profil customer dan dapat dipantau oleh admin & tim ahli.</p>

                            <form id="consultation-form" class="space-y-3.5">
                                <input type="hidden" name="user_id" value="<?= $selectedCustomer['id'] ?>">

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Konsultasi</label>
                                    <input type="date" name="consultation_date" value="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary font-medium">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Hubungkan dengan Foto (opsional)</label>
                                    <select name="face_photo_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary font-medium">
                                        <option value="">-- Tanpa Hubungan Foto Spesifik --</option>
                                        <?php foreach ($customerPhotos as $p): ?>
                                            <option value="<?= $p['id'] ?>">
                                                <?= ($p['photo_type'] === 'initial' ? 'Foto Awal' : 'Progress') ?> (<?= date('d M Y', strtotime($p['taken_at'])) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Kondisi Kulit / Diagnosa Singkat</label>
                                    <input type="text" name="skin_condition" placeholder="Cth: Kulit berminyak, Acne ringan di pipi, bekas jerawat PIH" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Ringkasan Evaluasi Perkembangan</label>
                                    <textarea name="summary" rows="3" placeholder="Tuliskan catatan evaluasi perkembangan kulit customer..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none"></textarea>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Rekomendasi Produk & Treatment</label>
                                    <textarea name="recommendation" rows="2" placeholder="Cth: Gunakan NPGLOW Acne Treatment Cream di malam hari, perbanyak hidrasi..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none"></textarea>
                                </div>

                                <button type="submit" id="save-log-btn" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-4 rounded-xl text-xs shadow-md transition flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                                    Simpan Catatan Diagnosa
                                </button>
                            </form>
                        </div>

                        <!-- Right: List Riwayat Konsultasi Terdahulu -->
                        <div class="lg:col-span-7 bg-white rounded-3xl p-5 sm:p-7 border border-slate-200 shadow-sm flex flex-col">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-base font-extrabold text-gray-900 flex items-center gap-2">
                                    <span class="w-7 h-7 rounded-lg bg-blue-100 text-primary flex items-center justify-center">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </span>
                                    Riwayat Konsultasi & Catatan Medis (<?= count($consultationLogs) ?>)
                                </h3>
                            </div>

                            <div class="space-y-3 flex-1 overflow-y-auto max-h-[520px] pr-1" id="logs-container">
                                <?php if (empty($consultationLogs)): ?>
                                    <div class="p-8 text-center text-slate-400 text-xs border border-dashed border-slate-200 rounded-2xl bg-slate-50">
                                        Belum ada catatan konsultasi untuk customer ini. Gunakan form di sebelah kiri untuk menambahkan catatan baru.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($consultationLogs as $log): ?>
                                    <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200 hover:border-slate-300 transition">
                                        <div class="flex items-start justify-between gap-2 mb-2">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center gap-1 text-xs font-bold text-primary bg-blue-50 px-2.5 py-0.5 rounded-full border border-blue-200">
                                                    <?= npglow_icon('calendar', 'w-3 h-3 text-primary') ?> <?= date('d M Y', strtotime($log['consultation_date'])) ?>
                                                </span>
                                                <span class="text-[11px] text-slate-500 font-medium">Oleh: <?= htmlspecialchars($log['expert_name'] ?? 'Tim Ahli') ?></span>
                                            </div>
                                            <?php if (!empty($log['photo_path'])): ?>
                                                <span class="text-[10px] text-purple-700 bg-purple-50 px-2 py-0.5 rounded font-semibold flex items-center gap-1 border border-purple-200/50">
                                                    <?= npglow_icon('camera', 'w-3 h-3 text-purple-600') ?> Terhubung Foto
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($log['skin_condition'])): ?>
                                            <div class="text-xs font-bold text-gray-800 mb-1">
                                                Kondisi Kulit: <span class="text-slate-600 font-normal"><?= htmlspecialchars($log['skin_condition']) ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($log['summary'])): ?>
                                            <p class="text-xs text-slate-700 bg-white p-3 rounded-xl border border-slate-200 mb-2 leading-relaxed">
                                                <?= nl2br(htmlspecialchars($log['summary'])) ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if (!empty($log['recommendation'])): ?>
                                            <div class="text-[11px] bg-emerald-50 text-emerald-800 p-2.5 rounded-xl border border-emerald-200 font-medium flex items-start gap-1.5">
                                                <span class="font-bold flex items-center gap-1"><?= npglow_icon('lightbulb', 'w-3.5 h-3.5 text-emerald-600') ?> Rekomendasi:</span>
                                                <span><?= htmlspecialchars($log['recommendation']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>
            </div>

            <?php else: ?>
                <!-- Empty state when no customer is selected -->
                <div class="flex-1 flex flex-col items-center justify-center p-8 text-center">
                    <div class="w-20 h-20 rounded-3xl bg-blue-100 text-primary flex items-center justify-center mb-4 shadow-sm">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <h2 class="text-xl font-extrabold text-gray-800 mb-1">Bank Data Foto & Histori Konsultasi</h2>
                    <p class="text-sm text-slate-500 max-w-md mb-6">Pilih customer di panel sebelah kiri untuk melihat seluruh riwayat foto wajah, evolusi perawatan dengan interactive Before-After slider, dan catatan konsultasi.</p>
                </div>
            <?php endif; ?>
        </div>
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

    <!-- JavaScript Engine for Before-After Slider & Consultation Form -->
    <script>
        // Customer Search Filter
        const searchInput = document.getElementById('search-customer');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                document.querySelectorAll('.customer-item').forEach(item => {
                    const name = item.dataset.name || '';
                    const email = item.dataset.email || '';
                    if (name.includes(query) || email.includes(query)) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }

        // Before-After Slider Interaction
        const compBox = document.getElementById('comparison-box');
        const beforeWrap = document.getElementById('comparison-before-wrap');
        const handle = document.getElementById('comparison-handle');
        const imgBefore = document.getElementById('img-before');
        const imgAfter = document.getElementById('img-after');

        if (compBox && handle && beforeWrap && imgBefore) {
            let isDragging = false;

            function syncImageSizes() {
                if (compBox && imgBefore) {
                    imgBefore.style.width = compBox.offsetWidth + 'px';
                    imgBefore.style.height = compBox.offsetHeight + 'px';
                }
            }

            window.addEventListener('resize', syncImageSizes);
            syncImageSizes();

            function setPosition(xPos) {
                const rect = compBox.getBoundingClientRect();
                let offsetX = xPos - rect.left;
                if (offsetX < 0) offsetX = 0;
                if (offsetX > rect.width) offsetX = rect.width;

                const percentage = (offsetX / rect.width) * 100;
                beforeWrap.style.width = percentage + '%';
                handle.style.left = percentage + '%';
            }

            // Mouse Events
            compBox.addEventListener('mousedown', (e) => {
                isDragging = true;
                setPosition(e.clientX);
            });
            window.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                setPosition(e.clientX);
            });
            window.addEventListener('mouseup', () => { isDragging = false; });

            // Touch Events
            compBox.addEventListener('touchstart', (e) => {
                isDragging = true;
                if (e.touches.length > 0) setPosition(e.touches[0].clientX);
            });
            window.addEventListener('touchmove', (e) => {
                if (!isDragging) return;
                if (e.touches.length > 0) setPosition(e.touches[0].clientX);
            });
            window.addEventListener('touchend', () => { isDragging = false; });
        }

        // Update Comparison from Dropdowns
        function updateComparison() {
            const selBefore = document.getElementById('select-before');
            const selAfter = document.getElementById('select-after');
            if (selBefore && selAfter) {
                const beforeOpt = selBefore.options[selBefore.selectedIndex];
                const afterOpt = selAfter.options[selAfter.selectedIndex];

                document.getElementById('img-before').src = beforeOpt.value;
                document.getElementById('label-before-date').textContent = beforeOpt.dataset.date;

                document.getElementById('img-after').src = afterOpt.value;
                document.getElementById('label-after-date').textContent = afterOpt.dataset.date;

                if (imgBefore && compBox) {
                    imgBefore.style.width = compBox.offsetWidth + 'px';
                    imgBefore.style.height = compBox.offsetHeight + 'px';
                }
            }
        }

        function setComparePhoto(position, src, date) {
            if (position === 'before') {
                const sel = document.getElementById('select-before');
                for (let i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].value === src) {
                        sel.selectedIndex = i;
                        break;
                    }
                }
            } else {
                const sel = document.getElementById('select-after');
                for (let i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].value === src) {
                        sel.selectedIndex = i;
                        break;
                    }
                }
            }
            updateComparison();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Lightbox Functions
        function openLightbox(src, date, notes) {
            document.getElementById('lightbox-img').src = src;
            document.getElementById('lightbox-date').textContent = date;
            const notesEl = document.getElementById('lightbox-notes');
            const wrapEl = document.getElementById('lightbox-notes-wrap');
            if (notes && notes.trim() !== '') {
                notesEl.textContent = 'Catatan: ' + notes;
                wrapEl.classList.remove('hidden');
            } else {
                wrapEl.classList.add('hidden');
            }
            document.getElementById('lightbox-modal').classList.remove('hidden');
        }

        function closeLightbox() {
            document.getElementById('lightbox-modal').classList.add('hidden');
        }

        // Consultation Form Submit
        const consultForm = document.getElementById('consultation-form');
        if (consultForm) {
            consultForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = document.getElementById('save-log-btn');
                btn.disabled = true;
                btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Menyimpan...';

                const formData = new FormData(this);
                try {
                    const res = await fetch('../api/consultation.php?action=save_log', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil Disimpan!',
                            text: 'Catatan diagnosa konsultasi telah tercatat.',
                            confirmButtonColor: '#3ca6f2'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Menyimpan',
                            text: data.error || 'Terjadi kesalahan.',
                            confirmButtonColor: '#3ca6f2'
                        });
                        btn.disabled = false;
                        btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg> Simpan Catatan Diagnosa';
                    }
                } catch (err) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Gagal terhubung ke server.',
                        confirmButtonColor: '#3ca6f2'
                    });
                    btn.disabled = false;
                    btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg> Simpan Catatan Diagnosa';
                }
            });
        }
    </script>
</body>
</html>
