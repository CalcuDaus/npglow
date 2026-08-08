<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth-helper.php';
require_once 'includes/settings-helper.php';

// Customer only guard
guard_customer_only();

$userId = (int)$_SESSION['user_id'];

// Fetch user data (no purchase gate — all logged-in users can consult)
$stmt = $conn->prepare("SELECT has_purchased, name FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get operational & online status
$opStatus = get_expert_operational_status($conn);

// Get user face photos for sidebar comparison
$stmt = $conn->prepare("SELECT * FROM user_face_photos WHERE user_id = ? AND photo_type = 'initial' ORDER BY created_at ASC LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$initialPhoto = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT * FROM user_face_photos WHERE user_id = ? ORDER BY taken_at DESC, created_at DESC LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$latestPhoto = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konsultasi Tim Ahli - NPGLOW</title>
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
    <style>
        .glass-card { background: rgba(255,255,255,0.92); backdrop-filter: blur(16px); }
        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.6; }
            100% { transform: scale(0.95); opacity: 1; }
        }
        .pulse-ring { animation: pulse-ring 2s ease-in-out infinite; }
    </style>
</head>
<body class="bg-slate-50 h-screen flex flex-col">
    <!-- Header -->
    <header class="glass-card shadow-sm border-b border-gray-200 sticky top-0 z-20">
        <div class="flex items-center gap-3 max-w-4xl mx-auto w-full p-3 sm:p-4">
            <a href="konsultasi.php" class="text-gray-400 hover:text-primary p-2 bg-gray-50 rounded-full transition-colors flex-shrink-0" title="Kembali">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <div class="flex items-center gap-3 flex-1">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-primary font-bold flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
                <div>
                    <h1 class="font-bold text-sm sm:text-base text-gray-800 leading-tight">Tim Ahli NPGLOW</h1>
                    <p id="chat-status-text" class="text-xs flex items-center gap-1.5 <?= $opStatus['is_online'] ? 'text-emerald-600 font-semibold' : 'text-gray-400' ?>">
                        <span id="chat-status-dot" class="w-2 h-2 rounded-full <?= $opStatus['dot_class'] ?>"></span>
                        <span id="chat-status-label"><?= htmlspecialchars($opStatus['status_label']) ?></span>
                    </p>
                </div>
            </div>

            <!-- Switch to AI Chat button -->
            <a href="chat-ai.php" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-violet-50 text-violet-700 hover:bg-violet-100 border border-violet-200 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                Chat AI Instan
            </a>

            <!-- Toggle Photo Panel -->
            <?php if ($initialPhoto): ?>
            <button onclick="togglePhotoPanel()" class="text-gray-400 hover:text-primary p-2 bg-gray-50 rounded-full transition-colors flex-shrink-0" title="Lihat foto wajah">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </button>
            <?php endif; ?>
        </div>

        <!-- Offline / Operating Notice Banner -->
        <div id="offline-banner" class="<?= $opStatus['is_online'] ? 'hidden' : '' ?> bg-amber-50 border-t border-amber-200/60 px-4 py-2 text-xs text-amber-800">
            <div class="max-w-4xl mx-auto flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span id="offline-banner-text"><?= htmlspecialchars($opStatus['offline_message']) ?></span>
                </div>
                <a href="chat-ai.php" class="text-violet-700 font-bold hover:underline whitespace-nowrap">Gunakan AI →</a>
            </div>
        </div>

        <!-- Collapsible Face Photo Panel -->
        <?php if ($initialPhoto): ?>
        <div id="photo-panel" class="hidden border-t border-gray-100 bg-gray-50/80">
            <div class="max-w-4xl mx-auto px-4 py-3">
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2">Foto Wajah — Referensi Konsultasi</p>
                <div class="flex gap-3 overflow-x-auto pb-1">
                    <div class="flex-shrink-0 text-center">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl overflow-hidden bg-gray-200 shadow-sm border-2 border-blue-300">
                            <img src="<?= htmlspecialchars($initialPhoto['photo_path']) ?>" alt="Foto Awal" class="w-full h-full object-cover">
                        </div>
                        <p class="text-[9px] text-gray-500 mt-1 font-semibold">Awal</p>
                        <p class="text-[8px] text-gray-400"><?= date('d/m/Y', strtotime($initialPhoto['taken_at'])) ?></p>
                    </div>
                    <?php if ($latestPhoto && $latestPhoto['id'] !== $initialPhoto['id']): ?>
                    <div class="flex-shrink-0 flex items-center text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                    </div>
                    <div class="flex-shrink-0 text-center">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl overflow-hidden bg-gray-200 shadow-sm border-2 border-primary">
                            <img src="<?= htmlspecialchars($latestPhoto['photo_path']) ?>" alt="Foto Terbaru" class="w-full h-full object-cover">
                        </div>
                        <p class="text-[9px] text-primary mt-1 font-semibold">Terbaru</p>
                        <p class="text-[8px] text-gray-400"><?= date('d/m/Y', strtotime($latestPhoto['taken_at'])) ?></p>
                    </div>
                    <?php endif; ?>
                    <div class="flex-shrink-0 flex items-center ml-2">
                        <a href="journal.php" class="text-[10px] text-primary font-semibold hover:underline whitespace-nowrap">Lihat Semua →</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </header>

    <!-- Chat Box -->
    <main class="flex-1 overflow-y-auto p-4 flex flex-col gap-3 max-w-4xl mx-auto w-full relative" id="chat-box">
        <div class="text-center text-sm text-gray-400 mt-4" id="loading-text">Memuat pesan...</div>
    </main>

    <!-- Input Area -->
    <footer class="bg-white border-t p-4">
        <form id="chat-form" class="flex gap-2 max-w-4xl mx-auto">
            <input type="text" id="message-input" class="flex-1 border border-gray-200 rounded-full px-5 py-3 focus:outline-none focus:ring-2 focus:ring-primary bg-gray-50 text-sm" placeholder="Ketik keluhan kulit Anda di sini...">
            <button type="submit" class="bg-primary text-white rounded-full px-6 py-3 font-medium hover:bg-primary-dark transition shadow-md flex items-center justify-center">
                <svg class="w-5 h-5 transform rotate-90" fill="currentColor" viewBox="0 0 20 20"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"></path></svg>
            </button>
        </form>
    </footer>

    <script>
        const chatBox = document.getElementById('chat-box');
        const chatForm = document.getElementById('chat-form');
        const messageInput = document.getElementById('message-input');
        const loadingText = document.getElementById('loading-text');
        
        let lastId = 0;
        let isFirstLoad = true;

        function togglePhotoPanel() {
            const panel = document.getElementById('photo-panel');
            if (panel) panel.classList.toggle('hidden');
        }

        function scrollToBottom() {
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        function renderMessage(msg) {
            const isMe = msg.sender === 'user';
            const div = document.createElement('div');
            div.className = `flex gap-3 ${isMe ? 'justify-end' : ''}`;
            
            let html = '';
            const time = new Date(msg.created_at).toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'});
            
            if (!isMe) {
                html += `<div class="w-8 h-8 rounded-full bg-blue-100 flex-shrink-0 flex items-center justify-center text-xs text-primary font-bold self-end mb-4">TA</div>`;
                html += `<div class="flex flex-col"><div class="bg-white border border-gray-100 rounded-2xl rounded-bl-none p-4 text-sm text-gray-700 shadow-sm max-w-sm">${msg.message}</div><span class="text-[10px] text-gray-400 mt-1 ml-1">${time}</span></div>`;
            } else {
                html += `<div class="flex flex-col items-end"><div class="bg-primary text-white rounded-2xl rounded-br-none p-4 text-sm shadow-md max-w-sm">${msg.message}</div><span class="text-[10px] text-gray-400 mt-1 mr-1">${time}</span></div>`;
            }
            
            div.innerHTML = html;
            chatBox.appendChild(div);
        }

        async function fetchMessages() {
            try {
                const res = await fetch(`api/chat.php?action=fetch&last_id=${lastId}`);
                const data = await res.json();
                
                if (data.messages && data.messages.length > 0) {
                    if (loadingText) loadingText.remove();
                    data.messages.forEach(msg => {
                        renderMessage(msg);
                        lastId = msg.id;
                    });
                    scrollToBottom();
                } else if (isFirstLoad && lastId === 0 && (!data.messages || data.messages.length === 0)) {
                     loadingText.innerHTML = '<div class="bg-blue-50 text-blue-600 p-4 rounded-xl text-center max-w-md mx-auto text-sm">Halo! Tim Ahli kami siap membantu menjawab keluhan kulit Anda. Silakan ketik pesan.</div>';
                }
                isFirstLoad = false;
            } catch (err) {
                console.error(err);
            }
        }

        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const message = messageInput.value.trim();
            if (!message) return;

            messageInput.value = '';
            
            const formData = new FormData();
            formData.append('message', message);

            try {
                const response = await fetch('api/chat.php?action=send', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    fetchMessages();
                }
            } catch (err) {
                console.error(err);
            }
        });

        // Live expert status polling
        async function updateExpertStatusInChat() {
            try {
                const res = await fetch('api/expert-status.php');
                const data = await res.json();
                
                const statusDot = document.getElementById('chat-status-dot');
                const statusLabel = document.getElementById('chat-status-label');
                const statusText = document.getElementById('chat-status-text');
                const banner = document.getElementById('offline-banner');
                const bannerText = document.getElementById('offline-banner-text');
                
                if (statusDot && statusLabel && statusText) {
                    statusDot.className = 'w-2 h-2 rounded-full ' + data.dot_class;
                    statusLabel.innerText = data.status_label;
                    statusText.className = 'text-xs flex items-center gap-1.5 ' + (data.expert_online ? 'text-emerald-600 font-semibold' : 'text-gray-400');
                }
                
                if (banner && bannerText) {
                    if (data.expert_online) {
                        banner.classList.add('hidden');
                    } else {
                        banner.classList.remove('hidden');
                        bannerText.innerText = data.offline_message || 'Tim ahli sedang offline.';
                    }
                }
            } catch (e) {
                console.warn('Status polling error:', e);
            }
        }

        // Initial fetch
        fetchMessages();
        
        // Polling messages every 3s
        setInterval(fetchMessages, 3000);
        
        // Polling expert status every 15s
        setInterval(updateExpertStatusInChat, 15000);
    </script>
<?php include 'includes/pwa-sw.php'; ?>
</body>
</html>
