<?php
require_once '../config/db.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $slug = $_POST['slug'];
    $icon = $_POST['icon'];
    $keywords = $_POST['keywords'];
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $trackingHead = $_POST['tracking_code_head'] ?? null;
    $trackingBody = $_POST['tracking_code_body'] ?? null;
    
    // Mevcut resmi al (eğer id varsa)
    $id = $_GET['id'] ?? null;
    $imagePath = '';
    if ($id) {
        $imagePath = $pdo->query("SELECT image FROM categories WHERE id = $id")->fetchColumn();
    }

    // Görsel Yükleme
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/categories/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileName = $_FILES['image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = $slug . '_' . uniqid() . '.' . $fileExtension;
            if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
                $imagePath = 'uploads/categories/' . $newFileName;
            }
        }
    }

    if ($id) {
        $stmt = $pdo->prepare("UPDATE categories SET name=?, slug=?, icon=?, image=?, keywords=?, is_active=?, is_featured=?, tracking_code_head=?, tracking_code_body=? WHERE id=?");
        $stmt->execute([$name, $slug, $icon, $imagePath, $keywords, $isActive, $isFeatured, $trackingHead, $trackingBody, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, image, keywords, is_active, is_featured, tracking_code_head, tracking_code_body) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $icon, $imagePath, $keywords, $isActive, $isFeatured, $trackingHead, $trackingBody]);
    }
    header("Location: categories.php");
    exit;
}

require_once 'includes/header.php';

$id = $_GET['id'] ?? null;
$category = ['name' => '', 'slug' => '', 'icon' => '', 'image' => '', 'keywords' => '', 'is_active' => 1, 'is_featured' => 0, 'tracking_code_head' => '', 'tracking_code_body' => ''];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();
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
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
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

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Kategori Görseli</label>
                    <?php if (!empty($category['image'])): ?>
                        <div class="mb-2">
                            <img src="../<?= htmlspecialchars($category['image']) ?>" alt="Kategori Görseli" class="h-20 w-auto object-cover rounded-lg border border-slate-200">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="text-xs text-slate-500 mt-1">Önerilen boyut: 600x800px (Dikey)</p>
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
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="flex items-center gap-3 p-4 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50">
                            <input type="checkbox" name="is_active" value="1" <?= $category['is_active'] ? 'checked' : '' ?> class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500">
                            <div>
                                <span class="block font-bold text-slate-700">Aktif Hizmet</span>
                                <span class="text-xs text-slate-500">Bu hizmet sitede görüntülensin ve aramalarda çıksın.</span>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-4 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50">
                            <input type="checkbox" name="is_featured" value="1" <?= $category['is_featured'] ? 'checked' : '' ?> class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500">
                            <div>
                                <span class="block font-bold text-slate-700">Anasayfada Göster</span>
                                <span class="text-xs text-slate-500">Bu hizmet anasayfadaki "Popüler Kategoriler" alanında listelensin.</span>
                            </div>
                        </label>
                    </div>
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