<?php
// Footer menülerini çek
$footerMenuItems = [];
if (isset($pdo)) {
    $stmt = $pdo->query("SELECT * FROM menu_items WHERE menu_location = 'footer' AND is_active = 1 ORDER BY sort_order ASC");
    $footerMenuItems = $stmt->fetchAll();
}
?>
<footer class="bg-primary text-white pt-24 pb-12">
    <div class="max-w-7xl mx-auto px-4">
        <div class="grid md:grid-cols-4 gap-12 mb-20">
            <div>
                <?php if (!empty($siteSettings['site_logo']) && file_exists($siteSettings['site_logo'])): ?>
                    <img src="<?= htmlspecialchars($siteSettings['site_logo']) ?>" alt="<?= htmlspecialchars($siteSettings['site_title'] ?? 'iyiteklif') ?>" class="h-12 w-auto object-contain mb-8 brightness-0 invert opacity-90">
                <?php else: ?>
                    <h1 class="text-3xl font-black tracking-tighter mb-8 italic"><?= htmlspecialchars($siteSettings['site_title'] ?? 'iyiteklif') ?></h1>
                <?php endif; ?>
                <p class="text-slate-300 text-sm leading-relaxed mb-8 font-medium"><?= htmlspecialchars($siteSettings['site_description'] ?? 'Türkiye\'nin en güvenilir hizmet pazaryeri.') ?></p>
                <div class="flex gap-4">
                    <a class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center hover:bg-accent hover:text-primary transition-all" href="#"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path></svg></a>
                    <a class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center hover:bg-accent hover:text-primary transition-all" href="#"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.84 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"></path></svg></a>
                </div>
            </div>
            <div>
                <h6 class="font-black mb-8 text-accent uppercase text-sm tracking-widest">Kategoriler</h6>
                <ul class="space-y-4 text-slate-300 text-sm font-medium">
                    <li><a class="hover:text-accent transition-colors" href="#">Ev Temizliği</a></li>
                    <li><a class="hover:text-accent transition-colors" href="#">Tadilat & Dekorasyon</a></li>
                    <li><a class="hover:text-accent transition-colors" href="#">Evden Eve Nakliyat</a></li>
                    <li><a class="hover:text-accent transition-colors" href="#">Klima Servisi</a></li>
                    <li><a class="hover:text-accent transition-colors" href="#">Matematik Özel Ders</a></li>
                </ul>
            </div>
            <div>
                <h6 class="font-black mb-8 text-accent uppercase text-sm tracking-widest">Kurumsal</h6>
                <ul class="space-y-4 text-slate-300 text-sm font-medium">
                    <?php foreach ($footerMenuItems as $item): ?>
                        <?php
                            $show = false;
                            $userRole = $_SESSION['user_role'] ?? 'guest';
                            if ($item['visibility'] === 'all') {
                                $show = true;
                            } elseif ($item['visibility'] === 'guest' && !$isLoggedIn) {
                                $show = true;
                            } elseif ($item['visibility'] === 'customer' && $isLoggedIn && $userRole === 'customer') {
                                $show = true;
                            } elseif ($item['visibility'] === 'provider' && $isLoggedIn && $userRole === 'provider') {
                                $show = true;
                            }
                        ?>
                        <?php if ($show): ?>
                            <li><a class="hover:text-accent transition-colors" href="<?= htmlspecialchars($item['url']) ?>" target="<?= htmlspecialchars($item['target']) ?>"><?= htmlspecialchars($item['title']) ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h6 class="font-black mb-8 text-accent uppercase text-sm tracking-widest">Mobil Uygulama</h6>
                <div class="space-y-4">
                    <button class="w-full flex items-center gap-3 bg-white/10 hover:bg-white hover:text-primary px-6 py-3 rounded-2xl border border-white/20 transition-all group">
                        <span class="material-symbols-outlined text-3xl">phone_iphone</span>
                        <div class="text-left">
                            <p class="text-[10px] uppercase font-bold opacity-70">App Store'dan</p>
                            <p class="text-sm font-black uppercase">İndirin</p>
                        </div>
                    </button>
                    <button class="w-full flex items-center gap-3 bg-white/10 hover:bg-white hover:text-primary px-6 py-3 rounded-2xl border border-white/20 transition-all group">
                        <span class="material-symbols-outlined text-3xl">play_arrow</span>
                        <div class="text-left">
                            <p class="text-[10px] uppercase font-bold opacity-70">Google Play'den</p>
                            <p class="text-sm font-black uppercase">Edinin</p>
                        </div>
                    </button>
                </div>
            </div>
        </div>
        <div class="pt-12 border-t border-white/10 flex flex-col md:row-span-1 md:flex-row justify-between items-center gap-8">
            <p class="text-slate-400 text-xs font-bold tracking-widest uppercase">© <?= date('Y') ?> <?= htmlspecialchars($siteSettings['site_title'] ?? 'iyiteklif') ?>. Tüm hakları saklıdır.</p>
            <div class="flex gap-10 text-slate-400 text-xs font-bold tracking-widest uppercase">
                <a class="hover:text-white" href="#">Yardım Merkezi</a>
                <a class="hover:text-white" href="#">Blog</a>
                <a class="hover:text-white" href="cerez-politikasi.php">Çerezler</a>
            </div>
        </div>
    </div>
</footer>

<!-- Cookie Policy Banner -->
<div id="cookie-banner" class="fixed bottom-0 left-0 w-full bg-white/95 dark:bg-slate-900/95 backdrop-blur-sm border-t border-slate-200 dark:border-slate-800 shadow-[0_-4px_20px_-5px_rgba(0,0,0,0.1)] z-[100] transform translate-y-full transition-transform duration-500 ease-out" style="display: none;">
    <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="hidden sm:flex bg-primary/10 dark:bg-accent/10 p-2.5 rounded-full shrink-0">
                <span class="material-symbols-outlined text-primary dark:text-accent">cookie</span>
            </div>
            <p class="text-sm text-slate-600 dark:text-slate-300 font-medium leading-relaxed text-center sm:text-left">
                Size daha iyi bir deneyim sunmak için çerezleri kullanıyoruz. Sitemizi kullanmaya devam ederek <a href="cerez-politikasi.php" class="text-primary dark:text-accent font-bold hover:underline transition-colors">Çerez Politikamızı</a> kabul etmiş olursunuz.
            </p>
        </div>
        <div class="flex items-center gap-3 shrink-0 w-full sm:w-auto justify-center">
            <button id="cookie-accept" class="bg-primary hover:bg-primary/90 text-white px-6 py-2.5 rounded-xl text-sm font-bold transition-all shadow-lg shadow-primary/20 active:scale-95">
                Kabul Et
            </button>
            <button id="cookie-close" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const banner = document.getElementById('cookie-banner');
        const acceptBtn = document.getElementById('cookie-accept');
        const closeBtn = document.getElementById('cookie-close');

        if (!document.cookie.split('; ').find(row => row.startsWith('cookie_consent='))) {
            banner.style.display = 'block';
            // Animasyonun tetiklenmesi için kısa bir gecikme
            setTimeout(() => {
                banner.classList.remove('translate-y-full');
            }, 50);
        }

        function closeBanner() {
            banner.classList.add('translate-y-full');
            setTimeout(() => {
                banner.style.display = 'none';
            }, 500);
        }

        acceptBtn.addEventListener('click', () => {
            document.cookie = "cookie_consent=accepted; path=/; max-age=" + (60*60*24*365); 
            closeBanner();
        });

        closeBtn.addEventListener('click', () => {
             closeBanner();
        });
    });
</script>

</body>
</html>