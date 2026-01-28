<?php
require_once 'config/db.php';
require_once 'includes/mail-helper.php';
require_once 'includes/push-helper.php'; // Push helper eklendi

// Oturumu başlat (header.php'den önce işlem yaptığımız için gerekli)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Token ile Otomatik Giriş (Magic Link)
if (!isset($_SESSION['user_id']) && isset($_GET['auth_token'])) {
    $token = $_GET['auth_token'];
    $stmtToken = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW()");
    $stmtToken->execute([$token]);
    $tokenUser = $stmtToken->fetch();

    if ($tokenUser) {
        $_SESSION['user_id'] = $tokenUser['id'];
        $_SESSION['user_name'] = $tokenUser['first_name'] . ' ' . $tokenUser['last_name'];
        $_SESSION['user_role'] = $tokenUser['role'];
    }
}

$userId = $_SESSION['user_id'] ?? null;
$isLoggedIn = !empty($userId);
$demandId = $_GET['id'] ?? null;

if (!$demandId) {
    header("Location: " . ($isLoggedIn ? "my-demands.php" : "index.php"));
    exit;
}

// Talep detaylarını çek
$stmt = $pdo->prepare("
    SELECT 
        d.*, 
        c.name as category_name, 
        l.city, l.district, l.neighborhood,
        u.first_name, u.last_name, u.email, u.phone
    FROM demands d
    LEFT JOIN categories c ON d.category_id = c.id
    LEFT JOIN locations l ON d.location_id = l.id
    LEFT JOIN users u ON d.user_id = u.id
    WHERE d.id = ?
");
$stmt->execute([$demandId]);
$demand = $stmt->fetch();

// Görüntülenme Sayısı
$stmtViews = $pdo->prepare("SELECT COUNT(*) FROM lead_access_logs WHERE demand_id = ?");
$stmtViews->execute([$demandId]);
$viewCount = $stmtViews->fetchColumn();

// Müşteri Puanı ve Yorum Sayısı
$stmtRating = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as count_rating FROM reviews WHERE reviewed_id = ?");
$stmtRating->execute([$demand['user_id']]);
$customerStats = $stmtRating->fetch();
$customerRating = $customerStats['avg_rating'] ? number_format($customerStats['avg_rating'], 1) : 'Yeni';
$customerReviewCount = $customerStats['count_rating'];

// Yetki Kontrolü Değişkenleri
$isOwner = ($demand && $demand['user_id'] == $userId);
$isProvider = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'provider');

// Provider için kredi ve teklif durumu kontrolü
$hasOffered = false;
$hasCredit = false;
$providerDetails = null;
$templates = [];
$myOffer = null;

if ($isLoggedIn && $isProvider && !$isOwner && $demand) {
    // Teklif verip vermediğini kontrol et
    $stmt = $pdo->prepare("SELECT id, status FROM offers WHERE demand_id = ? AND user_id = ?");
    $stmt->execute([$demandId, $userId]);
    $myOffer = $stmt->fetch();
    if ($myOffer) {
        $hasOffered = true;
    }

    // Kredi ve abonelik durumunu kontrol et
    $stmt = $pdo->prepare("SELECT * FROM provider_details WHERE user_id = ?");
    $stmt->execute([$userId]);
    $providerDetails = $stmt->fetch();

    if ($providerDetails) {
        $isSubscriptionActive = false;
        if (!empty($providerDetails['subscription_ends_at'])) {
            try {
                if (new DateTime($providerDetails['subscription_ends_at']) > new DateTime()) {
                    $isSubscriptionActive = true;
                }
            } catch (Exception $e) {}
        }

        if ($isSubscriptionActive && ($providerDetails['remaining_offer_credit'] > 0 || $providerDetails['remaining_offer_credit'] == -1)) {
            $hasCredit = true;
        }
    }

    // Şablonları Çek
    $stmt = $pdo->prepare("SELECT * FROM provider_message_templates WHERE user_id = ? ORDER BY title ASC");
    $stmt->execute([$userId]);
    $templates = $stmt->fetchAll();

    // Görüntülenme Kaydı (Log View)
    $pdo->prepare("INSERT IGNORE INTO lead_access_logs (demand_id, user_id, access_type) VALUES (?, ?, 'premium_view')")->execute([$demandId, $userId]);
}

