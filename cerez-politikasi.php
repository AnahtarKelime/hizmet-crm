<?php
require_once 'config/db.php';

$pageSlug = 'cerez-politikasi';

// Sayfa içeriğini veritabanından çek
$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ?");
$stmt->execute([$pageSlug]);
$page = $stmt->fetch();

// Eğer sayfa bulunamazsa, basit bir hata mesajı göster
if (!$page) {
    http_response_code(404);
    $pageTitle = "Sayfa Bulunamadı";
    require_once 'includes/header.php';
    echo "<main><div class='text-center py-20 max-w-4xl mx-auto px-4'>
            <h1 class='text-3xl font-bold text-primary dark:text-white mb-4'>404 - Sayfa Bulunamadı</h1>
            <p class='text-slate-600 dark:text-slate-400'>Aradığınız sayfa mevcut değil veya taşınmış olabilir.</p>
            <a href='index.php' class='mt-8 inline-block bg-primary text-white font-bold px-6 py-3 rounded-lg hover:bg-primary/90 transition-colors'>Anasayfaya Dön</a>
          </div></main>";
    require_once 'includes/footer.php';
    exit;
}

$pageTitle = $page['title'];
require_once 'includes/header.php';
?>

<main class="bg-background-light dark:bg-background-dark">
    <?= $page['content'] ?>
</main>

<?php require_once 'includes/footer.php'; ?>