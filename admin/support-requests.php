<?php
require_once '../config/db.php';
require_once 'includes/header.php';

$adminId = $_SESSION['user_id'];

// Destek Talebi sohbetlerini çek
$sql = "SELECT 
            o.id as offer_id,
            d.id as demand_id,
            u.id as user_id, u.first_name, u.last_name, u.email, u.avatar_url,
            (SELECT message FROM messages WHERE offer_id = o.id ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM messages WHERE offer_id = o.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
            (SELECT COUNT(*) FROM messages WHERE offer_id = o.id AND is_read = 0 AND sender_id != :admin_id) as unread_count
        FROM offers o
        JOIN demands d ON o.demand_id = d.id
        JOIN users u ON d.user_id = u.id
        WHERE d.title = 'Destek Talebi'
        HAVING last_message IS NOT NULL
        ORDER BY last_message_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['admin_id' => $adminId]);
$chats = $stmt->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Destek Talepleri</h2>
        <p class="text-slate-500 text-sm">Kullanıcılarla olan destek sohbetleri.</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left text-sm text-slate-600">
        <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
            <tr>
                <th class="px-6 py-4">Kullanıcı</th>
                <th class="px-6 py-4">Son Mesaj</th>
                <th class="px-6 py-4">Tarih</th>
                <th class="px-6 py-4 text-right">İşlemler</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($chats)): ?>
                <tr>
                    <td colspan="4" class="px-6 py-8 text-center text-slate-500">Henüz destek talebi sohbeti yok.</td>
                </tr>
            <?php else: ?>
                <?php foreach($chats as $chat): ?>
                <tr class="hover:bg-slate-50 transition-colors <?= $chat['unread_count'] > 0 ? 'bg-blue-50/50' : '' ?>">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($chat['avatar_url'])): ?>
                                <img src="../<?= htmlspecialchars($chat['avatar_url']) ?>" class="w-10 h-10 rounded-full object-cover border border-slate-200">
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-sm border border-slate-200">
                                    <?= mb_substr($chat['first_name'], 0, 1) . mb_substr($chat['last_name'], 0, 1) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="font-bold text-slate-800"><?= htmlspecialchars($chat['first_name'] . ' ' . $chat['last_name']) ?></div>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars($chat['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="truncate max-w-md text-slate-600">
                            <?= htmlspecialchars($chat['last_message']) ?>
                        </div>
                        <?php if ($chat['unread_count'] > 0): ?>
                            <span class="inline-block mt-1 px-2 py-0.5 bg-red-100 text-red-600 rounded text-[10px] font-bold"><?= $chat['unread_count'] ?> Yeni Mesaj</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-500">
                        <?= date('d.m.Y H:i', strtotime($chat['last_message_time'])) ?>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <a href="support-chat.php?offer_id=<?= $chat['offer_id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-medium text-xs bg-indigo-50 px-3 py-1.5 rounded transition-colors inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">chat</span> Sohbeti Aç
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>