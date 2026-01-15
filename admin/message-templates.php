<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Şablon Ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_template'])) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);

    if (!empty($title) && !empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO admin_message_templates (title, message) VALUES (?, ?)");
        $stmt->execute([$title, $message]);
        header("Location: message-templates.php?msg=added");
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
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Sol Kolon: Şablon Ekleme Formu -->
    <div class="space-y-6">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Yeni Şablon Ekle</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="add_template" value="1">
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Şablon Başlığı</label>
                    <input type="text" name="title" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Örn: Merhaba">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Mesaj İçeriği</label>
                    <textarea name="message" rows="6" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Şablon içeriğini buraya yazın..."></textarea>
                </div>

                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-lg transition-colors text-sm">
                    Şablonu Kaydet
                </button>
            </form>
        </div>
    </div>

    <!-- Sağ Kolon: Şablon Listesi -->
    <div class="lg:col-span-2">
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
                                <a href="message-templates.php?delete=<?= $tpl['id'] ?>" onclick="return confirm('Silmek istediğinize emin misiniz?')" class="text-red-400 hover:text-red-600 p-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <span class="material-symbols-outlined text-sm">delete</span>
                                </a>
                            </div>
                            <p class="text-sm text-slate-600 whitespace-pre-wrap bg-slate-50 p-3 rounded border border-slate-100"><?= htmlspecialchars($tpl['message']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>