<?php
require_once '../config/db.php';

// Oturum kontrolü ve rol doğrulama
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['provider', 'admin'])) {
    header("Location: ../login.php");
    exit;
}

// Paketleri veritabanından çek
$packages = [];
try {
    $stmt = $pdo->query("SELECT * FROM subscription_packages WHERE is_active = 1 ORDER BY price ASC");
    $packages = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Veritabanı Hatası: Abonelik paketleri tablosu bulunamadı. Lütfen SQL dosyasını içe aktarın. <br>Hata Detayı: " . $e->getMessage());
}

// Site ayarlarını çek (Logo vb. için)
$siteSettings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $siteSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}
$siteTitle = $siteSettings['site_title'] ?? 'iyiteklif';
?>
<!DOCTYPE html>
<html class="light" lang="tr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Paket Seçimi | <?= htmlspecialchars($siteTitle) ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#fbbd23",
                        "night-blue": "#1a2a6c",
                        "background-light": "#f8f7f5",
                        "background-dark": "#231d0f",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .hero-gradient { background: linear-gradient(135deg, #1a2a6c 0%, #2a48b1 100%); }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100">
    <div class="relative flex h-auto min-h-screen w-full flex-col group/design-root overflow-x-hidden">
        <div class="layout-container flex h-full grow flex-col">
            
            <!-- Navbar -->
            <header class="flex items-center justify-between whitespace-nowrap border-b border-solid border-slate-200 dark:border-slate-800 bg-white dark:bg-background-dark px-10 py-3 sticky top-0 z-50">
                <div class="flex items-center gap-4 text-night-blue dark:text-primary">
                    <a href="../index.php" class="flex items-center gap-2">
                        <?php if (!empty($siteSettings['site_logo']) && file_exists('../' . $siteSettings['site_logo'])): ?>
                            <img src="../<?= htmlspecialchars($siteSettings['site_logo']) ?>" alt="<?= htmlspecialchars($siteTitle) ?>" class="h-8 w-auto object-contain">
                        <?php else: ?>
                            <h2 class="text-xl font-bold leading-tight tracking-[-0.015em]"><?= htmlspecialchars($siteTitle) ?></h2>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="flex flex-1 justify-end gap-8">
                    <div class="flex items-center gap-4">
                        <span class="text-sm font-medium text-slate-600 dark:text-slate-300">Hoşgeldin, <?= htmlspecialchars($_SESSION['user_name']) ?></span>
                        <a href="../logout.php" class="text-sm font-bold text-red-600 hover:text-red-700">Çıkış</a>
                    </div>
                </div>
            </header>

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
                                ? "flex flex-col gap-6 rounded-xl border-4 border-solid border-primary bg-white dark:bg-slate-900 p-8 shadow-2xl relative z-10 scale-105 md:scale-110" 
                                : "flex flex-col gap-6 rounded-xl border border-solid border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 shadow-sm transition-transform hover:scale-[1.02]";
                        ?>
                        
                        <div class="<?= $cardClass ?>">
                            <?php if ($isFeatured): ?>
                                <div class="absolute -top-5 left-1/2 -translate-x-1/2 bg-primary text-night-blue text-xs font-black uppercase tracking-widest px-4 py-1.5 rounded-full shadow-lg whitespace-nowrap">
                                    EN ÇOK TERCİH EDİLEN
                                </div>
                            <?php endif; ?>

                            <div class="flex flex-col gap-1">
                                <h2 class="<?= $isFeatured ? 'text-primary' : 'text-slate-500 dark:text-slate-400' ?> text-sm font-bold uppercase tracking-wider">
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

                            <form action="process-payment.php" method="POST">
                                <input type="hidden" name="package_id" value="<?= $pkg['id'] ?>">
                                <button type="submit" class="flex w-full cursor-pointer items-center justify-center rounded-lg h-12 px-4 <?= $isFeatured ? 'bg-primary text-night-blue hover:bg-yellow-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700' ?> text-sm font-bold transition-colors shadow-md">
                                    <?= $pkg['price'] == 0 ? 'Ücretsiz Başla' : 'Hemen Yükselt' ?>
                                </button>
                            </form>

                            <div class="flex flex-col gap-4 border-t border-slate-100 dark:border-slate-800 pt-6">
                                <?php foreach ($features as $feature): ?>
                                    <div class="text-sm font-medium flex gap-3 text-slate-600 dark:text-slate-400 items-center">
                                        <span class="material-symbols-outlined <?= $isFeatured ? 'text-primary' : 'text-green-500' ?> text-[20px]">check_circle</span>
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
        </div>
    </div>
</body>
</html>