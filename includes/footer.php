<?php
// Footer menülerini çek
$footerMenuItems = [];
$footerMenuItems = $cache->get('footer_menus');

if ($footerMenuItems === null) {
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT * FROM menu_items WHERE menu_location = 'footer' AND is_active = 1 ORDER BY sort_order ASC");
        $footerMenuItems = $stmt->fetchAll();
        $cache->set('footer_menus', $footerMenuItems, 86400);
    }
}

// Footer için popüler kategorileri çek
$footerCategories = [];
$footerCategories = $cache->get('footer_categories');

if ($footerCategories === null) {
    if (isset($pdo)) {
        try {
            $stmt = $pdo->query("SELECT name, slug FROM categories WHERE is_active = 1 AND is_featured = 1 ORDER BY sort_order ASC LIMIT 5");
            $footerCategories = $stmt->fetchAll();
            
            if (empty($footerCategories)) {
                $stmt = $pdo->query("SELECT name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 5");
                $footerCategories = $stmt->fetchAll();
            }
            $cache->set('footer_categories', $footerCategories, 3600);
        } catch (Exception $e) {}
    }
}

// VAPID Public Key'i al (JS için)
$vapidPublicKey = '';
if (isset($pdo)) {
    $stmtVapid = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'vapid_public_key'");
    $vapidPublicKey = $stmtVapid->fetchColumn();
}

// Service Worker ve API Yolu (Alt klasör uyumluluğu için)
$siteRoot = '/';
if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    $siteRoot = '/hizmet-crm/'; // Localhost klasör adı
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
                    <?php if (!empty($siteSettings['social_instagram'])): ?>
                    <a class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center hover:bg-accent hover:text-primary transition-all" href="<?= htmlspecialchars($siteSettings['social_instagram']) ?>" target="_blank" rel="noopener noreferrer"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>
                    <?php endif; ?>
                    <?php if (!empty($siteSettings['social_linkedin'])): ?>
                    <a class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center hover:bg-accent hover:text-primary transition-all" href="<?= htmlspecialchars($siteSettings['social_linkedin']) ?>" target="_blank" rel="noopener noreferrer"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg></a>
                    <?php endif; ?>
                    <?php if (!empty($siteSettings['social_facebook'])): ?>
                    <a class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center hover:bg-accent hover:text-primary transition-all" href="<?= htmlspecialchars($siteSettings['social_facebook']) ?>" target="_blank" rel="noopener noreferrer"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path></svg></a>
                    <?php endif; ?>
                    <?php if (!empty($siteSettings['social_youtube'])): ?>
                    <a class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center hover:bg-accent hover:text-primary transition-all" href="<?= htmlspecialchars($siteSettings['social_youtube']) ?>" target="_blank" rel="noopener noreferrer"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></a>
                    <?php endif; ?>
                    <?php if (!empty($siteSettings['social_twitter'])): ?>
                    <a class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center hover:bg-accent hover:text-primary transition-all" href="<?= htmlspecialchars($siteSettings['social_twitter']) ?>" target="_blank" rel="noopener noreferrer"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <h6 class="font-black mb-8 text-accent uppercase text-sm tracking-widest">Kategoriler</h6>
                <ul class="space-y-4 text-slate-300 text-sm font-medium">
                    <?php if (!empty($footerCategories)): ?>
                        <?php foreach ($footerCategories as $cat): ?>
                            <li><a class="hover:text-accent transition-colors" href="<?= $pathPrefix ?? '' ?>teklif-al.php?service=<?= htmlspecialchars($cat['slug']) ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><a class="hover:text-accent transition-colors" href="#">Kategori Bulunamadı</a></li>
                    <?php endif; ?>
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
                            <?php 
                                $url = $item['url'];
                                if (!filter_var($url, FILTER_VALIDATE_URL) && substr($url, 0, 1) !== '#' && substr($url, 0, 7) !== 'mailto:' && substr($url, 0, 4) !== 'tel:') {
                                    $url = ($pathPrefix ?? '') . $url;
                                }
                            ?>
                            <li><a class="hover:text-accent transition-colors" href="<?= htmlspecialchars($url) ?>" target="<?= htmlspecialchars($item['target']) ?>"><?= htmlspecialchars($item['title']) ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h6 class="font-black mb-8 text-accent uppercase text-sm tracking-widest">Mobil Uygulama</h6>
                <div class="space-y-4">
                    <button onclick="alert('iOS cihazlarda: Safari tarayıcısında Paylaş butonuna tıklayıp \'Ana Ekrana Ekle\' seçeneğini kullanabilirsiniz.')" class="w-full flex items-center gap-3 bg-white/10 hover:bg-white hover:text-primary px-6 py-3 rounded-2xl border border-white/20 transition-all group">
                        <span class="material-symbols-outlined text-3xl">phone_iphone</span>
                        <div class="text-left">
                            <p class="text-[10px] uppercase font-bold opacity-70">App Store'dan</p>
                            <p class="text-sm font-black uppercase">İndirin</p>
                        </div>
                    </button>
                    <button id="pwa-install-btn" class="w-full flex items-center gap-3 bg-white/10 hover:bg-white hover:text-primary px-6 py-3 rounded-2xl border border-white/20 transition-all group">
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
                <a class="hover:text-white" href="<?= ($pathPrefix ?? '') ?>cerez-politikasi.php">Çerezler</a>
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
                Size daha iyi bir deneyim sunmak için çerezleri kullanıyoruz. Sitemizi kullanmaya devam ederek <a href="<?= ($pathPrefix ?? '') ?>cerez-politikasi.php" class="text-primary dark:text-accent font-bold hover:underline transition-colors">Çerez Politikamızı</a> kabul etmiş olursunuz.
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
    // PWA Kurulum Mantığı
    let deferredPrompt;
    const installBtn = document.getElementById('pwa-install-btn');

    // Service Worker Kaydı (Herkes için)
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('<?= $siteRoot ?>service-worker.js').then(function(registration) {
                console.log('ServiceWorker registration successful');
            }, function(err) {
                console.log('ServiceWorker registration failed: ', err);
            });
        });
    }

    // Kurulum butonu görünürlüğü ve tetikleme
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        console.log('PWA kurulumu hazır');
    });

    if (installBtn) {
        installBtn.addEventListener('click', (e) => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('Kullanıcı uygulamayı yükledi');
                    }
                    deferredPrompt = null;
                });
            } else {
                alert('Uygulamayı yüklemek için tarayıcı menüsünden "Uygulamayı Yükle" veya "Ana Ekrana Ekle" seçeneğini kullanabilirsiniz.\n\nNot: Eğer uygulama zaten yüklüyse veya tarayıcınız desteklemiyorsa bu özellik çalışmayabilir.');
            }
        });
    }
