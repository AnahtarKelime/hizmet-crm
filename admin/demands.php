<?php
require_once '../config/db.php';

// Toplu İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && isset($_POST['selected_ids'])) {
    $ids = $_POST['selected_ids'];
    $action = $_POST['bulk_action'];

    if (is_array($ids) && !empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        if ($action === 'archive') {
            $stmt = $pdo->prepare("UPDATE demands SET is_archived = 1 WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $msg = 'bulk_archived';
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM demands WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $msg = 'bulk_deleted';
        }
        
        if (isset($msg)) {
            header("Location: demands.php?msg=" . $msg);
            exit;
        }
    }
}

require_once 'includes/header.php';

// Sayfalama Ayarları
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10; // Sayfa başına gösterilecek talep sayısı
$offset = ($page - 1) * $limit;

// Filtreleme Parametreleri
$where = [];
$params = [];

// Arama (Kullanıcı Adı, Soyadı, Kategori veya Başlık)
if (!empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR c.name LIKE ? OR d.title LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

// Durum Filtresi
if (!empty($_GET['status'])) {
    $where[] = "d.status = ?";
    $params[] = $_GET['status'];
}

$whereClause = "";
if (!empty($where)) {
    $whereClause = " WHERE " . implode(" AND ", $where);
}

// Toplam Talep Sayısı (Filtreli)
$countSql = "SELECT COUNT(*) FROM demands d 
             LEFT JOIN users u ON d.user_id = u.id 
             LEFT JOIN categories c ON d.category_id = c.id 
             $whereClause";
$totalStmt = $pdo->prepare($countSql);
$totalStmt->execute($params);
$totalDemands = $totalStmt->fetchColumn();
$totalPages = ceil($totalDemands / $limit);

// Talepleri Çek (Filtreli)
$sql = "
    SELECT 
        d.*, 
        u.first_name, u.last_name, u.phone, u.whatsapp, u.is_verified,
        c.name as category_name, 
        l.city, l.district 
    FROM demands d
    LEFT JOIN users u ON d.user_id = u.id
    LEFT JOIN categories c ON d.category_id = c.id
    LEFT JOIN locations l ON d.location_id = l.id
    $whereClause
    ORDER BY d.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $pdo->prepare($sql);
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
        <h2 class="text-2xl font-bold text-slate-800">Tüm Talepler</h2>
        <p class="text-slate-500 text-sm">Sistemdeki tüm hizmet taleplerini buradan yönetebilirsiniz.</p>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">
        <?php 
        if ($_GET['msg'] == 'bulk_archived') echo "Seçilen talepler arşivlendi.";
        if ($_GET['msg'] == 'bulk_deleted') echo "Seçilen talepler silindi.";
        ?>
    </div>
<?php endif; ?>

<!-- Filtreleme Alanı -->
<div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-slate-700 mb-1">Arama</label>
            <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Kullanıcı, Kategori veya Başlık...">
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

<form method="POST" id="bulkActionForm">
    <?php if (!empty($demands)): ?>
    <div class="flex gap-2 justify-end mb-4">
        <button type="submit" name="bulk_action" value="archive" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors">
            <span class="material-symbols-outlined text-lg">archive</span> Seçilileri Arşivle
        </button>
        <button type="submit" name="bulk_action" value="delete" onclick="return confirm('Seçili talepleri silmek istediğinize emin misiniz?')" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors">
            <span class="material-symbols-outlined text-lg">delete</span> Seçilileri Sil
        </button>
    </div>
    <?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-left text-sm text-slate-600">
        <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 w-10">
                    <input type="checkbox" id="selectAll" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                </th>
                <th class="px-6 py-4 hidden md:table-cell">ID</th>
                <th class="px-6 py-4">Başlık</th>
                <th class="px-6 py-4">Kullanıcı</th>
                <th class="px-6 py-4 hidden lg:table-cell">Kategori</th>
                <th class="px-6 py-4 hidden xl:table-cell">Lokasyon</th>
                <th class="px-6 py-4 hidden lg:table-cell">Tarih</th>
                <th class="px-6 py-4">Durum</th>
                <th class="px-6 py-4 text-right">İşlemler</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($demands)): ?>
                <tr>
                    <td colspan="9" class="px-6 py-8 text-center text-slate-500">Henüz hiç talep yok.</td>
                </tr>
            <?php else: ?>
                <?php foreach($demands as $demand): ?>
                <tr class="hover:bg-slate-50 transition-colors cursor-pointer" onclick="window.location='demand-details.php?id=<?= $demand['id'] ?>'">
                    <td class="px-6 py-4" onclick="event.stopPropagation()">
                        <input type="checkbox" name="selected_ids[]" value="<?= $demand['id'] ?>" class="demand-checkbox rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                    </td>
                    <td class="px-6 py-4 font-mono text-xs text-slate-400 hidden md:table-cell">#<?= $demand['id'] ?></td>
                    <td class="px-6 py-4 font-medium text-slate-800">
                        <?= htmlspecialchars($demand['title']) ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="font-bold text-slate-700 whitespace-nowrap flex items-center gap-2">
                            <?= htmlspecialchars($demand['first_name'] . ' ' . $demand['last_name']) ?>
                            <?php if (isset($demand['is_verified']) && $demand['is_verified'] == 0): ?>
                                <span class="bg-orange-100 text-orange-700 text-[10px] px-1.5 py-0.5 rounded border border-orange-200 flex items-center gap-0.5" title="Misafir / Doğrulanmamış Kullanıcı">
                                    <span class="material-symbols-outlined text-[12px]">person_alert</span> Misafir
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($demand['phone'])): 
                            $rawPhone = preg_replace('/[^0-9]/', '', $demand['phone']);
                            if (strlen($rawPhone) > 10) $rawPhone = substr($rawPhone, -10);
                            
                            $formattedPhone = (strlen($rawPhone) === 10) 
                                ? '0' . substr($rawPhone, 0, 3) . ' ' . substr($rawPhone, 3, 3) . ' ' . substr($rawPhone, 6, 2) . ' ' . substr($rawPhone, 8, 2)
                                : $demand['phone'];
                        ?>
                        <div class="flex flex-wrap items-center gap-2 mt-1">
                            <a href="tel:<?= $rawPhone ?>" onclick="event.stopPropagation()" class="text-xs text-slate-500 hover:text-indigo-600 font-mono whitespace-nowrap transition-colors"><?= htmlspecialchars($formattedPhone) ?></a>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 hidden lg:table-cell">
                        <span class="px-2 py-1 bg-indigo-50 text-indigo-700 rounded text-xs font-bold">
                            <?= htmlspecialchars($demand['category_name']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-xs hidden xl:table-cell">
                        <?php 
                        $locationText = !empty($demand['address_text']) ? $demand['address_text'] : ($demand['city'] . ' / ' . $demand['district']);
                        ?>
                        <div class="truncate max-w-[200px]" title="<?= htmlspecialchars($locationText) ?>">
                            <?= htmlspecialchars($locationText) ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-500 hidden lg:table-cell">
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
                        ?>
                        <span class="px-2 py-1 rounded text-xs font-bold <?= $statusColors[$demand['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                            <?= $statusLabels[$demand['status']] ?? $demand['status'] ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <a href="demand-details.php?id=<?= $demand['id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-medium text-xs bg-indigo-50 px-3 py-1.5 rounded transition-colors inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">visibility</span> Detay
                        </a>
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
</form>

<script>
    document.getElementById('selectAll')?.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.demand-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
</script>

<?php require_once 'includes/footer.php'; ?>