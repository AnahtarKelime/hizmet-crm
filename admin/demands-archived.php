<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Sayfalama Ayarları
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtreleme Parametreleri
$where = ["d.is_archived = 1"];
$params = [];

if (!empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $where[] = "(d.title LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

if (!empty($_GET['status'])) {
    $where[] = "d.status = ?";
    $params[] = $_GET['status'];
}

// Toplam Kayıt Sayısı (Filtrelenmiş)
$countSql = "SELECT COUNT(*) FROM demands d LEFT JOIN users u ON d.user_id = u.id WHERE " . implode(" AND ", $where);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalDemands = $countStmt->fetchColumn();
$totalPages = ceil($totalDemands / $limit);

// Talepleri Çek
$sql = "SELECT 
            d.*, 
            u.first_name, u.last_name, 
            c.name as category_name, 
            l.city, l.district 
        FROM demands d
        LEFT JOIN users u ON d.user_id = u.id
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN locations l ON d.location_id = l.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY d.updated_at DESC
        LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
// Parametreleri bağla (Filtreler + Limit/Offset)
foreach ($params as $i => $val) {
    $stmt->bindValue($i + 1, $val);
}
$stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$demands = $stmt->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Arşivli Talepler</h2>
        <p class="text-slate-500 text-sm">Daha önce arşivlenmiş talepleri görüntüleyin ve yönetin.</p>
    </div>
</div>

<!-- Filtreleme Alanı -->
<div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-slate-700 mb-1">Arama</label>
            <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Başlık veya Müşteri Adı...">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Durum</label>
            <select name="status" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Tümü</option>
                <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Beklemede</option>
                <option value="approved" <?= ($_GET['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Onaylandı</option>
                <option value="completed" <?= ($_GET['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Tamamlandı</option>
                <option value="cancelled" <?= ($_GET['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>İptal</option>
            </select>
        </div>
        <div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-lg transition-colors text-sm flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-lg">filter_list</span> Filtrele
            </button>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">ID</th>
                    <th class="px-6 py-4">Müşteri</th>
                    <th class="px-6 py-4">Kategori / Lokasyon</th>
                    <th class="px-6 py-4">Başlık</th>
                    <th class="px-6 py-4">Arşiv Tarihi</th>
                    <th class="px-6 py-4">Durum</th>
                    <th class="px-6 py-4 text-right">İşlemler</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($demands)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-slate-500">Arşivlenmiş talep bulunmuyor.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach($demands as $demand): ?>
                    <tr class="hover:bg-slate-50 transition-colors bg-slate-50/50">
                        <td class="px-6 py-4 font-mono text-xs text-slate-400">#<?= $demand['id'] ?></td>
                        <td class="px-6 py-4 font-bold text-slate-700"><?= htmlspecialchars($demand['first_name'] . ' ' . $demand['last_name']) ?></td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-indigo-600"><?= htmlspecialchars($demand['category_name']) ?></div>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars($demand['city'] . ' / ' . $demand['district']) ?></div>
                        </td>
                        <td class="px-6 py-4 font-medium text-slate-600"><?= htmlspecialchars($demand['title']) ?></td>
                        <td class="px-6 py-4 text-xs text-slate-500"><?= date('d.m.Y', strtotime($demand['updated_at'])) ?></td>
                        <td class="px-6 py-4"><span class="px-2 py-1 rounded text-xs font-bold bg-gray-200 text-gray-600"><?= $demand['status'] ?></span></td>
                        <td class="px-6 py-4 text-right">
                            <a href="demand-details.php?id=<?= $demand['id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-medium text-xs bg-indigo-50 px-3 py-1.5 rounded transition-colors">Detay</a>
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
            <?php 
            // Mevcut query string'i koru (page hariç)
            $queryParams = $_GET;
            unset($queryParams['page']);
            $queryString = http_build_query($queryParams);
            $baseUrl = '?' . ($queryString ? $queryString . '&' : '');
            ?>
            
            <?php if ($page > 1): ?>
                <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" class="w-8 h-8 flex items-center justify-center bg-white border border-slate-200 rounded hover:bg-slate-100 text-slate-600 transition-colors">
                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                </a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="<?= $baseUrl ?>page=<?= $i ?>" class="w-8 h-8 flex items-center justify-center border rounded font-medium text-sm transition-colors <?= $i === $page ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" class="w-8 h-8 flex items-center justify-center bg-white border border-slate-200 rounded hover:bg-slate-100 text-slate-600 transition-colors">
                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>