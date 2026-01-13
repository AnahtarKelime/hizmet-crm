<?php
session_start();
require_once '../config/db.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Lütfen tüm alanları doldurun.';
    } else {
        // Sadece admin rolüne sahip kullanıcıları sorgula
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_role'] = $user['role'];
            
            header("Location: index.php");
            exit;
        } else {
            $error = 'Hatalı giriş veya yetkisiz erişim.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetici Girişi | iyiteklif</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="h-full flex items-center justify-center font-['Inter']">
    <div class="w-full max-w-md space-y-8 p-10 bg-slate-800 rounded-2xl shadow-2xl border border-slate-700">
        <div class="text-center">
            <h2 class="text-3xl font-black text-white tracking-tight">Yönetici Paneli</h2>
            <p class="mt-2 text-sm text-slate-400">Devam etmek için giriş yapın</p>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-500/10 border border-red-500/50 text-red-500 text-sm p-4 rounded-lg text-center font-medium">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-6" action="" method="POST">
            <div class="space-y-4">
                <div>
                    <label for="email" class="sr-only">E-posta</label>
                    <input id="email" name="email" type="email" required class="relative block w-full rounded-lg border-0 bg-slate-700/50 text-white ring-1 ring-inset ring-slate-600 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-500 sm:text-sm sm:leading-6 py-3" placeholder="Yönetici E-postası">
                </div>
                <div>
                    <label for="password" class="sr-only">Şifre</label>
                    <input id="password" name="password" type="password" required class="relative block w-full rounded-lg border-0 bg-slate-700/50 text-white ring-1 ring-inset ring-slate-600 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-500 sm:text-sm sm:leading-6 py-3" placeholder="Şifre">
                </div>
            </div>

            <button type="submit" class="group relative flex w-full justify-center rounded-lg bg-indigo-600 px-3 py-3 text-sm font-semibold text-white hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-all">Giriş Yap</button>
        </form>
    </div>
</body>
</html>