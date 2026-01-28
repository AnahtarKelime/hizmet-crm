<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Kullanıcı bilgilerini çek
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Eğer bilgiler zaten tamsa anasayfaya yönlendir
if (!empty($user['phone'])) {
    if (isset($_SESSION['social_redirect'])) {
        $redirect = $_SESSION['social_redirect'];
        unset($_SESSION['social_redirect']);
        header('Location: ' . $redirect);
    } else {
        header("Location: index.php");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

    if (empty($phone)) {
        $error = 'Lütfen tüm alanları doldurun.';
    } elseif (strlen($cleanPhone) !== 11 || substr($cleanPhone, 0, 2) !== '05') {
        $error = 'Lütfen geçerli bir cep telefonu numarası girin (Örn: 05XX XXX XX XX).';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?");
            $stmt->execute([$phone, $userId]);
            
            if (isset($_SESSION['social_redirect'])) {
                $redirect = $_SESSION['social_redirect'];
                unset($_SESSION['social_redirect']);
                header('Location: ' . $redirect);
            } else {
                header("Location: index.php");
            }
            exit;
        } catch (Exception $e) {
            $error = 'Güncelleme sırasında bir hata oluştu.';
        }
    }
}

$pageTitle = "Profil Tamamlama";
require_once 'includes/header.php';
?>
<main class="flex items-center justify-center min-h-[calc(100vh-4rem)] bg-slate-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-md space-y-8 bg-white p-8 rounded-2xl shadow-xl border border-slate-100">
        <div>
            <h2 class="mt-2 text-center text-3xl font-black tracking-tight text-slate-900">
                Bilgilerinizi Tamamlayın
            </h2>
            <p class="mt-2 text-center text-sm text-slate-600">
                Hizmet almaya başlamak için lütfen eksik bilgilerinizi girin.
            </p>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-xl text-sm font-medium border border-red-100">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-6" method="POST">
            <div class="space-y-4 rounded-md shadow-sm">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Telefon Numarası</label>
                    <input name="phone" id="phoneInput" type="tel" required class="block w-full rounded-xl border-slate-200 px-4 py-3 text-slate-900 focus:border-primary focus:ring-primary sm:text-sm" placeholder="(05XX) XXX XX XX" maxlength="17" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                </div>
            </div>

            <div>
                <button type="submit" class="group relative flex w-full justify-center rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-all shadow-lg shadow-primary/20">
                    Kaydet ve Devam Et
                </button>
            </div>
        </form>
    </div>
</main>

<script>
    // Telefon Numarası Maskeleme
    const phoneInput = document.getElementById('phoneInput');
    if (phoneInput) {
        phoneInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            
            // 0 ile başlamıyorsa ekle
            if (value.length > 0 && value[0] !== '0') {
                value = '0' + value;
            }
            if (value.length > 11) value = value.substring(0, 11);

            let formatted = '';
            if (value.length > 0) formatted += '(' + value.substring(0, 4);
            if (value.length > 4) formatted += ') ' + value.substring(4, 7);
            if (value.length > 7) formatted += ' ' + value.substring(7, 9);
            if (value.length > 9) formatted += ' ' + value.substring(9, 11);

            e.target.value = formatted;
        });
    }
</script>
<?php require_once 'includes/footer.php'; ?>