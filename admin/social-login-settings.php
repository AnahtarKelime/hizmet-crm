<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Ayarları Kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['settings'])) {
            // Checkbox için özel kontrol
            $_POST['settings']['google_login_active'] = isset($_POST['settings']['google_login_active']) ? '1' : '0';
            $_POST['settings']['facebook_login_active'] = isset($_POST['settings']['facebook_login_active']) ? '1' : '0';

            foreach ($_POST['settings'] as $key => $value) {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
        }

        $pdo->commit();
        $successMsg = "Sosyal giriş ayarları başarıyla güncellendi.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Hata oluştu: " . $e->getMessage();
    }
}

// Mevcut Ayarları Çek
$settings = [];
$stmt = $pdo->query("SELECT * FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Sosyal Giriş Ayarları</h2>
            <p class="text-slate-500 text-sm">Google ile giriş ayarlarını yapılandırın.</p>
        </div>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 space-y-8">
        
        <div>
            <div class="p-6 border border-slate-200 rounded-xl bg-slate-50">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-bold text-slate-700 flex items-center gap-2">
                        <svg class="w-6 h-6" viewBox="0 0 24 24"><path fill="currentColor" d="M21.35,11.1H12.18V13.83H18.69C18.36,17.64 15.19,19.27 12.19,19.27C8.36,19.27 5,16.25 5,12C5,7.9 8.2,5 12,5C14.6,5 16.1,6.05 17.1,6.95L19.25,4.85C17.1,2.95 14.8,2 12,2C6.48,2 2,6.48 2,12C2,17.52 6.48,22 12,22C17.52,22 21.7,17.52 21.7,12.33C21.7,11.87 21.5,11.35 21.35,11.1Z"></path></svg>
                        Google ile Giriş
                    </h4>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="settings[google_login_active]" value="1" class="sr-only peer" <?= ($settings['google_login_active'] ?? '0') == '1' ? 'checked' : '' ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900">Aktif</span>
                    </label>
                </div>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Google Client ID</label>
                        <input type="text" name="settings[google_client_id]" value="<?= htmlspecialchars($settings['google_client_id'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Google API Console'dan alınan Client ID">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Google Client Secret</label>
                        <input type="text" name="settings[google_client_secret]" value="<?= htmlspecialchars($settings['google_client_secret'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Google API Console'dan alınan Client Secret">
                    </div>
                    <div class="text-xs text-slate-500 bg-indigo-50 p-3 rounded-lg">
                        <strong>Yetkilendirilmiş yönlendirme URI'si:</strong> 
                        <code class="font-mono bg-indigo-100 p-1 rounded"><?= 'http://' . $_SERVER['HTTP_HOST'] . '/google-callback.php' ?></code>
                        <br>Bu adresi Google API Console'daki projenize eklemelisiniz.
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="p-6 border border-slate-200 rounded-xl bg-slate-50">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-bold text-slate-700 flex items-center gap-2">
                        <svg class="w-6 h-6 text-[#1877F2]" viewBox="0 0 24 24"><path fill="currentColor" d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg>
                        Facebook ile Giriş
                    </h4>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="settings[facebook_login_active]" value="1" class="sr-only peer" <?= ($settings['facebook_login_active'] ?? '0') == '1' ? 'checked' : '' ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900">Aktif</span>
                    </label>
                </div>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Facebook App ID</label>
                        <input type="text" name="settings[facebook_app_id]" value="<?= htmlspecialchars($settings['facebook_app_id'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Facebook for Developers'dan alınan App ID">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Facebook App Secret</label>
                        <input type="text" name="settings[facebook_app_secret]" value="<?= htmlspecialchars($settings['facebook_app_secret'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Facebook for Developers'dan alınan App Secret">
                    </div>
                    <div class="text-xs text-slate-500 bg-indigo-50 p-3 rounded-lg">
                        <strong>Geçerli OAuth Yönlendirme URI'si:</strong> 
                        <code class="font-mono bg-indigo-100 p-1 rounded"><?= 'http://' . $_SERVER['HTTP_HOST'] . '/facebook-callback.php' ?></code>
                        <br>Bu adresi Facebook for Developers'daki uygulamanıza eklemelisiniz.
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-6 border-t border-slate-100 flex justify-end">
            <button type="submit" class="px-8 py-3 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">Ayarları Kaydet</button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>