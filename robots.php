<?php
header('Content-Type: text/plain; charset=UTF-8');

// Base URL Tespiti
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['PHP_SELF']);
$baseUrl = rtrim($protocol . "://" . $host . $scriptDir, '/') . '/';
?>
User-agent: *
Allow: /

# Admin ve Özel Klasörleri Engelle
Disallow: /admin/
Disallow: /includes/
Disallow: /config/
Disallow: /uploads/
Disallow: /provider/
Disallow: /assets/

# Kullanıcı Özel Sayfalarını Engelle
Disallow: /my-demands.php
Disallow: /messages.php
Disallow: /profile.php
Disallow: /demand-details.php
Disallow: /offer-details.php
Disallow: /save-demand.php
Disallow: /logout.php

# Sitemap Yolu
Sitemap: <?= $baseUrl ?>sitemap.php