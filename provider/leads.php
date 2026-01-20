<?php
require_once '../config/db.php';
session_start();

// Yetki Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'provider') {
    header("Location: ../login.php");
    exit;
}

// Mesafe Hesaplama Fonksiyonu (Haversine Formülü)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return null;
    
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    return ($miles * 1.609344); // Kilometreye çevir
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

// 4. Sekme ve Filtreleme Mantığı
$activeTab = $_GET['tab'] ?? 'new'; // new, pending, accepted, rejected
$leads = [];

if ($myCategoryId && $myArea) {
    $params = ['user_id' => $userId];
    $sql = "";

    if ($activeTab === 'new') {
        // Sadece abonelik ve kredi varsa yeni işleri göster
        if ($isSubscribed && $hasCredit) {
            $sql = "SELECT d.*, c.name as category_name, l.city, l.district, l.neighborhood,
                    (SELECT COUNT(*) FROM lead_access_logs WHERE demand_id = d.id AND user_id = :user_id) as is_viewed 
                    FROM demands d
                    JOIN categories c ON d.category_id = c.id
                    JOIN locations l ON d.location_id = l.id
                    WHERE d.is_archived = 0
                    AND d.status = 'approved'
                    AND d.category_id = :category_id
                    AND l.city = :city
                    AND d.id NOT IN (SELECT demand_id FROM offers WHERE user_id = :user_id_2)
                    AND d.user_id != :user_id_3";
            
            $params['category_id'] = $myCategoryId;
            $params['city'] = $myArea['city'];
            $params['user_id_2'] = $userId;
            $params['user_id_3'] = $userId;

            if (!empty($myArea['districts'])) {
                $sql .= " AND l.district = :district";
                $params['district'] = $myArea['districts'];
            }
            $sql .= " ORDER BY d.created_at DESC";
        }
    } elseif ($activeTab === 'pending') {
        $sql = "SELECT d.*, c.name as category_name, l.city, l.district, l.neighborhood, o.price as my_offer_price, o.created_at as offer_date
                FROM offers o
                JOIN demands d ON o.demand_id = d.id
                JOIN categories c ON d.category_id = c.id
                JOIN locations l ON d.location_id = l.id
                WHERE o.user_id = :user_id AND o.status = 'pending'
                ORDER BY o.created_at DESC";
    } elseif ($activeTab === 'accepted') {
        $sql = "SELECT d.*, c.name as category_name, l.city, l.district, l.neighborhood, o.price as my_offer_price, o.created_at as offer_date
                FROM offers o
                JOIN demands d ON o.demand_id = d.id
                JOIN categories c ON d.category_id = c.id
                JOIN locations l ON d.location_id = l.id
                WHERE o.user_id = :user_id AND o.status = 'accepted'
                ORDER BY o.created_at DESC";
    } elseif ($activeTab === 'rejected') {
        $sql = "SELECT d.*, c.name as category_name, l.city, l.district, l.neighborhood, o.price as my_offer_price, o.created_at as offer_date
                FROM offers o
                JOIN demands d ON o.demand_id = d.id
                JOIN categories c ON d.category_id = c.id
                JOIN locations l ON d.location_id = l.id
                WHERE o.user_id = :user_id AND o.status = 'rejected'
                ORDER BY o.created_at DESC";
    }

    if ($sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $leads = $stmt->fetchAll();
    }
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

    <!-- Tabs -->
    <div class="flex border-b border-slate-200 mb-8 overflow-x-auto">
        <a href="?tab=new" class="px-6 py-3 text-sm font-bold whitespace-nowrap border-b-2 transition-colors <?= $activeTab === 'new' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
            Teklif Bekleyenler
        </a>
        <a href="?tab=pending" class="px-6 py-3 text-sm font-bold whitespace-nowrap border-b-2 transition-colors <?= $activeTab === 'pending' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
            Cevap Bekleyenler
        </a>
        <a href="?tab=accepted" class="px-6 py-3 text-sm font-bold whitespace-nowrap border-b-2 transition-colors <?= $activeTab === 'accepted' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
            Kazandığım Teklifler
        </a>
        <a href="?tab=rejected" class="px-6 py-3 text-sm font-bold whitespace-nowrap border-b-2 transition-colors <?= $activeTab === 'rejected' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
            Red Edilenler
        </a>
    </div>

    <?php if ($activeTab === 'new' && (!$isSubscribed || !$hasCredit)): ?>
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
            <p class="text-slate-500">Bu kategoride görüntülenecek talep bulunmuyor.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($leads as $lead): ?>
                <?php 
                    $isViewed = $lead['is_viewed'] ?? 0;
                    $cardBorderClass = 'border-slate-100 hover:border-[#1a2a6b]'; // Diğer sekmeler için varsayılan
                    
                    if ($activeTab === 'new') {
                        if ($isViewed) {
                            $cardBorderClass = 'border-[#1a2a6b]'; // Görüntülendi ise lacivert
                        } else {
                            $cardBorderClass = 'border-green-700 border-2'; // Görüntülenmedi ise koyu yeşil ve kalın
                        }
                    }
                ?>
                
                <?php 
                    // Mesafe Hesapla
                    $distance = calculateDistance($provider['latitude'], $provider['longitude'], $lead['latitude'], $lead['longitude']);
                ?>

                <div class="bg-white p-6 rounded-2xl shadow-sm border <?= $cardBorderClass ?> hover:shadow-md transition-all group relative overflow-hidden">
                    <?php if ($activeTab === 'new' && !$isViewed): ?>
                        <div class="absolute top-0 right-0 text-white text-[10px] font-bold px-3 py-1 rounded-bl-xl bg-green-700">
                            YENİ TALEP
                        </div>
                    <?php elseif ($activeTab !== 'new'): ?>
                        <div class="absolute top-0 right-0 text-white text-[10px] font-bold px-3 py-1 rounded-bl-xl 
                            <?= $activeTab === 'accepted' ? 'bg-green-500' : 
                               ($activeTab === 'rejected' ? 'bg-red-500' : 'bg-yellow-500') ?>">
                            <?= $activeTab === 'accepted' ? 'KAZANDINIZ' : 
                               ($activeTab === 'rejected' ? 'REDDEDİLDİ' : 'CEVAP BEKLİYOR') ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4 mt-2">
                        <span class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-bold uppercase tracking-wider inline-block mb-2">
                            <?= htmlspecialchars($lead['category_name']) ?>
                        </span>
                        <h4 class="font-bold text-slate-800 line-clamp-2 mb-1 h-12"><?= htmlspecialchars($lead['title']) ?></h4>
                        <div class="flex items-center justify-between">
                            <p class="text-slate-500 text-sm flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">location_on</span>
                                <?= htmlspecialchars($lead['city'] . ' / ' . $lead['district']) ?>
                            </p>
                            <?php if ($distance !== null): ?>
                                <span class="text-xs font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded flex items-center gap-1" title="Kuş uçuşu mesafe"><span class="material-symbols-outlined text-[10px]">straight</span> <?= number_format($distance, 1) ?> km</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-4 mb-4 border border-slate-100">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold text-slate-500 uppercase">Tarih</span>
                            <span class="text-xs font-bold text-slate-700"><?= date('d.m.Y', strtotime($lead['created_at'])) ?></span>
                        </div>
                        <?php if (isset($lead['my_offer_price'])): ?>
                            <div class="flex justify-between items-center mt-2 pt-2 border-t border-slate-200">
                                <span class="text-xs font-bold text-slate-500 uppercase">Teklifiniz</span>
                                <span class="text-sm font-black text-slate-800"><?= number_format($lead['my_offer_price'], 2, ',', '.') ?> ₺</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <a href="../demand-details.php?id=<?= $lead['id'] ?>" class="block w-full py-3 text-center bg-primary text-white font-bold rounded-xl hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                        <?= ($activeTab === 'new') ? 'Detayları Gör & Teklif Ver' : 'Detayları Gör' ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>