// Provider Teklif Verme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_offer'])) {
    if ($isProvider && !$isOwner && $hasCredit && !$hasOffered) {
        $price = $_POST['price'];
        $message = $_POST['message'];

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO offers (demand_id, user_id, price, message, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([$demandId, $userId, $price, $message]);

        if ($providerDetails['remaining_offer_credit'] != -1) {
            $stmt = $pdo->prepare("UPDATE provider_details SET remaining_offer_credit = remaining_offer_credit - 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
        }
        $pdo->commit();

        // Müşteriye Mail Gönder
        sendEmail($demand['email'], 'new_offer', [
            'name' => $demand['first_name'] . ' ' . $demand['last_name'],
            'demand_title' => $demand['title'],
            'link' => getBaseUrl() . '/demand-details.php?id=' . $demandId
        ]);

        // Müşteriye Push Bildirim Gönder
        sendPushNotification(
            $demand['user_id'],
            'Yeni Teklifiniz Var!',
            $demand['title'] . ' talebiniz için yeni bir fiyat teklifi geldi.',
            getBaseUrl() . '/demand-details.php?id=' . $demandId
        );

        $successMsg = "Teklifiniz başarıyla gönderildi.";
        $hasOffered = true; // Sayfa yenilenmeden durumu güncelle
    }
}

// İşlem Yönetimi (Teklif Kabul/Red) - Müşteri için
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $isOwner) {
    $action = $_POST['action'];
    $offerId = $_POST['offer_id'];

    if ($action === 'accept') {
        try {
            $pdo->beginTransaction();
            // 1. Bu teklifi kabul et
            $stmt = $pdo->prepare("UPDATE offers SET status = 'accepted' WHERE id = ?");
            $stmt->execute([$offerId]);
            
            // 2. Talebin durumunu güncelle
            $stmt = $pdo->prepare("UPDATE demands SET status = 'completed' WHERE id = ?");
            $stmt->execute([$demandId]);
            
            // Teklifi vereni bul (Push için)
            $stmtOfferOwner = $pdo->prepare("SELECT user_id FROM offers WHERE id = ?");
            $stmtOfferOwner->execute([$offerId]);
            $providerId = $stmtOfferOwner->fetchColumn();

            if ($providerId) {
                sendPushNotification($providerId, 'Teklifiniz Kabul Edildi! 🎉', 'Tebrikler! Müşteri teklifinizi onayladı. Detayları görüntülemek için tıklayın.', getBaseUrl() . '/offer-details.php?id=' . $offerId);
            }

            $pdo->commit();
            $successMsg = "Teklif başarıyla kabul edildi.";
            header("Refresh:1");
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMsg = "İşlem sırasında hata oluştu: " . $e->getMessage();
        }
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE offers SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$offerId]);
        $successMsg = "Teklif reddedildi.";
        header("Refresh:1");
    }
}

$pageTitle = "Talep Detayı";
// SEO Açıklaması
$siteDescription = htmlspecialchars($demand['title']) . " hizmeti için " . htmlspecialchars($demand['city']) . "/" . htmlspecialchars($demand['district']) . " bölgesinde fiyat teklifi al. " . htmlspecialchars($demand['category_name']) . " iş fırsatları.";

require_once 'includes/header.php';

if (!$demand) { // Talep yoksa
    echo "<div class='max-w-7xl mx-auto px-4 py-12'><div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded'>Talep bulunamadı veya bu talebi görüntüleme yetkiniz yok.</div></div>";
    require_once 'includes/footer.php';
    exit;
}

