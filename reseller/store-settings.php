<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth-helper.php';
require_once __DIR__ . '/../includes/reseller-helper.php';
require_once __DIR__ . '/../includes/icon-helper.php';

guard_reseller_only();

$userId = (int)$_SESSION['user_id'];
$store = get_reseller_store_by_user($conn, $userId);
if (!$store) {
    header("Location: index.php");
    exit();
}
$storeId = (int)$store['id'];

$successMsg = '';
$errorMsg = '';

// Handle Save Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storeName = trim($_POST['store_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $lat = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $lng = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

    if (empty($storeName)) {
        $errorMsg = "Nama toko tidak boleh kosong.";
    } else {
        // Handle logo upload
        $logoPath = $store['store_logo'];
        if (!empty($_FILES['store_logo']['name'])) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $fileInfo = pathinfo($_FILES['store_logo']['name']);
            $ext = strtolower($fileInfo['extension'] ?? '');

            if (in_array($ext, $allowedExts)) {
                $newFilename = 'logo_' . $storeId . '_' . time() . '.' . $ext;
                $targetPath = __DIR__ . '/../uploads/reseller/logos/' . $newFilename;
                if (move_uploaded_file($_FILES['store_logo']['tmp_name'], $targetPath)) {
                    $logoPath = 'uploads/reseller/logos/' . $newFilename;
                }
            } else {
                $errorMsg = "Format file logo tidak valid (gunakan JPG, PNG, atau WebP).";
            }
        }

        if (empty($errorMsg)) {
            $stmt = $conn->prepare("
                UPDATE reseller_stores 
                SET store_name = ?, description = ?, phone = ?, whatsapp = ?,
                    address = ?, province = ?, city = ?, district = ?, postal_code = ?,
                    latitude = ?, longitude = ?, store_logo = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                "sssssssssddsi",
                $storeName, $description, $phone, $whatsapp,
                $address, $province, $city, $district, $postalCode,
                $lat, $lng, $logoPath, $storeId
            );

            if ($stmt->execute()) {
                $successMsg = "Pengaturan toko berhasil diperbarui!";
                // Refresh store data
                $store = get_reseller_store_by_user($conn, $userId);
            } else {
                $errorMsg = "Gagal menyimpan pengaturan toko: " . $conn->error;
            }
        }
    }
}

