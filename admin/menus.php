<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Silme İşlemi
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: menus.php?msg=deleted");
    exit;
}

$headerItems = $pdo->query("SELECT * FROM menu_items WHERE menu_location = 'header' ORDER BY sort_order ASC")->fetchAll();
$footerItems = $pdo->query("SELECT * FROM menu_items WHERE menu_location = 'footer' ORDER BY sort_order ASC")->fetchAll();

$visibilityLabels = [
    'all' => 'Herkes',
    'guest' => 'Misafir',
    'customer' => 'Müşteri',
    'provider' => 'Hizmet Veren'
];

$visibilityColors = [
    'all' => 'bg-slate-100 text-slate-600',
    'guest' => 'bg-blue-100 text-blue-700',
    'customer' => 'bg-green-100 text-green-700',
    'provider' => 'bg-purple-100 text-purple-700'
];
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Menü Yönetimi</h2>
        <p class="text-slate-500 text-sm">Header ve footer alanlarındaki menü linklerini yönetin.</p>
    </div>
    <a href="menu-form.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors">
        <span class="material-symbols-outlined text-lg">add</span>
        Yeni Link Ekle
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Header Menüsü -->
    <div>
        <h3 class="text-lg font-bold text-slate-800 mb-4">Header Menüsü</h3>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Sıra</th>
                        <th class="px-4 py-3">Başlık</th>
                        <th class="px-4 py-3">Görünürlük</th>
                        <th class="px-4 py-3 text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach($headerItems as $item): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3 font-mono text-xs text-slate-400"><?= $item['sort_order'] ?></td>
                        <td class="px-4 py-3 font-bold text-slate-800"><?= htmlspecialchars($item['title']) ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs font-bold <?= $visibilityColors[$item['visibility']] ?? 'bg-gray-100' ?>">
                                <?= $visibilityLabels[$item['visibility']] ?? $item['visibility'] ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <a href="menu-form.php?id=<?= $item['id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-medium">Düzenle</a>
                            <a href="menus.php?delete=<?= $item['id'] ?>" onclick="return confirm('Bu linki silmek istediğinize emin misiniz?')" class="text-red-500 hover:text-red-700 font-medium">Sil</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Footer Menüsü -->
    <div>
        <h3 class="text-lg font-bold text-slate-800 mb-4">Footer Menüsü</h3>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Sıra</th>
                        <th class="px-4 py-3">Başlık</th>
                        <th class="px-4 py-3">Görünürlük</th>
                        <th class="px-4 py-3 text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach($footerItems as $item): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3 font-mono text-xs text-slate-400"><?= $item['sort_order'] ?></td>
                        <td class="px-4 py-3 font-bold text-slate-800"><?= htmlspecialchars($item['title']) ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs font-bold <?= $visibilityColors[$item['visibility']] ?? 'bg-gray-100' ?>">
                                <?= $visibilityLabels[$item['visibility']] ?? $item['visibility'] ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <a href="menu-form.php?id=<?= $item['id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-medium">Düzenle</a>
                            <a href="menus.php?delete=<?= $item['id'] ?>" onclick="return confirm('Bu linki silmek istediğinize emin misiniz?')" class="text-red-500 hover:text-red-700 font-medium">Sil</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>