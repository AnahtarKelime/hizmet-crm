<?php
session_start();
require_once 'config/db.php';
require_once 'includes/mail-helper.php';
require_once 'includes/push-helper.php';

$userId = null;
$autoLoginToken = null; // Token değişkenini başlat

// 1. Kullanıcı Kimlik Doğrulama veya Misafir Kaydı
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['guest_email'])) {
    // Misafir Girişi
    $guestName = trim($_POST['guest_name'] ?? '');
    $guestSurname = trim($_POST['guest_surname'] ?? '');
    $guestEmail = trim($_POST['guest_email'] ?? '');
    $guestPhone = trim($_POST['guest_phone'] ?? '');

    // E-posta veya Telefon kontrolü (Duplicate entry hatasını önlemek için)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$guestEmail, $guestPhone]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        // Kullanıcı varsa talebi ona bağla
        $userId = $existingUser['id'];
    } else {
        // Yeni kullanıcı oluştur
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, role, is_verified, created_at) VALUES (?, ?, ?, ?, 'customer', 0, NOW())");
        $stmt->execute([$guestName, $guestSurname, $guestEmail, $guestPhone]);
        $userId = $pdo->lastInsertId();

        // Token oluştur ve kaydet (24 saat geçerli)
        $token = bin2hex(random_bytes(32));
        $autoLoginToken = $token; // Token'ı sakla
        $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?")->execute([$token, $userId]);

        // Hoşgeldin ve Şifre Oluşturma Maili Gönder
        sendEmail($guestEmail, 'guest_welcome', [
            'name' => $guestName . ' ' . $guestSurname,
            'link' => getBaseUrl() . '/set-password.php?token=' . $token
        ]);

        // Yeni kullanıcıyı otomatik giriş yaptır
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $guestName . ' ' . $guestSurname;
        $_SESSION['user_role'] = 'customer';
    }
} else {
    // Giriş yok ve misafir verisi yok
    header("Location: login.php?error=login_required");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId = $_POST['category_id'] ?? null;
    
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

    // Kategori bilgilerini (is_location_required) çek
    $stmtCat = $pdo->prepare("SELECT slug, is_location_required FROM categories WHERE id = ?");
    $stmtCat->execute([$categoryId]);
    $catData = $stmtCat->fetch();

    // Konum Zorunluluğu Kontrolü
    if (empty($gAddress) && $catData && $catData['is_location_required']) {
        header("Location: teklif-al.php?service=" . $catData['slug'] . "&error=location_missing");
        exit;
    } elseif (empty($gAddress)) {
        // Konum zorunlu değilse ve boşsa varsayılan değerler
        $gAddress = "Online / Konumsuz";
        $gCity = "Genel";
    }

    try {
        // 1. Lokasyon ID Belirleme
        $locationId = null;
        $locationTitlePart = "";

        if ($gCity && $gDistrict) {
            $stmtLoc = $pdo->prepare("SELECT id, city, district FROM locations WHERE city = ? AND district = ? LIMIT 1");
            $stmtLoc->execute([$gCity, $gDistrict]);
            $matchedLocation = $stmtLoc->fetch();

            if ($matchedLocation) {
                $locationId = $matchedLocation['id'];
                $locationTitlePart = $matchedLocation['district'] . ' / ' . $matchedLocation['city'];
            }
        }

        // Fallback: Eğer Google verisiyle eşleşme yoksa, formdan gelen slug'ı kullan
        if (!$locationId && !empty($_POST['location_slug'])) {
            $stmtSlug = $pdo->prepare("SELECT id, city, district FROM locations WHERE slug = ?");
            $stmtSlug->execute([$_POST['location_slug']]);
            $slugLocation = $stmtSlug->fetch();

            if ($slugLocation) {
                $locationId = $slugLocation['id'];
            }
        }

        // Fallback 2: Hala yoksa veritabanındaki ilk lokasyonu al (Hata vermemesi için)
        if (!$locationId) {
             $stmtFirst = $pdo->query("SELECT id FROM locations LIMIT 1");
             $locationId = $stmtFirst->fetchColumn();
        }

        // 2. Kategori bilgisini çek
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $category = $stmt->fetch();

        if (!$category) {
            die("Hata: Geçersiz kategori.");
        }

        // Otomatik başlık oluştur
        $title = ($locationTitlePart ?: $gCity) . ' ' . $category['name'];

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
        $stmtAnswer = $pdo->prepare("INSERT INTO demand_answers (demand_id, question_id, answer_text) VALUES (?, ?, ?)");

        foreach ($answers as $questionId => $answerValue) {
            $answerText = is_array($answerValue) ? implode(', ', $answerValue) : trim($answerValue);
            if ($answerText !== '') {
                $stmtAnswer->execute([$demandId, $questionId, $answerText]);
            }
        }

        // 5. Dosya Yüklemelerini İşle (Resim Soruları)
        if (!empty($_FILES['answers']['name'])) {
            $uploadDir = 'uploads/demands/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            foreach ($_FILES['answers']['name'] as $qId => $fName) {
                if ($_FILES['answers']['error'][$qId] === UPLOAD_ERR_OK) {
                    // Dosya Boyutu Kontrolü (10MB)
                    if ($_FILES['answers']['size'][$qId] > 10485760) {
                        continue; // Dosya çok büyükse atla
                    }
                    $tmpName = $_FILES['answers']['tmp_name'][$qId];
                    $ext = strtolower(pathinfo($fName, PATHINFO_EXTENSION));
                    // İzin verilen dosya uzantıları (Görsel + Doküman)
                    if(in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip', 'rar', 'psd', 'ai'])) {
                        $newFileName = 'demand_' . $demandId . '_' . $qId . '_' . uniqid() . '.' . $ext;
                        if(move_uploaded_file($tmpName, $uploadDir . $newFileName)) {
                            $stmtAnswer->execute([$demandId, $qId, $uploadDir . $newFileName]);
                        }
                    }
                }
            }
        }

        // İşlemi onayla
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Veritabanı hatası: " . $e->getMessage());
    }

    // --- BİLDİRİMLER (Transaction dışı - Hata olsa bile talep oluşmuş olur) ---
    try {
        // 1. KULLANICIYA BİLDİRİM
        $stmtUser = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $currentUser = $stmtUser->fetch();

        if ($currentUser) {
            $demandLink = getBaseUrl() . '/demand-details.php?id=' . $demandId;
            
            // Eğer misafir kaydıysa ve token varsa linke ekle
            if ($autoLoginToken) {
                $demandLink .= '&auth_token=' . $autoLoginToken;
            }

            sendEmail($currentUser['email'], 'demand_created', [
                'name' => $currentUser['first_name'] . ' ' . $currentUser['last_name'],
                'demand_title' => $title,
                'link' => $demandLink
            ]);
        }

        // 2. HİZMET VERENLERE BİLDİRİM
        $stmtProviders = $pdo->prepare("
            SELECT u.id, u.email, u.first_name, u.last_name 
            FROM users u
            JOIN provider_service_categories psc ON u.id = psc.user_id
            LEFT JOIN provider_service_areas psa ON u.id = psa.user_id
            WHERE u.role = 'provider' AND psc.category_id = :category_id AND (psa.city = :city OR psa.city = 'Tümü')
            GROUP BY u.id
        ");
        // $gCity null ise boş string gönderelim
        $stmtProviders->execute([':category_id' => $categoryId, ':city' => (string)$gCity]);
        $providers = $stmtProviders->fetchAll();

        foreach ($providers as $prov) {
            sendEmail($prov['email'], 'new_lead', ['name' => $prov['first_name'] . ' ' . $prov['last_name'], 'demand_title' => $title, 'link' => getBaseUrl() . '/provider/leads.php']);
            $cityText = $gCity ? "$gCity bölgesinde" : "Yeni bir";
            sendPushNotification($prov['id'], 'Yeni İş Fırsatı 🔔', "$cityText {$category['name']} talebi oluşturuldu. Hemen teklif ver!", getBaseUrl() . '/demand-details.php?id=' . $demandId);
        }
    } catch (Exception $e) {
        // Bildirim hatası oluşursa logla ama kullanıcıyı durdurma
        error_log("Bildirim Hatası: " . $e->getMessage());
    }

    header("Location: demand-details.php?id=$demandId&status=success&msg=" . urlencode("Talep başarıyla oluşturuldu"));
    exit;
} else {
    header("Location: index.php");
    exit;
}