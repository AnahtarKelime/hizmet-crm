<?php
require_once '../config/db.php';
session_start();

// Sadece Provider'lar satın alabilir
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'provider') {
    die("Bu işlemi yapmak için 'Hizmet Veren' hesabıyla giriş yapmalısınız.");
}

// Hem POST (buy-package.php'den) hem GET (register.php'den) destekle
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = $_SESSION['user_id'];
    $packageId = $_REQUEST['package_id'] ?? null;
    $paymentMethod = $_REQUEST['payment_method'] ?? 'cc'; // Varsayılan cc

    if (!$packageId) {
        die("Paket ID eksik.");
        exit;
    }

    try {
        // Paketi Çek
        $stmt = $pdo->prepare("SELECT * FROM subscription_packages WHERE id = ? AND is_active = 1");
        $stmt->execute([$packageId]);
        $package = $stmt->fetch();

        if (!$package) {
            die("Paket bulunamadı.");
        }

        // Açıklama metni
        $methodText = ($paymentMethod === 'bank') ? 'Havale/EFT' : 'Kredi Kartı';
        $description = $package['name'] . ' Satın Alımı (' . $methodText . ')';

        // Transaction Başlat
        $pdo->beginTransaction();

        // 1. Ödeme Kaydı (Simülasyon)
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'subscription_payment', ?)");
        $stmt->execute([$userId, $package['price'], $description]);

        // 2. Kullanıcı Aboneliğini Güncelle
        // Bitiş tarihini hesapla (Şu an + Paket Süresi)
        $endDate = date('Y-m-d H:i:s', strtotime("+{$package['duration_days']} days"));
        
        // Paket tipini belirle (Fiyata göre basit mantık: 0 ise free, değilse premium)
        $subType = ($package['price'] > 0) ? 'premium' : 'free';

        // Provider detaylarını güncelle veya ekle
        $stmt = $pdo->prepare("
            INSERT INTO provider_details (user_id, subscription_type, subscription_ends_at) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                subscription_type = VALUES(subscription_type), 
                subscription_ends_at = VALUES(subscription_ends_at)
        ");
        $stmt->execute([$userId, $subType, $endDate]);

        $pdo->commit();

        // Başarılı sayfasına yönlendir (veya dashboard)
        echo "<script>alert('Paket başarıyla tanımlandı! Bitiş Tarihi: $endDate'); window.location.href='../index.php';</script>";

    } catch (Exception $e) {
        $pdo->rollBack();
        die("İşlem sırasında hata oluştu: " . $e->getMessage());
    }
} else {
    header("Location: buy-package.php");
    exit;
}
?>