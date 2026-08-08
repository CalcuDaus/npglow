<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth-helper.php';
require_once __DIR__ . '/../includes/reseller-helper.php';
require_once __DIR__ . '/../includes/icon-helper.php';

guard_reseller_only();

$userId = (int)$_SESSION['user_id'];
$store = get_reseller_store_by_user($conn, $userId);
if (!$store) { header("Location: index.php"); exit(); }

$storeId = (int)$store['id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'add_product') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $customPrice = !empty($_POST['custom_price']) ? (float)$_POST['custom_price'] : null;
        $stock = (int)($_POST['stock'] ?? 0);

        // Validate minimum price
        if ($customPrice !== null) {
            $pStmt = $conn->prepare("SELECT minimum_price, price FROM products WHERE id = ?");
            $pStmt->bind_param("i", $productId);
            $pStmt->execute();
            $prod = $pStmt->get_result()->fetch_assoc();
            $minPrice = (float)($prod['minimum_price'] ?? $prod['price'] ?? 0);
            if ($customPrice < $minPrice) {
                echo json_encode(['success' => false, 'error' => "Harga minimum adalah Rp " . number_format($minPrice, 0, ',', '.')]);
                exit();
            }
        }

        $stmt = $conn->prepare("INSERT INTO reseller_products (reseller_store_id, product_id, custom_price, stock, is_available) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE custom_price = VALUES(custom_price), stock = VALUES(stock), is_available = 1");
        $stmt->bind_param("iidi", $storeId, $productId, $customPrice, $stock);
        echo json_encode(['success' => $stmt->execute()]);
        exit();
    }

    if ($action === 'update_product') {
        $rpId = (int)($_POST['rp_id'] ?? 0);
        $customPrice = !empty($_POST['custom_price']) ? (float)$_POST['custom_price'] : null;
        $stock = (int)($_POST['stock'] ?? 0);

        // Validate minimum price
        if ($customPrice !== null) {
            $pStmt = $conn->prepare("SELECT p.minimum_price, p.price FROM reseller_products rp JOIN products p ON rp.product_id = p.id WHERE rp.id = ?");
            $pStmt->bind_param("i", $rpId);
            $pStmt->execute();
            $prod = $pStmt->get_result()->fetch_assoc();
            $minPrice = (float)($prod['minimum_price'] ?? $prod['price'] ?? 0);
            if ($customPrice < $minPrice) {
                echo json_encode(['success' => false, 'error' => "Harga minimum adalah Rp " . number_format($minPrice, 0, ',', '.')]);
                exit();
            }
        }

        $stmt = $conn->prepare("UPDATE reseller_products SET custom_price = ?, stock = ? WHERE id = ? AND reseller_store_id = ?");
        $stmt->bind_param("diii", $customPrice, $stock, $rpId, $storeId);
        echo json_encode(['success' => $stmt->execute()]);
        exit();
    }

    if ($action === 'toggle_product') {
        $rpId = (int)($_POST['rp_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE reseller_products SET is_available = NOT is_available WHERE id = ? AND reseller_store_id = ?");
        $stmt->bind_param("ii", $rpId, $storeId);
        echo json_encode(['success' => $stmt->execute()]);
        exit();
    }

    if ($action === 'remove_product') {
        $rpId = (int)($_POST['rp_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM reseller_products WHERE id = ? AND reseller_store_id = ?");
        $stmt->bind_param("ii", $rpId, $storeId);
        echo json_encode(['success' => $stmt->execute()]);
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit();
}

// Fetch my products (already in reseller_products)
$myProducts = [];
$stmt = $conn->prepare("
    SELECT rp.*, p.name, p.description, p.price as base_price, p.minimum_price, p.image_url,
           COALESCE(rp.custom_price, p.price) as display_price
    FROM reseller_products rp
    JOIN products p ON rp.product_id = p.id
    WHERE rp.reseller_store_id = ?
    ORDER BY rp.created_at DESC
");
$stmt->bind_param("i", $storeId);
$stmt->execute();
$myProducts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$myProductIds = array_column($myProducts, 'product_id');

// Fetch available catalog products (not yet added)
$allProducts = [];
$res = $conn->query("SELECT id, name, description, price, minimum_price, image_url FROM products ORDER BY id DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if (!in_array($row['id'], $myProductIds)) {
            $allProducts[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produk Saya - Reseller NPGLOW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: { primary: '#10b981', 'primary-dark': '#059669' },
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
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 flex flex-col min-w-0">
            <?php include 'topbar.php'; ?>

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <!-- Header -->
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-extrabold text-gray-800">Produk Saya</h2>
                    <?php if (!empty($allProducts)): ?>
                    <button onclick="showAddProduct()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-500 text-white text-sm font-bold rounded-xl hover:bg-emerald-600 transition shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Tambah Produk
                    </button>
                    <?php endif; ?>
                </div>

                <?php if (empty($myProducts)): ?>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-4">
                        <?= npglow_icon('package', 'w-8 h-8') ?>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Belum ada produk</h3>
                    <p class="text-sm text-gray-500 mb-4">Tambahkan produk dari katalog NPGLOW untuk mulai berjualan.</p>
                    <?php if (!empty($allProducts)): ?>
                    <button onclick="showAddProduct()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-500 text-white text-sm font-bold rounded-xl hover:bg-emerald-600 transition">
                        Tambah Produk Pertama
                    </button>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="grid gap-4">
                    <?php foreach ($myProducts as $rp): ?>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 flex items-start gap-4" id="rp-<?= $rp['id'] ?>">
                        <!-- Product Image -->
                        <?php if (!empty($rp['image_url'])): ?>
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl overflow-hidden bg-gray-100 flex-shrink-0 border border-gray-100">
                            <img src="../<?= htmlspecialchars($rp['image_url']) ?>" alt="" class="w-full h-full object-cover">
                        </div>
                        <?php else: ?>
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl bg-emerald-50 flex items-center justify-center flex-shrink-0">
                            <?= npglow_icon('package', 'w-8 h-8 text-emerald-600') ?>
                        </div>
                        <?php endif; ?>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="text-sm sm:text-base font-bold text-gray-800 truncate"><?= htmlspecialchars($rp['name']) ?></h3>
                                    <p class="text-xs text-gray-400 mt-0.5">Harga dasar: Rp <?= number_format($rp['base_price'], 0, ',', '.') ?> • Min: Rp <?= number_format($rp['minimum_price'] ?? $rp['base_price'], 0, ',', '.') ?></p>
                                </div>
                                <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full flex-shrink-0 <?= $rp['is_available'] ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' ?>">
                                    <?= $rp['is_available'] ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-5 gap-y-1 mt-2.5">
                                <div>
                                    <span class="text-[10px] text-gray-400 font-semibold uppercase">Harga Jual</span>
                                    <p class="text-sm font-extrabold text-emerald-700">Rp <?= number_format($rp['display_price'], 0, ',', '.') ?></p>
                                </div>
                                <div>
                                    <span class="text-[10px] text-gray-400 font-semibold uppercase">Stok</span>
                                    <p class="text-sm font-extrabold text-gray-800"><?= $rp['stock'] ?></p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 mt-3">
                                <button onclick="editProduct(<?= $rp['id'] ?>, <?= $rp['custom_price'] !== null ? $rp['custom_price'] : 'null' ?>, <?= $rp['stock'] ?>, <?= $rp['minimum_price'] ?? $rp['base_price'] ?>)" class="text-xs font-bold text-blue-600 hover:text-blue-800 px-2.5 py-1 rounded-lg hover:bg-blue-50 transition">Edit</button>
                                <button onclick="toggleProduct(<?= $rp['id'] ?>)" class="text-xs font-bold text-amber-600 hover:text-amber-800 px-2.5 py-1 rounded-lg hover:bg-amber-50 transition"><?= $rp['is_available'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                                <button onclick="removeProduct(<?= $rp['id'] ?>)" class="text-xs font-bold text-red-500 hover:text-red-700 px-2.5 py-1 rounded-lg hover:bg-red-50 transition">Hapus</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        const availableProducts = <?= json_encode($allProducts) ?>;

        function showAddProduct() {
            if (availableProducts.length === 0) {
                Swal.fire('Info', 'Semua produk dari katalog sudah ditambahkan.', 'info');
                return;
            }
            const options = {};
            availableProducts.forEach(p => {
                options[p.id] = `${p.name} (Rp ${parseInt(p.price).toLocaleString('id-ID')})`;
            });

            Swal.fire({
                title: 'Tambah Produk',
                html: `
                    <div class="text-left space-y-3">
                        <div><label class="text-sm font-semibold text-gray-700">Pilih Produk</label>
                        <select id="swal-product" class="w-full mt-1 p-2.5 border border-gray-300 rounded-xl text-sm">
                            ${availableProducts.map(p => `<option value="${p.id}" data-min="${p.minimum_price || p.price}">${p.name} (Rp ${parseInt(p.price).toLocaleString('id-ID')})</option>`).join('')}
                        </select></div>
                        <div><label class="text-sm font-semibold text-gray-700">Harga Jual (kosongkan = harga dasar)</label>
                        <input id="swal-price" type="number" class="w-full mt-1 p-2.5 border border-gray-300 rounded-xl text-sm" placeholder="Harga jual kustom"></div>
                        <div><label class="text-sm font-semibold text-gray-700">Stok</label>
                        <input id="swal-stock" type="number" value="10" class="w-full mt-1 p-2.5 border border-gray-300 rounded-xl text-sm"></div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Tambahkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#10b981',
                preConfirm: () => {
                    const productId = document.getElementById('swal-product').value;
                    const price = document.getElementById('swal-price').value;
                    const stock = document.getElementById('swal-stock').value;
                    return { product_id: productId, custom_price: price, stock: stock };
                }
            }).then(result => {
                if (result.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'add_product');
                    fd.append('product_id', result.value.product_id);
                    fd.append('custom_price', result.value.custom_price);
                    fd.append('stock', result.value.stock);
                    fetch('products.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) location.reload();
                            else Swal.fire('Error', data.error || 'Gagal menambahkan.', 'error');
                        });
                }
            });
        }

        function editProduct(rpId, currentPrice, currentStock, minPrice) {
            Swal.fire({
                title: 'Edit Produk',
                html: `
                    <div class="text-left space-y-3">
                        <div><label class="text-sm font-semibold text-gray-700">Harga Jual (min: Rp ${parseInt(minPrice).toLocaleString('id-ID')})</label>
                        <input id="swal-price" type="number" value="${currentPrice || ''}" class="w-full mt-1 p-2.5 border border-gray-300 rounded-xl text-sm" placeholder="Kosongkan = harga dasar"></div>
                        <div><label class="text-sm font-semibold text-gray-700">Stok</label>
                        <input id="swal-stock" type="number" value="${currentStock}" class="w-full mt-1 p-2.5 border border-gray-300 rounded-xl text-sm"></div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Simpan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#10b981',
                preConfirm: () => ({
                    custom_price: document.getElementById('swal-price').value,
                    stock: document.getElementById('swal-stock').value
                })
            }).then(result => {
                if (result.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'update_product');
                    fd.append('rp_id', rpId);
                    fd.append('custom_price', result.value.custom_price);
                    fd.append('stock', result.value.stock);
                    fetch('products.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) location.reload();
                            else Swal.fire('Error', data.error || 'Gagal menyimpan.', 'error');
                        });
                }
            });
        }

        function toggleProduct(rpId) {
            const fd = new FormData();
            fd.append('action', 'toggle_product');
            fd.append('rp_id', rpId);
            fetch('products.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => { if (data.success) location.reload(); });
        }

        function removeProduct(rpId) {
            Swal.fire({
                title: 'Hapus Produk?',
                text: 'Produk akan dihapus dari toko Anda.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#ef4444'
            }).then(result => {
                if (result.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'remove_product');
                    fd.append('rp_id', rpId);
                    fetch('products.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => { if (data.success) location.reload(); });
                }
            });
        }
    </script>
</body>
</html>
