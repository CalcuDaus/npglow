<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth-helper.php';

// Prevent admin and expert from accessing customer landing page
guard_landing_page();

$isLoggedIn = isset($_SESSION['user_id']);
$hasPurchased = false;
$userName = '';

if ($isLoggedIn) {
    $stmt = $conn->prepare("SELECT name, has_purchased FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $userName = $row['name'];
        $hasPurchased = $row['has_purchased'];
    }
}

// Fetch Testimonials (Before-After)
$testimoniQuery = $conn->query("
    SELECT 
        u.name,
        (SELECT photo_path FROM user_face_photos WHERE user_id = u.id AND photo_type = 'initial' ORDER BY taken_at ASC LIMIT 1) as photo_before,
        (SELECT photo_path FROM user_face_photos WHERE user_id = u.id AND photo_type = 'progress' ORDER BY taken_at DESC LIMIT 1) as photo_after
    FROM users u
    HAVING photo_before IS NOT NULL AND photo_after IS NOT NULL
    LIMIT 6
");
$testimonials = [];
if ($testimoniQuery) {
    while ($row = $testimoniQuery->fetch_assoc()) {
        $testimonials[] = $row;
    }
}

// Fallback dummy data if no real data yet, so the section still looks good
if (empty($testimonials)) {
    $testimonials = [
        [
            'name' => 'Amanda T.',
            'photo_before' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=400&h=500&fit=crop&q=80',
            'photo_after' => 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&h=500&fit=crop&q=80',
            'review' => 'Jerawat meradang kempes dalam 2 minggu!'
        ],
        [
            'name' => 'Dinda R.',
            'photo_before' => 'https://images.unsplash.com/photo-1554151228-14d9def656e4?w=400&h=500&fit=crop&q=80',
            'photo_after' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=400&h=500&fit=crop&q=80',
            'review' => 'Kulit kusam jadi jauh lebih cerah dan glowing.'
        ],
        [
            'name' => 'Sarah A.',
            'photo_before' => 'https://images.unsplash.com/photo-1548142813-c348350df52b?w=400&h=500&fit=crop&q=80',
            'photo_after' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=400&h=500&fit=crop&q=80',
            'review' => 'Tekstur kulit membaik drastis, super luv!'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NPGLOW - Konsultasi & Belanja Skincare Terpercaya</title>
    <?php include 'includes/pwa-head.php'; ?>
    <!-- Tailwind CSS -->
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
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- AOS CSS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <!-- Custom CSS (optional) -->
    <style>
        body { font-family: 'Inter', sans-serif; }
        .hero-pattern {
            background-color: #f8fafc;
            background-image: radial-gradient(#3ca6f2 0.5px, transparent 0.5px), radial-gradient(#3ca6f2 0.5px, #f8fafc 0.5px);
            background-size: 20px 20px;
            background-position: 0 0, 10px 10px;
            background-opacity: 0.1;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

    <!-- Navbar -->
    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <div class="flex items-center">
                    <a href="#" class="flex-shrink-0 flex items-center gap-3">
                        <img class="h-12 w-auto object-contain" src="assets/images/logo_np_glow.jpeg" alt="NPGLOW Logo">
                        <span class="font-bold text-xl tracking-tight text-primary">NPGLOW</span>
                    </a>
                    <div class="hidden md:ml-10 md:flex md:space-x-8">
                        <a href="#beranda" class="nav-link border-primary text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors">Beranda</a>
                        <a href="#marketplace" class="nav-link border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors">Produk</a>
                        <a href="#konsultasi" class="nav-link border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors">Konsultasi</a>
                    </div>
                </div>
                <div class="hidden md:flex items-center space-x-4">
                    <?php if ($isLoggedIn): ?>
                        <span class="text-gray-700 font-medium text-sm">Halo, <?= htmlspecialchars($userName) ?></span>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <a href="admin/index.php" class="bg-gray-800 hover:bg-gray-700 text-white px-5 py-2.5 rounded-full font-medium text-sm transition-all shadow-md">Dashboard Admin</a>
                        <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'expert'): ?>
                            <a href="expert/index.php" class="bg-[#3ca6f2] hover:bg-[#2e8ccf] text-white px-5 py-2.5 rounded-full font-medium text-sm transition-all shadow-md">Dashboard Ahli</a>
                        <?php else: ?>
                            <a href="dashboard.php" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-full font-medium text-sm transition-all shadow-md hover:shadow-lg">Dashboard</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-500 hover:text-primary font-medium text-sm transition-colors">Masuk</a>
                        <a href="register.php" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-full font-medium text-sm transition-all shadow-md hover:shadow-lg">Daftar</a>
                    <?php endif; ?>
                </div>
                <!-- Mobile menu button -->
                <div class="flex items-center md:hidden">
                    <button type="button" id="mobile-menu-btn" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary" aria-expanded="false">
                        <span class="sr-only">Buka menu utama</span>
                        <!-- Hamburger icon -->
                        <svg id="hamburger-icon" class="block h-6 w-6 transition-transform duration-300 transform rotate-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <!-- Close icon -->
                        <svg id="close-icon" class="hidden h-6 w-6 transition-transform duration-300 transform rotate-90 opacity-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Panel -->
        <div class="md:hidden hidden transform transition-all duration-300 origin-top opacity-0 -translate-y-4" id="mobile-menu">
            <div class="pt-2 pb-3 space-y-1">
                <a href="#beranda" class="mobile-nav-link bg-blue-50 border-primary text-primary block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition-colors">Beranda</a>
                <a href="#marketplace" class="mobile-nav-link border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700 block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition-colors">Produk</a>
                <a href="#konsultasi" class="mobile-nav-link border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700 block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition-colors">Konsultasi</a>
            </div>
            <div class="pt-4 pb-3 border-t border-gray-200">
                <div class="flex flex-col items-center px-4 space-y-3">
                    <?php if ($isLoggedIn): ?>
                        <span class="w-full text-center text-gray-700 font-medium">Halo, <?= htmlspecialchars($userName) ?></span>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <a href="admin/index.php" class="w-full text-center bg-gray-800 hover:bg-gray-700 text-white px-4 py-2 rounded-full font-medium shadow-md transition-colors">Dashboard Admin</a>
                        <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'expert'): ?>
                            <a href="expert/index.php" class="w-full text-center bg-[#3ca6f2] hover:bg-[#2e8ccf] text-white px-4 py-2 rounded-full font-medium shadow-md transition-colors">Dashboard Ahli</a>
                        <?php else: ?>
                            <a href="dashboard.php" class="w-full text-center bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-full font-medium shadow-md transition-colors">Dashboard</a>
                        <?php endif; ?>
                        <a href="logout.php" class="w-full text-center bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-full font-medium shadow-md transition-colors">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="w-full text-center text-primary border border-primary hover:bg-blue-50 px-4 py-2 rounded-full font-medium transition-colors">Masuk</a>
                        <a href="register.php" class="w-full text-center bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-full font-medium shadow-md transition-colors">Daftar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div id="beranda" class="relative bg-white overflow-hidden hero-pattern">
        <div class="max-w-7xl mx-auto">
            <div class="relative z-10 pb-8 bg-transparent sm:pb-16 md:pb-20 lg:max-w-2xl lg:w-full lg:pb-28 xl:pb-32 pt-10 sm:pt-16 lg:pt-20 lg:ml-auto">
                <main class="mt-10 mx-auto max-w-7xl px-4 sm:mt-12 sm:px-6 md:mt-16 lg:mt-20 lg:px-8 xl:mt-28">
                    <div class="sm:text-center lg:text-left" data-aos="fade-up">
                        <span class="inline-block py-1 px-3 rounded-full bg-blue-100 text-primary text-sm font-semibold mb-4 tracking-wide">Lebih dari Sekadar Skincare</span>
                        <h1 class="text-4xl tracking-tight font-extrabold text-gray-900 sm:text-5xl md:text-6xl">
                            <span class="block xl:inline">Perawatan Kulit Terbaik</span>
                            <span class="block text-primary">Di Tangan Ahlinya</span>
                        </h1>
                        <p class="mt-3 text-base text-gray-500 sm:mt-5 sm:text-lg sm:max-w-xl sm:mx-auto md:mt-5 md:text-xl lg:mx-0">
                            Dapatkan kulit sehat dan glowing dengan produk original NPGLOW. Konsultasi gratis dengan tim ahli atau AI assistant kami, kapan saja — bahkan sebelum membeli produk.
                        </p>
                        <div class="mt-5 sm:mt-8 sm:flex sm:justify-center lg:justify-start gap-4">
                            <div class="rounded-full shadow-lg">
                                <a href="#konsultasi" class="w-full flex items-center justify-center gap-2 px-8 py-3 border border-transparent text-base font-medium rounded-full text-white bg-primary hover:bg-primary-dark md:py-4 md:text-lg transition-transform hover:-translate-y-1">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                    Konsultasi Sekarang
                                </a>
                            </div>
                            <div class="mt-3 sm:mt-0">
                                <a href="#marketplace" class="w-full flex items-center justify-center gap-2 px-8 py-3 border-2 border-gray-200 text-base font-medium rounded-full text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-300 md:py-4 md:text-lg transition-transform hover:-translate-y-1">
                                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                    Belanja Produk
                                </a>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
        <div class="lg:absolute lg:inset-y-0 lg:left-0 lg:w-1/2 flex items-center justify-center p-4 sm:p-10 relative">
            <style>
                @keyframes float-wiggle {
                    0%, 100% { transform: translateY(0) rotate(-4deg); }
                    50% { transform: translateY(-15px) rotate(4deg); }
                }
                .animate-float-wiggle {
                    animation: float-wiggle 4s ease-in-out infinite;
                }
                .animate-float-wiggle-delay {
                    animation: float-wiggle 5s ease-in-out infinite 1.5s;
                }
                .animate-float-wiggle-fast {
                    animation: float-wiggle 3.5s ease-in-out infinite 0.5s;
                }
            </style>
            
            <!-- Talent Image with faded edges -->
            <div class="relative w-full max-w-lg lg:h-full flex items-center justify-center" data-aos="fade-right" data-aos-delay="200">
                <img src="assets/images/talent.jpg" alt="NPGLOW Talent" class="w-full h-auto object-cover max-h-[80vh] rounded-[3rem]" style="mask-image: radial-gradient(circle, black 55%, transparent 100%); -webkit-mask-image: radial-gradient(circle, black 55%, transparent 100%); mix-blend-mode: multiply;">
                
                <!-- Overlay Icons -->
                <!-- Icon 1: Sparkle / Glow -->
                <div class="absolute top-1/4 left-0 sm:-ml-8 bg-white/90 backdrop-blur p-3.5 rounded-full shadow-xl text-yellow-400 animate-float-wiggle border border-white/50">
                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                </div>
                
                <!-- Icon 2: Verified / Skincare Trust -->
                <div class="absolute bottom-1/4 right-0 sm:-mr-12 bg-white/90 backdrop-blur px-5 py-3.5 rounded-2xl shadow-xl flex items-center gap-3 animate-float-wiggle-delay border border-white/50">
                    <div class="bg-blue-100 p-2 rounded-full text-primary">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div>
                        <p class="text-sm font-black text-gray-800 tracking-tight">100% Original</p>
                        <p class="text-xs font-semibold text-gray-500">Teruji Klinis</p>
                    </div>
                </div>

                <!-- Icon 3: Beauty -->
                <div class="absolute top-10 right-10 bg-white/90 backdrop-blur p-4 rounded-full shadow-xl text-rose-400 animate-float-wiggle-fast border border-white/50">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Value Proposition -->
    <div class="py-16 bg-white">
        <!-- ... [Content from index.html] ... -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center" data-aos="fade-up">
                <h2 class="text-base text-primary font-semibold tracking-wide uppercase">Keunggulan NPGLOW</h2>
                <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                    Kenapa Percayakan Kulitmu pada Kami?
                </p>
            </div>

            <div class="mt-16">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                    <div class="group text-center p-6 bg-slate-50 hover:bg-white rounded-2xl hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 border border-slate-100 cursor-pointer" data-aos="fade-up" data-aos-delay="100">
                        <div class="flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 text-primary mx-auto mb-6 group-hover:bg-primary group-hover:text-white group-hover:scale-110 transition-all duration-300">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-primary transition-colors duration-300">100% Original & BPOM</h3>
                        <p class="text-gray-500 text-sm leading-relaxed">Produk kami telah teruji klinis dan memiliki sertifikasi BPOM, aman digunakan untuk kulit sehat dalam jangka panjang.</p>
                    </div>

                    <div class="group text-center p-6 bg-slate-50 hover:bg-white rounded-2xl hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 border border-slate-100 cursor-pointer" data-aos="fade-up" data-aos-delay="200">
                        <div class="flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 text-primary mx-auto mb-6 group-hover:bg-primary group-hover:text-white group-hover:scale-110 transition-all duration-300">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-primary transition-colors duration-300">Respons Cepat</h3>
                        <p class="text-gray-500 text-sm leading-relaxed">Tim ahli kami siap melayani dan merespons pertanyaan serta keluhan kulit Anda dengan sigap dan ramah.</p>
                    </div>

                    <div class="group text-center p-6 bg-slate-50 hover:bg-white rounded-2xl hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 border border-slate-100 cursor-pointer" data-aos="fade-up" data-aos-delay="300">
                        <div class="flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 text-primary mx-auto mb-6 group-hover:bg-primary group-hover:text-white group-hover:scale-110 transition-all duration-300">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-primary transition-colors duration-300">Pantau via Smartphone</h3>
                        <p class="text-gray-500 text-sm leading-relaxed">Konsultasi, belanja, dan pantau perkembangan kulitmu dengan mudah di manapun melalui gadget.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Testimoni / Stories Section -->
    <div id="testimoni" class="py-20 bg-white overflow-hidden relative border-t border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-16" data-aos="fade-up">
                <span class="inline-block py-1 px-3 rounded-full bg-blue-50 text-primary text-sm font-semibold mb-4 tracking-wide border border-blue-100">Kisah Nyata Pelanggan</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight">Klien Kami Menyoroti Keberhasilan Mereka</h2>
                <p class="mt-4 text-gray-500 max-w-2xl mx-auto text-lg">Lihat sendiri transformasi luar biasa dari pelanggan yang telah rutin menggunakan produk dan konsultasi di NPGLOW.</p>
            </div>

            <!-- Swiper Container -->
            <div class="swiper testimoni-swiper !pb-16" data-aos="fade-up" data-aos-delay="100">
                <div class="swiper-wrapper">
                    <?php foreach ($testimonials as $testi): ?>
                    <div class="swiper-slide h-auto">
                        <div class="bg-white rounded-[2rem] p-6 sm:p-8 shadow-[0_8px_30px_rgb(0,0,0,0.06)] hover:shadow-[0_20px_40px_rgb(0,0,0,0.12)] border border-gray-100 h-full flex flex-col group transition-all duration-300 relative">
                            <!-- Large Quote Icon Background -->
                            <svg class="absolute top-8 right-8 w-16 h-16 text-gray-100" fill="currentColor" viewBox="0 0 24 24"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/></svg>

                            <!-- Header -->
                            <div class="flex flex-col gap-1 mb-5 relative z-10">
                                <h4 class="text-gray-900 font-extrabold text-xl tracking-tight"><?= htmlspecialchars($testi['name']) ?></h4>
                                <p class="text-xs text-gray-500 font-medium flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-primary" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                    Verified Buyer
                                </p>
                            </div>

                            <!-- Title -->
                            <h5 class="text-gray-900 font-bold text-lg mb-3 relative z-10">Hasil Memuaskan</h5>

                            <!-- Review Text -->
                            <div class="mb-8 relative z-10 flex-1">
                                <?php if (isset($testi['review'])): ?>
                                <p class="text-gray-500 text-sm leading-relaxed">"<?= htmlspecialchars($testi['review']) ?>"</p>
                                <?php else: ?>
                                <p class="text-gray-500 text-sm leading-relaxed">"Progress perawatan terpantau sangat baik melalui Skincare Journal. Kulit terasa lebih sehat dan glowing setiap harinya."</p>
                                <?php endif; ?>
                            </div>

                            <!-- Before/After Images -->
                            <div class="flex gap-3 h-[180px] sm:h-[220px] relative z-10">
                                <div class="w-1/2 relative rounded-2xl overflow-hidden group-hover:shadow-md transition-all duration-300">
                                    <img src="<?= htmlspecialchars($testi['photo_before']) ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" alt="Before">
                                    <div class="absolute bottom-2 left-2 bg-white/90 backdrop-blur-md px-2.5 py-1 rounded-lg shadow-sm">
                                        <span class="text-[10px] font-bold text-gray-800 tracking-wider uppercase">Before</span>
                                    </div>
                                </div>
                                <div class="w-1/2 relative rounded-2xl overflow-hidden group-hover:shadow-md transition-all duration-300">
                                    <img src="<?= htmlspecialchars($testi['photo_after']) ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" alt="After">
                                    <div class="absolute bottom-2 right-2 bg-primary/90 backdrop-blur-md px-2.5 py-1 rounded-lg shadow-sm">
                                        <span class="text-[10px] font-bold text-white tracking-wider uppercase">After</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <!-- Pagination -->
                <div class="swiper-pagination !-bottom-2"></div>
            </div>
            
            <div class="text-center mt-4" data-aos="fade-up" data-aos-delay="200">
                <a href="#konsultasi" class="inline-flex items-center gap-2 px-8 py-4 bg-primary hover:bg-primary-dark text-white rounded-full font-semibold transition-all shadow-lg hover:shadow-primary/40 transform hover:-translate-y-1">
                    Mulai Perjalanan Glow Kamu
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </a>
            </div>
        </div>
    </div>

    <!-- Konsultasi Section -->
    <div id="konsultasi" class="bg-primary/5 py-16 lg:py-24 border-y border-blue-100">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center" data-aos="zoom-in">
            <h2 class="text-3xl font-extrabold text-gray-900 mb-4">Konsultasikan Kulitmu Bersama Ahli</h2>
            <p class="text-lg text-gray-600 mb-10 max-w-2xl mx-auto">
                Dapatkan rekomendasi produk dan panduan merawat kulit langsung dari ahlinya. 
                <strong class="text-primary">Gratis untuk semua pengguna — langsung konsultasi tanpa harus beli dulu!</strong>
            </p>
            
            <div class="bg-white p-8 rounded-3xl shadow-xl max-w-lg mx-auto border-t-4 border-primary">
                <div class="mb-6 flex justify-center">
                    <img src="assets/images/logo_np_glow.jpeg" alt="Logo" class="h-20 object-contain drop-shadow-sm">
                </div>
                <h3 class="text-xl font-bold mb-2">Siap Mulai Konsultasi?</h3>
                <p class="text-gray-500 text-sm mb-8">Pilih konsultasi dengan AI Assistant 24 jam atau langsung chat dengan Tim Ahli kami.</p>
                <button onclick="checkConsultation()" class="btn-konsultasi w-full flex items-center justify-center px-8 py-4 border border-transparent text-lg font-bold rounded-xl text-white bg-primary hover:bg-primary-dark transition-all shadow-lg hover:shadow-primary/40 transform hover:-translate-y-1">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
                    Mulai Konsultasi Gratis
                </button>
            </div>
        </div>
    </div>

    <!-- Marketplace Section -->
    <div id="marketplace" class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-12" data-aos="fade-up">
                <div>
                    <h2 class="text-3xl font-extrabold text-gray-900">Produk Terlaris NPGLOW</h2>
                    <p class="mt-2 text-gray-500">Pilih rangkaian produk terbaik untuk kulit cantikmu.</p>
                </div>
            </div>

            <!-- Product Grid -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-8">
                <?php
                // Fetch products along with their sold count from orders
                $prodQuery = $conn->query("
                    SELECT p.*, COUNT(o.id) as sold_count 
                    FROM products p 
                    LEFT JOIN orders o ON p.id = o.product_id AND o.status = 'completed'
                    GROUP BY p.id 
                    ORDER BY p.id DESC
                ");
                $delay = 100;
                while ($product = $prodQuery->fetch_assoc()):
                ?>
                <!-- Product Card -->
                <div class="bg-white rounded-[1.2rem] sm:rounded-[2rem] shadow-[0_8px_20px_rgb(0,0,0,0.06)] hover:shadow-[0_20px_40px_rgb(0,0,0,0.12)] transition-all duration-300 overflow-hidden group flex flex-col h-full" data-aos="fade-up" data-aos-delay="<?= $delay ?>">
                    
                    <!-- Image Section -->
                    <div class="relative bg-gradient-to-br from-blue-50 to-blue-200 aspect-square sm:aspect-[4/3] flex items-center justify-center p-4 sm:p-6 overflow-hidden">
                        <!-- Heart Icon -->
                        <button class="absolute top-2 right-2 sm:top-4 sm:right-4 z-20 w-7 h-7 sm:w-10 sm:h-10 rounded-full bg-white/20 backdrop-blur-md flex items-center justify-center text-white hover:bg-white hover:text-rose-500 transition-colors">
                            <svg class="w-3.5 h-3.5 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                        </button>
                        
                        <?php if (!empty($product['image_url'])): ?>
                            <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="w-full h-full object-cover sm:object-contain transition-transform duration-700 group-hover:scale-110 drop-shadow-xl">
                        <?php else: ?>
                            <div
                                class="w-16 h-16 sm:w-24 sm:h-24 bg-white/50 backdrop-blur-sm rounded-full flex items-center justify-center text-white font-bold text-xs sm:text-sm shadow-sm z-10">
                                Product
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Content Section -->
                    <div class="p-3 sm:p-6 flex flex-col flex-1 bg-white relative z-10 -mt-3 sm:-mt-6 rounded-t-[1.2rem] sm:rounded-t-[2rem]">
                        <h3 class="text-[12px] sm:text-[1.1rem] font-bold text-gray-800 mb-1 sm:mb-2 tracking-tight group-hover:text-primary transition-colors leading-snug line-clamp-2"><?= htmlspecialchars($product['name']) ?></h3>
                        
                        <!-- Rating & Terjual -->
                        <div class="flex items-center gap-1.5 mb-1.5 sm:mb-2">
                            <div class="flex items-center text-yellow-400">
                                <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                                <span class="text-[10px] sm:text-[11px] font-bold text-gray-700 ml-0.5">4.9</span>
                            </div>
                            <span class="w-0.5 h-0.5 bg-gray-300 rounded-full"></span>
                            <span class="text-[9px] sm:text-[11px] text-gray-500"><?= $product['sold_count'] > 0 ? $product['sold_count'] . ' Terjual' : 'Baru' ?></span>
                        </div>

                        <!-- Badges -->
                        <div class="flex flex-wrap gap-1 sm:gap-1.5 mb-2 sm:mb-3">
                            <span class="px-1.5 sm:px-2 py-0.5 sm:py-1 border border-gray-300 rounded text-[8px] sm:text-[9px] font-bold text-gray-600 uppercase tracking-widest">ORIGINAL</span>
                            <span class="px-1.5 sm:px-2 py-0.5 sm:py-1 border border-gray-300 rounded text-[8px] sm:text-[9px] font-bold text-gray-600 uppercase tracking-widest hidden sm:inline-block">BPOM</span>
                        </div>
                        
                        <p class="text-[10px] sm:text-[12px] text-gray-500 font-medium mb-3 sm:mb-5 line-clamp-2 leading-relaxed flex-1"><?= htmlspecialchars($product['description']) ?></p>
                        
                        <!-- Bottom Action Row -->
                        <div class="flex flex-row items-end sm:items-center justify-between mt-auto gap-2">
                            <div>
                                <span class="block text-[8px] sm:text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-0.5">PRICE</span>
                                <div class="flex items-baseline gap-1 flex-wrap">
                                    <span class="text-[12px] sm:text-[16px] font-black text-gray-800 tracking-tight leading-none">Rp <?= number_format($product['price'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                            <a href="checkout.php?product_id=<?= $product['id'] ?>"
                                class="w-auto text-center bg-primary hover:bg-blue-600 text-white px-3 sm:px-5 py-1.5 sm:py-2.5 rounded-lg sm:rounded-xl font-bold text-[10px] sm:text-[13px] transition-colors shadow-sm hover:shadow-md whitespace-nowrap">
                                Beli
                            </a>
                        </div>
                    </div>
                </div>
                <?php 
                $delay += 100;
                endwhile; 
                ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="relative overflow-hidden pt-24 pb-48 lg:pb-64 border-t border-gray-200 bg-gradient-to-b from-[#f8fafc] via-[#f1f5f3] to-[#e6ede8]">
        
        <!-- Large Background Text (Watermark) -->
        <div class="absolute bottom-[-8%] left-0 w-full text-center flex justify-center pointer-events-none select-none z-0">
            <span class="text-[18vw] font-bold leading-none tracking-tighter text-white opacity-90" style="text-shadow: 0 10px 30px rgba(0,0,0,0.02);">NPGLOW</span>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" data-aos="fade-up">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-12 lg:gap-8">
                
                <!-- Column 1: Brand Info -->
                <div class="md:col-span-12 lg:col-span-4 flex flex-col items-start">
                    <div class="flex items-center gap-3 mb-6">
                        <img class="h-10 w-auto rounded-lg shadow-sm" src="assets/images/logo_np_glow.jpeg" alt="NPGLOW Logo">
                        <span class="font-bold text-xl text-gray-900 tracking-tight">NPGLOW</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Solusi perawatan kulit cerdas Anda</h3>
                    <p class="text-sm text-gray-600 mb-8 leading-relaxed max-w-xs">
                        NPGLOW menghadirkan produk perawatan kulit inovatif, aman, dan tersertifikasi BPOM untuk mendukung kecantikan alami Anda setiap hari.
                    </p>
                    <a href="#marketplace" class="bg-gray-900 text-white hover:bg-gray-800 transition-colors px-6 py-3 rounded-xl text-sm font-semibold shadow-md flex items-center gap-2 mb-8">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        Belanja Sekarang
                    </a>
                    
                    <p class="text-xs text-gray-500 mb-2">&copy; 2026 NPGLOW. All rights reserved.</p>
                    <p class="text-xs text-gray-500 flex items-center gap-1">Built with <span class="text-[#3ca6f2]">&hearts;</span> by NPGLOW Team</p>
                </div>

                <!-- Column 2: Menu -->
                <div class="md:col-span-4 lg:col-span-2 lg:col-start-6">
                    <h4 class="text-sm font-bold text-gray-900 mb-6">Menu</h4>
                    <ul class="space-y-4">
                        <li><a href="#" class="text-sm text-gray-600 hover:text-gray-900 transition-colors font-medium">Beranda</a></li>
                        <li><a href="#marketplace" class="text-sm text-gray-600 hover:text-gray-900 transition-colors font-medium">Produk</a></li>
                        <li><a href="#konsultasi" class="text-sm text-gray-600 hover:text-gray-900 transition-colors font-medium">Konsultasi</a></li>
                        <li><a href="#" class="text-sm text-gray-600 hover:text-gray-900 transition-colors font-medium">Tentang Kami</a></li>
                        <li><a href="#" class="text-sm text-gray-600 hover:text-gray-900 transition-colors font-medium">Testimoni</a></li>
                    </ul>
                </div>

                <!-- Column 3: Navigation -->
                <div class="md:col-span-4 lg:col-span-2">
                    <h4 class="text-sm font-bold text-gray-900 mb-6">Navigation</h4>
                    <ul class="space-y-4">
                        <li><a href="#" class="text-sm text-gray-600 hover:text-gray-900 transition-colors font-medium">Contact</a></li>
                        <li><a href="#" class="text-sm text-gray-600 hover:text-gray-900 transition-colors font-medium">Roadmap</a></li>
                        <li><a href="#" class="text-sm text-gray-600 hover:text-gray-900 transition-colors font-medium">Privacy policy</a></li>
                        <li><a href="#" class="text-sm text-gray-600 hover:text-gray-900 transition-colors font-medium">Terms of service</a></li>
                        <li><a href="#" class="text-sm text-gray-600 hover:text-gray-900 transition-colors font-medium">Customer portal</a></li>
                    </ul>
                </div>

                <!-- Column 4: Social Media -->
                <div class="md:col-span-4 lg:col-span-3">
                    <h4 class="text-sm font-bold text-gray-900 mb-6">Sosial Media</h4>
                    <ul class="space-y-4">
                        <li>
                            <a href="https://web.facebook.com/profile.php?id=61565244363300" target="_blank" class="flex items-center gap-3 text-sm text-gray-600 hover:text-[#1877F2] transition-colors group font-medium">
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-[#1877F2] transition-colors" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                Facebook
                            </a>
                        </li>
                        <li>
                            <a href="https://www.instagram.com/npglow_brandofficial/" target="_blank" class="flex items-center gap-3 text-sm text-gray-600 hover:text-[#E4405F] transition-colors group font-medium">
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-[#E4405F] transition-colors" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                                Instagram
                            </a>
                        </li>
                        <li>
                            <a href="https://www.tiktok.com/@npglow_brandofficial" target="_blank" class="flex items-center gap-3 text-sm text-gray-600 hover:text-black transition-colors group font-medium">
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-black transition-colors" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005 15.71a6.34 6.34 0 006.33 6.33 6.32 6.32 0 006.32-6.19v-5.91a8.27 8.27 0 003.35.7v-3.4a4.85 4.85 0 01-1.41-.55z"/></svg>
                                TikTok
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <!-- Script Utama -->
    <script>
        const btn = document.getElementById('mobile-menu-btn');
        const menu = document.getElementById('mobile-menu');
        const hamburgerIcon = document.getElementById('hamburger-icon');
        const closeIcon = document.getElementById('close-icon');

        btn.addEventListener('click', () => {
            if (menu.classList.contains('hidden')) {
                // Open menu
                menu.classList.remove('hidden');
                setTimeout(() => {
                    menu.classList.remove('opacity-0', '-translate-y-4');
                    menu.classList.add('opacity-100', 'translate-y-0');
                }, 10);
                
                // Animate icons
                hamburgerIcon.classList.add('hidden', 'rotate-90', 'opacity-0');
                hamburgerIcon.classList.remove('block', 'rotate-0', 'opacity-100');
                
                closeIcon.classList.remove('hidden', 'rotate-90', 'opacity-0');
                closeIcon.classList.add('block', 'rotate-0', 'opacity-100');
            } else {
                // Close menu
                menu.classList.remove('opacity-100', 'translate-y-0');
                menu.classList.add('opacity-0', '-translate-y-4');
                setTimeout(() => {
                    menu.classList.add('hidden');
                }, 300); // match duration

                // Animate icons
                closeIcon.classList.remove('block', 'rotate-0', 'opacity-100');
                closeIcon.classList.add('hidden', 'rotate-90', 'opacity-0');

                hamburgerIcon.classList.remove('hidden', 'rotate-90', 'opacity-0');
                hamburgerIcon.classList.add('block', 'rotate-0', 'opacity-100');
            }
        });

        // Consultation Logic
        function checkConsultation() {
            const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

            if (!isLoggedIn) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Belum Login',
                    text: 'Silakan login terlebih dahulu untuk mengakses konsultasi.',
                    confirmButtonColor: '#3ca6f2',
                    confirmButtonText: 'Masuk / Login'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'login.php';
                    }
                });
                return;
            }

            // All logged-in users can access consultation
            window.location.href = 'konsultasi.php';
        }
    </script>
    
    <!-- AOS Script -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true,
        });

        // Active Nav Link based on Scroll
        document.addEventListener('DOMContentLoaded', () => {
            const navLinks = document.querySelectorAll('.nav-link');
            const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');

            const observerOptions = {
                root: null,
                rootMargin: '-20% 0px -70% 0px',
                threshold: 0
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const id = entry.target.getAttribute('id');
                        
                        // Update Desktop Nav
                        navLinks.forEach(link => {
                            link.classList.remove('border-primary', 'text-gray-900');
                            link.classList.add('border-transparent', 'text-gray-500', 'hover:border-gray-300', 'hover:text-gray-700');
                            if (link.getAttribute('href') === `#${id}`) {
                                link.classList.add('border-primary', 'text-gray-900');
                                link.classList.remove('border-transparent', 'text-gray-500', 'hover:border-gray-300', 'hover:text-gray-700');
                            }
                        });

                        // Update Mobile Nav
                        mobileNavLinks.forEach(link => {
                            link.classList.remove('bg-blue-50', 'border-primary', 'text-primary');
                            link.classList.add('border-transparent', 'text-gray-500', 'hover:bg-gray-50', 'hover:border-gray-300', 'hover:text-gray-700');
                            if (link.getAttribute('href') === `#${id}`) {
                                link.classList.add('bg-blue-50', 'border-primary', 'text-primary');
                                link.classList.remove('border-transparent', 'text-gray-500', 'hover:bg-gray-50', 'hover:border-gray-300', 'hover:text-gray-700');
                            }
                        });
                    }
                });
            }, observerOptions);

            const targetSections = ['beranda', 'marketplace', 'konsultasi'];
            targetSections.forEach(id => {
                const section = document.getElementById(id);
                if (section) {
                    observer.observe(section);
                }
            });
        });
    </script>

    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const swiper = new Swiper('.testimoni-swiper', {
                slidesPerView: 1.2,
                spaceBetween: 16,
                grabCursor: true,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                    dynamicBullets: true,
                },
                breakpoints: {
                    640: {
                        slidesPerView: 2.2,
                        spaceBetween: 20,
                    },
                    1024: {
                        slidesPerView: 3,
                        spaceBetween: 30,
                    },
                }
            });
        });
    </script>

    <?php if ($isLoggedIn): ?>
    <!-- Bottom Navbar for logged-in customers -->
    <?php 
    $bottomNavActive = 'beranda';
    include 'includes/bottom-nav.php'; 
    ?>
    <style>
        /* Add bottom padding when bottom nav is visible */
        @media (max-width: 639px) {
            body { padding-bottom: 4rem; }
        }
    </style>
    <?php endif; ?>

</body>
<?php include 'includes/pwa-sw.php'; ?>
</html>
