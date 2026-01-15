<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Kullanıcı Silme (Users sayfasıyla aynı mantık)
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'provider'");
    $stmt->execute([$_GET['delete']]);
    header("Location: providers.php?msg=deleted");
    exit;
}

// Sayfalama Ayarları
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Hizmet Verenleri Çek
$where = ["u.role = 'provider'"];
$params = [];

if (!empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR pd.business_name LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

if (!empty($_GET['email'])) {
    $where[] = "u.email LIKE ?";
    $params[] = "%" . $_GET['email'] . "%";
}

if (!empty($_GET['phone'])) {
    $where[] = "u.phone LIKE ?";
    $params[] = "%" . $_GET['phone'] . "%";
}

if (!empty($_GET['subscription_type'])) {
    $where[] = "pd.subscription_type = ?";
    $params[] = $_GET['subscription_type'];
}

// Toplam Kayıt Sayısı (Filtrelenmiş)
$countSql = "SELECT COUNT(*) FROM users u LEFT JOIN provider_details pd ON u.id = pd.user_id WHERE " . implode(" AND ", $where);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalProviders = $countStmt->fetchColumn();
$totalPages = ceil($totalProviders / $limit);

$sql = "SELECT u.*, pd.business_name, pd.subscription_type, pd.application_status 
        FROM users u 
        LEFT JOIN provider_details pd ON u.id = pd.user_id 
        WHERE " . implode(" AND ", $where) . " 
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
foreach ($params as $i => $val) {
    $stmt->bindValue($i + 1, $val);
}
$stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$providers = $stmt->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Hizmet Verenler</h2>
        <p class="text-slate-500 text-sm">Sistemdeki kayıtlı hizmet verenleri ve işletmeleri yönetin.</p>
    </div>
</div>

<!-- Filtreleme Alanı -->
<div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Ad Soyad / İşletme</label>
            <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Ara...">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">E-posta</label>
            <input type="text" name="email" value="<?= htmlspecialchars($_GET['email'] ?? '') ?>" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="E-posta">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Telefon</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($_GET['phone'] ?? '') ?>" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Telefon">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Abonelik</label>
            <select name="subscription_type" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Tümü</option>
                <option value="free" <?= ($_GET['subscription_type'] ?? '') === 'free' ? 'selected' : '' ?>>Ücretsiz</option>
                <option value="premium" <?= ($_GET['subscription_type'] ?? '') === 'premium' ? 'selected' : '' ?>>Premium</option>
            </select>
        </div>
        <div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-lg transition-colors text-sm flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-lg">filter_list</span> Filtrele
            </button>
        </div>
    </form>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">Kullanıcı başarıyla silindi.</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left text-sm text-slate-600">
        <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
            <tr>
                <th class="px-6 py-4">ID</th>
                <th class="px-6 py-4">İşletme / Ad Soyad</th>
                <th class="px-6 py-4">İletişim</th>
                <th class="px-6 py-4">Abonelik</th>
                <th class="px-6 py-4">Başvuru Durumu</th>
                <th class="px-6 py-4">Kayıt Tarihi</th>
                <th class="px-6 py-4 text-right">İşlemler</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($providers)): ?>
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-slate-500">Kayıtlı hizmet veren bulunmuyor.</td>
                </tr>
            <?php else: ?>
                <?php foreach($providers as $provider): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 font-mono text-xs text-slate-400">#<?= $provider['id'] ?></td>
                    <td class="px-6 py-4">
                        <div class="font-bold text-slate-800"><?= htmlspecialchars($provider['business_name'] ?: $provider['first_name'] . ' ' . $provider['last_name']) ?></div>
                        <?php if($provider['business_name']): ?>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars($provider['first_name'] . ' ' . $provider['last_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-slate-700"><?= htmlspecialchars($provider['email']) ?></div>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($provider['phone']) ?></div>
                    </td>
                    <td class="px-6 py-4">
                        <?php if($provider['subscription_type'] === 'premium'): ?>
                            <span class="px-2 py-1 rounded text-xs font-bold bg-purple-100 text-purple-700 flex items-center gap-1 w-fit">
                                <span class="material-symbols-outlined text-[14px]">diamond</span> Premium
                            </span>
                        <?php else: ?>
                            <span class="px-2 py-1 rounded text-xs font-bold bg-slate-100 text-slate-600">Ücretsiz</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php
                        $statusColors = [
                            'approved' => 'text-green-600',
                            'pending' => 'text-yellow-600',
                            'rejected' => 'text-red-600',
                            'incomplete' => 'text-orange-600',
                            'none' => 'text-slate-400'
                        ];
                        $statusLabel = ['approved' => 'Onaylı', 'pending' => 'Bekliyor', 'rejected' => 'Reddedildi', 'incomplete' => 'Eksik', 'none' => '-'];
                        ?>
                        <span class="font-bold text-xs <?= $statusColors[$provider['application_status']] ?? 'text-slate-500' ?>">
                            <?= $statusLabel[$provider['application_status']] ?? $provider['application_status'] ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-500">
                        <?= date('d.m.Y', strtotime($provider['created_at'])) ?>
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <a href="user-edit.php?id=<?= $provider['id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-medium">Detay & Düzenle</a>
                        <a href="providers.php?delete=<?= $provider['id'] ?>" onclick="return confirm('Bu hizmet vereni silmek istediğinize emin misiniz?')" class="text-red-500 hover:text-red-700 font-medium">Sil</a>
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