<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth-helper.php';
require_once 'includes/reseller-helper.php';
require_once 'includes/icon-helper.php';

// Customer only guard
guard_buyer_only();

$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? '';

// Search query
$searchQuery = trim($_GET['q'] ?? '');

// Get referred store if any
$currentStore = get_user_reseller_store($conn, $userId);

$products = [];

if ($currentStore) {
    // Fetch products belonging to this reseller store
    $sql = "
        SELECT p.*, rp.custom_price, rp.stock as reseller_stock,
               COALESCE(rp.custom_price, p.price) as effective_price,
               COUNT(o.id) as sold_count
        FROM reseller_products rp
        JOIN products p ON rp.product_id = p.id
        LEFT JOIN orders o ON p.id = o.product_id AND o.reseller_store_id = ? AND o.status = 'completed'
        WHERE rp.reseller_store_id = ? AND rp.is_available = 1
    ";
    if (!empty($searchQuery)) {
        $escaped = $conn->real_escape_string($searchQuery);
        $sql .= " AND (p.name LIKE '%{$escaped}%' OR p.description LIKE '%{$escaped}%')";
    }
    $sql .= " GROUP BY p.id, rp.id ORDER BY rp.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $currentStore['id'], $currentStore['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['price'] = $row['effective_price']; // override with custom price
        $products[] = $row;
    }
} else {
    // Fetch from Official Store
    $prodSql = "
        SELECT p.*, COUNT(o.id) as sold_count 
        FROM products p 
        LEFT JOIN orders o ON p.id = o.product_id AND o.status = 'completed'
    ";
    if (!empty($searchQuery)) {
        $escaped = $conn->real_escape_string($searchQuery);
        $prodSql .= " WHERE p.name LIKE '%{$escaped}%' OR p.description LIKE '%{$escaped}%'";
    }
    $prodSql .= " GROUP BY p.id ORDER BY p.id DESC";
    $prodQuery = $conn->query($prodSql);
    if ($prodQuery) {
        while ($row = $prodQuery->fetch_assoc()) {
            $products[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Belanja - NPGLOW <?= $currentStore ? htmlspecialchars($currentStore['store_name']) : 'Official Store' ?></title>
    <meta name="description" content="Belanja produk skincare NPGLOW original, terjamin BPOM.">
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
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-slate-800 antialiased min-h-screen pb-20">

    <!-- Sticky Header -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-lg border-b border-gray-200/80 shadow-sm">
        <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-3">
            <!-- Logo / Brand -->
            <a href="dashboard.php" class="flex items-center gap-2 flex-shrink-0">
                <img src="assets/images/logo_np_glow.jpeg" alt="NPGLOW" class="w-8 h-8 rounded-full shadow-sm">
                <span class="font-extrabold text-base text-gray-900 tracking-tight hidden sm:inline">NPGLOW</span>
            </a>
            
            <!-- Search Bar -->
            <form method="GET" class="flex-1 relative">
                <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" 
                       placeholder="Cari produk skincare..." 
                       class="w-full pl-9 pr-8 py-2 bg-gray-100 hover:bg-gray-50 focus:bg-white rounded-xl text-sm text-gray-800 placeholder-gray-400 border border-transparent focus:border-primary focus:outline-none transition">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <?php if (!empty($searchQuery)): ?>
                    <a href="shop.php" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-sm font-bold">&times;</a>
                <?php endif; ?>
            </form>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-3 sm:px-4 py-4 sm:py-6">
        
        <!-- Store Banner / Referral Indicator -->
        <div class="mb-5">
            <?php if ($currentStore): ?>
            <div class="bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200/80 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500 text-white flex items-center justify-center font-black text-lg flex-shrink-0 shadow-md shadow-emerald-500/20">
                        <?php if (!empty($currentStore['store_logo'])): ?>
                        <img src="<?= htmlspecialchars($currentStore['store_logo']) ?>" alt="" class="w-full h-full object-cover rounded-xl">
                        <?php else: ?>
                        <?= strtoupper(substr($currentStore['store_name'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-full">Mitra Resmi</span>
                            <span class="text-xs font-mono font-bold text-emerald-700"><?= htmlspecialchars($currentStore['referral_code']) ?></span>
                        </div>
                        <h2 class="text-sm sm:text-base font-extrabold text-gray-800 truncate"><?= htmlspecialchars($currentStore['store_name']) ?></h2>
                        <p class="text-xs text-gray-500">📍 <?= htmlspecialchars($currentStore['city'] ?: 'Indonesia') ?> <?php if ($currentStore['whatsapp']): ?> • WA: <?= htmlspecialchars($currentStore['whatsapp']) ?><?php endif; ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-2 self-end sm:self-center">
                    <a href="find-reseller.php" class="px-3.5 py-1.5 bg-white hover:bg-emerald-50 text-emerald-700 text-xs font-bold rounded-xl border border-emerald-200 transition shadow-sm whitespace-nowrap">
                        Ganti Toko 📍
                    </a>
                    <button onclick="revertToOfficial()" class="px-3 py-1.5 bg-white/70 hover:bg-white text-gray-500 hover:text-gray-700 text-xs font-semibold rounded-xl border border-gray-200 transition whitespace-nowrap" title="Beralih ke Toko Resmi Pusat">
                        Ke Pusat
                    </button>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200/80 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-primary text-white flex items-center justify-center flex-shrink-0 shadow-md shadow-blue-500/20">
                        <?= npglow_icon('shop-bag', 'w-5 h-5') ?>
                    </div>
                    <div>
                        <h2 class="text-sm sm:text-base font-extrabold text-gray-800">NPGLOW Official Store (Pusat)</h2>
                        <p class="text-xs text-gray-500">Mau ongkir lebih hemat & pengiriman lebih cepat?</p>
                    </div>
                </div>
                <a href="find-reseller.php" class="inline-flex items-center gap-1.5 px-4 py-2 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-xl transition shadow-sm shadow-blue-500/20 whitespace-nowrap self-start sm:self-center">
                    📍 Cari Toko Terdekat
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Page Title & Count -->
        <div class="mb-4 sm:mb-6">
            <?php if (!empty($searchQuery)): ?>
                <p class="text-sm text-gray-500">
                    Hasil pencarian untuk "<span class="font-semibold text-gray-900"><?= htmlspecialchars($searchQuery) ?></span>" 
                    <span class="text-gray-400">(<?= count($products) ?> produk)</span>
                </p>
            <?php else: ?>
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-lg sm:text-xl font-extrabold text-gray-900 tracking-tight">
                            <?= $currentStore ? 'Katalog Toko Mitra' : 'Katalog Produk NPGLOW' ?>
                        </h1>
                        <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Pilih rangkaian skincare terbaik untuk kulitmu</p>
                    </div>
                    <span class="text-xs text-gray-400 font-medium"><?= count($products) ?> produk</span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($products)): ?>
            <!-- Empty State -->
            <div class="bg-white rounded-3xl p-10 text-center border border-gray-200 shadow-sm">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-blue-50 text-primary flex items-center justify-center">
                    <?= npglow_icon('shop-bag', 'w-8 h-8 text-primary') ?>
                </div>
                <h3 class="text-base font-bold text-gray-800 mb-1">Produk Tidak Ditemukan</h3>
                <p class="text-sm text-gray-500 mb-4">
                    <?= $currentStore ? 'Toko mitra ini belum menambahkan produk atau produk sedang habis.' : 'Coba gunakan kata kunci lain untuk mencari produk.' ?>
                </p>
                <div class="flex items-center justify-center gap-3">
                    <?php if ($currentStore): ?>
                    <button onclick="revertToOfficial()" class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-xl hover:bg-primary-dark transition">
                        Belanja dari Official Store Pusat
                    </button>
                    <?php else: ?>
                    <a href="shop.php" class="inline-flex items-center gap-1.5 text-primary text-sm font-semibold hover:underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        Lihat Semua Produk
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Product Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-5">
                <?php foreach ($products as $product): ?>
                <div class="bg-white rounded-[1.2rem] sm:rounded-[1.5rem] shadow-[0_4px_16px_rgb(0,0,0,0.05)] hover:shadow-[0_12px_32px_rgb(0,0,0,0.1)] transition-all duration-300 overflow-hidden group flex flex-col h-full">
                    
                    <!-- Image Section -->
                    <div class="relative bg-gradient-to-br from-blue-50 to-blue-200 aspect-square flex items-center justify-center p-3 sm:p-5 overflow-hidden">
                        <?php if (!empty($product['image_url'])): ?>
                            <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110 drop-shadow-lg">
                        <?php else: ?>
                            <div class="w-16 h-16 bg-white/50 backdrop-blur-sm rounded-full flex items-center justify-center text-white font-bold text-xs shadow-sm z-10">
                                Product
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Content Section -->
                    <div class="p-3 sm:p-4 flex flex-col flex-1 bg-white relative z-10 -mt-3 sm:-mt-4 rounded-t-[1.2rem] sm:rounded-t-[1.5rem]">
                        <h3 class="text-[12px] sm:text-sm font-bold text-gray-800 mb-1 tracking-tight group-hover:text-primary transition-colors leading-snug line-clamp-2"><?= htmlspecialchars($product['name']) ?></h3>
                        
                        <!-- Rating & Terjual -->
                        <div class="flex items-center gap-1.5 mb-1.5">
                            <div class="flex items-center text-yellow-400">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                                <span class="text-[10px] font-bold text-gray-700 ml-0.5">4.9</span>
                            </div>
                            <span class="w-0.5 h-0.5 bg-gray-300 rounded-full"></span>
                            <span class="text-[9px] sm:text-[10px] text-gray-500"><?= $product['sold_count'] > 0 ? $product['sold_count'] . ' Terjual' : 'Baru' ?></span>
                        </div>

                        <!-- Badges -->
                        <div class="flex flex-wrap gap-1 mb-2">
                            <span class="px-1.5 py-0.5 border border-gray-200 rounded text-[8px] font-bold text-gray-500 uppercase tracking-widest">ORIGINAL</span>
                            <span class="px-1.5 py-0.5 border border-gray-200 rounded text-[8px] font-bold text-gray-500 uppercase tracking-widest">BPOM</span>
                        </div>
                        
                        <p class="text-[10px] sm:text-[11px] text-gray-500 font-medium mb-3 line-clamp-2 leading-relaxed flex-1"><?= htmlspecialchars($product['description']) ?></p>
                        
                        <!-- Bottom Action Row -->
                        <div class="flex flex-row items-end justify-between mt-auto gap-2">
                            <div>
                                <span class="block text-[8px] font-bold text-gray-400 uppercase tracking-widest mb-0.5">HARGA</span>
                                <span class="text-[12px] sm:text-[14px] font-black text-gray-800 tracking-tight leading-none">Rp <?= number_format($product['price'], 0, ',', '.') ?></span>
                            </div>
                            <a href="checkout.php?product_id=<?= $product['id'] ?>"
                                class="bg-primary hover:bg-blue-600 text-white px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg sm:rounded-xl font-bold text-[10px] sm:text-xs transition-colors shadow-sm hover:shadow-md whitespace-nowrap">
                                Beli
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function revertToOfficial() {
            Swal.fire({
                title: 'Beralih ke Toko Pusat?',
                text: 'Katalog akan menampilkan produk dari Official Store Pusat.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Beralih',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#3ca6f2'
            }).then(res => {
                if (res.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'clear_referral');
                    fetch('api/referral.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            }
                        });
                }
            });
        }
    </script>

    <?php 
    $bottomNavActive = 'belanja';
    include 'includes/bottom-nav.php'; 
    ?>

<?php include 'includes/pwa-sw.php'; ?>
</body>
</html>
