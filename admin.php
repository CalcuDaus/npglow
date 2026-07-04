<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: login.php");
    exit();
}

// Get list of users who have sent a message
$stmt = $conn->prepare("
    SELECT u.id, u.name, u.email, MAX(c.created_at) as last_msg_time 
    FROM users u
    JOIN chats c ON u.id = c.user_id
    GROUP BY u.id
    ORDER BY last_msg_time DESC
");
$stmt->execute();
$usersResult = $stmt->get_result();

$targetUserId = isset($_GET['target_user_id']) ? (int)$_GET['target_user_id'] : 0;
$targetUserName = "Pilih User";
if ($targetUserId > 0) {
    $stmtUser = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmtUser->bind_param("i", $targetUserId);
    $stmtUser->execute();
    $targetUserNameRow = $stmtUser->get_result()->fetch_assoc();
    if ($targetUserNameRow) {
        $targetUserName = $targetUserNameRow['name'];
    } else {
        $targetUserId = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NPGLOW</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 h-screen flex flex-col">
    <header class="bg-[#3ca6f2] text-white p-4 flex justify-between items-center shadow-md z-10">
        <div class="flex items-center gap-8">
            <h1 class="font-bold text-xl flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                NPGLOW Admin
            </h1>
            <nav class="hidden md:flex gap-2">
                <a href="admin.php" class="bg-white/20 px-4 py-2 rounded-lg text-sm font-medium transition">Konsultasi</a>
                <a href="admin_products.php" class="hover:bg-white/20 px-4 py-2 rounded-lg text-sm font-medium transition">Katalog Produk</a>
            </nav>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-sm font-medium bg-white/20 px-3 py-1 rounded-full hidden sm:inline-block">Halo, Admin</span>
            <a href="logout.php" class="bg-white text-[#3ca6f2] hover:bg-gray-50 px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition">Logout</a>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar -->
        <div class="w-1/3 max-w-sm bg-white border-r flex flex-col z-0 shadow-[4px_0_10px_rgba(0,0,0,0.02)]">
            <div class="p-4 bg-gray-50 font-bold border-b text-gray-700 flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
                Daftar Konsultasi
            </div>
            <div class="flex-1 overflow-y-auto">
                <?php if ($usersResult->num_rows == 0): ?>
                    <div class="p-8 text-gray-400 text-sm text-center flex flex-col items-center gap-2">
                        <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                        Belum ada chat.
                    </div>
                <?php else: ?>
                    <?php while($u = $usersResult->fetch_assoc()): ?>
                        <a href="admin.php?target_user_id=<?= $u['id'] ?>" class="block p-4 border-b hover:bg-blue-50 transition <?= $u['id'] == $targetUserId ? 'bg-blue-50 border-l-4 border-[#3ca6f2]' : '' ?>">
                            <div class="flex justify-between items-start mb-1">
                                <div class="font-bold text-gray-800 truncate pr-2"><?= htmlspecialchars($u['name']) ?></div>
                                <div class="text-[10px] text-gray-400 whitespace-nowrap mt-1"><?= date('H:i, d M', strtotime($u['last_msg_time'])) ?></div>
                            </div>
                            <div class="text-xs text-gray-500 truncate"><?= htmlspecialchars($u['email']) ?></div>
                        </a>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="flex-1 flex flex-col bg-[#f8fafc] relative">
            <?php if ($targetUserId > 0): ?>
                <div class="p-4 bg-white shadow-sm border-b font-bold text-gray-800 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-[#3ca6f2] font-bold">
                        <?= strtoupper(substr($targetUserName, 0, 1)) ?>
                    </div>
                    <div>
                        <div class="leading-tight"><?= htmlspecialchars($targetUserName) ?></div>
                        <div class="text-xs text-gray-400 font-normal">Customer</div>
                    </div>
                </div>
                
                <main class="flex-1 overflow-y-auto p-6 flex flex-col gap-4" id="chat-box">
                    <div class="text-center text-sm text-gray-400 mt-4" id="loading-text">Memuat pesan...</div>
                </main>

                <footer class="bg-white border-t p-4">
                    <form id="chat-form" class="flex gap-2 max-w-5xl mx-auto w-full">
                        <input type="text" id="message-input" class="flex-1 border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#3ca6f2] focus:border-transparent bg-gray-50" placeholder="Ketik balasan untuk user ini...">
                        <button type="submit" class="bg-[#3ca6f2] text-white rounded-xl px-8 py-3 font-medium hover:bg-[#2e8ccf] transition shadow flex items-center gap-2">
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
                        const isMe = msg.sender === 'admin';
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
                            const res = await fetch(`api_chat.php?action=fetch&last_id=${lastId}&target_user_id=${targetUserId}`);
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
                            const res = await fetch(`api_chat.php?action=send&target_user_id=${targetUserId}`, {
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
                <div class="flex-1 flex flex-col items-center justify-center text-gray-400 bg-white m-4 rounded-2xl border border-dashed border-gray-300">
                    <svg class="w-16 h-16 text-gray-200 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    <p class="font-medium text-gray-500">Pilih User</p>
                    <p class="text-sm">Pilih user dari daftar sebelah kiri untuk membalas pesan.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
