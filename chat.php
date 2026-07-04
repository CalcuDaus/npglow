<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check has_purchased
$stmt = $conn->prepare("SELECT has_purchased FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user['has_purchased']) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konsultasi NPGLOW</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-white shadow-sm p-4 flex items-center justify-between border-b">
        <div class="flex items-center gap-3 max-w-4xl mx-auto w-full">
            <a href="index.php" class="text-gray-500 hover:text-[#3ca6f2] p-2 bg-gray-50 rounded-full">&larr;</a>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-[#3ca6f2] font-bold">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <h1 class="font-bold text-lg text-gray-800 leading-tight">Tim Ahli NPGLOW</h1>
                    <p class="text-xs text-green-500 flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500 block"></span> Online</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Chat Box -->
    <main class="flex-1 overflow-y-auto p-4 flex flex-col gap-3 max-w-4xl mx-auto w-full relative" id="chat-box">
        <div class="text-center text-sm text-gray-400 mt-4" id="loading-text">Memuat pesan...</div>
    </main>

    <!-- Input Area -->
    <footer class="bg-white border-t p-4">
        <form id="chat-form" class="flex gap-2 max-w-4xl mx-auto">
            <input type="text" id="message-input" class="flex-1 border border-gray-200 rounded-full px-5 py-3 focus:outline-none focus:ring-2 focus:ring-[#3ca6f2] bg-gray-50" placeholder="Ketik keluhan kulit Anda di sini...">
            <button type="submit" class="bg-[#3ca6f2] text-white rounded-full px-6 py-3 font-medium hover:bg-[#2e8ccf] transition shadow-md flex items-center justify-center">
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
                html += `<div class="w-8 h-8 rounded-full bg-blue-100 flex-shrink-0 flex items-center justify-center text-xs text-[#3ca6f2] font-bold self-end mb-4">A</div>`;
                html += `<div class="flex flex-col"><div class="bg-white border border-gray-100 rounded-2xl rounded-bl-none p-4 text-sm text-gray-700 shadow-sm max-w-sm">${msg.message}</div><span class="text-[10px] text-gray-400 mt-1 ml-1">${time}</span></div>`;
            } else {
                html += `<div class="flex flex-col items-end"><div class="bg-[#3ca6f2] text-white rounded-2xl rounded-br-none p-4 text-sm shadow-md max-w-sm">${msg.message}</div><span class="text-[10px] text-gray-400 mt-1 mr-1">${time}</span></div>`;
            }
            
            div.innerHTML = html;
            chatBox.appendChild(div);
        }

        async function fetchMessages() {
            try {
                const res = await fetch(`api_chat.php?action=fetch&last_id=${lastId}`);
                const data = await res.json();
                
                if (data.messages && data.messages.length > 0) {
                    if (loadingText) loadingText.remove();
                    data.messages.forEach(msg => {
                        renderMessage(msg);
                        lastId = msg.id;
                    });
                    scrollToBottom();
                } else if (isFirstLoad && lastId === 0 && (!data.messages || data.messages.length === 0)) {
                     loadingText.innerHTML = '<div class="bg-blue-50 text-blue-600 p-4 rounded-xl text-center max-w-md mx-auto">Halo! Kami siap membantu. Silakan sampaikan keluhan kulit Anda di sini.</div>';
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
                const res = await fetch('api_chat.php?action=send', {
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
</body>
</html>
