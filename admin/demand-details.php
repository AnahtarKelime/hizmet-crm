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
                    <span class="font-medium text-slate-800"><?= htmlspecialchars($demand['city'] . ' / ' . $demand['district'] . ' / ' . $demand['neighborhood']) ?></span>
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

            <div class="mt-6">
                <h4 class="font-bold text-slate-800 mb-2">Soru & Cevaplar</h4>
                <div class="space-y-3 bg-slate-50 p-4 rounded-lg">
                    <?php foreach ($answers as $ans): ?>
                        <div>
                            <p class="text-xs text-slate-500 font-bold"><?= htmlspecialchars($ans['question_text']) ?></p>
                            <p class="text-sm text-slate-700"><?= htmlspecialchars($ans['answer_text']) ?></p>
                        </div>
                    <?php endforeach; ?>
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
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>