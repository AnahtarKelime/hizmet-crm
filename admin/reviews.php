<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Onaylama İşlemi
if (isset($_GET['approve'])) {
    $stmt = $pdo->prepare("UPDATE reviews SET is_approved = 1 WHERE id = ?");
    $stmt->execute([$_GET['approve']]);
    header("Location: reviews.php?msg=approved");
    exit;
}

// Onay Kaldırma İşlemi
if (isset($_GET['unapprove'])) {
    $stmt = $pdo->prepare("UPDATE reviews SET is_approved = 0 WHERE id = ?");
    $stmt->execute([$_GET['unapprove']]);
    header("Location: reviews.php?msg=unapproved");
    exit;
}

// Silme İşlemi
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: reviews.php?msg=deleted");
    exit;
}

// Sayfalama Ayarları
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Toplam Yorum Sayısı
$totalStmt = $pdo->query("SELECT COUNT(*) FROM reviews");
$totalReviews = $totalStmt->fetchColumn();
$totalPages = ceil($totalReviews / $limit);

// Yorumları Çek
$sql = "SELECT r.*, 
               reviewer.first_name as reviewer_name, reviewer.last_name as reviewer_surname, reviewer.email as reviewer_email,
               reviewed.first_name as reviewed_name, reviewed.last_name as reviewed_surname, reviewed.role as reviewed_role,
               pd.business_name
        FROM reviews r
        JOIN users reviewer ON r.reviewer_id = reviewer.id
        JOIN users reviewed ON r.reviewed_id = reviewed.id
        LEFT JOIN provider_details pd ON reviewed.id = pd.user_id
        ORDER BY r.created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$reviews = $stmt->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Yorum Yönetimi</h2>
        <p class="text-slate-500 text-sm">Kullanıcılar tarafından yapılan değerlendirmeleri yönetin.</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">Yorum Yapan</th>
                    <th class="px-6 py-4">Hizmet Veren / Alan</th>
                    <th class="px-6 py-4">Puan</th>
                    <th class="px-6 py-4 w-1/3">Yorum</th>
                    <th class="px-6 py-4">Tarih</th>
                    <th class="px-6 py-4">Durum</th>
                    <th class="px-6 py-4 text-right">İşlemler</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($reviews)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-slate-500">Henüz hiç yorum yapılmamış.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach($reviews as $review): 
                        $reviewedName = $review['business_name'] ?: $review['reviewed_name'] . ' ' . $review['reviewed_surname'];
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-800"><?= htmlspecialchars($review['reviewer_name'] . ' ' . $review['reviewer_surname']) ?></div>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars($review['reviewer_email']) ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-800"><?= htmlspecialchars($reviewedName) ?></div>
                            <div class="text-xs text-slate-500 capitalize"><?= $review['reviewed_role'] === 'provider' ? 'Hizmet Veren' : 'Müşteri' ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-1 text-yellow-500 font-bold">
                                <span class="material-symbols-outlined text-sm fill-1">star</span>
                                <?= $review['rating'] ?>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-slate-600 text-xs leading-relaxed max-h-20 overflow-y-auto custom-scrollbar">
                                <?= nl2br(htmlspecialchars($review['comment'])) ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500 whitespace-nowrap">
                            <?= date('d.m.Y H:i', strtotime($review['created_at'])) ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($review['is_approved']): ?>
                                <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold inline-flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">check_circle</span> Onaylı</span>
                            <?php else: ?>
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs font-bold inline-flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">hourglass_top</span> Bekliyor</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <?php if (!$review['is_approved']): ?>
                                <a href="reviews.php?approve=<?= $review['id'] ?>" class="text-green-600 hover:text-green-800 font-medium text-xs bg-green-50 px-3 py-1.5 rounded transition-colors inline-flex items-center gap-1 mr-1" title="Onayla">
                                    <span class="material-symbols-outlined text-sm">check</span> Onayla
                                </a>
                            <?php else: ?>
                                <a href="reviews.php?unapprove=<?= $review['id'] ?>" class="text-yellow-600 hover:text-yellow-800 font-medium text-xs bg-yellow-50 px-3 py-1.5 rounded transition-colors inline-flex items-center gap-1 mr-1" title="Onayı Kaldır">
                                    <span class="material-symbols-outlined text-sm">block</span> Gizle
                                </a>
                            <?php endif; ?>
                            <a href="reviews.php?delete=<?= $review['id'] ?>" onclick="return confirm('Bu yorumu silmek istediğinize emin misiniz?')" class="text-red-600 hover:text-red-800 font-medium text-xs bg-red-50 px-3 py-1.5 rounded transition-colors inline-flex items-center gap-1" title="Sil">
                                <span class="material-symbols-outlined text-sm">delete</span>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

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