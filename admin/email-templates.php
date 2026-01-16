<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Güncelleme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];

    $stmt = $pdo->prepare("UPDATE email_templates SET subject = ?, body = ? WHERE id = ?");
    $stmt->execute([$subject, $body, $id]);
    $successMsg = "Şablon başarıyla güncellendi.";
}

$templates = $pdo->query("SELECT * FROM email_templates ORDER BY id ASC")->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">E-posta Şablonları</h2>
        <p class="text-slate-500 text-sm">Sistem tarafından gönderilen otomatik e-postaları düzenleyin.</p>
    </div>
</div>

<?php if (isset($successMsg)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
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
                <form method="POST">
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