<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Silme İşlemi
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: categories.php?msg=deleted");
    exit;
}

// Filtreleme Parametreleri
$where = [];
$params = [];

if (!empty($_GET['search'])) {
    $where[] = "name LIKE ?";
    $params[] = "%" . $_GET['search'] . "%";
}

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $where[] = "is_active = ?";
    $params[] = $_GET['status'];
}

$sql = "SELECT * FROM categories";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Hizmet Kategorileri</h2>
        <p class="text-slate-500 text-sm">Sistemdeki tüm hizmetleri ve anahtar kelimeleri yönetin.</p>
    </div>
    <a href="category-form.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors">
        <span class="material-symbols-outlined text-lg">add</span>
        Yeni Ekle
    </a>
</div>

<!-- Filtreleme Alanı -->
<div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-slate-700 mb-1">Kategori Ara</label>
            <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Kategori adı...">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Durum</label>
            <select name="status" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Tümü</option>
                <option value="1" <?= (isset($_GET['status']) && $_GET['status'] === '1') ? 'selected' : '' ?>>Aktif</option>
                <option value="0" <?= (isset($_GET['status']) && $_GET['status'] === '0') ? 'selected' : '' ?>>Pasif</option>
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
                <th class="px-6 py-4">İkon</th>
                <th class="px-6 py-4">Hizmet Adı</th>
                <th class="px-6 py-4 w-1/3">Anahtar Kelimeler (SEO)</th>
                <th class="px-6 py-4">Durum</th>
                <th class="px-6 py-4 text-right">İşlemler</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach($categories as $cat): ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 font-mono text-xs text-slate-400">#<?= $cat['id'] ?></td>
                <td class="px-6 py-4">
                    <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center">
                        <span class="material-symbols-outlined"><?= $cat['icon'] ?: 'category' ?></span>
                    </div>
                </td>
                <td class="px-6 py-4 font-bold text-slate-800"><?= htmlspecialchars($cat['name']) ?></td>
                <td class="px-6 py-4 text-xs text-slate-500 leading-relaxed"><?= htmlspecialchars($cat['keywords'] ?? '-') ?></td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 rounded text-xs font-bold <?= $cat['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                        <?= $cat['is_active'] ? 'Aktif' : 'Pasif' ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-right space-x-2">
                    <a href="questions.php?category_id=<?= $cat['id'] ?>" class="text-amber-600 hover:text-amber-800 font-medium inline-flex items-center gap-1" title="Soruları Yönet">
                        <span class="material-symbols-outlined text-sm">quiz</span>
                    </a>
                    <a href="category-form.php?id=<?= $cat['id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-medium">Düzenle</a>
                    <a href="categories.php?delete=<?= $cat['id'] ?>" onclick="return confirm('Bu kategoriyi silmek istediğinize emin misiniz?')" class="text-red-500 hover:text-red-700 font-medium">Sil</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>