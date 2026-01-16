<?php
require_once 'config/db.php';

// Canlı site adresi (Sonunda / olmasın)
$baseUrl = "https://iyiteklif.com.tr";

header("Content-Type: application/xml; charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

// 1. Statik Sayfalar
$staticPages = [
    '/' => ['priority' => '1.0', 'freq' => 'daily'],
    '/nasil-calisir.php' => ['priority' => '0.8', 'freq' => 'monthly'],
    '/tum-hizmetler.php' => ['priority' => '0.9', 'freq' => 'weekly'],
    '/provider/apply.php' => ['priority' => '0.8', 'freq' => 'monthly'],
    '/login.php' => ['priority' => '0.6', 'freq' => 'yearly'],
    '/register.php' => ['priority' => '0.6', 'freq' => 'yearly'],
];

foreach ($staticPages as $url => $data) {
    echo '<url>';
    echo '<loc>' . $baseUrl . $url . '</loc>';
    echo '<changefreq>' . $data['freq'] . '</changefreq>';
    echo '<priority>' . $data['priority'] . '</priority>';
    echo '</url>';
}

// 2. Dinamik Kategoriler (Hizmetler)
try {
    $stmt = $pdo->query("SELECT slug, updated_at FROM categories WHERE is_active = 1");
    while ($row = $stmt->fetch()) {
        echo '<url>';
        echo '<loc>' . $baseUrl . '/teklif-al.php?service=' . htmlspecialchars($row['slug']) . '</loc>';
        echo '<lastmod>' . date('Y-m-d', strtotime($row['updated_at'])) . '</lastmod>';
        echo '<changefreq>weekly</changefreq>';
        echo '<priority>0.9</priority>';
        echo '</url>';
    }
} catch (PDOException $e) {
    // Hata durumunda sessiz kal
}

echo '</urlset>';
?>