$currentLat = (float)($store['latitude'] ?? -6.2088); // Default Jakarta
$currentLng = (float)($store['longitude'] ?? 106.8456);
$hasCoords = !empty($store['latitude']) && !empty($store['longitude']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Toko - Reseller NPGLOW</title>
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
    <!-- Leaflet CSS & JS for OpenStreetMap Picker -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 flex flex-col min-w-0">
            <?php include 'topbar.php'; ?>

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <!-- Notifications -->
                <?php if ($successMsg): ?>
                <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold flex items-center gap-3">
                    <?= npglow_icon('check', 'w-5 h-5 text-emerald-600') ?>
                    <span><?= htmlspecialchars($successMsg) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($errorMsg): ?>
                <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold flex items-center gap-3">
                    <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    <span><?= htmlspecialchars($errorMsg) ?></span>
                </div>
                <?php endif; ?>

                <div class="max-w-4xl mx-auto">
                    <div class="mb-6">
                        <h2 class="text-xl font-extrabold text-gray-800">Pengaturan Profil Toko</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Atur identitas toko, kontak, alamat pengiriman, dan titik koordinat GPS lokasi toko</p>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <!-- Card 1: Identitas Toko -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-6">
                            <h3 class="text-base font-bold text-gray-800 mb-4 pb-3 border-b border-gray-100">Informasi Dasar</h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2 flex items-center gap-4 pb-4">
                                    <div class="w-16 h-16 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center justify-center overflow-hidden flex-shrink-0">
                                        <?php if (!empty($store['store_logo'])): ?>
                                        <img src="../<?= htmlspecialchars($store['store_logo']) ?>" alt="Logo" class="w-full h-full object-cover">
                                        <?php else: ?>
                                        <span class="text-2xl font-black text-emerald-600"><?= strtoupper(substr($store['store_name'], 0, 1)) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Logo Toko</label>
                                        <input type="file" name="store_logo" accept="image/*" class="text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Nama Toko *</label>
                                    <input type="text" name="store_name" value="<?= htmlspecialchars($store['store_name']) ?>" required class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Kode Referral (Terkunci)</label>
                                    <input type="text" value="<?= htmlspecialchars($store['referral_code']) ?>" readonly class="w-full p-2.5 bg-gray-100 border border-gray-200 rounded-xl text-sm font-mono font-bold text-emerald-700 cursor-not-allowed">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">No. WhatsApp Toko</label>
                                    <input type="text" name="whatsapp" value="<?= htmlspecialchars($store['whatsapp'] ?? '') ?>" placeholder="Contoh: 081234567890" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">No. Telepon Cadangan</label>
                                    <input type="text" name="phone" value="<?= htmlspecialchars($store['phone'] ?? '') ?>" placeholder="08..." class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Deskripsi / Slogan Toko</label>
                                    <textarea name="description" rows="2" placeholder="Reseller Resmi NPGLOW Melayani Pengiriman Cepat Area..." class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none"><?= htmlspecialchars($store['description'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Alamat Pengiriman & Titik Map -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-6">
                            <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100">
                                <div>
                                    <h3 class="text-base font-bold text-gray-800">Alamat & Lokasi GPS</h3>
                                    <p class="text-xs text-gray-400">Titik lokasi digunakan agar pembeli dapat menemukan toko reseller terdekat</p>
                                </div>
                                <button type="button" onclick="detectCurrentLocation()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold rounded-xl border border-emerald-200 transition">
                                    📍 Deteksi Lokasi Saya
                                </button>
                            </div>

                            <div class="space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Provinsi</label>
                                        <input type="text" name="province" value="<?= htmlspecialchars($store['province'] ?? '') ?>" placeholder="Jawa Barat" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Kota / Kabupaten</label>
                                        <input type="text" name="city" value="<?= htmlspecialchars($store['city'] ?? '') ?>" placeholder="Kota Bandung" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Kecamatan</label>
                                        <input type="text" name="district" value="<?= htmlspecialchars($store['district'] ?? '') ?>" placeholder="Coblong" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Kode Pos</label>
                                        <input type="text" name="postal_code" value="<?= htmlspecialchars($store['postal_code'] ?? '') ?>" placeholder="40132" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Alamat Lengkap</label>
                                    <textarea name="address" rows="2" placeholder="Jl. Sukajadi No. 123, RT 01 / RW 02..." class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none"><?= htmlspecialchars($store['address'] ?? '') ?></textarea>
                                </div>

                                <!-- Leaflet Interactive Map Picker -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Pilih Titik Lokasi pada Peta (Klik / Geser Pin)</label>
                                    <div id="map" class="w-full h-64 rounded-xl border border-gray-200 overflow-hidden shadow-inner z-0"></div>
                                    <div class="grid grid-cols-2 gap-3 mt-3">
                                        <div>
                                            <span class="text-[10px] font-bold text-gray-400 uppercase">Latitude</span>
                                            <input type="text" name="latitude" id="latInput" value="<?= htmlspecialchars($store['latitude'] ?? '') ?>" readonly class="w-full p-2 bg-gray-100 border border-gray-200 rounded-lg text-xs font-mono font-bold text-gray-700">
                                        </div>
                                        <div>
                                            <span class="text-[10px] font-bold text-gray-400 uppercase">Longitude</span>
                                            <input type="text" name="longitude" id="lngInput" value="<?= htmlspecialchars($store['longitude'] ?? '') ?>" readonly class="w-full p-2 bg-gray-100 border border-gray-200 rounded-lg text-xs font-mono font-bold text-gray-700">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-3 bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-sm rounded-xl shadow-lg shadow-emerald-500/20 transition flex items-center gap-2">
                                <?= npglow_icon('check', 'w-4 h-4') ?>
                                Simpan Perubahan Pengaturan
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script>
        const initialLat = <?= $currentLat ?>;
        const initialLng = <?= $currentLng ?>;
        const hasSavedCoords = <?= $hasCoords ? 'true' : 'false' ?>;

        const map = L.map('map').setView([initialLat, initialLng], hasSavedCoords ? 14 : 10);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);

        let marker = null;
        if (hasSavedCoords) {
            marker = L.marker([initialLat, initialLng], { draggable: true }).addTo(map);
            marker.on('dragend', function(e) {
                const pos = marker.getLatLng();
                updateInputs(pos.lat, pos.lng);
            });
        }

        map.on('click', function(e) {
            const lat = e.latlng.lat;
            const lng = e.latlng.lng;
            if (!marker) {
                marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                marker.on('dragend', function(evt) {
                    const pos = marker.getLatLng();
                    updateInputs(pos.lat, pos.lng);
                });
            } else {
                marker.setLatLng([lat, lng]);
            }
            updateInputs(lat, lng);
        });

        function updateInputs(lat, lng) {
            document.getElementById('latInput').value = lat.toFixed(7);
            document.getElementById('lngInput').value = lng.toFixed(7);
        }

        function detectCurrentLocation() {
            if (!navigator.geolocation) {
                alert('Browser Anda tidak mendukung geolokasi.');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                function(pos) {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    map.setView([lat, lng], 15);
                    if (!marker) {
                        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                    } else {
                        marker.setLatLng([lat, lng]);
                    }
                    updateInputs(lat, lng);
                },
                function(err) {
                    alert('Gagal mendeteksi lokasi: ' + err.message);
                }
            );
        }
    </script>
</body>
</html>
