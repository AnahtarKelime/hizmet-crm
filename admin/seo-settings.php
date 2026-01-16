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

        // Favicon Yükleme
        if (isset($_FILES['site_favicon_file']) && $_FILES['site_favicon_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileTmpPath = $_FILES['site_favicon_file']['tmp_name'];
            $fileName = $_FILES['site_favicon_file']['name'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));

            $allowedfileExtensions = array('ico', 'png', 'jpg', 'jpeg', 'svg');
            if (in_array($fileExtension, $allowedfileExtensions)) {
                $newFileName = 'favicon_' . uniqid() . '.' . $fileExtension;
                $dest_path = $uploadDir . $newFileName;

                if(move_uploaded_file($fileTmpPath, $dest_path)) {
                    $faviconPath = 'uploads/' . $newFileName;
                    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('site_favicon', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$faviconPath, $faviconPath]);
                }
            }
        }

        // Robots.txt Yazma
        if (isset($_POST['robots_txt'])) {
            $robotsContent = $_POST['robots_txt'];
            $robotsPath = '../robots.txt';
            @file_put_contents($robotsPath, $robotsContent);
        }

        $pdo->commit();
        $successMsg = "SEO ayarları başarıyla güncellendi.";
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

// Robots.txt Oku
$robotsContent = '';
if (file_exists('../robots.txt')) {
    $robotsContent = file_get_contents('../robots.txt');
}
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">SEO Ayarları</h2>
            <p class="text-slate-500 text-sm">Arama motoru optimizasyonu, meta etiketler ve robots.txt yönetimi.</p>
        </div>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 space-y-8">
        
        <!-- Meta Etiketler -->
        <div>
            <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Meta Etiketler</h3>
            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Site Başlığı (Title)</label>
                    <input type="text" name="settings[site_title]" value="<?= htmlspecialchars($settings['site_title'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-slate-500 mt-1">Tarayıcı sekmesinde ve arama sonuçlarında görünen başlık.</p>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Site Açıklaması (Description)</label>
                    <textarea name="settings[site_description]" rows="2" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                    <p class="text-xs text-slate-500 mt-1">Arama sonuçlarında başlığın altında görünen kısa açıklama.</p>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Anahtar Kelimeler (Keywords)</label>
                    <input type="text" name="settings[site_keywords]" value="<?= htmlspecialchars($settings['site_keywords'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="hizmet, temizlik, nakliyat, usta...">
                    <p class="text-xs text-slate-500 mt-1">Virgülle ayırarak yazınız.</p>
                </div>
            </div>
        </div>

        <!-- Favicon -->
        <div>
            <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Favicon</h3>
            <div class="flex items-start gap-6">
                <div class="flex-1">
                    <label class="block text-sm font-bold text-slate-700 mb-2">Site İkonu (Favicon)</label>
                    <input type="file" name="site_favicon_file" accept=".ico,.png,.jpg,.svg" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="text-xs text-slate-500 mt-1">Önerilen format: .ico veya .png (32x32px)</p>
                </div>
                <?php if (!empty($settings['site_favicon'])): ?>
                    <div class="p-4 border border-slate-200 rounded-lg bg-slate-50 text-center">
                        <p class="text-xs font-bold text-slate-500 mb-2">Mevcut</p>
                        <img src="../<?= htmlspecialchars($settings['site_favicon']) ?>" alt="Favicon" class="h-8 w-8 object-contain mx-auto">
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Robots.txt -->
        <div>
            <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Robots.txt Dosyası</h3>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Dosya İçeriği</label>
                <textarea name="robots_txt" rows="8" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"><?= htmlspecialchars($robotsContent) ?></textarea>
                <p class="text-xs text-slate-500 mt-1">Arama motoru botlarının sitenizi nasıl tarayacağını belirler.</p>
            </div>
        </div>

        <div class="pt-6 border-t border-slate-100 flex justify-end">
            <button type="submit" class="px-8 py-3 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">Ayarları Kaydet</button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>