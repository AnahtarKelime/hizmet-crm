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

// Toplam Talep Sayısı
$totalStmt = $pdo->query("SELECT COUNT(*) FROM demands");
$totalDemands = $totalStmt->fetchColumn();
$totalPages = ceil($totalDemands / $limit);

// Talepleri Çek
$stmt = $pdo->prepare("
    SELECT 
        d.*, 
        u.first_name, u.last_name, 
        c.name as category_name, 
        l.city, l.district 
    FROM demands d
    LEFT JOIN users u ON d.user_id = u.id
    LEFT JOIN categories c ON d.category_id = c.id
    LEFT JOIN locations l ON d.location_id = l.id
    ORDER BY d.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
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
    <table class="w-full text-left text-sm text-slate-600">
        <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 w-10">
                    <input type="checkbox" id="selectAll" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                </th>
                <th class="px-6 py-4">ID</th>
                <th class="px-6 py-4">Başlık</th>
                <th class="px-6 py-4">Kullanıcı</th>
                <th class="px-6 py-4">Kategori</th>
                <th class="px-6 py-4">Lokasyon</th>
                <th class="px-6 py-4">Tarih</th>
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
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4">
                        <input type="checkbox" name="selected_ids[]" value="<?= $demand['id'] ?>" class="demand-checkbox rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                    </td>
                    <td class="px-6 py-4 font-mono text-xs text-slate-400">#<?= $demand['id'] ?></td>
                    <td class="px-6 py-4 font-medium text-slate-800">
                        <?= htmlspecialchars($demand['title']) ?>
                    </td>
                    <td class="px-6 py-4">
                        <?= htmlspecialchars($demand['first_name'] . ' ' . $demand['last_name']) ?>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 bg-indigo-50 text-indigo-700 rounded text-xs font-bold">
                            <?= htmlspecialchars($demand['category_name']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-xs">
                        <?php 
                        $locationText = !empty($demand['address_text']) ? $demand['address_text'] : ($demand['city'] . ' / ' . $demand['district']);
                        ?>
                        <div class="truncate max-w-[200px]" title="<?= htmlspecialchars($locationText) ?>">
                            <?= htmlspecialchars($locationText) ?>
                        </div>
                    </td>
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
</form>

<script>
    document.getElementById('selectAll')?.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.demand-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
</script>

<?php require_once 'includes/footer.php'; ?>