<?php
require_once 'config/db.php';
header('Content-Type: application/xml; charset=utf-8');

// Base URL Tespiti
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['PHP_SELF']);
$baseUrl = rtrim($protocol . "://" . $host . $scriptDir, '/') . '/';

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    
    <!-- Anasayfa -->
    <url>
        <loc><?= $baseUrl ?></loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Kategoriler -->
    <?php
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT slug, name, updated_at FROM categories WHERE is_active = 1");
        while ($cat = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $url = $baseUrl . 'teklif-al.php?service=' . $cat['slug'];
            $date = date('c', strtotime($cat['updated_at']));
    ?>
    <url>
        <loc><?= $url ?></loc>
        <lastmod><?= $date ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
        <?php 
        // Not: Eğer veritabanına 'image_url' sütunu eklenirse burası aktif edilebilir.
        /*
        <image:image>
            <image:loc><?= $baseUrl ?>uploads/categories/<?= $cat['slug'] ?>.jpg</image:loc>
            <image:title><?= htmlspecialchars($cat['name']) ?></image:title>
        </image:image>
        */
        ?>
    </url>
    <?php 
        }
    } 
    ?>

    <!-- Statik Sayfalar -->
    <url><loc><?= $baseUrl ?>login.php</loc><priority>0.5</priority></url>
    <url><loc><?= $baseUrl ?>register.php</loc><priority>0.5</priority></url>
    <url><loc><?= $baseUrl ?>provider-register.php</loc><priority>0.6</priority></url>
    <url><loc><?= $baseUrl ?>contact.php</loc><priority>0.4</priority></url>

</urlset>