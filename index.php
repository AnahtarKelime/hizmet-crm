<?php
require_once 'config/db.php';

// Popüler kategorileri veritabanından çek
$popularCategories = [];
try {
    // Önce öne çıkanları çek
    $stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 AND is_featured = 1 ORDER BY id ASC LIMIT 12");
    $popularCategories = $stmt->fetchAll();

    // Eğer öne çıkan yoksa, varsayılan olarak ilk 14'ü çek
    if (empty($popularCategories)) {
        $stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY id ASC LIMIT 12");
        $popularCategories = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Hata durumunda boş dizi kalır
}

// Kullanıcı giriş yapmışsa kayıtlı konumunu çek
$userLocationText = ''; // Varsayılan boş olsun
$userLocationSlug = '';

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT city, district FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userLoc = $stmt->fetch();

    if ($userLoc && !empty($userLoc['city']) && !empty($userLoc['district'])) {
        $userLocationText = $userLoc['district'] . ' / ' . $userLoc['city'];
        // Bu il/ilçe kombinasyonu için uygun bir slug bul (örn: ilk mahalle)
        $stmtSlug = $pdo->prepare("SELECT slug FROM locations WHERE city = ? AND district = ? LIMIT 1");
        $stmtSlug->execute([$userLoc['city'], $userLoc['district']]);
        $userLocationSlug = $stmtSlug->fetchColumn() ?: '';
    }
}

require_once 'includes/header.php';
?>

<?php
// Google Maps API Anahtarı Kontrolü (Fallback ile)
$googleApiKey = $siteSettings['google_maps_api_key'] ?? '';
$googleGeoApiKey = !empty($siteSettings['google_maps_geo_api_key']) ? $siteSettings['google_maps_geo_api_key'] : $googleApiKey;
?>

