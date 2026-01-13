<?php
session_start();
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$type = $_GET['type'] ?? 'customer';
$role = ($type === 'provider') ? 'provider' : 'customer';
$pageTitle = ($role === 'provider') ? 'Hizmet Veren Kaydı' : 'Kayıt Ol';
$packageId = $_GET['package_id'] ?? null; // Paketten geliyorsa
$redirect = $_GET['redirect'] ?? null; // Yönlendirme varsa

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $roleInput = $_POST['role'] ?? 'customer';
    $selectedPackageId = $_POST['package_id'] ?? null;
    $redirectUrl = $_POST['redirect'] ?? null;

    // Basit validasyon
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = 'Lütfen zorunlu alanları doldurun.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Şifreler eşleşmiyor.';
    } elseif (strlen($password) < 6) {
        $error = 'Şifre en az 6 karakter olmalıdır.';
    } else {
        // E-posta kontrolü
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Bu e-posta adresi zaten kayıtlı.';
        } else {
            try {
                $pdo->beginTransaction();

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                // is_verified 1 olarak ayarlandı (demo için)
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role, is_verified) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$firstName, $lastName, $email, $phone, $hashedPassword, $roleInput]);
                $userId = $pdo->lastInsertId();

                if ($roleInput === 'provider') {
                    $stmt = $pdo->prepare("INSERT INTO provider_details (user_id) VALUES (?)");
                    $stmt->execute([$userId]);
                }

                $pdo->commit();

                // Otomatik giriş
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                $_SESSION['user_role'] = $roleInput;

                // Eğer paket seçimiyle geldiyse ödeme sayfasına yönlendir
                if ($roleInput === 'provider' && $selectedPackageId) {
                    header("Location: provider/process-payment.php?package_id=" . $selectedPackageId);
                    exit;
                } elseif ($redirectUrl) {
                    header("Location: " . $redirectUrl);
                    exit;
                } else {
                    header("Location: index.php");
                    exit;
                }

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Kayıt sırasında bir hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

require_once 'includes/header.php';
?>
<main class="flex items-center justify-center min-h-[calc(100vh-4rem)] bg-slate-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-lg space-y-8 bg-white p-8 rounded-2xl shadow-xl border border-slate-100">
        <div>
            <h2 class="mt-2 text-center text-3xl font-black tracking-tight text-slate-900">
                <?= ($role === 'provider') ? 'Hizmet Veren Ol' : 'Hesap Oluştur' ?>
            </h2>
            <p class="mt-2 text-center text-sm text-slate-600">
                Zaten hesabınız var mı?
                <a href="login.php" class="font-medium text-primary hover:text-primary/80 transition-colors">
                    Giriş yapın
                </a>
            </p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-xl text-sm font-medium border border-red-100">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-6" action="register.php?type=<?= $role ?>" method="POST">
            <input type="hidden" name="role" value="<?= $role ?>">
            <?php if($packageId): ?>
                <input type="hidden" name="package_id" value="<?= htmlspecialchars($packageId) ?>">
            <?php endif; ?>
            <?php if($redirect): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ad</label>
                    <input name="first_name" type="text" required class="block w-full rounded-xl border-slate-200 px-4 py-3 text-slate-900 focus:border-primary focus:ring-primary sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Soyad</label>
                    <input name="last_name" type="text" required class="block w-full rounded-xl border-slate-200 px-4 py-3 text-slate-900 focus:border-primary focus:ring-primary sm:text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">E-posta Adresi</label>
                <input name="email" type="email" required class="block w-full rounded-xl border-slate-200 px-4 py-3 text-slate-900 focus:border-primary focus:ring-primary sm:text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Telefon Numarası</label>
                <input name="phone" type="tel" class="block w-full rounded-xl border-slate-200 px-4 py-3 text-slate-900 focus:border-primary focus:ring-primary sm:text-sm" placeholder="0555 555 55 55">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Şifre</label>
                    <input name="password" type="password" required class="block w-full rounded-xl border-slate-200 px-4 py-3 text-slate-900 focus:border-primary focus:ring-primary sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Şifre Tekrar</label>
                    <input name="password_confirm" type="password" required class="block w-full rounded-xl border-slate-200 px-4 py-3 text-slate-900 focus:border-primary focus:ring-primary sm:text-sm">
                </div>
            </div>

            <div>
                <button type="submit" class="group relative flex w-full justify-center rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-all shadow-lg shadow-primary/20">
                    <?= ($role === 'provider') ? 'Hizmet Veren Olarak Kaydol' : 'Kayıt Ol' ?>
                </button>
            </div>
        </form>
    </div>
</main>
<?php require_once 'includes/footer.php'; ?>