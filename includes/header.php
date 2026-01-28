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
        $stmtR = $pdo->prepare("SELECT id, first_name, last_name, password, role FROM users WHERE id = ?");
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
    $stmt = $pdo->prepare("SELECT role, first_name, last_name, avatar_url, email, phone, address_text, city, district FROM users WHERE id = ?");
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
// Cache kontrolü
$siteSettings = $cache->get('site_settings');

if ($siteSettings === null) {
    if (isset($pdo)) {
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $stmt->fetch()) {
                $siteSettings[$row['setting_key']] = $row['setting_value'];
            }
            $cache->set('site_settings', $siteSettings, 3600); // 1 saat cache
        } catch (Exception $e) {
            // Hata durumunda varsayılanlar
        }
    }
}
$siteTitle = $siteSettings['site_title'] ?? 'iyiteklif';
if (!isset($siteDescription)) {
    $siteDescription = $siteSettings['site_description'] ?? 'Aradığın Hizmeti Bul';
}
$siteKeywords = $siteSettings['site_keywords'] ?? '';
$siteFavicon = $siteSettings['site_favicon'] ?? '';

// Menüleri Çek
$headerMenuItems = [];
$megaMenuTree = [];

// Cache kontrolü
$cachedMenus = $cache->get('site_menus');

