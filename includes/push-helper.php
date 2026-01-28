<?php
// Composer autoload dosyasının yolu.
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php'
];

foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        @include_once $path;
        // Kütüphane bulunduysa döngüden çık
        if (class_exists('Minishlink\WebPush\WebPush')) {
            break;
        }
    }
}

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

function sendPushNotification($userId, $title, $body, $url = '/') {
    global $pdo;

    // Bildirimi veritabanına kaydet (Geçmiş için)
    try {
        $stmtNotify = $pdo->prepare("INSERT INTO notifications (user_id, title, message, url) VALUES (?, ?, ?, ?)");
        $stmtNotify->execute([$userId, $title, $body, $url]);
    } catch (Exception $e) {
        // Loglama yapılabilir, akışı bozmamak için sessiz geçiyoruz
    }

    // Ayarları Çek
    $stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'vapid_%'");
    $settings = [];
    while ($row = $stmtSettings->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // Kütüphane yüklü değilse işlem yapma
    if (!class_exists('Minishlink\WebPush\WebPush')) {
        return false;
    }

    if (empty($settings['vapid_public_key']) || empty($settings['vapid_private_key'])) {
        return false; // VAPID anahtarları ayarlanmamış
    }

    // Kullanıcının aboneliklerini çek
    $stmt = $pdo->prepare("SELECT * FROM push_subscriptions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $subscriptions = $stmt->fetchAll();

    if (empty($subscriptions)) {
        return false;
    }

    // WebPush Başlat
    $auth = [
        'VAPID' => [
            'subject' => $settings['vapid_subject'] ?? 'mailto:admin@example.com',
            'publicKey' => $settings['vapid_public_key'],
            'privateKey' => $settings['vapid_private_key'],
        ],
    ];

    try {
        $webPush = new WebPush($auth);

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url
        ]);

        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub['endpoint'],
                'publicKey' => $sub['public_key'],
                'authToken' => $sub['auth_token'],
            ]);
            $webPush->queueNotification($subscription, $payload);
        }

        foreach ($webPush->flush() as $report) {
            // Gerekirse başarısız olanları silebilirsiniz: $report->isSuccess()
            if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
                $delStmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?");
                $delStmt->execute([$report->getEndpoint()]);
            }
        }
        return true;
    } catch (Exception $e) {
        error_log("Push Notification Error: " . $e->getMessage());
        return false;
    }
}