<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth-helper.php';
require_once 'includes/reseller-helper.php';
require_once 'includes/icon-helper.php';

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$currentStore = $userId > 0 ? get_user_reseller_store($conn, $userId) : null;

// Initial fetch of all active stores
$storesQuery = $conn->query("
    SELECT id, user_id, store_name, store_slug, referral_code, description, phone, whatsapp,
           address, province, city, district, postal_code, latitude, longitude, store_logo
    FROM reseller_stores
    WHERE is_active = 1
    ORDER BY id DESC
");
$allStores = $storesQuery ? $storesQuery->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cari Mitra Reseller Terdekat - NPGLOW</title>
    <meta name="description" content="Temukan mitra resmi dan toko reseller NPGLOW terdekat dari lokasi Anda untuk belanja lebih cepat dan ongkir lebih hemat.">
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
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        #resellerMap { height: 360px; z-index: 10; }
        @media (min-width: 768px) {
            #resellerMap { height: 460px; }
        }
        .store-card.active-store {
            border-color: #10b981;
            background: #f0fdf4;
        }
    </style>
</head>
<body class="bg-gray-50 text-slate-800 antialiased min-h-screen pb-20">

    <!-- Header -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-lg border-b border-gray-200 shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-3.5 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="<?= isset($_SESSION['user_id']) ? 'dashboard.php' : 'index.php' ?>" class="p-2 -ml-2 text-gray-500 hover:text-primary transition rounded-full">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <div>
                    <h1 class="text-base sm:text-lg font-extrabold text-gray-900 tracking-tight">Mitra Resmi NPGLOW</h1>
                    <p class="text-[11px] sm:text-xs text-gray-500">Temukan toko reseller terdekat dari lokasimu</p>
                </div>
            </div>
            <a href="shop.php" class="px-3 py-1.5 bg-primary/10 text-primary hover:bg-primary hover:text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5">
                <?= npglow_icon('shop-bag', 'w-4 h-4') ?>
                <span>Ke Katalog</span>
            </a>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-3 sm:px-4 py-4 sm:py-6 space-y-4 sm:space-y-6">

        <!-- Current Active Store Banner -->
        <?php if ($currentStore): ?>
        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
                    <?= strtoupper(substr($currentStore['store_name'], 0, 1)) ?>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-full">Toko Aktif Anda</span>
                        <span class="text-xs font-mono font-bold text-emerald-700"><?= htmlspecialchars($currentStore['referral_code']) ?></span>
                    </div>
                    <p class="text-sm font-extrabold text-gray-800 truncate"><?= htmlspecialchars($currentStore['store_name']) ?> (<?= htmlspecialchars($currentStore['city']) ?>)</p>
                </div>
            </div>
            <button onclick="revertToOfficialStore()" class="px-3 py-1.5 bg-white text-gray-600 hover:text-red-600 text-xs font-semibold rounded-xl border border-gray-200 transition whitespace-nowrap">
                Beralih ke Pusat
            </button>
        </div>
        <?php endif; ?>

        <!-- Geolocation & Search Bar -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 space-y-3">
            <div class="flex flex-col sm:flex-row gap-2.5">
                <div class="relative flex-1">
                    <input type="text" id="searchInput" placeholder="Cari nama toko, kota, atau provinsi..." 
                           class="w-full pl-9 pr-4 py-2.5 bg-gray-50 hover:bg-white focus:bg-white rounded-xl text-sm border border-gray-200 focus:border-primary focus:outline-none transition">
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <button onclick="detectMyLocation()" id="btnDetectGps" class="px-4 py-2.5 bg-primary hover:bg-primary-dark text-white text-xs sm:text-sm font-bold rounded-xl transition flex items-center justify-center gap-2 shadow-sm shadow-blue-500/20 whitespace-nowrap">
                    <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    <span>Deteksi Lokasi Saya 📍</span>
                </button>
            </div>
            <div id="locationStatus" class="text-xs text-gray-500 flex items-center gap-1.5">
                <span>📍 Menampilkan seluruh mitra resmi NPGLOW di Indonesia.</span>
            </div>
        </div>

        <!-- Map & Results Split View -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6">
            
            <!-- Map Container (7 cols on desktop) -->
            <div class="lg:col-span-7">
                <div class="bg-white rounded-2xl overflow-hidden border border-gray-200 shadow-sm sticky top-20">
                    <div id="resellerMap" class="w-full"></div>
                    <div class="p-3 bg-gray-50 border-t border-gray-200 flex items-center justify-between text-xs text-gray-500">
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-primary inline-block"></span> Pin Mitra Toko
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-500 inline-block"></span> Lokasi Anda
                        </span>
                    </div>
                </div>
            </div>

            <!-- Stores List (5 cols on desktop) -->
            <div class="lg:col-span-5 space-y-3">
                <div class="flex items-center justify-between px-1">
                    <h2 class="text-sm font-extrabold text-gray-800 tracking-tight">Daftar Toko Mitra</h2>
                    <span id="storesCount" class="text-xs font-bold text-primary"><?= count($allStores) ?> Toko</span>
                </div>

                <div id="storesContainer" class="space-y-3 max-h-[600px] overflow-y-auto pr-1">
                    <!-- Dynamic store cards will be rendered here -->
                </div>
            </div>

        </div>

    </main>

    <script>
        const initialStores = <?= json_encode($allStores) ?>;
        const currentStoreId = <?= $currentStore ? (int)$currentStore['id'] : 0 ?>;
        const isUserLoggedIn = <?= $userId > 0 ? 'true' : 'false' ?>;

        let map;
        let markersGroup;
        let userMarker = null;
        let storesData = [...initialStores];
        let userCoords = null;

        // Initialize Map
        function initMap() {
            // Default center of Indonesia
            map = L.map('resellerMap', { attributionControl: false }).setView([-2.5489, 118.0149], 5);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }).addTo(map);

            markersGroup = L.layerGroup().addTo(map);
            renderStoresList(storesData);
            plotStoreMarkers(storesData);
        }

        // Plot store markers on map
        function plotStoreMarkers(stores) {
            markersGroup.clearLayers();
            const bounds = [];

            if (userMarker) {
                bounds.push(userMarker.getLatLng());
            }

            stores.forEach(store => {
                if (store.latitude && store.longitude) {
                    const lat = parseFloat(store.latitude);
                    const lng = parseFloat(store.longitude);
                    bounds.push([lat, lng]);

                    const isCurrent = store.id == currentStoreId;
                    
                    const customIcon = L.divIcon({
                        className: 'bg-transparent border-0',
                        html: `
                            <div class="relative flex flex-col items-center">
                                ${store.distance_km !== undefined ? `<div class="absolute -top-7 whitespace-nowrap bg-white text-primary text-[10px] font-extrabold px-2 py-0.5 rounded-md shadow-sm border border-gray-100">${store.distance_km} km</div>` : ''}
                                <div class="w-10 h-10 rounded-full border-[3px] ${isCurrent ? 'border-emerald-500' : 'border-white'} shadow-md bg-white overflow-hidden flex items-center justify-center relative z-10">
                                    <img src="assets/icons/icon-192.png" class="w-full h-full object-cover" alt="NPGLOW">
                                </div>
                                <div class="w-0 h-0 border-l-[6px] border-r-[6px] border-l-transparent border-r-transparent border-t-[8px] ${isCurrent ? 'border-t-emerald-500' : 'border-t-white'} -mt-1 drop-shadow-sm relative z-0"></div>
                            </div>
                        `,
                        iconSize: [40, 50],
                        iconAnchor: [20, 50],
                        popupAnchor: [0, -50]
                    });

                    const marker = L.marker([lat, lng], { icon: customIcon }).addTo(markersGroup);

                    const popupContent = `
                        <div class="p-1 max-w-[220px]">
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded">${store.referral_code}</span>
                                ${isCurrent ? '<span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-1 rounded">Aktif</span>' : ''}
                            </div>
                            <h4 class="font-extrabold text-sm text-gray-800 leading-tight">${store.store_name}</h4>
                            <p class="text-xs text-gray-500 mt-1">📍 ${store.city || ''}, ${store.province || ''}</p>
                            ${store.distance_km !== undefined ? `<p class="text-xs font-bold text-primary mt-1">🚗 Jarak: ${store.distance_km} km</p>` : ''}
                            <button onclick="selectStore('${store.referral_code}', '${store.store_name.replace(/'/g, "\\'")}')" class="mt-2 w-full py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs rounded-lg transition">
                                ${isCurrent ? 'Toko Pilihan Anda' : 'Pilih Toko Ini'}
                            </button>
                        </div>
                    `;
                    marker.bindPopup(popupContent);
                }
            });

            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
            }
        }

        // Render stores list in the side column
        function renderStoresList(stores) {
            const container = document.getElementById('storesContainer');
            document.getElementById('storesCount').textContent = `${stores.length} Toko`;

            if (stores.length === 0) {
                container.innerHTML = `
                    <div class="bg-white rounded-2xl p-8 text-center border border-gray-200">
                        <p class="text-sm text-gray-500">Tidak ada toko mitra yang cocok dengan pencarian.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = stores.map(store => {
                const isCurrent = store.id == currentStoreId;
                const distanceBadge = store.distance_km !== undefined 
                    ? `<span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-100/80 px-2 py-0.5 rounded-full">🚗 ${store.distance_km} km</span>`
                    : '';

                return `
                    <div class="store-card bg-white rounded-2xl p-4 border ${isCurrent ? 'border-emerald-400 bg-emerald-50/40' : 'border-gray-200 hover:border-blue-200'} shadow-xs transition duration-200 space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-10 h-10 rounded-xl ${isCurrent ? 'bg-emerald-500 text-white' : 'bg-blue-50 text-primary'} flex items-center justify-center font-bold text-sm flex-shrink-0">
                                    ${store.store_name.charAt(0).toUpperCase()}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 bg-gray-100 text-gray-700 rounded">${store.referral_code}</span>
                                        ${isCurrent ? '<span class="text-[10px] font-extrabold text-emerald-600">✓ Aktif</span>' : ''}
                                    </div>
                                    <h3 class="text-sm font-extrabold text-gray-800 truncate">${store.store_name}</h3>
                                </div>
                            </div>
                            ${distanceBadge}
                        </div>

                        <div class="text-xs text-gray-500 space-y-0.5">
                            <p>📍 ${store.address || ''}, ${store.city || ''}, ${store.province || ''}</p>
                            ${store.whatsapp ? `<p class="text-emerald-600 font-medium">💬 WA: ${store.whatsapp}</p>` : ''}
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <button onclick="focusStoreOnMap(${store.latitude}, ${store.longitude})" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl transition flex-1 text-center">
                                Lihat di Peta 🗺️
                            </button>
                            <button onclick="selectStore('${store.referral_code}', '${store.store_name.replace(/'/g, "\\'")}')" class="px-3.5 py-1.5 ${isCurrent ? 'bg-emerald-600 text-white' : 'bg-primary hover:bg-primary-dark text-white'} text-xs font-bold rounded-xl transition flex-1 text-center shadow-xs">
                                ${isCurrent ? '✓ Toko Aktif' : 'Pilih Toko'}
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Focus store marker on map
        function focusStoreOnMap(lat, lng) {
            if (lat && lng) {
                map.setView([lat, lng], 15, { animate: true });
            } else {
                Swal.fire({ icon: 'info', title: 'Koordinat Belum Ada', text: 'Toko ini belum mengatur titik koordinat GPS.', confirmButtonColor: '#3ca6f2' });
            }
        }

        // Detect user location via GPS Geolocation API
        function detectMyLocation() {
            const btn = document.getElementById('btnDetectGps');
            const status = document.getElementById('locationStatus');

            if (!navigator.geolocation) {
                Swal.fire({ icon: 'warning', title: 'GPS Tidak Didukung', text: 'Browser Anda tidak mendukung deteksi lokasi.', confirmButtonColor: '#3ca6f2' });
                return;
            }

            btn.disabled = true;
            btn.innerHTML = `<span>Mendeteksi Lokasi...</span>`;
            status.innerHTML = `<span class="text-primary animate-pulse">⏳ Sedang mengambil koordinat GPS Anda...</span>`;

            navigator.geolocation.getCurrentPosition(
                async (pos) => {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    userCoords = { lat, lng };

                    // Add or update User Marker
                    if (userMarker) map.removeLayer(userMarker);
                    const userIcon = L.circleMarker([lat, lng], {
                        radius: 8,
                        fillColor: '#f43f5e',
                        color: '#ffffff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.9
                    }).addTo(map);
                    userMarker = userIcon;
                    userMarker.bindPopup('<b>📍 Lokasi Anda Saat Ini</b>').openPopup();

                    // Fetch nearest stores sorted from API
                    try {
                        const res = await fetch(`api/referral.php?action=get_nearest&lat=${lat}&lng=${lng}`);
                        const data = await res.json();
                        if (data.success && data.stores) {
                            storesData = data.stores;
                            renderStoresList(storesData);
                            plotStoreMarkers(storesData);
                            status.innerHTML = `<span class="text-emerald-600 font-bold">✓ Lokasi terdeteksi! Menampilkan toko dari jarak terdekat.</span>`;
                        }
                    } catch (e) {
                        status.innerHTML = `<span class="text-red-500">Gagal mengambil jarak toko.</span>`;
                    } finally {
                        btn.disabled = false;
                        btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg> <span>Lokasi Aktif 📍</span>`;
                    }
                },
                (err) => {
                    btn.disabled = false;
                    btn.innerHTML = `<span>Deteksi Lokasi Saya 📍</span>`;
                    status.innerHTML = `<span class="text-amber-600">Akses lokasi ditolak atau tidak tersedia. Anda tetap dapat mencari toko secara manual.</span>`;
                    Swal.fire({
                        icon: 'info',
                        title: 'Akses Lokasi Ditolak',
                        text: 'Silakan izinkan akses lokasi di browser atau ketik nama kota Anda pada kotak pencarian.',
                        confirmButtonColor: '#3ca6f2'
                    });
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        }

        // Search Filter
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase().trim();
            if (!query) {
                renderStoresList(storesData);
                plotStoreMarkers(storesData);
                return;
            }
            const filtered = storesData.filter(s => 
                (s.store_name && s.store_name.toLowerCase().includes(query)) ||
                (s.city && s.city.toLowerCase().includes(query)) ||
                (s.province && s.province.toLowerCase().includes(query)) ||
                (s.district && s.district.toLowerCase().includes(query)) ||
                (s.referral_code && s.referral_code.toLowerCase().includes(query))
            );
            renderStoresList(filtered);
            plotStoreMarkers(filtered);
        });

        // Select Store Referral
        function selectStore(referralCode, storeName) {
            if (!isUserLoggedIn) {
                // If guest, redirect to register with referral or store in session
                Swal.fire({
                    title: 'Pilih Toko ' + storeName,
                    text: `Gunakan kode ${referralCode} saat mendaftar akun atau berbelanja?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Daftar dengan Toko Ini',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#10b981'
                }).then(res => {
                    if (res.isConfirmed) {
                        window.location.href = `register.php?ref=${referralCode}`;
                    }
                });
                return;
            }

            Swal.fire({
                title: 'Pilih Mitra Ini?',
                html: `Katalog dan pesanan belanja Anda akan dialihkan ke <b>${storeName}</b> (Kode: <code>${referralCode}</code>).`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Pilih Toko',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#10b981'
            }).then(async (res) => {
                if (res.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'set_referral');
                    fd.append('code', referralCode);
                    try {
                        const r = await fetch('api/referral.php', { method: 'POST', body: fd });
                        const data = await r.json();
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Toko Berhasil Dipilih!',
                                text: `Anda sekarang berbelanja melalui ${storeName}.`,
                                confirmButtonColor: '#10b981'
                            }).then(() => {
                                window.location.href = 'shop.php';
                            });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Gagal', text: data.error, confirmButtonColor: '#3ca6f2' });
                        }
                    } catch (e) {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal menghubungi server.', confirmButtonColor: '#3ca6f2' });
                    }
                }
            });
        }

        // Revert to Official Store Pusat
        function revertToOfficialStore() {
            Swal.fire({
                title: 'Beralih ke Toko Pusat?',
                text: 'Pesanan selanjutnya akan dilayani langsung oleh Official Store Pusat.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Beralih',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#3ca6f2'
            }).then(async (res) => {
                if (res.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'clear_referral');
                    const r = await fetch('api/referral.php', { method: 'POST', body: fd });
                    const data = await r.json();
                    if (data.success) {
                        location.reload();
                    }
                }
            });
        }

        // Init on DOM ready
        document.addEventListener('DOMContentLoaded', () => {
            initMap();
        });
    </script>

    <?php 
    $bottomNavActive = 'belanja';
    include 'includes/bottom-nav.php'; 
    ?>

<?php include 'includes/pwa-sw.php'; ?>
</body>
</html>