// Cevapları Çek
$stmt = $pdo->prepare("
    SELECT 
        da.answer_text, 
        cq.question_text,
        cq.input_type
    FROM demand_answers da
    LEFT JOIN category_questions cq ON da.question_id = cq.id
    WHERE da.demand_id = ?
");
$stmt->execute([$demandId]);
$answers = $stmt->fetchAll();

// Teklifleri Çek (Gelecek özellik)
$stmt = $pdo->prepare("
    SELECT 
        o.*, 
        u.first_name, u.last_name,
        pd.business_name,
        (SELECT COUNT(*) FROM reviews WHERE offer_id = o.id AND reviewer_id = :current_user) as has_reviewed
    FROM offers o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN provider_details pd ON u.id = pd.user_id
    WHERE o.demand_id = :demand_id
    ORDER BY o.created_at DESC
");
$stmt->execute(['demand_id' => $demandId, 'current_user' => $userId]);
$offers = $stmt->fetchAll();

// Konum ve metin cevaplarını ayır
$locationPoints = [];
$displayAnswers = []; // Kullanıcıya gösterilecek cevaplar (konumlar için sadece adres metni)

foreach ($answers as $ans) {
    $decoded = json_decode($ans['answer_text']);
    // Cevabın geçerli bir JSON ve koordinat içerip içermediğini kontrol et
    if (json_last_error() === JSON_ERROR_NONE && isset($decoded->lat) && isset($decoded->lng)) {
        $locationPoints[] = [
            'lat' => (float)$decoded->lat,
            'lng' => (float)$decoded->lng,
            'address' => htmlspecialchars($decoded->address ?? 'Adres belirtilmemiş'),
            'question' => htmlspecialchars($ans['question_text'])
        ];
        // Kullanıcı dostu gösterim için sadece adresi displayAnswers'a ekle
        $displayAnswers[] = [
            'question_text' => $ans['question_text'], 
            'answer_text' => $decoded->address ?? 'Adres belirtilmemiş',
            'input_type' => $ans['input_type'] ?? 'location'
        ];
    } else {
        // Metin tabanlı cevapları doğrudan displayAnswers'a ekle
        $displayAnswers[] = $ans;
    }
}

// Eğer dinamik cevaplarda konum yoksa, ana talep kaydındaki konumu haritaya ekle
if (empty($locationPoints) && !empty($demand['latitude']) && !empty($demand['longitude'])) {
    $locationPoints[] = [
        'lat' => (float)$demand['latitude'],
        'lng' => (float)$demand['longitude'],
        'address' => htmlspecialchars($demand['address_text'] ?? 'Konum belirtilmemiş'),
        'question' => 'Hizmet Konumu'
    ];
}

// Benzer İlanları Çek
$stmtSimilar = $pdo->prepare("
    SELECT 
        d.id, d.title, d.created_at, d.status, d.estimated_cost,
        l.city, l.district,
        c.name as category_name
    FROM demands d
    LEFT JOIN locations l ON d.location_id = l.id
    LEFT JOIN categories c ON d.category_id = c.id
    WHERE d.category_id = ? 
      AND d.id != ? 
      AND d.status IN ('pending', 'approved')
      AND d.is_archived = 0
    ORDER BY d.created_at DESC
    LIMIT 3
");
$stmtSimilar->execute([$demand['category_id'], $demandId]);
$similarDemands = $stmtSimilar->fetchAll();
?>

<main class="max-w-7xl mx-auto px-6 py-8">
    <nav class="flex items-center space-x-2 text-sm text-slate-500 mb-6">
        <a class="hover:text-primary transition" href="<?= $isLoggedIn ? 'my-demands.php' : 'provider/leads.php' ?>">Fırsatlar</a>
        <span class="material-symbols-outlined text-xs">chevron_right</span>
        <span class="text-slate-700 dark:text-slate-300 font-semibold"><?= htmlspecialchars($demand['category_name']) ?></span>
        <span class="material-symbols-outlined text-xs">chevron_right</span>
        <span class="text-primary font-semibold">İş İlanı Detayı</span>
    </nav>

    <?php 
    $showSuccessAlert = true;
    if (isset($_GET['status']) && $_GET['status'] == 'success' && isset($_GET['msg']) && $_GET['msg'] == 'Değerlendirmeniz alındı') {
        $showSuccessAlert = false;
    }
    ?>
    <?php if (isset($_GET['status']) && $_GET['status'] == 'success' && $showSuccessAlert): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">
            <?= htmlspecialchars($_GET['msg'] ?? 'İşlem başarılı.') ?>
        </div>
    <?php endif; ?>
    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-8 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-6">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <span class="text-sm font-semibold text-slate-500"><?= htmlspecialchars($demand['first_name'] . ' ' . mb_substr($demand['last_name'], 0, 1) . '.') ?></span>
                            <?php if (isset($demand['is_verified']) && $demand['is_verified']): ?>
                            <div class="flex items-center px-2 py-0.5 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-[10px] font-bold rounded-full border border-blue-100 dark:border-blue-800">
                                <span class="material-symbols-outlined text-[14px] mr-1" style="font-variation-settings: 'FILL' 1">verified</span>
                                ONAYLI
                            </div>
                            <?php endif; ?>
                        </div>
                        <h1 class="text-3xl font-extrabold text-primary dark:text-white leading-tight">
                            <?= htmlspecialchars($demand['title']) ?>
                        </h1>
                    </div>
                    <div class="flex items-center gap-2">
                        <button class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition text-slate-500">
                            <span class="material-symbols-outlined">share</span>
                        </button>
                        <button class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition text-slate-500">
                            <span class="material-symbols-outlined">bookmark_border</span>
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 py-6 border-y border-slate-100 dark:border-slate-800">
                    <div>
                        <span class="block text-[11px] text-slate-400 font-bold uppercase mb-1">Konum</span>
                        <div class="flex items-center text-slate-700 dark:text-slate-300">
                            <span class="material-symbols-outlined text-lg mr-1.5 text-slate-400">location_on</span>
                            <span class="text-sm font-medium"><?= htmlspecialchars($demand['city'] . ', ' . $demand['district']) ?></span>
                        </div>
                    </div>
                    <div>
                        <span class="block text-[11px] text-slate-400 font-bold uppercase mb-1">Yayınlanma</span>
                        <div class="flex items-center text-slate-700 dark:text-slate-300">
                            <span class="material-symbols-outlined text-lg mr-1.5 text-slate-400">schedule</span>
                            <span class="text-sm font-medium"><?= date('d.m.Y', strtotime($demand['created_at'])) ?></span>
                        </div>
                    </div>
                    <div>
                        <span class="block text-[11px] text-slate-400 font-bold uppercase mb-1">İş Tipi</span>
                        <div class="flex items-center text-slate-700 dark:text-slate-300">
                            <span class="material-symbols-outlined text-lg mr-1.5 text-slate-400">category</span>
                            <span class="text-sm font-medium"><?= htmlspecialchars($demand['category_name']) ?></span>
                        </div>
                    </div>
                    <div>
                        <span class="block text-[11px] text-slate-400 font-bold uppercase mb-1">Durum</span>
                        <div class="flex items-center text-green-600">
                            <span class="material-symbols-outlined text-lg mr-1.5">check_circle</span>
                            <span class="text-sm font-bold">
                                <?= $demand['status'] == 'pending' ? 'Beklemede' : 
                                   ($demand['status'] == 'approved' ? 'Teklife Açık' : 
                                   ($demand['status'] == 'completed' ? 'Tamamlandı' : 'İptal')) ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($locationPoints) && $isLoggedIn): ?>
                    <div class="mt-8">
                        <h2 class="text-lg font-bold text-primary dark:text-white mb-4">Harita Konumu</h2>
                        <div id="map" class="w-full h-64 rounded-xl bg-slate-50 border border-slate-200"></div>
                    </div>
                <?php elseif (!empty($locationPoints) && !$isLoggedIn): ?>
                    <div class="mt-8">
                        <h2 class="text-lg font-bold text-primary dark:text-white mb-4">Harita Konumu</h2>
                        <div class="w-full h-32 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400 text-sm">
                            <span class="flex items-center gap-2"><span class="material-symbols-outlined">visibility_off</span> Haritayı görmek için giriş yapın</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-white dark:bg-slate-900 rounded-2xl p-8 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="flex items-center gap-2 mb-6">
                    <span class="material-symbols-outlined text-primary dark:text-accent">quiz</span>
                    <h2 class="text-xl font-bold text-primary dark:text-white">Müşteri Soruları ve Cevapları</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($displayAnswers as $ans): ?>
                    <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                        <p class="text-xs font-bold text-slate-400 uppercase mb-2"><?= htmlspecialchars($ans['question_text']) ?></p>
                        <p class="text-slate-800 dark:text-slate-200 font-semibold">
                            <?php if (isset($ans['input_type']) && $ans['input_type'] === 'image'): ?>
                                <a href="<?= htmlspecialchars($ans['answer_text']) ?>" target="_blank" class="text-accent hover:underline flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">image</span> Görseli Görüntüle
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($ans['answer_text']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if ($isOwner && !empty($offers)): ?>
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-8 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <h2 class="text-xl font-bold text-primary dark:text-white mb-6">Gelen Teklifler</h2>
                    <div class="space-y-4">
                        <?php foreach ($offers as $offer): ?>
                            <div class="p-4 border border-slate-100 rounded-xl bg-slate-50">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="font-bold text-slate-800"><?= htmlspecialchars($offer['business_name'] ?: $offer['first_name'] . ' ' . $offer['last_name']) ?></span>
                                    <span class="font-black text-primary"><?= number_format($offer['price'], 2, ',', '.') ?> ₺</span>
                                </div>
                                <p class="text-sm text-slate-600 mb-3"><?= nl2br(htmlspecialchars($offer['message'])) ?></p>
                                <?php if ($offer['status'] === 'pending'): ?>
                                    <div class="flex gap-2">
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="action" value="accept">
                                            <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                                            <button type="submit" class="px-3 py-1.5 bg-green-600 text-white text-xs font-bold rounded hover:bg-green-700">Kabul Et</button>
                                        </form>
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                                            <button type="submit" class="px-3 py-1.5 bg-red-600 text-white text-xs font-bold rounded hover:bg-red-700">Reddet</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs font-bold uppercase px-2 py-1 bg-gray-200 rounded"><?= $offer['status'] ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="space-y-6">
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl overflow-hidden sticky top-24">
                <div class="p-8">
                    <div class="mb-6">
                        <span class="block text-xs text-slate-400 font-bold uppercase mb-2">Tahmini Bütçe</span>
                        <div class="flex items-baseline">
                            <span class="text-3xl font-black text-primary dark:text-white tracking-tight">
                                <?= (!empty($demand['estimated_cost']) && $demand['estimated_cost'] > 0) ? '₺' . number_format($demand['estimated_cost'], 0, ',', '.') : 'Teklif Usulü' ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($isProvider && !$isOwner): ?>
                        <?php if ($hasOffered): ?>
                            <div class="w-full bg-green-100 text-green-700 py-4 rounded-xl font-bold text-center mb-6 border border-green-200">
                                Teklif Verildi
                            </div>
                        <?php elseif ($hasCredit): ?>
                            <form method="POST" class="space-y-4 mb-6">
                                <input type="hidden" name="submit_offer" value="1">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1">Teklif Tutarı (TL)</label>
                                    <input type="number" name="price" step="0.01" required class="w-full rounded-lg border-slate-300 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1">Mesajınız</label>
                                    <textarea name="message" rows="3" required class="w-full rounded-lg border-slate-300 text-sm" placeholder="Detayları yazın..."></textarea>
                                </div>
                                <button type="submit" class="w-full bg-accent text-primary py-4 rounded-xl font-black text-lg hover:brightness-105 transition shadow-lg shadow-accent/20 flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined">send</span> Hemen Teklif Ver
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="provider/buy-package.php" class="block w-full bg-red-50 text-red-600 py-4 rounded-xl font-bold text-center mb-6 border border-red-100 hover:bg-red-100 transition">
                                Kredi Yetersiz - Paket Al
                            </a>
                        <?php endif; ?>
                    <?php elseif (!$isLoggedIn): ?>
                        <a href="login.php?redirect=demand-details.php?id=<?= $demandId ?>" class="block w-full bg-primary text-white py-4 rounded-xl font-black text-lg text-center hover:bg-primary/90 transition shadow-lg mb-6">
                            Giriş Yap ve Teklif Ver
                        </a>
                    <?php endif; ?>

                    <div class="space-y-4 pt-6 border-t border-slate-100 dark:border-slate-800">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center text-slate-500 text-sm">
                                <span class="material-symbols-outlined text-lg mr-2">group</span>
                                Gelen Teklif Sayısı
                            </div>
                            <span class="font-bold text-primary dark:text-white"><?= count($offers) ?> Teklif</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center text-slate-500 text-sm">
                                <span class="material-symbols-outlined text-lg mr-2">visibility</span>
                                Görüntülenme
                            </div>
                            <span class="font-bold text-primary dark:text-white"><?= $viewCount ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50 dark:bg-slate-800/50 p-6 border-t border-slate-100 dark:border-slate-800 rounded-2xl">
                <h3 class="text-xs font-bold text-slate-400 uppercase mb-4">İlan Sahibi</h3>
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 rounded-full bg-primary flex items-center justify-center text-white font-bold text-lg">
                        <?= mb_substr($demand['first_name'], 0, 1) . mb_substr($demand['last_name'], 0, 1) ?>
                    </div>
                    <div>
                        <div class="flex items-center gap-1 font-bold text-primary dark:text-white">
                            <?= htmlspecialchars($demand['first_name'] . ' ' . mb_substr($demand['last_name'], 0, 1) . '.') ?>
                            <?php if (isset($demand['is_verified']) && $demand['is_verified']): ?>
                                <span class="material-symbols-outlined text-blue-500 text-[18px]" style="font-variation-settings: 'FILL' 1">verified</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center text-xs text-slate-500">
                            <span class="material-symbols-outlined text-xs mr-1 text-accent" style="font-variation-settings: 'FILL' 1">star</span>
                            <?= $customerRating ?> (<?= $customerReviewCount ?> Değerlendirme)
                        </div>
                    </div>
                </div>
                <div class="text-sm text-slate-500 space-y-2">
                    <p class="flex items-center">
                        <span class="material-symbols-outlined text-xs mr-2 text-green-500">check</span>
                        E-posta Onaylı
                    </p>
                    <p class="flex items-center">
                        <span class="material-symbols-outlined text-xs mr-2 text-green-500">check</span>
                        Telefon Onaylı
                    </p>
                </div>
            </div>

            <div class="bg-primary/5 dark:bg-blue-900/10 p-6 rounded-2xl border border-primary/10">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-primary dark:text-accent">lightbulb</span>
                    <div>
                        <h4 class="font-bold text-primary dark:text-white text-sm mb-1">İpucu</h4>
                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                            Teklifinizi verirken daha önce yaptığınız benzer işlerin referanslarını eklemeniz, müşterinin sizi seçme şansını %40 oranında artırır.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($similarDemands)): ?>
    <div class="mt-12 pt-8 border-t border-slate-200 dark:border-slate-800">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Benzer İlanlar</h2>
            <a href="<?= $isLoggedIn ? 'my-demands.php' : 'provider/leads.php' ?>" class="text-sm font-bold text-primary hover:text-accent transition-colors">Tümünü Gör</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php foreach ($similarDemands as $sim): ?>
            <a href="demand-details.php?id=<?= $sim['id'] ?>" class="block bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5 hover:shadow-md hover:border-primary/50 transition-all group">
                <div class="flex justify-between items-start mb-3">
                    <span class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded text-xs font-bold">
                        <?= htmlspecialchars($sim['category_name']) ?>
                    </span>
                    <span class="text-xs text-slate-400"><?= date('d.m.Y', strtotime($sim['created_at'])) ?></span>
                </div>
                <h3 class="font-bold text-slate-800 dark:text-white mb-2 line-clamp-2 group-hover:text-primary transition-colors">
                    <?= htmlspecialchars($sim['title']) ?>
                </h3>
                <div class="flex items-center text-sm text-slate-500 dark:text-slate-400 mb-4">
                    <span class="material-symbols-outlined text-base mr-1">location_on</span>
                    <?= htmlspecialchars($sim['city'] . ', ' . $sim['district']) ?>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
                    <span class="text-xs font-bold text-slate-400 uppercase">Tahmini Bütçe</span>
                    <span class="font-bold text-slate-800 dark:text-white">
                        <?= ($sim['estimated_cost'] > 0) ? '₺' . number_format($sim['estimated_cost'], 0, ',', '.') : 'Teklif Usulü' ?>
                    </span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</main>

<?php
// Google API Anahtarını veritabanından çek
$googleApiKey = '';
try {
    $stmtKey = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'google_maps_api_key'");
    $googleApiKey = $stmtKey->fetchColumn();
} catch (Exception $e) {
    // Hata durumunda boş kalır, script yüklenmez.
}
?>

<?php if (!empty($locationPoints) && !empty($googleApiKey)): ?>
<script>
function initMap() {
    const locations = <?= json_encode($locationPoints) ?>;
    if (locations.length === 0) return;

    const map = new google.maps.Map(document.getElementById("map"), {
        zoom: 12,
        center: locations[0],
        disableDefaultUI: true,
        zoomControl: true,
    });

    const bounds = new google.maps.LatLngBounds();
    const labels = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    let labelIndex = 0;

    locations.forEach(location => {
        const marker = new google.maps.Marker({
            position: { lat: location.lat, lng: location.lng },
            map: map,
            label: labels[labelIndex++ % labels.length],
            title: location.question
        });

        const infoWindow = new google.maps.InfoWindow({
            content: `<div style="padding:5px; color:#333; font-family:sans-serif; font-size:13px;"><strong>${location.question}:</strong><br>${location.address}</div>`
        });

        marker.addListener("click", () => {
            infoWindow.open(map, marker);
        });

        bounds.extend(marker.getPosition());
    });

    if (locations.length > 1) {
        map.fitBounds(bounds);
    }
}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($googleApiKey) ?>&callback=initMap" async defer></script>
<?php endif; ?>

<!-- Rating Success Modal -->
<div id="rating-success-modal" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300 opacity-0">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-8 max-w-sm w-full mx-4 transform scale-95 transition-transform duration-300 text-center relative">
        <button onclick="closeRatingModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
            <span class="material-symbols-outlined">close</span>
        </button>
        
        <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-6 animate-bounce">
            <span class="material-symbols-outlined text-5xl">check_circle</span>
        </div>
        
        <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-2">Teşekkürler!</h3>
        <p class="text-slate-600 dark:text-slate-400 mb-8">Değerlendirmeniz başarıyla alındı. Geri bildiriminiz bizim için çok değerli.</p>
        
        <button onclick="closeRatingModal()" class="w-full py-3 bg-primary text-white font-bold rounded-xl hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
            Tamam
        </button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('status') === 'success' && urlParams.get('msg') === 'Değerlendirmeniz alındı') {
            const modal = document.getElementById('rating-success-modal');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.querySelector('div').classList.remove('scale-95');
                modal.querySelector('div').classList.add('scale-100');
            }, 10);
        }
    });

    function closeRatingModal() {
        const modal = document.getElementById('rating-success-modal');
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.remove('scale-100');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            // URL'i temizle
            const url = new URL(window.location);
            url.searchParams.delete('status');
            url.searchParams.delete('msg');
            window.history.replaceState({}, '', url);
        }, 300);
    }
</script>

<?php require_once 'includes/footer.php'; ?>