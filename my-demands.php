<?php
require_once 'config/db.php';
$pageTitle = "Taleplerim";
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Kullanıcının taleplerini çek
$stmt = $pdo->prepare("
    SELECT 
        d.*, 
        c.name as category_name, 
        l.city, l.district 
    FROM demands d
    LEFT JOIN categories c ON d.category_id = c.id
    LEFT JOIN locations l ON d.location_id = l.id
    WHERE d.user_id = ?
    ORDER BY d.created_at DESC
");
$stmt->execute([$userId]);
$demands = $stmt->fetchAll();
?>

<main class="max-w-7xl mx-auto px-4 py-12 min-h-[60vh]">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-black text-slate-800">Taleplerim</h1>
        <a href="index.php" class="px-6 py-3 bg-primary text-white rounded-xl font-bold hover:bg-primary/90 transition-all shadow-lg">
            Yeni Talep Oluştur
        </a>
    </div>

    <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">
            <?= htmlspecialchars($_GET['msg'] ?? 'İşlem başarılı.') ?>
        </div>
    <?php endif; ?>

    <?php if (empty($demands)): ?>
        <div class="text-center py-12 bg-white rounded-2xl shadow-sm border border-slate-100">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-slate-100 text-slate-400 rounded-full mb-4">
                <span class="material-symbols-outlined text-3xl">inbox</span>
            </div>
            <h2 class="text-xl font-bold text-slate-800 mb-2">Henüz talep oluşturmadınız.</h2>
            <p class="text-slate-500 mb-6">İhtiyacınız olan hizmeti bulmak için hemen bir talep oluşturun.</p>
        </div>
    <?php else: ?>
        <div class="grid gap-6">
            <?php foreach ($demands as $demand): ?>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow">
                    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                        <div>
                            <div class="flex items-center gap-3 mb-2">
                                <span class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-bold uppercase tracking-wider">
                                    <?= htmlspecialchars($demand['category_name']) ?>
                                </span>
                                <span class="text-slate-400 text-sm flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">calendar_today</span>
                                    <?= date('d.m.Y', strtotime($demand['created_at'])) ?>
                                </span>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800 mb-1"><?= htmlspecialchars($demand['title']) ?></h3>
                            <p class="text-slate-500 text-sm flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">location_on</span>
                                <?= htmlspecialchars($demand['city'] . ' / ' . $demand['district']) ?>
                            </p>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <div class="text-sm font-bold text-slate-700">Durum</div>
                                <div class="text-sm text-slate-500 capitalize"><?= htmlspecialchars($demand['status']) ?></div>
                            </div>
                            <a href="demand-details.php?id=<?= $demand['id'] ?>" class="px-4 py-2 border border-slate-200 rounded-lg text-slate-600 font-bold hover:bg-slate-50 transition-colors">Detaylar</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require_once 'includes/footer.php'; ?>