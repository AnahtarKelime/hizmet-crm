<?php
require_once '../config/db.php';
session_start();

// Yetki Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'provider') {
    header("Location: ../login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// 1. Hizmet Veren Bilgilerini ve Aboneliğini Çek
$stmt = $pdo->prepare("SELECT * FROM provider_details WHERE user_id = ?");
$stmt->execute([$userId]);
$provider = $stmt->fetch();

// Abonelik Kontrolü
$isSubscribed = false;
$hasCredit = false;
if ($provider && $provider['subscription_ends_at'] && new DateTime($provider['subscription_ends_at']) > new DateTime()) {
    $isSubscribed = true;
    $hasCredit = ($provider['remaining_offer_credit'] > 0 || $provider['remaining_offer_credit'] == -1);
}

// 2. Hizmet Verenin Kategorilerini Çek
$stmt = $pdo->prepare("SELECT category_id FROM provider_service_categories WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$myCategoryId = $stmt->fetchColumn();

// 3. Hizmet Verenin Bölgelerini Çek
$stmt = $pdo->prepare("SELECT city, districts FROM provider_service_areas WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$myArea = $stmt->fetch();

// 4. Uygun Talepleri (Leads) Bul (Algoritma)
$leads = [];
if ($isSubscribed && $hasCredit && $myCategoryId && $myArea) {
    
    // Temel Sorgu: Onaylanmış, Arşivlenmemiş ve Benim Kategorimdeki Talepler
    $sql = "SELECT d.*, c.name as category_name, l.city, l.district, l.neighborhood 
            FROM demands d
            JOIN categories c ON d.category_id = c.id
            JOIN locations l ON d.location_id = l.id
            WHERE d.status = 'approved' 
            AND d.is_archived = 0
            AND d.category_id = :category_id
            AND l.city = :city";
    
    $params = [
        'category_id' => $myCategoryId,
        'city' => $myArea['city']
    ];

    // Eğer hizmet veren belirli bir ilçe seçmişse, sadece o ilçedeki işleri göster
    // Eğer 'districts' boşsa veya NULL ise tüm şehri kapsar (filtre eklenmez)
    if (!empty($myArea['districts'])) {
        $sql .= " AND l.district = :district";
        $params['district'] = $myArea['districts'];
    }

    $sql .= " ORDER BY d.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll();
}

$pageTitle = "İş Fırsatları";
$pathPrefix = '../';
require_once '../includes/header.php';
?>

<main class="max-w-7xl mx-auto px-4 py-12 min-h-[60vh]">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800">İş Fırsatları</h1>
            <p class="text-slate-500">Uzmanlık alanınıza ve bölgenize uygun talepler.</p>
        </div>
        <?php if ($isSubscribed && $hasCredit): ?>
            <span class="bg-green-100 text-green-700 px-4 py-2 rounded-lg font-bold text-sm">
                Abonelik Aktif (Bitiş: <?= date('d.m.Y', strtotime($provider['subscription_ends_at'])) ?>)
            </span>
        <?php endif; ?>
    </div>

    <?php if (!$isSubscribed || !$hasCredit): ?>
        <div class="text-center py-16 bg-slate-50 rounded-2xl border border-slate-200">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-white rounded-full mb-6 shadow-sm text-primary">
                <span class="material-symbols-outlined text-4xl">workspace_premium</span>
            </div>
            <h2 class="text-2xl font-bold text-slate-800 mb-3">
                <?php if (!$isSubscribed): ?>
                    Abonelik Süreniz Doldu
                <?php else: ?>
                    Teklif Krediniz Tükendi
                <?php endif; ?>
            </h2>
            <p class="text-slate-500 mb-8 max-w-md mx-auto leading-relaxed">
                Yeni iş fırsatlarını görüntülemek ve teklif vermeye devam etmek için size uygun bir paket seçerek aboneliğinizi yenileyebilirsiniz. 
                <?php if ($isSubscribed): ?>Mevcut süreniz devam ediyor ancak krediniz bittiği için yeni teklif veremiyorsunuz.<?php endif; ?>
            </p>
            <a href="buy-package.php" class="px-6 py-3 bg-primary text-white rounded-xl font-bold hover:bg-primary/90 transition-all">Paketleri İncele</a>
        </div>
    <?php elseif (empty($leads)): ?>
        <div class="text-center py-20 bg-white rounded-2xl border border-slate-100 shadow-sm">
            <span class="material-symbols-outlined text-5xl text-slate-300 mb-4">search_off</span>
            <h2 class="text-xl font-bold text-slate-700 mb-2">Uygun Talep Bulunamadı</h2>
            <p class="text-slate-500">Şu an için kriterlerinize uygun yeni bir talep yok. Lütfen daha sonra tekrar kontrol edin.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($leads as $lead): ?>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-all group">
                    <div class="flex justify-between items-start mb-4">
                        <span class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-bold uppercase tracking-wider">
                            <?= htmlspecialchars($lead['category_name']) ?>
                        </span>
                        <span class="text-xs text-slate-400 font-medium"><?= date('d.m.Y', strtotime($lead['created_at'])) ?></span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2 line-clamp-2 group-hover:text-primary transition-colors">
                        <?= htmlspecialchars($lead['title']) ?>
                    </h3>
                    <p class="text-slate-500 text-sm mb-4 flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">location_on</span>
                        <?= htmlspecialchars($lead['city'] . ' / ' . $lead['district']) ?>
                    </p>
                    <a href="../demand-details.php?id=<?= $lead['id'] ?>" class="block w-full py-3 text-center bg-slate-50 text-slate-700 font-bold rounded-xl hover:bg-primary hover:text-white transition-all">
                        Detayları Gör & Teklif Ver
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>