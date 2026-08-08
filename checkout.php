<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth-helper.php';
require_once 'includes/image-helper.php';
require_once 'includes/shipping-helper.php';
require_once 'includes/settings-helper.php';
require_once 'includes/order-tracking-helper.php';
require_once 'includes/icon-helper.php';

// Strictly forbid Admin and Expert from purchasing
guard_buyer_only();

$userId = (int)$_SESSION['user_id'];
$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($productId <= 0) {
    // If no product specified, default to first product or redirect
    $firstProd = $conn->query("SELECT id FROM products LIMIT 1")->fetch_assoc();
    if ($firstProd) {
        $productId = (int)$firstProd['id'];
    } else {
        header("Location: index.php");
        exit();
    }
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch product details
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header("Location: index.php");
    exit();
}

// Check if user has initial face photo
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM user_face_photos WHERE user_id = ? AND photo_type = 'initial'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$hasInitialPhoto = $stmt->get_result()->fetch_assoc()['total'] > 0;

// Fetch active bank accounts & QRIS Statis
$banksResult = $conn->query("SELECT * FROM payment_bank_accounts WHERE is_active = 1 ORDER BY id ASC");
$bankAccounts = $banksResult ? $banksResult->fetch_all(MYSQLI_ASSOC) : [];

$qrisResult = $conn->query("SELECT * FROM payment_qris_accounts WHERE is_active = 1 ORDER BY is_primary DESC, id ASC LIMIT 1");
$activeQris = $qrisResult ? $qrisResult->fetch_assoc() : null;
$qrisActive = ($activeQris !== null);

// Location & Couriers list
$locationData = NPGLOW_Shipping::get_location_data();
$availableCouriers = NPGLOW_Shipping::get_available_couriers();

// Default values from user profile
$defaultName = $user['name'] ?? '';
$defaultPhone = $user['phone'] ?? '';
$defaultProvince = $user['province'] ?? 'DKI Jakarta';
$defaultCity = $user['city'] ?? 'Jakarta Barat';
$defaultDistrict = $user['district'] ?? '';
$defaultPostal = $user['postal_code'] ?? '';
$defaultAddress = $user['address'] ?? '';

$productPrice = (float)$product['price'];
$originalPrice = $productPrice * 1.25; // 25% original strikethrough price

// Initial shipping calculation
$defaultCourier = 'jnt';
$defaultService = 'EZ';
$shippingRate = NPGLOW_Shipping::calculate_rate($conn, $defaultProvince, $defaultCity, $defaultCourier, $defaultService, $productPrice, 350);

$error = '';

