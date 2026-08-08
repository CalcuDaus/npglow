<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth-helper.php';
require_once 'includes/image-helper.php';
require_once 'includes/settings-helper.php';
require_once 'includes/order-tracking-helper.php';
require_once 'includes/icon-helper.php';

// Strictly forbid Admin and Expert from customer payment flow
guard_buyer_only();

$userId = (int)$_SESSION['user_id'];
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($orderId <= 0) {
    header("Location: dashboard.php");
    exit();
}

// Fetch order details with product and bank
$stmt = $conn->prepare("
    SELECT o.*, p.name as product_name, p.image_url as product_image, 
           b.bank_name, b.account_number, b.account_holder, b.bank_code
    FROM orders o
    JOIN products p ON o.product_id = p.id
    LEFT JOIN payment_bank_accounts b ON o.payment_bank_id = b.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: dashboard.php");
    exit();
}

// Fetch active/primary QRIS Statis
$qrisQuery = $conn->query("SELECT * FROM payment_qris_accounts WHERE is_active = 1 ORDER BY is_primary DESC, id ASC LIMIT 1");
if ($qrisQuery && $qrisRow = $qrisQuery->fetch_assoc()) {
    $qrisImage = $qrisRow['image_path'];
    $qrisMerchant = $qrisRow['merchant_name'];
    $qrisNmid = $qrisRow['nmid'];
} else {
    $settings = get_all_settings($conn);
    $qrisImage = $settings['payment_qris_image'] ?? 'assets/images/qris-sample.png';
    $qrisMerchant = $settings['payment_qris_merchant'] ?? 'NPGLOW BEAUTY OFFICIAL';
    $qrisNmid = '';
}

$successMsg = '';
$errorMsg = '';

// Handle Proof of Payment Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_proof') {
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['payment_proof'];
        $destDir = "uploads/payments/proofs";
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $filename = "proof_{$orderId}_" . time() . ".webp";
        $targetPath = "{$destDir}/{$filename}";

        $convertResult = convert_image_to_webp($file['tmp_name'], $targetPath, 82, 1600, 1600);
        if ($convertResult['success']) {
            $stmt = $conn->prepare("UPDATE orders SET payment_proof = ?, payment_status = 'waiting_verification' WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sii", $targetPath, $orderId, $userId);
            if ($stmt->execute()) {
                $successMsg = 'Bukti pembayaran berhasil diupload! Tim Admin kami sedang memverifikasi pesanan Anda.';
                // Log tracking timeline
                add_order_tracking_log($conn, $orderId, 'waiting_verification', 'Bukti Pembayaran Diunggah', 'Customer telah mengunggah bukti transfer/QRIS. Sedang diverifikasi oleh Admin NPGLOW.', 'NPGLOW System');
                // Refresh order
                $order['payment_proof'] = $targetPath;
                $order['payment_status'] = 'waiting_verification';
            } else {
                $errorMsg = 'Gagal menyimpan data pembayaran.';
            }
        } else {
            $errorMsg = 'Gagal memproses gambar: ' . ($convertResult['error'] ?? 'Format tidak didukung');
        }
    } else {
        $errorMsg = 'Silakan pilih foto bukti pembayaran / transfer.';
    }
}

$totalAmount = (float)$order['total_amount'];
$isQris = $order['payment_method'] === 'qris';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pembayaran Pesanan - NPGLOW</title>
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
    </style>
