<?php
require_once '../config/db.php';
session_start();

// Sadece Provider'lar satın alabilir
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['provider', 'admin'])) {
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

        // Durum Belirleme: Banka ise 'pending', Kredi Kartı ise 'approved'
        $status = ($paymentMethod === 'bank') ? 'pending' : 'approved';

        // Transaction Başlat
        $pdo->beginTransaction();

        // 1. Ödeme Kaydı
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description, status, package_id) VALUES (?, ?, 'subscription_payment', ?, ?, ?)");
        $stmt->execute([$userId, $package['price'], $description, $status, $package['id']]);

        // 2. Eğer Kredi Kartı ise (Otomatik Onay) Aboneliği Hemen Başlat
        if ($status === 'approved') {
            // Mevcut abonelik durumunu kontrol et
            $stmtCheck = $pdo->prepare("SELECT subscription_ends_at, remaining_offer_credit FROM provider_details WHERE user_id = ?");
            $stmtCheck->execute([$userId]);
            $currentDetails = $stmtCheck->fetch();

            // Bitiş tarihini hesapla (Mevcut süre varsa üstüne ekle)
            $currentEndDate = ($currentDetails && $currentDetails['subscription_ends_at'] && new DateTime($currentDetails['subscription_ends_at']) > new DateTime()) 
                ? $currentDetails['subscription_ends_at'] 
                : date('Y-m-d H:i:s');
            
            $endDate = date('Y-m-d H:i:s', strtotime($currentEndDate . " +{$package['duration_days']} days"));
            
            // Paket tipini belirle
            $subType = ($package['price'] > 0) ? 'premium' : 'free';
            $offerCredit = $package['offer_credit'];

            // Provider detaylarını güncelle (Krediyi de üstüne ekle)
            $stmt = $pdo->prepare("
                INSERT INTO provider_details (user_id, subscription_type, subscription_ends_at, remaining_offer_credit) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                    subscription_type = VALUES(subscription_type), 
                    subscription_ends_at = VALUES(subscription_ends_at),
                    remaining_offer_credit = IF(remaining_offer_credit = -1 OR VALUES(remaining_offer_credit) = -1, -1, remaining_offer_credit + VALUES(remaining_offer_credit))
            ");
            $stmt->execute([$userId, $subType, $endDate, $offerCredit]);
            
            $msg = "Paket başarıyla tanımlandı! Bitiş Tarihi: $endDate";
        } else {
            $msg = "Ödeme bildiriminiz alındı. Yönetici onayından sonra paketiniz aktifleşecektir.";
        }

        $pdo->commit();

        // Yönlendirme
        echo "<script>alert('$msg'); window.location.href='../index.php';</script>";

    } catch (Exception $e) {
        $pdo->rollBack();
        die("İşlem sırasında hata oluştu: " . $e->getMessage());
    }
} else {
    header("Location: buy-package.php");
    exit;
}
?>