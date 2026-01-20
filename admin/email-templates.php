<?php
// Hata Raporlamayı Aç (Sorunu görmek için geçici olarak ekliyoruz)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/db.php';
require_once 'includes/header.php'; // Header'ı (Session'ı) önce başlat
require_once '../includes/mail-helper.php';

// Güncelleme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_template'])) {
    $id = $_POST['id'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];

    $stmt = $pdo->prepare("UPDATE email_templates SET subject = ?, body = ? WHERE id = ?");
    $stmt->execute([$subject, $body, $id]);
    $successMsg = "Şablon başarıyla güncellendi.";
}

// Test E-postası Gönderme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_email'])) {
    $templateKey = $_POST['template_key'];
    
    // Admin e-postasını bul
    $stmtAdmin = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmtAdmin->execute([$_SESSION['user_id']]);
    $adminEmail = $stmtAdmin->fetchColumn();

    if ($adminEmail) {
        $dummyData = [
            'name' => 'Test Kullanıcı',
            'demand_title' => 'Örnek Hizmet Talebi',
            'link' => 'https://iyiteklif.com.tr/ornek-link',
            'provider_name' => 'Örnek Hizmet Veren',
            'sender_name' => 'Örnek Gönderici',
            'amount' => '1.250,00',
            'package_name' => 'Premium Paket',
            'code' => '123456'
        ];

        $sendError = '';
        if (sendEmail($adminEmail, $templateKey, $dummyData, $sendError)) {
            $successMsg = "Test e-postası gönderildi: " . htmlspecialchars($adminEmail);
        } else {
            $errorMsg = "E-posta gönderilemedi ($templateKey). Hata: " . htmlspecialchars($sendError);
        }
    }
}

$templates = $pdo->query("SELECT * FROM email_templates ORDER BY id ASC")->fetchAll();
?>

<!-- Sistem Durumu -->
<div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl flex items-center justify-between">
    <div class="flex items-center gap-3">
        <span class="material-symbols-outlined text-blue-600">info</span>
        <span class="text-sm text-blue-800">
            <strong>PHPMailer Durumu:</strong> 
            <?= class_exists('PHPMailer\PHPMailer\PHPMailer') ? '<span class="text-green-600 font-bold">Yüklü (Kullanıma Hazır)</span>' : '<span class="text-red-600 font-bold">Yüklü Değil</span> - Lütfen vendor klasörünü ana dizine yükleyin.' ?>
        </span>
    </div>
</div>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">E-posta Şablonları</h2>
        <p class="text-slate-500 text-sm">Sistem tarafından gönderilen otomatik e-postaları düzenleyin.</p>
    </div>
</div>

<?php if (isset($successMsg)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
<?php endif; ?>
<?php if (isset($errorMsg)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 gap-6">
    <?php foreach ($templates as $tpl): ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center cursor-pointer" onclick="document.getElementById('body-<?= $tpl['id'] ?>').classList.toggle('hidden')">
                <div>
                    <h3 class="font-bold text-slate-800"><?= htmlspecialchars($tpl['name']) ?></h3>
                    <span class="text-xs text-slate-500">Key: <?= htmlspecialchars($tpl['template_key']) ?></span>
                </div>
                <span class="material-symbols-outlined text-slate-400">expand_more</span>
            </div>
            
            <div id="body-<?= $tpl['id'] ?>" class="hidden p-6">
                <div class="flex justify-end mb-4">
                    <form method="POST" onsubmit="return confirm('Bu şablonun test e-postası admin hesabınıza gönderilecek. Onaylıyor musunuz?');">
                        <input type="hidden" name="send_test_email" value="1">
                        <input type="hidden" name="template_key" value="<?= htmlspecialchars($tpl['template_key']) ?>">
                        <button type="submit" class="text-xs bg-blue-50 text-blue-600 hover:bg-blue-100 px-3 py-2 rounded-lg font-bold transition-colors flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">send</span> Test E-postası Gönder
                        </button>
                    </form>
                </div>

                <form method="POST">
                    <input type="hidden" name="update_template" value="1">
                    <input type="hidden" name="id" value="<?= $tpl['id'] ?>">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-slate-700 mb-2">E-posta Konusu</label>
                        <input type="text" name="subject" value="<?= htmlspecialchars($tpl['subject']) ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-bold text-slate-700 mb-2">İçerik (HTML)</label>
                        <div class="text-xs text-slate-500 mb-2 bg-yellow-50 p-2 rounded border border-yellow-100 flex flex-wrap gap-2 items-center">
                            <span class="font-bold mr-2">Değişken Ekle:</span>
                            <?php 
                            $vars = explode(',', $tpl['variables']);
                            foreach($vars as $var): 
                                $var = trim($var);
                                if(empty($var)) continue;
                            ?>
                                <button type="button" onclick="insertVariable('textarea-<?= $tpl['id'] ?>', '<?= $var ?>')" class="bg-white border border-yellow-200 text-yellow-700 px-2 py-1 rounded hover:bg-yellow-100 transition-colors font-mono text-xs cursor-pointer" title="Tıklayarak ekle"><?= htmlspecialchars($var) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <textarea id="textarea-<?= $tpl['id'] ?>" name="body" rows="8" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"><?= htmlspecialchars($tpl['body']) ?></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-bold transition-colors">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
function insertVariable(textareaId, text) {
    const textarea = document.getElementById(textareaId);
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const value = textarea.value;

    textarea.value = value.substring(0, start) + text + value.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + text.length;
    textarea.focus();
}
</script>

<?php require_once 'includes/footer.php'; ?>