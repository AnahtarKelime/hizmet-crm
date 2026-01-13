<?php
require_once '../config/db.php';

$pageTitle = "Paket Seçimi";
$pathPrefix = '../'; // Üst dizine çıkmak için
// Header'ı dahil et (Session burada başlar)
require_once '../includes/header.php';


// Paketleri veritabanından çek
$packages = [];
try {
    $stmt = $pdo->query("SELECT * FROM subscription_packages WHERE is_active = 1 ORDER BY price ASC");
    $packages = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Veritabanı Hatası: Abonelik paketleri tablosu bulunamadı. Lütfen SQL dosyasını içe aktarın. <br>Hata Detayı: " . $e->getMessage());
}
?>

<style>
    .hero-gradient { background: linear-gradient(135deg, #1a2a6c 0%, #2a48b1 100%); }
</style>

<main>
<!-- Hero Section -->
<div class="hero-gradient py-16 px-4">
    <div class="max-w-[960px] mx-auto text-center">
        <h1 class="text-white tracking-light text-[40px] font-extrabold leading-tight pb-3">İşinizi Büyütmeye Hazır mısınız?</h1>
        <p class="text-white/80 text-lg font-medium leading-normal max-w-2xl mx-auto">
            Size en uygun paketi seçin, rakiplerinizin önüne geçin ve platform üzerindeki görünürlüğünüzü artırarak daha fazla iş kazanın.
        </p>
    </div>
</div>

<!-- Main Pricing Section -->
<div class="flex flex-1 justify-center py-12 px-4 md:px-10 lg:px-40 -mt-10">
    <div class="layout-content-container flex flex-col max-w-[1100px] flex-1">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
            
            <?php foreach ($packages as $index => $pkg): 
                $features = json_decode($pkg['features'], true) ?? [];
                // Ortadaki paketi (genellikle 2. paket) öne çıkaralım
                $isFeatured = ($index === 1); 
                $cardClass = $isFeatured 
                    ? "flex flex-col gap-6 rounded-xl border-4 border-solid border-accent bg-white dark:bg-slate-900 p-8 shadow-2xl relative z-10 scale-105 md:scale-110" 
                    : "flex flex-col gap-6 rounded-xl border border-solid border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 shadow-sm transition-transform hover:scale-[1.02]";
            ?>
            
            <div class="<?= $cardClass ?>">
                <?php if ($isFeatured): ?>
                    <div class="absolute -top-5 left-1/2 -translate-x-1/2 bg-accent text-primary text-xs font-black uppercase tracking-widest px-4 py-1.5 rounded-full shadow-lg whitespace-nowrap">
                        EN ÇOK TERCİH EDİLEN
                    </div>
                <?php endif; ?>

                <div class="flex flex-col gap-1">
                    <h2 class="<?= $isFeatured ? 'text-accent' : 'text-slate-500 dark:text-slate-400' ?> text-sm font-bold uppercase tracking-wider">
                        <?= $isFeatured ? 'Popüler' : 'Paket' ?>
                    </h2>
                    <h1 class="text-slate-900 dark:text-white text-2xl font-bold leading-tight"><?= htmlspecialchars($pkg['name']) ?></h1>
                    <p class="flex items-baseline gap-1 mt-2">
                        <span class="text-slate-900 dark:text-white <?= $isFeatured ? 'text-5xl' : 'text-4xl' ?> font-black tracking-tighter">
                            <?= number_format($pkg['price'], 0, ',', '.') ?> TL
                        </span>
                        <span class="text-slate-500 text-sm font-medium">/ <?= $pkg['duration_days'] ?> gün</span>
                    </p>
                </div>

                <?php
                // Yönlendirme Mantığı
                if (isset($_SESSION['user_id'])) {
                    if ($_SESSION['user_role'] === 'provider' || $_SESSION['user_role'] === 'admin') {
                        // Giriş yapmış provider -> Ödeme sayfasına
                        $actionUrl = "payment.php";
                        $method = "POST";
                        $inputName = "package_id";
                    } else {
                        // Giriş yapmış ama provider değil (Müşteri) -> Başvuru sayfasına yönlendir
                        $actionUrl = "apply.php"; 
                        $method = "GET";
                        $inputName = "package_id"; // apply.php kullanmasa da form yapısı bozulmasın
                    }
                } else {
                    // Giriş yapmamış -> Kayıt sayfasına
                    $actionUrl = "../register.php";
                    $method = "GET";
                    $inputName = "package_id";
                }
                ?>

                <form action="<?= $actionUrl ?>" method="<?= $method ?>">
                    <input type="hidden" name="<?= $inputName ?>" value="<?= $pkg['id'] ?>">
                    <?php if($method === 'GET'): ?>
                        <input type="hidden" name="type" value="provider">
                    <?php endif; ?>
                    
                    <button type="submit" class="flex w-full cursor-pointer items-center justify-center rounded-lg h-12 px-4 <?= $isFeatured ? 'bg-accent text-primary hover:bg-yellow-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700' ?> text-sm font-bold transition-colors shadow-md">
                        <?= $pkg['price'] == 0 ? 'Ücretsiz Başla' : 'Hemen Yükselt' ?>
                    </button>
                </form>

                <div class="flex flex-col gap-4 border-t border-slate-100 dark:border-slate-800 pt-6">
                    <?php foreach ($features as $feature): ?>
                        <div class="text-sm font-medium flex gap-3 text-slate-600 dark:text-slate-400 items-center">
                            <span class="material-symbols-outlined <?= $isFeatured ? 'text-accent' : 'text-green-500' ?> text-[20px]">check_circle</span>
                            <?= htmlspecialchars($feature) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

        </div>

        <!-- Payment Logos -->
        <div class="mt-20 flex flex-col items-center border-t border-slate-200 dark:border-slate-800 pt-10">
            <div class="flex flex-wrap justify-center items-center gap-10 opacity-60 grayscale hover:grayscale-0 transition-all duration-300">
                <div class="flex items-center gap-2"><span class="material-symbols-outlined text-3xl">credit_card</span><span class="font-bold text-lg">MasterCard</span></div>
                <div class="flex items-center gap-2"><span class="material-symbols-outlined text-3xl">payments</span><span class="font-bold text-lg">VISA</span></div>
                <div class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-3xl">lock</span><span class="font-bold text-lg">SSL Secured</span></div>
            </div>
        </div>
    </div>
</div>
</main>

<?php require_once '../includes/footer.php'; ?>