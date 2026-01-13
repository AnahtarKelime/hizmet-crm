<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Kategorileri Çek
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Seçili Kategori (Varsayılan olarak ilk kategori)
$selectedCategoryId = $_GET['category_id'] ?? ($categories[0]['id'] ?? null);

// Talepleri Çek
$demands = [];
if ($selectedCategoryId) {
    $sql = "SELECT 
                d.*, 
                u.first_name, u.last_name, 
                c.name as category_name, 
                l.city, l.district, l.neighborhood 
            FROM demands d
            LEFT JOIN users u ON d.user_id = u.id
            LEFT JOIN categories c ON d.category_id = c.id
            LEFT JOIN locations l ON d.location_id = l.id
            WHERE d.category_id = ? AND d.is_archived = 0
            ORDER BY d.created_at DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$selectedCategoryId]);
        $demands = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Veri çekilirken hata oluştu: " . $e->getMessage();
    }
}
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Kategoriye Göre Talepler</h2>
        <p class="text-slate-500 text-sm">Belirli bir kategorideki talepleri görüntüleyin.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Sol Menü: Kategoriler -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 bg-slate-50 border-b border-slate-200 font-bold text-slate-700">
                Kategoriler
            </div>
            <nav class="flex flex-col p-2 space-y-1 max-h-[calc(100vh-300px)] overflow-y-auto">
                <?php foreach($categories as $cat): ?>
                    <a href="demands-by-category.php?category_id=<?= $cat['id'] ?>" 
                       class="px-3 py-2 rounded-lg text-sm font-medium transition-colors flex justify-between items-center <?= $selectedCategoryId == $cat['id'] ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                        <?php if($selectedCategoryId == $cat['id']): ?>
                            <span class="material-symbols-outlined text-lg">chevron_right</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>

    <!-- Sağ Taraf: Talep Listesi -->
    <div class="lg:col-span-3">
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?= $error ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4">ID</th>
                            <th class="px-6 py-4">Müşteri</th>
                            <th class="px-6 py-4">Lokasyon</th>
                            <th class="px-6 py-4">Başlık</th>
                            <th class="px-6 py-4">Tarih</th>
                            <th class="px-6 py-4">Durum</th>
                            <th class="px-6 py-4 text-right">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($demands)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                                    Bu kategoride henüz talep bulunmuyor.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($demands as $demand): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 font-mono text-xs text-slate-400">#<?= $demand['id'] ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-800"><?= htmlspecialchars($demand['first_name'] . ' ' . $demand['last_name']) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs text-slate-500"><?= htmlspecialchars($demand['city'] . ' / ' . $demand['district']) ?></div>
                                </td>
                                <td class="px-6 py-4 font-medium text-slate-700"><?= htmlspecialchars($demand['title']) ?></td>
                                <td class="px-6 py-4 text-xs text-slate-500">
                                    <?= date('d.m.Y H:i', strtotime($demand['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-700',
                                        'approved' => 'bg-green-100 text-green-700',
                                        'completed' => 'bg-blue-100 text-blue-700',
                                        'cancelled' => 'bg-red-100 text-red-700'
                                    ];
                                    $statusLabels = [
                                        'pending' => 'Beklemede',
                                        'approved' => 'Onaylandı',
                                        'completed' => 'Tamamlandı',
                                        'cancelled' => 'İptal'
                                    ];
                                    $statusClass = $statusColors[$demand['status']] ?? 'bg-gray-100 text-gray-700';
                                    $statusLabel = $statusLabels[$demand['status']] ?? $demand['status'];
                                    ?>
                                    <span class="px-2 py-1 rounded text-xs font-bold <?= $statusClass ?>">
                                        <?= $statusLabel ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="demand-details.php?id=<?= $demand['id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-medium text-xs bg-indigo-50 px-3 py-1.5 rounded transition-colors">Detay</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>