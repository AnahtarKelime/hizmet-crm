<?php
require_once 'config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Sayfaya girildiğinde tüm bildirimleri okundu olarak işaretle
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);

// Bildirimleri çek (Son 50 bildirim)
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

$pageTitle = "Bildirimlerim";
require_once 'includes/header.php';
?>

<main class="max-w-4xl mx-auto px-4 py-12 min-h-[60vh]">
    <div class="flex items-center gap-4 mb-8">
        <h1 class="text-3xl font-black text-slate-800">Bildirimlerim</h1>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="text-center py-12 bg-white rounded-2xl shadow-sm border border-slate-100">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-slate-100 text-slate-400 rounded-full mb-4">
                <span class="material-symbols-outlined text-3xl">notifications_off</span>
            </div>
            <h2 class="text-xl font-bold text-slate-800 mb-2">Henüz bildiriminiz yok.</h2>
            <p class="text-slate-500">Önemli gelişmelerden haberdar olduğunuzda burada listelenecek.</p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="divide-y divide-slate-100">
                <?php foreach ($notifications as $notif): ?>
                    <a href="<?= htmlspecialchars($notif['url'] ?? '#') ?>" class="block p-6 hover:bg-slate-50 transition-colors group">
                        <div class="flex gap-4">
                            <div class="shrink-0 mt-1">
                                <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                                    <span class="material-symbols-outlined">notifications</span>
                                </div>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start mb-1">
                                    <h3 class="font-bold text-slate-800 group-hover:text-indigo-600 transition-colors"><?= htmlspecialchars($notif['title']) ?></h3>
                                    <span class="text-xs text-slate-400 whitespace-nowrap ml-2"><?= date('d.m.Y H:i', strtotime($notif['created_at'])) ?></span>
                                </div>
                                <p class="text-slate-600 text-sm leading-relaxed"><?= htmlspecialchars($notif['message']) ?></p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php require_once 'includes/footer.php'; ?>