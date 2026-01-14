<?php
require_once '../config/db.php';
session_start();

// Yetki Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'provider') {
    header("Location: ../login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Şablon Ekleme / Düzenleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $message = $_POST['message'] ?? '';
    $templateId = $_POST['template_id'] ?? null;

    if ($title && $message) {
        if ($templateId) {
            // Güncelle
            $stmt = $pdo->prepare("UPDATE provider_message_templates SET title = ?, message = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$title, $message, $templateId, $userId]);
        } else {
            // Ekle
            $stmt = $pdo->prepare("INSERT INTO provider_message_templates (user_id, title, message) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $title, $message]);
        }
        header("Location: templates.php?status=success");
        exit;
    }
}

// Şablon Silme
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM provider_message_templates WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['delete'], $userId]);
    header("Location: templates.php?status=deleted");
    exit;
}

// Şablonları Çek
$stmt = $pdo->prepare("SELECT * FROM provider_message_templates WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$templates = $stmt->fetchAll();

$pageTitle = "Mesaj Şablonları";
$pathPrefix = '../';
require_once '../includes/header.php';
?>

<main class="max-w-4xl mx-auto px-4 py-12 min-h-[60vh]">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800">Mesaj Şablonları</h1>
            <p class="text-slate-500">Teklif verirken kullanabileceğiniz hazır mesajlar oluşturun.</p>
        </div>
        <button onclick="openModal()" class="px-6 py-3 bg-primary text-white rounded-xl font-bold hover:bg-primary/90 transition-all shadow-lg flex items-center gap-2">
            <span class="material-symbols-outlined">add</span> Yeni Şablon
        </button>
    </div>

    <?php if (empty($templates)): ?>
        <div class="text-center py-12 bg-white rounded-2xl shadow-sm border border-slate-100">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-slate-100 text-slate-400 rounded-full mb-4">
                <span class="material-symbols-outlined text-3xl">library_books</span>
            </div>
            <h2 class="text-xl font-bold text-slate-800 mb-2">Henüz şablon oluşturmadınız.</h2>
            <p class="text-slate-500 mb-6">Sık kullandığınız teklif mesajlarını kaydederek zaman kazanın.</p>
        </div>
    <?php else: ?>
        <div class="grid gap-4">
            <?php foreach ($templates as $tpl): ?>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 hover:shadow-md transition-all group">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="font-bold text-slate-800 text-lg"><?= htmlspecialchars($tpl['title']) ?></h3>
                        <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick='editTemplate(<?= json_encode($tpl) ?>)' class="text-indigo-600 hover:bg-indigo-50 p-2 rounded-lg transition-colors"><span class="material-symbols-outlined text-sm">edit</span></button>
                            <a href="templates.php?delete=<?= $tpl['id'] ?>" onclick="return confirm('Silmek istediğinize emin misiniz?')" class="text-red-600 hover:bg-red-50 p-2 rounded-lg transition-colors"><span class="material-symbols-outlined text-sm">delete</span></a>
                        </div>
                    </div>
                    <p class="text-slate-600 text-sm line-clamp-2"><?= htmlspecialchars($tpl['message']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Modal -->
<div id="templateModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-800" id="modalTitle">Yeni Şablon Ekle</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="template_id" id="templateId">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Şablon Başlığı</label>
                <input type="text" name="title" id="inputTitle" required class="w-full rounded-lg border-slate-300 focus:border-primary focus:ring-primary" placeholder="Örn: Standart Teklif">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Mesaj İçeriği</label>
                <textarea name="message" id="inputMessage" rows="6" required class="w-full rounded-lg border-slate-300 focus:border-primary focus:ring-primary" placeholder="Merhaba, talebinizi inceledim..."></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closeModal()" class="px-4 py-2 text-slate-600 font-bold hover:bg-slate-50 rounded-lg transition-colors">İptal</button>
                <button type="submit" class="px-6 py-2 bg-primary text-white font-bold rounded-lg hover:bg-primary/90 transition-colors">Kaydet</button>
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
</script>

<?php require_once '../includes/footer.php'; ?>