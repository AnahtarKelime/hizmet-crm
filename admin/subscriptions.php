<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Silme İşlemi
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM subscription_packages WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: subscriptions.php?msg=deleted");
    exit;
}

$packages = $pdo->query("SELECT * FROM subscription_packages ORDER BY price ASC")->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Abonelik Paketleri</h2>
        <p class="text-slate-500 text-sm">Hizmet verenler için paketleri ve özellikleri yönetin.</p>
    </div>
    <a href="subscription-form.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors">
        <span class="material-symbols-outlined text-lg">add</span>
        Yeni Paket Ekle
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach($packages as $pkg): 
        $features = json_decode($pkg['features'], true) ?? [];
    ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
        <div class="p-6 border-b border-slate-100">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-bold text-slate-800"><?= htmlspecialchars($pkg['name']) ?></h3>
                <span class="px-2 py-1 rounded text-xs font-bold <?= $pkg['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= $pkg['is_active'] ? 'Aktif' : 'Pasif' ?>
                </span>
            </div>
            <div class="flex items-baseline gap-1">
                <span class="text-3xl font-black text-indigo-600"><?= number_format($pkg['price'], 0, ',', '.') ?> ₺</span>
                <span class="text-sm text-slate-500 font-medium">/ <?= $pkg['duration_days'] ?> Gün</span>
            </div>
        </div>
        
        <div class="p-6 flex-1 bg-slate-50/50">
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Özellikler</h4>
            <ul class="space-y-3">
                <?php foreach($features as $feature): ?>
                <li class="flex items-start gap-3 text-sm text-slate-600">
                    <span class="material-symbols-outlined text-green-500 text-lg">check_circle</span>
                    <span><?= htmlspecialchars($feature) ?></span>
                </li>
                <?php endforeach; ?>
                <?php if(empty($features)): ?>
                    <li class="text-sm text-slate-400 italic">Özellik belirtilmemiş.</li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="p-4 border-t border-slate-100 bg-white flex justify-end gap-3">
            <a href="subscription-form.php?id=<?= $pkg['id'] ?>" class="flex-1 py-2 text-center rounded-lg border border-slate-200 text-slate-600 font-bold text-sm hover:bg-slate-50 transition-colors">
                Düzenle
            </a>
            <a href="subscriptions.php?delete=<?= $pkg['id'] ?>" onclick="return confirm('Bu paketi silmek istediğinize emin misiniz?')" class="px-3 py-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors flex items-center justify-center">
                <span class="material-symbols-outlined text-lg">delete</span>
            </a>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Yeni Ekle Kartı (Opsiyonel Görsel) -->
    <a href="subscription-form.php" class="border-2 border-dashed border-slate-200 rounded-xl flex flex-col items-center justify-center p-8 text-slate-400 hover:border-indigo-300 hover:text-indigo-500 transition-all cursor-pointer group min-h-[300px]">
        <span class="material-symbols-outlined text-4xl mb-2 group-hover:scale-110 transition-transform">add_circle</span>
        <span class="font-bold">Yeni Paket Oluştur</span>
    </a>
</div>

<?php require_once 'includes/footer.php'; ?>