</script>

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

<!-- Bildirim İzni Banner'ı -->
<div id="notification-banner" class="fixed bottom-4 right-4 z-50 hidden max-w-sm w-full bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-700 p-4 transform transition-all duration-300 translate-y-20 opacity-0">
    <div class="flex items-start gap-4">
        <div class="bg-indigo-100 text-indigo-600 p-3 rounded-full shrink-0">
            <span class="material-symbols-outlined">notifications_active</span>
        </div>
        <div class="flex-1">
            <h4 class="font-bold text-slate-900 dark:text-white text-sm mb-1">Bildirimleri Açın</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-3 leading-relaxed">Teklifler, mesajlar ve önemli güncellemelerden anında haberdar olmak için bildirim izni verin.</p>
            <div class="flex gap-2">
                <button onclick="requestPermission()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-4 py-2 rounded-lg transition-colors">İzin Ver</button>
                <button onclick="dismissNotificationBanner()" class="text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 text-xs font-bold px-2 py-2">Daha Sonra</button>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['user_id']) && !empty($vapidPublicKey)): ?>
<script>
    // Push Notification Logic
    const vapidPublicKey = "<?= $vapidPublicKey ?>";
    const siteRoot = "<?= $siteRoot ?>";

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function showNotificationBanner() {
        const banner = document.getElementById('notification-banner');
        // Eğer izin 'default' ise (ne reddedilmiş ne kabul edilmiş) ve kullanıcı daha önce kapatmadıysa göster
        if (banner && Notification.permission === 'default' && !localStorage.getItem('notification_dismissed')) {
            banner.classList.remove('hidden');
            // Animasyon için reflow
            void banner.offsetWidth;
            banner.classList.remove('translate-y-20', 'opacity-0');
        }
    }

    function dismissNotificationBanner() {
        const banner = document.getElementById('notification-banner');
        if (banner) {
            banner.classList.add('translate-y-20', 'opacity-0');
            setTimeout(() => banner.classList.add('hidden'), 300);
            localStorage.setItem('notification_dismissed', 'true');
        }
    }

    function requestPermission() {
        Notification.requestPermission().then(function(permission) {
            dismissNotificationBanner();
            if (permission === 'granted') {
                navigator.serviceWorker.ready.then(function(swReg) {
                    subscribeUser(swReg);
                });
            }
        });
    }

    if ('serviceWorker' in navigator && 'PushManager' in window) {
        // Service Worker yolunu dinamik yapıyoruz (Admin panelinden de erişilebilsin diye)
        navigator.serviceWorker.register(siteRoot + 'service-worker.js')
        .then(function(swReg) {
            if (Notification.permission === 'default') {
                // Sayfa yüklendikten 2 saniye sonra banner'ı göster
                setTimeout(showNotificationBanner, 2000);
            }
            
            // Abonelik kontrolü
            swReg.pushManager.getSubscription().then(function(subscription) {
                if (Notification.permission === 'granted') {
                    if (!subscription) {
                        subscribeUser(swReg);
                    } else {
                        // Mevcut aboneliği güncelle (Token değişmiş olabilir)
                        updateSubscriptionOnServer(subscription);
                    }
                }
            });
        })
        .catch(function(error) {
            console.error('Service Worker Error', error);
        });
    }

    function subscribeUser(swReg) {
        const applicationServerKey = urlBase64ToUint8Array(vapidPublicKey);
        swReg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: applicationServerKey
        })
        .then(function(subscription) {
            updateSubscriptionOnServer(subscription);
        })
        .catch(function(err) { console.log('Failed to subscribe the user: ', err); });
    }

    function updateSubscriptionOnServer(subscription) {
        fetch(siteRoot + 'ajax/save-subscription.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(subscription)
        });
    }
</script>
<?php endif; ?>