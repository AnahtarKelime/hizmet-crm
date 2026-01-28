<?php
// Hata Raporlama ve Loglama Ayarları
// Canlı ortamda hataları ekrana basma (0), dosyaya kaydet (1)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Hataları ana dizindeki 'error_log.txt' dosyasına yaz
ini_set('error_log', dirname(__DIR__) . '/error_log.txt');

$host = 'localhost';
$db   = 'galataha_hizmetcrm'; // Hosting'de oluşturduğunuz veritabanı adı
$user = 'galataha_user';      // Hosting'de oluşturduğunuz kullanıcı adı
$pass = '6559xZbKXxvGjfK7Cy8G'; // Hosting'de belirlediğiniz şifre
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Bağlantı Hatası: " . $e->getMessage());
}

// Cache Sistemini Başlat
require_once dirname(__DIR__) . '/includes/cache-helper.php';