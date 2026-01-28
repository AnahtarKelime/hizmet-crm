<?php
require_once '../config/db.php';
require_once 'includes/header.php';

$successMsg = '';
$errorMsg = '';

// Ayarları Kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Text Ayarları
        $settingsToUpdate = [
            'site_title' => $_POST['site_title'],
            'site_description' => $_POST['site_description'],
            'site_keywords' => $_POST['site_keywords'],
            'contact_email' => $_POST['contact_email'],
            'contact_phone' => $_POST['contact_phone'],
            'contact_address' => $_POST['contact_address']
        ];

        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        foreach ($settingsToUpdate as $key => $value) {
            $stmt->execute([$key, $value]);
        }

        // Dosya Yükleme Fonksiyonu
        function uploadSettingFile($fileInputName, $settingKey, $pdo) {
            if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
                // Boyut Kontrolü (10MB)
                if ($_FILES[$fileInputName]['size'] > 10485760) {
                    return;
                }
                $uploadDir = '../uploads/settings/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $fileTmpPath = $_FILES[$fileInputName]['tmp_name'];
                $fileName = $_FILES[$fileInputName]['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'ico', 'svg'];

                if (in_array($fileExtension, $allowedExtensions)) {
                    $newFileName = $settingKey . '_' . uniqid() . '.' . $fileExtension;
                    if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
                        $filePath = 'uploads/settings/' . $newFileName;
                        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                        $stmt->execute([$settingKey, $filePath]);
                    }
                }
            }
        }

        // Dosyaları Yükle
        uploadSettingFile('site_logo', 'site_logo', $pdo);
        uploadSettingFile('site_favicon', 'site_favicon', $pdo);
        uploadSettingFile('pwa_icon', 'pwa_icon', $pdo);

        $pdo->commit();
        $successMsg = "Ayarlar başarıyla güncellendi.";
        
        // Cache temizle (Eğer varsa)
        if (class_exists('CacheSystem')) {
            global $cache;
            if(isset($cache)) $cache->delete('site_settings');
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Hata: " . $e->getMessage();
    }
}

// Mevcut Ayarları Çek
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Genel Ayarlar</h2>
            <p class="text-slate-500 text-sm">Site kimliği, iletişim bilgileri ve görseller.</p>
        </div>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        
        <!-- Site Kimliği -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Site Kimliği</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="col-span-2">
                    <label class="block text-sm font-bold text-slate-700 mb-2">Site Başlığı</label>
                    <input type="text" name="site_title" value="<?= htmlspecialchars($settings['site_title'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-bold text-slate-700 mb-2">Site Açıklaması (Description)</label>
                    <textarea name="site_description" rows="2" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-bold text-slate-700 mb-2">Anahtar Kelimeler (Keywords)</label>
                    <input type="text" name="site_keywords" value="<?= htmlspecialchars($settings['site_keywords'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <!-- Görseller -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Görseller & İkonlar</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Site Logosu</label>
                    <?php if (!empty($settings['site_logo'])): ?>
                        <img src="../<?= htmlspecialchars($settings['site_logo']) ?>" class="h-12 w-auto object-contain mb-2 border border-slate-200 rounded p-1">
                    <?php endif; ?>
                    <input type="file" name="site_logo" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Favicon</label>
                    <?php if (!empty($settings['site_favicon'])): ?>
                        <img src="../<?= htmlspecialchars($settings['site_favicon']) ?>" class="h-8 w-8 object-contain mb-2 border border-slate-200 rounded p-1">
                    <?php endif; ?>
                    <input type="file" name="site_favicon" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">PWA Uygulama İkonu</label>
                    <?php if (!empty($settings['pwa_icon'])): ?>
                        <img src="../<?= htmlspecialchars($settings['pwa_icon']) ?>" class="h-16 w-16 object-contain mb-2 border border-slate-200 rounded p-1">
                    <?php endif; ?>
                    <input type="file" name="pwa_icon" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="text-xs text-slate-500 mt-1">Mobil uygulama görünümü için. Önerilen: 512x512px PNG.</p>
                </div>
            </div>
        </div>

        <!-- İletişim Bilgileri -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">İletişim Bilgileri</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">E-posta Adresi</label>
                    <input type="email" name="contact_email" value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Telefon Numarası</label>
                    <input type="text" name="contact_phone" value="<?= htmlspecialchars($settings['contact_phone'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-bold text-slate-700 mb-2">Adres</label>
                    <textarea name="contact_address" rows="2" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"><?= htmlspecialchars($settings['contact_address'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-xl transition-all shadow-lg shadow-indigo-200">
                Ayarları Kaydet
            </button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>