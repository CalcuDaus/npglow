<?php
session_start();
require_once 'config.php';

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
    <title>Daftar NPGLOW</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-96 border-t-4 border-[#3ca6f2]">
        <a href="index.php" class="text-sm text-gray-500 hover:text-[#3ca6f2] mb-4 inline-block">&larr; Kembali ke Beranda</a>
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Daftar Akun</h2>
        <?php if(isset($error)): ?>
            <div class="bg-red-100 text-red-600 p-3 rounded mb-4 text-sm text-center"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                <input type="text" name="name" required class="mt-1 w-full p-2 border rounded-xl focus:ring-[#3ca6f2] outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" required class="mt-1 w-full p-2 border rounded-xl focus:ring-[#3ca6f2] outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" required class="mt-1 w-full p-2 border rounded-xl focus:ring-[#3ca6f2] outline-none">
            </div>
            <button type="submit" class="w-full bg-[#3ca6f2] text-white p-3 rounded-xl font-bold hover:bg-[#2e8ccf] transition">Daftar</button>
        </form>
        <p class="text-center text-sm text-gray-500 mt-4">Sudah punya akun? <a href="login.php" class="text-[#3ca6f2] hover:underline">Masuk</a></p>
    </div>
</body>
</html>
