<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Ekleme ve Düzenleme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $id = $_POST['template_id'] ?? '';

    if (!empty($title) && !empty($message)) {
        if ($id) {
            // Güncelleme
            $stmt = $pdo->prepare("UPDATE admin_message_templates SET title = ?, message = ? WHERE id = ?");
            $stmt->execute([$title, $message, $id]);
            $msg = 'updated';
        } else {
            // Ekleme
            $stmt = $pdo->prepare("INSERT INTO admin_message_templates (title, message) VALUES (?, ?)");
            $stmt->execute([$title, $message]);
            $msg = 'added';
        }
        header("Location: message-templates.php?msg=" . $msg);
        exit;
    }
}

// Şablon Silme
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM admin_message_templates WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: message-templates.php?msg=deleted");
    exit;
}

// Şablonları Çek
$templates = $pdo->query("SELECT * FROM admin_message_templates ORDER BY title ASC")->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Mesaj Şablonları</h2>
        <p class="text-slate-500 text-sm">Sık kullanılan yanıtlar için hazır şablonlar oluşturun.</p>
    </div>
    <button onclick="openModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors">
        <span class="material-symbols-outlined text-lg">add</span>
        Yeni Şablon
    </button>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">
        <?php
        if ($_GET['msg'] == 'added') echo "Şablon başarıyla eklendi.";
        elseif ($_GET['msg'] == 'updated') echo "Şablon başarıyla güncellendi.";
        elseif ($_GET['msg'] == 'deleted') echo "Şablon başarıyla silindi.";
        ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
        <h3 class="font-bold text-slate-800">Mevcut Şablonlar</h3>
        <span class="text-xs font-medium bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full"><?= count($templates) ?> Şablon</span>
    </div>
    
    <?php if (empty($templates)): ?>
        <div class="p-8 text-center text-slate-500">
            Henüz hiç şablon eklenmemiş.
        </div>
    <?php else: ?>
        <div class="divide-y divide-slate-100">
            <?php foreach ($templates as $tpl): ?>
                <div class="p-4 hover:bg-slate-50 transition-colors group">
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-bold text-slate-800"><?= htmlspecialchars($tpl['title']) ?></h4>
                        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick='editTemplate(<?= json_encode($tpl) ?>)' class="text-indigo-600 hover:text-indigo-800 p-1" title="Düzenle">
                                <span class="material-symbols-outlined text-sm">edit</span>
                            </button>
                            <a href="message-templates.php?delete=<?= $tpl['id'] ?>" onclick="return confirm('Silmek istediğinize emin misiniz?')" class="text-red-400 hover:text-red-600 p-1" title="Sil">
                                <span class="material-symbols-outlined text-sm">delete</span>
                            </a>
                        </div>
                    </div>
                    <p class="text-sm text-slate-600 whitespace-pre-wrap bg-slate-50 p-3 rounded border border-slate-100"><?= htmlspecialchars($tpl['message']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div id="templateModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all scale-100">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-800" id="modalTitle">Yeni Şablon Ekle</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="template_id" id="templateId">
            
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Şablon Başlığı</label>
                <input type="text" name="title" id="inputTitle" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Örn: Merhaba">
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Mesaj İçeriği</label>
                <textarea name="message" id="inputMessage" rows="6" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Şablon içeriğini buraya yazın..."></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closeModal()" class="px-4 py-2 text-slate-600 font-bold hover:bg-slate-50 rounded-lg transition-colors text-sm">İptal</button>
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition-colors text-sm">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('templateModal').classList.remove('hidden');
        document.getElementById('modalTitle').textContent = 'Yeni Şablon Ekle';
        document.getElementById('templateId').value = '';
        document.getElementById('inputTitle').value = '';
        document.getElementById('inputMessage').value = '';
    }

    function closeModal() {
        document.getElementById('templateModal').classList.add('hidden');
    }

    function editTemplate(tpl) {
        openModal();
        document.getElementById('modalTitle').textContent = 'Şablonu Düzenle';
        document.getElementById('templateId').value = tpl.id;
        document.getElementById('inputTitle').value = tpl.title;
        document.getElementById('inputMessage').value = tpl.message;
    }

    // Modal dışına tıklayınca kapatma
    document.getElementById('templateModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>