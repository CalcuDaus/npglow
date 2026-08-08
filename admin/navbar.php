<?php
// Shared Admin Navigation Bar
if (!isset($activeNav)) {
    $currentScript = basename($_SERVER['PHP_SELF']);
    $activeNav = match($currentScript) {
        'orders.php' => 'orders',
        'payments.php' => 'payments',
        'products.php' => 'products',
        'photos.php' => 'photos',
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
<header class="bg-slate-800 text-white p-3 sm:p-4 shadow-md z-30 sticky top-0 border-b border-slate-700/60">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
        <!-- Logo & Title -->
        <div class="flex items-center gap-6 lg:gap-8">
            <a href="index.php" class="font-extrabold text-lg sm:text-xl flex items-center gap-2.5 text-white tracking-tight hover:opacity-90 transition">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-[#3ca6f2] to-blue-400 flex items-center justify-center shadow-sm">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                </div>
                <span>NPGLOW <span class="text-[#3ca6f2] font-semibold text-sm sm:text-base">Admin</span></span>
            </a>

            <!-- Desktop Menu Links -->
            <nav class="hidden lg:flex items-center gap-1.5">
                <a href="index.php" class="<?= $activeNav === 'dashboard' ? 'bg-white/15 text-white shadow-sm font-semibold' : 'text-slate-300 hover:text-white hover:bg-white/10 font-medium' ?> px-3.5 py-2 rounded-xl text-xs sm:text-sm transition-all flex items-center gap-1.5">
                    <span>Dashboard</span>
                </a>
                
                <a href="orders.php" class="<?= $activeNav === 'orders' ? 'bg-white/15 text-white shadow-sm font-semibold' : 'text-slate-300 hover:text-white hover:bg-white/10 font-medium' ?> px-3.5 py-2 rounded-xl text-xs sm:text-sm transition-all flex items-center gap-1.5 relative">
                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                    <span>Pesanan Masuk</span>
                    <?php if ($pendingVerificationCount > 0): ?>
                        <span class="ml-1 px-1.5 py-0.2 bg-amber-500 text-white font-extrabold text-[10px] rounded-full animate-pulse"><?= $pendingVerificationCount ?></span>
                    <?php endif; ?>
                </a>

                <a href="payments.php" class="<?= $activeNav === 'payments' ? 'bg-white/15 text-white shadow-sm font-semibold' : 'text-slate-300 hover:text-white hover:bg-white/10 font-medium' ?> px-3.5 py-2 rounded-xl text-xs sm:text-sm transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    <span>Metode Pembayaran</span>
                </a>

                <a href="products.php" class="<?= $activeNav === 'products' ? 'bg-white/15 text-white shadow-sm font-semibold' : 'text-slate-300 hover:text-white hover:bg-white/10 font-medium' ?> px-3.5 py-2 rounded-xl text-xs sm:text-sm transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    <span>Katalog Produk</span>
                </a>

                <a href="photos.php" class="<?= $activeNav === 'photos' ? 'bg-white/15 text-white shadow-sm font-semibold' : 'text-slate-300 hover:text-white hover:bg-white/10 font-medium' ?> px-3.5 py-2 rounded-xl text-xs sm:text-sm transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span>Bank Data Foto</span>
                </a>

                <a href="../expert/index.php" class="text-slate-300 hover:text-white hover:bg-white/10 font-medium px-3.5 py-2 rounded-xl text-xs sm:text-sm transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    <span>Dashboard Tim Ahli</span>
                </a>
            </nav>
        </div>

        <!-- Right Side User & Navigation -->
        <div class="flex items-center gap-2 sm:gap-3">
            <a href="../index.php" class="bg-slate-700/80 hover:bg-slate-600 border border-slate-600/60 text-slate-200 hover:text-white px-3.5 py-1.5 sm:py-2 rounded-full font-medium text-xs transition flex items-center gap-1.5 shadow-sm" title="Buka Website Pengunjung">
                <svg class="w-3.5 h-3.5 text-[#3ca6f2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                <span class="hidden sm:inline">Web Utama</span>
            </a>

            <span class="text-xs font-semibold bg-slate-700/90 text-slate-300 px-3 py-1.5 rounded-full border border-slate-600/40 hidden md:inline-block">Admin System</span>

            <a href="../logout.php" class="bg-red-500/10 hover:bg-red-500/20 text-red-400 hover:text-red-300 border border-red-500/20 px-3.5 py-1.5 sm:py-2 rounded-full font-semibold text-xs transition">Logout</a>

            <!-- Mobile Menu Toggle Button -->
            <button onclick="document.getElementById('mobile-admin-menu').classList.toggle('hidden')" class="lg:hidden p-2 rounded-xl bg-slate-700 text-slate-200 hover:text-white hover:bg-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
            </button>
        </div>
    </div>

    <!-- Mobile Dropdown Navigation -->
    <div id="mobile-admin-menu" class="hidden lg:hidden border-t border-slate-700/80 mt-3 pt-3 space-y-1.5">
        <a href="index.php" class="<?= $activeNav === 'dashboard' ? 'bg-white/15 text-white font-semibold' : 'text-slate-300 hover:bg-white/10' ?> block px-4 py-2 rounded-xl text-sm transition">Dashboard</a>
        <a href="orders.php" class="<?= $activeNav === 'orders' ? 'bg-white/15 text-white font-semibold' : 'text-slate-300 hover:bg-white/10' ?> flex items-center justify-between px-4 py-2 rounded-xl text-sm transition">
            <span>Pesanan Masuk</span>
            <?php if ($pendingVerificationCount > 0): ?>
                <span class="px-2 py-0.5 bg-amber-500 text-white font-extrabold text-xs rounded-full"><?= $pendingVerificationCount ?> Baru</span>
            <?php endif; ?>
        </a>
        <a href="payments.php" class="<?= $activeNav === 'payments' ? 'bg-white/15 text-white font-semibold' : 'text-slate-300 hover:bg-white/10' ?> block px-4 py-2 rounded-xl text-sm transition">Metode Pembayaran (QRIS & Bank)</a>
        <a href="products.php" class="<?= $activeNav === 'products' ? 'bg-white/15 text-white font-semibold' : 'text-slate-300 hover:bg-white/10' ?> block px-4 py-2 rounded-xl text-sm transition">Katalog Produk</a>
        <a href="photos.php" class="<?= $activeNav === 'photos' ? 'bg-white/15 text-white font-semibold' : 'text-slate-300 hover:bg-white/10' ?> block px-4 py-2 rounded-xl text-sm transition">Bank Data Foto</a>
        <a href="../expert/index.php" class="text-slate-300 hover:bg-white/10 block px-4 py-2 rounded-xl text-sm transition">Dashboard Tim Ahli</a>
        <a href="../index.php" class="text-[#3ca6f2] hover:bg-white/10 block px-4 py-2 rounded-xl text-sm font-semibold transition">← Kunjungi Web Utama</a>
    </div>
</header>
