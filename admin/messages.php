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

require_once 'includes/header.php';

// Mesajları Çek
$messages = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">İletişim Mesajları</h2>
        <p class="text-slate-500 text-sm">İletişim formundan gelen mesajları buradan yönetebilirsiniz.</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left text-sm text-slate-600">
        <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
            <tr>
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
                    <td colspan="6" class="px-6 py-8 text-center text-slate-500">Henüz hiç mesaj yok.</td>
                </tr>
            <?php else: ?>
                <?php foreach($messages as $msg): ?>
                <tr class="hover:bg-slate-50 transition-colors <?= $msg['is_read'] ? '' : 'bg-blue-50/50' ?>">
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
</div>

<?php require_once 'includes/footer.php'; ?>