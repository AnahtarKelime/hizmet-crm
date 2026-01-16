<?php
require_once 'config/db.php';
session_start();

// Ayarları veritabanından çek
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'facebook_%'");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$facebookLoginActive = ($settings['facebook_login_active'] ?? '0') == '1';
$appId = trim($settings['facebook_app_id'] ?? '');

if (!$facebookLoginActive || empty($appId)) {
    die("Facebook ile giriş aktif değil veya API bilgileri eksik.");
}

$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? "https://" : "http://";
$redirectUri = $protocol . $_SERVER['HTTP_HOST'] . '/facebook-callback.php';

if (isset($_GET['redirect'])) {
    $_SESSION['social_redirect'] = $_GET['redirect'];
}

$authUrl = 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
    'client_id' => $appId,
    'redirect_uri' => $redirectUri,
    'scope' => 'email,public_profile',
]);

header('Location: ' . $authUrl);
exit();