<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($productId <= 0) {
    header("Location: index.php");
    exit();
}

// Get product details
$stmt = $conn->prepare("SELECT name, price FROM products WHERE id = ?");
$stmt->bind_param("i", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Process mockup checkout
    $userId = $_SESSION['user_id'];
    
    // Insert order
    $stmt = $conn->prepare("INSERT INTO orders (user_id, product_id, status) VALUES (?, ?, 'completed')");
    $stmt->bind_param("ii", $userId, $productId);
    $stmt->execute();

    // Update has_purchased
    $stmt = $conn->prepare("UPDATE users SET has_purchased = 1 WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    header("Location: index.php?payment=success");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Checkout NPGLOW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-slate-50 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-96 border-t-4 border-[#3ca6f2]">
        <a href="index.php" class="text-sm text-gray-500 hover:text-[#3ca6f2] mb-4 inline-block">&larr; Batal</a>
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Checkout Simulasi</h2>
        <div class="bg-blue-50 p-4 rounded-xl mb-6">
            <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($product['name']) ?></h3>
            <p class="text-lg font-black text-gray-900 mt-2">Rp <?= number_format($product['price'], 0, ',', '.') ?></p>
        </div>
        <form method="POST" id="checkout-form">
            <button type="submit" class="w-full bg-[#3ca6f2] text-white p-3 rounded-xl font-bold hover:bg-[#2e8ccf] transition shadow-md">Bayar Sekarang (Mockup)</button>
        </form>
    </div>
    
    <script>
        document.getElementById('checkout-form').addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Memproses Pembayaran...',
                text: 'Simulasi pembayaran sedang berjalan.',
                icon: 'info',
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
                this.submit();
            });
        });
    </script>
</body>
</html>
