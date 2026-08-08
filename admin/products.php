<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/image-helper.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        
        $image_url = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "../assets/images/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $new_filename = generate_unique_webp_filename('product');
            $target_file = $target_dir . $new_filename;
            
            $convertResult = convert_image_to_webp($_FILES["image"]["tmp_name"], $target_file, 85, 1200, 1200);
            if ($convertResult['success']) {
                $image_url = "assets/images/" . $new_filename;
            }
        }

        $stmt = $conn->prepare("INSERT INTO products (name, description, price, image_url) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssds", $name, $description, $price, $image_url);
        $stmt->execute();
        
        header("Location: products.php?msg=created");
        exit();
    } elseif ($action === 'update') {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        
        $stmt = $conn->prepare("SELECT image_url FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $old_image = $stmt->get_result()->fetch_assoc()['image_url'];

        $image_url = $old_image;
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "../assets/images/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $new_filename = generate_unique_webp_filename('product');
            $target_file = $target_dir . $new_filename;
            
            $convertResult = convert_image_to_webp($_FILES["image"]["tmp_name"], $target_file, 85, 1200, 1200);
            if ($convertResult['success']) {
                $image_url = "assets/images/" . $new_filename;
                if (!empty($old_image) && file_exists("../" . $old_image)) {
                    unlink("../" . $old_image);
                }
            }
        }

        $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, image_url = ? WHERE id = ?");
        $stmt->bind_param("ssdsi", $name, $description, $price, $image_url, $id);
        $stmt->execute();
        
        header("Location: products.php?msg=updated");
        exit();
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        
        $stmt = $conn->prepare("SELECT image_url FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $old_image = $stmt->get_result()->fetch_assoc()['image_url'];

        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        if (!empty($old_image) && file_exists("../" . $old_image)) {
            unlink("../" . $old_image);
        }

        header("Location: products.php?msg=deleted");
        exit();
    }
}

