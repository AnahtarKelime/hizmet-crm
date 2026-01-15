<?php
require_once 'config/db.php';

// Veritabanından içeriği çek
$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ?");
$stmt->execute(['gizlilik-politikasi']);
$page = $stmt->fetch();

$pageTitle = $page ? $page['title'] : "Gizlilik Politikası";
require_once 'includes/header.php';
?>

<main class="flex-1 bg-white dark:bg-background-dark">
    <!-- Header -->
    <div class="bg-slate-50 dark:bg-slate-900 py-16 border-b border-slate-200 dark:border-slate-800">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h1 class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white mb-4"><?= htmlspecialchars($pageTitle) ?></h1>
            <p class="text-slate-500 dark:text-slate-400 text-lg">Kişisel verilerinizin güvenliği bizim için önemlidir.</p>
            <p class="text-sm text-slate-400 mt-4">Son Güncelleme: <?= $page ? date('d.m.Y', strtotime($page['updated_at'])) : date('d.m.Y') ?></p>
        </div>
    </div>

    <?= $page ? $page['content'] : '<div class="p-20 text-center text-slate-500">İçerik bulunamadı.</div>' ?>
</main>

<?php require_once 'includes/footer.php'; ?>