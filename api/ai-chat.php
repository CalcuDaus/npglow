<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/ai-config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$userId = $_SESSION['user_id'];

// ========================
// FETCH AI CHAT HISTORY
// ========================
if ($action === 'fetch') {
    $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    
    $stmt = $conn->prepare("SELECT * FROM ai_chats WHERE user_id = ? AND id > ? ORDER BY id ASC");
    $stmt->bind_param("ii", $userId, $lastId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    echo json_encode(['messages' => $messages]);
    exit();
}

// ========================
// SEND MESSAGE TO AI
// ========================
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    if (empty($message)) {
        echo json_encode(['error' => 'Empty message']);
        exit();
    }
    
    // Check API key
    if (empty(GEMINI_API_KEY)) {
        // Save user message
        $stmt = $conn->prepare("INSERT INTO ai_chats (user_id, sender, message) VALUES (?, 'user', ?)");
        $stmt->bind_param("is", $userId, $message);
        $stmt->execute();
        
        // Return a fallback message
        $fallbackMsg = "Maaf, AI Assistant sedang dalam proses konfigurasi. Silakan hubungi Tim Ahli untuk konsultasi langsung. 🙏";
        $stmt = $conn->prepare("INSERT INTO ai_chats (user_id, sender, message) VALUES (?, 'ai', ?)");
        $stmt->bind_param("is", $userId, $fallbackMsg);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'needs_config' => true]);
        exit();
    }
    
    // Save user message to DB
    $stmt = $conn->prepare("INSERT INTO ai_chats (user_id, sender, message) VALUES (?, 'user', ?)");
    $stmt->bind_param("is", $userId, $message);
    $stmt->execute();
    
    // Build RAG context from knowledge base
    $knowledgeBase = '';
    
    // FETCH ALL PRODUCTS FROM DATABASE
    $knowledgeBase .= "\n\nINFORMASI SELURUH PRODUK NPGLOW YANG TERSEDIA:\n";
    $productQuery = $conn->query("SELECT id, name, price, description FROM products");
    if ($productQuery) {
        while ($p = $productQuery->fetch_assoc()) {
            $desc = trim(preg_replace('/\s+/', ' ', strip_tags($p['description'])));
            $knowledgeBase .= "\n- {$p['name']} (ID Produk: {$p['id']} | Rp " . number_format($p['price'], 0, ',', '.') . ")\n";
            $knowledgeBase .= "  Deskripsi: {$desc}\n";
        }
    }

    $kbPath = __DIR__ . '/../data/knowledge-base.json';
    if (file_exists($kbPath)) {
        $kb = json_decode(file_get_contents($kbPath), true);
        if ($kb) {
            if (isset($kb['products'])) {
                $knowledgeBase .= "\n\nPANDUAN TAMBAHAN (Manfaat & Cara Pakai untuk Produk Tertentu):\n";
                foreach ($kb['products'] as $product) {
                    $knowledgeBase .= "\n* {$product['name']}:\n";
                    if (isset($product['skin_type'])) $knowledgeBase .= "  Cocok untuk kulit: " . implode(', ', $product['skin_type']) . "\n";
                    if (isset($product['benefits'])) $knowledgeBase .= "  Manfaat: " . implode(', ', $product['benefits']) . "\n";
                    if (isset($product['key_ingredients'])) $knowledgeBase .= "  Bahan utama: " . implode(', ', $product['key_ingredients']) . "\n";
                    if (isset($product['usage'])) $knowledgeBase .= "  Cara pakai: {$product['usage']}\n";
                }
            }
            
            if (isset($kb['faq'])) {
                $knowledgeBase .= "\n\nFAQ:\n";
                foreach ($kb['faq'] as $faq) {
                    $knowledgeBase .= "Q: {$faq['question']}\nA: {$faq['answer']}\n\n";
                }
            }
            
            if (isset($kb['skincare_tips'])) {
                $knowledgeBase .= "\nTIPS SKINCARE:\n";
                foreach ($kb['skincare_tips'] as $tip) {
                    $knowledgeBase .= "- {$tip}\n";
                }
            }
        }
    }
    
    // Get conversation history for context
    $contextLimit = AI_CONTEXT_MESSAGES;
    $stmt = $conn->prepare("SELECT sender, message FROM ai_chats WHERE user_id = ? ORDER BY id DESC LIMIT ?");
    $stmt->bind_param("ii", $userId, $contextLimit);
    $stmt->execute();
    $historyResult = $stmt->get_result();
    
    $history = [];
    while ($row = $historyResult->fetch_assoc()) {
        $history[] = $row;
    }
    $history = array_reverse($history); // Oldest first
    
    // Build Gemini API request
    $contents = [];
    
    // Add conversation history
    foreach ($history as $msg) {
        $role = $msg['sender'] === 'user' ? 'user' : 'model';
        $contents[] = [
            'role' => $role,
            'parts' => [['text' => $msg['message']]]
        ];
    }
    
    $requestBody = [
        'system_instruction' => [
            'parts' => [['text' => AI_SYSTEM_PROMPT . $knowledgeBase]]
        ],
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => AI_TEMPERATURE,
            'maxOutputTokens' => AI_MAX_TOKENS,
            'topP' => 0.95
        ]
    ];
    
    // Call Gemini API
    $ch = curl_init(GEMINI_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestBody),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        $aiMessage = "Maaf, terjadi kesalahan koneksi. Silakan coba lagi nanti atau hubungi Tim Ahli. 🙏";
    } elseif ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? 'Unknown error';
        error_log("Gemini API Error [{$httpCode}]: {$errorMsg}");
        $aiMessage = "Maaf, AI sedang mengalami gangguan. Silakan coba lagi nanti atau chat langsung dengan Tim Ahli kami. 🙏";
    } else {
        $data = json_decode($response, true);
        $aiMessage = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Maaf, saya tidak bisa memproses pesan ini. Silakan coba lagi.';
    }
    
    // Save AI response to DB
    $stmt = $conn->prepare("INSERT INTO ai_chats (user_id, sender, message) VALUES (?, 'ai', ?)");
    $stmt->bind_param("is", $userId, $aiMessage);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit();
}

echo json_encode(['error' => 'Invalid action']);
?>
