<?php
require_once 'config/db.php';

// Veritabanından içeriği çek
$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ?");
$stmt->execute(['hakkimizda']);
$page = $stmt->fetch();

$pageTitle = $page ? $page['title'] : "Hakkımızda";
require_once 'includes/header.php';
?>

<main class="flex-1">
    <?= $page ? $page['content'] : '<div class="p-20 text-center text-slate-500">İçerik bulunamadı.</div>' ?>
</main>

<?php require_once 'includes/footer.php'; ?>