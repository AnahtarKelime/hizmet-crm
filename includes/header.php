<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? 'Hesabım';

// Linkler için kök dizin öneki (Varsayılan boş)
$pathPrefix = $pathPrefix ?? '';

// Beni Hatırla (Auto Login)
if (!$isLoggedIn && isset($_COOKIE['remember_token']) && isset($pdo)) {
    list($rUserId, $rHash) = explode(':', base64_decode($_COOKIE['remember_token']), 2);
    if ($rUserId && $rHash) {
        $stmtR = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmtR->execute([$rUserId]);
        $userR = $stmtR->fetch();
        if ($userR && hash_equals(hash_hmac('sha256', $userR['password'], 'HIZMET_CRM_SECURE_KEY'), $rHash)) {
            $_SESSION['user_id'] = $userR['id'];
            $_SESSION['user_name'] = $userR['first_name'] . ' ' . $userR['last_name'];
            $_SESSION['user_role'] = $userR['role'];
            $isLoggedIn = true;
        }
    }
}

// Kullanıcı Rolünü ve Bilgilerini Güncelle (Session Senkronizasyonu)
if ($isLoggedIn && isset($pdo)) {
    $stmt = $pdo->prepare("SELECT role, first_name, last_name, avatar_url FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
    if ($currentUser) {
        $_SESSION['user_role'] = $currentUser['role'];
        $_SESSION['user_name'] = $currentUser['first_name'] . ' ' . $currentUser['last_name'];
        $_SESSION['user_avatar'] = $currentUser['avatar_url'];
    }

    // Okunmamış Mesaj Sayısı
    $stmtMsg = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0 AND deleted_by_receiver = 0");
    $stmtMsg->execute([$_SESSION['user_id']]);
    $unreadCount = $stmtMsg->fetchColumn();

    // Bildirimler (Son gelen teklifler)
    $notifications = [];
    $unreadNotificationCount = 0;
    
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer') {
        // Son 5 teklifi çek
        try {
            $stmtNotif = $pdo->prepare("
                SELECT 
                    o.id as offer_id, 
                    o.created_at, 
                    o.is_read,
                    d.title as demand_title, 
                    d.id as demand_id,
                    u.first_name, u.last_name, pd.business_name
                FROM offers o
                JOIN demands d ON o.demand_id = d.id
                JOIN users u ON o.user_id = u.id
                LEFT JOIN provider_details pd ON u.id = pd.user_id
                WHERE d.user_id = ?
                ORDER BY o.created_at DESC
                LIMIT 5
            ");
            $stmtNotif->execute([$_SESSION['user_id']]);
            $notifications = $stmtNotif->fetchAll();
            
            // Okunmamış teklifleri say (is_read = 0)
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM offers o JOIN demands d ON o.demand_id = d.id WHERE d.user_id = ? AND o.is_read = 0");
            $stmtCount->execute([$_SESSION['user_id']]);
            $unreadNotificationCount = $stmtCount->fetchColumn();
        } catch (PDOException $e) {
            // is_read kolonu yoksa hata vermemesi için fallback
            $stmtNotif = $pdo->prepare("
                SELECT 
                    o.id as offer_id, 
                    o.created_at, 
                    0 as is_read,
                    d.title as demand_title, 
                    d.id as demand_id,
                    u.first_name, u.last_name, pd.business_name
                FROM offers o
                JOIN demands d ON o.demand_id = d.id
                JOIN users u ON o.user_id = u.id
                LEFT JOIN provider_details pd ON u.id = pd.user_id
                WHERE d.user_id = ?
                ORDER BY o.created_at DESC
                LIMIT 5
            ");
            $stmtNotif->execute([$_SESSION['user_id']]);
            $notifications = $stmtNotif->fetchAll();
            $unreadNotificationCount = 0;
        }
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
$siteKeywords = $siteSettings['site_keywords'] ?? '';
$siteFavicon = $siteSettings['site_favicon'] ?? '';

// Menüleri Çek
$headerMenuItems = [];
if (isset($pdo)) {
    $stmt = $pdo->query("SELECT * FROM menu_items WHERE menu_location = 'header' AND is_active = 1 ORDER BY sort_order ASC");
    $headerMenuItems = $stmt->fetchAll();
}

// Konum Cookie Kontrolü
$headerLocationText = isset($_COOKIE['user_location']) ? $_COOKIE['user_location'] : 'Konum Seç';
?>
<!DOCTYPE html>
<html class="light" lang="tr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?php 
        if (isset($pageTitle)) {
            echo $pageTitle . ' | ' . htmlspecialchars($siteTitle);
        } else {
            echo !empty($siteSettings['homepage_title']) 
                ? htmlspecialchars($siteSettings['homepage_title']) 
                : htmlspecialchars($siteTitle) . ' | ' . htmlspecialchars($siteDescription);
        }
    ?></title>
    <meta name="description" content="<?= htmlspecialchars($siteDescription) ?>">
    <?php if ($siteKeywords): ?>
    <meta name="keywords" content="<?= htmlspecialchars($siteKeywords) ?>">
    <?php endif; ?>
    <?php if ($siteFavicon): ?>
    <link rel="icon" href="<?= $pathPrefix . htmlspecialchars($siteFavicon) ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
          darkMode: "class",
          theme: {
            extend: {
              colors: {
                "primary": "<?= $siteSettings['theme_color_primary'] ?? '#1a2a6c' ?>",
                "accent": "<?= $siteSettings['theme_color_accent'] ?? '#fbbd23' ?>",
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
    <?php if (!empty($siteSettings['custom_css'])): ?>
    <style>
        <?= $siteSettings['custom_css'] ?>
    </style>
    <?php endif; ?>
    <?php if (isset($category) && !empty($category['tracking_code_head'])): ?>
        <?= $category['tracking_code_head'] ?>
    <?php endif; ?>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 transition-colors duration-200">
<?php if (isset($category) && !empty($category['tracking_code_body'])): ?>
    <?= $category['tracking_code_body'] ?>
<?php endif; ?>

<?php
// Aktif Duyuruları Çek ve Göster
if (isset($pdo)) {
    try {
        $targetRole = $_SESSION['user_role'] ?? 'guest';
        // Hedef kitleye uygun (all veya kullanıcının rolü) aktif duyuruları çek
        $announcements = $pdo->query("SELECT * FROM announcements WHERE is_active = 1 AND (target_role = 'all' OR target_role = '$targetRole') ORDER BY created_at DESC")->fetchAll();
        
        foreach ($announcements as $ann) {
            $annId = 'ann_' . $ann['id'];
            // Cookie kontrolü: Eğer kullanıcı kapatmışsa gösterme
            if (!isset($_COOKIE[$annId])) {
                echo '<div id="' . $annId . '" class="bg-indigo-600 text-white px-4 py-3 relative text-center text-sm font-medium shadow-sm z-[70] flex items-center justify-center">';
                echo '<span><span class="font-bold uppercase tracking-wide opacity-90 mr-2">' . htmlspecialchars($ann['title']) . ':</span> ' . htmlspecialchars($ann['message']) . '</span>';
                echo '<button onclick="closeAnnouncement(\'' . $annId . '\')" class="absolute right-4 top-1/2 -translate-y-1/2 text-white/80 hover:text-white transition-colors"><span class="material-symbols-outlined text-lg">close</span></button>';
                echo '</div>';
            }
        }
        echo '<script>
            function closeAnnouncement(id) {
                document.getElementById(id).style.display = "none";
                document.cookie = id + "=1; path=/; max-age=" + (60*60*24); // 1 gün boyunca gizle
            }
        </script>';
    } catch (Exception $e) {
        // Tablo yoksa veya hata varsa sessizce geç (Beyaz ekranı önler)
    }
}
?>

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
                                    <a class="px-6 py-3 bg-primary text-white rounded-xl font-bold hover:bg-primary/90 transition-all" href="<?= $pathPrefix ?>tum-hizmetler.php">Tüm Kategoriler</a>
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
                <div id="header-location-btn" class="hidden md:flex items-center gap-2 px-3 py-1.5 bg-slate-100 dark:bg-slate-800 rounded-lg cursor-pointer hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors ml-4">
                    <span class="material-symbols-outlined text-primary dark:text-accent text-xl">location_on</span>
                    <span class="text-sm font-semibold" id="header-location-text"><?= htmlspecialchars($headerLocationText) ?></span>
                    <span class="material-symbols-outlined text-sm">expand_more</span>
                </div>
            </div>
            <nav class="hidden lg:flex items-center gap-8">
                <?php foreach ($headerMenuItems as $item): ?>
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
                        <a class="text-sm font-bold text-slate-700 dark:text-slate-300 hover:text-primary dark:hover:text-accent transition-colors" href="<?= $pathPrefix . htmlspecialchars($item['url']) ?>" target="<?= htmlspecialchars($item['target']) ?>"><?= htmlspecialchars($item['title']) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <div class="flex items-center gap-3">
                <?php if ($isLoggedIn): ?>
                    <!-- Mesajlar -->
                    <a href="<?= $pathPrefix ?>messages.php" class="relative p-2 text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent transition-colors" title="Mesajlarım">
                        <span class="material-symbols-outlined text-2xl">mail</span>
                        <?php if (isset($unreadCount) && $unreadCount > 0): ?>
                            <span class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold flex items-center justify-center rounded-full border-2 border-white dark:border-slate-900"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </a>
                    <!-- Bildirimler -->
                    <div class="relative group mr-2">
                        <button class="relative p-2 text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent transition-colors">
                            <span class="material-symbols-outlined text-2xl">notifications</span>
                            <?php if (isset($unreadNotificationCount) && $unreadNotificationCount > 0): ?>
                                <span class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold flex items-center justify-center rounded-full border-2 border-white dark:border-slate-900 notification-badge"><?= $unreadNotificationCount ?></span>
                            <?php endif; ?>
                        </button>
                        
                        <!-- Dropdown -->
                        <div class="absolute right-0 top-full pt-2 w-80 hidden group-hover:block z-50">
                            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-100 dark:border-slate-700 overflow-hidden">
                                <div class="p-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 flex justify-between items-center">
                                    <h4 class="font-bold text-slate-800 dark:text-white text-sm">Bildirimler</h4>
                                    <?php if (isset($unreadNotificationCount) && $unreadNotificationCount > 0): ?>
                                        <span class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold"><?= $unreadNotificationCount ?> Yeni</span>
                                    <?php endif; ?>
                                </div>
                                <div class="max-h-[300px] overflow-y-auto">
                                    <?php if (empty($notifications)): ?>
                                        <div class="p-6 text-center text-slate-500 text-xs">
                                            <span class="material-symbols-outlined text-3xl mb-2 opacity-50">notifications_off</span>
                                            <p>Henüz bildiriminiz yok.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach (array_slice($notifications, 0, 3) as $notif): ?>
                                            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'provider'): ?>
                                                <!-- Hizmet Veren Bildirimi -->
                                                <?php 
                                                    $targetUrl = ($notif['type'] === 'offer_accepted') ? "offer-details.php?id=" . $notif['ref_id'] : "demand-details.php?id=" . $notif['ref_id'];
                                                    $icon = ($notif['type'] === 'offer_accepted') ? 'check_circle' : 'work';
                                                    $iconBg = ($notif['type'] === 'offer_accepted') ? 'bg-green-100 text-green-600' : 'bg-blue-100 text-blue-600';
                                                ?>
                                                <a href="<?= $pathPrefix . $targetUrl ?>" onclick="markAsRead(<?= $notif['ref_id'] ?>, '<?= $notif['type'] ?>', this)" class="block p-3 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors border-b border-slate-50 dark:border-slate-700 last:border-0 bg-blue-50/30 dark:bg-blue-900/10">
                                                    <div class="flex gap-3">
                                                        <div class="w-10 h-10 rounded-full <?= $iconBg ?> flex items-center justify-center shrink-0">
                                                            <span class="material-symbols-outlined text-lg"><?= $icon ?></span>
                                                        </div>
                                                        <div>
                                                            <p class="text-xs text-slate-800 dark:text-slate-200 line-clamp-2">
                                                                <?php if ($notif['type'] === 'offer_accepted'): ?>
                                                                    <span class="font-bold">Tebrikler!</span> "<?= htmlspecialchars($notif['title']) ?>" için teklifiniz kabul edildi.
                                                                <?php else: ?>
                                                                    <span class="font-bold">Yeni İş Fırsatı:</span> <?= htmlspecialchars($notif['title']) ?>
                                                                <?php endif; ?>
                                                            </p>
                                                            <span class="text-[10px] text-slate-400 mt-1 block"><?= date('d.m H:i', strtotime($notif['created_at'])) ?></span>
                                                        </div>
                                                    </div>
                                                </a>
                                            <?php else: ?>
                                                <!-- Müşteri Bildirimi -->
                                                <a href="<?= $pathPrefix ?>demand-details.php?id=<?= $notif['demand_id'] ?>" onclick="markAsRead(<?= $notif['offer_id'] ?>, 'customer_offer', this)" class="block p-3 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors border-b border-slate-50 dark:border-slate-700 last:border-0 <?= $notif['is_read'] ? '' : 'bg-blue-50/30 dark:bg-blue-900/10' ?>">
                                                    <div class="flex gap-3">
                                                        <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                                                            <span class="material-symbols-outlined text-lg">local_offer</span>
                                                        </div>
                                                        <div>
                                                            <p class="text-xs text-slate-800 dark:text-slate-200 line-clamp-2">
                                                                <span class="font-bold"><?= htmlspecialchars($notif['business_name'] ?: $notif['first_name'] . ' ' . $notif['last_name']) ?></span>, 
                                                                <span class="font-medium text-slate-600 dark:text-slate-400">"<?= htmlspecialchars($notif['demand_title']) ?>"</span> talebinize teklif verdi.
                                                            </p>
                                                            <span class="text-[10px] text-slate-400 mt-1 block"><?= date('d.m.Y H:i', strtotime($notif['created_at'])) ?></span>
                                                        </div>
                                                    </div>
                                                </a>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <?php 
                                    $allLink = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'provider') ? 'provider/leads.php' : 'my-demands.php';
                                ?>
                                <a href="<?= $pathPrefix . $allLink ?>" class="block p-3 text-center text-xs font-bold text-primary hover:text-accent hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors border-t border-slate-100 dark:border-slate-700">
                                    Tüm Bildirimleri Gör
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="relative group">
                        <button class="flex items-center gap-2 text-sm font-bold text-slate-700 dark:text-slate-300 hover:text-primary dark:hover:text-accent transition-colors py-2">
                            <?php if (!empty($_SESSION['user_avatar'])): ?>
                                <img src="<?= htmlspecialchars($_SESSION['user_avatar']) ?>" alt="<?= htmlspecialchars($userName) ?>" class="w-8 h-8 rounded-full object-cover border border-slate-200">
                            <?php else: ?>
                                <span class="material-symbols-outlined text-2xl">account_circle</span>
                            <?php endif; ?>
                            <span><?= htmlspecialchars($userName) ?></span>
                            <span class="material-symbols-outlined text-sm">expand_more</span>
                        </button>
                        <!-- Dropdown -->
                        <div class="absolute right-0 top-full pt-2 w-48 hidden group-hover:block z-50">
                            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-100 dark:border-slate-700 overflow-hidden">
                            <ul class="py-2">
                                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'provider'): ?>
                                    <li><a href="<?= $pathPrefix ?>provider/won-jobs.php" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Kazandığım İşler</a></li>
                                <?php endif; ?>
                                <li><a href="<?= $pathPrefix ?>profile.php" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Profilim</a></li>
                                <li><a href="<?= $pathPrefix ?>logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">Çıkış Yap</a></li>
                            </ul>
                            </div>
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

        // Bildirimi okundu olarak işaretle
        window.markAsRead = function(id, type, element) {
            // UI'ı hemen güncelle (Optimistic UI)
            if (element) {
                element.classList.remove('bg-blue-50/30', 'dark:bg-blue-900/10');
            }
            
            // Badge sayısını düşür
            const badge = document.querySelector('.notification-badge');
            if (badge) {
                let count = parseInt(badge.innerText);
                if (count > 1) {
                    badge.innerText = count - 1;
                } else {
                    badge.remove();
                }
            }

            // AJAX isteği
            const formData = new FormData();
            formData.append('id', id);
            formData.append('type', type);

            fetch('<?= $pathPrefix ?>ajax/mark-notification-read.php', {
                method: 'POST',
                body: formData
            }).catch(err => console.error('Bildirim işaretlenemedi:', err));
        };

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

            // Header Konum Seç Butonu
            const headerLocBtn = document.getElementById('header-location-btn');
            if (headerLocBtn) {
                headerLocBtn.addEventListener('click', () => {
                    if (!navigator.geolocation) {
                        alert('Tarayıcınız konum servisini desteklemiyor.');
                        return;
                    }

                    const locText = document.getElementById('header-location-text');
                    const originalText = locText.textContent;
                    locText.textContent = 'Alınıyor...';

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
                                        
                                        // İl/İlçe parse et
                                        let city = "";
                                        for (const component of place.address_components) {
                                            if (component.types.includes("administrative_area_level_1")) {
                                                city = component.long_name;
                                            }
                                        }
                                        const newLocation = city || "Konum Seçildi";
                                        
                                        // Header ve Cookie Güncelle
                                        locText.textContent = newLocation;
                                        document.cookie = "user_location=" + encodeURIComponent(newLocation) + "; path=/; max-age=" + (60*60*24*30); // 30 gün

                                        // Veritabanına Kaydet (AJAX)
                                        const formData = new FormData();
                                        formData.append('address', place.formatted_address);
                                        formData.append('lat', lat);
                                        formData.append('lng', lng);
                                        formData.append('city', city);
                                        // district bilgisini burada tam alamıyoruz ama backend null check yapıyor
                                        
                                        fetch('<?= $pathPrefix ?>ajax/update-user-location.php', { method: 'POST', body: formData })
                                            .catch(err => console.error('Konum kaydedilemedi:', err));

                                        // Anasayfadaki arama kutusunu doldur (Eğer varsa)
                                        const searchInput = document.getElementById("google-location-search");
                                        if (searchInput) {
                                            searchInput.value = place.formatted_address;
                                            document.getElementById("g-address").value = place.formatted_address;
                                            document.getElementById("g-lat").value = lat;
                                            document.getElementById("g-lng").value = lng;
                                        }
                                    } else {
                                        alert('Adres çözümlenemedi.');
                                        locText.textContent = originalText;
                                    }
                                });
                            }
                        },
                        (error) => {
                            console.warn("Geolocation error:", error);
                            locText.textContent = originalText;
                        }
                    );
                });
            }
        }
    });
</script>