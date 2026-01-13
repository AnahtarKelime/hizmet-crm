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
    $categories = $_POST['categories'] ?? [];
    $city = $_POST['city'] ?? '';
    $districts = $_POST['districts'] ?? null;

    try {
        $pdo->beginTransaction();

        // 1. Kategorileri Kaydet
        if (!empty($categories)) {
            // Önce eskileri temizle (Güncelleme mantığı için)
            $stmt = $pdo->prepare("DELETE FROM provider_service_categories WHERE user_id = ?");
            $stmt->execute([$userId]);

            $stmt = $pdo->prepare("INSERT INTO provider_service_categories (user_id, category_id) VALUES (?, ?)");
            foreach ($categories as $catId) {
                $stmt->execute([$userId, $catId]);
            }
        }

        // 2. Hizmet Bölgesini Kaydet
        if (!empty($city)) {
            // Tek bölge varsayımı (Geliştirilebilir)
            $stmt = $pdo->prepare("DELETE FROM provider_service_areas WHERE user_id = ?");
            $stmt->execute([$userId]);

            $stmt = $pdo->prepare("INSERT INTO provider_service_areas (user_id, city, districts) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $city, $districts]);
        }

        // 3. Evrakları Yükle ve Kaydet
        $uploadDir = '../uploads/documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $documents = [
            'doc_tax_plate' => 'Vergi Levhası',
            'doc_identity' => 'Kimlik',
            'doc_residence' => 'İkametgah'
        ];

        $stmt = $pdo->prepare("INSERT INTO provider_documents (user_id, document_type, file_path) VALUES (?, ?, ?)");

        foreach ($documents as $inputName => $docType) {
            if (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES[$inputName]['tmp_name'];
                $name = basename($_FILES[$inputName]['name']);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                
                // Güvenlik: Sadece belirli uzantılara izin ver
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                    $newName = $userId . '_' . $inputName . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($tmpName, $uploadDir . $newName)) {
                        $stmt->execute([$userId, $docType, 'uploads/documents/' . $newName]);
                    }
                }
            }
        }

        // 4. Başvuru Durumunu Güncelle
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