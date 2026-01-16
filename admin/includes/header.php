<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Güvenlik Kontrolü: Giriş yapmamışsa veya rolü admin değilse at
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
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
    </style>
</head>
<body class="h-full overflow-hidden flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 text-white flex flex-col flex-shrink-0 transition-all duration-300">
        <div class="h-16 flex items-center px-6 border-b border-slate-800">
            <span class="text-xl font-black tracking-tight text-white">iyiteklif<span class="text-indigo-500">.admin</span></span>
        </div>
        
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            <a href="index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'index.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">dashboard</span>
                Dashboard
            </a>
            
            <!-- Hizmet Yönetimi -->
            <div class="pt-4 pb-2 px-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Hizmet Yönetimi</div>
            
            <a href="categories.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'categor') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">category</span>
                Kategoriler
            </a>
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors text-slate-400 hover:bg-slate-800 hover:text-white">
                <span class="material-symbols-outlined">subdirectory_arrow_right</span>
                Alt Kategoriler
            </a>
            <a href="questions.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'question') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">quiz</span>
                Dinamik Sorular
            </a>
            
            <!-- Talep & Teklifler -->
            <div class="pt-4 pb-2 px-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Talep & Teklifler</div>

            <a href="demands.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'demands.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">format_list_bulleted</span>
                Tüm Talepler
            </a>
            <a href="demands-by-category.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'demands-by-category.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">filter_list</span>
                Kategoriye Göre
            </a>
            <a href="offers-recent.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'offers-recent.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">local_offer</span>
                Güncel Teklifler
            </a>
            <a href="demands-pending.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'demands-pending.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">pending_actions</span>
                Cevap Bekleyenler
            </a>
            <a href="demands-archived.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'demands-archived.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">archive</span>
                Arşivli Talepler
            </a>
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors text-slate-400 hover:bg-slate-800 hover:text-white">
                <span class="material-symbols-outlined">report_problem</span>
                Sorun Bildirilenler
            </a>

            <!-- Kullanıcılar -->
            <div class="pt-4 pb-2 px-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Kullanıcılar</div>

            <a href="users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'user') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">group</span>
                Tüm Kullanıcılar
            </a>
            <a href="providers.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'provider') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">engineering</span>
                Hizmet Verenler
            </a>
            <a href="applications.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'applications.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">person_add</span>
                Başvurular
            </a>
            <a href="reviews.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'reviews.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">reviews</span>
                Yorum Yönetimi
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
            <a href="payments.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'payments.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">receipt_long</span>
                Ödeme Geçmişi
            </a>

            <!-- İletişim -->
            <div class="pt-4 pb-2 px-3 text-xs font-bold text-slate-500 uppercase tracking-wider">İletişim</div>

            <a href="messages.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'messages.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">mail</span>
                Mesajlar
            </a>
            <a href="message-templates.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'message-templates.php' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">library_books</span>
                Mesaj Şablonları
            </a>
            <a href="support-requests.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= strpos($currentPage, 'support') !== false ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                <span class="material-symbols-outlined">support_agent</span>
                Destek Talepleri
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
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors text-slate-400 hover:bg-slate-800 hover:text-white">
                <span class="material-symbols-outlined">mark_email_unread</span>
                Mail Servisleri
            </a>
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors text-slate-400 hover:bg-slate-800 hover:text-white">
                <span class="material-symbols-outlined">share</span>
                Sosyal Medya
            </a>
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors text-slate-400 hover:bg-slate-800 hover:text-white">
                <span class="material-symbols-outlined">palette</span>
                Görünüm & CSS
            </a>
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors text-slate-400 hover:bg-slate-800 hover:text-white">
                <span class="material-symbols-outlined">search</span>
                SEO Ayarları
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
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50">
        <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-8">
            <h1 class="text-lg font-bold text-slate-800">Yönetim Paneli</h1>
            <div class="flex items-center gap-4">
                <span class="text-sm font-medium text-slate-600"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                <div class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center font-bold text-sm">
                    <?= substr($_SESSION['user_name'], 0, 1) ?>
                </div>
            </div>
        </header>
        <main class="flex-1 overflow-y-auto p-8">