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
                // Eski kayıtları sil (Duplicate key veya güncelleme sorunlarını önlemek için)
                $delStmt = $pdo->prepare("DELETE FROM settings WHERE setting_key = ?");
                $delStmt->execute([$key]);
                
                // Yeni değeri ekle
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
        }

        // Logo Yükleme İşlemi
        if (isset($_FILES['site_logo_file']) && $_FILES['site_logo_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileTmpPath = $_FILES['site_logo_file']['tmp_name'];
            $fileName = $_FILES['site_logo_file']['name'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));

            $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'svg', 'webp');
            if (in_array($fileExtension, $allowedfileExtensions)) {
                $newFileName = 'logo_' . uniqid() . '.' . $fileExtension;
                $dest_path = $uploadDir . $newFileName;

                if(move_uploaded_file($fileTmpPath, $dest_path)) {
                    $logoPath = 'uploads/' . $newFileName;
                    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('site_logo', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$logoPath, $logoPath]);
                }
            }
        }

        $pdo->commit();
        $successMsg = "Ayarlar başarıyla güncellendi.";
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
            <h2 class="text-2xl font-bold text-slate-800">Genel Site Ayarları</h2>
            <p class="text-slate-500 text-sm">Site başlığı, iletişim bilgileri ve temel yapılandırmaları yönetin.</p>
        </div>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 space-y-8">
        
        <!-- Temel Bilgiler -->
        <div>
            <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Temel Bilgiler</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Anasayfa Başlığı (Meta Title)</label>
                    <input type="text" name="settings[homepage_title]" value="<?= htmlspecialchars($settings['homepage_title'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Örn: İyiteklif - Hizmet Bulmanın Kolay Yolu">
                    <p class="text-xs text-slate-500 mt-1">Boş bırakılırsa "Site Başlığı | Site Açıklaması" formatı kullanılır.</p>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Site Açıklaması (Meta Description)</label>
                    <input type="text" name="settings[site_description]" value="<?= htmlspecialchars($settings['site_description'] ?? '') ?>" maxlength="90" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Site Logosu</label>
                    <div class="flex items-center gap-4">
                        <?php if (!empty($settings['site_logo'])): ?>
                            <div class="p-2 border border-slate-200 rounded-lg bg-slate-50">
                                <img src="../<?= htmlspecialchars($settings['site_logo']) ?>" alt="Site Logo" class="h-12 object-contain">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="site_logo_file" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                    <p class="text-xs text-slate-500 mt-1">PNG, JPG, SVG veya WEBP. Maksimum 2MB.</p>
                </div>
            </div>
        </div>

        <!-- İletişim Bilgileri -->
        <div>
            <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">İletişim Bilgileri</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">İletişim E-posta</label>
                    <input type="email" name="settings[contact_email]" value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">İletişim Telefon</label>
                    <input type="text" name="settings[contact_phone]" value="<?= htmlspecialchars($settings['contact_phone'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <div class="pt-6 border-t border-slate-100 flex justify-end">
            <button type="submit" class="px-8 py-3 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">Ayarları Kaydet</button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>