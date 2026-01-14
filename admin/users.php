<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Kullanıcı Silme
if (isset($_GET['delete'])) {
    // Kendini silmeyi engelle
    if ($_GET['delete'] == $_SESSION['user_id']) {
        $error = "Kendinizi silemezsiniz.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        header("Location: users.php?msg=deleted");
        exit;
    }
}

// Filtreleme Parametreleri
$where = [];
$params = [];

if (!empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

// Kullanıcıları Çek
$sql = "SELECT * FROM users";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Tüm Kullanıcılar</h2>
        <p class="text-slate-500 text-sm">Sistemdeki kayıtlı kullanıcıları yönetin.</p>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4"><?= $error ?></div>
<?php endif; ?>

<!-- Filtreleme Alanı -->
<div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div class="md:col-span-3">
            <label class="block text-xs font-bold text-slate-700 mb-1">Kullanıcı Ara</label>
            <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Ad, Soyad veya E-posta...">
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
                <th class="px-6 py-4">Ad Soyad</th>
                <th class="px-6 py-4">E-posta / Telefon</th>
                <th class="px-6 py-4">Rol</th>
                <th class="px-6 py-4">Durum</th>
                <th class="px-6 py-4">Kayıt Tarihi</th>
                <th class="px-6 py-4 text-right">İşlemler</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach($users as $user): ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 font-mono text-xs text-slate-400">#<?= $user['id'] ?></td>
                <td class="px-6 py-4 font-bold text-slate-800">
                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                </td>
                <td class="px-6 py-4">
                    <div class="text-slate-700"><?= htmlspecialchars($user['email']) ?></div>
                    <div class="text-xs text-slate-400"><?= htmlspecialchars($user['phone']) ?></div>
                </td>
                <td class="px-6 py-4">
                    <?php
                    $roleColors = [
                        'admin' => 'bg-purple-100 text-purple-700',
                        'provider' => 'bg-blue-100 text-blue-700',
                        'customer' => 'bg-green-100 text-green-700'
                    ];
                    $roleLabels = [
                        'admin' => 'Yönetici',
                        'provider' => 'Hizmet Veren',
                        'customer' => 'Müşteri'
                    ];
                    ?>
                    <span class="px-2 py-1 rounded text-xs font-bold <?= $roleColors[$user['role']] ?? 'bg-gray-100' ?>">
                        <?= $roleLabels[$user['role']] ?? $user['role'] ?>
                    </span>
                </td>
                <td class="px-6 py-4">
                    <?php if($user['is_verified']): ?>
                        <span class="text-green-600 flex items-center gap-1 text-xs font-bold"><span class="material-symbols-outlined text-sm">check_circle</span> Onaylı</span>
                    <?php else: ?>
                        <span class="text-slate-400 flex items-center gap-1 text-xs font-bold"><span class="material-symbols-outlined text-sm">pending</span> Onaysız</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-xs text-slate-500">
                    <?= date('d.m.Y', strtotime($user['created_at'])) ?>
                </td>
                <td class="px-6 py-4 text-right space-x-2">
                    <?php if($user['id'] != $_SESSION['user_id']): ?>
                        <a href="user-edit.php?id=<?= $user['id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-medium">Düzenle</a>
                        <a href="users.php?delete=<?= $user['id'] ?>" onclick="return confirm('Bu kullanıcıyı silmek istediğinize emin misiniz?')" class="text-red-500 hover:text-red-700 font-medium">Sil</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>