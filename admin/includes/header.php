<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Güvenlik Kontrolü: Giriş yapmamışsa veya rolü admin değilse at
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Veritabanı bağlantısı (Eğer başka bir yerde dahil edilmediyse)
if (!isset($pdo)) {
    require_once dirname(__DIR__) . '/config/db.php';
}

// Bildirim Sayılarını Çek
$counts = [
    'demands' => 0,
    'offers' => 0,
    'users' => 0,
    'reviews' => 0,
    'reports' => 0,
    'transactions' => 0, // payments.php için
    'messages' => 0,
    'support' => 0, // support-requests.php için
    'applications' => 0,
];

if (isset($pdo)) {
    try {
        // Bu sorgular, SQL güncellemesi yapıldıktan sonra çalışacak.
        // Yapılmadıysa, catch bloğu hatayı yakalayıp devam edecek.
        $counts['demands'] = $pdo->query("SELECT COUNT(*) FROM demands WHERE is_read_by_admin = 0")->fetchColumn();
        $counts['offers'] = $pdo->query("SELECT COUNT(*) FROM offers WHERE is_read_by_admin = 0")->fetchColumn();
        $counts['users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE is_read_by_admin = 0 AND role != 'admin'")->fetchColumn();
        $counts['reviews'] = $pdo->query("SELECT COUNT(*) FROM reviews WHERE is_read_by_admin = 0 AND is_approved = 0")->fetchColumn();
        $counts['reports'] = $pdo->query("SELECT COUNT(*) FROM reports WHERE is_read_by_admin = 0")->fetchColumn();
        $counts['transactions'] = $pdo->query("SELECT COUNT(*) FROM transactions WHERE is_read_by_admin = 0 AND status = 'pending'")->fetchColumn();
        $counts['messages'] = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
        $counts['support'] = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE is_read_by_admin = 0 AND status = 'open'")->fetchColumn();
        $counts['applications'] = $pdo->query("SELECT COUNT(*) FROM provider_details WHERE application_status = 'pending'")->fetchColumn();
    } catch (Exception $e) {
        // Tablolar veya sütunlar henüz oluşmadıysa hata vermeden devam et.
        // $counts dizisi varsayılan 0 değerleriyle kalacak.
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);

// Sayfa yüklendiğinde bildirimleri okundu olarak işaretle
if (isset($pdo)) {
    $pageToTypeMap = [
        'demands.php' => 'demands',
        'offers-recent.php' => 'offers',
        'users.php' => 'users',
        'reviews.php' => 'reviews',
        'reports.php' => 'reports',
        'payments.php' => 'transactions',
        'messages.php' => 'messages',
        'support-requests.php' => 'support',
        'applications.php' => 'applications'
    ];

    if (isset($pageToTypeMap[$currentPage])) {
        $typeToUpdate = $pageToTypeMap[$currentPage];
        
        if (isset($counts[$typeToUpdate]) && $counts[$typeToUpdate] > 0) {
            $table = '';
            $column = 'is_read_by_admin';
            $whereClause = "WHERE `$column` = 0";

            switch ($typeToUpdate) {
                case 'demands': $table = 'demands'; break;
                case 'offers': $table = 'offers'; break;
                case 'users': $table = 'users'; $whereClause = "WHERE `is_read_by_admin` = 0 AND `role` != 'admin'"; break;
                case 'reviews': $table = 'reviews'; $whereClause = "WHERE `is_read_by_admin` = 0 AND `is_approved` = 0"; break;
                case 'reports': $table = 'reports'; break;
                case 'transactions': $table = 'transactions'; $whereClause = "WHERE `is_read_by_admin` = 0 AND `status` = 'pending'"; break;
                case 'messages': $table = 'contact_messages'; $column = 'is_read'; break;
                case 'support': $table = 'support_tickets'; $whereClause = "WHERE `is_read_by_admin` = 0 AND `status` = 'open'"; break;
                case 'applications': $table = 'provider_details'; $column = 'application_status'; $whereClause = "WHERE `$column` = 'pending'"; break;
            }

            if ($table) {
                $sql = ($typeToUpdate === 'applications') ? "UPDATE `$table` SET `$column` = 'viewed' $whereClause" : "UPDATE `$table` SET `$column` = 1 $whereClause";
                $pdo->exec($sql);
                $counts[$typeToUpdate] = 0;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli | iyiteklif</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .sidebar-transition { transition: transform 0.3s ease-in-out; }
    </style>
</head>
<body class="h-full bg-slate-50">

    <!-- Mobile Header -->
    <div class="lg:hidden flex items-center justify-between bg-slate-900 border-b border-slate-800 px-4 py-3 sticky top-0 z-30">
        <div class="flex items-center gap-3">
            <button id="mobile-menu-btn" class="text-slate-300 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-3xl">menu</span>
            </button>
            <span class="text-lg font-black tracking-tight text-white">iyiteklif<span class="text-indigo-500">.admin</span></span>
        </div>
        <div class="relative">
             <div class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center font-bold text-sm">
                <?= substr($_SESSION['user_name'], 0, 1) ?>
            </div>
            <?php if(array_sum($counts) > 0): ?>
                <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full border-2 border-slate-900"></span>
            <?php endif; ?>
        </div>
    </div>

<div class="flex h-screen">

    <!-- Sidebar Overlay (Mobile) -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/50 z-40 hidden lg:hidden backdrop-blur-sm transition-opacity duration-300 opacity-0"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-50 w-64 bg-slate-900 text-white flex flex-col flex-shrink-0 sidebar-transition transform -translate-x-full lg:translate-x-0">
        <div class="h-16 flex items-center px-6 border-b border-slate-800">
            <span class="text-xl font-black tracking-tight text-white">iyiteklif<span class="text-indigo-500">.admin</span></span>
        </div>
        
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            <a href="index.php" class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'index.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <div class="flex items-center gap-3 truncate">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span>Dashboard</span>
                </div>
            </a>
            
            <!-- Hizmet Yönetimi -->
            <div class="pt-4 pb-2 px-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Hizmet Yönetimi</div>
            
            <a href="locations.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-xl">map</span>
                <span class="font-medium">Lokasyonlar</span>
            </a>
            <a href="categories.php" class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'categor') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <div class="flex items-center gap-3 truncate">
                    <span class="material-symbols-outlined">category</span>
                    <span>Kategoriler</span>
                </div>
            </a>
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors text-slate-400 hover:bg-slate-800 hover:text-white">
                <span class="material-symbols-outlined">subdirectory_arrow_right</span>
                Alt Kategoriler
            </a>
            <a href="questions.php" class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'question') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <div class="flex items-center gap-3 truncate">
                    <span class="material-symbols-outlined">quiz</span>
                    <span>Dinamik Sorular</span>
                </div>
            </a>
            
            <!-- Talep & Teklifler -->
            <div class="pt-4 pb-2 px-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Talep & Teklifler</div>

            <a href="demands.php" class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'demands.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <div class="flex items-center gap-3 truncate">
                    <span class="material-symbols-outlined">format_list_bulleted</span>
                    <span>Tüm Talepler</span>
                </div>
                <?php if ($counts['demands'] > 0): ?>
                    <span id="badge-demands" class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full min-w-[20px] text-center"><?= $counts['demands'] ?></span>
                <?php endif; ?>
            </a>
            <a href="demands-by-category.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'demands-by-category.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">filter_list</span>
                Kategoriye Göre
            </a>
            <a href="offers-recent.php" class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'offers-recent.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <div class="flex items-center gap-3 truncate">
                    <span class="material-symbols-outlined">local_offer</span>
                    <span>Güncel Teklifler</span>
                </div>
                <?php if ($counts['offers'] > 0): ?>
                    <span id="badge-offers" class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full min-w-[20px] text-center"><?= $counts['offers'] ?></span>
                <?php endif; ?>
            </a>
            <a href="demands-pending.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'demands-pending.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">pending_actions</span>
                Cevap Bekleyenler
            </a>
            <a href="demands-archived.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'demands-archived.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">archive</span>
                Arşivli Talepler
            </a>
            <a href="reports.php" class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'reports.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <div class="flex items-center gap-3 truncate">
                    <span class="material-symbols-outlined">report_problem</span>
                    <span>Sorun Bildirilenler</span>
                </div>
                 <?php if ($counts['reports'] > 0): ?>
                    <span id="badge-reports" class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full min-w-[20px] text-center"><?= $counts['reports'] ?></span>
                <?php endif; ?>
            </a>

            <!-- Kullanıcılar -->
            <div class="pt-4 pb-2 px-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Kullanıcılar</div>

            <a href="users.php" class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'user') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <div class="flex items-center gap-3 truncate">
                    <span class="material-symbols-outlined">group</span>
                    <span>Tüm Kullanıcılar</span>
                </div>
                <?php if ($counts['users'] > 0): ?>
                    <span id="badge-users" class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full min-w-[20px] text-center"><?= $counts['users'] ?></span>
                <?php endif; ?>
            </a>
            <a href="providers.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'provider') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">engineering</span>
                Hizmet Verenler
            </a>
            <a href="applications.php" class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'applications.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <div class="flex items-center gap-3 truncate">
                    <span class="material-symbols-outlined">person_add</span>
                    <span>Başvurular</span>
                </div>
                <?php if ($counts['applications'] > 0): ?>
                    <span id="badge-applications" class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full min-w-[20px] text-center"><?= $counts['applications'] ?></span>
                <?php endif; ?>
            </a>
            <a href="reviews.php" class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'reviews.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <div class="flex items-center gap-3 truncate">
                    <span class="material-symbols-outlined">reviews</span>
                    <span>Yorum Yönetimi</span>
                </div>
                <?php if ($counts['reviews'] > 0): ?>
                    <span id="badge-reviews" class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full min-w-[20px] text-center"><?= $counts['reviews'] ?></span>
                <?php endif; ?>
            </a>
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors text-slate-400 hover:bg-slate-800 hover:text-white">
                <span class="material-symbols-outlined">admin_panel_settings</span>
                Yöneticiler
            </a>

            <!-- Finans & Abonelik -->
            <div class="pt-4 pb-2 px-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Finans & Abonelik</div>

            <a href="subscriptions.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'subscription') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">card_membership</span>
                Abonelikler
            </a>
            <a href="payment-settings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'payment-settings.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">payments</span>
                Ödeme Ayarları
            </a>
            <a href="payments.php" class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'payments.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <div class="flex items-center gap-3 truncate">
                    <span class="material-symbols-outlined">receipt_long</span>
                    <span>Ödeme Geçmişi</span>
                </div>
                <?php if ($counts['transactions'] > 0): ?>
                    <span id="badge-transactions" class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full min-w-[20px] text-center"><?= $counts['transactions'] ?></span>
                <?php endif; ?>
            </a>

            <!-- İletişim -->
            <div class="pt-4 pb-2 px-3 text-xs font-bold text-slate-500 uppercase tracking-wider">İletişim</div>

            <a href="messages.php" class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'messages.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <div class="flex items-center gap-3 truncate">
                    <span class="material-symbols-outlined">mail</span>
                    <span>Mesajlar</span>
                </div>
                <?php if ($counts['messages'] > 0): ?>
                    <span id="badge-messages" class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full min-w-[20px] text-center"><?= $counts['messages'] ?></span>
                <?php endif; ?>
            </a>
            <a href="message-templates.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'message-templates.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">library_books</span>
                Mesaj Şablonları
            </a>
            <a href="support-requests.php" class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'support') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <div class="flex items-center gap-3 truncate">
                    <span class="material-symbols-outlined">support_agent</span>
                    <span>Destek Talepleri</span>
                </div>
                <?php if ($counts['support'] > 0): ?>
                    <span id="badge-support" class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full min-w-[20px] text-center"><?= $counts['support'] ?></span>
                <?php endif; ?>
            </a>
            <a href="announcements.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'announcements') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">campaign</span>
                Duyurular
            </a>

            <!-- Site Ayarları -->
            <div class="pt-4 pb-2 px-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Site Ayarları</div>

            <a href="settings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'settings.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">settings</span>
                Genel Ayarlar
            </a>
            <a href="file-manager.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'file-manager.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">folder</span>
                Dosya Yöneticisi
            </a>
            <a href="social-login-settings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'social-login-settings.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">login</span>
                Sosyal Giriş
            </a>
            <a href="menus.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'menu') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">menu</span>
                Menü Yönetimi
            </a>
            <a href="pages.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'page') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">article</span>
                Sayfa Yönetimi
            </a>
            <a href="email-templates.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'email-template') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">mark_email_unread</span>
                Mail Servisleri
            </a>
            <a href="social-media.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'social-media') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">share</span>
                Sosyal Medya
            </a>
            <a href="appearance.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'appearance') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">palette</span>
                Görünüm & CSS
            </a>
            <a href="seo-settings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'seo-settings') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">search</span>
                SEO Ayarları
            </a>
            <a href="system-logs.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'system-logs') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">terminal</span>
                Sistem Günlükleri
            </a>
            <a href="backup.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'backup') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">backup</span>
                Veritabanı Yedekleme
            </a>
            <a href="repair-db.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'repair-db') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">build</span>
                Veritabanı Onar
            </a>
        </nav>

        <div class="p-4 border-t border-slate-800">
            <a href="../logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-red-400 hover:bg-red-500/10 hover:text-red-300 transition-colors">
                <span class="material-symbols-outlined">logout</span>
                Çıkış Yap
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0 bg-slate-50">
        <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-8 sticky top-0 lg:top-auto z-20">
            <!-- Mobile Menu Button (visible on small screens) -->
            <button id="main-content-menu-btn" class="lg:hidden text-slate-600 hover:text-indigo-600 transition-colors -ml-2">
                <span class="material-symbols-outlined text-3xl">menu</span>
            </button>
            
            <h1 class="text-lg font-bold text-slate-800 hidden lg:block">Yönetim Paneli</h1>

            <div class="flex items-center gap-4">
                <span class="text-sm font-medium text-slate-600"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                <div class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center font-bold text-sm">
                    <?= substr($_SESSION['user_name'], 0, 1) ?>
                </div>
            </div>
        </header>
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">

<script>
    // Hamburger Menu Logic
    const mobileBtn = document.getElementById('mobile-menu-btn');
    const mainContentBtn = document.getElementById('main-content-menu-btn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    let isSidebarOpen = false;

    function toggleSidebar() {
        isSidebarOpen = !isSidebarOpen;
        if (isSidebarOpen) {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            // Fade in effect
            setTimeout(() => overlay.classList.remove('opacity-0'), 10);
        } else {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('opacity-0');
            // Hide after fade out
            setTimeout(() => overlay.classList.add('hidden'), 300);
        }
    }

    if(mobileBtn) mobileBtn.addEventListener('click', toggleSidebar);
    if(mainContentBtn) mainContentBtn.addEventListener('click', toggleSidebar);
    if(overlay) overlay.addEventListener('click', toggleSidebar);

</script>