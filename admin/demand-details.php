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
        u.first_name, u.last_name, u.email, u.phone, u.whatsapp,
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
        cq.question_text,
        cq.input_type
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
    <a href="print-demand.php?id=<?= $demand['id'] ?>" target="_blank" class="bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors shadow-sm">
        <span class="material-symbols-outlined">print</span>
        Sözleşme / PDF Yazdır
    </a>
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
                    <div class="flex items-center gap-2 mt-1">
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($demand['phone']) ?></div>
                        <?php 
                        $rawPhone = preg_replace('/[^0-9]/', '', $demand['phone']);
                        if (strlen($rawPhone) > 10) $rawPhone = substr($rawPhone, -10);
                        
                        $waNumber = !empty($demand['whatsapp']) ? preg_replace('/[^0-9]/', '', $demand['whatsapp']) : $rawPhone;
                        if (strlen($waNumber) > 10) $waNumber = substr($waNumber, -10);
                        
                        if (strlen($waNumber) === 10): 
                        ?>
                        <a href="https://wa.me/90<?= $waNumber ?>" target="_blank" class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-green-100 text-green-600 hover:bg-green-600 hover:text-white transition-all" title="WhatsApp ile İletişime Geç">
                            <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.536 0 1.52 1.115 2.988 1.264 3.186.149.198 2.19 3.361 5.27 4.713 2.179.957 3.039.768 4.117.669 1.186-.109 2.592-1.057 2.964-2.077.372-1.019.372-1.893.26-2.077-.112-.184-.411-.298-.709-.447zM12 21.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.83.51-3.54 1.39-5.02L2.5 2.5l5.16 1.18c1.42-.8 3.06-1.26 4.84-1.26 5.385 0 9.75 4.365 9.75 9.75s-4.365 9.75-9.75 9.75z"/></svg>
                        </a>
                        <?php endif; ?>
                    </div>
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
                            <?php if (isset($ans['input_type']) && $ans['input_type'] === 'image'): ?>
                                <div class="mt-2">
                                    <a href="../<?= htmlspecialchars($ans['answer_text']) ?>" target="_blank" class="block w-32 h-32 rounded-lg overflow-hidden border border-slate-200 relative group">
                                        <img src="../<?= htmlspecialchars($ans['answer_text']) ?>" class="w-full h-full object-cover">
                                        <div class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                            <span class="material-symbols-outlined text-white">visibility</span>
                                        </div>
                                    </a>
                                </div>
                            <?php else: ?>
                                <p class="text-sm text-slate-700"><?= htmlspecialchars($ans['answer_text']) ?></p>
                            <?php endif; ?>
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