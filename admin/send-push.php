<?php
require_once '../config/db.php';
// WebPush sınıflarını kullanabilmek için helper'ı dahil ediyoruz
require_once '../includes/push-helper.php'; 
require_once 'includes/header.php';

// Yetki Kontrolü
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $url = trim($_POST['url'] ?? '/');
    $target = $_POST['target'] ?? 'all';

    if (empty($title) || empty($body)) {
        $errorMsg = "Başlık ve mesaj içeriği zorunludur.";
    } else {
        // VAPID Ayarlarını Çek
        $stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'vapid_%'");
        $settings = [];
        while ($row = $stmtSettings->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        if (empty($settings['vapid_public_key']) || empty($settings['vapid_private_key'])) {
            $errorMsg = "VAPID anahtarları bulunamadı. Lütfen veritabanı ayarlarını kontrol edin.";
        } else {
            try {
                // Hedef Kitleye Göre Abonelikleri Çek
                $sql = "SELECT ps.* FROM push_subscriptions ps JOIN users u ON ps.user_id = u.id";
                
                if ($target === 'customer') {
                    $sql .= " WHERE u.role = 'customer'";
                } elseif ($target === 'provider') {
                    $sql .= " WHERE u.role = 'provider'";
                }
                // 'all' seçeneği için WHERE koşulu eklenmez, herkesi çeker.

                $stmt = $pdo->query($sql);
                $subscriptions = $stmt->fetchAll();

                if (empty($subscriptions)) {
                    $errorMsg = "Seçilen kriterlere uygun bildirim abonesi bulunamadı.";
                } elseif (!class_exists('Minishlink\WebPush\WebPush')) {
                    $errorMsg = "WebPush kütüphanesi eksik veya hatalı. Lütfen 'Bildirim Ayarları' sayfasını kontrol edin.";
                } else {
                    // WebPush Başlat
                    $auth = [
                        'VAPID' => [
                            'subject' => $settings['vapid_subject'] ?? 'mailto:admin@iyiteklif.com',
                            'publicKey' => $settings['vapid_public_key'],
                            'privateKey' => $settings['vapid_private_key'],
                        ],
                    ];

                    $webPush = new Minishlink\WebPush\WebPush($auth);
                    
                    // Payload Hazırla
                    $payload = json_encode([
                        'title' => $title,
                        'body' => $body,
                        'url' => $url
                    ]);

                    $processedUsers = []; // Mükerrer kaydı önlemek için

                    // Kuyruğa Ekle
                    foreach ($subscriptions as $sub) {
                        $subscription = Minishlink\WebPush\Subscription::create([
                            'endpoint' => $sub['endpoint'],
                            'publicKey' => $sub['public_key'],
                            'authToken' => $sub['auth_token'],
                        ]);
                        $webPush->queueNotification($subscription, $payload);

                        // Veritabanına Kaydet (Her kullanıcı için bir kez)
                        if (!in_array($sub['user_id'], $processedUsers)) {
                            $stmtLog = $pdo->prepare("INSERT INTO notifications (user_id, title, message, url) VALUES (?, ?, ?, ?)");
                            $stmtLog->execute([$sub['user_id'], $title, $body, $url]);
                            $processedUsers[] = $sub['user_id'];
                        }
                    }

                    // Gönderimi Başlat
                    $results = $webPush->flush();
                    
                    $successCount = 0;
                    $failCount = 0;

                    foreach ($results as $report) {
                        if ($report->isSuccess()) {
                            $successCount++;
                        } else {
                            $failCount++;
                            // Süresi dolmuş abonelikleri temizle
                            if ($report->isSubscriptionExpired()) {
                                $delStmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?");
                                $delStmt->execute([$report->getEndpoint()]);
                            }
                        }
                    }

                    $successMsg = "İşlem Tamamlandı. Başarılı: <strong>$successCount</strong>, Başarısız: <strong>$failCount</strong>";
                }

            } catch (Exception $e) {
                $errorMsg = "Bildirim gönderilirken hata oluştu: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <h2 class="text-2xl font-bold text-slate-800">Push Bildirim Gönder</h2>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 space-y-6">
        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">Hedef Kitle</label>
            <select name="target" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                <option value="all">Tüm Kullanıcılar</option>
                <option value="customer">Sadece Müşteriler</option>
                <option value="provider">Sadece Hizmet Verenler</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">Bildirim Başlığı</label>
            <input type="text" name="title" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Örn: Kampanya Başladı!">
        </div>
        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">Mesaj İçeriği</label>
            <textarea name="body" rows="3" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Bildirim metnini buraya yazın..."></textarea>
        </div>
        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">Yönlendirilecek URL (Opsiyonel)</label>
            <input type="text" name="url" value="/" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="/kampanyalar">
            <p class="text-xs text-slate-500 mt-1">Kullanıcı bildirime tıkladığında gideceği adres.</p>
        </div>

        <div class="pt-4 border-t border-slate-100 flex justify-end">
            <button type="submit" class="px-8 py-3 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all flex items-center gap-2">
                <span class="material-symbols-outlined">send</span> Gönder
            </button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>