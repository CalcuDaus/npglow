<?php
// Reseller Topbar Component
if (!isset($pageTitle)) {
    $currentScript = basename($_SERVER['PHP_SELF']);
    $pageTitle = match($currentScript) {
        'products.php' => 'Produk Saya',
        'orders.php' => 'Pesanan Masuk',
        'store-settings.php' => 'Pengaturan Toko',
        default => 'Dashboard'
    };
}
?>
<header class="bg-white border-b border-gray-200/80 sticky top-0 z-30 px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-3">
        <button onclick="toggleResellerSidebar()" class="lg:hidden p-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition border border-gray-200 focus:outline-none">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </button>
        <div class="flex items-center gap-2 text-xs sm:text-sm">
            <span class="text-slate-400 font-medium hidden sm:inline">Reseller</span>
            <span class="text-slate-300 hidden sm:inline">/</span>
            <h1 class="font-bold text-slate-800 tracking-tight text-sm sm:text-base"><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
    </div>
    <div class="flex items-center gap-2 sm:gap-3">
        <div class="flex items-center gap-2 px-3 py-1.5 bg-slate-50 border border-slate-200/60 rounded-xl text-xs font-medium text-slate-500">
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span><?= date('d M Y') ?></span>
        </div>
    </div>
</header>
