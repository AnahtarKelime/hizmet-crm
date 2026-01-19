<?php
require_once '../config/db.php';
session_start();

// Admin hariç giriş yapmış herkes başvuru yapabilir (Müşteri veya Provider)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] === 'admin') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $categoryId = $_POST['category_id'] ?? null;
    $city = $_POST['city'] ?? '';
    $districtsInput = $_POST['districts'] ?? null;

    $districts = null;
    if (is_array($districtsInput)) {
        $districts = implode(', ', $districtsInput);
    } elseif (is_string($districtsInput)) {
        $districts = $districtsInput;
    }

    try {
        $pdo->beginTransaction();

        // 1. Kategorileri Kaydet
        if ($categoryId) {
            // Önce eskileri temizle (Güncelleme mantığı için)
            $stmt = $pdo->prepare("DELETE FROM provider_service_categories WHERE user_id = ?");
            $stmt->execute([$userId]);

            $stmt = $pdo->prepare("INSERT INTO provider_service_categories (user_id, category_id) VALUES (?, ?)");
            $stmt->execute([$userId, $categoryId]);
        }

        // 2. Hizmet Bölgesini Kaydet
        if (!empty($city)) {
            // Tek bölge varsayımı (Geliştirilebilir)
            $stmt = $pdo->prepare("DELETE FROM provider_service_areas WHERE user_id = ?");
            $stmt->execute([$userId]);

            $stmt = $pdo->prepare("INSERT INTO provider_service_areas (user_id, city, districts) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $city, $districts]);
        }

        // 3. Başvuru Durumunu Güncelle
        $stmt = $pdo->prepare("
            INSERT INTO provider_details (user_id, application_status) 
            VALUES (?, 'pending') 
            ON DUPLICATE KEY UPDATE application_status = 'pending'
        ");
        $stmt->execute([$userId]);

        $pdo->commit();

        // Başarılı -> Başvuru sonuç sayfasına yönlendir
        header("Location: application-success.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Hata oluştu: " . $e->getMessage());
    }
} else {
    header("Location: apply.php");
}
?>