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
if (!empty($user['phone']) && !empty($user['city']) && !empty($user['district'])) {
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
    $city = trim($_POST['city'] ?? '');
    $district = trim($_POST['district'] ?? '');

    if (empty($phone) || empty($city) || empty($district)) {
        $error = 'Lütfen tüm alanları doldurun.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET phone = ?, city = ?, district = ? WHERE id = ?");
            $stmt->execute([$phone, $city, $district, $userId]);
            
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

// Şehir ve İlçe verilerini çek
$cities = $pdo->query("SELECT DISTINCT city FROM locations ORDER BY city ASC")->fetchAll(PDO::FETCH_COLUMN);
$districts = $pdo->query("SELECT city, district FROM locations ORDER BY district ASC")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

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
                    <input name="phone" id="phoneInput" type="tel" required class="block w-full rounded-xl border-slate-200 px-4 py-3 text-slate-900 focus:border-primary focus:ring-primary sm:text-sm" placeholder="(05XX) XXX XX XX" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">İl</label>
                    <select name="city" id="citySelect" onchange="updateDistricts()" required class="block w-full rounded-xl border-slate-200 px-4 py-3 text-slate-900 focus:border-primary focus:ring-primary sm:text-sm">
                        <option value="">Seçiniz</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= htmlspecialchars($city) ?>" <?= ($user['city'] ?? '') == $city ? 'selected' : '' ?>><?= htmlspecialchars($city) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">İlçe</label>
                    <select name="district" id="districtSelect" required class="block w-full rounded-xl border-slate-200 px-4 py-3 text-slate-900 focus:border-primary focus:ring-primary sm:text-sm">
                        <option value="">Önce İl Seçiniz</option>
                    </select>
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
    const districtsData = <?= json_encode($districts) ?>;
    const currentDistrict = "<?= htmlspecialchars($user['district'] ?? '') ?>";

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
                if (district === currentDistrict) {
                    option.selected = true;
                }
                districtSelect.appendChild(option);
            });
        }
    }
    
    // Sayfa yüklendiğinde eğer şehir seçiliyse ilçeleri doldur
    document.addEventListener('DOMContentLoaded', function() {
        if(document.getElementById('citySelect').value) {
            updateDistricts();
        }
    });

    // Telefon Numarası Maskeleme
    const phoneInput = document.getElementById('phoneInput');
    if (phoneInput) {
        phoneInput.addEventListener('input', function (e) {
            var x = e.target.value.replace(/\D/g, '').match(/(\d{0,4})(\d{0,3})(\d{0,2})(\d{0,2})/);
            e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? ' ' + x[3] : '') + (x[4] ? ' ' + x[4] : '');
        });
    }
</script>
<?php require_once 'includes/footer.php'; ?>