<style>
    /* Google Autocomplete Dropdown Z-Index Fix ve Tasarım */
    .pac-container {
        z-index: 10000 !important; /* Dropdown'ın en üstte görünmesini sağlar */
        border-radius: 1rem;
        margin-top: 0.5rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        border: 1px solid rgba(226, 232, 240, 0.8);
        font-family: 'Inter', sans-serif;
    }
    .pac-item {
        padding: 0.75rem 1rem;
        cursor: pointer;
        border-top: 1px solid #f1f5f9;
        font-size: 0.875rem;
        color: #334155;
    }
    .pac-item:first-child { border-top: none; }
    .pac-item:hover { background-color: #f8fafc; }
    .pac-item-query { font-size: 0.875rem; color: #0f172a; font-weight: 600; }
</style>

<main>
    <div class="relative w-full min-h-[500px] flex items-center justify-center py-20">
        <div class="absolute inset-0 bg-cover bg-center" style='background-image: url("https://lh3.googleusercontent.com/aida-public/AB6AXuCFTmfms5gIZw0baGYxkytuep9SuCC6LSyPQzBXoPRlY7zFY0o4joOj2hN9KuTfdCevD1bLV7HTf8o0ZyP-TP_f_L3IRSdRsFYa0LjxhkI6Tl3kFnLnpYPrdomUduyQtZEEGAWCKVeP6CstQQV5xzXdOulfi5B4AlzNu4KrCf8pWgtElx6AL6Bb5h8fstdHowpkVaSqpsp4UouQPDkvdzLgE1IonAGOjiSkRQjuIdl7yI-iZNVz9yJAuK_6BW_rjQ62Nf8I_FmETME");'>
            <div class="absolute inset-0 bg-primary/40 backdrop-blur-[2px]"></div>
        </div>
        <div class="relative z-10 w-full max-w-5xl px-4 text-center">
            <h2 class="text-4xl md:text-6xl font-black text-white mb-6 drop-shadow-xl leading-tight">
                Aradığın hizmeti bul.
            </h2>
            <p class="text-lg md:text-2xl text-white/95 mb-10 font-semibold">
                Binlerce güvenilir uzman <span class="text-accent underline decoration-4 underline-offset-8"><?= htmlspecialchars($siteTitle) ?></span> güvencesiyle yanınızda.
            </p>
            <!-- Arama Formu Alanı -->
            <div class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md p-2 md:p-3 rounded-2xl shadow-[0_35px_60px_-15px_rgba(0,0,0,0.3)] flex flex-col md:flex-row items-stretch gap-2 border border-white/20 dark:border-slate-700 relative z-20 ring-1 ring-white/40 dark:ring-slate-800">
                <!-- Hizmet Arama -->
                <div class="flex-[1.5] flex items-center px-4 border-b md:border-b-0 md:border-r border-slate-200 dark:border-slate-700 group relative">
                    <span id="search-icon" class="material-symbols-outlined text-slate-400 group-focus-within:text-primary">search</span>
                    <span id="search-spinner" class="material-symbols-outlined text-primary animate-spin hidden">progress_activity</span>
                    <input id="service-search" autocomplete="off" class="w-full border-none focus:ring-0 bg-transparent py-4 text-slate-800 dark:text-white placeholder:text-slate-500 font-semibold" placeholder="Hangi hizmeti arıyorsun? (örn: Temizlik, Boyacı)" type="text"/>
                    <input type="hidden" id="selected-service-slug" name="service_slug">
                    <!-- Hizmet Sonuçları Dropdown -->
                    <ul id="service-results" class="absolute top-full left-0 w-full bg-white dark:bg-slate-800 rounded-xl shadow-xl mt-2 hidden overflow-hidden border border-slate-100 dark:border-slate-700 z-50 max-h-60 overflow-y-auto"></ul>
                </div>
                
                <!-- Lokasyon Arama -->
                <div class="flex-1 flex items-center px-4 group relative">
                    <!-- Konumum Butonu -->
                    <button type="button" id="btn-my-location" class="p-2 text-slate-400 hover:text-primary transition-colors" title="Konumumu Bul">
                        <span class="material-symbols-outlined">my_location</span>
                    </button>
                    <!-- ID'yi değiştirdik ki eski search.js çakışmasın -->
                    <input id="google-location-search" autocomplete="off" class="w-full border-none focus:ring-0 bg-transparent py-4 text-slate-800 dark:text-white placeholder:text-slate-500 font-semibold" placeholder="Konumunuzu arayın (İlçe, Mahalle...)" type="text" value="<?= htmlspecialchars($userLocationText) ?>"/>
                    
                    <!-- Google'dan gelen verileri tutacak hidden inputlar -->
                    <input type="hidden" id="g-address" name="g_address">
                    <input type="hidden" id="g-lat" name="g_lat">
                    <input type="hidden" id="g-lng" name="g_lng">
                    <input type="hidden" id="g-city" name="g_city">
                    <input type="hidden" id="g-district" name="g_district">
                </div>

                <button id="btn-find-service-custom" class="bg-primary hover:bg-primary/90 text-white font-black py-4 px-12 rounded-xl transition-all flex items-center justify-center gap-2 group shadow-lg shadow-primary/30 hover:shadow-primary/50 hover:-translate-y-0.5">
                    Hizmet Bul
                    <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </button>
            </div>
        </div>
    </div>
    <div class="bg-accent py-5 shadow-inner">
        <div class="max-w-7xl mx-auto px-4 flex flex-wrap justify-around gap-8 text-primary font-black uppercase tracking-tight text-sm">
            <div class="hidden md:flex items-center gap-3">
                <span class="material-symbols-outlined text-2xl">verified</span>
                <span>1M+ Onaylı Uzman</span>
            </div>
            <div class="hidden md:flex items-center gap-3">
                <span class="material-symbols-outlined text-2xl">star</span>
                <span>4.8/5 Kullanıcı Puanı</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-2xl">shield</span>
                <span><?= htmlspecialchars($siteTitle) ?> İyi Teklif Garantisi</span>
            </div>
        </div>
    </div>
    <section class="max-w-7xl mx-auto px-4 py-12">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-6">
            <div class="max-w-2xl">
                <h3 class="text-2xl md:text-4xl font-black text-primary dark:text-white mb-4 uppercase tracking-tighter flex items-center gap-2">
                    <span class="material-symbols-outlined text-2xl md:text-4xl fill-1 text-primary dark:text-white">local_fire_department</span>
                    Popüler Kategoriler
                </h3>
                <p class="text-slate-600 dark:text-slate-400 text-lg font-medium">En çok tercih edilen, yüksek puanlı uzmanlarımızın bulunduğu popüler hizmetlerimiz.</p>
            </div>
            <a class="bg-primary/5 hover:bg-primary/10 text-primary dark:text-accent px-6 py-3 rounded-xl font-black flex items-center gap-2 transition-all border border-primary/10" href="#">
                Tümünü Keşfet <span class="material-symbols-outlined">chevron_right</span>
            </a>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-6">
            <?php foreach($popularCategories as $cat): ?>
            <div class="group relative h-[250px] md:h-[380px] rounded-2xl md:rounded-3xl overflow-hidden cursor-pointer shadow-xl hover:shadow-2xl transition-all border-2 md:border-4 border-transparent hover:border-accent/50" onclick="window.location.href='teklif-al.php?service=<?= $cat['slug'] ?>'">
                <?php 
                // Görsel varsa onu kullan, yoksa placeholder
                $bgImage = !empty($cat['image']) && file_exists($cat['image']) 
                    ? htmlspecialchars($cat['image']) 
                    : "https://placehold.co/600x800/1a2a6c/FFF?text=" . urlencode($cat['name']);
                ?>
                <img alt="<?= htmlspecialchars($cat['name']) ?>" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" src="<?= $bgImage ?>"/>
                <div class="absolute inset-0 service-card-overlay flex flex-col justify-end p-4 md:p-8">
                    <?php if($cat['id'] == 1): ?><span class="bg-accent text-primary text-[10px] font-black px-2 py-0.5 rounded w-fit mb-3">EN ÇOK ARANAN</span><?php endif; ?>
                    <h4 class="text-white font-black text-lg md:text-2xl mb-1 md:mb-2 leading-tight"><?= htmlspecialchars($cat['name']) ?></h4>
                    <p class="text-white/80 text-sm font-medium">Hemen teklif al.</p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="bg-primary/5 dark:bg-slate-900/50 py-32 border-y border-slate-200 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h3 class="text-4xl font-black text-primary dark:text-white mb-6 uppercase tracking-tight">Nasıl Çalışır?</h3>
            <p class="text-slate-600 dark:text-slate-400 max-w-2xl mx-auto mb-24 text-lg font-medium">Aradığınız hizmete ulaşmak <?= htmlspecialchars($siteTitle) ?> ile artık çok daha kolay ve güvenli.</p>
            <div class="grid md:grid-cols-3 gap-12 relative">
                <div class="hidden lg:block absolute top-24 left-0 w-full h-1 bg-primary/10 -z-10"></div>
                <div class="flex flex-col items-center">
                    <div class="w-48 h-48 bg-white dark:bg-slate-800 rounded-full flex items-center justify-center mb-10 shadow-2xl relative border-8 border-primary group hover:border-accent transition-colors duration-500">
                        <span class="material-symbols-outlined text-7xl text-primary dark:text-accent group-hover:scale-110 transition-transform">edit_document</span>
                        <div class="absolute -top-4 -right-4 w-12 h-12 bg-accent text-primary font-black rounded-full flex items-center justify-center text-xl shadow-lg ring-4 ring-white">1</div>
                    </div>
                    <h5 class="text-2xl font-black mb-4 dark:text-white">Hizmeti Tanımla</h5>
                    <p class="text-slate-600 dark:text-slate-400 font-medium">İhtiyacın olan hizmeti seç ve kısa birkaç soruyu yanıtla.</p>
                </div>
                <div class="flex flex-col items-center">
                    <div class="w-48 h-48 bg-white dark:bg-slate-800 rounded-full flex items-center justify-center mb-10 shadow-2xl relative border-8 border-primary group hover:border-accent transition-colors duration-500">
                        <span class="material-symbols-outlined text-7xl text-primary dark:text-accent group-hover:scale-110 transition-transform">send_money</span>
                        <div class="absolute -top-4 -right-4 w-12 h-12 bg-accent text-primary font-black rounded-full flex items-center justify-center text-xl shadow-lg ring-4 ring-white">2</div>
                    </div>
                    <h5 class="text-2xl font-black mb-4 dark:text-white">Teklifleri Karşılaştır</h5>
                    <p class="text-slate-600 dark:text-slate-400 font-medium">Dakikalar içinde sana özel gelen ücretsiz teklifleri incele.</p>
                </div>
                <div class="flex flex-col items-center">
                    <div class="w-48 h-48 bg-white dark:bg-slate-800 rounded-full flex items-center justify-center mb-10 shadow-2xl relative border-8 border-primary group hover:border-accent transition-colors duration-500">
                        <span class="material-symbols-outlined text-7xl text-primary dark:text-accent group-hover:scale-110 transition-transform">task_alt</span>
                        <div class="absolute -top-4 -right-4 w-12 h-12 bg-accent text-primary font-black rounded-full flex items-center justify-center text-xl shadow-lg ring-4 ring-white">3</div>
                    </div>
                    <h5 class="text-2xl font-black mb-4 dark:text-white">İşini Tamamla</h5>
                    <p class="text-slate-600 dark:text-slate-400 font-medium">En uygun uzmanı seç, işini güvenle ve huzurla tamamlat.</p>
                </div>
            </div>
        </div>
    </section>
    <section class="bg-primary py-24 relative overflow-hidden">
        <div class="absolute top-0 right-0 opacity-10 pointer-events-none">
            <span class="material-symbols-outlined text-[400px]">handshake</span>
        </div>
        <div class="max-w-4xl mx-auto px-4 text-center relative z-10">
            <h3 class="text-4xl font-black text-white mb-8">İşini büyütmek mi istiyorsun?</h3>
            <p class="text-white/80 mb-12 text-xl font-medium"> İyi Teklif ile her gün binlerce yeni müşteriye ulaş. Hemen ücretsiz profilini oluştur ve teklif vermeye baş.</p>
            <div class="flex flex-col sm:flex-row justify-center gap-6">
                <button class="bg-accent hover:bg-white text-primary font-black px-12 py-5 rounded-2xl transition-all text-xl shadow-2xl" onclick="window.location.href='provider/apply.php'">
                    Hizmet Veren Ol
                </button>
                <button class="bg-white/10 hover:bg-white/20 text-white font-black px-12 py-5 rounded-2xl transition-all text-xl border border-white/20" onclick="window.location.href='nasil-calisir.php'">
                    Nasıl İlerlerim?
                </button>
            </div>
        </div>
    </section>
    <section class="max-w-7xl mx-auto px-4 py-32">
        <div class="text-center mb-16">
            <h3 class="text-4xl font-black text-primary dark:text-white uppercase tracking-tighter">Mutlu Kullanıcılar</h3>
            <div class="h-1.5 w-24 bg-accent mx-auto mt-4 rounded-full"></div>
        </div>
        <div class="grid md:grid-cols-3 gap-10">
            <div class="bg-white dark:bg-slate-800 p-10 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-700 hover:-translate-y-2 transition-transform">
                <div class="flex gap-1 text-accent mb-6">
                    <span class="material-symbols-outlined fill-1">star</span><span class="material-symbols-outlined fill-1">star</span><span class="material-symbols-outlined fill-1">star</span><span class="material-symbols-outlined fill-1">star</span><span class="material-symbols-outlined fill-1">star</span>
                </div>
                <p class="text-slate-700 dark:text-slate-300 mb-8 font-semibold text-lg leading-relaxed">"Taşınma sürecimde bu kadar hızlı ve profesyonel bir ekip bulacağımı tahmin etmemiştim. Teklifler anında geldi."</p>
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-slate-200 border-2 border-accent" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuDqzP4pviRTHmKg5-1be9yin5jqmzQEVLDYVyJFPEjxZYru2gh6Osi7sCulVv2EiM6T7apYH5EkC6lb-tNeU4wuiuhhbp6bYcLvg-2dSu3PjymxNnRA70belHUssVcUwAGdENwGVZb2jkEIZTXP060j3hOUHGXu9dpz3VkgZSrE3IB4Sz4EvUHQeYtAdbRbPcb60vTfykdc0dyywgThbcf_BMXN3Rm8FWHQ4ELsIKK57oO0oJ8270Xqk4-puPgRJ16AhcpCmSXG23Y'); background-size: cover;"></div>
                    <div>
                        <p class="font-black text-slate-900 dark:text-white">Seda Bakış</p>
                        <p class="text-sm text-slate-500 font-bold uppercase tracking-wider">Nakliyat Hizmeti</p>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-800 p-10 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-700 hover:-translate-y-2 transition-transform">
                <div class="flex gap-1 text-accent mb-6">
                    <span class="material-symbols-outlined fill-1">star</span><span class="material-symbols-outlined fill-1">star</span><span class="material-symbols-outlined fill-1">star</span><span class="material-symbols-outlined fill-1">star</span><span class="material-symbols-outlined fill-1">star</span>
                </div>
                <p class="text-slate-700 dark:text-slate-300 mb-8 font-semibold text-lg leading-relaxed">"Ev temizliği için düzenli hizmet alıyorum. Gelen personeller her zaman dakik ve işlerini çok iyi yapıyorlar."</p>
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-slate-200 border-2 border-accent" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuDPIJ20C4HD992lCY9eVFUUaVo2dRKlY7xAyhHNrMuNzupXEay5zN2mUAEBAtn4qTujuoYeOSuKjJQ3goIfqyv09yTdzcScmpRuQdeasnClljzlJAUv8gMDUzeLoRGAoWq3XTSP3qdccmERIZPwqkQeDTW28xMf1BN4AojxzmMmHi4NRDBO-ennXbLa3BMP1Tv68q-U8pfyzefD1fS8awNpOSoskig8N2RYMb3296Y4NGZAoZ913SXWLZp8nM_r05y0Ft7WAhacMPA'); background-size: cover;"></div>
                    <div>
                        <p class="font-black text-slate-900 dark:text-white">Burak Üzel</p>
                        <p class="text-sm text-slate-500 font-bold uppercase tracking-wider">Temizlik Hizmeti</p>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-800 p-10 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-700 hover:-translate-y-2 transition-transform">
                <div class="flex gap-1 text-accent mb-6">
                    <span class="material-symbols-outlined fill-1">star</span><span class="material-symbols-outlined fill-1">star</span><span class="material-symbols-outlined fill-1">star</span><span class="material-symbols-outlined fill-1">star</span><span class="material-symbols-outlined fill-1">star</span>
                </div>
                <p class="text-slate-700 dark:text-slate-300 mb-8 font-semibold text-lg leading-relaxed">"Tadilat işleri hep korkutucudur ama iyi teklif üzerinden bulduğumuz usta çok titiz ve dürüst çıktı."</p>
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-slate-200 border-2 border-accent" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuCllGvrDgGk16uxQojJY6f9NUv3lM25K2Ani2BP3PKIFjBh0DpTqtw2jpdrR0aULWBk-2gksH0tNVlOZmJE7D4eZ-hKz0ZsEkC_iPQqL5tlIC7zWPmIMjz9lv8PduT9Kac_IH0VHmbEt-0D4akvT3lI7jnz0OaI3X4UQKXShJmla_7SjOJoJcKmw56sf4CdV3esn8vSI3iG_WNOjJ7x1i-H2Eb1pH6b0tOjE9ngTonwqFRtMgiyIfN7JmeKw69h3ISfQUmcXCXq9yo'); background-size: cover;"></div>
                    <div>
                        <p class="font-black text-slate-900 dark:text-white">Merve Şen</p>
                        <p class="text-sm text-slate-500 font-bold uppercase tracking-wider">Tadilat Hizmeti</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Popular Services Modal -->
<div id="popular-services-modal" class="fixed inset-0 z-[80] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300 opacity-0">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-6 max-w-2xl w-full mx-4 transform scale-95 transition-transform duration-300 relative">
        <button onclick="closePopularServicesModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
            <span class="material-symbols-outlined text-2xl">close</span>
        </button>
        
        <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-2">Popüler Hizmetler</h3>
        <p class="text-slate-500 dark:text-slate-400 mb-6">Lütfen devam etmek için bir hizmet seçin veya arama yapın.</p>
        
        <div class="relative mb-4">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input type="text" id="modal-service-search" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white pl-10 py-3 focus:ring-primary focus:border-primary" placeholder="Listede ara...">
        </div>

        <div id="popular-services-grid" class="grid grid-cols-2 md:grid-cols-3 gap-4 max-h-[50vh] overflow-y-auto custom-scrollbar p-1">
            <?php foreach($popularCategories as $cat): ?>
                <div onclick="selectService('<?= $cat['slug'] ?>', '<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>')" class="cursor-pointer flex flex-col items-center justify-center p-4 rounded-xl border border-slate-100 dark:border-slate-700 hover:border-primary/50 hover:bg-primary/5 dark:hover:bg-slate-700/50 transition-all group text-center h-full">
                    <div class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-2xl"><?= $cat['icon'] ?: 'category' ?></span>
                    </div>
                    <span class="text-sm font-bold text-slate-700 dark:text-slate-200 group-hover:text-primary transition-colors"><?= htmlspecialchars($cat['name']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    // Popüler Hizmetleri JS'e aktar
    const popularServicesData = <?= json_encode(array_map(function($cat) {
        return [
            'name' => $cat['name'],
            'slug' => $cat['slug'],
            'icon' => $cat['icon']
        ];
    }, $popularCategories)) ?>;
</script>
<script src="assets/js/search.js"></script>
<script>
    // Google Maps API Yükleme Hatası Yakalama
    window.gm_authFailure = function() {
        console.error("Google Maps API Hatası: Kimlik doğrulama başarısız.");
        alert("Harita servisi yüklenemedi. API Anahtarı geçersiz veya süresi dolmuş.");
    };

    // Google Places Autocomplete Başlatma
    window.initAutocomplete = function() {
        const input = document.getElementById("google-location-search");
        if (!input) return;

        const options = {
            componentRestrictions: { country: "tr" }, // Sadece Türkiye
            fields: ["formatted_address", "geometry", "address_components"],
            types: ["geocode"] // Sokak, Cadde, İl, İlçe ve Mahalle odaklı arama
        };

        let autocomplete;
        try {
            autocomplete = new google.maps.places.Autocomplete(input, options);
        } catch (e) {
            console.error("Autocomplete başlatılamadı:", e);
            return;
        }

        autocomplete.addListener("place_changed", () => {
            const place = autocomplete.getPlace();
            
            if (!place.geometry || !place.geometry.location) {
                return;
            }

            // Değerleri hidden inputlara ata
            document.getElementById("g-address").value = place.formatted_address;
            document.getElementById("g-lat").value = place.geometry.location.lat();
            document.getElementById("g-lng").value = place.geometry.location.lng();

            // Adres bileşenlerini ayrıştır (İl/İlçe)
            parseAddressComponents(place.address_components);
        });
    };

    // Google Adres Bileşenlerini Ayrıştırma Fonksiyonu
    function parseAddressComponents(components) {
        let city = "";
        let district = "";

        if (components) {
            for (const component of components) {
                const types = component.types;
                if (types.includes("administrative_area_level_1")) {
                    city = component.long_name; // İl (Örn: İstanbul)
                }
                if (types.includes("administrative_area_level_2")) {
                    district = component.long_name; // İlçe (Örn: Kadıköy)
                }
                // Bazı durumlarda ilçe 'locality' veya 'sublocality' olabilir
                if (!district && (types.includes("locality") || types.includes("sublocality_level_1"))) {
                    district = component.long_name;
                }
            }
        }
        
        document.getElementById("g-city").value = city;
        document.getElementById("g-district").value = district;

        // Header ve Cookie Güncelleme
        const locationName = city || district;
        if (locationName) {
            const headerLocText = document.getElementById('header-location-text');
            if (headerLocText) {
                headerLocText.textContent = locationName;
            }
            document.cookie = "user_location=" + encodeURIComponent(locationName) + "; path=/; max-age=" + (60*60*24*30);
            
            // Veritabanına Kaydet (Eğer giriş yapmışsa)
            // PHP tarafında isLoggedIn kontrolü yapıldığı için JS tarafında basitçe isteği atabiliriz,
            // backend session yoksa reddeder.
            const address = document.getElementById("g-address").value;
            const lat = document.getElementById("g-lat").value;
            const lng = document.getElementById("g-lng").value;

            if (address) {
                const formData = new FormData();
                formData.append('address', address);
                formData.append('lat', lat);
                formData.append('lng', lng);
                formData.append('city', city);
                formData.append('district', district);

                fetch('ajax/update-user-location.php', { method: 'POST', body: formData });
            }
        }
    }

    // Modal Functions
    window.openPopularServicesModal = function() {
        const modal = document.getElementById('popular-services-modal');
        if(modal) {
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                const content = modal.querySelector('div');
                if(content) {
                    content.classList.remove('scale-95');
                    content.classList.add('scale-100');
                }
            }, 10);
        }
    }

    window.closePopularServicesModal = function() {
        const modal = document.getElementById('popular-services-modal');
        if(modal) {
            modal.classList.add('opacity-0');
            const content = modal.querySelector('div');
            if(content) {
                content.classList.remove('scale-100');
                content.classList.add('scale-95');
            }
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
    }
    
    // Close modal on outside click
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('popular-services-modal');
        if (modal && !modal.classList.contains('hidden') && event.target === modal) {
            closePopularServicesModal();
        }
    });

    // Hizmet Seçimi ve Modal Arama
    window.selectService = function(slug, name) {
        const serviceInput = document.getElementById('service-search');
        const slugInput = document.getElementById('selected-service-slug');
        const locationInput = document.getElementById('google-location-search');
        
        if(serviceInput) serviceInput.value = name;
        if(slugInput) slugInput.value = slug;
        
        closePopularServicesModal();
        
        // Konum seçilmemişse konuma odaklan
        if(locationInput && !locationInput.value) {
            setTimeout(() => {
                locationInput.focus();
            }, 300);
        }
    };

    document.getElementById('modal-service-search')?.addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        const items = document.querySelectorAll('#popular-services-grid > div');
        items.forEach(item => {
            const text = item.querySelector('span').textContent.toLowerCase();
            if(text.includes(term)) {
                item.classList.remove('hidden');
            } else {
                item.classList.add('hidden');
            }
        });
    });

    // Modal Functions
    window.openPopularServicesModal = function() {
        const modal = document.getElementById('popular-services-modal');
        if(modal) {
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                const content = modal.querySelector('div');
                if(content) {
                    content.classList.remove('scale-95');
                    content.classList.add('scale-100');
                }
            }, 10);
        }
    }

    window.closePopularServicesModal = function() {
        const modal = document.getElementById('popular-services-modal');
        if(modal) {
            modal.classList.add('opacity-0');
            const content = modal.querySelector('div');
            if(content) {
                content.classList.remove('scale-100');
                content.classList.add('scale-95');
            }
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
    }
    
    // Close modal on outside click
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('popular-services-modal');
        if (modal && !modal.classList.contains('hidden') && event.target === modal) {
            closePopularServicesModal();
        }
    });

    // Sayfa yüklendiğinde çalıştır
    document.addEventListener("DOMContentLoaded", function() {
        // search.js içindeki butona tıklama olayını override ediyoruz
        const findBtn = document.getElementById('btn-find-service-custom');
        if(findBtn) {
            // Mevcut event listener'ı kaldırmak zor olduğu için, search.js'deki ID'leri değiştirdik veya
            // burada kendi mantığımızı kuruyoruz. search.js'deki locationInput kontrolü null döneceği için hata vermez.
            
            findBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();

                const serviceSlug = document.getElementById('selected-service-slug')?.value;
                const serviceText = document.getElementById('service-search')?.value;
                
                // Helper to stop loading
                const stopLoading = () => {
                    findBtn.innerHTML = originalContent;
                    findBtn.disabled = false;
                    findBtn.classList.remove('opacity-80', 'cursor-not-allowed');
                };

                if (!serviceSlug && !serviceText) {
                    // Hizmet seçilmediyse modal aç
                    openPopularServicesModal();
                    return;
                }
                const originalContent = findBtn.innerHTML;

                // Arama Fonksiyonu
                const performSearch = () => {
                    // Loading Başlat (Eğer başlatılmadıysa)
                    findBtn.innerHTML = '<span class="material-symbols-outlined animate-spin text-xl">progress_activity</span> Aranıyor...';
                    findBtn.disabled = true;
                    findBtn.classList.add('opacity-80', 'cursor-not-allowed');

                    // Güncel değerleri al
                    const finalGAddress = document.getElementById('g-address').value;
                    const finalGLat = document.getElementById('g-lat').value;
                    const finalGLng = document.getElementById('g-lng').value;
                    const finalGCity = document.getElementById('g-city').value;
                    const finalGDistrict = document.getElementById('g-district').value;
                    const finalRawLoc = document.getElementById('google-location-search').value;

                    let url = `teklif-al.php?service=${encodeURIComponent(serviceSlug || '')}`;
                    
                    if (finalGAddress && finalGLat) {
                        // Google verileri tam ise
                        url += `&address=${encodeURIComponent(finalGAddress)}&lat=${finalGLat}&lng=${finalGLng}&city=${encodeURIComponent(finalGCity)}&district=${encodeURIComponent(finalGDistrict)}`;
                    } else {
                        // Sadece metin varsa (Fallback)
                        if(finalRawLoc) url += `&raw_location=${encodeURIComponent(finalRawLoc)}`;
                    }
                    window.location.href = url;
                };

                const rawLoc = document.getElementById('google-location-search').value;

                // Konum metni girilmemişse Geolocation dene
                if (!rawLoc) {
                    if (navigator.geolocation) {
                        findBtn.innerHTML = '<span class="material-symbols-outlined animate-spin text-xl">progress_activity</span> Konum alınıyor...';
                        findBtn.disabled = true;
                        findBtn.classList.add('opacity-80', 'cursor-not-allowed');

                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                const lat = position.coords.latitude;
                                const lng = position.coords.longitude;

                                // Google Maps JS Geocoder Kullan (Daha kararlı)
                                if (typeof google !== 'undefined' && google.maps && google.maps.Geocoder) {
                                    const geocoder = new google.maps.Geocoder();
                                    geocoder.geocode({ location: { lat: lat, lng: lng } }, (results, status) => {
                                        if (status === "OK" && results[0]) {
                                            const place = results[0];
                                            document.getElementById("g-address").value = place.formatted_address;
                                            document.getElementById("g-lat").value = lat;
                                            document.getElementById("g-lng").value = lng;
                                            document.getElementById("google-location-search").value = place.formatted_address;

                                            // Adres bileşenlerini ayrıştır
                                            parseAddressComponents(place.address_components);

                                            performSearch();
                                        } else {
                                            console.error("Geocoder failed: " + status);
                                            alert('Adres çözümlenemedi. Lütfen manuel seçiniz.');
                                            stopLoading();
                                        }
                                    });
                                } else {
                                    // JS API yüklenmediyse REST API dene (Yedek)
                                    const geoApiKey = '<?= $googleGeoApiKey ?>';
                                    fetch(`https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key=${geoApiKey}&language=tr`)
                                        .then(response => response.json())
                                        .then(data => {
                                            if (data.status === 'OK' && data.results[0]) {
                                                const place = data.results[0];
                                                document.getElementById("g-address").value = place.formatted_address;
                                                document.getElementById("g-lat").value = lat;
                                                document.getElementById("g-lng").value = lng;
                                                document.getElementById("google-location-search").value = place.formatted_address;
                                                
                                                parseAddressComponents(place.address_components);
                                                performSearch();
                                            } else {
                                                console.error('Geocoding API Error:', data.status, data.error_message);
                                                alert('Adres çözümlenemedi. Lütfen manuel seçiniz.');
                                                stopLoading();
                                            }
                                        })
                                        .catch(err => {
                                            console.error(err);
                                            alert('Konum servisi hatası. Lütfen manuel seçiniz.');
                                            stopLoading();
                                        });
                                }
                            },
                            (error) => {
                                console.warn("Geolocation error:", error);
                                alert('Konum alınamadı. Lütfen arama kutusundan konum seçiniz.');
                                stopLoading();
                                document.getElementById('google-location-search')?.focus();
                            },
                            { timeout: 10000 }
                        );
                    } else {
                        alert('Tarayıcınız konum özelliğini desteklemiyor. Lütfen manuel seçiniz.');
                        document.getElementById('google-location-search')?.focus();
                    }
                    return;
                }

                // Konum varsa normal akış (Manuel giriş kontrolü)
                findBtn.innerHTML = '<span class="material-symbols-outlined animate-spin text-xl">progress_activity</span> Aranıyor...';
                findBtn.disabled = true;
                findBtn.classList.add('opacity-80', 'cursor-not-allowed');

                // Eğer koordinat yoksa ama metin varsa (Manuel giriş veya API hatası)
                // Geocoding servisini manuel tetiklemeyi dene
                const currentLat = document.getElementById('g-lat').value;
                if (!currentLat && rawLoc && typeof google !== 'undefined' && google.maps && google.maps.Geocoder) {
                    const geocoder = new google.maps.Geocoder();
                    geocoder.geocode({ 'address': rawLoc, 'componentRestrictions': { 'country': 'TR' } }, function(results, status) {
                        if (status === 'OK' && results[0]) {
                            const place = results[0];
                            document.getElementById("g-address").value = place.formatted_address;
                            document.getElementById("g-lat").value = place.geometry.location.lat();
                            document.getElementById("g-lng").value = place.geometry.location.lng();
                            
                            parseAddressComponents(place.address_components);
                        }
                        // Her durumda aramayı yap (Bulunsa da bulunmasa da)
                        performSearch();
                    });
                } else {
                    // Geocoder yoksa veya zaten koordinat varsa direkt ara
                    performSearch();
                }
            };
        }

        // Konumum Butonu İşlevi
        const myLocationBtn = document.getElementById('btn-my-location');
        if (myLocationBtn) {
            myLocationBtn.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    alert('Tarayıcınız konum servisini desteklemiyor.');
                    return;
                }

                const originalIcon = myLocationBtn.innerHTML;
                myLocationBtn.innerHTML = '<span class="material-symbols-outlined animate-spin">progress_activity</span>';
                myLocationBtn.disabled = true;

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;

                        // Google Maps JS Geocoder Kullan
                        if (typeof google !== 'undefined' && google.maps && google.maps.Geocoder) {
                            const geocoder = new google.maps.Geocoder();
                            geocoder.geocode({ location: { lat: lat, lng: lng } }, (results, status) => {
                                if (status === "OK" && results[0]) {
                                    const place = results[0];
                                    document.getElementById("g-address").value = place.formatted_address;
                                    document.getElementById("g-lat").value = lat;
                                    document.getElementById("g-lng").value = lng;
                                    document.getElementById("google-location-search").value = place.formatted_address;
                                    
                                    parseAddressComponents(place.address_components);
                                } else {
                                    alert('Adres çözümlenemedi.');
                                }
                                myLocationBtn.innerHTML = originalIcon;
                                myLocationBtn.disabled = false;
                            });
                        } else {
                            // Fallback REST API
                            const geoApiKey = '<?= $googleGeoApiKey ?>';
                            fetch(`https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key=${geoApiKey}&language=tr`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.status === 'OK' && data.results[0]) {
                                        const place = data.results[0];
                                        document.getElementById("g-address").value = place.formatted_address;
                                        document.getElementById("g-lat").value = lat;
                                        document.getElementById("g-lng").value = lng;
                                        document.getElementById("google-location-search").value = place.formatted_address;
                                        
                                        parseAddressComponents(place.address_components);
                                    } else {
                                        alert('Adres çözümlenemedi.');
                                    }
                                })
                                .catch(err => alert('Konum servisi hatası.'))
                                .finally(() => {
                                    myLocationBtn.innerHTML = originalIcon;
                                    myLocationBtn.disabled = false;
                                });
                        }
                    },
                    (error) => {
                        console.warn("Geolocation error:", error);
                        let msg = 'Konum alınamadı.';
                        if (error.code === 1) msg = 'Konum izni reddedildi.';
                        alert(msg);
                        myLocationBtn.innerHTML = originalIcon;
                        myLocationBtn.disabled = false;
                    },
                    { timeout: 10000, enableHighAccuracy: true }
                );
            });
        }
    });
