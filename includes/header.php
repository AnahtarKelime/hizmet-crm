<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? 'Hesabım';

// Linkler için kök dizin öneki (Varsayılan boş)
$pathPrefix = $pathPrefix ?? '';

// Kullanıcı Rolünü ve Bilgilerini Güncelle (Session Senkronizasyonu)
if ($isLoggedIn && isset($pdo)) {
    $stmt = $pdo->prepare("SELECT role, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
    if ($currentUser) {
        $_SESSION['user_role'] = $currentUser['role'];
        $_SESSION['user_name'] = $currentUser['first_name'] . ' ' . $currentUser['last_name'];
    }
}

// Site Ayarlarını Çek
$siteSettings = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch()) {
            $siteSettings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        // Hata durumunda varsayılanlar
    }
}
$siteTitle = $siteSettings['site_title'] ?? 'iyiteklif';
$siteDescription = $siteSettings['site_description'] ?? 'Aradığın Hizmeti Bul';
?>
<!DOCTYPE html>
<html class="light" lang="tr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | ' . htmlspecialchars($siteTitle) : htmlspecialchars($siteTitle) . ' | ' . htmlspecialchars($siteDescription); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
          darkMode: "class",
          theme: {
            extend: {
              colors: {
                "primary": "#1a2a6c",
                "accent": "#fbbd23",
                "background-light": "#f6f6f8",
                "background-dark": "#13151f",
              },
              fontFamily: {
                "display": ["Inter", "sans-serif"]
              },
              borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1rem", "full": "9999px"},
            },
          },
        }
    </script>
    <style type="text/tailwindcss">
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        body {
            font-family: 'Inter', sans-serif;
        }
        .service-card-overlay {
            background: linear-gradient(to top, rgba(26, 42, 108, 0.95) 0%, rgba(26, 42, 108, 0.4) 50%, rgba(26, 42, 108, 0.1) 100%);
        }.mega-menu-open {
            overflow: hidden;
        }
    </style>
    <?php if (isset($category) && !empty($category['tracking_code_head'])): ?>
        <?= $category['tracking_code_head'] ?>
    <?php endif; ?>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 transition-colors duration-200">
<?php if (isset($category) && !empty($category['tracking_code_body'])): ?>
    <?= $category['tracking_code_body'] ?>