if ($cachedMenus !== null) {
    $headerMenuItems = $cachedMenus['header'];
    $megaMenuTree = $cachedMenus['mega'];
} else {
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT * FROM menu_items WHERE menu_location = 'header' AND is_active = 1 ORDER BY sort_order ASC");
        $headerMenuItems = $stmt->fetchAll();
        
        // Mega Menü Öğelerini Çek
        $megaMenuItemsRaw = $pdo->query("SELECT * FROM menu_items WHERE menu_location = 'mega_menu' AND is_active = 1 ORDER BY sort_order ASC")->fetchAll();
        
        // Önce ebeveynlerin işlendiğinden emin olmak için sıralama yapıyoruz
        usort($megaMenuItemsRaw, function($a, $b) {
            // Parent ID'si olmayanlar (Ana kategoriler) önce gelsin
            $aIsParent = empty($a['parent_id']);
            $bIsParent = empty($b['parent_id']);
            
            if ($aIsParent && !$bIsParent) return -1;
            if (!$aIsParent && $bIsParent) return 1;
            
            return $a['sort_order'] <=> $b['sort_order'];
        });

        foreach ($megaMenuItemsRaw as $item) {
            if (empty($item['parent_id'])) {
                $megaMenuTree[$item['id']] = $item;
                $megaMenuTree[$item['id']]['children'] = [];
            } else {
                $megaMenuTree[$item['parent_id']]['children'][] = $item;
            }
        }
        
        $cache->set('site_menus', ['header' => $headerMenuItems, 'mega' => $megaMenuTree], 86400); // 24 saat cache
    }
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
    <link rel="manifest" href="<?= $pathPrefix ?>manifest.php">
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

    <!-- Google Search Console -->
    <?php if (!empty($siteSettings['google_search_console_meta'])): ?>
        <?= $siteSettings['google_search_console_meta'] ?>
    <?php endif; ?>

    <!-- Google Tag Manager -->
    <?php if (!empty($siteSettings['google_tag_manager_id'])): ?>
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?= htmlspecialchars($siteSettings['google_tag_manager_id']) ?>');</script>
    <?php endif; ?>

    <!-- Google Analytics 4 & Google Ads (gtag.js) -->
    <?php 
    $gaId = $siteSettings['google_analytics_id'] ?? '';
    $adsId = $siteSettings['google_ads_id'] ?? '';
    $mainId = $gaId ?: $adsId;
    
    if ($mainId): 
    ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($mainId) ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      <?php 
      $userProps = [
          'user_type' => $isLoggedIn ? ($_SESSION['user_role'] ?? 'customer') : 'guest',
          'logged_in' => $isLoggedIn ? 'true' : 'false'
      ];
      ?>

      <?php if ($gaId): ?>
      gtag('config', '<?= htmlspecialchars($gaId) ?>', {
          <?php if ($isLoggedIn): ?>'user_id': '<?= $_SESSION['user_id'] ?>',<?php endif; ?>
          'user_properties': <?= json_encode($userProps) ?>
      });
      <?php endif; ?>
      
      <?php if ($adsId): ?>gtag('config', '<?= htmlspecialchars($adsId) ?>');<?php endif; ?>

      // GTM Gelişmiş Dönüşümler İçin Kullanıcı Verileri
      window.currentUserData = {
        <?php if ($isLoggedIn && isset($currentUser)): ?>
          email: "<?= htmlspecialchars($currentUser['email'] ?? '') ?>",
          phone_number: "<?= htmlspecialchars($currentUser['phone'] ?? '') ?>",
          first_name: "<?= htmlspecialchars($currentUser['first_name'] ?? '') ?>",
          last_name: "<?= htmlspecialchars($currentUser['last_name'] ?? '') ?>",
          street: "<?= htmlspecialchars($currentUser['address_text'] ?? '') ?>",
          city: "<?= htmlspecialchars($currentUser['city'] ?? '') ?>",
          region: "<?= htmlspecialchars($currentUser['district'] ?? '') ?>",
          country: "TR"
        <?php else: ?>
          country: "TR" // Giriş yapmamışsa varsayılan ülke
        <?php endif; ?>
      };

      // --- Google Ads Dinamik Yeniden Pazarlama (Remarketing) ---
      <?php if (isset($category) && !empty($category['id'])): ?>
        // Kategori Sayfası (Hizmet Detay)
        gtag('event', 'view_item', {
            'items': [{
                'id': '<?= $category['id'] ?>',
                'name': <?= json_encode($category['name']) ?>,
                'category': 'Hizmetler'
            }],
            'dynx_itemid': '<?= $category['id'] ?>',
            'dynx_pagetype': 'offerdetail',
            'dynx_totalvalue': 0
        });
        
        // GTM için dataLayer push (Mevcut yapıyı koruyoruz)
        dataLayer.push({
            'event': 'view_hizmet_kategori',
            'kategori_adi': <?= json_encode($category['name']) ?>,
            'kategori_id': <?= json_encode($category['id']) ?>
        });

      <?php elseif (basename($_SERVER['PHP_SELF']) == 'index.php'): ?>
        // Anasayfa
        gtag('event', 'page_view', {
            'dynx_pagetype': 'home'
        });
      <?php else: ?>
        // Diğer Sayfalar
        gtag('event', 'page_view', {
            'dynx_pagetype': 'other'
        });
      <?php endif; ?>

      <?php 
      // Session tabanlı tek seferlik olaylar (Login, Sign Up vb.)
      if (isset($_SESSION['ga_event'])) {
          echo "gtag('event', '" . $_SESSION['ga_event']['name'] . "', " . json_encode($_SESSION['ga_event']['params']) . ");";
          unset($_SESSION['ga_event']);
      }
      ?>
    </script>
    <?php endif; ?>

    <?php if (isset($category) && !empty($category['tracking_code_head'])): ?>
        <?= $category['tracking_code_head'] ?>
    <?php endif; ?>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 transition-colors duration-200">
    <!-- Google Tag Manager (noscript) -->
    <?php if (!empty($siteSettings['google_tag_manager_id'])): ?>
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= htmlspecialchars($siteSettings['google_tag_manager_id']) ?>"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <?php endif; ?>

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
                            
                            <!-- Dinamik Mega Menü -->
                            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-10">
                                <?php foreach ($megaMenuTree as $parent): ?>
                                <div>
                                    <div class="flex items-center gap-2 mb-6 text-primary dark:text-accent">
                                        <?php if ($parent['icon']): ?>
                                            <span class="material-symbols-outlined"><?= htmlspecialchars($parent['icon']) ?></span>
                                        <?php endif; ?>
                                        <h4 class="font-black text-lg uppercase tracking-tight"><?= htmlspecialchars($parent['title']) ?></h4>
                                    </div>
                                    <ul class="space-y-4">
                                        <?php foreach ($parent['children'] as $child): ?>
                                            <li><a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent font-medium flex items-center justify-between group" href="<?= htmlspecialchars($child['url']) ?>" target="<?= htmlspecialchars($child['target']) ?>"><?= htmlspecialchars($child['title']) ?> <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endforeach; ?>
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

                        // Aktif sayfa kontrolü
                        $isActive = false;
                        if (!empty($item['url'])) {
                            $currentScript = basename($_SERVER['PHP_SELF']);
                            $menuScript = basename(parse_url($item['url'], PHP_URL_PATH));
                            if ($currentScript === $menuScript) {
                                $isActive = true;
                            }
                        }
                    ?>
                    <?php if ($show): ?>
                        <a class="text-sm font-bold <?= $isActive ? 'text-primary dark:text-accent border-[#1a2a6b]' : 'text-slate-700 dark:text-slate-300 border-transparent' ?> hover:text-primary dark:hover:text-accent transition-all border-b-2 hover:border-[#1a2a6b]" href="<?= $pathPrefix . htmlspecialchars($item['url']) ?>" target="<?= htmlspecialchars($item['target']) ?>"><?= htmlspecialchars($item['title']) ?></a>
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
                    <div class="relative mr-2">
                        <button onclick="toggleDropdown(event, 'notification-dropdown')" class="relative p-2 text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-accent transition-colors">
                            <span class="material-symbols-outlined text-2xl">notifications</span>
                            <?php if (isset($unreadNotificationCount) && $unreadNotificationCount > 0): ?>
                                <span class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold flex items-center justify-center rounded-full border-2 border-white dark:border-slate-900 notification-badge"><?= $unreadNotificationCount ?></span>
                            <?php endif; ?>
                        </button>
                        
                        <!-- Dropdown -->
                        <div id="notification-dropdown" class="absolute right-0 top-full pt-2 w-80 hidden z-50">
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

                    <div class="relative">
                        <button onclick="toggleDropdown(event, 'profile-dropdown')" class="flex items-center gap-2 text-sm font-bold text-slate-700 dark:text-slate-300 hover:text-primary dark:hover:text-accent transition-colors py-2">
                            <?php if (!empty($_SESSION['user_avatar'])): ?>
                                <?php 
                                    $avatarSrc = $_SESSION['user_avatar'];
                                    if (!filter_var($avatarSrc, FILTER_VALIDATE_URL)) {
                                        $avatarSrc = $pathPrefix . $avatarSrc;
                                    }
                                ?>
                                <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="<?= htmlspecialchars($userName) ?>" class="w-8 h-8 rounded-full object-cover border border-slate-200">
                            <?php else: ?>
                                <span class="material-symbols-outlined text-2xl">account_circle</span>
                            <?php endif; ?>
                            <span class="hidden md:inline"><?= htmlspecialchars($userName) ?></span>
                            <span class="material-symbols-outlined text-sm hidden md:inline">expand_more</span>
                        </button>
                        <!-- Dropdown -->
                        <div id="profile-dropdown" class="absolute right-0 top-full pt-2 w-48 hidden z-50">
                            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-100 dark:border-slate-700 overflow-hidden">
                            <ul class="py-2">
                                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'provider'): ?>
                                    <li><a href="<?= $pathPrefix ?>provider/won-jobs.php" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Kazandığım İşler</a></li>
                                <?php else: ?>
                                    <li>
                                        <a href="<?= $pathPrefix ?>my-demands.php" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                                            <span class="material-symbols-outlined text-lg">format_list_bulleted</span>
                                            Tekliflerim
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <li>
                                    <a href="<?= $pathPrefix ?>profile.php" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                                        <span class="material-symbols-outlined text-lg">person</span>
                                        Profilim
                                    </a>
                                </li>
                                <li>
                                    <a href="<?= $pathPrefix ?>logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                                        <span class="material-symbols-outlined text-lg">logout</span>
                                        Çıkış Yap
                                    </a>
                                </li>
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

    // Dropdown Toggle Logic
    window.toggleDropdown = function(event, dropdownId) {
        event.stopPropagation();
        const dropdown = document.getElementById(dropdownId);
        const isHidden = dropdown.classList.contains('hidden');
        
        // Tüm dropdownları kapat
        document.querySelectorAll('[id$="-dropdown"]').forEach(el => el.classList.add('hidden'));
        
        // Tıklananı aç/kapat
        if (isHidden) {
            dropdown.classList.remove('hidden');
        }
    };

    // Dışarı tıklayınca dropdownları kapat
    document.addEventListener('click', () => {
        document.querySelectorAll('[id$="-dropdown"]').forEach(el => el.classList.add('hidden'));
    });

    // Sekmeler Arası Oturum Senkronizasyonu
    window.addEventListener('storage', function(event) {
        if (event.key === 'login_status') {
            // Başka bir sekmede oturum durumu değiştiyse sayfayı yenile
            window.location.reload();
        }
    });

    // Mevcut oturum durumunu localStorage'a yaz
    <?php if ($isLoggedIn): ?>
        if (!localStorage.getItem('login_status')) {
            localStorage.setItem('login_status', '<?= time() ?>');
        }
    <?php else: ?>
        if (localStorage.getItem('login_status')) {
            localStorage.removeItem('login_status');
        }
    <?php endif; ?>
</script>