<?php
session_start();
require_once 'config/db.php';

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    // Giriş yapmamışsa login sayfasına yönlendir
    header("Location: login.php?error=login_required");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $categoryId = $_POST['category_id'] ?? null;
    $locationSlug = $_POST['location_slug'] ?? null;
    $answers = $_POST['answers'] ?? [];

    // Temel validasyon
    if (!$categoryId || !$locationSlug) {
        die("Hata: Kategori veya lokasyon bilgisi eksik.");
    }

    try {
        // 1. Lokasyon bilgisini çek
        $stmt = $pdo->prepare("SELECT id, city, district, neighborhood FROM locations WHERE slug = ?");
        $stmt->execute([$locationSlug]);
        $location = $stmt->fetch();

        if (!$location) {
            die("Hata: Geçersiz lokasyon.");
        }

        // 2. Kategori bilgisini çek
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $category = $stmt->fetch();

        if (!$category) {
            die("Hata: Geçersiz kategori.");
        }

        // Otomatik başlık oluştur (Örn: "Kadıköy Caferağa Ev Temizliği")
        $title = $location['district'] . ' ' . $location['neighborhood'] . ' ' . $category['name'];

        // Transaction başlat
        $pdo->beginTransaction();

        // 3. Talebi (Lead) demands tablosuna kaydet
        $stmt = $pdo->prepare("
            INSERT INTO demands (user_id, category_id, location_id, title, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$userId, $categoryId, $location['id'], $title]);
        $demandId = $pdo->lastInsertId();

        // 4. Cevapları demand_answers tablosuna kaydet
        $stmtAnswer = $pdo->prepare("
            INSERT INTO demand_answers (demand_id, question_id, answer_text) 
            VALUES (?, ?, ?)
        ");

        foreach ($answers as $questionId => $answerValue) {
            // Checkbox gibi çoklu seçimler array gelebilir, string'e çeviriyoruz
            if (is_array($answerValue)) {
                $answerText = implode(', ', $answerValue);
            } else {
                $answerText = trim($answerValue);
            }

            // Boş cevapları kaydetmeyebiliriz veya boş string olarak kaydedebiliriz
            if ($answerText !== '') {
                $stmtAnswer->execute([$demandId, $questionId, $answerText]);
            }
        }

        // İşlemi onayla
        $pdo->commit();

        // Başarılı işlem sonrası yönlendirme
        header("Location: demand-details.php?id=$demandId&status=success&msg=Talep+başarıyla+oluşturuldu");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Veritabanı hatası: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit;
}