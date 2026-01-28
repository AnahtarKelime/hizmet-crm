<?php
require_once 'config/db.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if (!$token) {
    header("Location: index.php");
    exit;
}

// Token Kontrolü
$stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    $error = "Bu bağlantı geçersiz veya süresi dolmuş.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $password = $_POST['password'];
    $passwordConfirm = $_POST['password_confirm'];

    if (strlen($password) < 6) {
        $error = "Şifre en az 6 karakter olmalıdır.";
    } elseif ($password !== $passwordConfirm) {
        $error = "Şifreler eşleşmiyor.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Şifreyi güncelle ve token'ı temizle
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires_at = NULL, is_verified = 1 WHERE id = ?");
        $stmt->execute([$hashedPassword, $user['id']]);

        $success = "Şifreniz başarıyla oluşturuldu. Giriş yapabilirsiniz.";
        
        // Otomatik giriş yaptır (Opsiyonel)
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_role'] = 'customer';
        
        header("Refresh: 2; url=index.php");
    }
}

$pageTitle = "Şifre Oluştur";
require_once 'includes/header.php';
?>

<main class="flex items-center justify-center min-h-[calc(100vh-4rem)] bg-slate-50 py-12 px-4">
    <div class="w-full max-w-md bg-white p-8 rounded-2xl shadow-xl border border-slate-100">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-black text-slate-900">Şifre Oluştur</h1>
            <p class="text-slate-500 text-sm mt-2">Hesabınızın güvenliği için yeni bir şifre belirleyin.</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-xl text-sm font-medium border border-red-100 mb-6 text-center">
                <?= $error ?>
                <?php if(strpos($error, 'geçersiz') !== false): ?>
                    <br><a href="index.php" class="underline mt-2 inline-block">Anasayfaya Dön</a>
                <?php endif; ?>
            </div>
        <?php elseif ($success): ?>
            <div class="bg-green-50 text-green-600 p-4 rounded-xl text-sm font-medium border border-green-100 mb-6 text-center">
                <?= $success ?>
                <br>Yönlendiriliyorsunuz...
            </div>
        <?php else: ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Yeni Şifre</label>
                    <input type="password" name="password" required class="w-full rounded-xl border-slate-200 px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary" placeholder="******">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Şifre Tekrar</label>
                    <input type="password" name="password_confirm" required class="w-full rounded-xl border-slate-200 px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary" placeholder="******">
                </div>
                <button type="submit" class="w-full bg-primary text-white font-bold py-3 rounded-xl hover:bg-primary/90 transition-all shadow-lg">Şifreyi Kaydet</button>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>