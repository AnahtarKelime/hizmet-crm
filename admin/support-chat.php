<?php
require_once '../config/db.php';
require_once 'includes/header.php';

$offerId = $_GET['offer_id'] ?? null;
$adminId = $_SESSION['user_id'];

if (!$offerId) {
    header("Location: support-requests.php");
    exit;
}

// Sohbet Bilgilerini Çek
$stmt = $pdo->prepare("
    SELECT 
        o.id as offer_id,
        u.id as user_id, u.first_name, u.last_name, u.email, u.avatar_url
    FROM offers o
    JOIN demands d ON o.demand_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE o.id = ? AND d.title = 'Destek Talebi'
");
$stmt->execute([$offerId]);
$chat = $stmt->fetch();

if (!$chat) {
    echo "<div class='p-8 text-center text-red-500'>Sohbet bulunamadı.</div>";
    require_once 'includes/footer.php';
    exit;
}

// Mesaj Gönderme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO messages (offer_id, sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$offerId, $adminId, $chat['user_id'], $message]);
        header("Location: support-chat.php?offer_id=" . $offerId);
        exit;
    }
}

// Mesajları Çek
$stmt = $pdo->prepare("SELECT * FROM messages WHERE offer_id = ? ORDER BY created_at ASC");
$stmt->execute([$offerId]);
$messages = $stmt->fetchAll();

// Okundu İşaretle (Admin okuyor)
$pdo->prepare("UPDATE messages SET is_read = 1 WHERE offer_id = ? AND receiver_id = ?")->execute([$offerId, $adminId]);

// Şablonları Çek
$templates = $pdo->query("SELECT * FROM admin_message_templates ORDER BY title ASC")->fetchAll();
?>

<div class="max-w-4xl mx-auto h-[calc(100vh-140px)] flex flex-col">
    <div class="flex items-center gap-4 mb-4 shrink-0">
        <a href="support-requests.php" class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div class="flex items-center gap-3">
            <?php if (!empty($chat['avatar_url'])): ?>
                <img src="../<?= htmlspecialchars($chat['avatar_url']) ?>" class="w-10 h-10 rounded-full object-cover border border-slate-200">
            <?php else: ?>
                <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-sm border border-slate-200">
                    <?= mb_substr($chat['first_name'], 0, 1) . mb_substr($chat['last_name'], 0, 1) ?>
                </div>
            <?php endif; ?>
            <div>
                <h2 class="text-lg font-bold text-slate-800"><?= htmlspecialchars($chat['first_name'] . ' ' . $chat['last_name']) ?></h2>
                <p class="text-xs text-slate-500"><?= htmlspecialchars($chat['email']) ?></p>
            </div>
        </div>
    </div>

    <div class="flex-1 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
        <div class="flex-1 overflow-y-auto p-6 space-y-4 bg-slate-50" id="chatContainer">
            <?php foreach ($messages as $msg): 
                $isMe = ($msg['sender_id'] == $adminId);
            ?>
                <div class="flex <?= $isMe ? 'justify-end' : 'justify-start' ?>">
                    <div class="max-w-[70%] <?= $isMe ? 'bg-indigo-600 text-white rounded-l-xl rounded-tr-xl' : 'bg-white border border-slate-200 text-slate-700 rounded-r-xl rounded-tl-xl' ?> p-4 shadow-sm">
                        <p class="text-sm whitespace-pre-wrap"><?= htmlspecialchars($msg['message']) ?></p>
                        <div class="text-[10px] mt-1 opacity-70 text-right">
                            <?= date('d.m H:i', strtotime($msg['created_at'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="p-4 bg-white border-t border-slate-200">
            <?php if (!empty($templates)): ?>
                <div class="mb-3">
                    <select onchange="if(this.value) document.getElementById('messageInput').value = this.value;" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Hazır Şablon Seç...</option>
                        <?php foreach ($templates as $tpl): ?>
                            <option value="<?= htmlspecialchars($tpl['message']) ?>"><?= htmlspecialchars($tpl['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="flex gap-2">
                <input type="text" name="message" id="messageInput" class="flex-1 rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Mesajınızı yazın..." autocomplete="off" required>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 rounded-lg font-bold transition-colors">
                    <span class="material-symbols-outlined">send</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    const chatContainer = document.getElementById('chatContainer');
    chatContainer.scrollTop = chatContainer.scrollHeight;
</script>

<?php require_once 'includes/footer.php'; ?>