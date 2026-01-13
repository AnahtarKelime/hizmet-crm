<?php
// Çıktı tamponlamayı başlat (Olası boşlukları yakalamak için)
ob_start();
require_once '../config/db.php';
// Tamponu temizle (db.php'den gelen boşlukları siler)
ob_clean();

header('Content-Type: application/json; charset=utf-8');

$type = $_GET['type'] ?? '';
$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    if ($type === 'service') {
        // Kategorilerde ara (Aktif olanlar)
        
        // Önce keywords sütununun var olup olmadığını kontrol edelim (Hata önleyici)
        $columnCheck = $pdo->query("SHOW COLUMNS FROM categories LIKE 'keywords'");
        $hasKeywords = $columnCheck->fetch() !== false;

        if ($hasKeywords) {
            $stmt = $pdo->prepare("SELECT name, slug, icon, keywords FROM categories WHERE (name LIKE :q1 OR keywords LIKE :q2) AND is_active = 1 LIMIT 5");
            $stmt->execute(['q1' => "%$query%", 'q2' => "%$query%"]);
        } else {
            $stmt = $pdo->prepare("SELECT name, slug, icon FROM categories WHERE name LIKE :q AND is_active = 1 LIMIT 5");
            $stmt->execute(['q' => "%$query%"]);
        }

        $results = $stmt->fetchAll();

        foreach ($results as &$row) {
            $row['matched_keyword'] = null;
            // Eğer aranan terim kategori adında geçmiyorsa, hangi keyword ile eşleştiğini bul
            if ($hasKeywords && !empty($row['keywords']) && function_exists('mb_stripos') && mb_stripos((string)$row['name'], $query, 0, 'UTF-8') === false) {
                $keywords = explode(',', $row['keywords']);
                foreach ($keywords as $keyword) {
                    if (mb_stripos(trim($keyword), $query, 0, 'UTF-8') !== false) {
                        $row['matched_keyword'] = trim($keyword);
                        break;
                    }
                }
            }
            if(isset($row['keywords'])) unset($row['keywords']); // Gereksiz veriyi temizle
        }
        echo json_encode($results);
    } elseif ($type === 'location') {
        // Lokasyonlarda ara (İl, İlçe veya Mahalle)
        $stmt = $pdo->prepare("
            SELECT id, city, district, neighborhood, slug 
            FROM locations 
            WHERE city LIKE :q1 OR district LIKE :q2 OR neighborhood LIKE :q3 
            LIMIT 10
        ");
        $stmt->execute(['q1' => "%$query%", 'q2' => "%$query%", 'q3' => "%$query%"]);
        echo json_encode($stmt->fetchAll());
    } else {
        echo json_encode([]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}