// Handle Checkout Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipientName = trim($_POST['recipient_name'] ?? '');
    $recipientPhone = trim($_POST['recipient_phone'] ?? '');
    $province = trim($_POST['province'] ?? 'DKI Jakarta');
    $city = trim($_POST['city'] ?? 'Jakarta Barat');
    $district = trim($_POST['district'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $fullAddress = trim($_POST['full_address'] ?? '');
    $customerNote = trim($_POST['customer_note'] ?? '');
    
    $courierCode = trim($_POST['courier_code'] ?? 'jnt');
    $serviceCode = trim($_POST['service_code'] ?? 'EZ');
    $paymentMethod = trim($_POST['payment_method'] ?? 'qris');
    $bankId = isset($_POST['payment_bank_id']) && $paymentMethod === 'bank_transfer' ? (int)$_POST['payment_bank_id'] : null;

    // Role & Buyer Validation
    if (!can_buy_products()) {
        $error = 'Akun Admin atau Tim Ahli tidak dapat melakukan pembelian produk.';
    }

    // Validation
    if (empty($error) && (empty($recipientName) || empty($recipientPhone) || empty($fullAddress))) {
        $error = 'Harap lengkapi nama penerima, nomor telepon, dan alamat pengiriman.';
    }

    // Handle Face Photo (for first-time buyers)
    $photoSaved = false;
    $needsPhoto = !$hasInitialPhoto;

    if (empty($error)) {
        if ($needsPhoto) {
            if (isset($_FILES['face_photo']) && $_FILES['face_photo']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['face_photo'];
                $userDir = "uploads/faces/{$userId}";
                if (!is_dir($userDir)) {
                    mkdir($userDir, 0755, true);
                }
                
                $filename = generate_unique_webp_filename('initial');
                $filepath = "{$userDir}/{$filename}";
                $convertResult = convert_image_to_webp($file['tmp_name'], $filepath, 82, 1600, 1600);
                
                if ($convertResult['success']) {
                    $today = date('Y-m-d');
                    $skinNotes = trim($_POST['skin_notes'] ?? '');
                    $stmt = $conn->prepare("INSERT INTO user_face_photos (user_id, photo_path, photo_type, notes, taken_at) VALUES (?, ?, 'initial', ?, ?)");
                    $stmt->bind_param("isss", $userId, $filepath, $skinNotes, $today);
                    $stmt->execute();
                    
                    // Update user profile photo
                    $stmt = $conn->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                    $stmt->bind_param("si", $filepath, $userId);
                    $stmt->execute();
                    $photoSaved = true;
                } else {
                    $error = 'Gagal memproses foto wajah: ' . ($convertResult['error'] ?? 'Format tidak valid');
                }
            } else {
                $error = 'Foto kondisi kulit wajah wajib diunggah untuk pembelian pertama.';
            }
        } else {
            $photoSaved = true;
        }
    }

    if (empty($error) && $photoSaved) {
        // Auto-save user profile address for future checkouts
        $updateUser = $conn->prepare("UPDATE users SET phone = ?, province = ?, city = ?, district = ?, postal_code = ?, address = ? WHERE id = ?");
        $updateUser->bind_param("ssssssi", $recipientPhone, $province, $city, $district, $postalCode, $fullAddress, $userId);
        $updateUser->execute();

        // Calculate accurate server-side shipping cost
        $finalShipping = NPGLOW_Shipping::calculate_rate($conn, $province, $city, $courierCode, $serviceCode, $productPrice, 350);
        $shippingCost = $finalShipping['final_cost'];
        $discountAmount = $finalShipping['discount_ongkir'];
        $totalAmount = $productPrice + $shippingCost;

        // Generate unique order number: NP-YYYYMMDD-RAND
        $orderNumber = 'NP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
        $courierName = $finalShipping['courier_name'];
        $serviceName = $finalShipping['service_name'];

        // Insert order
        $insertOrder = $conn->prepare("INSERT INTO orders (
            order_number, user_id, product_id, recipient_name, recipient_phone,
            shipping_province, shipping_city, shipping_district, shipping_postal_code,
            shipping_address, shipping_courier, shipping_service, shipping_cost,
            product_price, discount_amount, total_amount, payment_method,
            payment_bank_id, payment_status, customer_note
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");

        $insertOrder->bind_param(
            "siisssssssssddddsis",
            $orderNumber, $userId, $productId, $recipientName, $recipientPhone,
            $province, $city, $district, $postalCode,
            $fullAddress, $courierName, $serviceName, $shippingCost,
            $productPrice, $discountAmount, $totalAmount, $paymentMethod,
            $bankId, $customerNote
        );

        if ($insertOrder->execute()) {
            $newOrderId = $conn->insert_id;
            // Add initial timeline event
            add_order_tracking_log($conn, $newOrderId, 'unpaid', 'Pesanan Dibuat', 'Pesanan telah berhasil dibuat. Menunggu pembayaran dari customer.', 'Sistem NPGLOW');
            header("Location: payment.php?order_id={$newOrderId}");
            exit();
        } else {
            $error = 'Gagal membuat pesanan: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Checkout - NPGLOW Official</title>
    <?php include 'includes/pwa-head.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3ca6f2',
                        'primary-dark': '#2e8ccf',
                        'primary-light': '#eff6ff',
                        brand: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#3ca6f2',
                            600: '#2563eb',
                            700: '#1d4ed8'
                        }
                    },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            -webkit-tap-highlight-color: transparent;
        }
        .two-tone-icon {
            color: #3ca6f2;
            fill: rgba(60, 166, 242, 0.15);
        }
        .two-tone-orange {
            color: #f97316;
            fill: rgba(249, 115, 22, 0.15);
        }
        .two-tone-emerald {
            color: #10b981;
            fill: rgba(16, 185, 129, 0.15);
        }
        .photo-drop-zone {
            border: 2px dashed #cbd5e1;
            transition: all 0.25s ease;
        }
        .photo-drop-zone:hover, .photo-drop-zone.active {
            border-color: #3ca6f2;
            background: rgba(60,166,242,0.04);
        }
        .photo-drop-zone.has-image {
            border-color: #10b981;
            border-style: solid;
            background: #f0fdf4;
        }
    </style>
</head>
<body class="min-h-screen pb-28">

    <!-- Mobile Header -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-100 shadow-[0_2px_8px_rgba(0,0,0,0.03)]">
        <div class="max-w-md mx-auto px-4 h-14 flex items-center justify-between">
            <a href="index.php" class="p-2 -ml-2 text-slate-600 hover:text-primary transition rounded-full">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
            </a>
            <h1 class="text-base font-bold text-slate-800 tracking-tight">Checkout</h1>
            <div class="w-8"></div>
        </div>
    </header>

    <main class="max-w-md mx-auto px-3.5 pt-3.5 space-y-3">

        <!-- Error Notification -->
        <?php if (!empty($error)): ?>
            <div class="bg-red-50/90 border border-red-200 text-red-700 p-3.5 rounded-2xl text-xs flex items-start gap-2.5 shadow-sm">
                <svg class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span class="leading-relaxed"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="checkoutForm">

            <!-- 1. Alamat Pengiriman Card -->
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100/80 cursor-pointer hover:border-blue-200 transition" onclick="openAddressModal()">
                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-xl bg-orange-50/90 text-orange-500 flex-shrink-0 mt-0.5">
                        <!-- Two Tone Location Icon -->
                        <svg class="w-5 h-5 two-tone-orange" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wide">Alamat Pengiriman</h2>
                            <span class="text-xs text-primary font-semibold flex items-center gap-0.5">
                                Ubah
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                            </span>
                        </div>
                        
                        <div class="mt-1.5" id="addressDisplay">
                            <?php if (!empty($defaultAddress)): ?>
                                <p class="text-sm font-bold text-slate-900 leading-tight">
                                    <span id="dispName"><?= htmlspecialchars($defaultName) ?></span>
                                    <span class="text-xs font-normal text-slate-500 ml-1" id="dispPhone"><?= htmlspecialchars($defaultPhone) ?></span>
                                </p>
                                <p class="text-xs text-slate-600 mt-1 leading-relaxed line-clamp-2" id="dispFull">
                                    <?= htmlspecialchars($defaultAddress) ?>, <?= htmlspecialchars($defaultDistrict ?: '') ?> <?= htmlspecialchars($defaultCity) ?>, <?= htmlspecialchars($defaultProvince) ?> <?= htmlspecialchars($defaultPostal) ?>
                                </p>
                            <?php else: ?>
                                <p class="text-xs text-amber-600 font-medium py-0.5 flex items-center gap-1.5">
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-md bg-amber-100 text-amber-600 flex-shrink-0">
                                        <?= npglow_icon('warning', 'w-3.5 h-3.5') ?>
                                    </span>
                                    <span>Belum ada alamat. Ketuk di sini untuk mengisi alamat pengiriman.</span>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hidden Address Inputs (controlled by modal) -->
            <input type="hidden" name="recipient_name" id="inp_recipient_name" value="<?= htmlspecialchars($defaultName) ?>">
            <input type="hidden" name="recipient_phone" id="inp_recipient_phone" value="<?= htmlspecialchars($defaultPhone) ?>">
            <input type="hidden" name="province" id="inp_province" value="<?= htmlspecialchars($defaultProvince) ?>">
            <input type="hidden" name="city" id="inp_city" value="<?= htmlspecialchars($defaultCity) ?>">
            <input type="hidden" name="district" id="inp_district" value="<?= htmlspecialchars($defaultDistrict) ?>">
            <input type="hidden" name="postal_code" id="inp_postal_code" value="<?= htmlspecialchars($defaultPostal) ?>">
            <input type="hidden" name="full_address" id="inp_full_address" value="<?= htmlspecialchars($defaultAddress) ?>">

            <!-- 2. Kartu Toko & Produk -->
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100/80 space-y-3.5">
                <!-- Shop Header -->
                <div class="flex items-center gap-2 pb-2.5 border-b border-slate-100">
                    <span class="bg-gradient-to-r from-red-500 to-orange-500 text-white text-[10px] font-extrabold px-1.5 py-0.5 rounded">
                        Star+
                    </span>
                    <span class="font-bold text-xs text-slate-800">NPGLOW Official Store</span>
                    <svg class="w-3.5 h-3.5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>

                <!-- Product Details -->
                <div class="flex items-center gap-3">
                    <div class="w-20 h-20 rounded-xl bg-slate-50 border border-slate-100 overflow-hidden flex-shrink-0">
                        <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-xs font-semibold text-slate-800 line-clamp-2 leading-snug"><?= htmlspecialchars($product['name']) ?></h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Paket Skincare & Journal Telehealth</p>
                        
                        <div class="flex items-baseline justify-between mt-2">
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-sm font-extrabold text-slate-900">Rp <?= number_format($productPrice, 0, ',', '.') ?></span>
                                <span class="text-[11px] text-slate-400 line-through">Rp <?= number_format($originalPrice, 0, ',', '.') ?></span>
                            </div>
                            <span class="text-xs text-slate-500 font-medium">x1</span>
                        </div>
                    </div>
                </div>

                <!-- Voucher & Promo Bar -->
                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="font-medium text-slate-700 flex items-center gap-1.5">
                        <svg class="w-4 h-4 two-tone-orange" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z"/>
                        </svg>
                        Voucher Toko
                    </span>
                    <span class="text-[11px] text-emerald-600 font-semibold bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                        Diskon Spesial Aktif
                    </span>
                </div>

                <!-- Note for Seller -->
                <div class="pt-2 border-t border-slate-100 flex items-center gap-2">
                    <span class="text-xs font-medium text-slate-700 flex-shrink-0">Pesan:</span>
                    <input type="text" name="customer_note" placeholder="Tinggalkan pesan ke penjual..." class="w-full text-xs text-right text-slate-700 focus:outline-none placeholder:text-slate-400">
                </div>
            </div>

            <!-- 3. Opsi Pengiriman Card -->
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100/80 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
                        <svg class="w-4 h-4 two-tone-icon" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.676a2.056 2.056 0 00-.86 1.58V14.25m0 0h3.75"/>
                        </svg>
                        Opsi Pengiriman
                    </h3>
                    <span class="text-[11px] text-slate-400 font-medium" id="originNote">Dari: Jakarta Barat</span>
                </div>

                <!-- Courier Selector Dropdown/Cards -->
                <div class="space-y-2">
                    <label class="block text-[11px] font-semibold text-slate-500">Pilih Jasa Ekspedisi & Layanan</label>
                    <select id="courierSelect" name="courier_select" onchange="onCourierChange()" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="jnt_EZ" selected>J&T Express - Reguler (1 - 3 Hari)</option>
                        <option value="jnt_SUPER">J&T Super - Kilat (1 - 2 Hari)</option>
                        <option value="jne_REG">JNE Express - Reguler (2 - 3 Hari)</option>
                        <option value="jne_YES">JNE YES - Yakin Esok Sampai (1 Hari)</option>
                        <option value="sicepat_SIUNT">SiCepat Ekspres - Reguler (1 - 3 Hari)</option>
                        <option value="sicepat_GOKIL">SiCepat Kargo - Hemat (3 - 6 Hari)</option>
                    </select>
                    <input type="hidden" name="courier_code" id="inp_courier_code" value="jnt">
                    <input type="hidden" name="service_code" id="inp_service_code" value="EZ">
                </div>

                <!-- Selected Shipping Highlight Box (Shopee-Style) -->
                <div class="bg-emerald-50/70 border border-emerald-200 rounded-xl p-3 relative" id="shippingHighlight">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow-xs">
                                <?= npglow_icon('check', 'w-3 h-3 text-white') ?>
                            </span>
                            <span class="text-xs font-bold text-slate-800" id="dispEtd">Estimasi Tiba: <?= $shippingRate['etd'] ?></span>
                        </div>
                        <div class="text-right">
                            <span class="text-[11px] text-slate-400 line-through mr-1" id="dispRawCost"><?= $shippingRate['formatted_raw_cost'] ?></span>
                            <span class="text-xs font-extrabold text-emerald-700" id="dispFinalCost">
                                <?= $shippingRate['is_free_shipping'] ? 'Rp 0' : $shippingRate['formatted_final_cost'] ?>
                            </span>
                        </div>
                    </div>
                    <p class="text-[11px] text-emerald-700 font-semibold mt-1.5 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        <span id="dispFreeBadge">Kamu mendapatkan subsidi gratis ongkir!</span>
                    </p>
                </div>
            </div>

            <!-- 4. Pilihan Metode Pembayaran Card -->
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100/80 space-y-3">
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
                    <svg class="w-4 h-4 two-tone-icon" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>
                    </svg>
                    Metode Pembayaran
                </h3>

                <div class="grid grid-cols-1 gap-2.5">
                    <!-- QRIS Option -->
                    <label class="payment-option relative flex items-center justify-between p-3.5 rounded-xl border-2 cursor-pointer transition border-primary bg-blue-50/50" id="opt_qris">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="payment_method" value="qris" checked onchange="onPaymentMethodChange('qris')" class="text-primary focus:ring-primary h-4 w-4">
                            <div>
                                <span class="font-bold text-xs text-slate-900 block">QRIS (Semua Bank & E-Wallet)</span>
                                <span class="text-[11px] text-slate-500">BCA, Mandiri, BRI, Gopay, OVO, ShopeePay, Dana</span>
                            </div>
                        </div>
                        <span class="text-[10px] font-bold bg-primary text-white px-2 py-0.5 rounded-md">Instan</span>
                    </label>

                    <!-- Bank Transfer Option -->
                    <label class="payment-option relative flex items-center justify-between p-3.5 rounded-xl border border-slate-200 cursor-pointer transition hover:border-slate-300" id="opt_bank">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="payment_method" value="bank_transfer" onchange="onPaymentMethodChange('bank_transfer')" class="text-primary focus:ring-primary h-4 w-4">
                            <div>
                                <span class="font-bold text-xs text-slate-900 block">Transfer Bank Manual</span>
                                <span class="text-[11px] text-slate-500">BCA, Mandiri, BRI, BNI</span>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                    </label>
                </div>

                <!-- Bank Selection Sub-Menu (hidden unless bank_transfer is selected) -->
                <div id="bankSelectorContainer" class="hidden pt-2 border-t border-slate-100 space-y-2">
                    <label class="block text-[11px] font-semibold text-slate-600">Pilih Rekening Bank Tujuan:</label>
                    <select name="payment_bank_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <?php foreach ($bankAccounts as $bank): ?>
                            <option value="<?= $bank['id'] ?>">
                                Bank <?= htmlspecialchars($bank['bank_name']) ?> - <?= htmlspecialchars($bank['account_number']) ?> (a.n <?= htmlspecialchars($bank['account_holder']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- 5. Upload Foto Kondisi Kulit (Khusus Pembelian Pertama) -->
            <?php if (!$hasInitialPhoto): ?>
                <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100/80 space-y-3">
                    <div class="flex items-center gap-2">
                        <div class="p-1.5 rounded-lg bg-purple-50 text-purple-600 flex-shrink-0">
                            <svg class="w-4 h-4 two-tone-icon" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wide">Foto Wajah Awal <span class="text-red-500">*</span></h3>
                            <p class="text-[11px] text-slate-400">Diperlukan sekali untuk titik awal Skincare Journal & AI Anda</p>
                        </div>
                    </div>

                    <label for="face_photo" class="photo-drop-zone block rounded-2xl p-4 cursor-pointer text-center" id="dropZone">
                        <div id="uploadPlaceholder">
                            <div class="w-10 h-10 mx-auto rounded-full bg-slate-100 flex items-center justify-center mb-1.5 text-slate-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            </div>
                            <p class="text-xs font-semibold text-slate-700">Ambil / Upload Foto Wajah</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">Kamera / Galeri • Otomatis WebP</p>
                        </div>
                        <div id="uploadPreview" class="hidden">
                            <img id="previewImg" class="w-24 h-24 mx-auto rounded-xl object-cover shadow-sm mb-1.5" alt="Preview Wajah">
                            <p class="text-[11px] text-emerald-600 font-bold flex items-center justify-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Foto Wajah Siap
                            </p>
                            <span id="compressBadge" class="inline-block text-[10px] font-medium bg-emerald-100/70 text-emerald-800 px-2 py-0.5 rounded-full mt-1"></span>
                        </div>
                        <input type="file" name="face_photo" id="face_photo" accept="image/*" class="hidden" required>
                    </label>

                    <div>
                        <input type="text" name="skin_notes" placeholder="Catatan kondisi kulit saat ini (opsional: berminyak, jerawat...)" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary/20">
                    </div>
                </div>
            <?php endif; ?>

            <!-- 6. Rincian Pembayaran Card -->
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100/80 space-y-2.5 text-xs text-slate-600">
                <h3 class="font-bold text-slate-800 uppercase tracking-wide text-xs mb-2">Rincian Pembayaran</h3>
                
                <div class="flex justify-between">
                    <span>Subtotal Produk</span>
                    <span class="font-semibold text-slate-800">Rp <?= number_format($productPrice, 0, ',', '.') ?></span>
                </div>
                <div class="flex justify-between">
                    <span>Biaya Pengiriman</span>
                    <span class="font-semibold text-slate-800" id="summaryRawShipping"><?= $shippingRate['formatted_raw_cost'] ?></span>
                </div>
                <div class="flex justify-between text-emerald-600">
                    <span>Diskon Pengiriman</span>
                    <span class="font-semibold" id="summaryDiscountShipping">-Rp <?= number_format($shippingRate['discount_ongkir'], 0, ',', '.') ?></span>
                </div>
                <div class="pt-2 border-t border-slate-100 flex justify-between items-center text-sm font-bold text-slate-900">
                    <span>Total Pembayaran</span>
                    <span class="text-base text-primary font-extrabold" id="summaryGrandTotal">Rp <?= number_format($productPrice + $shippingRate['final_cost'], 0, ',', '.') ?></span>
                </div>
            </div>

            <!-- Sticky Bottom Bar (Shopee-Style) -->
            <div class="fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200/80 shadow-[0_-4px_12px_rgba(0,0,0,0.05)]">
                <div class="max-w-md mx-auto px-4 py-2.5 flex items-center justify-between gap-3">
                    <div class="text-right flex-1 pr-2">
                        <div class="text-[11px] text-slate-500">Total Pembayaran</div>
                        <div class="text-base font-black text-orange-600 tracking-tight" id="bottomGrandTotal">
                            Rp <?= number_format($productPrice + $shippingRate['final_cost'], 0, ',', '.') ?>
                        </div>
                        <div class="text-[10px] text-emerald-600 font-semibold" id="bottomSavings">
                            Hemat Rp <?= number_format(($originalPrice - $productPrice) + $shippingRate['discount_ongkir'], 0, ',', '.') ?>
                        </div>
                    </div>
                    <button type="submit" id="btnSubmitCheckout" class="bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white font-bold text-sm px-6 py-3 rounded-2xl shadow-md shadow-orange-500/20 active:scale-95 transition flex items-center justify-center gap-1.5 flex-shrink-0">
                        Buat Pesanan
                    </button>
                </div>
            </div>

        </form>

    </main>

    <!-- Modal Form Edit Alamat -->
    <div id="addressModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-end sm:items-center justify-center p-0 sm:p-4">
        <div class="bg-white rounded-t-3xl sm:rounded-3xl p-5 max-w-md w-full shadow-2xl border border-slate-100 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-orange-50 text-orange-500 flex items-center justify-center border border-orange-200/60">
                        <?= npglow_icon('pin', 'w-4 h-4') ?>
                    </span>
                    Alamat Pengiriman
                </h3>
                <button type="button" onclick="closeAddressModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nama Penerima</label>
                    <input type="text" id="modal_name" value="<?= htmlspecialchars($defaultName) ?>" placeholder="Nama lengkap penerima" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nomor WhatsApp / HP</label>
                    <input type="tel" id="modal_phone" value="<?= htmlspecialchars($defaultPhone) ?>" placeholder="08xxxxxxxxxx" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Provinsi</label>
                        <select id="modal_province" onchange="onProvinceChange()" class="w-full px-2.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                            <?php foreach ($locationData as $prov => $cities): ?>
                                <option value="<?= htmlspecialchars($prov) ?>" <?= $prov === $defaultProvince ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($prov) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Kota / Kabupaten</label>
                        <select id="modal_city" class="w-full px-2.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                            <!-- Populated dynamically via JS -->
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Kecamatan</label>
                        <input type="text" id="modal_district" value="<?= htmlspecialchars($defaultDistrict) ?>" placeholder="Kecamatan" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Kode Pos</label>
                        <input type="text" id="modal_postal" value="<?= htmlspecialchars($defaultPostal) ?>" placeholder="12345" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20">
                    </div>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Alamat Lengkap (Nama Jalan, No. Rumah, RT/RW, Patokan)</label>
                    <textarea id="modal_address" rows="3" placeholder="Jl. Mawar No. 12 RT 01/RW 02..." class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 resize-none"><?= htmlspecialchars($defaultAddress) ?></textarea>
                </div>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" onclick="closeAddressModal()" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:bg-slate-100 rounded-xl">Batal</button>
                <button type="button" onclick="saveAddressModal()" class="px-5 py-2 text-xs font-bold bg-primary hover:bg-primary-dark text-white rounded-xl shadow-sm">Gunakan Alamat Ini</button>
            </div>
        </div>
    </div>

    <!-- Client-side Image Compressor -->
    <script src="assets/js/image-compressor.js"></script>

    <script>
        const locationMap = <?= json_encode($locationData) ?>;
        const baseProductPrice = <?= (float)$productPrice ?>;
        const originalProductPrice = <?= (float)$originalPrice ?>;

        // Address modal handling
        function openAddressModal() {
            populateCities(document.getElementById('modal_province').value, document.getElementById('inp_city').value);
            document.getElementById('addressModal').classList.remove('hidden');
        }

        function closeAddressModal() {
            document.getElementById('addressModal').classList.add('hidden');
        }

        function onProvinceChange() {
            const prov = document.getElementById('modal_province').value;
            populateCities(prov, null);
        }

        function populateCities(province, selectedCity) {
            const citySelect = document.getElementById('modal_city');
            citySelect.innerHTML = '';
            const cities = locationMap[province] || [];
            cities.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c;
                opt.textContent = c;
                if (selectedCity && c === selectedCity) {
                    opt.selected = true;
                }
                citySelect.appendChild(opt);
            });
        }

        function saveAddressModal() {
            const name = document.getElementById('modal_name').value.trim();
            const phone = document.getElementById('modal_phone').value.trim();
            const prov = document.getElementById('modal_province').value.trim();
            const city = document.getElementById('modal_city').value.trim();
            const district = document.getElementById('modal_district').value.trim();
            const postal = document.getElementById('modal_postal').value.trim();
            const address = document.getElementById('modal_address').value.trim();

            if (!name || !phone || !address) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Lengkapi Alamat',
                    text: 'Nama penerima, no HP, dan alamat lengkap wajib diisi.',
                    confirmButtonColor: '#3ca6f2'
                });
                return;
            }

            // Update hidden inputs
            document.getElementById('inp_recipient_name').value = name;
            document.getElementById('inp_recipient_phone').value = phone;
            document.getElementById('inp_province').value = prov;
            document.getElementById('inp_city').value = city;
            document.getElementById('inp_district').value = district;
            document.getElementById('inp_postal_code').value = postal;
            document.getElementById('inp_full_address').value = address;

            // Update display card
            document.getElementById('addressDisplay').innerHTML = `
                <p class="text-sm font-bold text-slate-900 leading-tight">
                    <span>${name}</span>
                    <span class="text-xs font-normal text-slate-500 ml-1">${phone}</span>
                </p>
                <p class="text-xs text-slate-600 mt-1 leading-relaxed line-clamp-2">
                    ${address}, ${district ? district + ', ' : ''}${city}, ${prov} ${postal}
                </p>
            `;

            closeAddressModal();
            // Re-calculate shipping for newly selected city/province
            fetchShippingCost();
        }

        // Courier change
        function onCourierChange() {
            const val = document.getElementById('courierSelect').value;
            const parts = val.split('_');
            document.getElementById('inp_courier_code').value = parts[0];
            document.getElementById('inp_service_code').value = parts[1];
            fetchShippingCost();
        }

        // Live Shipping Calculation via AJAX API
        async function fetchShippingCost() {
            const prov = document.getElementById('inp_province').value;
            const city = document.getElementById('inp_city').value;
            const courier = document.getElementById('inp_courier_code').value;
            const service = document.getElementById('inp_service_code').value;

            try {
                const formData = new FormData();
                formData.append('province', prov);
                formData.append('city', city);
                formData.append('courier', courier);
                formData.append('service', service);
                formData.append('subtotal', baseProductPrice);
                formData.append('weight', 350);

                const res = await fetch('api/shipping-cost.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    const r = data.rate;
                    document.getElementById('dispEtd').textContent = 'Estimasi Tiba: ' + r.etd;
                    document.getElementById('dispRawCost').textContent = r.formatted_raw_cost;
                    document.getElementById('dispFinalCost').textContent = r.is_free_shipping ? 'Rp 0' : r.formatted_final_cost;
                    
                    document.getElementById('summaryRawShipping').textContent = r.formatted_raw_cost;
                    document.getElementById('summaryDiscountShipping').textContent = '-Rp ' + new Intl.NumberFormat('id-ID').format(r.discount_ongkir);
                    document.getElementById('summaryGrandTotal').textContent = data.formatted_grand_total;
                    document.getElementById('bottomGrandTotal').textContent = data.formatted_grand_total;

                    const totalSavings = (originalProductPrice - baseProductPrice) + r.discount_ongkir;
                    document.getElementById('bottomSavings').textContent = 'Hemat Rp ' + new Intl.NumberFormat('id-ID').format(totalSavings);
                }
            } catch (err) {
                console.error('Shipping calculation error:', err);
            }
        }

        // Payment Method toggle
        function onPaymentMethodChange(method) {
            const optQris = document.getElementById('opt_qris');
            const optBank = document.getElementById('opt_bank');
            const bankContainer = document.getElementById('bankSelectorContainer');

            if (method === 'qris') {
                optQris.className = 'payment-option relative flex items-center justify-between p-3.5 rounded-xl border-2 cursor-pointer transition border-primary bg-blue-50/50';
                optBank.className = 'payment-option relative flex items-center justify-between p-3.5 rounded-xl border border-slate-200 cursor-pointer transition hover:border-slate-300';
                bankContainer.classList.add('hidden');
            } else {
                optBank.className = 'payment-option relative flex items-center justify-between p-3.5 rounded-xl border-2 cursor-pointer transition border-primary bg-blue-50/50';
                optQris.className = 'payment-option relative flex items-center justify-between p-3.5 rounded-xl border border-slate-200 cursor-pointer transition hover:border-slate-300';
                bankContainer.classList.remove('hidden');
            }
        }

        // Initial face photo upload handling & WebP conversion
        const fileInput = document.getElementById('face_photo');
        const dropZone = document.getElementById('dropZone');
        const placeholder = document.getElementById('uploadPlaceholder');
        const preview = document.getElementById('uploadPreview');
        const previewImg = document.getElementById('previewImg');
        const badge = document.getElementById('compressBadge');

        if (fileInput) {
            fileInput.addEventListener('change', async function(e) {
                if (this.files && this.files[0]) {
                    const originalFile = this.files[0];
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        placeholder.classList.add('hidden');
                        preview.classList.remove('hidden');
                        dropZone.classList.add('has-image');
                    };
                    reader.readAsDataURL(originalFile);

                    if (badge) badge.textContent = 'Mengompresi ke WebP...';

                    try {
                        const result = await NPGLOWCompressor.compress(originalFile, { quality: 0.82, maxWidth: 1600, maxHeight: 1600 });
                        previewImg.src = result.previewUrl;
                        if (badge) {
                            badge.innerHTML = `WebP: ${NPGLOWCompressor.formatBytes(result.originalSize)} &rarr; ${NPGLOWCompressor.formatBytes(result.compressedSize)} (-${result.savings}%)`;
                        }

                        if (window.DataTransfer) {
                            const dt = new DataTransfer();
                            dt.items.add(result.file);
                            fileInput.files = dt.files;
                        }
                    } catch (err) {
                        console.warn('Client compression fallback:', err);
                    }
                }
            });
        }

        // Form submission validation
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const addr = document.getElementById('inp_full_address').value.trim();
            if (!addr) {
                e.preventDefault();
                openAddressModal();
                Swal.fire({
                    icon: 'warning',
                    title: 'Alamat Diperlukan',
                    text: 'Silakan lengkapi alamat pengiriman Anda terlebih dahulu.',
                    confirmButtonColor: '#3ca6f2'
                });
                return;
            }

            <?php if (!$hasInitialPhoto): ?>
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Foto Wajah Diperlukan',
                    text: 'Silakan upload foto wajah Anda untuk memulai Skincare Journal.',
                    confirmButtonColor: '#3ca6f2'
                });
                return;
            }
            <?php endif; ?>

            const btn = document.getElementById('btnSubmitCheckout');
            btn.disabled = true;
            btn.innerHTML = '<svg class="w-4 h-4 animate-spin inline-block mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...';
        });

        // Initialize city dropdown for modal
        populateCities(document.getElementById('modal_province').value, '<?= htmlspecialchars($defaultCity) ?>');
    </script>
</body>
<?php include 'includes/pwa-sw.php'; ?>
</html>
