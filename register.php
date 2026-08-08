<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth-helper.php';

// If already logged in, redirect based on role
if (is_logged_in()) {
    $currentRole = get_current_user_role();
    if ($currentRole === 'admin') {
        header("Location: admin/index.php");
    } elseif ($currentRole === 'expert') {
        header("Location: expert/index.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $password);
    
    if ($stmt->execute()) {
        header("Location: login.php?success=1");
        exit();
    } else {
        $error = "Pendaftaran gagal! Email mungkin sudah digunakan.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - NPGLOW</title>
    <meta name="description" content="Daftar akun NPGLOW untuk belanja skincare terpercaya dan konsultasi kecantikan gratis.">
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
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; margin: 0; padding: 0; }

        /* Floating animation keyframes - matching hero section */
        @keyframes float-wiggle {
            0%, 100% { transform: translateY(0) rotate(-4deg); }
            50% { transform: translateY(-15px) rotate(4deg); }
        }
        @keyframes float-wiggle-delay {
            0%, 100% { transform: translateY(0) rotate(3deg); }
            50% { transform: translateY(-12px) rotate(-3deg); }
        }
        @keyframes float-wiggle-fast {
            0%, 100% { transform: translateY(0) rotate(-2deg); }
            50% { transform: translateY(-10px) rotate(5deg); }
        }
        .animate-float-wiggle {
            animation: float-wiggle 4s ease-in-out infinite;
        }
        .animate-float-wiggle-delay {
            animation: float-wiggle-delay 5s ease-in-out infinite 1.5s;
        }
        .animate-float-wiggle-fast {
            animation: float-wiggle-fast 3.5s ease-in-out infinite 0.5s;
        }

        /* Subtle pulse for the gradient overlay */
        @keyframes pulse-overlay {
            0%, 100% { opacity: 0.4; }
            50% { opacity: 0.6; }
        }

        /* Input focus animation */
        .auth-input {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .auth-input:focus {
            box-shadow: 0 0 0 3px rgba(60, 166, 242, 0.15);
            border-color: #3ca6f2;
        }

        /* Button hover effect */
        .auth-btn {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .auth-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(60, 166, 242, 0.35);
        }
        .auth-btn:active {
            transform: translateY(0);
        }

        /* Image panel styling */
        .image-panel {
            position: relative;
            overflow: hidden;
        }
        .image-panel::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(60, 166, 242, 0.15) 0%, rgba(46, 140, 207, 0.25) 50%, rgba(60, 166, 242, 0.1) 100%);
            z-index: 1;
            animation: pulse-overlay 6s ease-in-out infinite;
        }

        /* Password toggle */
        .password-toggle {
            transition: color 0.2s;
        }
        .password-toggle:hover {
            color: #3ca6f2;
        }

        /* Fade-in animation */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out forwards;
        }
        .fade-in-up-delay-1 { animation-delay: 0.1s; opacity: 0; }
        .fade-in-up-delay-2 { animation-delay: 0.2s; opacity: 0; }
        .fade-in-up-delay-3 { animation-delay: 0.3s; opacity: 0; }
        .fade-in-up-delay-4 { animation-delay: 0.4s; opacity: 0; }
        .fade-in-up-delay-5 { animation-delay: 0.5s; opacity: 0; }
        .fade-in-up-delay-6 { animation-delay: 0.6s; opacity: 0; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="flex min-h-screen">
        
        <!-- Left Panel - Image -->
        <div class="hidden lg:flex lg:w-1/2 image-panel relative items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200">
            <!-- Background Image -->
            <img 
                src="assets/images/image_signup.jpg" 
                alt="NPGLOW Skincare" 
                class="absolute inset-0 w-full h-full object-cover"
            >

            <!-- Gradient Overlay -->
            <div class="absolute inset-0 bg-gradient-to-t from-primary/30 via-transparent to-primary-dark/20 z-[2]"></div>

            <!-- Overlay Icons (matching hero section style) -->
            <!-- Icon 1: Sparkle / Glow -->
            <div class="absolute top-[20%] left-[10%] z-10 bg-white/90 backdrop-blur-md p-3.5 rounded-full shadow-xl text-yellow-400 animate-float-wiggle border border-white/50">
                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
            </div>

            <!-- Icon 2: Verified / Skincare Trust Badge -->
            <div class="absolute bottom-[25%] right-[8%] z-10 bg-white/90 backdrop-blur-md px-5 py-3.5 rounded-2xl shadow-xl flex items-center gap-3 animate-float-wiggle-delay border border-white/50">
                <div class="bg-blue-100 p-2 rounded-full text-primary">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-black text-gray-800 tracking-tight">100% Original</p>
                    <p class="text-xs font-semibold text-gray-500">Teruji Klinis</p>
                </div>
            </div>

            <!-- Icon 3: Beauty Heart -->
            <div class="absolute top-[12%] right-[15%] z-10 bg-white/90 backdrop-blur-md p-4 rounded-full shadow-xl text-rose-400 animate-float-wiggle-fast border border-white/50">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
            </div>

            <!-- Icon 4: Users / Community -->
            <div class="absolute bottom-[40%] left-[8%] z-10 bg-white/90 backdrop-blur-md px-4 py-3 rounded-2xl shadow-xl flex items-center gap-2.5 animate-float-wiggle-fast border border-white/50" style="animation-delay: 1s;">
                <div class="bg-emerald-100 p-2 rounded-full text-emerald-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-800">5K+</p>
                    <p class="text-xs text-gray-500">Pelanggan Puas</p>
                </div>
            </div>

            <!-- Icon 5: Skincare Droplet -->
            <div class="absolute bottom-[12%] left-[40%] z-10 bg-white/90 backdrop-blur-md p-3 rounded-full shadow-xl text-teal-400 animate-float-wiggle border border-white/50" style="animation-delay: 0.8s;">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c-1.2 1.6-5 6.8-5 10a5 5 0 0010 0c0-3.2-3.8-8.4-5-10z"></path></svg>
            </div>

            <!-- Bottom branding overlay -->
            <div class="absolute bottom-0 left-0 right-0 z-10 p-8 bg-gradient-to-t from-black/40 to-transparent">
                <div class="flex items-center gap-3">
                    <img src="assets/images/logo_np_glow.jpeg" alt="NPGLOW" class="w-10 h-10 rounded-full shadow-lg">
                    <div>
                        <h3 class="text-white font-bold text-lg tracking-tight">NPGLOW</h3>
                        <p class="text-white/80 text-sm">Bergabung Bersama Ribuan Pelanggan Kami</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel - Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 bg-white">
            <div class="w-full max-w-md">
                <!-- Back to Home -->
                <a href="index.php" class="inline-flex items-center gap-2 text-sm text-gray-400 hover:text-primary transition-colors mb-8 group fade-in-up">
                    <svg class="w-4 h-4 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    Kembali ke Beranda
                </a>

                <!-- Header -->
                <div class="mb-8 fade-in-up fade-in-up-delay-1">
                    <!-- Mobile logo -->
                    <div class="flex items-center gap-3 mb-6 lg:hidden">
                        <img src="assets/images/logo_np_glow.jpeg" alt="NPGLOW" class="w-10 h-10 rounded-full shadow">
                        <span class="font-bold text-lg text-primary">NPGLOW</span>
                    </div>
                    <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Daftar Akun</h1>
                    <p class="mt-2 text-gray-500">Buat akun baru untuk mulai berbelanja</p>
                </div>

                <!-- Alerts -->
                <?php if(isset($error)): ?>
                    <div class="bg-red-50 border border-red-200 text-red-600 p-4 rounded-xl mb-6 text-sm flex items-center gap-3 fade-in-up">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" class="space-y-5">
                    <!-- Nama Lengkap -->
                    <div class="fade-in-up fade-in-up-delay-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nama Lengkap <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            </div>
                            <input 
                                type="text" 
                                name="name" 
                                id="register-name"
                                required 
                                placeholder="Nama Lengkap"
                                class="auth-input w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl bg-gray-50/50 text-gray-800 placeholder-gray-400 focus:bg-white focus:outline-none text-sm"
                            >
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="fade-in-up fade-in-up-delay-3">
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Email <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path></svg>
                            </div>
                            <input 
                                type="email" 
                                name="email" 
                                id="register-email"
                                required 
                                placeholder="Email"
                                class="auth-input w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl bg-gray-50/50 text-gray-800 placeholder-gray-400 focus:bg-white focus:outline-none text-sm"
                            >
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="fade-in-up fade-in-up-delay-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Password <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            </div>
                            <input 
                                type="password" 
                                name="password" 
                                id="register-password"
                                required 
                                placeholder="Password"
                                class="auth-input w-full pl-11 pr-12 py-3 border border-gray-200 rounded-xl bg-gray-50/50 text-gray-800 placeholder-gray-400 focus:bg-white focus:outline-none text-sm"
                            >
                            <button type="button" onclick="togglePassword('register-password', this)" class="absolute inset-y-0 right-0 pr-3.5 flex items-center password-toggle text-gray-400">
                                <svg class="w-5 h-5 eye-open" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                <svg class="w-5 h-5 eye-closed hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="fade-in-up fade-in-up-delay-5">
                        <button 
                            type="submit" 
                            id="register-submit"
                            class="auth-btn w-full bg-primary text-white py-3.5 rounded-xl font-bold text-sm tracking-wide hover:bg-primary-dark"
                        >
                            Daftar
                        </button>
                    </div>
                </form>

                <!-- Divider -->
                <div class="my-6 flex items-center gap-4 fade-in-up fade-in-up-delay-5">
                    <div class="flex-1 h-px bg-gray-200"></div>
                    <span class="text-xs text-gray-400 font-medium">Daftar Dengan</span>
                    <div class="flex-1 h-px bg-gray-200"></div>
                </div>

                <!-- Google Button -->
                <a href="auth/google_login.php" class="w-full flex items-center justify-center gap-3 py-3 px-4 border-2 border-gray-200 rounded-xl text-sm font-semibold text-gray-600 hover:border-primary/30 hover:bg-blue-50/50 transition-all fade-in-up fade-in-up-delay-5" id="register-google">
                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Google
                </a>

                <!-- Login Link -->
                <p class="text-center text-sm text-gray-500 mt-8 fade-in-up fade-in-up-delay-6">
                    Sudah Punya Akun? 
                    <a href="login.php" class="text-primary font-bold hover:underline hover:text-primary-dark transition-colors">Masuk Disini !</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const eyeOpen = btn.querySelector('.eye-open');
            const eyeClosed = btn.querySelector('.eye-closed');
            
            if (input.type === 'password') {
                input.type = 'text';
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            } else {
                input.type = 'password';
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            }
        }
    </script>
</body>
<?php include 'includes/pwa-sw.php'; ?>
</html>
