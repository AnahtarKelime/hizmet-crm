<?php
session_start();
require_once 'config/db.php';
require_once 'includes/mail-helper.php';

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
    
    // Google Verileri
    $gAddress = $_POST['g_address'] ?? null;
    $gLat = $_POST['g_lat'] ?? null;
    $gLng = $_POST['g_lng'] ?? null;
    $gCity = $_POST['g_city'] ?? null;
    $gDistrict = $_POST['g_district'] ?? null;

    $answers = $_POST['answers'] ?? [];

    // Temel validasyon
    if (!$categoryId) {
        die("Hata: Kategori bilgisi eksik.");
    }

    // Konum Zorunluluğu Kontrolü
    if (empty($gAddress) && empty($locationSlug)) {
        header("Location: teklif-al.php?service=" . ($category['slug'] ?? '') . "&error=location_missing");
        exit;
    }

    try {
        // 1. Lokasyon ID Belirleme
        $locationId = null;
        $locationTitlePart = "";

        // Eğer Google'dan İl/İlçe geldiyse, veritabanımızda eşleşen bir lokasyon var mı bakalım
        if ($gCity && $gDistrict) {
            // Basit bir eşleştirme: Şehir ve İlçe adı geçen herhangi bir lokasyon ID'si al
            // Bu, hizmet verenlerin bölge eşleşmesi için gereklidir.
            $stmtLoc = $pdo->prepare("SELECT id, city, district, neighborhood FROM locations WHERE city LIKE ? AND district LIKE ? LIMIT 1");
            $stmtLoc->execute(["%$gCity%", "%$gDistrict%"]);
            $matchedLocation = $stmtLoc->fetch();

            if ($matchedLocation) {
                $locationId = $matchedLocation['id'];
                $locationTitlePart = $matchedLocation['district'] . ' / ' . $matchedLocation['city'];
            }
        }

        // Eğer Google eşleşmesi yoksa veya Google verisi yoksa, slug'dan git
        if (!$locationId && $locationSlug) {
            $stmt = $pdo->prepare("SELECT id, city, district, neighborhood FROM locations WHERE slug = ?");
            $stmt->execute([$locationSlug]);
            $location = $stmt->fetch();
            if ($location) {
                $locationId = $location['id'];
                $locationTitlePart = $location['district'] . ' ' . $location['neighborhood'];
            }
        }

        // Hala locationId yoksa, varsayılan bir ID ata (Sistemin çökmemesi için)
        if (!$locationId) {
            $stmt = $pdo->query("SELECT id FROM locations LIMIT 1");
            $locationId = $stmt->fetchColumn();

            if (!$locationId) {
                // Veritabanında hiç lokasyon yoksa varsayılan oluştur
                $pdo->exec("INSERT IGNORE INTO locations (city, district, neighborhood, slug) VALUES ('Genel', 'Merkez', 'Merkez', 'genel-merkez')");
                $locationId = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM locations LIMIT 1")->fetchColumn();
            }

            $locationTitlePart = "Genel";
        }

        // 2. Kategori bilgisini çek
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $category = $stmt->fetch();

        if (!$category) {
            die("Hata: Geçersiz kategori.");
        }

        // Otomatik başlık oluştur (Örn: "Kadıköy Caferağa Ev Temizliği")
        $title = $locationTitlePart . ' ' . $category['name'];

        // Transaction başlat
        $pdo->beginTransaction();

        // 3. Talebi (Lead) demands tablosuna kaydet
        $stmt = $pdo->prepare("
            INSERT INTO demands (user_id, category_id, location_id, title, address_text, latitude, longitude, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', NOW())
        ");
        $stmt->execute([$userId, $categoryId, $locationId, $title, $gAddress, $gLat, $gLng]);
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

        // --- KULLANICIYA BİLDİRİM (Talep Onaylandı) ---
        $stmtUser = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $currentUser = $stmtUser->fetch();

        if ($currentUser) {
            sendEmail($currentUser['email'], 'demand_created', [
                'name' => $currentUser['first_name'] . ' ' . $currentUser['last_name'],
                'demand_title' => $title,
                'link' => getBaseUrl() . '/demand-details.php?id=' . $demandId
            ]);
        }

        // --- HİZMET VERENLERE BİLDİRİM (New Lead) ---
        // İlgili kategoride ve şehirde hizmet verenleri bul
        // Not: Çok fazla kullanıcı varsa bu işlem arka planda (queue) yapılmalıdır.
        // Şimdilik basitçe ilk 20 kişiye gönderelim.
        $stmtProviders = $pdo->prepare("
            SELECT u.email, u.first_name, u.last_name 
            FROM users u
            JOIN provider_service_categories psc ON u.id = psc.user_id
            JOIN provider_service_areas psa ON u.id = psa.user_id
            WHERE u.role = 'provider' 
            AND psc.category_id = ? 
            AND psa.city LIKE ?
            LIMIT 20
        ");
        // Şehir eşleşmesi için basit like kullanıyoruz, daha gelişmiş yapılabilir
        $citySearch = explode(' ', trim($locationTitlePart))[0] ?? ''; // İlk kelimeyi al (Örn: İstanbul)
        $stmtProviders->execute([$categoryId, "%$citySearch%"]);
        $providers = $stmtProviders->fetchAll();

        foreach ($providers as $prov) {
            sendEmail($prov['email'], 'new_lead', [
                'name' => $prov['first_name'] . ' ' . $prov['last_name'],
                'demand_title' => $title,
                'link' => getBaseUrl() . '/provider/leads.php'
            ]);
        }

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