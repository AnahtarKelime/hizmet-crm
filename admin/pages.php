<?php
require_once '../config/db.php';
require_once 'includes/header.php';

$pages = $pdo->query("SELECT * FROM pages ORDER BY title ASC")->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Sayfa Yönetimi</h2>
        <p class="text-slate-500 text-sm">Statik sayfaların içeriklerini buradan düzenleyebilirsiniz.</p>
    </div>
    <a href="page-edit.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors">
        <span class="material-symbols-outlined text-lg">add</span>
        Yeni Sayfa Ekle
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left text-sm text-slate-600">
        <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
            <tr>
                <th class="px-6 py-4">Başlık</th>
                <th class="px-6 py-4">URL (Slug)</th>
                <th class="px-6 py-4">Son Güncelleme</th>
                <th class="px-6 py-4 text-right">İşlemler</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach($pages as $page): ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 font-bold text-slate-800">
                    <?= htmlspecialchars($page['title']) ?>
                </td>
                <td class="px-6 py-4 font-mono text-xs text-slate-500">
                    /<?= htmlspecialchars($page['slug']) ?>.php
                </td>
                <td class="px-6 py-4 text-xs text-slate-500">
                    <?= date('d.m.Y H:i', strtotime($page['updated_at'])) ?>
                </td>
                <td class="px-6 py-4 text-right">
                    <a href="page-edit.php?id=<?= $page['id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-medium bg-indigo-50 px-3 py-1.5 rounded transition-colors">Düzenle</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>