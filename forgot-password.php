<?php
require_once 'config/db.php';
require_once 'includes/mail-helper.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi giriniz.';
    } else {
        // Kullanıcı kontrolü
        $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Token oluştur
            $token = bin2hex(random_bytes(32));
            
            // Token'ı kaydet (24 saat geçerli)
            $updateStmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?");
            $updateStmt->execute([$token, $user['id']]);

            // Mail gönder
            $link = getBaseUrl() . '/set-password.php?token=' . $token;
            $mailResult = sendEmail($email, 'password_reset', [
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'link' => $link
            ]);

            if ($mailResult) {
                $success = true;
            } else {
                $error = 'E-posta gönderilirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
            }
        } else {
            $error = 'Bu e-posta adresi ile kayıtlı kullanıcı bulunamadı.';
        }
    }
}

$pageTitle = "Şifremi Unuttum";
require_once 'includes/header.php';
?>

<main class="flex items-center justify-center min-h-[calc(100vh-4rem)] bg-slate-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-lg bg-white p-8 rounded-2xl shadow-xl border border-slate-100">
        
        <?php if ($success): ?>
            <!-- Başarılı Gönderim Ekranı -->
            <div class="text-center">
                <div class="mx-auto w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-3xl">mark_email_read</span>
                </div>
                <h1 class="text-slate-900 tracking-tight text-3xl font-bold leading-tight mb-2">E-posta Gönderildi</h1>
                <p class="text-slate-600 text-base font-normal leading-normal px-4 mb-6">
                    Şifre sıfırlama talimatlarını içeren bir e-postayı <strong><?= htmlspecialchars($email) ?></strong> adresine gönderdik. Lütfen gelen kutunuzu (ve spam klasörünü) kontrol edin.
                </p>
                <a href="login.php" class="inline-block bg-primary text-white font-bold py-3 px-8 rounded-xl hover:bg-primary/90 transition-all shadow-lg">Giriş Yap</a>
            </div>
        <?php else: ?>
            <!-- Form Ekranı -->
            <div class="flex flex-col gap-6">
            <div class="text-center">
                <div class="mx-auto w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-primary text-3xl">lock_reset</span>
                </div>
                <h1 class="text-slate-900 tracking-tight text-3xl font-bold leading-tight mb-2">Şifrenizi mi Unuttunuz?</h1>
                <p class="text-slate-600 text-base font-normal leading-normal px-4">E-posta adresinizi girin, size şifre sıfırlama talimatlarını gönderelim.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-xl text-sm font-medium border border-red-100 text-center">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form class="flex flex-col gap-4 mt-2" method="POST">
                <label class="flex flex-col w-full">
                    <p class="text-slate-900 text-sm font-medium leading-normal pb-2">E-posta Adresi</p>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl">mail</span>
                        <input type="email" name="email" required class="flex w-full rounded-xl border-slate-200 bg-white h-14 pl-12 pr-4 placeholder:text-slate-400 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all" placeholder="ornek@eposta.com"/>
                    </div>
                </label>
                <button type="submit" class="flex w-full cursor-pointer items-center justify-center overflow-hidden rounded-xl h-12 px-5 bg-primary text-white text-base font-bold leading-normal tracking-[0.015em] hover:bg-primary/90 transition-all mt-2 shadow-lg shadow-primary/20">
                    <span>Talimat Gönder</span>
                </button>
            </form>
            </div>
        <?php endif; ?>

        <!-- Footer Back Link -->
        <div class="mt-8 pt-6 border-t border-slate-100 flex justify-center">
            <a class="flex items-center gap-2 text-primary font-semibold text-sm hover:gap-3 transition-all" href="login.php">
                <span class="material-symbols-outlined text-base">arrow_back</span>
                Giriş sayfasına geri dön
            </a>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>