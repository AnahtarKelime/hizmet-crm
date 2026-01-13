<?php
require_once '../config/db.php';

$id = $_GET['id'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $duration = $_POST['duration_days'];
    $offerCredit = $_POST['offer_credit'];
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Textarea'dan gelen veriyi satır satır bölüp JSON yap
    $featuresList = array_filter(array_map('trim', explode("\n", $_POST['features'])));
    $featuresJson = json_encode(array_values($featuresList), JSON_UNESCAPED_UNICODE);

    if ($id) {
        $stmt = $pdo->prepare("UPDATE subscription_packages SET name=?, price=?, duration_days=?, offer_credit=?, features=?, is_active=? WHERE id=?");
        $stmt->execute([$name, $price, $duration, $offerCredit, $featuresJson, $isActive, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO subscription_packages (name, price, duration_days, offer_credit, features, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $price, $duration, $offerCredit, $featuresJson, $isActive]);
    }
    header("Location: subscriptions.php");
    exit;
}

require_once 'includes/header.php';

$package = ['name' => '', 'price' => '', 'duration_days' => 30, 'offer_credit' => 0, 'features' => '', 'is_active' => 1];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM subscription_packages WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    if ($data) {
        $package = $data;
        // JSON verisini textarea için satırlara dönüştür
        $featuresArray = json_decode($package['features'], true);
        $package['features'] = is_array($featuresArray) ? implode("\n", $featuresArray) : '';
    }
}
?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="subscriptions.php" class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <h2 class="text-2xl font-bold text-slate-800"><?= $id ? 'Paketi Düzenle' : 'Yeni Paket Ekle' ?></h2>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8">
        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Paket Adı</label>
                <input type="text" name="name" value="<?= htmlspecialchars($package['name']) ?>" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Örn: Profesyonel Paket">
            </div>

            <div class="grid grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Fiyat (TL)</label>
                    <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($package['price']) ?>" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Süre (Gün)</label>
                    <input type="number" name="duration_days" value="<?= htmlspecialchars($package['duration_days']) ?>" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Teklif Kredisi</label>
                    <input type="number" name="offer_credit" value="<?= htmlspecialchars($package['offer_credit']) ?>" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-slate-500 mt-1">Sınırsız için -1 girin.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">
                    Paket Özellikleri
                    <span class="block text-xs font-normal text-slate-500 mt-1">Her satıra bir özellik yazınız.</span>
                </label>
                <textarea name="features" rows="6" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Ayda 50 Teklif Hakkı&#10;Öne Çıkan Profil&#10;7/24 Destek"><?= htmlspecialchars($package['features']) ?></textarea>
            </div>

            <div class="p-4 border border-slate-200 rounded-lg bg-slate-50">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" <?= $package['is_active'] ? 'checked' : '' ?> class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500">
                    <div>
                        <span class="block font-bold text-slate-700">Aktif Paket</span>
                        <span class="text-xs text-slate-500">Bu paket hizmet verenler tarafından satın alınabilir olsun.</span>
                    </div>
                </label>
            </div>

            <div class="pt-6 border-t border-slate-100 flex justify-end gap-4">
                <a href="subscriptions.php" class="px-6 py-3 rounded-lg text-slate-600 font-bold hover:bg-slate-100 transition-colors">İptal</a>
                <button type="submit" class="px-8 py-3 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">
                    <?= $id ? 'Değişiklikleri Kaydet' : 'Paketi Oluştur' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>