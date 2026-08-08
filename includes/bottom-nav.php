<?php
/**
 * NPGLOW Shared Bottom Navigation Bar (Shopee-Style)
 * Include this component at the bottom of every customer-facing page.
 */

// Ensure icon helper is loaded
require_once __DIR__ . '/icon-helper.php';

// Only show for logged-in customers (not admin/expert)
if (!isset($_SESSION['user_id'])) return;
$_bnRole = $_SESSION['role'] ?? 'customer';
if ($_bnRole === 'admin' || $_bnRole === 'expert') return;

// Determine active tab from variable set by parent page
$_bnActive = $bottomNavActive ?? '';

// Count unpaid orders for badge
$_bnUnpaidCount = 0;
if (isset($conn) && isset($_SESSION['user_id'])) {
    $_bnRes = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id = " . (int)$_SESSION['user_id'] . " AND order_status = 'unpaid' AND payment_status != 'rejected'");
    if ($_bnRes) {
        $_bnUnpaidCount = (int)($_bnRes->fetch_assoc()['c'] ?? 0);
    }
}

// Determine base path (for pages in subdirectories)
$_bnBasePath = '';
if (strpos($_SERVER['SCRIPT_NAME'], '/expert/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) {
    $_bnBasePath = '../';
}

// Navigation items
$_bnItems = [
    [
        'key'          => 'beranda',
        'label'        => 'Beranda',
        'href'         => $_bnBasePath . 'dashboard.php',
        'icon'         => 'home',
        'icon_active'  => 'home-filled',
        'color'        => 'text-primary',
    ],
    [
        'key'          => 'belanja',
        'label'        => 'Belanja',
        'href'         => $_bnBasePath . 'shop.php',
        'icon'         => 'shop-bag',
        'icon_active'  => 'shop-bag-filled',
        'color'        => 'text-primary',
    ],
    [
        'key'          => 'pesanan',
        'label'        => 'Pesanan',
        'href'         => $_bnBasePath . 'my-orders.php?status=all',
        'icon'         => 'clipboard',
        'icon_active'  => 'clipboard-filled',
        'color'        => 'text-primary',
        'badge'        => $_bnUnpaidCount,
    ],
    [
        'key'          => 'konsultasi',
        'label'        => 'Konsultasi',
        'href'         => $_bnBasePath . 'konsultasi.php',
        'icon'         => 'chat',
        'icon_active'  => 'chat-filled',
        'color'        => 'text-primary',
    ],
    [
        'key'          => 'profil',
        'label'        => 'Profil',
        'href'         => $_bnBasePath . 'profile.php',
        'icon'         => 'user',
        'icon_active'  => 'user-filled',
        'color'        => 'text-primary',
    ],
];
?>

<!-- Bottom Navigation Bar (Mobile Only - Shopee Style) -->
<nav class="sm:hidden fixed bottom-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-lg border-t border-gray-200/80 shadow-[0_-2px_12px_rgba(0,0,0,0.06)]" style="padding-bottom: env(safe-area-inset-bottom, 0px);" id="npglow-bottom-nav">
    <div class="flex items-stretch justify-around max-w-lg mx-auto h-[56px]">
        <?php foreach ($_bnItems as $_item): ?>
            <?php
                $_isActive = ($_bnActive === $_item['key']);
                $_iconName = $_isActive ? $_item['icon_active'] : $_item['icon'];
                $_badgeCount = $_item['badge'] ?? 0;
            ?>
            <a href="<?= htmlspecialchars($_item['href']) ?>" 
               class="relative flex flex-col items-center justify-center flex-1 gap-0.5 transition-all duration-200 <?= $_isActive ? $_item['color'] : 'text-gray-400' ?>"
               <?= $_isActive ? 'aria-current="page"' : '' ?>>
                
                <?php if ($_isActive): ?>
                    <span class="absolute top-0 left-1/2 -translate-x-1/2 w-8 h-0.5 rounded-full bg-primary"></span>
                <?php endif; ?>

                <span class="relative">
                    <?= npglow_icon($_iconName, 'w-[22px] h-[22px]') ?>
                    
                    <?php if ($_badgeCount > 0): ?>
                        <span class="absolute -top-1.5 -right-2.5 min-w-[16px] h-4 px-1 flex items-center justify-center bg-red-500 text-white text-[9px] font-bold rounded-full shadow-sm leading-none">
                            <?= $_badgeCount > 99 ? '99+' : $_badgeCount ?>
                        </span>
                    <?php endif; ?>
                </span>

                <span class="text-[10px] leading-tight <?= $_isActive ? 'font-bold' : 'font-medium' ?>">
                    <?= htmlspecialchars($_item['label']) ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
