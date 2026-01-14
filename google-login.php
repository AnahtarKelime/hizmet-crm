<?php
require_once 'config/db.php';
session_start();

// Ayarları veritabanından çek
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'google_%'");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$googleLoginActive = ($settings['google_login_active'] ?? '0') == '1';
$clientId = $settings['google_client_id'] ?? '';
$clientSecret = $settings['google_client_secret'] ?? '';

if (!$googleLoginActive || empty($clientId) || empty($clientSecret)) {
    die("Google ile giriş aktif değil veya API bilgileri eksik.");
}

$redirectUri = 'http://' . $_SERVER['HTTP_HOST'] . '/google-callback.php';

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'response_type' => 'code',
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'scope' => 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email',
    'access_type' => 'offline',
    'prompt' => 'select_account'
]);

header('Location: ' . $authUrl);
exit();