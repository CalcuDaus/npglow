<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth-helper.php';
require_once __DIR__ . '/../includes/reseller-helper.php';
require_once __DIR__ . '/../includes/icon-helper.php';

guard_admin_only();

$successMsg = '';
$errorMsg = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Create New Reseller
    if ($action === 'create_reseller') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $storeName = trim($_POST['store_name'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $customCode = trim($_POST['referral_code'] ?? '');

        if (empty($name) || empty($email) || empty($password) || empty($storeName)) {
            $errorMsg = "Nama, email, password, dan nama toko wajib diisi.";
        } else {
            // Check email uniqueness
            $chk = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $chk->bind_param("s", $email);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errorMsg = "Email sudah terdaftar. Gunakan email lain.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $role = 'reseller';

                $uStmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                $uStmt->bind_param("ssss", $name, $email, $hashedPassword, $role);
                
                if ($uStmt->execute()) {
                    $newUserId = $uStmt->insert_id;

                    // Generate or validate referral code
                    $refCode = !empty($customCode) ? strtoupper($customCode) : generate_referral_code($conn, 'NP');
                    // Ensure unique referral code
                    $cChk = $conn->prepare("SELECT id FROM reseller_stores WHERE referral_code = ?");
                    $cChk->bind_param("s", $refCode);
                    $cChk->execute();
                    if ($cChk->get_result()->num_rows > 0) {
                        $refCode = generate_referral_code($conn, 'NP');
                    }

                    $slug = generate_store_slug($conn, $storeName);

                    $sStmt = $conn->prepare("
                        INSERT INTO reseller_stores (user_id, store_name, store_slug, referral_code, city, province, whatsapp, is_active, is_verified)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)
                    ");
                    $sStmt->bind_param("issssss", $newUserId, $storeName, $slug, $refCode, $city, $province, $whatsapp);
                    $sStmt->execute();

                    $successMsg = "Akun reseller <strong>" . htmlspecialchars($storeName) . "</strong> berhasil dibuat! Kode Referral: <strong>" . $refCode . "</strong>";
                } else {
                    $errorMsg = "Gagal membuat akun user: " . $conn->error;
                }
            }
        }
    }

    // 2. Toggle Status Active
    if ($action === 'toggle_active') {
        $storeId = (int)($_POST['store_id'] ?? 0);
        $conn->query("UPDATE reseller_stores SET is_active = NOT is_active WHERE id = {$storeId}");
        $successMsg = "Status toko reseller berhasil diperbarui.";
    }

    // 3. Delete Reseller
    if ($action === 'delete_reseller') {
        $storeId = (int)($_POST['store_id'] ?? 0);
        $stmt = $conn->prepare("SELECT user_id FROM reseller_stores WHERE id = ?");
        $stmt->bind_param("i", $storeId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if ($res) {
            $uId = (int)$res['user_id'];
            $conn->query("DELETE FROM users WHERE id = {$uId}"); // Cascade deletes reseller_stores & products
            $successMsg = "Akun reseller dan toko berhasil dihapus.";
        }
    }
}

// Fetch All Reseller Stores with User info & Stats
$query = "
    SELECT rs.*, u.name as user_name, u.email as user_email,
           (SELECT COUNT(*) FROM orders WHERE reseller_store_id = rs.id) as total_orders,
           (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE reseller_store_id = rs.id AND payment_status = 'paid') as total_revenue,
           (SELECT COUNT(*) FROM users WHERE referred_by = rs.user_id) as total_customers,
           (SELECT COUNT(*) FROM reseller_products WHERE reseller_store_id = rs.id) as total_products
    FROM reseller_stores rs
    JOIN users u ON rs.user_id = u.id
    ORDER BY rs.id DESC
