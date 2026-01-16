<?php
session_start();
require_once 'config/db.php';

// Google ayarlarını çek
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'google_%' OR setting_key LIKE 'facebook_%'");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // settings tablosu yoksa hata vermemesi için
}
$googleLoginActive = ($settings['google_login_active'] ?? '0') == '1';
$googleClientId = $settings['google_client_id'] ?? '';

$facebookLoginActive = ($settings['facebook_login_active'] ?? '0') == '1';
$facebookAppId = $settings['facebook_app_id'] ?? '';


if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$redirect = $_GET['redirect'] ?? null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirectUrl = $_POST['redirect'] ?? null;

    if (empty($email) || empty($password)) {
        $error = 'Lütfen e-posta ve şifrenizi girin.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_role'] = $user['role'];
            
            if ($redirectUrl) {
                header("Location: " . $redirectUrl);
                exit;
            } else {
                header("Location: index.php");
                exit;
            }
        } else {
            $error = 'E-posta adresi veya şifre hatalı.';
        }
    }
}

$pageTitle = "Giriş Yap";
require_once 'includes/header.php';
?>
<main class="flex items-center justify-center min-h-[calc(100vh-4rem)] bg-slate-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-md space-y-8 bg-white p-8 rounded-2xl shadow-xl border border-slate-100">
        <div>
            <h2 class="mt-2 text-center text-3xl font-black tracking-tight text-slate-900">
                Giriş Yap
            </h2>
            <p class="mt-2 text-center text-sm text-slate-600">
                Hesabınız yok mu?
                <a href="register.php" class="font-medium text-primary hover:text-primary/80 transition-colors">
                    Hemen kayıt olun
                </a>
            </p>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-xl text-sm font-medium border border-red-100">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-6" action="login.php" method="POST">
            <?php if($redirect): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
            <?php endif; ?>
            <div class="space-y-4 rounded-md shadow-sm">
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">E-posta Adresi</label>
                    <input id="email" name="email" type="email" autocomplete="email" required class="relative block w-full rounded-xl border-slate-200 px-4 py-3 text-slate-900 placeholder-slate-400 focus:z-10 focus:border-primary focus:ring-primary sm:text-sm" placeholder="ornek@mail.com">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Şifre</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required class="relative block w-full rounded-xl border-slate-200 px-4 py-3 text-slate-900 placeholder-slate-400 focus:z-10 focus:border-primary focus:ring-primary sm:text-sm" placeholder="••••••••">
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                    <label for="remember-me" class="ml-2 block text-sm text-slate-900">Beni hatırla</label>
                </div>

                <div class="text-sm">
                    <a href="#" class="font-medium text-primary hover:text-primary/80">Şifremi unuttum</a>
                </div>
            </div>

            <div>
                <button type="submit" class="group relative flex w-full justify-center rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-all shadow-lg shadow-primary/20">
                    Giriş Yap
                </button>
            </div>
        </form>

        <?php if ($googleLoginActive || $facebookLoginActive): ?>
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-slate-200"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="bg-white px-2 text-slate-500">veya sosyal medya ile</span>
                </div>
            </div>

            <div class="space-y-3">
                <?php if ($googleLoginActive): ?>
                <a href="google-login.php<?= $redirect ? '?redirect=' . urlencode($redirect) : '' ?>" class="group relative flex w-full justify-center rounded-xl bg-[#DB4437] px-4 py-3 text-sm font-bold text-white hover:bg-[#c53929] focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-all shadow-sm">
                    <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24"><path fill="currentColor" d="M21.35,11.1H12.18V13.83H18.69C18.36,17.64 15.19,19.27 12.19,19.27C8.36,19.27 5,16.25 5,12C5,7.9 8.2,5 12,5C14.6,5 16.1,6.05 17.1,6.95L19.25,4.85C17.1,2.95 14.8,2 12,2C6.48,2 2,6.48 2,12C2,17.52 6.48,22 12,22C17.52,22 21.7,17.52 21.7,12.33C21.7,11.87 21.5,11.35 21.35,11.1Z"></path></svg>
                    Google ile Giriş Yap
                </a>
                <?php endif; ?>

                <?php if ($facebookLoginActive): ?>
                <a href="facebook-login.php<?= $redirect ? '?redirect=' . urlencode($redirect) : '' ?>" class="group relative flex w-full justify-center rounded-xl bg-[#1877F2] px-4 py-3 text-sm font-bold text-white hover:bg-[#166fe5] focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 transition-all">
                    <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24"><path fill="currentColor" d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg>
                    Facebook ile Giriş Yap
                </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php require_once 'includes/footer.php'; ?>