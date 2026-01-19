<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Ekleme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_announcement'])) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $target_role = $_POST['target_role'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!empty($title) && !empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO announcements (title, message, target_role, is_active) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $message, $target_role, $is_active]);
        $successMsg = "Duyuru başarıyla yayınlandı.";
    }
}

// Silme İşlemi
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: announcements.php?msg=deleted");
    exit;
}

// Durum Değiştirme
if (isset($_GET['toggle']) && isset($_GET['status'])) {
    $id = $_GET['toggle'];
    $newStatus = $_GET['status'] ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE announcements SET is_active = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    header("Location: announcements.php");
    exit;
}

$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Duyurular</h2>
        <p class="text-slate-500 text-sm">Kullanıcılara site içi duyuru ve bilgilendirme yayınlayın.</p>
    </div>
</div>

<?php if (isset($successMsg)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
<?php endif; ?>
<?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">Duyuru silindi.</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Ekleme Formu -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="font-bold text-slate-800 mb-4">Yeni Duyuru Ekle</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="add_announcement" value="1">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Başlık</label>
                    <input type="text" name="title" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Mesaj</label>
                    <textarea name="message" rows="4" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Hedef Kitle</label>
                    <select name="target_role" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="all">Herkes (Tüm Kullanıcılar)</option>
                        <option value="customer">Sadece Müşteriler</option>
                        <option value="provider">Sadece Hizmet Verenler</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded text-indigo-600 focus:ring-indigo-500">
                    <label class="text-sm text-slate-700">Aktif (Hemen Yayınla)</label>
                </div>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-lg transition-colors text-sm">Yayınla</button>
            </form>
        </div>
    </div>

    <!-- Liste -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="divide-y divide-slate-100">
                <?php if (empty($announcements)): ?>
                    <div class="p-8 text-center text-slate-500">Henüz duyuru bulunmuyor.</div>
                <?php else: ?>
                    <?php foreach ($announcements as $ann): ?>
                        <div class="p-6 hover:bg-slate-50 transition-colors group">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-bold text-slate-800"><?= htmlspecialchars($ann['title']) ?></h4>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold px-2 py-1 rounded bg-slate-100 text-slate-600 uppercase"><?= $ann['target_role'] ?></span>
                                    <a href="announcements.php?toggle=<?= $ann['id'] ?>&status=<?= $ann['is_active'] ?>" class="text-xs font-bold px-2 py-1 rounded <?= $ann['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                                        <?= $ann['is_active'] ? 'Yayında' : 'Pasif' ?>
                                    </a>
                                    <a href="announcements.php?delete=<?= $ann['id'] ?>" onclick="return confirm('Silmek istediğinize emin misiniz?')" class="text-red-400 hover:text-red-600 p-1 opacity-0 group-hover:opacity-100 transition-opacity"><span class="material-symbols-outlined text-sm">delete</span></a>
                                </div>
                            </div>
                            <p class="text-sm text-slate-600"><?= nl2br(htmlspecialchars($ann['message'])) ?></p>
                            <div class="mt-2 text-xs text-slate-400"><?= date('d.m.Y H:i', strtotime($ann['created_at'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>