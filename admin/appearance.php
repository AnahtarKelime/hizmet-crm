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
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
        }

        $pdo->commit();
        $successMsg = "Görünüm ayarları başarıyla güncellendi.";
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
            <h2 class="text-2xl font-bold text-slate-800">Görünüm & CSS</h2>
            <p class="text-slate-500 text-sm">Sitenin renklerini ve stilini özelleştirin.</p>
        </div>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 space-y-8">
        
        <!-- Renk Ayarları -->
        <div>
            <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Renk Teması</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Ana Renk (Primary)</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="settings[theme_color_primary]" value="<?= htmlspecialchars($settings['theme_color_primary'] ?? '#1a2a6c') ?>" class="h-10 w-20 rounded border border-slate-300 cursor-pointer">
                        <input type="text" value="<?= htmlspecialchars($settings['theme_color_primary'] ?? '#1a2a6c') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 uppercase" onchange="this.previousElementSibling.value = this.value">
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Butonlar, başlıklar ve ana vurgular için kullanılır.</p>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Vurgu Rengi (Accent)</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="settings[theme_color_accent]" value="<?= htmlspecialchars($settings['theme_color_accent'] ?? '#fbbd23') ?>" class="h-10 w-20 rounded border border-slate-300 cursor-pointer">
                        <input type="text" value="<?= htmlspecialchars($settings['theme_color_accent'] ?? '#fbbd23') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 uppercase" onchange="this.previousElementSibling.value = this.value">
                    </div>
                    <p class="text-xs text-slate-500 mt-1">İkincil butonlar, rozetler ve detaylar için kullanılır.</p>
                </div>
            </div>
        </div>

        <!-- Özel CSS -->
        <div>
            <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Özel CSS</h3>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">CSS Kodları</label>
                <textarea name="settings[custom_css]" rows="12" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm bg-slate-50" placeholder="/* Buraya özel CSS kodlarınızı yazabilirsiniz */"><?= htmlspecialchars($settings['custom_css'] ?? '') ?></textarea>
                <p class="text-xs text-slate-500 mt-1">Bu kodlar sitenin &lt;head&gt; bölümüne eklenecektir.</p>
            </div>
        </div>

        <div class="pt-6 border-t border-slate-100 flex justify-end">
            <button type="submit" class="px-8 py-3 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">Ayarları Kaydet</button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>