<?php
require_once '../config/db.php';
require_once 'includes/header.php';

$adminId = $_SESSION['user_id'];

// Sayfalama Ayarları
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Toplam Destek Talebi Sayısı
$countSql = "SELECT COUNT(*) FROM offers o JOIN demands d ON o.demand_id = d.id WHERE d.title = 'Destek Talebi' AND (SELECT COUNT(*) FROM messages WHERE offer_id = o.id) > 0";
$totalStmt = $pdo->query($countSql);
$totalChats = $totalStmt->fetchColumn();
$totalPages = ceil($totalChats / $limit);

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
        ORDER BY last_message_time DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
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

<?php require_once 'includes/footer.php'; ?>