<?php endif; ?>
<div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[55]" style="display:none;"></div> <!-- JS ile kontrol edilecek, varsayılan gizli -->
<header class="sticky top-0 z-[60] w-full bg-white dark:bg-background-dark border-b border-slate-200 dark:border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <div class="flex items-center gap-6">
                <div class="relative">
                    <button id="menu-toggle" class="flex items-center gap-2 text-primary dark:text-accent transition-colors">
                        <span class="material-symbols-outlined text-3xl">menu</span> <!-- İkonu menu olarak değiştirdim, açılınca close olur -->
                    </button>
                    <!-- Mega Menu Başlangıcı (Varsayılan olarak gizli olması gerekebilir, JS ile tetiklenecek) -->
                    <div id="mega-menu" class="hidden fixed top-16 left-0 w-full bg-white dark:bg-slate-900 shadow-2xl border-t border-slate-100 dark:border-slate-800 z-[60] overflow-y-auto max-h-[calc(100vh-64px)]">
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                            <!-- Mobile Nav Links (Sadece mobilde görünür) -->
                            <div class="lg:hidden mb-8 pb-8 border-b border-slate-100 dark:border-slate-800 space-y-4">
                                <a class="block text-lg font-bold text-slate-800 dark:text-slate-200 hover:text-primary" href="<?= $pathPrefix ?>index.php">Hizmetleri Keşfet</a>
                                <a class="block text-lg font-bold text-slate-800 dark:text-slate-200 hover:text-primary" href="<?= $pathPrefix ?>provider/apply.php">Hizmet Veren Ol</a>
                                <a class="block text-lg font-bold text-slate-800 dark:text-slate-200 hover:text-primary" href="<?= $pathPrefix ?>nasil-calisir.php">Nasıl Çalışır?</a>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-10">
                                <div>
                                    <div class="flex items-center gap-2 mb-6 text-primary dark:text-accent">
                                        <span class="material-symbols-outlined">cleaning_services</span>
                                        <h4 class="font-black text-lg uppercase tracking-tight">Ev Temizliği & Hizmetleri</h4>
                                    </div>
                                    <ul class="space-y-4">
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Ev Temizliği <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Ofis Temizliği <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">İnşaat Sonrası Temizlik <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Koltuk Yıkama <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Halı Yıkama <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                    </ul>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 mb-6 text-primary dark:text-accent">
                                        <span class="material-symbols-outlined">format_paint</span>
                                        <h4 class="font-black text-lg uppercase tracking-tight">Tadilat & Dekorasyon</h4>
                                    </div>
                                    <ul class="space-y-4">
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Boya Badana <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Mutfak Yenileme <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Banyo Tadilatı <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Elektrik Tesisatı <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Alçıpan & Kartonpiyer <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                    </ul>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 mb-6 text-primary dark:text-accent">
                                        <span class="material-symbols-outlined">local_shipping</span>
                                        <h4 class="font-black text-lg uppercase tracking-tight">Nakliyat & Depolama</h4>
                                    </div>
                                    <ul class="space-y-4">
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Evden Eve Nakliyat <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Şehirler Arası Nakliyat <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Eşya Depolama <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Ofis Taşıma <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Parça Eşya Taşıma <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                    </ul>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 mb-6 text-primary dark:text-accent">
                                        <span class="material-symbols-outlined">school</span>
                                        <h4 class="font-black text-lg uppercase tracking-tight">Özel Ders</h4>
                                    </div>
                                    <ul class="space-y-4">
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Matematik <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">İngilizce <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">LGS & YKS Hazırlık <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">İlkokul Takviye <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Müzik & Enstrüman <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                    </ul>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 mb-6 text-primary dark:text-accent">
                                        <span class="material-symbols-outlined">health_and_safety</span>
                                        <h4 class="font-black text-lg uppercase tracking-tight">Sağlık & Kişisel Bakım</h4>
                                    </div>
                                    <ul class="space-y-4">
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Diyetisyen <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Psikolog <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Kişisel Eğitmen <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Fizyoterapist <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="#">Evde Bakım <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="mt-16 pt-8 border-t border-slate-100 dark:border-slate-800 flex justify-between items-center">
                                <div class="flex gap-4">
                                    <a class="px-6 py-3 bg-primary text-white rounded-xl font-bold hover:bg-primary/90 transition-all" href="#">Tüm Kategoriler</a>
                                    <a class="px-6 py-3 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-xl font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-all" href="<?= $pathPrefix ?>nasil-calisir.php">Nasıl Çalışır?</a>
                                </div>
                                <div class="text-slate-400 text-sm font-medium italic"><?= htmlspecialchars($siteTitle) ?> ile aradığın uzman kapında.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="<?= $pathPrefix ?>index.php" class="flex items-center gap-2">
                        <?php if (!empty($siteSettings['site_logo']) && file_exists($pathPrefix . $siteSettings['site_logo'])): ?>
                            <img src="<?= $pathPrefix . htmlspecialchars($siteSettings['site_logo']) ?>" alt="<?= htmlspecialchars($siteTitle) ?>" class="h-10 w-auto object-contain">
                        <?php else: ?>
                            <h1 class="text-2xl font-black tracking-tighter text-primary dark:text-white italic"><?= htmlspecialchars($siteTitle) ?></h1>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="hidden md:flex items-center gap-2 px-3 py-1.5 bg-slate-100 dark:bg-slate-800 rounded-lg cursor-pointer hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors ml-4">
                    <span class="material-symbols-outlined text-primary dark:text-accent text-xl">location_on</span>
                    <span class="text-sm font-semibold">İstanbul</span>
                    <span class="material-symbols-outlined text-sm">expand_more</span>
                </div>
            </div>
            <nav class="hidden lg:flex items-center gap-8">
                <a class="text-sm font-bold text-slate-700 dark:text-slate-300 hover:text-primary dark:hover:text-accent transition-colors" href="<?= $pathPrefix ?>index.php">Hizmetleri Keşfet</a>
                <a class="text-sm font-bold text-slate-700 dark:text-slate-300 hover:text-primary dark:hover:text-accent transition-colors" href="<?= $pathPrefix ?>provider/apply.php">Hizmet Veren Ol</a>
                <a class="text-sm font-bold text-slate-700 dark:text-slate-300 hover:text-primary dark:hover:text-accent transition-colors" href="<?= $pathPrefix ?>nasil-calisir.php">Nasıl Çalışır?</a>
            </nav>
            <div class="flex items-center gap-3">
                <?php if ($isLoggedIn): ?>
                    <div class="relative group">
                        <button class="flex items-center gap-2 text-sm font-bold text-slate-700 dark:text-slate-300 hover:text-primary dark:hover:text-accent transition-colors">
                            <span class="material-symbols-outlined">account_circle</span>
                            <span><?= htmlspecialchars($userName) ?></span>
                            <span class="material-symbols-outlined text-sm">expand_more</span>
                        </button>
                        <!-- Dropdown -->
                        <div class="absolute right-0 top-full mt-2 w-48 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-100 dark:border-slate-700 hidden group-hover:block z-50">
                            <ul class="py-2">
                                <li><a href="<?= $pathPrefix ?>profile.php" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Profilim</a></li>
                                <li><a href="<?= $pathPrefix ?>logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">Çıkış Yap</a></li>
                            </ul>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= $pathPrefix ?>login.php" class="px-4 py-2 text-sm font-bold text-primary dark:text-white hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg">Giriş Yap</a>
                    <a href="<?= $pathPrefix ?>register.php" class="px-6 py-2.5 text-sm font-bold bg-primary text-white hover:bg-primary/90 rounded-xl transition-all shadow-lg">Kayıt Ol</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const menuToggle = document.getElementById('menu-toggle');
        const megaMenu = document.getElementById('mega-menu');
        const menuIcon = menuToggle?.querySelector('.material-symbols-outlined');

        function closeMenu() {
            if (!megaMenu.classList.contains('hidden')) {
                megaMenu.classList.add('hidden');
                if (menuIcon) menuIcon.textContent = 'menu';
            }
        }

        if (menuToggle && megaMenu) {
            menuToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                megaMenu.classList.toggle('hidden');
                
                if (megaMenu.classList.contains('hidden')) {
                    menuIcon.textContent = 'menu';
                } else {
                    menuIcon.textContent = 'close';
                }
            });

            document.addEventListener('click', (e) => {
                if (!megaMenu.contains(e.target) && !menuToggle.contains(e.target)) {
                    closeMenu();
                }
            });

            window.addEventListener('scroll', () => {
                closeMenu();
            });
        }
    });
</script>