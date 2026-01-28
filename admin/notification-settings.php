<?php
require_once '../config/db.php';

// Autoloader ve Kütüphane Kontrolü
$webPushInstalled = false;
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php'
];

foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        @include_once $path;
        if (class_exists('Minishlink\WebPush\WebPush')) {
            $webPushInstalled = true;
            break;
        }
    }
}

require_once 'includes/header.php';

// Test Bildirimi Gönder
if (isset($_POST['send_test'])) {
    require_once '../includes/push-helper.php';
    
    // Oturumdaki kullanıcıya gönder
    $testResult = sendPushNotification($_SESSION['user_id'], 'Test Bildirimi 🔔', 'Firebase/VAPID yapılandırmanız başarıyla çalışıyor!');
    
    if ($testResult) {
        $testSuccessMsg = "Test bildirimi başarıyla gönderildi. (Tarayıcı bildirimlerini kontrol edin)";
    } else {
        $testErrorMsg = "Bildirim gönderilemedi. Lütfen VAPID anahtarlarını kontrol edin ve bu tarayıcıdan bildirim izni verdiğinizden emin olun.";
    }
}

// Ayarları Kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings'])) {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['settings'])) {
            foreach ($_POST['settings'] as $key => $value) {
                $value = trim($value);
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
        }

        $pdo->commit();
        $successMsg = "Bildirim ayarları başarıyla güncellendi.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Hata oluştu: " . $e->getMessage();
    }
}

// Mevcut Ayarları Çek
$settings = [];
$stmt = $pdo->query("SELECT * FROM settings WHERE setting_key LIKE 'vapid_%' OR setting_key LIKE 'firebase_%'");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Bildirim Ayarları (Firebase FCM)</h2>
            <p class="text-slate-500 text-sm">Google Firebase Cloud Messaging ve Web Push yapılandırması.</p>
        </div>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>

    <!-- Test Paneli -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-bold text-slate-800">Sistemi Test Et</h3>
                <p class="text-sm text-slate-500">Ayarların doğruluğunu kontrol etmek için kendinize bir bildirim gönderin.</p>
            </div>
            <form method="POST">
                <input type="hidden" name="send_test" value="1">
                <button type="submit" class="px-6 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-bold rounded-lg transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">notifications_active</span> Test Gönder
                </button>
            </form>
        </div>
        <?php if (isset($testSuccessMsg)): ?>
            <div class="mt-4 p-3 bg-green-50 text-green-700 text-sm font-bold rounded-lg flex items-center gap-2 border border-green-100"><span class="material-symbols-outlined">check_circle</span> <?= $testSuccessMsg ?></div>
        <?php endif; ?>
        <?php if (isset($testErrorMsg)): ?>
            <div class="mt-4 p-3 bg-red-50 text-red-700 text-sm font-bold rounded-lg flex items-center gap-2 border border-red-100"><span class="material-symbols-outlined">error</span> <?= $testErrorMsg ?></div>
        <?php endif; ?>
    </div>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 space-y-6">
        
        <!-- Kütüphane Durumu -->
        <div class="mb-6 p-4 <?= $webPushInstalled ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' ?> border rounded-xl flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined <?= $webPushInstalled ? 'text-green-600' : 'text-red-600' ?>">extension</span>
                <span class="text-sm <?= $webPushInstalled ? 'text-green-800' : 'text-red-800' ?>">
                    <strong>WebPush Kütüphanesi:</strong> 
                    <?= $webPushInstalled ? 'Yüklü (Kullanıma Hazır)' : 'Yüklü Değil - Lütfen terminalden <code>composer require minishlink/web-push</code> komutunu çalıştırın.' ?>
                </span>
            </div>
        </div>
        
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h4 class="font-bold text-blue-800 mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined">info</span> Firebase Kurulumu
            </h4>
            <ol class="list-decimal list-inside text-sm text-blue-700 space-y-1">
                <li><a href="https://console.firebase.google.com/" target="_blank" class="underline font-bold hover:text-blue-900">Firebase Console</a>'a gidin ve projenizi seçin.</li>
                <li><strong>Proje Ayarları > Cloud Messaging</strong> sekmesine gidin.</li>
                <li><strong>Cloud Messaging API (Legacy)</strong> etkin değilse sağdaki menüden etkinleştirin (Sunucu Anahtarı için).</li>
                <li><strong>Web Push certificates</strong> bölümünden bir anahtar çifti oluşturun (VAPID Anahtarları).</li>
                <li>Aşağıdaki alanları Firebase'den aldığınız bilgilerle doldurun.</li>
            </ol>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Firebase Server Key (Sunucu Anahtarı)</label>
                <input type="text" name="settings[firebase_server_key]" value="<?= htmlspecialchars($settings['firebase_server_key'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-xs font-mono">
                <p class="text-xs text-slate-500 mt-1">Cloud Messaging sekmesindeki "Server key".</p>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Firebase Sender ID (Gönderen Kimliği)</label>
                <input type="text" name="settings[firebase_sender_id]" value="<?= htmlspecialchars($settings['firebase_sender_id'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-xs font-mono">
                <p class="text-xs text-slate-500 mt-1">Cloud Messaging sekmesindeki "Sender ID".</p>
            </div>
        </div>

        <hr class="border-slate-100">

        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">VAPID Subject (İletişim E-postası)</label>
            <input type="text" name="settings[vapid_subject]" value="<?= htmlspecialchars($settings['vapid_subject'] ?? 'mailto:admin@iyiteklif.com') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="mailto:admin@siteniz.com">
            <p class="text-xs text-slate-500 mt-1">Bildirim servisi sağlayıcılarının size ulaşabilmesi için bir e-posta adresi (mailto: formatında).</p>
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">VAPID Public Key (Web Push Certificate)</label>
            <input type="text" name="settings[vapid_public_key]" value="<?= htmlspecialchars($settings['vapid_public_key'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono text-xs">
            <p class="text-xs text-slate-500 mt-1">Firebase Console > Web Push certificates > Key pair (Public)</p>
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">VAPID Private Key</label>
            <input type="text" name="settings[vapid_private_key]" value="<?= htmlspecialchars($settings['vapid_private_key'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono text-xs">
            <p class="text-xs text-red-500 mt-1">Bu anahtarı kimseyle paylaşmayın. (Firebase'de Private key'i göremezseniz yeni bir çift oluşturun)</p>
        </div>

        <div class="pt-6 border-t border-slate-100 flex justify-end">
            <button type="submit" class="px-8 py-3 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">Ayarları Kaydet</button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>