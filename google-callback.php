<?php
require_once 'config/db.php';
session_start();

// Ayarları veritabanından çek
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'google_%'");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$clientId = $settings['google_client_id'] ?? '';
$clientSecret = $settings['google_client_secret'] ?? '';
$redirectUri = 'http://' . $_SERVER['HTTP_HOST'] . '/google-callback.php';

if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // 1. Access Token Al
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $postData = [
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $tokenData = json_decode($response, true);

    if (isset($tokenData['access_token'])) {
        // 2. Kullanıcı Bilgilerini Al
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . $tokenData['access_token'];
        $userInfoResponse = file_get_contents($userInfoUrl);
        $userInfo = json_decode($userInfoResponse, true);

        if (isset($userInfo['id'])) {
            $googleId = $userInfo['id'];
            $email = $userInfo['email'];
            $firstName = $userInfo['given_name'] ?? '';
            $lastName = $userInfo['family_name'] ?? '';
            $avatarUrl = $userInfo['picture'] ?? null;

            // 3. Kullanıcıyı Veritabanında Kontrol Et
            // Önce Google ID ile kontrol et
            $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
            $stmt->execute([$googleId]);
            $user = $stmt->fetch();

            // Google ID ile bulunamazsa, e-posta ile kontrol et
            if (!$user) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                // E-posta ile bulunduysa, Google ID'yi bu kullanıcıya bağla
                if ($user) {
                    $updateStmt = $pdo->prepare("UPDATE users SET google_id = ?, avatar_url = ? WHERE id = ?");
                    $updateStmt->execute([$googleId, $avatarUrl, $user['id']]);
                }
            }

            // Kullanıcı hiç bulunamadıysa, yeni bir tane oluştur
            if (!$user) {
                // Rastgele bir şifre oluştur, çünkü sosyal girişlerde şifre kullanılmaz
                $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                
                $insertStmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, is_verified, google_id, avatar_url) VALUES (?, ?, ?, ?, 'customer', 1, ?, ?)");
                $insertStmt->execute([$firstName, $lastName, $email, $randomPassword, $googleId, $avatarUrl]);
                $userId = $pdo->lastInsertId();

                // Yeni oluşturulan kullanıcıyı tekrar çek
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            }

            // 4. Oturum Başlat ve Yönlendir
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_role'] = $user['role'];
                header('Location: index.php');
                exit();
            } else {
                // Bu durumun olmaması gerekir
                header('Location: login.php?error=social_login_failed');
                exit();
            }
        }
    }
}

// Hata durumunda login sayfasına yönlendir
header('Location: login.php?error=google_auth_failed');
exit();