";
$resellers = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Reseller - NPGLOW Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: { primary: '#3ca6f2', 'primary-dark': '#2e8ccf' },
                fontFamily: { sans: ['Inter', 'sans-serif'] }
            }}
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="flex min-h-screen">
        <?php $activeNav = 'resellers'; include 'sidebar.php'; ?>

        <div class="flex-1 flex flex-col min-w-0">
            <?php $pageTitle = 'Kelola Toko Reseller'; include 'topbar.php'; ?>

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <!-- Notifications -->
                <?php if ($successMsg): ?>
                <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold flex items-center gap-3">
                    <?= npglow_icon('check', 'w-5 h-5 text-emerald-600') ?>
                    <span><?= $successMsg ?></span>
                </div>
                <?php endif; ?>
                <?php if ($errorMsg): ?>
                <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold flex items-center gap-3">
                    <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    <span><?= htmlspecialchars($errorMsg) ?></span>
                </div>
                <?php endif; ?>

                <!-- Header & Create Button -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-xl font-extrabold text-gray-800">Daftar Mitra Reseller</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Daftarkan akun reseller baru dan pantau performa jaringan toko cabang</p>
                    </div>

                    <button onclick="openCreateModal()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-500 text-white text-xs sm:text-sm font-bold rounded-xl hover:bg-emerald-600 transition shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Daftarkan Reseller Baru
                    </button>
                </div>

                <!-- Resellers Table / Grid -->
                <?php if (empty($resellers)): ?>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    </div>
                    <h3 class="text-base font-bold text-gray-800 mb-1">Belum Ada Reseller</h3>
                    <p class="text-xs text-gray-500 mb-4">Klik tombol di atas untuk mendaftarkan akun mitra reseller pertama Anda.</p>
                    <button onclick="openCreateModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-500 text-white text-xs font-bold rounded-xl hover:bg-emerald-600 transition">
                        Tambah Reseller
                    </button>
                </div>
                <?php else: ?>
                <div class="grid gap-4">
                    <?php foreach ($resellers as $r): ?>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-6 transition hover:shadow-md">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-gray-100">
                            <!-- Store Brand & Owner -->
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center font-black text-lg flex-shrink-0">
                                    <?php if (!empty($r['store_logo'])): ?>
                                    <img src="../<?= htmlspecialchars($r['store_logo']) ?>" alt="" class="w-full h-full object-cover rounded-2xl">
                                    <?php else: ?>
                                    <?= strtoupper(substr($r['store_name'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-base font-bold text-gray-800 truncate"><?= htmlspecialchars($r['store_name']) ?></h3>
                                        <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full <?= $r['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' ?>">
                                            <?= $r['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        Pemilik: <strong class="text-gray-700"><?= htmlspecialchars($r['user_name']) ?></strong> (<?= htmlspecialchars($r['user_email']) ?>)
                                    </p>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        📍 <?= htmlspecialchars($r['city'] ?: 'Kota belum diset') ?> <?php if ($r['province']): ?>, <?= htmlspecialchars($r['province']) ?><?php endif; ?>
                                        <?php if ($r['whatsapp']): ?> • WA: <?= htmlspecialchars($r['whatsapp']) ?><?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Referral Code Pill -->
                            <div class="flex items-center gap-3">
                                <div class="bg-gray-50 border border-gray-200 px-3 py-2 rounded-xl text-center">
                                    <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider block">Kode Referral</span>
                                    <span class="text-sm font-mono font-black text-emerald-700"><?= htmlspecialchars($r['referral_code']) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Stats Row -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 py-4 text-center">
                            <div class="bg-gray-50/70 p-3 rounded-xl">
                                <p class="text-lg font-extrabold text-gray-800"><?= $r['total_orders'] ?></p>
                                <p class="text-[10px] text-gray-400 font-semibold uppercase">Pesanan</p>
                            </div>
                            <div class="bg-gray-50/70 p-3 rounded-xl">
                                <p class="text-lg font-extrabold text-emerald-600">Rp <?= number_format((float)$r['total_revenue'], 0, ',', '.') ?></p>
                                <p class="text-[10px] text-gray-400 font-semibold uppercase">Total Penjualan</p>
                            </div>
                            <div class="bg-gray-50/70 p-3 rounded-xl">
                                <p class="text-lg font-extrabold text-gray-800"><?= $r['total_customers'] ?></p>
                                <p class="text-[10px] text-gray-400 font-semibold uppercase">Pelanggan</p>
                            </div>
                            <div class="bg-gray-50/70 p-3 rounded-xl">
                                <p class="text-lg font-extrabold text-gray-800"><?= $r['total_products'] ?></p>
                                <p class="text-[10px] text-gray-400 font-semibold uppercase">Katalog Toko</p>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="pt-3 border-t border-gray-100 flex items-center justify-end gap-2">
                            <button onclick="toggleActive(<?= $r['id'] ?>, '<?= htmlspecialchars($r['store_name']) ?>', <?= $r['is_active'] ? 'true' : 'false' ?>)" class="px-3 py-1.5 text-xs font-bold rounded-lg border transition <?= $r['is_active'] ? 'text-amber-700 bg-amber-50 border-amber-200 hover:bg-amber-100' : 'text-emerald-700 bg-emerald-50 border-emerald-200 hover:bg-emerald-100' ?>">
                                <?= $r['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </button>
                            <button onclick="deleteReseller(<?= $r['id'] ?>, '<?= htmlspecialchars($r['store_name']) ?>')" class="px-3 py-1.5 text-xs font-bold text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition">
                                Hapus Reseller
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Hidden Form -->
    <form id="actionForm" method="POST" class="hidden">
        <input type="hidden" name="action" id="formAction">
        <input type="hidden" name="store_id" id="formStoreId">
    </form>

    <script>
        function openCreateModal() {
            Swal.fire({
                title: 'Daftarkan Reseller Baru',
                html: `
                    <form id="swalCreateForm" class="text-left space-y-3 pt-2">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-bold text-gray-700">Nama Pemilik *</label>
                                <input id="swal-name" type="text" placeholder="Budi Santoso" class="w-full mt-1 p-2 border border-gray-300 rounded-xl text-xs font-medium" required>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-700">Nama Toko / Cabang *</label>
                                <input id="swal-store" type="text" placeholder="NPGLOW Store Bandung" class="w-full mt-1 p-2 border border-gray-300 rounded-xl text-xs font-medium" required>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-bold text-gray-700">Email Akun *</label>
                                <input id="swal-email" type="email" placeholder="budi@npglow.com" class="w-full mt-1 p-2 border border-gray-300 rounded-xl text-xs font-medium" required>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-700">Password Akun *</label>
                                <input id="swal-password" type="password" placeholder="Minimal 6 karakter" class="w-full mt-1 p-2 border border-gray-300 rounded-xl text-xs font-medium" required>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-bold text-gray-700">Kota / Kabupaten</label>
                                <input id="swal-city" type="text" placeholder="Kota Bandung" class="w-full mt-1 p-2 border border-gray-300 rounded-xl text-xs font-medium">
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-700">Provinsi</label>
                                <input id="swal-province" type="text" placeholder="Jawa Barat" class="w-full mt-1 p-2 border border-gray-300 rounded-xl text-xs font-medium">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-bold text-gray-700">No. WhatsApp Toko</label>
                                <input id="swal-wa" type="text" placeholder="081234567890" class="w-full mt-1 p-2 border border-gray-300 rounded-xl text-xs font-medium">
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-700">Kode Referral (Opsional/Auto)</label>
                                <input id="swal-refcode" type="text" placeholder="Contoh: NP-BDG01" class="w-full mt-1 p-2 border border-gray-300 rounded-xl text-xs font-mono font-bold uppercase">
                            </div>
                        </div>
                    </form>
                `,
                showCancelButton: true,
                confirmButtonText: 'Buat Akun Reseller',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#10b981',
                width: '600px',
                preConfirm: () => {
                    const name = document.getElementById('swal-name').value.trim();
                    const store = document.getElementById('swal-store').value.trim();
                    const email = document.getElementById('swal-email').value.trim();
                    const password = document.getElementById('swal-password').value;
                    const city = document.getElementById('swal-city').value.trim();
                    const province = document.getElementById('swal-province').value.trim();
                    const wa = document.getElementById('swal-wa').value.trim();
                    const refcode = document.getElementById('swal-refcode').value.trim();

                    if (!name || !store || !email || !password) {
                        Swal.showValidationMessage('Nama, Toko, Email, dan Password wajib diisi.');
                        return false;
                    }
                    return { name, store_name: store, email, password, city, province, whatsapp: wa, referral_code: refcode };
                }
            }).then(res => {
                if (res.isConfirmed && res.value) {
                    const f = document.createElement('form');
                    f.method = 'POST';
                    f.innerHTML = `
                        <input type="hidden" name="action" value="create_reseller">
                        <input type="hidden" name="name" value="${res.value.name}">
                        <input type="hidden" name="store_name" value="${res.value.store_name}">
                        <input type="hidden" name="email" value="${res.value.email}">
                        <input type="hidden" name="password" value="${res.value.password}">
                        <input type="hidden" name="city" value="${res.value.city}">
                        <input type="hidden" name="province" value="${res.value.province}">
                        <input type="hidden" name="whatsapp" value="${res.value.whatsapp}">
                        <input type="hidden" name="referral_code" value="${res.value.referral_code}">
                    `;
                    document.body.appendChild(f);
                    f.submit();
                }
            });
        }

        function toggleActive(storeId, storeName, currentStatus) {
            const nextAction = currentStatus ? 'menonaktifkan' : 'mengaktifkan';
            Swal.fire({
                title: 'Konfirmasi',
                text: `Apakah Anda yakin ingin ${nextAction} toko "${storeName}"?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya',
                cancelButtonText: 'Batal'
            }).then(res => {
                if (res.isConfirmed) {
                    document.getElementById('formAction').value = 'toggle_active';
                    document.getElementById('formStoreId').value = storeId;
                    document.getElementById('actionForm').submit();
                }
            });
        }

        function deleteReseller(storeId, storeName) {
            Swal.fire({
                title: 'Hapus Reseller?',
                text: `Toko "${storeName}" beserta akun reseller akan dihapus permanen.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#ef4444'
            }).then(res => {
                if (res.isConfirmed) {
                    document.getElementById('formAction').value = 'delete_reseller';
                    document.getElementById('formStoreId').value = storeId;
                    document.getElementById('actionForm').submit();
                }
            });
        }
    </script>
</body>
</html>
