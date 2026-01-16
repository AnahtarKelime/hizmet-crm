<?php
require_once 'config/db.php';

// Oturumu başlat (header.php'den önce işlem yaptığımız için gerekli)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$demandId = $_GET['id'] ?? null;

if (!$demandId) {
    header("Location: my-demands.php");
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

// Yetki Kontrolü Değişkenleri
$isOwner = ($demand && $demand['user_id'] == $userId);
$isProvider = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'provider');

// Provider için kredi ve teklif durumu kontrolü
$hasOffered = false;
$hasCredit = false;
$providerDetails = null;
$templates = [];
$myOffer = null;

if ($isProvider && !$isOwner && $demand) {
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

    if ($providerDetails && $providerDetails['subscription_ends_at'] && new DateTime($providerDetails['subscription_ends_at']) > new DateTime()) {
        if ($providerDetails['remaining_offer_credit'] > 0 || $providerDetails['remaining_offer_credit'] == -1) {
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
        cq.question_text 
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
?>

<main class="max-w-7xl mx-auto px-4 py-12 min-h-[60vh]">
    <div class="flex items-center gap-4 mb-8">
        <a href="my-demands.php" class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <h1 class="text-3xl font-black text-slate-800">Talep Detayı</h1>
    </div>

    <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
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
        <!-- Sol Kolon: Talep Bilgileri -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <span class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-bold uppercase tracking-wider mb-2 inline-block">
                            <?= htmlspecialchars($demand['category_name']) ?>
                        </span>
                        <h2 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($demand['title']) ?></h2>
                    </div>
                    <div class="text-right">
                        <span class="block text-xs text-slate-400 mb-1">Durum</span>
                        <span class="px-3 py-1 rounded-full text-xs font-bold 
                            <?= $demand['status'] == 'pending' ? 'bg-yellow-100 text-yellow-700' : 
                               ($demand['status'] == 'approved' ? 'bg-green-100 text-green-700' : 
                               ($demand['status'] == 'completed' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700')) ?>">
                            <?= $demand['status'] == 'pending' ? 'Beklemede' : 
                               ($demand['status'] == 'approved' ? 'Onaylandı' : 
                               ($demand['status'] == 'completed' ? 'Tamamlandı' : 'İptal')) ?>
                        </span>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm border-t border-slate-100 pt-4">
                    <div>
                        <span class="block text-slate-500 mb-1">Lokasyon</span>
                        <span class="font-medium text-slate-800 flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">location_on</span>
                            <?php if (!empty($demand['address_text'])): ?>
                                <?= htmlspecialchars($demand['address_text']) ?>
                            <?php else: ?>
                                <?= htmlspecialchars($demand['city'] . ' / ' . $demand['district'] . ' / ' . $demand['neighborhood']) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div>
                        <span class="block text-slate-500 mb-1">Oluşturulma Tarihi</span>
                        <span class="font-medium text-slate-800 flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">event</span>
                            <?= date('d.m.Y H:i', strtotime($demand['created_at'])) ?>
                        </span>
                    </div>
                </div>

                <?php if (!empty($demand['latitude']) && !empty($demand['longitude'])): ?>
                    <div class="mt-6 pt-4 border-t border-slate-100">
                        <h3 class="font-bold text-slate-800 mb-3">Harita Konumu</h3>
                        <div id="map" class="w-full h-64 rounded-xl bg-slate-50 border border-slate-200"></div>
                        <script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($siteSettings['google_maps_api_key'] ?? '') ?>&callback=initMap" async defer></script>
                        <script>
                            function initMap() {
                                // Sitenizin tasarımına uygun özel harita stili (Sade/Gri Tonlar)
                                const mapStyles = [
                                    { "featureType": "water", "elementType": "geometry", "stylers": [{ "color": "#e9e9e9" }, { "lightness": 17 }] },
                                    { "featureType": "landscape", "elementType": "geometry", "stylers": [{ "color": "#f5f5f5" }, { "lightness": 20 }] },
                                    { "featureType": "road.highway", "elementType": "geometry.fill", "stylers": [{ "color": "#ffffff" }, { "lightness": 17 }] },
                                    { "featureType": "road.highway", "elementType": "geometry.stroke", "stylers": [{ "color": "#ffffff" }, { "lightness": 29 }, { "weight": 0.2 }] },
                                    { "featureType": "road.arterial", "elementType": "geometry", "stylers": [{ "color": "#ffffff" }, { "lightness": 18 }] },
                                    { "featureType": "road.local", "elementType": "geometry", "stylers": [{ "color": "#ffffff" }, { "lightness": 16 }] },
                                    { "featureType": "poi", "elementType": "geometry", "stylers": [{ "color": "#f5f5f5" }, { "lightness": 21 }] },
                                    { "featureType": "poi.park", "elementType": "geometry", "stylers": [{ "color": "#dedede" }, { "lightness": 21 }] },
                                    { "elementType": "labels.text.stroke", "stylers": [{ "visibility": "on" }, { "color": "#ffffff" }, { "lightness": 16 }] },
                                    { "elementType": "labels.text.fill", "stylers": [{ "saturation": 36 }, { "color": "#333333" }, { "lightness": 40 }] },
                                    { "elementType": "labels.icon", "stylers": [{ "visibility": "off" }] },
                                    { "featureType": "transit", "elementType": "geometry", "stylers": [{ "color": "#f2f2f2" }, { "lightness": 19 }] },
                                    { "featureType": "administrative", "elementType": "geometry.fill", "stylers": [{ "color": "#fefefe" }, { "lightness": 20 }] },
                                    { "featureType": "administrative", "elementType": "geometry.stroke", "stylers": [{ "color": "#fefefe" }, { "lightness": 17 }, { "weight": 1.2 }] }
                                ];

                                const position = { lat: <?= floatval($demand['latitude']) ?>, lng: <?= floatval($demand['longitude']) ?> };
                                const map = new google.maps.Map(document.getElementById("map"), {
                                    zoom: 15,
                                    center: position,
                                    disableDefaultUI: true,
                                    zoomControl: true,
                                    styles: mapStyles // Stili buraya ekliyoruz
                                });
                                new google.maps.Marker({ position: position, map: map });
                            }
                        </script>
                    </div>
                <?php endif; ?>

                <div class="mt-6">
                    <h3 class="font-bold text-slate-800 mb-3">Detaylar</h3>
                    <div class="bg-slate-50 rounded-xl p-4 space-y-3">
                        <?php foreach ($answers as $ans): ?>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <span class="text-sm font-medium text-slate-500"><?= htmlspecialchars($ans['question_text']) ?></span>
                                <span class="text-sm font-bold text-slate-800 sm:col-span-2"><?= htmlspecialchars($ans['answer_text']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if ($isOwner): // Müşteri Görünümü ?>
                <!-- Gelen Teklifler -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">local_offer</span>
                        Gelen Teklifler (<?= count($offers) ?>)
                    </h3>
                    
                    <?php if (empty($offers)): ?>
                        <div class="text-center py-8 text-slate-500 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                            Henüz bu talep için teklif gelmedi. <br>
                            Hizmet verenler talebini incelediğinde burada tekliflerini göreceksin.
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($offers as $offer): ?>
                                <?php
                                    $borderColor = 'border-slate-100';
                                    if ($offer['status'] === 'accepted') $borderColor = 'border-green-500 ring-1 ring-green-500';
                                    if ($offer['status'] === 'rejected') $borderColor = 'border-red-500 ring-1 ring-red-500';
                                ?>
                                <div class="bg-white p-6 rounded-2xl shadow-sm border <?= $borderColor ?> relative overflow-hidden transition-all hover:shadow-md group">
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 font-bold text-lg border-2 border-white shadow-sm group-hover:border-primary/20 transition-colors">
                                                <?= mb_substr($offer['first_name'], 0, 1) . mb_substr($offer['last_name'], 0, 1) ?>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-slate-800 text-lg"><?= htmlspecialchars($offer['business_name'] ?: $offer['first_name'] . ' ' . $offer['last_name']) ?></h4>
                                                <div class="flex items-center gap-1 text-xs text-slate-500">
                                                    <span class="material-symbols-outlined text-[14px] text-yellow-500 fill-1">star</span>
                                                    <span>4.9 (24 Değerlendirme)</span>
                                                    <span class="mx-1">•</span>
                                                    <span class="text-green-600 font-medium bg-green-50 px-1.5 py-0.5 rounded">Onaylı</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-2xl font-black text-primary"><?= number_format($offer['price'], 2, ',', '.') ?> ₺</div>
                                            <span class="text-xs text-slate-400"><?= date('d.m.Y', strtotime($offer['created_at'])) ?></span>
                                        </div>
                                    </div>
                                    <p class="text-sm text-slate-600 bg-slate-50 p-4 rounded-xl mb-4 line-clamp-2 border border-slate-100">
                                        <?= nl2br(htmlspecialchars($offer['message'])) ?>
                                    </p>
                                    
                                    <div class="flex flex-wrap justify-end gap-3 pt-2 border-t border-slate-50">
                                        <?php if ($offer['status'] === 'pending'): ?>
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="action" value="accept">
                                                <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                                                <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors flex items-center gap-1" onclick="return confirm('Bu teklifi kabul etmek istediğinize emin misiniz?')">
                                                    <span class="material-symbols-outlined text-sm">check_circle</span> Kabul Et
                                                </button>
                                            </form>
                                            
                                            <a href="messages.php?offer_id=<?= $offer['id'] ?>" class="px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-1">
                                                <span class="material-symbols-outlined text-sm">chat</span> Mesaj
                                            </a>

                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                                                <button type="submit" class="px-4 py-2 text-xs font-bold text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50 transition-colors flex items-center gap-1" onclick="return confirm('Bu teklifi reddetmek istediğinize emin misiniz?')">
                                                    <span class="material-symbols-outlined text-sm">cancel</span> Reddet
                                                </button>
                                            </form>
                                        <?php elseif ($offer['status'] === 'accepted'): ?>
                                            <span class="px-4 py-2 text-xs font-bold text-green-700 bg-green-100 rounded-lg border border-green-200 flex items-center gap-1"><span class="material-symbols-outlined text-sm">verified</span> Kabul Edildi</span>
                                            <a href="messages.php?offer_id=<?= $offer['id'] ?>" class="px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-1">
                                                <span class="material-symbols-outlined text-sm">chat</span> Mesaj
                                            </a>
                                            <?php if (!$offer['has_reviewed']): ?>
                                                <a href="rate-provider.php?offer_id=<?= $offer['id'] ?>" class="px-4 py-2 text-xs font-bold text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-lg hover:bg-yellow-100 transition-colors flex items-center gap-1">
                                                    <span class="material-symbols-outlined text-sm">star</span> Hizmeti Değerlendir
                                                </a>
                                            <?php endif; ?>
                                        <?php elseif ($offer['status'] === 'rejected'): ?>
                                            <span class="px-4 py-2 text-xs font-bold text-red-700 bg-red-100 rounded-lg border border-red-200 flex items-center gap-1"><span class="material-symbols-outlined text-sm">block</span> Reddedildi</span>
                                        <?php endif; ?>

                                        <a href="offer-details.php?id=<?= $offer['id'] ?>" class="px-5 py-2.5 text-sm font-bold text-slate-700 border border-slate-200 rounded-xl hover:bg-slate-50 flex items-center gap-2 transition-colors">
                                            <span class="material-symbols-outlined text-sm">visibility</span> İncele
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($isProvider): // Hizmet Veren Görünümü ?>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">edit_note</span>
                        Teklif Ver
                    </h3>
                    <?php if ($hasOffered): ?>
                        <div class="text-center py-8 text-green-700 bg-green-50 rounded-xl border border-dashed border-green-200">
                            Bu talebe zaten teklif verdiniz.
                        </div>
                    <?php elseif ($hasCredit): ?>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="submit_offer" value="1">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Teklif Fiyatınız (₺)</label>
                                <input type="number" name="price" step="0.01" required class="w-full rounded-lg border-slate-300 focus:border-primary focus:ring-primary">
                            </div>
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <label class="block text-sm font-bold text-slate-700">Müşteriye Mesajınız</label>
                                    <?php if (!empty($templates)): ?>
                                        <select onchange="insertTemplate(this)" class="text-xs border-slate-200 rounded-lg py-1 pl-2 pr-8 focus:ring-primary focus:border-primary text-slate-600">
                                            <option value="">Şablon Seç...</option>
                                            <?php foreach ($templates as $tpl): ?>
                                                <option value="<?= htmlspecialchars($tpl['message']) ?>"><?= htmlspecialchars($tpl['title']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <a href="provider/templates.php" class="text-xs text-primary hover:underline font-medium flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px]">add_circle</span> Şablon Oluştur
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <textarea name="message" id="offerMessage" rows="4" required class="w-full rounded-lg border-slate-300 focus:border-primary focus:ring-primary" placeholder="İşle ilgili detayları, neden sizi seçmesi gerektiğini ve süreci anlatın..."></textarea>
                                <script>
                                    function insertTemplate(select) {
                                        if(select.value) document.getElementById('offerMessage').value = select.value;
                                    }
                                </script>
                            </div>
                            <button type="submit" class="w-full py-3 bg-primary text-white font-bold rounded-xl hover:bg-primary/90 shadow-lg">Teklifi Gönder (-1 Kredi)</button>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-8 text-red-700 bg-red-50 rounded-xl border border-dashed border-red-200">
                            Teklif vermek için yeterli krediniz bulunmuyor.
                            <a href="provider/buy-package.php" class="block mt-4 font-bold underline">Hemen Kredi Satın Al</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sağ Kolon: Bilgi -->
        <div class="space-y-6">
            <?php if ($isProvider && !empty($myOffer) && $myOffer['status'] === 'accepted'): ?>
                <div class="bg-green-50 p-6 rounded-2xl border border-green-100 shadow-sm">
                    <h4 class="font-bold text-green-800 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined">person</span>
                        Müşteri Detayları
                    </h4>
                    <div class="space-y-3">
                        <div>
                            <span class="text-xs font-bold text-green-600 uppercase tracking-wider block mb-1">Ad Soyad</span>
                            <p class="font-bold text-slate-800"><?= htmlspecialchars($demand['first_name'] . ' ' . $demand['last_name']) ?></p>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-green-600 uppercase tracking-wider block mb-1">Telefon</span>
                            <a href="tel:<?= htmlspecialchars($demand['phone']) ?>" class="font-bold text-slate-800 hover:text-green-700 transition-colors"><?= htmlspecialchars($demand['phone']) ?></a>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-green-600 uppercase tracking-wider block mb-1">E-posta</span>
                            <a href="mailto:<?= htmlspecialchars($demand['email']) ?>" class="font-bold text-slate-800 hover:text-green-700 transition-colors"><?= htmlspecialchars($demand['email']) ?></a>
                        </div>
                        <div class="pt-2">
                            <a href="messages.php?offer_id=<?= $myOffer['id'] ?>" class="w-full py-3 bg-white text-green-700 font-bold rounded-xl border border-green-200 hover:bg-green-100 transition-colors flex items-center justify-center gap-2 text-sm shadow-sm">
                                <span class="material-symbols-outlined text-sm">chat</span> Mesaj Gönder
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-blue-50 p-6 rounded-2xl border border-blue-100">
                <h4 class="font-bold text-blue-800 mb-2 flex items-center gap-2">
                    <span class="material-symbols-outlined">info</span>
                    Bilgilendirme
                </h4>
                <p class="text-sm text-blue-700 leading-relaxed">
                    Talebini oluşturduğun için teşekkürler! İlgili hizmet verenlere bildirim gönderdik. Genellikle 24 saat içinde ilk teklifler gelmeye başlar.
                </p>
            </div>
            
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <h4 class="font-bold text-slate-800 mb-4">Süreç Nasıl İşler?</h4>
                <ul class="space-y-4">
                    <li class="flex gap-3 text-sm text-slate-600">
                        <span class="flex-shrink-0 w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center font-bold text-xs">1</span>
                        <span>Talebin onaylandı ve hizmet verenlere iletildi.</span>
                    </li>
                    <li class="flex gap-3 text-sm text-slate-600">
                        <span class="flex-shrink-0 w-6 h-6 bg-slate-100 text-slate-500 rounded-full flex items-center justify-center font-bold text-xs">2</span>
                        <span>Gelen teklifleri incele ve karşılaştır.</span>
                    </li>
                    <li class="flex gap-3 text-sm text-slate-600">
                        <span class="flex-shrink-0 w-6 h-6 bg-slate-100 text-slate-500 rounded-full flex items-center justify-center font-bold text-xs">3</span>
                        <span>Sana en uygun uzmanı seç ve işi başlat.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>