<?php
require_once 'config/db.php';
session_start();

// Ayarları veritabanından çek
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'facebook_%'");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$appId = trim($settings['facebook_app_id'] ?? '');
$appSecret = trim($settings['facebook_app_secret'] ?? '');
$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? "https://" : "http://";
$redirectUri = $protocol . $_SERVER['HTTP_HOST'] . '/facebook-callback.php';

if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // 1. Access Token Al
    $tokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
        'client_id' => $appId,
        'redirect_uri' => $redirectUri,
        'client_secret' => $appSecret,
        'code' => $code,
    ]);

    $tokenResponse = file_get_contents($tokenUrl);
    $tokenData = json_decode($tokenResponse, true);

    if (isset($tokenData['access_token'])) {
        // 2. Kullanıcı Bilgilerini Al
        $userInfoUrl = 'https://graph.facebook.com/me?fields=id,first_name,last_name,email,picture.type(large)&access_token=' . $tokenData['access_token'];
        $userInfoResponse = file_get_contents($userInfoUrl);
        $userInfo = json_decode($userInfoResponse, true);

        if (isset($userInfo['id'])) {
            $facebookId = $userInfo['id'];
            $email = $userInfo['email'] ?? null;
            $firstName = $userInfo['first_name'] ?? '';
            $lastName = $userInfo['last_name'] ?? '';
            $avatarUrl = $userInfo['picture']['data']['url'] ?? null;

            // E-posta adresi zorunlu
            if (!$email) {
                header('Location: login.php?error=facebook_email_required');
                exit();
            }

            // 3. Kullanıcıyı Veritabanında Kontrol Et
            // Önce Facebook ID ile kontrol et
            $stmt = $pdo->prepare("SELECT * FROM users WHERE facebook_id = ?");
            $stmt->execute([$facebookId]);
            $user = $stmt->fetch();

            if ($user) {
                // Kullanıcı zaten Facebook ile bağlı. Avatarı yoksa güncelle.
                if (empty($user['avatar_url']) && !empty($avatarUrl)) {
                    $updateStmt = $pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
                    $updateStmt->execute([$avatarUrl, $user['id']]);
                }
            } else {
                // Facebook ID ile bulunamazsa, e-posta ile kontrol et
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                // E-posta ile bulunduysa, Facebook ID'yi bu kullanıcıya bağla
                if ($user) {
                    $updateStmt = $pdo->prepare("UPDATE users SET facebook_id = ?, avatar_url = IF(avatar_url IS NULL OR avatar_url = '', ?, avatar_url) WHERE id = ?");
                    $updateStmt->execute([$facebookId, $avatarUrl, $user['id']]);
                }
            }

            // Kullanıcı hiç bulunamadıysa, yeni bir tane oluştur
            if (!$user) {
                // Rastgele bir şifre oluştur
                
                $insertStmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, role, is_verified, facebook_id, avatar_url) VALUES (?, ?, ?, 'customer', 1, ?, ?)");
                $insertStmt->execute([$firstName, $lastName, $email, $facebookId, $avatarUrl]);
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
                
                // Eksik bilgi kontrolü
                if (empty($user['phone']) || empty($user['city']) || empty($user['district'])) {
                    header('Location: complete-profile.php');
                } else {
                    if (isset($_SESSION['social_redirect'])) {
                        $redirect = $_SESSION['social_redirect'];
                        unset($_SESSION['social_redirect']);
                        header('Location: ' . $redirect);
                    } else {
                        header('Location: index.php');
                    }
                }
                exit();
            } else {
                header('Location: login.php?error=social_login_failed');
                exit();
            }
        }
    }
}

// Hata durumunda login sayfasına yönlendir
header('Location: login.php?error=facebook_auth_failed');
exit();