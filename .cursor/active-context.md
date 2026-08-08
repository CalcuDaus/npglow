> **BrainSync Context Pumper** 🧠
> Dynamically loaded for active file: `checkout.php` (Domain: **Generic Logic**)

### 📐 Generic Logic Conventions & Fixes
- **[decision] Optimized Static — parallelizes async operations for speed**: - const CACHE_NAME = 'npglow-v1';
+ const CACHE_NAME = 'npglow-v2';
- // Static assets to cache on install
+ // Static assets to cache immediately on install
-     '/npglow/assets/images/logo_np_glow.jpeg',
+     '/npglow/manifest.json',
-     'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap'
+     '/npglow/assets/images/logo_np_glow.jpeg',
- ];
+     '/npglow/assets/icons/icon-192.png',
- 
+     '/npglow/assets/icons/icon-512.png',
- // Install: cache the offline page and core static assets
+     '/npglow/assets/icons/icon-maskable-192.png',
- self.addEventListener('install', (event) => {
+     '/npglow/assets/icons/icon-maskable-512.png',
-     event.waitUntil(
+     'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap',
-         caches.open(CACHE_NAME).then((cache) => {
+     'https://cdn.tailwindcss.com',
-             return cache.addAll(STATIC_ASSETS);
+     'https://cdn.jsdelivr.net/npm/sweetalert2@11'
-         })
+ ];
-     );
+ 
-     self.skipWaiting();
+ // Install Event: cache the offline page and essential assets
- });
+ self.addEventListener('install', (event) => {
- 
+     event.waitUntil(
- // Activate: clean up old caches
+         caches.open(CACHE_NAME).then((cache) => {
- self.addEventListener('activate', (event) => {
+             return cache.addAll(STATIC_ASSETS).catch((err) => {
-     event.waitUntil(
+                 console.warn('[SW] Pre-caching non-fatal warning:', err);
-         caches.keys().then((keys) => {
+             });
-             return Promise.all(
+         })
-                 keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
+     );
-             );
+     self.skipWaiting();
-         })
+ });
-     );
+ 
-     self.clients.claim();
+ // Activate Event: clean up old caches
- });
+ self.addEventListener('activate', (event) => {
- 
+     event.waitUntil(
- // Fetch: Network First strategy for PHP pages, Cache First for 
… [diff truncated]

📌 IDE AST Context: Modified symbols likely include [CACHE_NAME, OFFLINE_URL, STATIC_ASSETS, self.addEventListener('install') callback, self.addEventListener('activate') callback]
- **[what-changed] 🟢 Edited dashboard.php (8 changes, 2min)**: Active editing session on dashboard.php.
8 content changes over 2 minutes.
- **[what-changed] 🟢 Edited index.php (101 changes, 103min)**: Active editing session on index.php.
101 content changes over 103 minutes.
- **[what-changed] 🟢 Edited .gitignore (7 changes, 25min)**: Active editing session on .gitignore.
7 content changes over 25 minutes.
- **[what-changed] 🟢 Edited index.php (8 changes, 7min)**: Active editing session on index.php.
8 content changes over 7 minutes.
- **[decision] decision in index.php**: -                     <div class="text-center p-6 bg-slate-50 rounded-2xl hover:shadow-xl transition-shadow border border-slate-100" data-aos="fade-up" data-aos-delay="100">
+                     <div class="group text-center p-6 bg-slate-50 hover:bg-white rounded-2xl hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 border border-slate-100 cursor-pointer" data-aos="fade-up" data-aos-delay="100">
-                         <div class="flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 text-primary mx-auto mb-6">
+                         <div class="flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 text-primary mx-auto mb-6 group-hover:bg-primary group-hover:text-white group-hover:scale-110 transition-all duration-300">
-                         <h3 class="text-xl font-bold text-gray-900 mb-3">100% Original & BPOM</h3>
+                         <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-primary transition-colors duration-300">100% Original & BPOM</h3>
-                     <div class="text-center p-6 bg-slate-50 rounded-2xl hover:shadow-xl transition-shadow border border-slate-100" data-aos="fade-up" data-aos-delay="200">
+                     <div class="group text-center p-6 bg-slate-50 hover:bg-white rounded-2xl hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 border border-slate-100 cursor-pointer" data-aos="fade-up" data-aos-delay="200">
-                         <div class="flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 text-primary mx-auto mb-6">
+                         <div class="flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 text-primary mx-auto mb-6 group-hover:bg-primary group-hover:text-white group-hover:scale-110 transition-all duration-300">
-                         <h3 class="text-xl font-bold text-gray-900 mb-3">Respons Cepat</h3>
+                         <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:te
… [diff truncated]
- **[what-changed] Replaced auth Custom**: -     <!-- Custom CSS (optional) -->
+     <!-- AOS CSS -->
-     <style>
+     <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
-         body { font-family: 'Inter', sans-serif; }
+     <!-- Custom CSS (optional) -->
-         .hero-pattern {
+     <style>
-             background-color: #f8fafc;
+         body { font-family: 'Inter', sans-serif; }
-             background-image: radial-gradient(#3ca6f2 0.5px, transparent 0.5px), radial-gradient(#3ca6f2 0.5px, #f8fafc 0.5px);
+         .hero-pattern {
-             background-size: 20px 20px;
+             background-color: #f8fafc;
-             background-position: 0 0, 10px 10px;
+             background-image: radial-gradient(#3ca6f2 0.5px, transparent 0.5px), radial-gradient(#3ca6f2 0.5px, #f8fafc 0.5px);
-             background-opacity: 0.1;
+             background-size: 20px 20px;
-         }
+             background-position: 0 0, 10px 10px;
-     </style>
+             background-opacity: 0.1;
- </head>
+         }
- <body class="bg-slate-50 text-slate-800 antialiased">
+     </style>
- 
+ </head>
-     <!-- Navbar -->
+ <body class="bg-slate-50 text-slate-800 antialiased">
-     <nav class="bg-white shadow-sm sticky top-0 z-50">
+ 
-         <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
+     <!-- Navbar -->
-             <div class="flex justify-between h-20">
+     <nav class="bg-white shadow-sm sticky top-0 z-50">
-                 <div class="flex items-center">
+         <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
-                     <a href="#" class="flex-shrink-0 flex items-center gap-3">
+             <div class="flex justify-between h-20">
-                         <img class="h-12 w-auto object-contain" src="logo_np_glow.jpeg" alt="NPGLOW Logo">
+                 <div class="flex items-center">
-                         <span class="font-bold text-xl tracking-tight text-primary">NPGLOW</span>
+                     <a href="#" class="flex-shrink-0 flex
… [diff truncated]
- **[what-changed] 🟢 Edited includes/image-helper.php (6 changes, 639min)**: Active editing session on includes/image-helper.php.
6 content changes over 639 minutes.
- **[what-changed] 🟢 Edited includes/ai-config.php (6 changes, 4min)**: Active editing session on includes/ai-config.php.
6 content changes over 4 minutes.
