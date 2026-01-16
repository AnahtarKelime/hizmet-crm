<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Ayarları Kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['settings'])) {
            foreach ($_POST['settings'] as $key => $value) {
                $value = trim($value);
                // Varsa güncelle, yoksa ekle
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
        }

        $pdo->commit();
        $successMsg = "Sosyal medya ayarları başarıyla güncellendi.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Hata oluştu: " . $e->getMessage();
    }
}

// Mevcut Ayarları Çek
$settings = [];
$stmt = $pdo->query("SELECT * FROM settings WHERE setting_key LIKE 'social_%'");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Sosyal Medya Hesapları</h2>
            <p class="text-slate-500 text-sm">Sitenizin footer alanında görünecek sosyal medya bağlantılarını yönetin.</p>
        </div>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 space-y-6">
        
        <div class="grid grid-cols-1 gap-6">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-600">facebook</span> Facebook URL
                </label>
                <input type="url" name="settings[social_facebook]" value="<?= htmlspecialchars($settings['social_facebook'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="https://facebook.com/...">
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sky-500">alternate_email</span> Twitter (X) URL
                </label>
                <input type="url" name="settings[social_twitter]" value="<?= htmlspecialchars($settings['social_twitter'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="https://twitter.com/...">
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                    <span class="material-symbols-outlined text-pink-600">photo_camera</span> Instagram URL
                </label>
                <input type="url" name="settings[social_instagram]" value="<?= htmlspecialchars($settings['social_instagram'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="https://instagram.com/...">
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-700">work</span> LinkedIn URL
                </label>
                <input type="url" name="settings[social_linkedin]" value="<?= htmlspecialchars($settings['social_linkedin'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="https://linkedin.com/in/...">
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                    <span class="material-symbols-outlined text-red-600">smart_display</span> YouTube URL
                </label>
                <input type="url" name="settings[social_youtube]" value="<?= htmlspecialchars($settings['social_youtube'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="https://youtube.com/...">
            </div>
        </div>

        <div class="pt-6 border-t border-slate-100 flex justify-end">
            <button type="submit" class="px-8 py-3 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">Kaydet</button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>