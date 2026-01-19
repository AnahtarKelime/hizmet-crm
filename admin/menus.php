<?php
require_once '../config/db.php';

// Silme İşlemi
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: menus.php?msg=deleted");
    exit;
}

require_once 'includes/header.php';

$headerItems = $pdo->query("SELECT * FROM menu_items WHERE menu_location = 'header' ORDER BY sort_order ASC")->fetchAll();
$footerItems = $pdo->query("SELECT * FROM menu_items WHERE menu_location = 'footer' ORDER BY sort_order ASC")->fetchAll();
$megaMenuItemsRaw = $pdo->query("SELECT * FROM menu_items WHERE menu_location = 'mega_menu' ORDER BY sort_order ASC")->fetchAll();

// Mega Menü Ağaç Yapısı Oluştur
// Önce ebeveynlerin işlendiğinden emin olmak için sıralama yapıyoruz
usort($megaMenuItemsRaw, function($a, $b) {
    // Parent ID'si olmayanlar (Ana kategoriler) önce gelsin
    $aIsParent = empty($a['parent_id']);
    $bIsParent = empty($b['parent_id']);
    
    if ($aIsParent && !$bIsParent) return -1;
    if (!$aIsParent && $bIsParent) return 1;
    
    return $a['sort_order'] <=> $b['sort_order'];
});

$megaMenuTree = [];
foreach ($megaMenuItemsRaw as $item) {
    if (empty($item['parent_id'])) {
        $megaMenuTree[$item['id']] = $item;
        $megaMenuTree[$item['id']]['children'] = [];
    } else {
        $megaMenuTree[$item['parent_id']]['children'][] = $item;
    }
}

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

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
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

<!-- Mega Menü -->
<div class="mt-8">
    <h3 class="text-lg font-bold text-slate-800 mb-4">Mega Menü (Kategoriler)</h3>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php if (empty($megaMenuTree)): ?>
                <div class="col-span-full text-center text-slate-500 py-8">Mega menü için henüz kategori eklenmemiş.</div>
            <?php else: ?>
                <?php foreach($megaMenuTree as $parent): ?>
                <div class="border border-slate-200 rounded-xl p-4 bg-slate-50">
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex items-center gap-2 font-bold text-slate-800">
                            <?php if($parent['icon']): ?>
                                <span class="material-symbols-outlined text-indigo-600"><?= htmlspecialchars($parent['icon']) ?></span>
                            <?php endif; ?>
                            <?= htmlspecialchars($parent['title']) ?>
                        </div>
                        <div class="flex gap-1">
                            <a href="menu-form.php?id=<?= $parent['id'] ?>" class="text-indigo-600 hover:bg-indigo-50 p-1 rounded"><span class="material-symbols-outlined text-sm">edit</span></a>
                            <a href="menus.php?delete=<?= $parent['id'] ?>" onclick="return confirm('Bu kategoriyi ve altındakileri silmek istediğinize emin misiniz?')" class="text-red-500 hover:bg-red-50 p-1 rounded"><span class="material-symbols-outlined text-sm">delete</span></a>
                        </div>
                    </div>
                    
                    <?php if (!empty($parent['children'])): ?>
                        <ul class="space-y-2">
                            <?php foreach($parent['children'] as $child): ?>
                            <li class="flex justify-between items-center text-sm bg-white p-2 rounded border border-slate-100">
                                <span class="text-slate-600 truncate pr-2"><?= htmlspecialchars($child['title']) ?></span>
                                <div class="flex gap-1 shrink-0">
                                    <a href="menu-form.php?id=<?= $child['id'] ?>" class="text-indigo-400 hover:text-indigo-600"><span class="material-symbols-outlined text-[16px]">edit</span></a>
                                    <a href="menus.php?delete=<?= $child['id'] ?>" onclick="return confirm('Silmek istediğinize emin misiniz?')" class="text-red-400 hover:text-red-600"><span class="material-symbols-outlined text-[16px]">delete</span></a>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-xs text-slate-400 italic text-center py-2">Alt link yok</div>
                    <?php endif; ?>
                    
                    <div class="mt-3 pt-3 border-t border-slate-200 text-center">
                        <a href="menu-form.php" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">+ Alt Link Ekle</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>