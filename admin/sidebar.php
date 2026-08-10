<?php
// Shared Admin Sidebar Component
if (!isset($activeNav)) {
    $currentScript = basename($_SERVER['PHP_SELF']);
    $activeNav = match($currentScript) {
        'orders.php' => 'orders',
        'payments.php' => 'payments',
        'products.php' => 'products',
        'photos.php' => 'photos',
        'resellers.php' => 'resellers',
        default => 'dashboard'
    };
}

// Count waiting verification orders if DB connection is available
$pendingVerificationCount = 0;
if (isset($conn) && $conn instanceof mysqli) {
    $cntRes = $conn->query("SELECT COUNT(*) as total FROM orders WHERE payment_status = 'waiting_verification'");
    if ($cntRes) {
        $pendingVerificationCount = (int)($cntRes->fetch_assoc()['total'] ?? 0);
    }
}
?>

<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.2);
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(148, 163, 184, 0.4);
    }
</style>

<!-- Mobile Sidebar Backdrop Overlay -->
<div id="sidebarBackdrop" onclick="toggleAdminSidebar()" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-40 lg:hidden hidden transition-opacity duration-300"></div>

<!-- Admin Sidebar -->
<aside id="adminSidebar" class="fixed lg:sticky top-0 left-0 h-screen w-64 lg:w-72 bg-slate-900 text-slate-300 flex flex-col z-50 transition-transform duration-300 -translate-x-full lg:translate-x-0 border-r border-slate-800 shadow-2xl lg:shadow-none flex-shrink-0">
    <!-- Brand / Header -->
    <div class="p-5 border-b border-slate-800/80 flex items-center justify-between">
        <a href="index.php" class="flex items-center gap-3 group">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-[#3ca6f2] to-blue-500 flex items-center justify-center shadow-lg shadow-blue-500/20 group-hover:scale-105 transition-transform">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
            </div>
            <div>
                <h1 class="font-black text-lg text-white tracking-tight flex items-center gap-1.5 leading-none">
                    NPGLOW <span class="text-xs px-2 py-0.5 rounded-md bg-[#3ca6f2]/20 text-[#3ca6f2] font-bold">Admin</span>
                </h1>
                <p class="text-[11px] text-slate-400 font-medium mt-1">Management Portal</p>
            </div>
        </a>
        <button onclick="toggleAdminSidebar()" class="lg:hidden text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>

    <!-- Navigation Links -->
    <div class="flex-1 overflow-y-auto px-4 py-5 space-y-6 custom-scrollbar">
        <!-- Section: Menu Utama -->
        <div>
            <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Menu Utama</p>
            <nav class="space-y-1">
                <!-- Dashboard -->
                <a href="index.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeNav === 'dashboard' ? 'bg-[#3ca6f2] text-white shadow-lg shadow-blue-500/20' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                    <svg class="w-5 h-5 <?= $activeNav === 'dashboard' ? 'text-white' : 'text-blue-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    <span>Dashboard</span>
                </a>

                <!-- Pesanan Masuk -->
                <a href="orders.php" class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeNav === 'orders' ? 'bg-[#3ca6f2] text-white shadow-lg shadow-blue-500/20' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 <?= $activeNav === 'orders' ? 'text-white' : 'text-amber-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        <span>Pesanan Masuk</span>
                    </div>
                    <?php if ($pendingVerificationCount > 0): ?>
                        <span class="px-2 py-0.5 bg-amber-500 text-white font-extrabold text-[10px] rounded-full animate-pulse shadow-sm"><?= $pendingVerificationCount ?> Baru</span>
                    <?php endif; ?>
                </a>

                <!-- Metode Pembayaran -->
                <a href="payments.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeNav === 'payments' ? 'bg-[#3ca6f2] text-white shadow-lg shadow-blue-500/20' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                    <svg class="w-5 h-5 <?= $activeNav === 'payments' ? 'text-white' : 'text-emerald-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    <span>Metode Pembayaran</span>
                </a>
                
                <!-- Laporan -->
                <a href="reports.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeNav === 'reports' ? 'bg-[#3ca6f2] text-white shadow-lg shadow-blue-500/20' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                    <svg class="w-5 h-5 <?= $activeNav === 'reports' ? 'text-white' : 'text-pink-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    <span>Laporan</span>
                </a>
            </nav>
        </div>

        <!-- Section: Katalog & Media -->
        <div>
            <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Katalog & Data</p>
            <nav class="space-y-1">
                <!-- Katalog Produk -->
                <a href="products.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeNav === 'products' ? 'bg-[#3ca6f2] text-white shadow-lg shadow-blue-500/20' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                    <svg class="w-5 h-5 <?= $activeNav === 'products' ? 'text-white' : 'text-indigo-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    <span>Katalog Produk</span>
                </a>

                <!-- Bank Data Foto -->
                <a href="photos.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeNav === 'photos' ? 'bg-[#3ca6f2] text-white shadow-lg shadow-blue-500/20' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                    <svg class="w-5 h-5 <?= $activeNav === 'photos' ? 'text-white' : 'text-purple-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span>Bank Data Foto</span>
                </a>

                <!-- Kelola Reseller -->
                <a href="resellers.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeNav === 'resellers' ? 'bg-[#3ca6f2] text-white shadow-lg shadow-blue-500/20' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                    <svg class="w-5 h-5 <?= $activeNav === 'resellers' ? 'text-white' : 'text-teal-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    <span>Kelola Reseller</span>
                </a>
            </nav>
        </div>

        <!-- Section: Navigasi Eksternal -->
        <div>
            <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Portal Terkait</p>
            <nav class="space-y-1">
                <!-- Dashboard Tim Ahli -->
                <a href="../expert/index.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-800/80 transition">
                    <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    <span>Dashboard Tim Ahli</span>
                </a>
            </nav>
        </div>
    </div>

    <!-- User Profile & Logout Footer -->
    <div class="p-4 border-t border-slate-800 bg-slate-950/40">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-slate-800 border border-slate-700 text-slate-200 flex items-center justify-center font-bold text-sm flex-shrink-0">
                    A
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-bold text-white truncate">Administrator</p>
                    <p class="text-[10px] text-emerald-400 font-semibold flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Online
                    </p>
                </div>
            </div>
            <a href="../logout.php" class="p-2 rounded-xl text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition" title="Logout dari Sistem">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            </a>
        </div>
    </div>
</aside>

<script>
    function toggleAdminSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar && backdrop) {
            const isHidden = sidebar.classList.contains('-translate-x-full');
            if (isHidden) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            }
        }
    }
</script>
