<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Başvuruları Çek (Pending olanlar öncelikli)
$where = ["pd.application_status != 'none'"];
$params = [];

if (!empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR pd.business_name LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

if (!empty($_GET['status'])) {
    $where[] = "pd.application_status = ?";
    $params[] = $_GET['status'];
}

$sql = "SELECT 
            u.id, u.first_name, u.last_name, u.email, u.phone, u.created_at,
            pd.business_name, pd.application_status
        FROM users u
        JOIN provider_details pd ON u.id = pd.user_id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY FIELD(pd.application_status, 'pending', 'incomplete', 'approved', 'rejected'), u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Hizmet Veren Başvuruları</h2>
        <p class="text-slate-500 text-sm">Onay bekleyen ve işlem yapılmış başvurular.</p>
    </div>
</div>

<!-- Filtreleme Alanı -->
<div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-slate-700 mb-1">Arama</label>
            <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Ad, E-posta veya İşletme Adı...">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Durum</label>
            <select name="status" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Tümü</option>
                <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Bekliyor</option>
                <option value="incomplete" <?= ($_GET['status'] ?? '') === 'incomplete' ? 'selected' : '' ?>>Eksik Evrak</option>
                <option value="approved" <?= ($_GET['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Onaylandı</option>
                <option value="rejected" <?= ($_GET['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Reddedildi</option>
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
    <table class="w-full text-left text-sm text-slate-600">
        <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
            <tr>
                <th class="px-6 py-4">ID</th>
                <th class="px-6 py-4">Ad Soyad / İşletme</th>
                <th class="px-6 py-4">İletişim</th>
                <th class="px-6 py-4">Başvuru Tarihi</th>
                <th class="px-6 py-4">Durum</th>
                <th class="px-6 py-4 text-right">İşlemler</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($applications)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-slate-500">Henüz başvuru bulunmuyor.</td>
                </tr>
            <?php else: ?>
                <?php foreach($applications as $app): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 font-mono text-xs text-slate-400">#<?= $app['id'] ?></td>
                    <td class="px-6 py-4">
                        <div class="font-bold text-slate-800"><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></div>
                        <?php if($app['business_name']): ?>
                            <div class="text-xs text-indigo-600 font-medium"><?= htmlspecialchars($app['business_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-slate-700"><?= htmlspecialchars($app['email']) ?></div>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($app['phone']) ?></div>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-500">
                        <?= date('d.m.Y H:i', strtotime($app['created_at'])) ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php
                        $statusColors = [
                            'pending' => 'bg-yellow-100 text-yellow-700',
                            'approved' => 'bg-green-100 text-green-700',
                            'rejected' => 'bg-red-100 text-red-700',
                            'incomplete' => 'bg-orange-100 text-orange-700'
                        ];
                        $statusLabel = ['pending' => 'Bekliyor', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi', 'incomplete' => 'Eksik Evrak'];
                        ?>
                        <span class="px-2 py-1 rounded text-xs font-bold <?= $statusColors[$app['application_status']] ?? 'bg-gray-100' ?>">
                            <?= $statusLabel[$app['application_status']] ?? $app['application_status'] ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <a href="application-details.php?id=<?= $app['id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-medium text-xs bg-indigo-50 px-3 py-1.5 rounded transition-colors">İncele</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>