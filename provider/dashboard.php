<?php
require_once '../config/db.php';
session_start();

// Yetki Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'provider') {
    header("Location: ../login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Kullanıcı ve Provider Detaylarını Çek
$stmt = $pdo->prepare("
    SELECT u.*, pd.* 
    FROM users u 
    LEFT JOIN provider_details pd ON u.id = pd.user_id 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Profil Güncelleme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $businessName = $_POST['business_name'] ?? '';
    $bio = $_POST['bio'] ?? '';
    
    // Provider detaylarını güncelle
    $stmt = $pdo->prepare("UPDATE provider_details SET business_name = ?, bio = ? WHERE user_id = ?");
    $stmt->execute([$businessName, $bio, $userId]);
    
    // Sayfayı yenile ve başarı mesajı göster (Basit refresh)
    header("Location: dashboard.php?status=success");
    exit;
}

$pageTitle = "Panelim";
$pathPrefix = '../';
require_once '../includes/header.php';
?>

<style>
    .active-tab { background-color: #fbbd2320; border-left: 4px solid #fbbd23; color: #1a2a6c; }
</style>

<main class="max-w-[1440px] mx-auto px-6 py-8 flex flex-col lg:flex-row gap-8 min-h-[80vh]">
    <!-- Left Sidebar Navigation -->
    <aside class="w-full lg:w-64 flex-shrink-0">
        <div class="flex flex-col gap-6 sticky top-24">
            <div class="flex flex-col">
                <h1 class="text-primary dark:text-white text-lg font-bold">Profil Yönetimi</h1>
                <p class="text-slate-500 text-xs mt-1">Bilgilerinizi güncel tutarak daha fazla iş alın.</p>
            </div>
            <div class="flex flex-col gap-1">
                <a class="active-tab flex items-center gap-3 px-4 py-3 rounded-lg font-semibold text-sm transition-colors" href="dashboard.php">
                    <span class="material-symbols-outlined">person</span>
                    Genel Bilgiler
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors font-medium text-sm dark:text-slate-400 dark:hover:bg-slate-800" href="leads.php">
                    <span class="material-symbols-outlined">work</span>
                    İş Fırsatları
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors font-medium text-sm dark:text-slate-400 dark:hover:bg-slate-800" href="buy-package.php">
                    <span class="material-symbols-outlined">card_membership</span>
                    Paket & Abonelik
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors font-medium text-sm dark:text-slate-400 dark:hover:bg-slate-800" href="templates.php">
                    <span class="material-symbols-outlined">library_books</span>
                    Mesaj Şablonları
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors font-medium text-sm dark:text-slate-400 dark:hover:bg-slate-800" href="#">
                    <span class="material-symbols-outlined">photo_library</span>
                    Portfolyo / Galeri
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors font-medium text-sm dark:text-slate-400 dark:hover:bg-slate-800" href="../logout.php">
                    <span class="material-symbols-outlined">logout</span>
                    Çıkış Yap
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 max-w-3xl">
        <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined">check_circle</span>
                Bilgileriniz başarıyla güncellendi.
            </div>
        <?php endif; ?>

        <form method="POST" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
            <div class="p-8 border-b border-slate-100 dark:border-slate-800">
                <h2 class="text-primary dark:text-white text-3xl font-black tracking-tight">Genel Bilgiler</h2>
                <p class="text-slate-500 mt-2">Müşterilerinize kendinizi ve uzmanlığınızı en iyi şekilde tanıtın.</p>
            </div>
            
            <div class="p-8 space-y-8">
                <!-- Profile Picture Upload (Mock) -->
                <div class="flex items-center gap-6">
                    <div class="relative group">
                        <div class="size-32 rounded-xl bg-slate-100 dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-700 flex items-center justify-center overflow-hidden text-slate-400 font-bold text-4xl">
                            <?= mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1) ?>
                        </div>
                        <button type="button" class="absolute -bottom-2 -right-2 bg-accent p-2 rounded-full shadow-lg text-primary hover:scale-105 transition-transform">
                            <span class="material-symbols-outlined text-sm">edit</span>
                        </button>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-primary dark:text-white font-bold">Profil Fotoğrafı / Logo</h4>
                        <p class="text-slate-500 text-sm mt-1">En az 400x400px boyutunda, profesyonel bir fotoğraf veya şirket logonuzu yükleyin.</p>
                        <div class="mt-3 flex gap-2">
                            <button type="button" class="text-xs font-bold px-3 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg dark:text-slate-300 hover:bg-slate-50 transition-colors">Yükle</button>
                        </div>
                    </div>
                </div>

                <!-- Company Name -->
                <div class="space-y-2">
                    <label class="text-sm font-bold text-primary dark:text-slate-200">Firma veya Usta Adı</label>
                    <input name="business_name" class="w-full rounded-lg border-slate-300 dark:bg-slate-800 dark:border-slate-700 dark:text-white focus:ring-accent focus:border-accent px-4 py-3" type="text" value="<?= htmlspecialchars($user['business_name'] ?? '') ?>" placeholder="Örn: Yılmaz Tesisat"/>
                </div>

                <!-- About Section -->
                <div class="space-y-2">
                    <label class="text-sm font-bold text-primary dark:text-slate-200">Hakkımızda</label>
                    <div class="border border-slate-300 dark:border-slate-700 rounded-lg overflow-hidden">
                        <div class="bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 p-2 flex gap-2">
                            <button type="button" class="p-1 hover:bg-slate-200 dark:hover:bg-slate-700 rounded"><span class="material-symbols-outlined text-sm">format_bold</span></button>
                            <button type="button" class="p-1 hover:bg-slate-200 dark:hover:bg-slate-700 rounded"><span class="material-symbols-outlined text-sm">format_italic</span></button>
                            <button type="button" class="p-1 hover:bg-slate-200 dark:hover:bg-slate-700 rounded"><span class="material-symbols-outlined text-sm">format_list_bulleted</span></button>
                        </div>
                        <textarea name="bio" class="w-full border-none focus:ring-0 dark:bg-slate-800 dark:text-white px-4 py-3 text-sm" rows="5" placeholder="Kendinizi ve hizmetlerinizi anlatın..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>
                    <p class="text-xs text-slate-400">Müşterileriniz sizi bu açıklama ile tanıyacak.</p>
                </div>

                <!-- Working Hours (Mock) -->
                <div class="space-y-4">
                    <label class="text-sm font-bold text-primary dark:text-slate-200">Çalışma Saatleri</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex items-center justify-between p-3 border border-slate-100 dark:border-slate-700 rounded-lg">
                            <span class="text-sm font-medium dark:text-slate-300">Hafta İçi</span>
                            <div class="flex items-center gap-2">
                                <input class="w-16 text-xs p-1 rounded border-slate-300 dark:bg-slate-800 dark:border-slate-700 dark:text-white" type="text" value="09:00"/>
                                <span class="text-slate-400">-</span>
                                <input class="w-16 text-xs p-1 rounded border-slate-300 dark:bg-slate-800 dark:border-slate-700 dark:text-white" type="text" value="18:00"/>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 border border-slate-100 dark:border-slate-700 rounded-lg">
                            <span class="text-sm font-medium dark:text-slate-300">Hafta Sonu</span>
                            <div class="flex items-center gap-2">
                                <input class="w-16 text-xs p-1 rounded border-slate-300 dark:bg-slate-800 dark:border-slate-700 dark:text-white" type="text" value="10:00"/>
                                <span class="text-slate-400">-</span>
                                <input class="w-16 text-xs p-1 rounded border-slate-300 dark:bg-slate-800 dark:border-slate-700 dark:text-white" type="text" value="14:00"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-8 bg-slate-50 dark:bg-slate-900/50 flex justify-end border-t border-slate-100 dark:border-slate-800">
                <button type="submit" class="bg-accent text-primary px-8 py-3 rounded-lg font-bold shadow-md hover:bg-yellow-400 transition-all">
                    Değişiklikleri Kaydet
                </button>
            </div>
        </form>
    </div>

    <!-- Right Sidebar Info -->
    <aside class="w-full lg:w-80 flex flex-col gap-6">
        
        <!-- Abonelik Durumu Kartı -->
        <div class="bg-primary text-white rounded-xl p-6 relative overflow-hidden shadow-lg">
            <div class="relative z-10">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h4 class="font-bold text-sm opacity-80 mb-1">Mevcut Paket</h4>
                        <h3 class="text-xl font-black text-accent capitalize">
                            <?= $user['subscription_type'] === 'premium' ? 'Premium Paket' : 'Ücretsiz Paket' ?>
                        </h3>
                    </div>
                    <span class="material-symbols-outlined text-accent text-3xl">workspace_premium</span>
                </div>
                
                <div class="space-y-3 mb-6">
                    <div class="flex justify-between text-sm border-b border-white/10 pb-2">
                        <span class="opacity-80">Kalan Kredi</span>
                        <span class="font-bold text-accent">
                            <?= $user['remaining_offer_credit'] == -1 ? 'Sınırsız' : $user['remaining_offer_credit'] ?> Adet
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="opacity-80">Bitiş Tarihi</span>
                        <span class="font-bold">
                            <?= $user['subscription_ends_at'] ? date('d.m.Y', strtotime($user['subscription_ends_at'])) : '-' ?>
                        </span>
                    </div>
                </div>

                <a href="buy-package.php" class="block w-full text-center bg-white/10 hover:bg-white/20 text-white border border-white/20 py-2 rounded-lg text-xs font-bold transition-colors">
                    Paket Yükselt / Yenile
                </a>
            </div>
            <!-- Abstract pattern -->
            <div class="absolute -right-4 -bottom-4 opacity-10">
                <span class="material-symbols-outlined text-9xl">verified</span>
            </div>
        </div>

        <!-- Profil Doluluk Oranı -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-primary dark:text-white font-bold text-sm">Profil Doluluk Oranı</h3>
                <span class="text-primary font-black text-sm">85%</span>
            </div>
            <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-3 mb-4">
                <div class="bg-accent h-3 rounded-full transition-all duration-500" style="width: 85%"></div>
            </div>
            <p class="text-slate-500 text-xs leading-relaxed">Profilinizi tamamlayarak %40 daha fazla müşteri mesajı alabilirsiniz.</p>
        </div>

        <!-- Support Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm">
            <div class="flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-accent">lightbulb</span>
                <h3 class="text-primary dark:text-white font-bold text-sm">İpucu</h3>
            </div>
            <p class="text-slate-600 dark:text-slate-400 text-xs font-medium leading-relaxed">
                Hizmet alanlarınızı detaylandırarak doğru müşterilere ulaşabilirsiniz. "İş Fırsatları" sayfasını sık sık kontrol etmeyi unutmayın.
            </p>
        </div>
    </aside>
</main>

<?php require_once '../includes/footer.php'; ?>