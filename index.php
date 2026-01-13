<?php
require_once 'config/db.php';
$pageTitle = "Anasayfa";

// Popüler kategorileri veritabanından çek
$popularCategories = [];
try {
    $stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY id ASC LIMIT 4");
    $popularCategories = $stmt->fetchAll();
} catch (PDOException $e) {
    // Hata durumunda boş dizi kalır
}

require_once 'includes/header.php';
?>

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
            <div class="bg-white dark:bg-slate-900 p-2 md:p-3 rounded-2xl shadow-2xl flex flex-col md:flex-row items-stretch gap-2 border border-white/20 relative z-20">
                <!-- Hizmet Arama -->
                <div class="flex-[1.5] flex items-center px-4 border-b md:border-b-0 md:border-r border-slate-200 dark:border-slate-700 group relative">
                    <span class="material-symbols-outlined text-slate-400 group-focus-within:text-primary">search</span>
                    <input id="service-search" autocomplete="off" class="w-full border-none focus:ring-0 bg-transparent py-4 text-slate-800 dark:text-white placeholder:text-slate-500 font-semibold" placeholder="Hangi hizmeti arıyorsun? (örn: Temizlik, Boyacı)" type="text"/>
                    <input type="hidden" id="selected-service-slug" name="service_slug">
                    <!-- Hizmet Sonuçları Dropdown -->
                    <ul id="service-results" class="absolute top-full left-0 w-full bg-white dark:bg-slate-800 rounded-xl shadow-xl mt-2 hidden overflow-hidden border border-slate-100 dark:border-slate-700 z-50 max-h-60 overflow-y-auto"></ul>
                </div>
                
                <!-- Lokasyon Arama -->
                <div class="flex-1 flex items-center px-4 group relative">
                    <span class="material-symbols-outlined text-slate-400 group-focus-within:text-primary">location_on</span>
                    <input id="location-search" autocomplete="off" class="w-full border-none focus:ring-0 bg-transparent py-4 text-slate-800 dark:text-white placeholder:text-slate-500 font-semibold" placeholder="Şehir veya İlçe" type="text" value="İstanbul"/>
                    <input type="hidden" id="selected-location-slug" name="location_slug">
                    <!-- Lokasyon Sonuçları Dropdown -->
                    <ul id="location-results" class="absolute top-full left-0 w-full bg-white dark:bg-slate-800 rounded-xl shadow-xl mt-2 hidden overflow-hidden border border-slate-100 dark:border-slate-700 z-50 max-h-60 overflow-y-auto"></ul>
                </div>

                <button id="btn-find-service" class="bg-primary hover:bg-primary/95 text-white font-black py-4 px-12 rounded-xl transition-all flex items-center justify-center gap-2 group shadow-xl">
                    Hizmet Bul
                    <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </button>
            </div>
        </div>
    </div>
    <div class="bg-accent py-5 shadow-inner">
        <div class="max-w-7xl mx-auto px-4 flex flex-wrap justify-around gap-8 text-primary font-black uppercase tracking-tight text-sm">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-2xl">verified</span>
                <span>1M+ Onaylı Uzman</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-2xl">star</span>
                <span>4.8/5 Kullanıcı Puanı</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-2xl">shield</span>
                <span><?= htmlspecialchars($siteTitle) ?> Garantisi</span>
            </div>
        </div>
    </div>
    <section class="max-w-7xl mx-auto px-4 py-24">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-6">
            <div class="max-w-2xl">
                <h3 class="text-4xl font-black text-primary dark:text-white mb-4 uppercase tracking-tighter">Popüler Kategoriler</h3>
                <p class="text-slate-600 dark:text-slate-400 text-lg font-medium">En çok tercih edilen, yüksek puanlı uzmanlarımızın bulunduğu popüler hizmetlerimiz.</p>
            </div>
            <a class="bg-primary/5 hover:bg-primary/10 text-primary dark:text-accent px-6 py-3 rounded-xl font-black flex items-center gap-2 transition-all border border-primary/10" href="#">
                Tümünü Keşfet <span class="material-symbols-outlined">chevron_right</span>
            </a>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach($popularCategories as $cat): ?>
            <div class="group relative h-[380px] rounded-3xl overflow-hidden cursor-pointer shadow-xl hover:shadow-2xl transition-all border-4 border-transparent hover:border-accent/50" onclick="window.location.href='teklif-al.php?service=<?= $cat['slug'] ?>'">
                <!-- Dinamik resim olmadığı için placeholder kullanıyoruz -->
                <img alt="<?= htmlspecialchars($cat['name']) ?>" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" src="https://placehold.co/600x800/1a2a6c/FFF?text=<?= urlencode($cat['name']) ?>"/>
                <div class="absolute inset-0 service-card-overlay flex flex-col justify-end p-8">
                    <?php if($cat['id'] == 1): ?><span class="bg-accent text-primary text-[10px] font-black px-2 py-0.5 rounded w-fit mb-3">EN ÇOK ARANAN</span><?php endif; ?>
                    <h4 class="text-white font-black text-2xl mb-2"><?= htmlspecialchars($cat['name']) ?></h4>
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
            <p class="text-white/80 mb-12 text-xl font-medium"><?= htmlspecialchars($siteTitle) ?> ile her gün binlerce yeni müşteriye ulaş. Hemen ücretsiz profilini oluştur ve teklif vermeye baş.</p>
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
                        <p class="font-black text-slate-900 dark:text-white">Ahmet Selim</p>
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
                        <p class="font-black text-slate-900 dark:text-white">Merve Kaya</p>
                        <p class="text-sm text-slate-500 font-bold uppercase tracking-wider">Temizlik Hizmeti</p>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-800 p-10 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-700 hover:-translate-y-2 transition-transform">
                <div class="flex gap-1 text-accent mb-6">
                    <span class="material-symbols-outlined fill-1">star</span><span class="material-symbols-outlined fill-1">star</span><span class="material-symbols-outlined fill-1">star</span><span class="material-symbols-outlined fill-1">star</span><span class="material-symbols-outlined fill-1">star</span>
                </div>
                <p class="text-slate-700 dark:text-slate-300 mb-8 font-semibold text-lg leading-relaxed">"Tadilat işleri hep korkutucudur ama <?= htmlspecialchars($siteTitle) ?> üzerinden bulduğumuz usta çok titiz ve dürüst çıktı."</p>
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-slate-200 border-2 border-accent" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuCllGvrDgGk16uxQojJY6f9NUv3lM25K2Ani2BP3PKIFjBh0DpTqtw2jpdrR0aULWBk-2gksH0tNVlOZmJE7D4eZ-hKz0ZsEkC_iPQqL5tlIC7zWPmIMjz9lv8PduT9Kac_IH0VHmbEt-0D4akvT3lI7jnz0OaI3X4UQKXShJmla_7SjOJoJcKmw56sf4CdV3esn8vSI3iG_WNOjJ7x1i-H2Eb1pH6b0tOjE9ngTonwqFRtMgiyIfN7JmeKw69h3ISfQUmcXCXq9yo'); background-size: cover;"></div>
                    <div>
                        <p class="font-black text-slate-900 dark:text-white">Burak Öztürk</p>
                        <p class="text-sm text-slate-500 font-bold uppercase tracking-wider">Tadilat Hizmeti</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script src="assets/js/search.js"></script>
<?php require_once 'includes/footer.php'; ?>