</head>
<body class="min-h-screen pb-16">

    <!-- Mobile Header -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-100 shadow-[0_2px_8px_rgba(0,0,0,0.03)]">
        <div class="max-w-md mx-auto px-4 h-14 flex items-center justify-between">
            <a href="dashboard.php" class="p-2 -ml-2 text-slate-600 hover:text-primary transition rounded-full">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
            </a>
            <h1 class="text-base font-bold text-slate-800 tracking-tight">Instruksi Pembayaran</h1>
            <a href="dashboard.php" class="text-xs font-semibold text-primary">Dashboard</a>
        </div>
    </header>

    <main class="max-w-md mx-auto px-3.5 pt-3.5 space-y-3.5">

        <!-- Notifications -->
        <?php if (!empty($successMsg)): ?>
            <div class="bg-emerald-50 text-emerald-700 p-3.5 rounded-2xl border border-emerald-200 text-xs flex items-center gap-2.5 shadow-sm">
                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <span class="font-medium leading-relaxed"><?= htmlspecialchars($successMsg) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($errorMsg)): ?>
            <div class="bg-red-50 text-red-700 p-3.5 rounded-2xl border border-red-200 text-xs flex items-center gap-2.5 shadow-sm">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                <span class="font-medium leading-relaxed"><?= htmlspecialchars($errorMsg) ?></span>
            </div>
        <?php endif; ?>

        <!-- Order Summary & Status Card -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100/80 space-y-3">
            <div class="flex items-center justify-between pb-2.5 border-b border-slate-100">
                <div>
                    <span class="text-[11px] text-slate-400 font-medium block">Nomor Pesanan</span>
                    <span class="text-xs font-mono font-bold text-slate-800"><?= htmlspecialchars($order['order_number'] ?: ('NP-#' . $order['id'])) ?></span>
                </div>
                <div>
                    <?php if ($order['payment_status'] === 'waiting_verification'): ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                            Menunggu Verifikasi
                        </span>
                    <?php elseif ($order['payment_status'] === 'paid'): ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <?= npglow_icon('check', 'w-3 h-3 text-emerald-600') ?> Pembayaran Lunas
                        </span>
                    <?php elseif ($order['payment_status'] === 'rejected'): ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-red-50 text-red-600 border border-red-200">
                            <?= npglow_icon('x-circle', 'w-3 h-3 text-red-500') ?> Bukti Ditolak
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-primary border border-blue-200">
                            Belum Dibayar
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Total Amount Highlight -->
            <div class="bg-slate-50/80 rounded-xl p-3 flex items-center justify-between">
                <div>
                    <p class="text-[11px] text-slate-500 font-medium">Total Tagihan Pembayaran</p>
                    <p class="text-lg font-black text-slate-900 tracking-tight" id="nominalText">
                        Rp <?= number_format($totalAmount, 0, ',', '.') ?>
                    </p>
                </div>
                <button onclick="copyToClipboard('<?= (int)$totalAmount ?>', 'Nominal transfer berhasil disalin!')" class="px-3 py-1.5 bg-white border border-slate-200 hover:border-primary text-slate-700 hover:text-primary rounded-xl text-xs font-bold transition shadow-2xl flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75"/></svg>
                    Salin
                </button>
            </div>
        </div>

        <?php if ($order['payment_status'] === 'rejected' && !empty($order['admin_note'])): ?>
            <!-- Rejection Alert -->
            <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-2xl text-xs space-y-1">
                <p class="font-bold flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Catatan dari Admin:
                </p>
                <p class="text-slate-700 leading-relaxed"><?= htmlspecialchars($order['admin_note']) ?></p>
                <p class="text-[11px] text-red-600 pt-1 font-medium">Silakan upload ulang bukti pembayaran yang sah di bawah ini.</p>
            </div>
        <?php endif; ?>

        <!-- Payment Instructions Card -->
        <?php if ($isQris): ?>
            <!-- QRIS PAYMENT VIEW -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100/80 text-center space-y-4">
                <div>
                    <span class="text-[11px] font-bold text-primary uppercase tracking-wider bg-blue-50 px-2.5 py-1 rounded-full">QRIS Pembayaran</span>
                    <h3 class="text-sm font-bold text-slate-800 mt-2"><?= htmlspecialchars($qrisMerchant) ?></h3>
                    <p class="text-xs text-slate-400 mt-0.5">Scan menggunakan BCA Mobile, GoPay, OVO, ShopeePay, DANA, atau M-Banking lainnya</p>
                </div>

                <!-- QR Image Display -->
                <div class="w-56 h-56 mx-auto bg-white p-3 rounded-2xl border-2 border-slate-200 shadow-sm flex items-center justify-center overflow-hidden">
                    <?php if (!empty($qrisImage) && file_exists($qrisImage)): ?>
                        <img src="<?= htmlspecialchars($qrisImage) ?>" alt="QRIS Toko" class="w-full h-full object-contain">
                    <?php else: ?>
                        <div class="text-center text-slate-400 text-xs p-4">
                            <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                            QR Code Toko
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($qrisImage) && file_exists($qrisImage)): ?>
                    <a href="<?= htmlspecialchars($qrisImage) ?>" download="QRIS_NPGLOW.webp" class="inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:text-primary-dark bg-blue-50 px-4 py-2 rounded-xl transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        Download / Simpan QR
                    </a>
                <?php endif; ?>

                <div class="text-left text-xs text-slate-600 bg-slate-50 rounded-xl p-3.5 space-y-1.5">
                    <p class="font-bold text-slate-800">Petunjuk Pembayaran QRIS:</p>
                    <ol class="list-decimal list-inside space-y-1 text-slate-600 leading-relaxed">
                        <li>Buka aplikasi M-Banking atau E-Wallet pilihan Anda.</li>
                        <li>Pilih menu <strong>Scan / Bayar QRIS</strong>.</li>
                        <li>Arahkan kamera ke QR Code di atas (atau pilih dari galeri jika didownload).</li>
                        <li>Pastikan nama merchant adalah <strong><?= htmlspecialchars($qrisMerchant) ?></strong>.</li>
                        <li>Masukkan nominal tepat: <strong>Rp <?= number_format($totalAmount, 0, ',', '.') ?></strong>.</li>
                        <li>Simpan screenshot bukti pembayaran untuk diunggah di bawah.</li>
                    </ol>
                </div>
            </div>
        <?php else: ?>
            <!-- BANK TRANSFER VIEW -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100/80 space-y-4">
                <div class="text-center pb-2 border-b border-slate-100">
                    <span class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider bg-emerald-50 px-2.5 py-1 rounded-full">Transfer Bank Manual</span>
                    <h3 class="text-sm font-bold text-slate-800 mt-2">Bank <?= htmlspecialchars($order['bank_name'] ?: 'BCA') ?></h3>
                    <p class="text-xs text-slate-400 mt-0.5">Transfer melalui ATM, M-Banking, atau Internet Banking</p>
                </div>

                <!-- Account Box -->
                <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100 space-y-3">
                    <div>
                        <span class="text-[11px] text-slate-400 font-medium">Nomor Rekening</span>
                        <div class="flex items-center justify-between mt-1">
                            <span class="font-mono text-base font-extrabold text-slate-900 tracking-wider" id="bankAccNumber">
                                <?= htmlspecialchars($order['account_number'] ?: '8280912345') ?>
                            </span>
                            <button onclick="copyToClipboard('<?= htmlspecialchars($order['account_number'] ?: '8280912345') ?>', 'Nomor rekening berhasil disalin!')" class="px-3 py-1.5 bg-white border border-slate-200 hover:border-primary text-slate-700 hover:text-primary rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75"/></svg>
                                Salin
                            </button>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-200/60">
                        <span class="text-[11px] text-slate-400 font-medium">Atas Nama Rekening</span>
                        <p class="text-xs font-bold text-slate-800 mt-0.5"><?= htmlspecialchars($order['account_holder'] ?: 'NPGLOW INDONESIA') ?></p>
                    </div>
                </div>

                <div class="text-left text-xs text-slate-600 bg-slate-50 rounded-xl p-3.5 space-y-1.5">
                    <p class="font-bold text-slate-800">Petunjuk Transfer Bank:</p>
                    <ol class="list-decimal list-inside space-y-1 text-slate-600 leading-relaxed">
                        <li>Buka aplikasi M-Banking Anda (BCA, Mandiri, BRI, dll).</li>
                        <li>Pilih menu <strong>Transfer Rekening</strong>.</li>
                        <li>Masukkan nomor rekening di atas.</li>
                        <li>Pastikan nama penerima adalah <strong><?= htmlspecialchars($order['account_holder'] ?: 'NPGLOW INDONESIA') ?></strong>.</li>
                        <li>Transfer nominal tepat: <strong>Rp <?= number_format($totalAmount, 0, ',', '.') ?></strong>.</li>
                        <li>Simpan bukti transfer dan upload di form di bawah ini.</li>
                    </ol>
                </div>
            </div>
        <?php endif; ?>

        <!-- Proof of Payment Upload Card -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100/80 space-y-4">
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
                <svg class="w-4 h-4 two-tone-icon" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                </svg>
                Bukti Pembayaran
            </h3>

            <?php if ($order['payment_status'] === 'paid'): ?>
                <!-- PAID SUCCESS VIEW -->
                <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 text-center space-y-3">
                    <div class="w-12 h-12 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-emerald-800">Pembayaran Berhasil Diverifikasi!</h4>
                        <p class="text-xs text-emerald-700 mt-1 leading-relaxed">
                            Terima kasih! Pesanan Anda sedang dipersiapkan dan fitur <strong>Skincare Journal & Konsultasi Tim Ahli</strong> Anda sudah aktif.
                        </p>
                    </div>
                    <div class="pt-2 flex flex-col sm:flex-row gap-2 justify-center">
                        <a href="order-tracking.php?order_id=<?= $order['id'] ?>" class="px-5 py-2.5 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center justify-center gap-1.5">
                            <?= npglow_icon('package', 'w-4 h-4 text-white') ?>
                            Lacak Pengiriman
                        </a>
                        <a href="journal.php" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center justify-center gap-1.5">
                            <?= npglow_icon('book', 'w-4 h-4 text-white') ?>
                            Buka Skincare Journal
                        </a>
                    </div>
                </div>

            <?php elseif ($order['payment_status'] === 'waiting_verification'): ?>
                <!-- WAITING VERIFICATION VIEW -->
                <div class="bg-amber-50/70 border border-amber-200 rounded-2xl p-4 text-center space-y-3">
                    <div class="w-12 h-12 mx-auto rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-amber-900">Bukti Pembayaran Terkirim</h4>
                        <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                            Admin NPGLOW sedang memverifikasi pembayaran Anda. Proses verifikasi biasanya memakan waktu 5-15 menit pada jam kerja.
                        </p>
                    </div>

                    <?php if (!empty($order['payment_proof']) && file_exists($order['payment_proof'])): ?>
                        <div class="w-32 h-32 mx-auto rounded-xl overflow-hidden border border-amber-200 bg-white p-1 shadow-sm mt-2">
                            <img src="<?= htmlspecialchars($order['payment_proof']) ?>" alt="Bukti Pembayaran" class="w-full h-full object-cover rounded-lg">
                        </div>
                    <?php endif; ?>

                    <div class="pt-2 flex flex-col sm:flex-row gap-2 justify-center">
                        <a href="order-tracking.php?order_id=<?= $order['id'] ?>" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-xl text-xs font-bold transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                            Lihat Timeline Pesanan
                        </a>
                        <a href="https://wa.me/6283111536065?text=Halo%20Admin%20NPGLOW,%20saya%20sudah%20upload%20bukti%20pembayaran%20untuk%20pesanan%20<?= urlencode($order['order_number'] ?: ('NP-#' . $order['id'])) ?>" target="_blank" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                            Konfirmasi WhatsApp
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- FORM UPLOAD BUKTI (PENDING OR REJECTED) -->
                <form method="POST" enctype="multipart/form-data" id="proofForm" class="space-y-3">
                    <input type="hidden" name="action" value="upload_proof">
                    
                    <label for="payment_proof" class="photo-drop-zone block rounded-2xl p-4 cursor-pointer text-center border-2 border-dashed border-slate-200 hover:border-primary transition" id="proofDropZone">
                        <div id="proofPlaceholder">
                            <div class="w-10 h-10 mx-auto rounded-full bg-slate-100 flex items-center justify-center mb-1.5 text-slate-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            </div>
                            <p class="text-xs font-semibold text-slate-700">Upload Struk / Screenshot Bukti Bayar</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">JPG, PNG, WebP • Auto-Convert</p>
                        </div>
                        <div id="proofPreview" class="hidden">
                            <img id="proofPreviewImg" class="w-32 h-32 mx-auto rounded-xl object-cover shadow-sm mb-1.5" alt="Preview Bukti">
                            <p class="text-[11px] text-emerald-600 font-bold flex items-center justify-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Foto bukti siap diunggah
                            </p>
                            <span id="proofBadge" class="inline-block text-[10px] font-medium bg-emerald-100/70 text-emerald-800 px-2 py-0.5 rounded-full mt-1"></span>
                        </div>
                        <input type="file" name="payment_proof" id="payment_proof" accept="image/*" class="hidden" required>
                    </label>

                    <button type="submit" id="btnUploadProof" class="w-full bg-gradient-to-r from-primary to-blue-500 hover:from-primary-dark hover:to-blue-600 text-white font-bold text-xs py-3 rounded-xl shadow-md transition flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                        Kirim Bukti Pembayaran
                    </button>
                </form>
            <?php endif; ?>
        </div>

    </main>

    <!-- Compressor JS -->
    <script src="assets/js/image-compressor.js"></script>

    <script>
        function copyToClipboard(text, message) {
            navigator.clipboard.writeText(text).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil Disalin!',
                    text: message,
                    timer: 1500,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }).catch(err => {
                console.error('Copy failed:', err);
            });
        }

        // Proof image preview & WebP compression
        const proofInput = document.getElementById('payment_proof');
        const proofDropZone = document.getElementById('proofDropZone');
        const proofPlaceholder = document.getElementById('proofPlaceholder');
        const proofPreview = document.getElementById('proofPreview');
        const proofPreviewImg = document.getElementById('proofPreviewImg');
        const proofBadge = document.getElementById('proofBadge');

        if (proofInput) {
            proofInput.addEventListener('change', async function(e) {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        proofPreviewImg.src = e.target.result;
                        proofPlaceholder.classList.add('hidden');
                        proofPreview.classList.remove('hidden');
                        proofDropZone.classList.add('has-image');
                    };
                    reader.readAsDataURL(file);

                    if (proofBadge) proofBadge.textContent = 'Mengompresi ke WebP...';

                    try {
                        const result = await NPGLOWCompressor.compress(file, { quality: 0.82, maxWidth: 1600, maxHeight: 1600 });
                        proofPreviewImg.src = result.previewUrl;
                        if (proofBadge) {
                            proofBadge.innerHTML = `WebP: ${NPGLOWCompressor.formatBytes(result.originalSize)} &rarr; ${NPGLOWCompressor.formatBytes(result.compressedSize)} (-${result.savings}%)`;
                        }

                        if (window.DataTransfer) {
                            const dt = new DataTransfer();
                            dt.items.add(result.file);
                            proofInput.files = dt.files;
                        }
                    } catch (err) {
                        console.warn('Compression fallback:', err);
                    }
                }
            });
        }

        const proofForm = document.getElementById('proofForm');
        if (proofForm) {
            proofForm.addEventListener('submit', function(e) {
                const btn = document.getElementById('btnUploadProof');
                btn.disabled = true;
                btn.innerHTML = '<svg class="w-4 h-4 animate-spin inline-block mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Mengunggah...';
            });
        }
    </script>
</body>
<?php include 'includes/pwa-sw.php'; ?>
</html>
