<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth-helper.php';
require_once 'includes/icon-helper.php';

// Customer only guard
guard_customer_only();

$userId = $_SESSION['user_id'];

// Fetch user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch all photos
$stmt = $conn->prepare("SELECT * FROM user_face_photos WHERE user_id = ? ORDER BY taken_at DESC, created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Initial photo
$stmt = $conn->prepare("SELECT * FROM user_face_photos WHERE user_id = ? AND photo_type = 'initial' ORDER BY created_at ASC LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$initialPhoto = $stmt->get_result()->fetch_assoc();

// Latest photo
$latestPhoto = !empty($photos) ? $photos[0] : null;

// Consultation logs
$stmt = $conn->prepare("SELECT cl.*, u.name as admin_name FROM consultation_logs cl LEFT JOIN users u ON cl.expert_id = u.id WHERE cl.user_id = ? ORDER BY cl.consultation_date DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skincare Journal - NPGLOW</title>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        .glass-card { background: rgba(255,255,255,0.85); backdrop-filter: blur(16px); }
        .gradient-mesh {
            background: 
                radial-gradient(at 20% 20%, rgba(147,51,234,0.08) 0px, transparent 50%),
                radial-gradient(at 80% 80%, rgba(60,166,242,0.08) 0px, transparent 50%);
        }
        .timeline-line { position: relative; }
        .timeline-line::before {
            content: '';
            position: absolute;
            left: 23px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #3ca6f2, #e2e8f0);
        }
        .photo-drop-zone {
            border: 2px dashed #cbd5e1;
            transition: all 0.3s ease;
        }
        .photo-drop-zone:hover { border-color: #3ca6f2; background: rgba(60,166,242,0.05); }
        .photo-drop-zone.has-image { border-color: #10b981; border-style: solid; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen gradient-mesh pb-20 sm:pb-8">

    <!-- Navbar -->
    <nav class="sticky top-0 z-50 glass-card border-b border-white/30 shadow-sm">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
            <a href="dashboard.php" class="flex items-center gap-2 text-gray-500 hover:text-primary transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                <span class="text-sm font-medium">Dashboard</span>
            </a>
            <h1 class="text-base font-extrabold text-gray-800">Skincare Journal</h1>
            <div class="w-8"></div>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 py-8">

        <!-- Before-After Comparison -->
        <?php if ($initialPhoto && $latestPhoto && $initialPhoto['id'] !== $latestPhoto['id']): ?>
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-md mb-8" data-aos="fade-up">
            <h2 class="text-xl font-extrabold text-gray-800 mb-1 flex items-center gap-2">
                Perbandingan Progress <?= npglow_icon('sparkles', 'w-5 h-5 text-amber-500') ?>
            </h2>
            <p class="text-sm text-gray-400 mb-6">Lihat perubahan kulitmu dari awal hingga sekarang</p>
            <div class="grid grid-cols-2 gap-4 sm:gap-6">
                <div class="text-center">
                    <div class="aspect-[3/4] rounded-2xl overflow-hidden bg-gray-100 mb-3 shadow-inner relative">
                        <img src="<?= htmlspecialchars($initialPhoto['photo_path']) ?>" alt="Foto Awal" class="w-full h-full object-cover">
                        <span class="absolute top-2 left-2 bg-white/90 backdrop-blur-sm text-xs font-bold text-gray-700 px-2.5 py-1 rounded-full shadow-sm">AWAL</span>
                    </div>
                    <p class="text-xs font-bold text-gray-600"><?= date('d M Y', strtotime($initialPhoto['taken_at'])) ?></p>
                    <?php if ($initialPhoto['notes']): ?>
                    <p class="text-[11px] text-gray-400 mt-0.5 line-clamp-1"><?= htmlspecialchars($initialPhoto['notes']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="text-center">
                    <div class="aspect-[3/4] rounded-2xl overflow-hidden bg-gray-100 mb-3 shadow-inner relative">
                        <img src="<?= htmlspecialchars($latestPhoto['photo_path']) ?>" alt="Foto Terbaru" class="w-full h-full object-cover">
                        <span class="absolute top-2 left-2 bg-primary/90 backdrop-blur-sm text-xs font-bold text-white px-2.5 py-1 rounded-full shadow-sm">TERBARU</span>
                    </div>
                    <p class="text-xs font-bold text-gray-600"><?= date('d M Y', strtotime($latestPhoto['taken_at'])) ?></p>
                    <?php if ($latestPhoto['notes']): ?>
                    <p class="text-[11px] text-gray-400 mt-0.5 line-clamp-1"><?= htmlspecialchars($latestPhoto['notes']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php elseif (!$initialPhoto): ?>
        <div class="bg-white rounded-3xl p-8 sm:p-10 border border-gray-100 shadow-md mb-8 text-center" data-aos="fade-up">
            <div class="w-16 h-16 mx-auto rounded-full bg-purple-50 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800 mb-2">Belum Ada Foto</h3>
            <p class="text-sm text-gray-500 mb-4">Foto wajah awal akan dicatat saat pembelian pertama produk NPGLOW.</p>
            <a href="index.php#marketplace" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-primary-dark transition-colors shadow-md">
                Belanja Sekarang
            </a>
        </div>
        <?php endif; ?>

        <!-- Upload Progress Photo -->
        <?php if ($initialPhoto): ?>
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-md mb-8" data-aos="fade-up" data-aos-delay="100">
            <h2 class="text-lg font-extrabold text-gray-800 mb-1">Tambah Foto Progress</h2>
            <p class="text-sm text-gray-400 mb-5">Dokumentasikan perkembangan kulitmu</p>

            <form id="upload-form" enctype="multipart/form-data">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <label for="progress_photo" class="photo-drop-zone block rounded-2xl p-6 cursor-pointer text-center" id="progress-drop-zone">
                        <div id="progress-placeholder">
                            <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <p class="text-sm text-gray-500 font-medium">Ketuk untuk pilih foto</p>
                            <p class="text-xs text-gray-400">Kamera / Galeri • Auto-Convert WebP</p>
                        </div>
                        <div id="progress-preview" class="hidden">
                            <img id="progress-preview-img" class="w-28 h-28 mx-auto rounded-xl object-cover shadow-sm mb-2" alt="Preview">
                            <p class="text-xs text-emerald-600 font-semibold flex items-center justify-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Siap diunggah
                            </p>
                            <span id="compress-info-badge" class="inline-block text-[11px] font-medium bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full mt-1 border border-emerald-200"></span>
                        </div>
                        <input type="file" name="photo" id="progress_photo" accept="image/*" class="hidden" required>
                    </label>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Tanggal Foto</label>
                            <input type="date" name="taken_at" value="<?= date('Y-m-d') ?>" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Catatan (opsional)</label>
                            <textarea name="notes" rows="3" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary bg-gray-50 resize-none" placeholder="Cth: Jerawat berkurang, kulit lebih cerah..."></textarea>
                        </div>
                    </div>
                </div>
                <button type="submit" id="upload-btn" class="w-full sm:w-auto bg-purple-600 text-white px-8 py-3 rounded-xl font-bold text-sm hover:bg-purple-700 transition-colors shadow-md flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    Upload Foto Progress
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Timeline -->
        <div class="mb-8" data-aos="fade-up" data-aos-delay="200">
            <h2 class="text-xl font-extrabold text-gray-800 mb-6">Timeline Perjalanan</h2>

            <?php if (empty($photos) && empty($logs)): ?>
            <div class="text-center py-12 text-gray-400">
                <p class="text-sm">Belum ada entri journal.</p>
            </div>
            <?php else: ?>
            <div class="timeline-line space-y-6 pl-2">
                <?php 
                // Merge photos and logs into timeline
                $timeline = [];
                foreach ($photos as $p) {
                    $timeline[] = ['type' => 'photo', 'date' => $p['taken_at'], 'data' => $p];
                }
                foreach ($logs as $l) {
                    $timeline[] = ['type' => 'log', 'date' => $l['consultation_date'], 'data' => $l];
                }
                // Sort by date descending
                usort($timeline, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
                
                foreach ($timeline as $item):
                    if ($item['type'] === 'photo'):
                        $p = $item['data'];
                ?>
                <div class="relative flex gap-4 items-start">
                    <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0 z-10 shadow-sm border-2 border-white">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <div class="bg-white rounded-2xl p-4 flex-1 border border-gray-100 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <?= npglow_photo_badge($p['photo_type'], $p['photo_type'] === 'initial' ? 'Foto Awal' : 'Foto Progress') ?>
                            <span class="text-[11px] text-gray-400"><?= date('d M Y', strtotime($p['taken_at'])) ?></span>
                        </div>
                        <div class="w-full aspect-video rounded-xl overflow-hidden bg-gray-100 mb-2">
                            <img src="<?= htmlspecialchars($p['photo_path']) ?>" alt="Foto" class="w-full h-full object-cover">
                        </div>
                        <?php if ($p['notes']): ?>
                        <p class="text-sm text-gray-600"><?= htmlspecialchars($p['notes']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php 
                    else:
                        $l = $item['data'];
                ?>
                <div class="relative flex gap-4 items-start">
                    <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0 z-10 shadow-sm border-2 border-white">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div class="bg-white rounded-2xl p-4 flex-1 border border-gray-100 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                                <?= npglow_icon('chat', 'w-3.5 h-3.5 text-emerald-600') ?> Sesi Konsultasi
                            </span>
                            <span class="text-[11px] text-gray-400"><?= date('d M Y', strtotime($l['consultation_date'])) ?></span>
                        </div>
                        <?php if ($l['skin_condition']): ?>
                        <p class="text-xs text-gray-500 mb-1"><span class="font-semibold">Kondisi:</span> <?= htmlspecialchars($l['skin_condition']) ?></p>
                        <?php endif; ?>
                        <?php if ($l['summary']): ?>
                        <p class="text-sm text-gray-700 mb-1"><?= htmlspecialchars($l['summary']) ?></p>
                        <?php endif; ?>
                        <?php if ($l['recommendation']): ?>
                        <div class="bg-blue-50 rounded-lg p-2.5 mt-2">
                            <p class="text-xs text-blue-700"><span class="font-bold">Rekomendasi:</span> <?= htmlspecialchars($l['recommendation']) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($l['admin_name']): ?>
                        <p class="text-[11px] text-gray-400 mt-2">oleh <?= htmlspecialchars($l['admin_name']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="assets/js/image-compressor.js"></script>
    <script>AOS.init({ duration: 600, once: true });</script>
    <script>
        // Photo preview & client-side compression for progress upload
        const progressInput = document.getElementById('progress_photo');
        const progressDropZone = document.getElementById('progress-drop-zone');
        let compressedPhotoFile = null;

        if (progressInput) {
            progressInput.addEventListener('change', async function() {
                if (this.files && this.files[0]) {
                    const originalFile = this.files[0];
                    const previewImg = document.getElementById('progress-preview-img');
                    const badge = document.getElementById('compress-info-badge');

                    // Show temporary preview immediately
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        document.getElementById('progress-placeholder').classList.add('hidden');
                        document.getElementById('progress-preview').classList.remove('hidden');
                        progressDropZone.classList.add('has-image');
                    };
                    reader.readAsDataURL(originalFile);

                    if (badge) {
                        badge.textContent = 'Mengompresi ke WebP...';
                    }

                    try {
                        const result = await NPGLOWCompressor.compress(originalFile, { quality: 0.82, maxWidth: 1600, maxHeight: 1600 });
                        compressedPhotoFile = result.file;
                        previewImg.src = result.previewUrl;
                        if (badge) {
                            badge.innerHTML = `WebP: ${NPGLOWCompressor.formatBytes(result.originalSize)} &rarr; ${NPGLOWCompressor.formatBytes(result.compressedSize)} (-${result.savings}%)`;
                        }
                    } catch (err) {
                        console.warn('Client-side compression fallback to original:', err);
                        compressedPhotoFile = originalFile;
                        if (badge) {
                            badge.textContent = `${NPGLOWCompressor.formatBytes(originalFile.size)}`;
                        }
                    }
                }
            });
        }

        // Upload form
        const uploadForm = document.getElementById('upload-form');
        if (uploadForm) {
            uploadForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                if (!progressInput.files || progressInput.files.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Pilih Foto', text: 'Silakan pilih foto terlebih dahulu.', confirmButtonColor: '#3ca6f2' });
                    return;
                }
                
                const btn = document.getElementById('upload-btn');
                btn.disabled = true;
                btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Mengunggah WebP...';
                
                const formData = new FormData(this);
                // Attach pre-compressed WebP file if available
                if (compressedPhotoFile) {
                    formData.set('photo', compressedPhotoFile);
                }

                try {
                    const res = await fetch('api/journal.php?action=upload_photo', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        Swal.fire({ 
                            icon: 'success', 
                            title: 'Berhasil!', 
                            text: 'Foto progress berhasil diunggah dan disimpan dalam format WebP.', 
                            confirmButtonColor: '#3ca6f2' 
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: data.error || 'Terjadi kesalahan.', confirmButtonColor: '#3ca6f2' });
                        btn.disabled = false;
                        btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg> Upload Foto Progress';
                    }
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal menghubungi server.', confirmButtonColor: '#3ca6f2' });
                    btn.disabled = false;
                    btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg> Upload Foto Progress';
                }
            });
        }
    </script>

    <?php 
    $bottomNavActive = 'profil';
    include 'includes/bottom-nav.php'; 
    ?>

</body>
<?php include 'includes/pwa-sw.php'; ?>
</html>
