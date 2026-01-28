<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Yetki Kontrolü
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Ayarları Kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $successMsg = "Google servis ayarları başarıyla güncellendi.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Hata oluştu: " . $e->getMessage();
    }
}

// Mevcut Ayarları Çek
$settings = [];
$stmt = $pdo->query("SELECT * FROM settings WHERE setting_key LIKE 'google_%'");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Google Servisleri</h2>
            <p class="text-slate-500 text-sm">Google hizmetlerine ait entegrasyon kodlarını ve kimliklerini buradan yönetebilirsiniz.</p>
        </div>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        
        <!-- Google Analytics -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-orange-500">analytics</span>
                Google Analytics 4 (GA4)
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Ölçüm Kimliği (Measurement ID)</label>
                    <input type="text" name="settings[google_analytics_id]" value="<?= htmlspecialchars($settings['google_analytics_id'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="G-XXXXXXXXXX">
                    <p class="text-xs text-slate-500 mt-1">Google Analytics panelinden alacağınız 'G-' ile başlayan kimlik.</p>
                </div>
            </div>
        </div>

        <!-- Google Tag Manager -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-500">sell</span>
                Google Tag Manager
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Konteyner Kimliği (Container ID)</label>
                    <input type="text" name="settings[google_tag_manager_id]" value="<?= htmlspecialchars($settings['google_tag_manager_id'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="GTM-XXXXXX">
                    <p class="text-xs text-slate-500 mt-1">GTM panelinden alacağınız 'GTM-' ile başlayan kimlik.</p>
                </div>
            </div>
        </div>

        <!-- Google Ads -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-600">ads_click</span>
                Google Ads
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Dönüşüm Kimliği (Conversion ID)</label>
                    <input type="text" name="settings[google_ads_id]" value="<?= htmlspecialchars($settings['google_ads_id'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="AW-XXXXXXXXX">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Dönüşüm Etiketi (Conversion Label)</label>
                    <input type="text" name="settings[google_ads_label]" value="<?= htmlspecialchars($settings['google_ads_label'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="AbC_Cj0Q...">
                </div>
            </div>
        </div>

        <!-- Google Search Console -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-green-600">search</span>
                Google Search Console
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Doğrulama Meta Etiketi (HTML Tag)</label>
                    <input type="text" name="settings[google_search_console_meta]" value="<?= htmlspecialchars($settings['google_search_console_meta'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder='<meta name="google-site-verification" content="..." />'>
                    <p class="text-xs text-slate-500 mt-1">Sahiplik doğrulaması için verilen HTML meta etiketinin tamamını yapıştırın.</p>
                </div>
            </div>
        </div>

        <!-- Google Maps -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-red-500">map</span>
                Google Maps Platform
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">API Anahtarı (API Key)</label>
                    <input type="text" name="settings[google_maps_api_key]" value="<?= htmlspecialchars($settings['google_maps_api_key'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                    <p class="text-xs text-slate-500 mt-1">Harita gösterimi ve konum servisleri için gereklidir. (Maps JavaScript API, Places API, Geocoding API)</p>
                </div>
            </div>
        </div>

        <!-- Google Firebase -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-yellow-500">local_fire_department</span>
                Google Firebase (Frontend Config)
            </h3>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-blue-700">
                    <strong>Not:</strong> Sunucu tarafı (Backend) Firebase ayarları için <a href="notification-settings.php" class="underline font-bold">Bildirim Ayarları</a> sayfasını kullanın. Burası sadece ön yüz (JavaScript) entegrasyonu içindir.
                </p>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Firebase Config Object (JSON)</label>
                    <textarea name="settings[google_firebase_config]" rows="8" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono text-xs" placeholder='{
  "apiKey": "...",
  "authDomain": "...",
  "projectId": "...",
  "storageBucket": "...",
  "messagingSenderId": "...",
  "appId": "..."
}'><?= htmlspecialchars($settings['google_firebase_config'] ?? '') ?></textarea>
                    <p class="text-xs text-slate-500 mt-1">Firebase konsolundan alacağınız <code>firebaseConfig</code> objesinin içeriğini JSON formatında yapıştırın.</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-8 py-3 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">
                Ayarları Kaydet
            </button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>