$productsResult = $conn->query("SELECT * FROM products ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Produk - NPGLOW Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-2xl font-bold text-gray-800">Daftar Produk</h2>
                <button onclick="openModal('modal-add')" class="bg-[#3ca6f2] hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition shadow-sm flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Tambah Produk
                </button>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        let msg = "<?= htmlspecialchars($_GET['msg']) ?>";
                        let text = "";

                        if (msg === 'created') {
                            text = "Produk berhasil ditambahkan.";
                        } else if (msg === 'updated') {
                            text = "Produk berhasil diperbarui.";
                        } else if (msg === 'deleted') {
                            text = "Produk berhasil dihapus.";
                        }

                        if(text) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: text,
                                confirmButtonColor: '#3ca6f2',
                                timer: 3000,
                                timerProgressBar: true
                            });
                            
                            // Remove msg param from URL so it doesn't show again on refresh
                            window.history.replaceState(null, null, window.location.pathname);
                        }
                    });
                </script>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-sm text-gray-600">
                                <th class="p-4 font-semibold w-24">Gambar</th>
                                <th class="p-4 font-semibold">Nama Produk</th>
                                <th class="p-4 font-semibold">Harga</th>
                                <th class="p-4 font-semibold hidden md:table-cell">Deskripsi</th>
                                <th class="p-4 font-semibold w-32 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if ($productsResult->num_rows > 0): ?>
                                <?php while($p = $productsResult->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="p-4">
                                            <?php if (!empty($p['image_url'])): ?>
                                                <img src="../<?= htmlspecialchars($p['image_url']) ?>" alt="Produk" class="w-16 h-16 object-cover rounded-lg shadow-sm border border-gray-100">
                                            <?php else: ?>
                                                <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 font-medium text-gray-800"><?= htmlspecialchars($p['name']) ?></td>
                                        <td class="p-4 text-[#3ca6f2] font-semibold">Rp <?= number_format($p['price'], 0, ',', '.') ?></td>
                                        <td class="p-4 text-sm text-gray-500 hidden md:table-cell max-w-xs truncate"><?= htmlspecialchars($p['description']) ?></td>
                                        <td class="p-4">
                                            <div class="flex justify-center gap-2">
                                                <button onclick="openEditModal(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', <?= $p['price'] ?>, '<?= htmlspecialchars(addslashes($p['description'])) ?>')" class="p-2 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition" title="Edit">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                </button>
                                                <form method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus produk ini?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                    <button type="submit" class="p-2 text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition" title="Hapus">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-gray-500">Belum ada produk.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Add Product -->
    <div id="modal-add" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-opacity duration-300">
        <div class="absolute inset-0 bg-black/50" onclick="closeModal('modal-add')"></div>
        <div id="modal-add-content" class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden relative transform scale-95 opacity-0 transition-all duration-300">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-800">Tambah Produk</h3>
                <button onclick="closeModal('modal-add')" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6">
                <input type="hidden" name="action" value="create">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Produk</label>
                        <input type="text" name="name" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#3ca6f2] focus:border-[#3ca6f2] outline-none transition">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Harga (Rp)</label>
                        <input type="number" name="price" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#3ca6f2] focus:border-[#3ca6f2] outline-none transition">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gambar Produk</label>
                        <input type="file" name="image" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#3ca6f2] focus:border-[#3ca6f2] outline-none transition file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                        <textarea name="description" rows="3" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#3ca6f2] focus:border-[#3ca6f2] outline-none transition"></textarea>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('modal-add')" class="px-5 py-2.5 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl font-medium transition">Batal</button>
                    <button type="submit" class="px-5 py-2.5 text-white bg-[#3ca6f2] hover:bg-blue-600 rounded-xl font-medium transition shadow-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Product -->
    <div id="modal-edit" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-opacity duration-300">
        <div class="absolute inset-0 bg-black/50" onclick="closeModal('modal-edit')"></div>
        <div id="modal-edit-content" class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden relative transform scale-95 opacity-0 transition-all duration-300">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-800">Edit Produk</h3>
                <button onclick="closeModal('modal-edit')" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit-id">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Produk</label>
                        <input type="text" name="name" id="edit-name" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#3ca6f2] focus:border-[#3ca6f2] outline-none transition">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Harga (Rp)</label>
                        <input type="number" name="price" id="edit-price" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#3ca6f2] focus:border-[#3ca6f2] outline-none transition">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gambar Produk <span class="text-xs text-gray-400 font-normal">(Kosongkan jika tidak ingin mengubah)</span></label>
                        <input type="file" name="image" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#3ca6f2] focus:border-[#3ca6f2] outline-none transition file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                        <textarea name="description" id="edit-description" rows="3" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#3ca6f2] focus:border-[#3ca6f2] outline-none transition"></textarea>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('modal-edit')" class="px-5 py-2.5 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl font-medium transition">Batal</button>
                    <button type="submit" class="px-5 py-2.5 text-white bg-[#3ca6f2] hover:bg-blue-600 rounded-xl font-medium transition shadow-sm">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            const modal = document.getElementById(id);
            const content = document.getElementById(id + '-content');
            
            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.classList.add('opacity-100');
            
            if (content) {
                setTimeout(() => {
                    content.classList.remove('scale-95', 'opacity-0');
                    content.classList.add('scale-100', 'opacity-100');
                }, 10);
            }
        }

        function closeModal(id) {
            const modal = document.getElementById(id);
            const content = document.getElementById(id + '-content');
            
            if (content) {
                content.classList.remove('scale-100', 'opacity-100');
                content.classList.add('scale-95', 'opacity-0');
            }
            
            setTimeout(() => {
                modal.classList.remove('opacity-100');
                modal.classList.add('opacity-0', 'pointer-events-none');
            }, 300);
        }

        function openEditModal(id, name, price, description) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-name').value = name;
            document.getElementById('edit-price').value = price;
            document.getElementById('edit-description').value = description;
            openModal('modal-edit');
        }
    </script>
</body>
</html>
