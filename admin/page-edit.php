<?php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $title = $_POST['title'];
    $slug = $_POST['slug'];
    $content = $_POST['content'];

    if ($id) {
        $stmt = $pdo->prepare("UPDATE pages SET title=?, slug=?, content=? WHERE id=?");
        $stmt->execute([$title, $slug, $content, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO pages (title, slug, content) VALUES (?, ?, ?)");
        $stmt->execute([$title, $slug, $content]);
    }
    header("Location: pages.php");
    exit;
}

require_once 'includes/header.php';

$id = $_GET['id'] ?? null;
$page = ['title' => '', 'slug' => '', 'content' => ''];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
    $stmt->execute([$id]);
    $page = $stmt->fetch();
}
?>

<div class="max-w-5xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="pages.php" class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <h2 class="text-2xl font-bold text-slate-800"><?= $id ? 'Sayfayı Düzenle' : 'Yeni Sayfa Ekle' ?></h2>
    </div>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 space-y-6">
        <input type="hidden" name="id" value="<?= $id ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Sayfa Başlığı</label>
                <input type="text" name="title" value="<?= htmlspecialchars($page['title']) ?>" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">URL (Slug)</label>
                <input type="text" name="slug" value="<?= htmlspecialchars($page['slug']) ?>" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="ornek-sayfa">
                <p class="text-xs text-slate-400 mt-1">Türkçe karakter kullanmayınız. Örn: hakkimizda</p>
            </div>
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">İçerik (HTML)</label>
            <div class="text-xs text-slate-500 mb-2 bg-yellow-50 p-2 rounded border border-yellow-100">
                <span class="font-bold">Not:</span> Bu alan HTML kodlarını kabul eder. Tailwind CSS sınıflarını kullanabilirsiniz.
            </div>
            <textarea name="content" rows="20" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"><?= htmlspecialchars($page['content']) ?></textarea>
        </div>

        <div class="pt-6 border-t border-slate-100 flex justify-end gap-4">
            <a href="pages.php" class="px-6 py-3 rounded-lg text-slate-600 font-bold hover:bg-slate-100 transition-colors">İptal</a>
            <button type="submit" class="px-8 py-3 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">Kaydet</button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>