<?php
require_once '../config/db.php';

session_start();

// Giriş Kontrolü: Giriş yapmamışsa kayıt sayfasına yönlendir, dönüşte buraya gel
if (!isset($_SESSION['user_id'])) {
    header("Location: ../register.php?type=provider&redirect=provider/apply.php");
    exit;
}

// Rol Kontrolü
if ($_SESSION['user_role'] === 'admin') {
    header("Location: ../admin/index.php");
    exit;
}

$pageTitle = "Hizmet Veren Başvurusu";
$pathPrefix = '../';
require_once '../includes/header.php';

// Başvuru Durumunu Kontrol Et
$stmt = $pdo->prepare("SELECT application_status FROM provider_details WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$status = $stmt->fetchColumn();

if ($status === 'pending') {
    echo "<div class='max-w-4xl mx-auto px-4 py-20 text-center'>
            <div class='inline-flex items-center justify-center w-20 h-20 bg-yellow-100 text-yellow-600 rounded-full mb-6'>
                <span class='material-symbols-outlined text-4xl'>hourglass_top</span>
            </div>
            <h1 class='text-3xl font-bold text-slate-800 mb-4'>Başvurunuz İnceleniyor</h1>
            <p class='text-slate-500 text-lg'>Başvurunuz alınmıştır ve ekibimiz tarafından incelenmektedir. Sonuçlandığında size bilgi verilecektir.</p>
            <a href='../index.php' class='inline-block mt-8 px-6 py-3 bg-primary text-white rounded-xl font-bold'>Anasayfaya Dön</a>
          </div>";
    require_once '../includes/footer.php';
    exit;
} elseif ($status === 'approved') {
    echo "<div class='max-w-4xl mx-auto px-4 py-20 text-center'>
            <div class='inline-flex items-center justify-center w-20 h-20 bg-green-100 text-green-600 rounded-full mb-6'>
                <span class='material-symbols-outlined text-4xl'>check_circle</span>
            </div>
            <h1 class='text-3xl font-bold text-slate-800 mb-4'>Başvurunuz Onaylandı</h1>
            <p class='text-slate-500 text-lg'>Tebrikler! Hizmet veren hesabınız onaylanmıştır.</p>
            <a href='buy-package.php' class='inline-block mt-8 px-6 py-3 bg-primary text-white rounded-xl font-bold'>Paket Satın Al</a>
          </div>";
    require_once '../includes/footer.php';
    exit;
}

// Verileri Çek
$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$cities = $pdo->query("SELECT DISTINCT city FROM locations ORDER BY city ASC")->fetchAll(PDO::FETCH_COLUMN);
$districts = $pdo->query("SELECT DISTINCT city, district FROM locations ORDER BY district ASC")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
?>

<main class="max-w-[1200px] mx-auto w-full px-4 md:px-10 py-8">
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Main Form Area -->
        <div class="flex-1">
            <!-- Page Heading -->
            <div class="mb-8">
                <h1 class="text-slate-900 dark:text-white text-4xl font-black leading-tight tracking-tight">Hizmet Veren Başvurusu</h1>
                <p class="text-slate-500 dark:text-gray-400 text-lg mt-2">Profesyoneller aramıza katılıyor. Bilgilerinizi eksiksiz doldurarak başvurunuzu tamamlayın.</p>
            </div>

            <?php if ($status === 'incomplete'): ?>
                <div class="mb-8 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 flex items-start gap-3">
                    <span class="material-symbols-outlined">error</span>
                    <div>
                        <p class="font-bold">Başvurunuzda eksikler var!</p>
                        <p class="text-sm">Lütfen bilgilerinizi kontrol edip eksik evrakları yükleyerek tekrar gönderin.</p>
                    </div>
                </div>
            <?php endif; ?>

            <form action="process-application.php" method="POST">
                <!-- Section 1: Expertise -->
                <div class="mb-10">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="bg-primary text-white w-8 h-8 rounded-full flex items-center justify-center font-bold">1</span>
                        <h2 class="text-primary dark:text-white text-2xl font-bold leading-tight">Uzmanlık Alanlarınızı Seçin</h2>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                        <?php foreach ($categories as $cat): ?>
                        <label class="flex flex-col gap-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 cursor-pointer transition-all hover:shadow-md hover:border-primary relative group">
                            <input type="radio" name="category_id" value="<?= $cat['id'] ?>" required class="absolute top-3 right-3 w-5 h-5 text-primary focus:ring-primary border-slate-300">
                            <span class="material-symbols-outlined text-slate-500 dark:text-gray-400 text-3xl group-hover:text-primary"><?= $cat['icon'] ?: 'work' ?></span>
                            <div class="flex flex-col gap-1">
                                <h3 class="text-slate-900 dark:text-white text-base font-bold"><?= htmlspecialchars($cat['name']) ?></h3>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-xs text-slate-500 mt-4">* Sadece bir ana uzmanlık alanı seçebilirsiniz.</p>
                </div>

                <!-- Section 2: Region Selection -->
                <div class="mb-10">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="bg-primary text-white w-8 h-8 rounded-full flex items-center justify-center font-bold">2</span>
                        <h2 class="text-primary dark:text-white text-2xl font-bold leading-tight">Hizmet Bölgesi Seçimi</h2>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-white dark:bg-slate-800 p-6 rounded-xl border border-slate-200 dark:border-slate-700">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-slate-900 dark:text-white">İl Seçiniz</label>
                            <select name="city" id="citySelect" onchange="updateDistricts()" required class="w-full rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-900 focus:ring-primary focus:border-primary">
                                <option value="">Seçiniz...</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?= htmlspecialchars($city) ?>"><?= htmlspecialchars($city) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-slate-900 dark:text-white">Hizmet Vereceğiniz İlçeler</label>
                            <div id="districtsContainer" class="w-full rounded-lg border border-slate-200 dark:border-slate-700 dark:bg-slate-900 p-3 max-h-60 overflow-y-auto grid grid-cols-1 gap-2 custom-scrollbar">
                                <div class="text-sm text-slate-500 italic p-2">Önce il seçiniz.</div>
                            </div>
                            <p class="text-xs text-slate-500">Hiçbir ilçe seçmezseniz, tüm şehirde hizmet verdiğiniz varsayılır.</p>
                        </div>
                    </div>
                </div>

                <!-- Final Action -->
                <div class="flex flex-col sm:flex-row gap-4 items-center justify-between mt-12 bg-white dark:bg-slate-800 p-6 rounded-xl border-t-4 border-accent shadow-lg">
                    <div class="flex items-center gap-3">
                        <input class="rounded border-gray-300 text-primary focus:ring-primary h-5 w-5" id="kvkk" type="checkbox" required>
                        <label class="text-sm text-slate-600 dark:text-gray-400" for="kvkk">
                            <a class="underline font-medium" href="#">Kullanım Koşulları</a> ve <a class="underline font-medium" href="#">KVKK Metni</a>'ni okudum, onaylıyorum.
                        </label>
                    </div>
                    <button type="submit" class="w-full sm:w-auto flex min-w-[200px] cursor-pointer items-center justify-center rounded-lg h-14 px-8 bg-accent text-primary text-lg font-black uppercase tracking-wider hover:brightness-105 transition-all shadow-md">
                        Başvuruyu Tamamla
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    const districtsData = <?= json_encode($districts) ?>;

    function updateDistricts() {
        const citySelect = document.getElementById('citySelect');
        const container = document.getElementById('districtsContainer');
        const selectedCity = citySelect.value;

        // İlçeleri temizle
        container.innerHTML = '';

        if (selectedCity && districtsData[selectedCity]) {
            districtsData[selectedCity].forEach(district => {
                const label = document.createElement('label');
                label.className = 'flex items-center gap-2 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800 p-1.5 rounded transition-colors';
                
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = 'districts[]';
                checkbox.value = district;
                checkbox.className = 'rounded border-slate-300 text-primary focus:ring-primary w-4 h-4';
                
                const span = document.createElement('span');
                span.className = 'text-sm text-slate-700 dark:text-slate-300 select-none';
                span.textContent = district;
                
                label.appendChild(checkbox);
                label.appendChild(span);
                container.appendChild(label);
            });
        } else {
            container.innerHTML = '<div class="text-sm text-slate-500 italic p-2">Önce il seçiniz.</div>';
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>