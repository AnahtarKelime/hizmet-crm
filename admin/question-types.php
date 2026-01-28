<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Ekleme / Düzenleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $typeKey = $_POST['type_key'];
    $renderAs = $_POST['render_as'];
    $name = $_POST['name'];
    $hasOptions = isset($_POST['has_options']) ? 1 : 0;
    $customCss = $_POST['custom_css'];
    $customJs = $_POST['custom_js'];
    $sortOrder = $_POST['sort_order'] ?? 0;

    if ($id) {
        $stmt = $pdo->prepare("UPDATE question_types SET type_key=?, render_as=?, name=?, has_options=?, custom_css=?, custom_js=?, sort_order=? WHERE id=?");
        $stmt->execute([$typeKey, $renderAs, $name, $hasOptions, $customCss, $customJs, $sortOrder, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO question_types (type_key, render_as, name, has_options, custom_css, custom_js, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$typeKey, $renderAs, $name, $hasOptions, $customCss, $customJs, $sortOrder]);
    }
    header("Location: question-types.php");
    exit;
}

// Silme
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM question_types WHERE id = ?")->execute([$_GET['delete']]);
    header("Location: question-types.php");
    exit;
}

$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM question_types";
$params = [];

if ($search) {
    $sql .= " WHERE name LIKE ? OR type_key LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY sort_order ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$types = $stmt->fetchAll();

$editType = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM question_types WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editType = $stmt->fetch();
}
?>

<div class="flex gap-6">
    <!-- Liste -->
    <div class="w-2/3">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-slate-800">Soru Tipleri</h2>
        </div>

        <!-- Arama Kutusu -->
        <form method="GET" class="mb-6 relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tip ara..." class="w-full pl-10 pr-4 py-3 rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
        </form>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 font-bold border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Anahtar</th>
                        <th class="px-4 py-3">Ad</th>
                        <th class="px-4 py-3">Altyapı (Render)</th>
                        <th class="px-4 py-3 text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach($types as $t): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs"><?= htmlspecialchars($t['type_key']) ?></td>
                        <td class="px-4 py-3 font-bold"><?= htmlspecialchars($t['name']) ?></td>
                        <td class="px-4 py-3 text-xs"><?= htmlspecialchars($t['render_as']) ?></td>
                        <td class="px-4 py-3 text-right">
                            <a href="?edit=<?= $t['id'] ?>" class="text-indigo-600 hover:underline mr-2">Düzenle</a>
                            <a href="?delete=<?= $t['id'] ?>" onclick="return confirm('Silmek istediğinize emin misiniz?')" class="text-red-600 hover:underline">Sil</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Form -->
    <div class="w-1/3">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 sticky top-6">
            <h3 class="font-bold text-lg mb-4"><?= $editType ? 'Tipi Düzenle' : 'Yeni Tip Ekle' ?></h3>
            <form method="POST" class="space-y-4">
                <?php if($editType): ?><input type="hidden" name="id" value="<?= $editType['id'] ?>"><?php endif; ?>
                
                <div>
                    <label class="block text-xs font-bold mb-1">Tip Anahtarı (Benzersiz)</label>
                    <input type="text" name="type_key" value="<?= $editType['type_key'] ?? '' ?>" class="w-full rounded border-slate-300 text-sm" required placeholder="orn: logo_upload">
                </div>
                
                <div>
                    <label class="block text-xs font-bold mb-1">Görünen Ad</label>
                    <input type="text" name="name" value="<?= $editType['name'] ?? '' ?>" class="w-full rounded border-slate-300 text-sm" required placeholder="Logo Yükleme">
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1">Altyapı (Render As)</label>
                    <select name="render_as" class="w-full rounded border-slate-300 text-sm">
                        <option value="text" <?= ($editType['render_as'] ?? '') == 'text' ? 'selected' : '' ?>>Metin (Text)</option>
                        <option value="image" <?= ($editType['render_as'] ?? '') == 'image' ? 'selected' : '' ?>>Resim (Image)</option>
                        <option value="select" <?= ($editType['render_as'] ?? '') == 'select' ? 'selected' : '' ?>>Seçim (Select)</option>
                        <option value="color" <?= ($editType['render_as'] ?? '') == 'color' ? 'selected' : '' ?>>Renk (Color)</option>
                        <option value="jewelry_box_select" <?= ($editType['render_as'] ?? '') == 'jewelry_box_select' ? 'selected' : '' ?>>Takı Kutusu (Özel)</option>
                        <option value="file" <?= ($editType['render_as'] ?? '') == 'file' ? 'selected' : '' ?>>Dosya Yükleme (File)</option>
                        <!-- Diğer temel tipler buraya eklenebilir -->
                    </select>
                    <p class="text-[10px] text-slate-400 mt-1">Bu tipin teknik olarak nasıl çalışacağını belirler.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1">Özel CSS</label>
                    <textarea name="custom_css" rows="3" class="w-full rounded border-slate-300 text-xs font-mono"><?= $editType['custom_css'] ?? '' ?></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1">Özel JS</label>
                    <textarea name="custom_js" rows="3" class="w-full rounded border-slate-300 text-xs font-mono"><?= $editType['custom_js'] ?? '' ?></textarea>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="has_options" value="1" <?= ($editType['has_options'] ?? 0) ? 'checked' : '' ?>>
                    <label class="text-xs">Seçenekleri var mı? (Select/Radio)</label>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <?php if($editType): ?><a href="question-types.php" class="px-3 py-2 text-xs font-bold text-slate-500">İptal</a><?php endif; ?>
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded text-xs font-bold">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
