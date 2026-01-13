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

            <form action="process-application.php" method="POST" enctype="multipart/form-data">
                <!-- Section 1: Expertise -->
                <div class="mb-10">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="bg-primary text-white w-8 h-8 rounded-full flex items-center justify-center font-bold">1</span>
                        <h2 class="text-primary dark:text-white text-2xl font-bold leading-tight">Uzmanlık Alanlarınızı Seçin</h2>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                        <?php foreach ($categories as $cat): ?>
                        <label class="flex flex-col gap-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 cursor-pointer transition-all hover:shadow-md hover:border-primary relative group">
                            <input type="checkbox" name="categories[]" value="<?= $cat['id'] ?>" class="absolute top-3 right-3 w-5 h-5 text-primary rounded focus:ring-primary border-slate-300">
                            <span class="material-symbols-outlined text-slate-500 dark:text-gray-400 text-3xl group-hover:text-primary"><?= $cat['icon'] ?: 'work' ?></span>
                            <div class="flex flex-col gap-1">
                                <h3 class="text-slate-900 dark:text-white text-base font-bold"><?= htmlspecialchars($cat['name']) ?></h3>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Section 2: Documents -->
                <div class="mb-10">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="bg-primary text-white w-8 h-8 rounded-full flex items-center justify-center font-bold">2</span>
                        <h2 class="text-primary dark:text-white text-2xl font-bold leading-tight">Belge Yükleme</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Upload Box 1 -->
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-bold text-slate-900 dark:text-white">Vergi Levhası</label>
                            <input type="file" name="doc_tax_plate" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm text-slate-500 file:mr-4 file:py-3 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-slate-200 rounded-xl">
                        </div>
                        <!-- Upload Box 2 -->
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-bold text-slate-900 dark:text-white">Kimlik / Ustalık Belgesi</label>
                            <input type="file" name="doc_identity" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm text-slate-500 file:mr-4 file:py-3 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-slate-200 rounded-xl">
                        </div>
                    </div>
                </div>

                <!-- Section 3: Region Selection -->
                <div class="mb-10">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="bg-primary text-white w-8 h-8 rounded-full flex items-center justify-center font-bold">3</span>
                        <h2 class="text-primary dark:text-white text-2xl font-bold leading-tight">Hizmet Bölgesi Seçimi</h2>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-white dark:bg-slate-800 p-6 rounded-xl border border-slate-200 dark:border-slate-700">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-slate-900 dark:text-white">İl Seçiniz</label>
                            <select name="city" class="w-full rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-900 focus:ring-primary focus:border-primary">
                                <option value="">Seçiniz...</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?= htmlspecialchars($city) ?>"><?= htmlspecialchars($city) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-slate-900 dark:text-white">İlçe(ler) (Opsiyonel)</label>
                            <input type="text" name="districts" class="w-full rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-900 focus:ring-primary focus:border-primary" placeholder="Tüm şehir için boş bırakın">
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

        <!-- Sidebar Information -->
        <aside class="lg:w-80 flex flex-col gap-6">
            <div class="sticky top-24 bg-primary text-white p-8 rounded-2xl shadow-xl">
                <h3 class="text-xl font-bold mb-6 border-b border-white/20 pb-4">Neden Bize Katılmalısın?</h3>
                <div class="flex flex-col gap-6">
                    <div class="flex gap-4">
                        <span class="material-symbols-outlined text-accent">group</span>
                        <div>
                            <p class="font-bold">Binlerce Yeni Müşteri</p>
                            <p class="text-sm text-blue-100 mt-1">Her gün binlerce kişi hizmet arıyor, biz sizi onlarla buluşturuyoruz.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <span class="material-symbols-outlined text-accent">payments</span>
                        <div>
                            <p class="font-bold">Güvenli Ödeme</p>
                            <p class="text-sm text-blue-100 mt-1">Ödemeleriniz platform güvencesiyle hesabınıza aktarılır.</p>
                        </div>
                    </div>
                </div>
                <div class="mt-8 pt-6 border-t border-white/20 text-center">
                    <p class="text-xs text-blue-200">Yardıma mı ihtiyacınız var?</p>
                    <p class="font-bold mt-1">0850 123 45 67</p>
                </div>
            </div>
        </aside>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>