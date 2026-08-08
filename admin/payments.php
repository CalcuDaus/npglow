<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/settings-helper.php';
require_once '../includes/image-helper.php';
require_once '../includes/icon-helper.php';

// Auth Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$successMsg = '';
$errorMsg = '';

// Handle Add QRIS Statis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_qris') {
    $merchant = trim($_POST['merchant_name'] ?? 'NPGLOW BEAUTY OFFICIAL');
    $nmid = trim($_POST['nmid'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $isPrimary = isset($_POST['is_primary']) ? 1 : 0;

    if (isset($_FILES['qris_image']) && $_FILES['qris_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['qris_image'];
        $destDir = '../uploads/payments/qris';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        
        $filename = 'qris_' . time() . '_' . rand(100, 999) . '.webp';
        $targetPath = $destDir . '/' . $filename;
        $dbPath = 'uploads/payments/qris/' . $filename;
        
        $convertResult = convert_image_to_webp($file['tmp_name'], $targetPath, 85, 1200, 1200);
        if ($convertResult['success']) {
            if ($isPrimary) {
                $conn->query("UPDATE payment_qris_accounts SET is_primary = 0");
                save_setting($conn, 'payment_qris_image', $dbPath);
                save_setting($conn, 'payment_qris_merchant', $merchant);
            }
            
            $stmt = $conn->prepare("INSERT INTO payment_qris_accounts (merchant_name, nmid, image_path, is_active, is_primary) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssii", $merchant, $nmid, $dbPath, $isActive, $isPrimary);
            if ($stmt->execute()) {
                $successMsg = 'QRIS Statis baru berhasil ditambahkan!';
            } else {
                $errorMsg = 'Gagal menyimpan QRIS: ' . $conn->error;
            }
        } else {
            $errorMsg = 'Gagal memproses gambar QRIS: ' . ($convertResult['error'] ?? 'Format tidak didukung');
        }
    } else {
        $errorMsg = 'Foto QR Code QRIS wajib diunggah!';
    }
}

// Handle Edit QRIS Statis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_qris') {
    $id = (int)$_POST['qris_id'];
    $merchant = trim($_POST['merchant_name'] ?? 'NPGLOW BEAUTY OFFICIAL');
    $nmid = trim($_POST['nmid'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $isPrimary = isset($_POST['is_primary']) ? 1 : 0;

    if ($id > 0) {
        $newDbPath = null;
        if (isset($_FILES['qris_image']) && $_FILES['qris_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['qris_image'];
            $destDir = '../uploads/payments/qris';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            
            $filename = 'qris_' . time() . '_' . rand(100, 999) . '.webp';
            $targetPath = $destDir . '/' . $filename;
            $newDbPath = 'uploads/payments/qris/' . $filename;
            
            $convertResult = convert_image_to_webp($file['tmp_name'], $targetPath, 85, 1200, 1200);
            if (!$convertResult['success']) {
                $newDbPath = null;
                $errorMsg = 'Gagal mengonversi gambar baru.';
            }
        }

        if (empty($errorMsg)) {
            if ($isPrimary) {
                $conn->query("UPDATE payment_qris_accounts SET is_primary = 0");
                if ($newDbPath) {
                    save_setting($conn, 'payment_qris_image', $newDbPath);
                }
                save_setting($conn, 'payment_qris_merchant', $merchant);
            }

            if ($newDbPath) {
                $stmt = $conn->prepare("UPDATE payment_qris_accounts SET merchant_name = ?, nmid = ?, image_path = ?, is_active = ?, is_primary = ? WHERE id = ?");
                $stmt->bind_param("sssiii", $merchant, $nmid, $newDbPath, $isActive, $isPrimary, $id);
            } else {
                $stmt = $conn->prepare("UPDATE payment_qris_accounts SET merchant_name = ?, nmid = ?, is_active = ?, is_primary = ? WHERE id = ?");
                $stmt->bind_param("ssiii", $merchant, $nmid, $isActive, $isPrimary, $id);
            }

            if ($stmt->execute()) {
                $successMsg = 'Data QRIS Statis berhasil diperbarui!';
            } else {
                $errorMsg = 'Gagal memperbarui QRIS: ' . $conn->error;
            }
        }
    }
}

// Handle Set Primary QRIS
if (isset($_GET['set_primary_qris'])) {
    $primId = (int)$_GET['set_primary_qris'];
    $conn->query("UPDATE payment_qris_accounts SET is_primary = 0");
    $stmt = $conn->prepare("UPDATE payment_qris_accounts SET is_primary = 1, is_active = 1 WHERE id = ?");
    $stmt->bind_param("i", $primId);
    if ($stmt->execute()) {
        // Sync with settings
        $qRow = $conn->query("SELECT * FROM payment_qris_accounts WHERE id = $primId")->fetch_assoc();
        if ($qRow) {
            save_setting($conn, 'payment_qris_image', $qRow['image_path']);
            save_setting($conn, 'payment_qris_merchant', $qRow['merchant_name']);
        }
        header("Location: payments.php?msg=qris_primary_set");
        exit();
    }
}

// Handle Delete QRIS
if (isset($_GET['delete_qris'])) {
    $delId = (int)$_GET['delete_qris'];
    $stmt = $conn->prepare("DELETE FROM payment_qris_accounts WHERE id = ?");
    $stmt->bind_param("i", $delId);
    if ($stmt->execute()) {
        header("Location: payments.php?msg=qris_deleted");
        exit();
    }
}

// Handle Add Bank Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_bank') {
    $bankName = trim($_POST['bank_name'] ?? '');
    $bankCode = strtoupper(trim($_POST['bank_code'] ?? ''));
    $accountNumber = trim($_POST['account_number'] ?? '');
    $accountHolder = trim($_POST['account_holder'] ?? '');

    if (!empty($bankName) && !empty($accountNumber) && !empty($accountHolder)) {
        if (empty($bankCode)) {
            $bankCode = strtoupper(substr($bankName, 0, 10));
        }

        $stmt = $conn->prepare("INSERT INTO payment_bank_accounts (bank_name, bank_code, account_number, account_holder, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("ssss", $bankName, $bankCode, $accountNumber, $accountHolder);
        if ($stmt->execute()) {
            $successMsg = 'Rekening bank baru berhasil ditambahkan!';
        } else {
            $errorMsg = 'Gagal menambahkan rekening bank: ' . $conn->error;
        }
    } else {
        $errorMsg = 'Semua kolom rekening wajib diisi!';
    }
}

// Handle Edit Bank Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_bank') {
    $id = (int)$_POST['bank_id'];
    $bankName = trim($_POST['bank_name'] ?? '');
    $bankCode = strtoupper(trim($_POST['bank_code'] ?? ''));
    $accountNumber = trim($_POST['account_number'] ?? '');
    $accountHolder = trim($_POST['account_holder'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($id > 0 && !empty($bankName) && !empty($accountNumber) && !empty($accountHolder)) {
        $stmt = $conn->prepare("UPDATE payment_bank_accounts SET bank_name = ?, bank_code = ?, account_number = ?, account_holder = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("ssssii", $bankName, $bankCode, $accountNumber, $accountHolder, $isActive, $id);
        if ($stmt->execute()) {
            $successMsg = 'Data rekening bank berhasil diperbarui!';
        } else {
            $errorMsg = 'Gagal memperbarui rekening.';
        }
    }
}

// Handle Delete Bank
if (isset($_GET['delete_bank'])) {
    $delId = (int)$_GET['delete_bank'];
    $stmt = $conn->prepare("DELETE FROM payment_bank_accounts WHERE id = ?");
    $stmt->bind_param("i", $delId);
    if ($stmt->execute()) {
        header("Location: payments.php?msg=bank_deleted");
        exit();
    }
}

// Fetch QRIS & Bank Accounts
$qrisList = $conn->query("SELECT * FROM payment_qris_accounts ORDER BY is_primary DESC, id ASC");
$banks = $conn->query("SELECT * FROM payment_bank_accounts ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Metode Pembayaran - Admin NPGLOW</title>
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
    </style>
</head>
<body class="bg-gray-50 text-slate-800 antialiased font-sans flex min-h-screen">
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 min-h-screen overflow-x-hidden">
        <!-- Topbar -->
        <?php include 'topbar.php'; ?>

        <!-- Content Body -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="max-w-6xl mx-auto space-y-8 pb-12">
            
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                        <span class="p-2 bg-blue-100/70 text-primary rounded-xl inline-flex">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                        </span>
                        Kelola Metode Pembayaran
                    </h2>
                    <p class="text-gray-500 text-sm mt-1">Atur QRIS Statis dan rekening Bank Transfer yang tampil di halaman checkout.</p>
                </div>
            </div>

            <!-- Notifications -->
            <?php if (!empty($successMsg)): ?>
                <div class="bg-emerald-50 text-emerald-700 p-4 rounded-2xl border border-emerald-200 flex items-center gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span class="text-sm font-semibold"><?= htmlspecialchars($successMsg) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($errorMsg)): ?>
                <div class="bg-red-50 text-red-700 p-4 rounded-2xl border border-red-200 flex items-center gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    <span class="text-sm font-semibold"><?= htmlspecialchars($errorMsg) ?></span>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['msg'])): ?>
                <div class="bg-emerald-50 text-emerald-700 p-4 rounded-2xl border border-emerald-200 flex items-center gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span class="text-sm font-semibold">
                        <?php
                            echo match($_GET['msg']) {
                                'qris_primary_set' => 'QRIS Statis utama berhasil diubah.',
                                'qris_deleted' => 'QRIS Statis berhasil dihapus.',
                                'bank_deleted' => 'Rekening bank berhasil dihapus.',
                                default => 'Operasi berhasil.'
                            };
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <!-- QRIS Statis CRUD Card -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-gray-100">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-gray-100 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                                Daftar QRIS Statis Toko
                                <span class="text-[11px] font-bold px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full">Statis</span>
                            </h3>
                            <p class="text-xs text-gray-400">Kelola foto QRIS Statis (NMID, Nama Merchant, Status Utama)</p>
                        </div>
                    </div>
                    <button onclick="openAddQrisModal()" class="inline-flex items-center gap-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Tambah QRIS Statis
                    </button>
                </div>

                <!-- QRIS Statis Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-50 text-gray-700 uppercase font-semibold text-xs border-b border-gray-100">
                            <tr>
                                <th class="p-3.5">QR Code</th>
                                <th class="p-3.5">Merchant & NMID</th>
                                <th class="p-3.5 text-center">Status</th>
                                <th class="p-3.5 text-center">Default / Utama</th>
                                <th class="p-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if ($qrisList && $qrisList->num_rows > 0): ?>
                                <?php while ($q = $qrisList->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="p-3.5">
                                            <div class="w-14 h-14 bg-white p-1 rounded-xl shadow-sm border border-gray-200 overflow-hidden cursor-pointer group" onclick="previewImage('../<?= htmlspecialchars($q['image_path']) ?>', '<?= htmlspecialchars($q['merchant_name']) ?>')">
                                                <img src="../<?= htmlspecialchars($q['image_path']) ?>" alt="QRIS" class="w-full h-full object-contain group-hover:scale-105 transition">
                                            </div>
                                        </td>
                                        <td class="p-3.5">
                                            <p class="font-bold text-gray-900"><?= htmlspecialchars($q['merchant_name']) ?></p>
                                            <p class="text-xs text-gray-400 font-mono mt-0.5">NMID: <?= htmlspecialchars($q['nmid'] ?: '-') ?></p>
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?= $q['is_active'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-500' ?>">
                                                <?= $q['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                            </span>
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <?php if ($q['is_primary']): ?>
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-700 border border-amber-300 rounded-full text-xs font-bold shadow-sm">
                                                    <?= npglow_icon('star', 'w-3 h-3 text-amber-500') ?>
                                                    Utama
                                                </span>
                                            <?php else: ?>
                                                <a href="payments.php?set_primary_qris=<?= $q['id'] ?>" class="text-xs text-gray-400 hover:text-primary font-semibold hover:underline">
                                                    Jadikan Utama
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button onclick='openEditQrisModal(<?= json_encode($q) ?>)' class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                </button>
                                                <button onclick="confirmDeleteQris(<?= $q['id'] ?>)" class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition" title="Hapus">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="p-6 text-center text-gray-400">Belum ada data QRIS Statis.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Bank Accounts Card -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-gray-100">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-gray-100 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Daftar Rekening Bank Tujuan Transfer</h3>
                            <p class="text-xs text-gray-400">Pembeli dapat memilih transfer manual ke rekening bank berikut</p>
                        </div>
                    </div>
                    <button onclick="openAddBankModal()" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Tambah Rekening Bank
                    </button>
                </div>

                <!-- Bank Accounts Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-50 text-gray-700 uppercase font-semibold text-xs border-b border-gray-100">
                            <tr>
                                <th class="p-3.5">Nama Bank</th>
                                <th class="p-3.5">Nomor Rekening</th>
                                <th class="p-3.5">Atas Nama</th>
                                <th class="p-3.5 text-center">Status</th>
                                <th class="p-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if ($banks && $banks->num_rows > 0): ?>
                                <?php while ($b = $banks->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="p-3.5 font-bold text-gray-900 flex items-center gap-2">
                                            <span class="w-8 h-8 rounded-lg bg-blue-50 text-primary flex items-center justify-center font-extrabold text-xs">
                                                <?= htmlspecialchars(substr($b['bank_code'] ?: $b['bank_name'], 0, 3)) ?>
                                            </span>
                                            <?= htmlspecialchars($b['bank_name']) ?>
                                        </td>
                                        <td class="p-3.5 font-mono font-bold text-gray-800">
                                            <?= htmlspecialchars($b['account_number']) ?>
                                        </td>
                                        <td class="p-3.5 font-medium text-gray-700">
                                            <?= htmlspecialchars($b['account_holder']) ?>
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?= $b['is_active'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-500' ?>">
                                                <?= $b['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                            </span>
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button onclick='openEditBankModal(<?= json_encode($b) ?>)' class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                </button>
                                                <button onclick="confirmDeleteBank(<?= $b['id'] ?>)" class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition" title="Hapus">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="p-6 text-center text-gray-400">Belum ada rekening bank yang ditambahkan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            </div>
        </main>
    </div>

    <!-- Modal Add QRIS Statis -->
    <div id="addQrisModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl p-6 max-w-md w-full shadow-2xl border border-gray-100">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="p-1.5 bg-amber-50 text-amber-600 rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                </span>
                Tambah QRIS Statis Toko
            </h3>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="add_qris">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Nama Merchant / Toko</label>
                    <input type="text" name="merchant_name" placeholder="NPGLOW BEAUTY OFFICIAL" required class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">NMID (Nomor Merchant ID)</label>
                    <input type="text" name="nmid" placeholder="ID1020000000000" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Upload Foto QR Code QRIS</label>
                    <input type="file" name="qris_image" accept="image/*" required class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
                </div>
                <div class="space-y-2 pt-1">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 rounded text-primary focus:ring-primary/30 border-gray-300">
                        <span class="text-xs font-semibold text-gray-700">Aktifkan QRIS ini</span>
                    </label>
                    <br>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_primary" value="1" checked class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400 border-gray-300">
                        <span class="text-xs font-semibold text-gray-700">Jadikan QRIS Utama Toko</span>
                    </label>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeAddQrisModal()" class="px-4 py-2 text-sm text-gray-500 hover:bg-gray-100 rounded-xl font-medium">Batal</button>
                    <button type="submit" class="px-5 py-2 text-sm bg-amber-600 hover:bg-amber-700 text-white rounded-xl font-bold">Simpan QRIS</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit QRIS Statis -->
    <div id="editQrisModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl p-6 max-w-md w-full shadow-2xl border border-gray-100">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="p-1.5 bg-blue-50 text-blue-600 rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                </span>
                Edit QRIS Statis Toko
            </h3>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="edit_qris">
                <input type="hidden" name="qris_id" id="edit_qris_id">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Nama Merchant / Toko</label>
                    <input type="text" name="merchant_name" id="edit_qris_merchant" required class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">NMID (Nomor Merchant ID)</label>
                    <input type="text" name="nmid" id="edit_qris_nmid" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Ganti Foto QR Code (Opsional)</label>
                    <input type="file" name="qris_image" accept="image/*" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-primary hover:file:bg-blue-100">
                </div>
                <div class="space-y-2 pt-1">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" id="edit_qris_is_active" value="1" class="w-4 h-4 rounded text-primary focus:ring-primary/30 border-gray-300">
                        <span class="text-xs font-semibold text-gray-700">Aktifkan QRIS ini</span>
                    </label>
                    <br>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_primary" id="edit_qris_is_primary" value="1" class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400 border-gray-300">
                        <span class="text-xs font-semibold text-gray-700">Jadikan QRIS Utama Toko</span>
                    </label>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeEditQrisModal()" class="px-4 py-2 text-sm text-gray-500 hover:bg-gray-100 rounded-xl font-medium">Batal</button>
                    <button type="submit" class="px-5 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Add Bank -->
    <div id="addBankModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl p-6 max-w-md w-full shadow-2xl border border-gray-100">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="p-1.5 bg-emerald-50 text-emerald-600 rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                </span>
                Tambah Rekening Bank
            </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_bank">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Nama Bank (Cth: BCA, Mandiri, BRI)</label>
                    <input type="text" name="bank_name" placeholder="BCA" required class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Kode Singkat (Cth: BCA)</label>
                    <input type="text" name="bank_code" placeholder="BCA" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Nomor Rekening</label>
                    <input type="text" name="account_number" placeholder="8280912345" required class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Atas Nama Rekening</label>
                    <input type="text" name="account_holder" placeholder="NPGLOW INDONESIA" required class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeAddBankModal()" class="px-4 py-2 text-sm text-gray-500 hover:bg-gray-100 rounded-xl font-medium">Batal</button>
                    <button type="submit" class="px-5 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold">Simpan Rekening</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Bank -->
    <div id="editBankModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl p-6 max-w-md w-full shadow-2xl border border-gray-100">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="p-1.5 bg-blue-50 text-blue-600 rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                </span>
                Edit Rekening Bank
            </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit_bank">
                <input type="hidden" name="bank_id" id="edit_bank_id">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Nama Bank</label>
                    <input type="text" name="bank_name" id="edit_bank_name" required class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Kode Singkat</label>
                    <input type="text" name="bank_code" id="edit_bank_code" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Nomor Rekening</label>
                    <input type="text" name="account_number" id="edit_account_number" required class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Atas Nama Rekening</label>
                    <input type="text" name="account_holder" id="edit_account_holder" required class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>
                <div>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1" class="w-4 h-4 rounded text-primary focus:ring-primary/30 border-gray-300">
                        <span class="text-sm font-semibold text-gray-700">Aktifkan rekening ini</span>
                    </label>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeEditBankModal()" class="px-4 py-2 text-sm text-gray-500 hover:bg-gray-100 rounded-xl font-medium">Batal</button>
                    <button type="submit" class="px-5 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddQrisModal() {
            document.getElementById('addQrisModal').classList.remove('hidden');
        }
        function closeAddQrisModal() {
            document.getElementById('addQrisModal').classList.add('hidden');
        }
        function openEditQrisModal(data) {
            document.getElementById('edit_qris_id').value = data.id;
            document.getElementById('edit_qris_merchant').value = data.merchant_name;
            document.getElementById('edit_qris_nmid').value = data.nmid || '';
            document.getElementById('edit_qris_is_active').checked = data.is_active == 1;
            document.getElementById('edit_qris_is_primary').checked = data.is_primary == 1;
            document.getElementById('editQrisModal').classList.remove('hidden');
        }
        function closeEditQrisModal() {
            document.getElementById('editQrisModal').classList.add('hidden');
        }
        function confirmDeleteQris(id) {
            Swal.fire({
                title: 'Hapus QRIS Statis?',
                text: 'QR Code ini akan dihapus dari daftar metode pembayaran.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'payments.php?delete_qris=' + id;
                }
            });
        }
        function previewImage(src, title) {
            Swal.fire({
                title: title,
                imageUrl: src,
                imageAlt: title,
                imageWidth: 320,
                imageHeight: 320,
                confirmButtonText: 'Tutup',
                confirmButtonColor: '#3ca6f2'
            });
        }
        function openAddBankModal() {
            document.getElementById('addBankModal').classList.remove('hidden');
        }
        function closeAddBankModal() {
            document.getElementById('addBankModal').classList.add('hidden');
        }
        function openEditBankModal(data) {
            document.getElementById('edit_bank_id').value = data.id;
            document.getElementById('edit_bank_name').value = data.bank_name;
            document.getElementById('edit_bank_code').value = data.bank_code;
            document.getElementById('edit_account_number').value = data.account_number;
            document.getElementById('edit_account_holder').value = data.account_holder;
            document.getElementById('edit_is_active').checked = data.is_active == 1;
            document.getElementById('editBankModal').classList.remove('hidden');
        }
        function closeEditBankModal() {
            document.getElementById('editBankModal').classList.add('hidden');
        }
        function confirmDeleteBank(id) {
            Swal.fire({
                title: 'Hapus Rekening?',
                text: 'Rekening bank ini tidak akan muncul lagi di opsi pembayaran.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'payments.php?delete_bank=' + id;
                }
            });
        }
    </script>
</body>
</html>
