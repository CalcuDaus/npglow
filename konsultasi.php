<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth-helper.php';
require_once 'includes/settings-helper.php';
require_once 'includes/icon-helper.php';

// Customer only guard
guard_customer_only();

$userId = (int)$_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT name, has_purchased FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Check expert operational & online status
$opStatus = get_expert_operational_status($conn);
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Konsultasi - NPGLOW</title>
    <?php include 'includes/pwa-head.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3ca6f2',
                        'primary-dark': '#2e8ccf',
                        'primary-light': '#66bcf5',
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
        }
        .gradient-mesh {
            background: 
                radial-gradient(at 20% 20%, rgba(60, 166, 242, 0.12) 0px, transparent 50%),
                radial-gradient(at 80% 80%, rgba(60, 166, 242, 0.08) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(147, 197, 253, 0.06) 0px, transparent 50%);
        }
        .mode-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .mode-card:hover {
            transform: translateY(-8px);
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        .float-anim { animation: float 3s ease-in-out infinite; }
        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.6; }
            100% { transform: scale(0.95); opacity: 1; }
        }
        .pulse-ring { animation: pulse-ring 2s ease-in-out infinite; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen gradient-mesh pb-20 sm:pb-0">

    <!-- Navbar -->
    <nav class="sticky top-0 z-50 glass-card border-b border-white/30 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
            <a href="dashboard.php" class="flex items-center gap-2.5">
                <img class="h-9 w-auto object-contain rounded-lg" src="assets/images/logo_np_glow.jpeg" alt="NPGLOW">
                <span class="font-extrabold text-lg text-gray-800 tracking-tight">NPGLOW</span>
            </a>
            <div class="flex items-center gap-3">
                <a href="dashboard.php" class="text-sm text-gray-500 hover:text-primary transition-colors font-medium">← Dashboard</a>
            </div>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 py-10 sm:py-16">

        <!-- Header -->
        <div class="text-center mb-12" data-aos="fade-up">
            <div class="inline-flex items-center gap-2 bg-blue-50 text-primary px-4 py-1.5 rounded-full text-sm font-semibold mb-4 border border-blue-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                Konsultasi Skincare NPGLOW
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-3">Pilih Mode Konsultasi</h1>
            <p class="text-gray-500 text-base sm:text-lg max-w-xl mx-auto">Konsultasikan masalah kulitmu dengan cara yang paling nyaman dan sesuai kebutuhanmu.</p>
        </div>

        <!-- Mode Selection Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">

            <!-- AI Assistant Card -->
            <a href="chat-ai.php" class="mode-card bg-white rounded-3xl p-8 border border-gray-100 shadow-lg hover:shadow-2xl hover:border-violet-200 group relative overflow-hidden flex flex-col justify-between">
                <!-- Gradient overlay -->
                <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-bl from-violet-100 to-transparent rounded-bl-full opacity-60 group-hover:opacity-100 transition-opacity"></div>
                
                <div class="relative">
                    <!-- Icon -->
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center mb-6 group-hover:shadow-lg group-hover:shadow-violet-200 transition-shadow float-anim">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>

                    <!-- Content -->
                    <h3 class="text-xl font-extrabold text-gray-900 mb-2 group-hover:text-violet-700 transition-colors">AI Assistant</h3>
                    <p class="text-gray-500 text-sm mb-6 leading-relaxed">Dapatkan rekomendasi produk dan tips skincare instan dari AI kami yang sudah memahami seluruh katalog produk NPGLOW.</p>

                    <!-- Features -->
                    <div class="space-y-2.5 mb-6">
                        <div class="flex items-center gap-2.5 text-sm text-gray-600">
                            <span class="w-5 h-5 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                            Tersedia 24 jam, 7 hari non-stop
                        </div>
                        <div class="flex items-center gap-2.5 text-sm text-gray-600">
                            <span class="w-5 h-5 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                            Respons cerdas & instan
                        </div>
                        <div class="flex items-center gap-2.5 text-sm text-gray-600">
                            <span class="w-5 h-5 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                            Rekomendasi seluruh produk variatif
                        </div>
                    </div>
                </div>

                <!-- Status Badge & CTA -->
                <div class="pt-4 border-t border-gray-50 flex items-center justify-between">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold bg-emerald-100 text-emerald-700 px-3 py-1.5 rounded-full border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 pulse-ring"></span>
                        Selalu Online
                    </span>
                    <span class="text-sm font-bold text-violet-600 group-hover:translate-x-1 transition-transform inline-flex items-center gap-1">
                        Mulai Chat
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </span>
                </div>
            </a>

            <!-- Tim Ahli Card -->
            <a href="chat.php" class="mode-card bg-white rounded-3xl p-8 border border-gray-100 shadow-lg hover:shadow-2xl hover:border-blue-200 group relative overflow-hidden flex flex-col justify-between">
                <!-- Gradient overlay -->
                <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-bl from-blue-100 to-transparent rounded-bl-full opacity-60 group-hover:opacity-100 transition-opacity"></div>
                
                <div class="relative">
                    <!-- Icon -->
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-400 to-primary flex items-center justify-center mb-6 group-hover:shadow-lg group-hover:shadow-blue-200 transition-shadow float-anim" style="animation-delay: 0.5s;">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>

                    <!-- Content -->
                    <h3 class="text-xl font-extrabold text-gray-900 mb-2 group-hover:text-primary transition-colors">Tim Ahli NPGLOW</h3>
                    <p class="text-gray-500 text-sm mb-6 leading-relaxed">Chat langsung dengan dokter & praktisi skincare kami. Dapatkan saran personal yang lebih mendalam sesuai kondisi kulitmu.</p>

                    <!-- Features -->
                    <div class="space-y-2.5 mb-4">
                        <div class="flex items-center gap-2.5 text-sm text-gray-600">
                            <span class="w-5 h-5 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                            Konsultasi personal dengan staf ahli
                        </div>
                        <div class="flex items-center gap-2.5 text-sm text-gray-600">
                            <span class="w-5 h-5 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                            Analisis kondisi kulit mendalam
                        </div>
                        <div class="flex items-center gap-2.5 text-sm text-gray-600">
                            <span class="w-5 h-5 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                            Evaluasi perkembangan via foto wajah
                        </div>
                    </div>

                    <!-- Schedule Hint -->
                    <div class="text-[11px] text-gray-500 mb-6 flex items-center gap-1.5 bg-gray-50 p-2.5 rounded-xl border border-gray-100">
                        <svg class="w-3.5 h-3.5 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span id="expert-schedule-hint">Jadwal: <?= htmlspecialchars($opStatus['schedule_text']) ?></span>
                    </div>
                </div>

                <!-- Status Badge & CTA -->
                <div class="pt-4 border-t border-gray-50 flex items-center justify-between">
                    <span id="expert-status-badge" class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full <?= $opStatus['badge_class'] ?>">
                        <span id="expert-status-dot" class="w-2 h-2 rounded-full <?= $opStatus['dot_class'] ?>"></span>
                        <span id="expert-status-label"><?= htmlspecialchars($opStatus['status_label']) ?></span>
                    </span>
                    <span class="text-sm font-bold text-primary group-hover:translate-x-1 transition-transform inline-flex items-center gap-1">
                        Mulai Chat
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </span>
                </div>
            </a>
        </div>

        <!-- Helper Note -->
        <div class="text-center mt-10">
            <p class="inline-flex items-center gap-2 text-gray-500 text-sm bg-slate-100/80 px-4 py-2 rounded-2xl border border-slate-200/60">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-amber-100 text-amber-600">
                    <?= npglow_icon('lightbulb', 'w-4 h-4') ?>
                </span>
                <span>Tidak yakin harus pilih yang mana? Mulai dari <strong class="text-violet-600 font-semibold">AI Assistant</strong> — bisa pindah ke Tim Ahli kapan saja.</span>
            </p>
        </div>
    </main>

    <!-- Real-time Poll for Expert Status -->
    <script>
        async function updateExpertStatus() {
            try {
                const res = await fetch('api/expert-status.php');
                const data = await res.json();
                
                const badge = document.getElementById('expert-status-badge');
                const dot = document.getElementById('expert-status-dot');
                const label = document.getElementById('expert-status-label');
                const scheduleHint = document.getElementById('expert-schedule-hint');
                
                if (badge && dot && label) {
                    badge.className = 'inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full ' + data.badge_class;
                    dot.className = 'w-2 h-2 rounded-full ' + data.dot_class;
                    label.innerText = data.status_label;
                }
                if (scheduleHint && data.schedule_text) {
                    scheduleHint.innerText = 'Jadwal: ' + data.schedule_text;
                }
            } catch (e) {
                console.warn('Expert status check failed:', e);
            }
        }

        // Poll every 15 seconds
        setInterval(updateExpertStatus, 15000);
    </script>

    <?php 
    $bottomNavActive = 'konsultasi';
    include 'includes/bottom-nav.php'; 
    ?>

<?php include 'includes/pwa-sw.php'; ?>
</body>
</html>
