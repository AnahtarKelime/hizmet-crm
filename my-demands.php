<?php
require_once 'config/db.php';
$pageTitle = "Taleplerim";
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Sayfalama Ayarları
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 9;
$offset = ($page - 1) * $limit;

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM demands WHERE user_id = ?");
$totalStmt->execute([$userId]);
$totalDemands = $totalStmt->fetchColumn();
$totalPages = ceil($totalDemands / $limit);

$stmt = $pdo->prepare("
    SELECT 
        d.*, 
        c.name as category_name, 
        l.city, l.district,
        (SELECT COUNT(*) FROM offers WHERE demand_id = d.id) as offer_count,
        (SELECT id FROM offers WHERE demand_id = d.id AND status = 'accepted' LIMIT 1) as accepted_offer_id,
        (SELECT COUNT(*) FROM reviews WHERE offer_id = (SELECT id FROM offers WHERE demand_id = d.id AND status = 'accepted' LIMIT 1) AND reviewer_id = :reviewer_id) as has_reviewed
    FROM demands d
    LEFT JOIN categories c ON d.category_id = c.id
    LEFT JOIN locations l ON d.location_id = l.id
    WHERE d.user_id = :user_id
    ORDER BY d.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmt->bindValue(':reviewer_id', $userId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($demands as $demand): ?>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-all group relative overflow-hidden">
                    <div class="absolute top-0 right-0 text-white text-[10px] font-bold px-3 py-1 rounded-bl-xl 
                        <?= $demand['status'] == 'pending' ? 'bg-yellow-500' : 
                           ($demand['status'] == 'approved' ? 'bg-green-500' : 
                           ($demand['status'] == 'completed' ? 'bg-blue-500' : 'bg-red-500')) ?>">
                        <?= $demand['status'] == 'pending' ? 'BEKLİYOR' : 
                           ($demand['status'] == 'approved' ? 'ONAYLANDI' : 
                           ($demand['status'] == 'completed' ? 'TAMAMLANDI' : 'İPTAL')) ?>
                    </div>

                    <?php
                        // Progress Bar Hesaplaması
                        $progressWidth = '0%';
                        $progressColor = 'bg-slate-200';
                        $progressText = '';
                        
                        switch($demand['status']) {
                            case 'pending':
                                $progressWidth = '33%';
                                $progressColor = 'bg-yellow-400';
                                $progressText = 'Onay Bekleniyor';
                                break;
                            case 'approved':
                                $progressWidth = '66%';
                                $progressColor = 'bg-blue-500';
                                $progressText = 'Teklifler Toplanıyor';
                                break;
                            case 'completed':
                                $progressWidth = '100%';
                                $progressColor = 'bg-green-500';
                                $progressText = 'Tamamlandı';
                                break;
                            case 'cancelled':
                                $progressWidth = '100%';
                                $progressColor = 'bg-red-500';
                                $progressText = 'İptal Edildi';
                                break;
                        }
                    ?>

                    <div class="mb-4 mt-2">
                        <div class="flex flex-wrap gap-2 mb-2">
                            <span class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-bold uppercase tracking-wider">
                                <?= htmlspecialchars($demand['category_name']) ?>
                            </span>
                            <?php if ($demand['offer_count'] > 0): ?>
                                <span class="px-2 py-1 bg-orange-100 text-orange-700 rounded-lg text-xs font-bold flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">local_offer</span>
                                    <?= $demand['offer_count'] ?> Teklif
                                </span>
                            <?php endif; ?>
                        </div>
                        <h4 class="font-bold text-slate-800 line-clamp-2 mb-1 h-12"><?= htmlspecialchars($demand['title']) ?></h4>
                        <p class="text-slate-500 text-sm flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">location_on</span>
                            <?= htmlspecialchars($demand['city'] . ' / ' . $demand['district']) ?>
                        </p>
                    </div>

                    <!-- Progress Bar -->
                    <div class="mb-4">
                        <div class="flex justify-between text-xs font-bold text-slate-500 mb-1">
                            <span>Süreç Durumu</span>
                            <span><?= $progressText ?></span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                            <div class="<?= $progressColor ?> h-2.5 rounded-full transition-all duration-500" style="width: <?= $progressWidth ?>"></div>
                        </div>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-4 mb-4 border border-slate-100">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold text-slate-500 uppercase">Oluşturulma</span>
                            <span class="text-xs font-bold text-slate-700"><?= date('d.m.Y', strtotime($demand['created_at'])) ?></span>
                        </div>
                    </div>

                    <?php if ($demand['status'] === 'completed' && $demand['accepted_offer_id']): ?>
                        <?php if (!$demand['has_reviewed']): ?>
                            <a href="rate-provider.php?offer_id=<?= $demand['accepted_offer_id'] ?>" class="block w-full mb-2 py-2.5 bg-yellow-50 text-yellow-700 font-bold rounded-lg hover:bg-yellow-100 transition-colors text-center text-sm flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-sm">star</span> Hizmeti Değerlendir
                            </a>
                        <?php else: ?>
                            <div class="mb-2 py-2 text-center text-xs text-green-600 font-bold bg-green-50 rounded-lg border border-green-100">Değerlendirme Yapıldı</div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <a href="demand-details.php?id=<?= $demand['id'] ?>" class="block w-full py-2.5 text-center bg-slate-100 text-slate-600 font-bold rounded-xl hover:bg-slate-200 transition-all text-sm">
                        Detayları Görüntüle
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-10 gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="w-10 h-10 flex items-center justify-center bg-white border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-600 font-bold transition-colors shadow-sm">
                        &lt;
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>" class="w-10 h-10 flex items-center justify-center border rounded-lg font-bold transition-colors shadow-sm <?= $i === $page ? 'bg-primary text-white border-primary' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="w-10 h-10 flex items-center justify-center bg-white border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-600 font-bold transition-colors shadow-sm">
                        &gt;
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require_once 'includes/footer.php'; ?>