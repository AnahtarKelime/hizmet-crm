<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Sayfalama Ayarları
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Toplam Bekleyen Talep Sayısı
$totalStmt = $pdo->query("SELECT COUNT(*) FROM demands WHERE status = 'pending' AND is_archived = 0");
$totalDemands = $totalStmt->fetchColumn();
$totalPages = ceil($totalDemands / $limit);

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
        ORDER BY d.created_at DESC
        LIMIT :limit OFFSET :offset";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $demands = $stmt->fetchAll();
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

    <!-- Sayfalama -->
    <?php if ($totalPages > 1): ?>
    <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-center">
        <div class="flex gap-2">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>" class="w-8 h-8 flex items-center justify-center bg-white border border-slate-200 rounded hover:bg-slate-100 text-slate-600 transition-colors">
                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                </a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="w-8 h-8 flex items-center justify-center border rounded font-medium text-sm transition-colors <?= $i === $page ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>" class="w-8 h-8 flex items-center justify-center bg-white border border-slate-200 rounded hover:bg-slate-100 text-slate-600 transition-colors">
                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>