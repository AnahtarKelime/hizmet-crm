<?php
require_once '../config/db.php';
require_once 'includes/header.php';

$id = $_GET['id'] ?? null;
$category = ['name' => '', 'slug' => '', 'icon' => '', 'keywords' => '', 'is_active' => 1, 'tracking_code_head' => '', 'tracking_code_body' => ''];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $slug = $_POST['slug'];
    $icon = $_POST['icon'];
    $keywords = $_POST['keywords'];
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $trackingHead = $_POST['tracking_code_head'] ?? null;
    $trackingBody = $_POST['tracking_code_body'] ?? null;

    if ($id) {
        $stmt = $pdo->prepare("UPDATE categories SET name=?, slug=?, icon=?, keywords=?, is_active=?, tracking_code_head=?, tracking_code_body=? WHERE id=?");
        $stmt->execute([$name, $slug, $icon, $keywords, $isActive, $trackingHead, $trackingBody, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, keywords, is_active, tracking_code_head, tracking_code_body) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $icon, $keywords, $isActive, $trackingHead, $trackingBody]);
    }
    header("Location: categories.php");
    exit;
}
?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="categories.php" class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <h2 class="text-2xl font-bold text-slate-800"><?= $id ? 'Kategoriyi Düzenle' : 'Yeni Kategori Ekle' ?></h2>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8">
        <form method="POST" class="space-y-6">
            <div class="grid grid-cols-2 gap-6">
                <div class="col-span-2">
                    <label class="block text-sm font-bold text-slate-700 mb-2">Hizmet Adı</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($category['name']) ?>" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">URL Slug</label>
                    <input type="text" name="slug" value="<?= htmlspecialchars($category['slug']) ?>" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-slate-500">
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">
                        Material Icon 
                        <a href="https://fonts.google.com/icons" target="_blank" class="text-indigo-500 text-xs font-normal ml-1">(İkon Bul)</a>
                    </label>
                    <div class="relative">
                        <input type="text" name="icon" value="<?= htmlspecialchars($category['icon']) ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 pl-10">
                        <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400">stars</span>
                    </div>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-bold text-slate-700 mb-2">
                        Anahtar Kelimeler (Keywords)
                        <span class="block text-xs font-normal text-slate-500 mt-1">Arama motorunda bu hizmetin bulunmasını sağlayacak kelimeleri virgülle ayırarak yazın. (Örn: gündelikçi, temizlikçi, cam silme)</span>
                    </label>
                    <textarea name="keywords" rows="3" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"><?= htmlspecialchars($category['keywords']) ?></textarea>
                </div>

                <div class="col-span-2 border-t border-slate-100 pt-6 mt-2">
                    <h3 class="text-lg font-bold text-slate-800 mb-4">Takip ve Analiz Kodları</h3>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">
                                &lt;head&gt; Kodları
                                <span class="block text-xs font-normal text-slate-500 mt-1">Google Analytics, Facebook Pixel vb. script etiketleri.</span>
                            </label>
                            <textarea name="tracking_code_head" rows="4" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono text-xs placeholder:text-slate-300" placeholder="<script>...</script>"><?= htmlspecialchars($category['tracking_code_head'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">
                                &lt;body&gt; Kodları
                                <span class="block text-xs font-normal text-slate-500 mt-1">Google Tag Manager (noscript) vb. body başlangıcına eklenecek kodlar.</span>
                            </label>
                            <textarea name="tracking_code_body" rows="4" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono text-xs placeholder:text-slate-300" placeholder="<noscript>...</noscript>"><?= htmlspecialchars($category['tracking_code_body'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="col-span-2">
                    <label class="flex items-center gap-3 p-4 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50">
                        <input type="checkbox" name="is_active" value="1" <?= $category['is_active'] ? 'checked' : '' ?> class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500">
                        <div>
                            <span class="block font-bold text-slate-700">Aktif Hizmet</span>
                            <span class="text-xs text-slate-500">Bu hizmet sitede görüntülensin ve aramalarda çıksın.</span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="pt-6 border-t border-slate-100 flex justify-end gap-4">
                <a href="categories.php" class="px-6 py-3 rounded-lg text-slate-600 font-bold hover:bg-slate-100 transition-colors">İptal</a>
                <button type="submit" class="px-8 py-3 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">
                    <?= $id ? 'Değişiklikleri Kaydet' : 'Hizmeti Oluştur' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>