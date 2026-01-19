<?php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $menu_location = $_POST['menu_location'];
    $title = $_POST['title'];
    $icon = $_POST['icon'] ?? null;
    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
    $url = $_POST['url'];
    $visibility = $_POST['visibility'];
    $target = $_POST['target'];
    $sort_order = $_POST['sort_order'] ?? 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE menu_items SET menu_location=?, title=?, icon=?, parent_id=?, url=?, visibility=?, target=?, sort_order=?, is_active=? WHERE id=?");
            $stmt->execute([$menu_location, $title, $icon, $parent_id, $url, $visibility, $target, $sort_order, $is_active, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO menu_items (menu_location, title, icon, parent_id, url, visibility, target, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$menu_location, $title, $icon, $parent_id, $url, $visibility, $target, $sort_order, $is_active]);
        }
    } catch (PDOException $e) {
        die("Veritabanı hatası: " . $e->getMessage() . " <br>Lütfen <a href='repair-db.php'>Veritabanı Onar</a> sayfasını ziyaret edin.");
    }
    header("Location: menus.php");
    exit;
}

require_once 'includes/header.php';

$id = $_GET['id'] ?? null;
$item = ['menu_location' => 'header', 'title' => '', 'icon' => '', 'parent_id' => null, 'url' => '', 'visibility' => 'all', 'target' => '_self', 'sort_order' => 0, 'is_active' => 1];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
    $stmt->execute([$id]);
    $fetched = $stmt->fetch();
    if ($fetched) {
        $item = $fetched;
    }
}

// Mega Menü Üst Kategorilerini Çek (Kendisi hariç)
$parents = [];
try {
    $parentsSql = "SELECT * FROM menu_items WHERE menu_location = 'mega_menu' AND parent_id IS NULL";
    if ($id) $parentsSql .= " AND id != $id";
    $parents = $pdo->query($parentsSql . " ORDER BY title ASC")->fetchAll();
} catch (PDOException $e) {
    // Veritabanı sütunları eksik olabilir, yoksay
}
?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="menus.php" class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <h2 class="text-2xl font-bold text-slate-800"><?= $id ? 'Menü Linkini Düzenle' : 'Yeni Menü Linki Ekle' ?></h2>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8">
        <form method="POST" class="space-y-6">
            <input type="hidden" name="id" value="<?= $id ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Menü Konumu</label>
                    <select name="menu_location" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="header" <?= $item['menu_location'] == 'header' ? 'selected' : '' ?>>Header (Üst Menü)</option>
                        <option value="footer" <?= $item['menu_location'] == 'footer' ? 'selected' : '' ?>>Footer (Alt Menü)</option>
                        <option value="mega_menu" <?= $item['menu_location'] == 'mega_menu' ? 'selected' : '' ?>>Mega Menü</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Sıra</label>
                    <input type="number" name="sort_order" value="<?= htmlspecialchars($item['sort_order']) ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Başlık</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($item['title']) ?>" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Üst Menü (Sadece Mega Menü)</label>
                    <select name="parent_id" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Yok (Ana Kategori)</option>
                        <?php foreach ($parents as $parent): ?>
                            <option value="<?= $parent['id'] ?>" <?= $item['parent_id'] == $parent['id'] ? 'selected' : '' ?>><?= htmlspecialchars($parent['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">İkon (Material Symbols)</label>
                <input type="text" name="icon" value="<?= htmlspecialchars($item['icon'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Örn: home, cleaning_services">
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">URL</label>
                <input type="text" name="url" value="<?= htmlspecialchars($item['url']) ?>" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Örn: provider/apply.php veya https://...">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Görünürlük</label>
                    <select name="visibility" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="all" <?= $item['visibility'] == 'all' ? 'selected' : '' ?>>Herkes</option>
                        <option value="guest" <?= $item['visibility'] == 'guest' ? 'selected' : '' ?>>Sadece Misafirler</option>
                        <option value="customer" <?= $item['visibility'] == 'customer' ? 'selected' : '' ?>>Sadece Müşteriler</option>
                        <option value="provider" <?= $item['visibility'] == 'provider' ? 'selected' : '' ?>>Sadece Hizmet Verenler</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Hedef</label>
                    <select name="target" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="_self" <?= $item['target'] == '_self' ? 'selected' : '' ?>>Aynı Sayfada Aç</option>
                        <option value="_blank" <?= $item['target'] == '_blank' ? 'selected' : '' ?>>Yeni Sekmede Aç</option>
                    </select>
                </div>
            </div>

            <label class="flex items-center gap-3 p-4 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50">
                <input type="checkbox" name="is_active" value="1" <?= $item['is_active'] ? 'checked' : '' ?> class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500">
                <span class="font-bold text-slate-700">Aktif</span>
            </label>

            <div class="pt-6 border-t border-slate-100 flex justify-end gap-4">
                <a href="menus.php" class="px-6 py-3 rounded-lg text-slate-600 font-bold hover:bg-slate-100 transition-colors">İptal</a>
                <button type="submit" class="px-8 py-3 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>