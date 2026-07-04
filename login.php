<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password, is_admin FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['name'];
            $_SESSION['is_admin'] = $row['is_admin'];
            
            if ($row['is_admin']) {
                header("Location: admin.php");
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "Email tidak terdaftar!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login NPGLOW</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-96 border-t-4 border-[#3ca6f2]">
        <a href="index.php" class="text-sm text-gray-500 hover:text-[#3ca6f2] mb-4 inline-block">&larr; Kembali ke Beranda</a>
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Login</h2>
        <?php if(isset($_GET['success'])): ?>
            <div class="bg-green-100 text-green-600 p-3 rounded mb-4 text-sm text-center">Pendaftaran berhasil! Silakan login.</div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="bg-red-100 text-red-600 p-3 rounded mb-4 text-sm text-center"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" required class="mt-1 w-full p-2 border rounded-xl focus:ring-[#3ca6f2] outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" required class="mt-1 w-full p-2 border rounded-xl focus:ring-[#3ca6f2] outline-none">
            </div>
            <button type="submit" class="w-full bg-[#3ca6f2] text-white p-3 rounded-xl font-bold hover:bg-[#2e8ccf] transition">Masuk</button>
        </form>
        <p class="text-center text-sm text-gray-500 mt-4">Belum punya akun? <a href="register.php" class="text-[#3ca6f2] hover:underline">Daftar</a></p>
    </div>
</body>
</html>
