<?php
session_start();
require_once 'config/db.php';

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
    </div>
</main>
<?php require_once 'includes/footer.php'; ?>