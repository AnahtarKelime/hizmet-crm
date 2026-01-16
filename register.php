<?php
session_start();
require_once 'config/db.php';

// Google ve Facebook ayarlarını çek
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
$facebookLoginActive = ($settings['facebook_login_active'] ?? '0') == '1';

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
    $city = trim($_POST['city'] ?? '');
    $district = trim($_POST['district'] ?? '');
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
        // E-posta ve Telefon kontrolü
        $stmt = $pdo->prepare("SELECT email, phone FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$email, $phone]);
        $existingUser = $stmt->fetch();

        if ($existingUser) {
            if ($existingUser['email'] === $email) {
                $error = 'Bu e-posta adresi zaten kayıtlı.';
            } else {
                $error = 'Bu telefon numarası zaten kayıtlı.';
            }
        } else {
            try {
                $pdo->beginTransaction();

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                // is_verified 1 olarak ayarlandı (demo için)
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, city, district, password, role, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$firstName, $lastName, $email, $phone, $city, $district, $hashedPassword, $roleInput]);
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

// Şehir ve İlçe verilerini çek (Form için)
$cities = $pdo->query("SELECT DISTINCT city FROM locations ORDER BY city ASC")->fetchAll(PDO::FETCH_COLUMN);
$districts = $pdo->query("SELECT city, district FROM locations ORDER BY district ASC")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

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
                <input name="phone" id="phoneInput" type="tel" class="block w-full rounded-xl border-slate-200 px-4 py-3 text-slate-900 focus:border-primary focus:ring-primary sm:text-sm" placeholder="(05XX) XXX XX XX" maxlength="17">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">İl</label>
                    <select name="city" id="citySelect" onchange="updateDistricts()" class="block w-full rounded-xl border-slate-200 px-4 py-3 text-slate-900 focus:border-primary focus:ring-primary sm:text-sm">
                        <option value="">Seçiniz</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= htmlspecialchars($city) ?>"><?= htmlspecialchars($city) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">İlçe</label>
                    <select name="district" id="districtSelect" class="block w-full rounded-xl border-slate-200 px-4 py-3 text-slate-900 focus:border-primary focus:ring-primary sm:text-sm">
                        <option value="">Önce İl Seçiniz</option>
                    </select>
                </div>
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

        <?php if ($googleLoginActive || $facebookLoginActive): ?>
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-slate-200"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="bg-white px-2 text-slate-500">veya sosyal medya ile kayıt olun</span>
                </div>
            </div>

            <div class="space-y-3">
                <?php if ($googleLoginActive): ?>
                <a href="google-login.php" class="group relative flex w-full justify-center rounded-xl bg-[#DB4437] px-4 py-3 text-sm font-bold text-white hover:bg-[#c53929] focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-all shadow-sm">
                    <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24"><path fill="currentColor" d="M21.35,11.1H12.18V13.83H18.69C18.36,17.64 15.19,19.27 12.19,19.27C8.36,19.27 5,16.25 5,12C5,7.9 8.2,5 12,5C14.6,5 16.1,6.05 17.1,6.95L19.25,4.85C17.1,2.95 14.8,2 12,2C6.48,2 2,6.48 2,12C2,17.52 6.48,22 12,22C17.52,22 21.7,17.52 21.7,12.33C21.7,11.87 21.5,11.35 21.35,11.1Z"></path></svg>
                    Google ile Kayıt Ol
                </a>
                <?php endif; ?>

                <?php if ($facebookLoginActive): ?>
                <a href="facebook-login.php" class="group relative flex w-full justify-center rounded-xl bg-[#1877F2] px-4 py-3 text-sm font-bold text-white hover:bg-[#166fe5] focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 transition-all">
                    <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24"><path fill="currentColor" d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg>
                    Facebook ile Kayıt Ol
                </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
    const districtsData = <?= json_encode($districts) ?>;

    function updateDistricts() {
        const citySelect = document.getElementById('citySelect');
        const districtSelect = document.getElementById('districtSelect');
        const selectedCity = citySelect.value;

        districtSelect.innerHTML = '<option value="">Seçiniz</option>';

        if (selectedCity && districtsData[selectedCity]) {
            districtsData[selectedCity].forEach(district => {
                const option = document.createElement('option');
                option.value = district;
                option.textContent = district;
                districtSelect.appendChild(option);
            });
        }
    }

    // Telefon Numarası Maskeleme (05XX XXX XX XX)
    const phoneInput = document.getElementById('phoneInput');
    if (phoneInput) {
        phoneInput.addEventListener('input', function (e) {
            let x = e.target.value.replace(/\D/g, '').match(/(\d{0,4})(\d{0,3})(\d{0,2})(\d{0,2})/);
            e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? ' ' + x[3] : '') + (x[4] ? ' ' + x[4] : '');
        });
    }
</script>
<?php require_once 'includes/footer.php'; ?>