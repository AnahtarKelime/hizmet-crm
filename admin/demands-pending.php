<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Sadece Beklemede (Pending) Olan Talepleri Çek
$sql = "SELECT 
            d.*, 
            u.first_name, u.last_name, 
            c.name as category_name, 
            l.city, l.district, l.neighborhood 
        FROM demands d
        LEFT JOIN users u ON d.user_id = u.id
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN locations l ON d.location_id = l.id
        WHERE d.status = 'pending' AND d.is_archived = 0
        ORDER BY d.created_at DESC";

try {
    $demands = $pdo->query($sql)->fetchAll();
} catch (PDOException $e) {
    $demands = [];
    $error = "Veri çekilirken hata oluştu: " . $e->getMessage();
}
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Cevap Bekleyen Talepler</h2>
        <p class="text-slate-500 text-sm">Henüz onaylanmamış veya işlem yapılmamış talepler.</p>
    </div>
</div>

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
                    <th class="px-6 py-4">Hizmet & Lokasyon</th>
                    <th class="px-6 py-4">Başlık</th>
                    <th class="px-6 py-4">Tarih</th>
                    <th class="px-6 py-4">Durum</th>
                    <th class="px-6 py-4 text-right">İşlemler</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($demands)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-slate-500">Bekleyen talep bulunmuyor.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach($demands as $demand): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 font-mono text-xs text-slate-400">#<?= $demand['id'] ?></td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-800"><?= htmlspecialchars($demand['first_name'] . ' ' . $demand['last_name']) ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-indigo-600"><?= htmlspecialchars($demand['category_name']) ?></div>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars($demand['city'] . ' / ' . $demand['district']) ?></div>
                        </td>
                        <td class="px-6 py-4 font-medium text-slate-700"><?= htmlspecialchars($demand['title']) ?></td>
                        <td class="px-6 py-4 text-xs text-slate-500">
                            <?= date('d.m.Y H:i', strtotime($demand['created_at'])) ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 rounded text-xs font-bold bg-yellow-100 text-yellow-700">Beklemede</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="demand-details.php?id=<?= $demand['id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-medium text-xs bg-indigo-50 px-3 py-1.5 rounded transition-colors">İncele</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>