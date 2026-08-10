<?php
// Reseller Sidebar Component
require_once __DIR__ . '/../includes/icon-helper.php';

if (!isset($activeNav)) {
    $currentScript = basename($_SERVER['PHP_SELF']);
    $activeNav = match($currentScript) {
        'products.php' => 'products',
        'orders.php' => 'orders',
        'store-settings.php' => 'settings',
        default => 'dashboard'
    };
}

// Get store info
$_sidebarStore = null;
if (isset($conn) && isset($_SESSION['user_id'])) {
    $stStmt = $conn->prepare("SELECT store_name, referral_code FROM reseller_stores WHERE user_id = ? LIMIT 1");
    $stStmt->bind_param("i", $_SESSION['user_id']);
    $stStmt->execute();
    $_sidebarStore = $stStmt->get_result()->fetch_assoc();
}
$_storeName = $_sidebarStore['store_name'] ?? 'Toko Saya';
$_referralCode = $_sidebarStore['referral_code'] ?? '-';
$_userName = $_SESSION['user_name'] ?? 'Reseller';
?>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148,163,184,0.2); border-radius: 4px; }
</style>

<!-- Mobile Sidebar Backdrop -->
<div id="sidebarBackdrop" onclick="toggleResellerSidebar()" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-40 lg:hidden hidden transition-opacity duration-300"></div>

<!-- Reseller Sidebar -->
<aside id="resellerSidebar" class="fixed lg:sticky top-0 left-0 h-screen w-64 lg:w-72 bg-slate-900 text-slate-300 flex flex-col z-50 transition-transform duration-300 -translate-x-full lg:translate-x-0 border-r border-slate-800 shadow-2xl lg:shadow-none flex-shrink-0">
    <!-- Brand Header -->
    <div class="p-5 border-b border-slate-800/80 flex items-center justify-between">
        <a href="index.php" class="flex items-center gap-3 group">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center shadow-lg shadow-emerald-500/20 group-hover:scale-105 transition-transform">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"></path></svg>
            </div>
            <div>
                <h1 class="font-black text-lg text-white tracking-tight flex items-center gap-1.5 leading-none">
                    NPGLOW <span class="text-xs px-2 py-0.5 rounded-md bg-emerald-500/20 text-emerald-400 font-bold">Reseller</span>
                </h1>
                <p class="text-[11px] text-slate-400 font-medium mt-1"><?= htmlspecialchars($_storeName) ?></p>
            </div>
        </a>
        <button onclick="toggleResellerSidebar()" class="lg:hidden text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>

    <!-- Referral Code Badge -->
    <div class="px-5 py-3 border-b border-slate-800/50">
        <div class="bg-slate-800/70 rounded-xl px-3.5 py-2.5 flex items-center justify-between">
            <div>
                <p class="text-[10px] text-slate-500 font-semibold uppercase tracking-wider">Kode Referral</p>
                <p class="text-sm font-mono font-bold text-emerald-400 mt-0.5"><?= htmlspecialchars($_referralCode) ?></p>
            </div>
            <button onclick="copyReferral('<?= htmlspecialchars($_referralCode) ?>')" class="p-1.5 rounded-lg text-slate-400 hover:text-emerald-400 hover:bg-slate-700 transition" title="Salin Kode">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
            </button>
        </div>
    </div>

    <!-- Navigation -->
    <div class="flex-1 overflow-y-auto px-4 py-5 space-y-6 custom-scrollbar">
        <div>
            <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Menu Utama</p>
            <nav class="space-y-1">
                <a href="index.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeNav === 'dashboard' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                    <svg class="w-5 h-5 <?= $activeNav === 'dashboard' ? 'text-white' : 'text-emerald-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    <span>Dashboard</span>
                </a>

                <a href="products.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeNav === 'products' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                    <svg class="w-5 h-5 <?= $activeNav === 'products' ? 'text-white' : 'text-indigo-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    <span>Produk Saya</span>
                </a>

                <a href="orders.php" class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeNav === 'orders' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 <?= $activeNav === 'orders' ? 'text-white' : 'text-amber-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        <span>Pesanan Masuk</span>
                    </div>
                    <span id="sidebar-order-badge" class="hidden bg-rose-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm min-w-[20px] text-center transition-all duration-300">0</span>
                </a>
            </nav>
        </div>

        <div>
            <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Pengaturan</p>
            <nav class="space-y-1">
                <a href="store-settings.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeNav === 'settings' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                    <svg class="w-5 h-5 <?= $activeNav === 'settings' ? 'text-white' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    <span>Pengaturan Toko</span>
                </a>
            </nav>
        </div>
    </div>

    <!-- User Footer -->
    <div class="p-4 border-t border-slate-800 bg-slate-950/40">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-emerald-800 border border-emerald-700 text-emerald-200 flex items-center justify-center font-bold text-sm flex-shrink-0">
                    R
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-bold text-white truncate"><?= htmlspecialchars($_userName) ?></p>
                    <p class="text-[10px] text-emerald-400 font-semibold">Reseller</p>
                </div>
            </div>
            <a href="../logout.php" class="p-2 rounded-xl text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition" title="Logout">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            </a>
        </div>
    </div>
</aside>

<script>
    function toggleResellerSidebar() {
        const sidebar = document.getElementById('resellerSidebar');
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
    function copyReferral(code) {
        navigator.clipboard.writeText(code).then(() => {
            const btn = event.currentTarget;
            btn.innerHTML = '<svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
            setTimeout(() => {
                btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>';
            }, 2000);
        });
    }

    // Realtime badge updates for active orders
    async function updateOrderBadge() {
        try {
            const r = await fetch('../api/reseller-orders-count.php');
            const data = await r.json();
            const badge = document.getElementById('sidebar-order-badge');
            if (data.success && data.count > 0 && badge) {
                badge.textContent = data.count;
                badge.classList.remove('hidden');
                
                // Add a small scale bump for new orders
                const prevCount = parseInt(badge.dataset.count || '0');
                if (data.count > prevCount && prevCount > 0) {
                    badge.classList.add('scale-125');
                    setTimeout(() => badge.classList.remove('scale-125'), 300);
                }
                badge.dataset.count = data.count;
            } else if (badge) {
                badge.classList.add('hidden');
                badge.dataset.count = '0';
            }
        } catch(e) {}
    }
    
    // Check every 10 seconds
    setInterval(updateOrderBadge, 10000);
    // Initial check on load
    document.addEventListener('DOMContentLoaded', updateOrderBadge);
</script>
