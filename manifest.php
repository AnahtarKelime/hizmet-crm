<?php
require_once 'config/db.php';
header('Content-Type: application/json');

// Ayarları çek
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}

$name = $settings['site_title'] ?? 'Hizmet CRM';
$shortName = mb_substr($name, 0, 15);
$themeColor = $settings['theme_color_primary'] ?? '#1a2a6c';
$bgColor = '#ffffff';
$iconSrc = !empty($settings['pwa_icon']) ? $settings['pwa_icon'] : (!empty($settings['site_favicon']) ? $settings['site_favicon'] : 'https://placehold.co/512x512/1a2a6c/ffffff.png?text=App');

// Base URL Tespiti (Mutlak yol için)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['PHP_SELF']);
$baseUrl = rtrim($protocol . "://" . $host . $scriptDir, '/') . '/';

// İkon yolunu mutlak URL'e çevir (Google botları için önemli)
if (!filter_var($iconSrc, FILTER_VALIDATE_URL)) {
    $iconSrc = $baseUrl . ltrim($iconSrc, '/');
}

$output = [
    "name" => $name,
    "short_name" => $shortName,
    "start_url" => "./index.php?utm_source=pwa",
    "display" => "standalone",
    "background_color" => $bgColor,
    "theme_color" => $themeColor,
    "orientation" => "portrait",
    "icons" => [
        [
            "src" => $iconSrc,
            "sizes" => "192x192",
            "type" => "image/png",
            "purpose" => "any maskable"
        ],
        [
            "src" => $iconSrc,
            "sizes" => "512x512",
            "type" => "image/png",
            "purpose" => "any maskable"
        ]
    ]
];

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);