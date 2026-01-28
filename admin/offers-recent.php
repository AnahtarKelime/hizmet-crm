<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Sayfalama Ayarları
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Toplam Teklif Sayısı
$totalOffers = 0;
try {
    $totalStmt = $pdo->query("SELECT COUNT(*) FROM offers");
    $totalOffers = $totalStmt->fetchColumn();
} catch (PDOException $e) {
    // Hata durumunda 0 varsay
}
$totalPages = ceil($totalOffers / $limit);

// Tüm Teklifleri Çek (Hizmet Veren, Müşteri ve Talep Bilgileriyle)
$sql = "SELECT 
            o.*, 
            d.title as demand_title,
            c.name as category_name,
            provider.first_name as p_name, provider.last_name as p_surname,
            customer.first_name as c_name, customer.last_name as c_surname
        FROM offers o
        JOIN demands d ON o.demand_id = d.id
        JOIN categories c ON d.category_id = c.id
        JOIN users provider ON o.user_id = provider.id
        JOIN users customer ON d.user_id = customer.id
        ORDER BY o.created_at DESC
        LIMIT :limit OFFSET :offset";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $offers = $stmt->fetchAll();
} catch (PDOException $e) {
    $offers = [];
    $error = "Veri çekilirken hata oluştu: " . $e->getMessage();
}
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Güncel Teklifler</h2>
        <p class="text-slate-500 text-sm">Hizmet verenler tarafından sunulan son teklifler ve detayları.</p>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <span class="block sm:inline"><?= $error ?></span>
    </div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 hidden md:table-cell">ID</th>
                    <th class="px-6 py-4">Hizmet Veren</th>
                    <th class="px-6 py-4 hidden xl:table-cell">Hizmet Alan</th>
                    <th class="px-6 py-4">Kategori / Talep</th>
                    <th class="px-6 py-4">Tutar</th>
                    <th class="px-6 py-4 hidden lg:table-cell">Tarih</th>
                    <th class="px-6 py-4">Durum</th>
                    <th class="px-6 py-4 text-right">İşlemler</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($offers)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-slate-500">Henüz hiç teklif verilmemiş.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach($offers as $offer): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 font-mono text-xs text-slate-400 hidden md:table-cell">#<?= $offer['id'] ?></td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-indigo-700"><?= htmlspecialchars($offer['p_name'] . ' ' . $offer['p_surname']) ?></div>
                            <div class="text-xs text-slate-400">Uzman</div>
                        </td>
                        <td class="px-6 py-4 hidden xl:table-cell">
                            <div class="font-medium text-slate-800"><?= htmlspecialchars($offer['c_name'] . ' ' . $offer['c_surname']) ?></div>
                            <div class="text-xs text-slate-400">Müşteri</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-700"><?= htmlspecialchars($offer['category_name']) ?></div>
                            <div class="text-xs text-slate-500 truncate max-w-[150px]" title="<?= htmlspecialchars($offer['demand_title']) ?>"><?= htmlspecialchars($offer['demand_title']) ?></div>
                        </td>
                        <td class="px-6 py-4 font-black text-slate-800">
                            <?= number_format($offer['price'], 2, ',', '.') ?> ₺
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500 hidden lg:table-cell">
                            <?= date('d.m.Y H:i', strtotime($offer['created_at'])) ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php
                            $statusClasses = [
                                'accepted' => 'bg-green-100 text-green-700',
                                'rejected' => 'bg-red-100 text-red-700',
                                'pending' => 'bg-yellow-100 text-yellow-700'
                            ];
                            $statusLabels = [
                                'accepted' => 'Kabul Edildi',
                                'rejected' => 'Reddedildi',
                                'pending' => 'Beklemede'
                            ];
                            $statusClass = $statusClasses[$offer['status']] ?? 'bg-yellow-100 text-yellow-700';
                            $statusLabel = $statusLabels[$offer['status']] ?? 'Beklemede';
                            ?>
                            <span class="px-2 py-1 rounded text-xs font-bold <?= $statusClass ?>"><?= $statusLabel ?></span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="../offer-details.php?id=<?= $offer['id'] ?>" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium text-xs bg-indigo-50 px-3 py-1.5 rounded transition-colors">Detay</a>
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