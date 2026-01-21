<?php
require_once '../config/db.php';
require_once 'includes/header.php';

$demandId = $_GET['id'] ?? null;

if (!$demandId) {
    header("Location: demands.php");
    exit;
}

// Durum Güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE demands SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $demandId]);
    $successMsg = "Talep durumu güncellendi.";
}

// Teklif Miktarı Güncelleme (Yönetici tarafından)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_offer_price'])) {
    // Bu kısım, eğer talep için bir "taban fiyat" veya "tahmini maliyet" alanı demands tablosuna eklenirse kullanılabilir.
    // Şimdilik offers tablosundaki bir teklifi güncellemek yerine, genel bir işlem olarak düşünebiliriz.
    // Ancak, demands tablosunda 'budget' gibi bir alan olmadığı için bu kısmı şimdilik pas geçiyorum veya
    // offers tablosunda yönetici adına bir teklif oluşturulabilir.
    // Senaryo gereği, demands tablosuna 'estimated_cost' (tahmini maliyet) ekleyip onu güncelleyelim.
    
    // Önce sütun var mı kontrol edelim, yoksa ekleyelim (Geliştirme aşamasında pratiklik için)
    try {
        $pdo->query("SELECT estimated_cost FROM demands LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE demands ADD estimated_cost DECIMAL(10,2) DEFAULT 0.00");
    }

    $estimatedCost = $_POST['estimated_cost'];
    $stmt = $pdo->prepare("UPDATE demands SET estimated_cost = ? WHERE id = ?");
    $stmt->execute([$estimatedCost, $demandId]);
    $successMsg = "Tahmini maliyet güncellendi.";
}

// Arşivleme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_archive'])) {
    $newArchiveStatus = $_POST['is_archived'] == 1 ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE demands SET is_archived = ? WHERE id = ?");
    $stmt->execute([$newArchiveStatus, $demandId]);
    $successMsg = $newArchiveStatus ? "Talep arşivlendi." : "Talep arşivden çıkarıldı.";
}

// Talep Detaylarını Çek
$stmt = $pdo->prepare("
    SELECT 
        d.*, 
        u.first_name, u.last_name, u.email, u.phone,
        c.name as category_name, 
        l.city, l.district, l.neighborhood 
    FROM demands d
    LEFT JOIN users u ON d.user_id = u.id
    LEFT JOIN categories c ON d.category_id = c.id
    LEFT JOIN locations l ON d.location_id = l.id
    WHERE d.id = ?
");
$stmt->execute([$demandId]);
$demand = $stmt->fetch();

if (!$demand) {
    echo "<div class='p-8 text-center text-red-500'>Talep bulunamadı.</div>";
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

// Konum ve metin cevaplarını ayır
$locationPoints = [];
$textAnswers = [];
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
    } else {
        $textAnswers[] = $ans;
    }
}
?>

<div class="flex justify-between items-center mb-6">
    <div class="flex items-center gap-4">
        <a href="demands.php" class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Talep Detayı #<?= $demand['id'] ?></h2>
            <p class="text-slate-500 text-sm"><?= htmlspecialchars($demand['title']) ?></p>
        </div>
    </div>
</div>

