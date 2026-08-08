<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth-helper.php';
require_once 'includes/icon-helper.php';

// Customer only guard
guard_customer_only();

$userId = $_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch product images
$productsResult = $conn->query("SELECT id, image_url FROM products");
$productImages = [];
if ($productsResult) {
    while ($row = $productsResult->fetch_assoc()) {
        if (!empty($row['image_url'])) {
            $productImages[$row['id']] = $row['image_url'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant - NPGLOW</title>
    <?php include 'includes/pwa-head.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3ca6f2',
                        'primary-dark': '#2e8ccf',
                        'ai-purple': '#7c3aed',
                        'ai-purple-dark': '#6d28d9',
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .glass-card { background: rgba(255,255,255,0.9); backdrop-filter: blur(16px); }
        @keyframes typing-dot {
            0%, 80%, 100% { opacity: 0.3; transform: scale(0.8); }
            40% { opacity: 1; transform: scale(1); }
        }
        .typing-dot { 
            width: 8px; height: 8px; border-radius: 50%; background: #a78bfa;
            display: inline-block; margin: 0 2px;
        }
        .typing-dot:nth-child(1) { animation: typing-dot 1.4s ease-in-out infinite; }
        .typing-dot:nth-child(2) { animation: typing-dot 1.4s ease-in-out 0.2s infinite; }
        .typing-dot:nth-child(3) { animation: typing-dot 1.4s ease-in-out 0.4s infinite; }
        .ai-msg { white-space: pre-wrap; }
        .ai-msg p { margin-bottom: 0.5rem; }
        .ai-msg ul, .ai-msg ol { margin-left: 1.25rem; margin-bottom: 0.5rem; }
        .ai-msg li { margin-bottom: 0.25rem; }
        .ai-msg strong { font-weight: 600; }
    </style>
</head>
<body class="bg-slate-50 h-screen flex flex-col">
    <!-- Header -->
    <header class="glass-card shadow-sm border-b border-gray-200">
        <div class="flex items-center gap-3 max-w-4xl mx-auto w-full p-4">
            <a href="konsultasi.php" class="text-gray-400 hover:text-ai-purple p-2 bg-gray-50 rounded-full transition-colors flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <div class="flex items-center gap-3 flex-1">
                <div class="w-10 h-10 bg-violet-100 rounded-full flex items-center justify-center text-ai-purple font-bold flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>
                <div>
                    <h1 class="font-bold text-base text-gray-800 leading-tight">NPGLOW AI Assistant</h1>
                    <p class="text-xs text-violet-500 flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-violet-500 block"></span>AI aktif 24 jam</p>
                </div>
            </div>
            <!-- Switch to Tim Ahli -->
            <a href="chat.php" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-primary rounded-full text-xs font-semibold transition-colors border border-blue-200">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Tim Ahli
            </a>
        </div>
    </header>

    <!-- Chat Box -->
    <main class="flex-1 overflow-y-auto p-4 flex flex-col gap-3 max-w-4xl mx-auto w-full relative" id="chat-box">
        <div class="text-center text-sm text-gray-400 mt-4" id="loading-text">Memuat percakapan...</div>
    </main>

    <!-- Disclaimer Banner -->
    <div class="bg-violet-50 border-t border-violet-100 px-4 py-2 text-center">
        <p class="text-[11px] text-violet-600 max-w-4xl mx-auto flex items-center justify-center gap-1.5 flex-wrap">
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-md bg-violet-100 text-violet-600">
                <?= npglow_icon('bot', 'w-3.5 h-3.5') ?>
            </span>
            <span><strong>AI Assistant</strong> memberikan saran umum berdasarkan pengetahuan produk NPGLOW. Untuk konsultasi mendalam, gunakan <a href="chat.php" class="font-semibold underline hover:text-violet-800">Tim Ahli</a>.</span>
        </p>
    </div>

    <!-- Input Area -->
    <footer class="bg-white border-t p-4">
        <form id="chat-form" class="flex gap-2 max-w-4xl mx-auto">
            <input type="text" id="message-input" class="flex-1 border border-gray-200 rounded-full px-5 py-3 focus:outline-none focus:ring-2 focus:ring-violet-400 bg-gray-50 text-sm" placeholder="Tanya seputar skincare atau produk NPGLOW...">
            <button type="submit" id="send-btn" class="bg-gradient-to-r from-violet-500 to-purple-600 text-white rounded-full px-6 py-3 font-medium hover:from-violet-600 hover:to-purple-700 transition shadow-md flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed">
                <svg class="w-5 h-5 transform rotate-90" fill="currentColor" viewBox="0 0 20 20"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"></path></svg>
            </button>
        </form>
    </footer>

    <script>
        const productImages = <?= json_encode($productImages) ?>;
        const chatBox = document.getElementById('chat-box');
        const chatForm = document.getElementById('chat-form');
        const messageInput = document.getElementById('message-input');
        const loadingText = document.getElementById('loading-text');
        const sendBtn = document.getElementById('send-btn');
        
        let lastId = 0;
        let isFirstLoad = true;
        let isSending = false;

        function scrollToBottom() {
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        function formatAIMessage(text) {
            // Parse [PRODUK: id | nama | harga] tags into HTML cards
            let html = text.replace(/\[PRODUK:\s*(\d+)\s*\|\s*(.*?)\s*\|\s*(\d+)\]/g, function(match, id, name, price) {
                const formattedPrice = parseInt(price).toLocaleString('id-ID');
                
                let imageHtml = '';
                if (productImages && productImages[id]) {
                    imageHtml = `<img src="${productImages[id]}" alt="${name}" class="w-full h-full object-cover">`;
                } else {
                    imageHtml = `<svg class="w-6 h-6 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>`;
                }
                
                return `<div class="mt-3 mb-2 bg-white border border-violet-100 rounded-xl p-3 shadow-sm w-full min-w-[240px] max-w-[280px] whitespace-normal"><div class="flex items-center gap-3 mb-3"><div class="w-14 h-14 bg-violet-50 border border-violet-100 rounded-xl flex items-center justify-center shrink-0 overflow-hidden">${imageHtml}</div><div class="flex-1 min-w-0"><p class="font-bold text-gray-800 text-sm leading-tight mb-1 truncate" title="${name}">${name}</p><p class="text-violet-600 font-bold text-sm">Rp ${formattedPrice}</p></div></div><a href="checkout.php?product_id=${id}" class="block w-full py-2 bg-gradient-to-r from-violet-500 to-purple-600 text-white text-center text-sm font-semibold rounded-lg hover:shadow-md hover:from-violet-600 hover:to-purple-700 transition-all">Beli Sekarang</a></div>`;
            });

            // Parse standard markdown
            html = html
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/\n/g, '<br>');
            
            return html;
        }

        function renderMessage(msg) {
            const isMe = msg.sender === 'user';
            const div = document.createElement('div');
            div.className = `flex gap-3 ${isMe ? 'justify-end' : ''}`;
            
            let html = '';
            const time = new Date(msg.created_at).toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'});
            
            if (!isMe) {
                html += `<div class="w-8 h-8 rounded-full bg-violet-100 flex-shrink-0 flex items-center justify-center text-xs text-violet-600 font-bold self-end mb-4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>`;
                html += `<div class="flex flex-col"><div class="bg-white border border-violet-100 rounded-2xl rounded-bl-none p-4 text-sm text-gray-700 shadow-sm max-w-sm ai-msg">${formatAIMessage(msg.message)}</div><span class="text-[10px] text-gray-400 mt-1 ml-1">${time}</span></div>`;
            } else {
                html += `<div class="flex flex-col items-end"><div class="bg-gradient-to-r from-violet-500 to-purple-600 text-white rounded-2xl rounded-br-none p-4 text-sm shadow-md max-w-sm">${msg.message}</div><span class="text-[10px] text-gray-400 mt-1 mr-1">${time}</span></div>`;
            }
            
            div.innerHTML = html;
            chatBox.appendChild(div);
        }

        function showTypingIndicator() {
            const existing = document.getElementById('typing-indicator');
            if (existing) return;
            
            const div = document.createElement('div');
            div.id = 'typing-indicator';
            div.className = 'flex gap-3';
            div.innerHTML = `
                <div class="w-8 h-8 rounded-full bg-violet-100 flex-shrink-0 flex items-center justify-center self-end mb-4">
                    <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>
                <div class="flex flex-col">
                    <div class="bg-white border border-violet-100 rounded-2xl rounded-bl-none px-5 py-4 shadow-sm">
                        <div class="flex gap-1 items-center">
                            <span class="typing-dot"></span>
                            <span class="typing-dot"></span>
                            <span class="typing-dot"></span>
                        </div>
                    </div>
                    <span class="text-[10px] text-violet-400 mt-1 ml-1">AI sedang mengetik...</span>
                </div>
            `;
            chatBox.appendChild(div);
            scrollToBottom();
        }

        function removeTypingIndicator() {
            const el = document.getElementById('typing-indicator');
            if (el) el.remove();
        }

        async function fetchMessages() {
            try {
                const res = await fetch(`api/ai-chat.php?action=fetch&last_id=${lastId}`);
                const data = await res.json();
                
                if (data.messages && data.messages.length > 0) {
                    if (loadingText) loadingText.remove();
                    removeTypingIndicator();
                    data.messages.forEach(msg => {
                        renderMessage(msg);
                        lastId = msg.id;
                    });
                    scrollToBottom();
                } else if (isFirstLoad && lastId === 0 && (!data.messages || data.messages.length === 0)) {
                    loadingText.innerHTML = `
                        <div class="bg-violet-50 text-violet-700 p-6 rounded-2xl text-center max-w-md mx-auto border border-violet-100">
                            <div class="w-14 h-14 rounded-2xl bg-violet-100 text-violet-600 flex items-center justify-center mx-auto mb-3 shadow-xs">
                                <?= npglow_icon('bot', 'w-7 h-7') ?>
                            </div>
                            <p class="font-bold text-base mb-1">Halo, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!</p>
                            <p class="text-sm text-violet-600/90">Saya AI Assistant NPGLOW. Ceritakan masalah kulit atau tanya tentang produk kami, saya siap bantu!</p>
                        </div>
                    `;
                }
                isFirstLoad = false;
            } catch (err) {
                console.error(err);
            }
        }

        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const message = messageInput.value.trim();
            if (!message || isSending) return;

            isSending = true;
            sendBtn.disabled = true;
            messageInput.value = '';
            
            // Show typing indicator
            showTypingIndicator();

            const formData = new FormData();
            formData.append('message', message);

            try {
                const response = await fetch('api/ai-chat.php?action=send', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    await fetchMessages();
                }
            } catch (err) {
                console.error(err);
                removeTypingIndicator();
            } finally {
                isSending = false;
                sendBtn.disabled = false;
                messageInput.focus();
            }
        });

        // Initial fetch
        fetchMessages();
        
        // Polling every 5 seconds (less aggressive than human chat)
        setInterval(fetchMessages, 5000);
    </script>
<?php include 'includes/pwa-sw.php'; ?>
</body>
</html>