</script>

<!-- Google Maps API (Callback ile yükleme - Daha kararlı çalışır) -->
<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($googleApiKey) ?>&libraries=places&callback=initAutocomplete" async defer></script>

<?php if (!$isLoggedIn): ?>
<!-- Google Login Prompt -->
<div id="google-login-prompt" class="fixed z-[70] transition-all duration-500 opacity-0 invisible" style="display: none;">
    
    <!-- Desktop Pop-up (Sağ Üst) -->
    <div class="hidden md:flex fixed top-24 right-6 w-80 bg-white dark:bg-slate-800 rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.12)] border border-slate-100 dark:border-slate-700 p-5 flex-col gap-4 transform transition-transform duration-500 translate-x-10" id="desktop-prompt-content">
        <button onclick="closeGooglePrompt(event)" class="absolute top-3 right-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
            <span class="material-symbols-outlined text-lg">close</span>
        </button>
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-sm border border-slate-100 shrink-0">
                <img src="https://www.google.com/favicon.ico" alt="Google" class="w-6 h-6">
            </div>
            <div>
                <h5 class="font-bold text-slate-800 dark:text-white text-base">Hızlı Giriş Yap</h5>
                <p class="text-xs text-slate-500 dark:text-slate-400 leading-tight mt-1">Teklifleri kaçırma, hemen hesabına eriş.</p>
            </div>
        </div>
        <a href="google-login.php" class="w-full bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 font-bold py-2.5 rounded-xl text-sm flex items-center justify-center gap-3 transition-all shadow-sm group">
            <img src="https://www.google.com/favicon.ico" alt="Google" class="w-4 h-4">
            <span class="group-hover:text-primary transition-colors">Google ile Devam Et</span>
        </a>
    </div>

    <!-- Mobile Bottom Bar -->
    <div class="md:hidden fixed bottom-0 left-0 w-full bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 p-4 shadow-[0_-4px_20px_rgba(0,0,0,0.1)] transform transition-transform duration-500 translate-y-full" id="mobile-prompt-content">
         <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 flex-1">
                <div class="w-10 h-10 bg-white dark:bg-slate-800 rounded-full flex items-center justify-center border border-slate-100 dark:border-slate-700 shadow-sm shrink-0">
                    <img src="https://www.google.com/favicon.ico" alt="Google" class="w-5 h-5">
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-slate-800 dark:text-white text-sm truncate">Google ile Giriş Yap</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">Hızlı ve güvenli erişim.</p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="google-login.php" class="bg-primary text-white px-4 py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-primary/20 whitespace-nowrap">
                    Giriş Yap
                </a>
                <button onclick="closeGooglePrompt(event)" class="p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-full transition-colors">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Oturum süresince kapattıysa tekrar gösterme
        if (!sessionStorage.getItem('googlePromptClosed')) {
            setTimeout(() => {
                const prompt = document.getElementById('google-login-prompt');
                const desktopContent = document.getElementById('desktop-prompt-content');
                const mobileContent = document.getElementById('mobile-prompt-content');
                
                if(prompt) {
                    prompt.style.display = 'block';
                    prompt.offsetHeight; // Reflow tetikle
                    prompt.classList.remove('opacity-0', 'invisible');
                    
                    if(desktopContent) desktopContent.classList.remove('translate-x-10');
                    if(mobileContent) mobileContent.classList.remove('translate-y-full');
                }
            }, 3500); // 3.5 saniye sonra göster
        }

        // Dışarı tıklama kontrolü (Sadece masaüstü için)
        document.addEventListener('click', function(event) {
            const prompt = document.getElementById('google-login-prompt');
            const desktopContent = document.getElementById('desktop-prompt-content');
            
            if (prompt && !prompt.classList.contains('invisible') && window.innerWidth >= 768) {
                if (desktopContent && !desktopContent.contains(event.target)) {
                    closeGooglePrompt(event);
                }
            }
        });
    });

    function closeGooglePrompt(e) {
        if(e) e.stopPropagation();
        const prompt = document.getElementById('google-login-prompt');
        const desktopContent = document.getElementById('desktop-prompt-content');
        const mobileContent = document.getElementById('mobile-prompt-content');
        
        if(prompt) {
            if(desktopContent) desktopContent.classList.add('translate-x-10');
            if(mobileContent) mobileContent.classList.add('translate-y-full');
            prompt.classList.add('opacity-0', 'invisible');
            setTimeout(() => { prompt.style.display = 'none'; }, 500);
            sessionStorage.setItem('googlePromptClosed', 'true');
        }
    }
</script>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