<?php if (isset($successMsg)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">
        <?= $successMsg ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Sol Kolon: Talep Bilgileri -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Talep Bilgileri</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="block text-slate-500">Kategori</span>
                    <span class="font-medium text-slate-800"><?= htmlspecialchars($demand['category_name']) ?></span>
                </div>
                <div>
                    <span class="block text-slate-500">Lokasyon</span>
                    <span class="font-medium text-slate-800">
                        <?php if (!empty($demand['address_text'])): ?>
                            <?= htmlspecialchars($demand['address_text']) ?>
                        <?php else: ?>
                            <?= htmlspecialchars($demand['city'] . ' / ' . $demand['district'] . ' / ' . $demand['neighborhood']) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div>
                    <span class="block text-slate-500">Oluşturulma Tarihi</span>
                    <span class="font-medium text-slate-800"><?= date('d.m.Y H:i', strtotime($demand['created_at'])) ?></span>
                </div>
                <div>
                    <span class="block text-slate-500">Müşteri</span>
                    <span class="font-medium text-slate-800"><?= htmlspecialchars($demand['first_name'] . ' ' . $demand['last_name']) ?></span>
                    <div class="text-xs text-slate-400"><?= htmlspecialchars($demand['phone']) ?></div>
                </div>
            </div>

            <?php if (!empty($locationPoints)): ?>
            <div class="mt-6">
                <h4 class="font-bold text-slate-800 mb-2">Konum Haritası</h4>
                <div id="map" class="w-full h-80 rounded-lg bg-slate-100 border border-slate-200"></div>
            </div>
            <?php endif; ?>

            <div class="mt-6">
                <h4 class="font-bold text-slate-800 mb-2">Soru & Cevaplar</h4>
                <div class="space-y-3 bg-slate-50 p-4 rounded-lg">
                    <?php if (empty($textAnswers)): ?>
                        <p class="text-sm text-slate-500">Metin tabanlı soru-cevap bulunmuyor.</p>
                    <?php else: ?>
                        <?php foreach ($textAnswers as $ans): ?>
                        <div>
                            <p class="text-xs text-slate-500 font-bold"><?= htmlspecialchars($ans['question_text']) ?></p>
                            <p class="text-sm text-slate-700"><?= htmlspecialchars($ans['answer_text']) ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Hizmet Veren Eşleşmesi (Gelecek Özellik) -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 opacity-75">
            <h3 class="text-lg font-bold text-slate-800 mb-2">Uygun Hizmet Verenler</h3>
            <p class="text-sm text-slate-500 mb-4">Bu talep için uygun kriterlere (Lokasyon, Kategori, Abonelik) sahip hizmet verenler burada listelenecek.</p>
            <div class="text-center py-4 bg-slate-50 rounded border border-dashed border-slate-300 text-slate-400 text-sm">
                Otomatik eşleştirme sistemi yakında aktif olacak.
            </div>
        </div>
    </div>

    <!-- Sağ Kolon: Yönetim -->
    <div class="space-y-6">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Yönetim</h3>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="update_status" value="1">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Talep Durumu</label>
                    <select name="status" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="pending" <?= $demand['status'] == 'pending' ? 'selected' : '' ?>>Beklemede</option>
                        <option value="approved" <?= $demand['status'] == 'approved' ? 'selected' : '' ?>>Onaylandı</option>
                        <option value="completed" <?= $demand['status'] == 'completed' ? 'selected' : '' ?>>Tamamlandı</option>
                        <option value="cancelled" <?= $demand['status'] == 'cancelled' ? 'selected' : '' ?>>İptal Edildi</option>
                    </select>
                </div>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 rounded-lg transition-colors text-sm">Durumu Güncelle</button>
            </form>

            <hr class="my-6 border-slate-100">

            <form method="POST" class="space-y-4">
                <input type="hidden" name="update_offer_price" value="1">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tahmini Maliyet / Taban Fiyat</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-slate-400">₺</span>
                        <input type="number" step="0.01" name="estimated_cost" value="<?= $demand['estimated_cost'] ?? '0.00' ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 pl-7 text-sm">
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Bu fiyat hizmet verenlere referans olarak gösterilebilir.</p>
                </div>
                <button type="submit" class="w-full bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 font-bold py-2 rounded-lg transition-colors text-sm">Fiyatı Güncelle</button>
            </form>

            <hr class="my-6 border-slate-100">

            <form method="POST" class="space-y-4">
                <input type="hidden" name="toggle_archive" value="1">
                <input type="hidden" name="is_archived" value="<?= $demand['is_archived'] ?>">
                <button type="submit" class="w-full <?= $demand['is_archived'] ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-slate-600 hover:bg-slate-700' ?> text-white font-bold py-2 rounded-lg transition-colors text-sm flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-lg"><?= $demand['is_archived'] ? 'unarchive' : 'archive' ?></span>
                    <?= $demand['is_archived'] ? 'Arşivden Çıkar' : 'Talebi Arşivle' ?>
                </button>
            </form>
        </div>
    </div>
</div>

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

<?php require_once 'includes/footer.php'; ?>