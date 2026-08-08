<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/settings-helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'expert') {
    header("Location: ../login.php");
    exit();
}

$expertId = (int)$_SESSION['user_id'];

// Initial heartbeat: set expert online when entering dashboard
$stmtOnline = $conn->prepare("UPDATE users SET is_online = 1, last_active = NOW() WHERE id = ?");
$stmtOnline->bind_param("i", $expertId);
$stmtOnline->execute();

// Fetch operational schedule
$opStatus = get_expert_operational_status($conn);

// Get list of users who have sent a message (include purchase status)
$stmt = $conn->prepare("
    SELECT u.id, u.name, u.email, u.has_purchased, MAX(c.created_at) as last_msg_time 
    FROM users u
    JOIN chats c ON u.id = c.user_id
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY last_msg_time DESC
");
$stmt->execute();
$usersResult = $stmt->get_result();

$targetUserId = isset($_GET['target_user_id']) ? (int)$_GET['target_user_id'] : 0;
$targetUserName = "Pilih User";
$targetUserHasPurchased = false;
if ($targetUserId > 0) {
    $stmtUser = $conn->prepare("SELECT name, has_purchased FROM users WHERE id = ?");
    $stmtUser->bind_param("i", $targetUserId);
    $stmtUser->execute();
    $targetUserNameRow = $stmtUser->get_result()->fetch_assoc();
    if ($targetUserNameRow) {
        $targetUserName = $targetUserNameRow['name'];
        $targetUserHasPurchased = (bool)$targetUserNameRow['has_purchased'];
        
        // Fetch target user photo count
        $photoCountStmt = $conn->prepare("SELECT COUNT(*) as total FROM user_face_photos WHERE user_id = ?");
        $photoCountStmt->bind_param("i", $targetUserId);
        $photoCountStmt->execute();
        $targetUserPhotoCount = $photoCountStmt->get_result()->fetch_assoc()['total'] ?? 0;
    } else {
        $targetUserId = 0;
        $targetUserPhotoCount = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tim Ahli Dashboard - NPGLOW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3ca6f2',
                        'primary-dark': '#2e8ccf',
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.6; }
            100% { transform: scale(0.95); opacity: 1; }
        }
        .pulse-ring { animation: pulse-ring 2s ease-in-out infinite; }
    </style>
</head>
<body class="bg-gray-100 h-screen flex flex-col">
    <header class="bg-[#3ca6f2] text-white p-3 sm:p-4 flex flex-wrap justify-between items-center shadow-md z-10 gap-3">
        <div class="flex items-center gap-4 sm:gap-8">
            <h1 class="font-bold text-lg sm:text-xl flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                NPGLOW Tim Ahli
            </h1>
            <nav class="hidden md:flex gap-2">
                <a href="index.php" class="bg-white/20 px-4 py-2 rounded-lg text-sm font-bold transition flex items-center gap-1.5 shadow-inner">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    Konsultasi Chat
                </a>
                <a href="photos.php" class="hover:bg-white/20 px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Bank Data Foto
                </a>
            </nav>
        </div>

        <div class="flex items-center gap-2 sm:gap-4 ml-auto">
            <!-- Operational Schedule Mini Badge -->
            <div class="hidden lg:flex items-center gap-2 bg-white/10 px-3 py-1.5 rounded-full text-xs font-medium border border-white/20">
                <svg class="w-3.5 h-3.5 text-blue-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Jam Kerja: <?= htmlspecialchars($opStatus['start_time']) ?> - <?= htmlspecialchars($opStatus['end_time']) ?> WIB</span>
            </div>

            <!-- Online / Offline Toggle Button -->
            <button id="expert-toggle-btn" onclick="toggleMyStatus()" class="inline-flex items-center gap-2 bg-white text-emerald-700 px-3.5 py-1.5 rounded-full text-xs font-bold shadow-sm transition-all hover:bg-emerald-50 border border-emerald-200">
                <span id="expert-toggle-dot" class="w-2.5 h-2.5 rounded-full bg-emerald-500 pulse-ring"></span>
                <span id="expert-toggle-text">Online (Siap)</span>
            </button>

            <span class="text-xs sm:text-sm font-medium bg-white/20 px-3 py-1.5 rounded-full hidden sm:inline-block">Halo, <?= htmlspecialchars($_SESSION['user_name']) ?></span>
            <a href="../logout.php" class="bg-red-500/20 hover:bg-red-500/30 text-white px-3.5 py-1.5 rounded-full font-medium text-xs sm:text-sm transition-colors border border-red-300/30">Logout</a>
        </div>
    </header>

    <?php if (isset($_GET['notice']) && $_GET['notice'] === 'buyer_only'): ?>
        <div class="bg-amber-500 text-white px-4 py-2.5 text-xs sm:text-sm font-semibold flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2 mx-auto">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Akun Tim Ahli tidak diperuntukkan untuk membeli produk (hanya untuk akun Pelanggan/Customer).</span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-white/80 hover:text-white">&times;</button>
        </div>
    <?php endif; ?>

    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar -->
        <div class="w-full sm:w-1/3 max-w-sm bg-white border-r flex flex-col z-0 shadow-[4px_0_10px_rgba(0,0,0,0.02)]">
            <div class="p-4 bg-gray-50 font-bold border-b text-gray-700 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
                    Daftar Konsultasi
                </div>
                <span class="text-xs font-normal text-gray-400"><?= $usersResult->num_rows ?> Chat</span>
            </div>
            <div class="flex-1 overflow-y-auto">
                <?php if ($usersResult->num_rows == 0): ?>
                    <div class="p-8 text-gray-400 text-sm text-center flex flex-col items-center gap-2">
                        <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                        Belum ada chat dari pengguna.
                    </div>
                <?php else: ?>
                    <?php while($u = $usersResult->fetch_assoc()): ?>
                        <a href="index.php?target_user_id=<?= $u['id'] ?>" class="block p-4 border-b hover:bg-blue-50 transition <?= $u['id'] == $targetUserId ? 'bg-blue-50 border-l-4 border-[#3ca6f2]' : '' ?>">
                            <div class="flex justify-between items-start mb-1">
                                <div class="font-bold text-gray-800 truncate pr-2"><?= htmlspecialchars($u['name']) ?></div>
                                <div class="text-[10px] text-gray-400 whitespace-nowrap mt-1"><?= date('H:i, d M', strtotime($u['last_msg_time'])) ?></div>
                            </div>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs text-gray-500 truncate"><?= htmlspecialchars($u['email']) ?></span>
                            </div>
                            <?php if ($u['has_purchased']): ?>
                                <span class="inline-flex items-center gap-1 mt-1.5 text-[10px] bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full font-semibold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Pelanggan
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 mt-1.5 text-[10px] bg-blue-50 text-primary border border-blue-200 px-2 py-0.5 rounded-full font-semibold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-primary"></span> Calon Pelanggan
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="hidden sm:flex flex-1 flex-col bg-[#f8fafc] relative">
            <?php if ($targetUserId > 0): ?>
                <div class="p-4 bg-white shadow-sm border-b font-bold text-gray-800 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-[#3ca6f2] font-bold">
                            <?= strtoupper(substr($targetUserName, 0, 1)) ?>
                        </div>
                        <div>
                            <div class="leading-tight"><?= htmlspecialchars($targetUserName) ?></div>
                            <div class="text-xs text-gray-400 font-normal flex items-center gap-2">
                                <?php if ($targetUserHasPurchased): ?>
                                    <span class="inline-flex items-center gap-1 text-emerald-600"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>Pelanggan Terverifikasi</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-blue-600"><span class="w-1.5 h-1.5 rounded-full bg-blue-500 inline-block"></span>Calon Pelanggan — Berikan Rekomendasi Produk</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Link to Bank Data Foto Customer -->
                    <div class="flex items-center gap-2">
                        <a href="photos.php?user_id=<?= $targetUserId ?>" class="inline-flex items-center gap-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 px-3.5 py-1.5 rounded-xl text-xs font-bold transition shadow-sm">
                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <span>Bank Data Foto <?= $targetUserPhotoCount > 0 ? "({$targetUserPhotoCount} Foto)" : '' ?></span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </a>
                    </div>
                </div>
                
                <main class="flex-1 overflow-y-auto p-6 flex flex-col gap-4" id="chat-box">
                    <div class="text-center text-sm text-gray-400 mt-4" id="loading-text">Memuat pesan...</div>
                </main>

                <footer class="bg-white border-t p-4">
                    <form id="chat-form" class="flex gap-2 max-w-5xl mx-auto w-full">
                        <input type="text" id="message-input" class="flex-1 border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#3ca6f2] focus:border-transparent bg-gray-50 text-sm" placeholder="Ketik balasan untuk <?= htmlspecialchars($targetUserName) ?>...">
                        <button type="submit" class="bg-[#3ca6f2] text-white rounded-xl px-6 sm:px-8 py-3 font-medium hover:bg-[#2e8ccf] transition shadow flex items-center gap-2 text-sm">
                            Kirim <svg class="w-4 h-4 transform rotate-90" fill="currentColor" viewBox="0 0 20 20"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"></path></svg>
                        </button>
                    </form>
                </footer>
                
                <script>
                    const chatBox = document.getElementById('chat-box');
                    const chatForm = document.getElementById('chat-form');
                    const messageInput = document.getElementById('message-input');
                    const loadingText = document.getElementById('loading-text');
                    const targetUserId = <?= $targetUserId ?>;
                    
                    let lastId = 0;

                    function scrollToBottom() {
                        chatBox.scrollTop = chatBox.scrollHeight;
                    }

                    function renderMessage(msg) {
                        const isMe = msg.sender === 'expert' || msg.sender === 'admin';
                        const div = document.createElement('div');
                        div.className = `flex gap-3 ${isMe ? 'justify-end' : ''}`;
                        
                        let html = '';
                        const time = new Date(msg.created_at).toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'});
                        
                        if (!isMe) {
                            html += `<div class="w-8 h-8 rounded-full bg-gray-200 flex-shrink-0 flex items-center justify-center text-xs text-gray-600 font-bold self-end mb-4">${'<?= strtoupper(substr($targetUserName, 0, 1)) ?>'}</div>`;
                            html += `<div class="flex flex-col"><div class="bg-white border border-gray-200 rounded-2xl rounded-bl-none p-4 text-sm text-gray-800 shadow-sm max-w-md">${msg.message}</div><span class="text-[10px] text-gray-400 mt-1 ml-1">${time}</span></div>`;
                        } else {
                            html += `<div class="flex flex-col items-end"><div class="bg-[#3ca6f2] text-white rounded-2xl rounded-br-none p-4 text-sm shadow-md max-w-md">${msg.message}</div><span class="text-[10px] text-gray-400 mt-1 mr-1">${time}</span></div>`;
                        }
                        
                        div.innerHTML = html;
                        chatBox.appendChild(div);
                    }

                    async function fetchMessages() {
                        try {
                            const res = await fetch(`../api/chat.php?action=fetch&last_id=${lastId}&target_user_id=${targetUserId}`);
                            const data = await res.json();
                            
                            if (data.messages && data.messages.length > 0) {
                                if (loadingText) loadingText.remove();
                                data.messages.forEach(msg => {
                                    renderMessage(msg);
                                    lastId = msg.id;
                                });
                                scrollToBottom();
                            } else if (lastId === 0 && (!data.messages || data.messages.length === 0)) {
                                 loadingText.innerHTML = '<div class="bg-white p-4 rounded-xl border border-dashed border-gray-300 text-gray-400">Belum ada pesan dari user ini.</div>';
                            }
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
                            const res = await fetch('../api/chat.php?action=send&target_user_id='+targetUserId, {
                                method: 'POST',
                                body: formData
                            });
                            const data = await res.json();
                            if (data.success) {
                                fetchMessages();
                            }
                        } catch (err) {
                            console.error(err);
                        }
                    });

                    // Initial fetch
                    fetchMessages();
                    
                    // Polling every 3 seconds
                    setInterval(fetchMessages, 3000);
                </script>
            <?php else: ?>
                <div class="flex-1 flex flex-col items-center justify-center text-gray-400 bg-white m-4 rounded-2xl border border-dashed border-gray-300 p-8 text-center">
                    <svg class="w-16 h-16 text-gray-200 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    <p class="font-bold text-gray-700 text-lg">Pilih User Konsultasi</p>
                    <p class="text-sm max-w-sm mt-1">Pilih pengguna dari daftar sebelah kiri untuk membalas keluhan kulit dan memberikan rekomendasi produk.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Real-time Heartbeat & Presence Engine -->
    <script>
        let isOnlineStatus = true;
        let heartbeatInterval = null;
        let awayTimeout = null;

        const toggleBtn = document.getElementById('expert-toggle-btn');
        const toggleDot = document.getElementById('expert-toggle-dot');
        const toggleText = document.getElementById('expert-toggle-text');

        function updateToggleUI(online) {
            isOnlineStatus = online;
            if (online) {
                toggleBtn.className = 'inline-flex items-center gap-2 bg-white text-emerald-700 px-3.5 py-1.5 rounded-full text-xs font-bold shadow-sm transition-all hover:bg-emerald-50 border border-emerald-200';
                toggleDot.className = 'w-2.5 h-2.5 rounded-full bg-emerald-500 pulse-ring';
                toggleText.innerText = 'Online (Siap)';
            } else {
                toggleBtn.className = 'inline-flex items-center gap-2 bg-white text-gray-500 px-3.5 py-1.5 rounded-full text-xs font-bold shadow-sm transition-all hover:bg-gray-100 border border-gray-200';
                toggleDot.className = 'w-2.5 h-2.5 rounded-full bg-gray-400';
                toggleText.innerText = 'Istirahat (Offline)';
            }
        }

        // Toggle button handler
        async function toggleMyStatus() {
            const nextStatus = !isOnlineStatus;
            updateToggleUI(nextStatus);

            const formData = new FormData();
            formData.append('action', 'toggle');
            formData.append('is_online', nextStatus ? '1' : '0');

            try {
                await fetch('../api/expert-heartbeat.php', {
                    method: 'POST',
                    body: formData
                });
            } catch (e) {
                console.warn('Status toggle error:', e);
            }
        }

        // Heartbeat Ping
        async function sendHeartbeat() {
            if (!isOnlineStatus) return; // Do not ping if manually paused/resting
            try {
                await fetch('../api/expert-heartbeat.php?action=ping');
            } catch (e) {
                console.warn('Heartbeat error:', e);
            }
        }

        // Run heartbeat every 25 seconds
        heartbeatInterval = setInterval(sendHeartbeat, 25000);

        // Page Visibility API: Detect if tab is hidden or minimized
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                // If tab is hidden for more than 3 minutes, set to away
                awayTimeout = setTimeout(() => {
                    if (isOnlineStatus) {
                        fetch('../api/expert-heartbeat.php?action=away');
                    }
                }, 180000);
            } else {
                // When coming back to active tab
                clearTimeout(awayTimeout);
                if (isOnlineStatus) {
                    sendHeartbeat();
                }
            }
        });

        // Set status offline on tab close or navigation
        window.addEventListener('beforeunload', () => {
            if (navigator.sendBeacon) {
                navigator.sendBeacon('../api/expert-heartbeat.php?action=offline');
            }
        });
    </script>
</body>
</html>
