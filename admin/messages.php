<?php
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Güvenlik Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Silme İşlemi
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: messages.php?msg=deleted");
    exit;
}

// Okundu İşaretleme
if (isset($_GET['read'])) {
    $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?");
    $stmt->execute([$_GET['read']]);
    header("Location: messages.php");
    exit;
}

// Toplu Silme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && isset($_POST['selected_ids'])) {
    $ids = $_POST['selected_ids'];
    if (is_array($ids) && !empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        header("Location: messages.php?msg=bulk_deleted");
        exit;
    }
}

require_once 'includes/header.php';

// Sayfalama Ayarları
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Toplam Mesaj Sayısı
$totalStmt = $pdo->query("SELECT COUNT(*) FROM contact_messages");
$totalMessages = $totalStmt->fetchColumn();
$totalPages = ceil($totalMessages / $limit);

// Mesajları Çek
$stmt = $pdo->prepare("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$messages = $stmt->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">İletişim Mesajları</h2>
        <p class="text-slate-500 text-sm">İletişim formundan gelen mesajları buradan yönetebilirsiniz.</p>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] == 'bulk_deleted'): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">Seçilen mesajlar başarıyla silindi.</div>
<?php endif; ?>

<form method="POST" id="bulkDeleteForm">
    <input type="hidden" name="bulk_delete" value="1">

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <?php if (!empty($messages)): ?>
    <div class="p-4 border-b border-slate-200 bg-slate-50 flex justify-end">
        <button type="submit" onclick="return confirm('Seçili mesajları silmek istediğinize emin misiniz?')" class="text-red-600 hover:text-red-800 font-bold text-xs bg-red-50 hover:bg-red-100 px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
            <span class="material-symbols-outlined text-lg">delete_sweep</span> Seçilileri Sil
        </button>
    </div>
    <?php endif; ?>

    <table class="w-full text-left text-sm text-slate-600">
        <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 w-10">
                    <input type="checkbox" id="selectAll" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                </th>
                <th class="px-6 py-4">Gönderen</th>
                <th class="px-6 py-4">Konu</th>
                <th class="px-6 py-4">Mesaj</th>
                <th class="px-6 py-4">Tarih</th>
                <th class="px-6 py-4">Durum</th>
                <th class="px-6 py-4 text-right">İşlemler</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($messages)): ?>
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-slate-500">Henüz hiç mesaj yok.</td>
                </tr>
            <?php else: ?>
                <?php foreach($messages as $msg): ?>
                <tr class="hover:bg-slate-50 transition-colors <?= $msg['is_read'] ? '' : 'bg-blue-50/50' ?>">
                    <td class="px-6 py-4">
                        <input type="checkbox" name="selected_ids[]" value="<?= $msg['id'] ?>" class="message-checkbox rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                    </td>
                    <td class="px-6 py-4">
                        <div class="font-bold text-slate-800"><?= htmlspecialchars($msg['name']) ?></div>
                        <div class="text-xs text-slate-500"><?= htmlspecialchars($msg['email']) ?></div>
                    </td>
                    <td class="px-6 py-4 font-medium text-slate-800">
                        <?= htmlspecialchars($msg['subject']) ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="truncate max-w-xs text-slate-600 cursor-help" title="<?= htmlspecialchars($msg['message']) ?>">
                            <?= htmlspecialchars($msg['message']) ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-500">
                        <?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php if ($msg['is_read']): ?>
                            <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs font-bold">Okundu</span>
                        <?php else: ?>
                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold">Yeni</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <?php if (!$msg['is_read']): ?>
                            <a href="messages.php?read=<?= $msg['id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-medium text-xs bg-indigo-50 px-3 py-1.5 rounded transition-colors inline-flex items-center gap-1" title="Okundu Olarak İşaretle">
                                <span class="material-symbols-outlined text-sm">mark_email_read</span>
                            </a>
                        <?php endif; ?>
                        <a href="message-details.php?id=<?= $msg['id'] ?>" class="text-blue-600 hover:text-blue-800 font-medium text-xs bg-blue-50 px-3 py-1.5 rounded transition-colors inline-flex items-center gap-1" title="Oku ve Yanıtla">
                            <span class="material-symbols-outlined text-sm">visibility</span>
                        </a>
                        <a href="messages.php?delete=<?= $msg['id'] ?>" onclick="return confirm('Bu mesajı silmek istediğinize emin misiniz?')" class="text-red-600 hover:text-red-800 font-medium text-xs bg-red-50 px-3 py-1.5 rounded transition-colors inline-flex items-center gap-1" title="Sil">
                            <span class="material-symbols-outlined text-sm">delete</span>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Sayfalama -->
    <?php if ($totalPages > 1): ?>
    <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-center">
        <div class="flex gap-2">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>" class="w-8 h-8 flex items-center justify-center bg-white border border-slate-200 rounded hover:bg-slate-100 text-slate-600 transition-colors">
                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                </a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="w-8 h-8 flex items-center justify-center border rounded font-medium text-sm transition-colors <?= $i === $page ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>" class="w-8 h-8 flex items-center justify-center bg-white border border-slate-200 rounded hover:bg-slate-100 text-slate-600 transition-colors">
                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</form>

<script>
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.message-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
</script>

<?php require_once 'includes/footer.php'; ?>