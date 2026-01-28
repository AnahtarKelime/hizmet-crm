<?php
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Header için path ayarı
$pathPrefix = '../';

// Filtreleme Parametreleri
$search = $_GET['search'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$city = $_GET['city'] ?? '';
$district = $_GET['district'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Sorgu Hazırlığı
$where = ["d.status = 'approved' AND d.is_archived = 0"];
$params = [];

if ($search) {
    $where[] = "(d.title LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category_id) {
    $where[] = "d.category_id = ?";
    $params[] = $category_id;
}
if ($city) {
    $where[] = "l.city = ?";
    $params[] = $city;
}
if ($district) {
    $where[] = "l.district = ?";
    $params[] = $district;
}
if ($min_price) {
    $where[] = "d.estimated_cost >= ?";
    $params[] = $min_price;
}
if ($max_price) {
    $where[] = "d.estimated_cost <= ?";
    $params[] = $max_price;
}

// Sıralama
$orderBy = "d.created_at DESC";
if ($sort === 'price_high') {
    $orderBy = "d.estimated_cost DESC";
} elseif ($sort === 'price_low') {
    $orderBy = "d.estimated_cost ASC";
}

// Sayfalama
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Toplam Sayı
$countSql = "SELECT COUNT(*) FROM demands d 
             LEFT JOIN categories c ON d.category_id = c.id 
             LEFT JOIN locations l ON d.location_id = l.id 
             WHERE " . implode(" AND ", $where);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalLeads = $stmt->fetchColumn();
$totalPages = ceil($totalLeads / $limit);

// Verileri Çek
$sql = "SELECT d.*, c.name as category_name, l.city, l.district, u.first_name, u.last_name, u.is_verified,
        (SELECT COUNT(*) FROM offers WHERE demand_id = d.id) as offer_count
        FROM demands d
        LEFT JOIN users u ON d.user_id = u.id
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN locations l ON d.location_id = l.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY $orderBy
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Filtreler İçin Veriler
$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$cities = $pdo->query("SELECT DISTINCT city FROM locations ORDER BY city ASC")->fetchAll(PDO::FETCH_COLUMN);
$districts = [];
if ($city) {
    $stmt = $pdo->prepare("SELECT DISTINCT district FROM locations WHERE city = ? ORDER BY district ASC");
    $stmt->execute([$city]);
    $districts = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$pageTitle = "Hizmet İlanları ve İş Fırsatları";
require_once '../includes/header.php';
?>

<main class="max-w-7xl mx-auto px-6 py-8 min-h-screen">
    <div class="flex flex-col md:flex-row gap-8">
        <!-- Sidebar Filtre -->
        <aside class="w-full md:w-72 flex-shrink-0">
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 p-6 sticky top-24">
                <h2 class="text-lg font-bold mb-6 flex items-center text-primary dark:text-white">
                    <span class="material-symbols-outlined mr-2">filter_alt</span>
                    Filtrele
                </h2>
                <form method="GET" action="leads.php" id="filterForm">
                    <div class="mb-6">
                        <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">Kelime ile Ara</label>
                        <div class="relative">
                            <input name="search" value="<?= htmlspecialchars($search) ?>" class="w-full pl-3 pr-10 py-2.5 rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary focus:border-primary text-sm" placeholder="Örn: Boya, Tadilat..." type="text"/>
                            <button type="submit" class="absolute right-2 top-2.5 text-slate-400 hover:text-primary">
                                <span class="material-symbols-outlined text-lg">search</span>
                            </button>
                        </div>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">Hizmet Kategorisi</label>
                        <select name="category_id" class="w-full py-2.5 rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary focus:border-primary text-sm" onchange="this.form.submit()">
                            <option value="">Tüm Kategoriler</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">Konum (İl/İlçe)</label>
                        <select name="city" class="w-full py-2.5 rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary focus:border-primary mb-2 text-sm" onchange="this.form.submit()">
                            <option value="">Tüm İller</option>
                            <?php foreach($cities as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= $city == $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="district" class="w-full py-2.5 rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary focus:border-primary text-sm" <?= empty($districts) ? 'disabled' : '' ?>>
                            <option value="">Tüm İlçeler</option>
                            <?php foreach($districts as $d): ?>
                                <option value="<?= htmlspecialchars($d) ?>" <?= $district == $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-8">
                        <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">Bütçe Aralığı (₺)</label>
                        <div class="flex items-center space-x-2">
                            <input name="min_price" value="<?= htmlspecialchars($min_price) ?>" class="w-full px-3 py-2 rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm" placeholder="Min" type="number"/>
                            <span class="text-slate-400">-</span>
                            <input name="max_price" value="<?= htmlspecialchars($max_price) ?>" class="w-full px-3 py-2 rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm" placeholder="Max" type="number"/>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg font-bold hover:bg-primary/90 transition shadow-sm mb-3">
                        Filtreleri Uygula
                    </button>
                    <a href="leads.php" class="block w-full text-center text-slate-500 text-sm font-medium hover:text-primary transition">
                        Filtreleri Temizle
                    </a>
                </form>
            </div>
        </aside>

        <!-- Liste -->
        <div class="flex-grow">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-primary dark:text-white">
                    <?= $totalLeads ?> <span class="font-normal">İş Fırsatı Bulundu</span>
                </h1>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-slate-500 hidden sm:inline">Sırala:</span>
                    <select name="sort" form="filterForm" class="bg-transparent border-none text-sm font-semibold focus:ring-0 cursor-pointer text-slate-700 dark:text-slate-300" onchange="this.form.submit()">
                        <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>En Yeni İlanlar</option>
                        <option value="price_high" <?= $sort == 'price_high' ? 'selected' : '' ?>>Bütçe (Yüksekten Düşüğe)</option>
                        <option value="price_low" <?= $sort == 'price_low' ? 'selected' : '' ?>>Bütçe (Düşükten Yükseğe)</option>
                    </select>
                </div>
            </div>

            <div class="space-y-4">
                <?php if (empty($leads)): ?>
                    <div class="bg-white dark:bg-slate-900 rounded-xl p-12 text-center border border-slate-200 dark:border-slate-800">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-slate-100 dark:bg-slate-800 rounded-full mb-4">
                            <span class="material-symbols-outlined text-3xl text-slate-400">search_off</span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-2">Sonuç Bulunamadı</h3>
                        <p class="text-slate-500">Arama kriterlerinize uygun iş fırsatı bulunamadı. Filtreleri temizleyip tekrar deneyin.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($leads as $lead): ?>
                    <div class="bg-white dark:bg-slate-900 rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 hover:border-primary/50 transition-colors group relative">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <div class="flex items-center space-x-2 mb-1">
                                    <span class="font-semibold text-slate-600 dark:text-slate-400"><?= htmlspecialchars($lead['first_name'] . ' ' . mb_substr($lead['last_name'], 0, 1) . '.') ?></span>
                                    <?php if ($lead['is_verified']): ?>
                                        <span class="material-symbols-outlined text-blue-500 text-sm" title="Onaylı Müşteri">verified</span>
                                        <span class="bg-blue-50 text-blue-600 text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider">ONAYLI</span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="text-xl font-bold text-slate-800 dark:text-white group-hover:text-primary transition-colors">
                                    <a href="../demand-details.php?id=<?= $lead['id'] ?>"><?= htmlspecialchars($lead['title']) ?></a>
                                </h3>
                            </div>
                            <div class="text-right">
                                <span class="block text-xs text-slate-400 uppercase font-bold mb-1">Tahmini Bütçe</span>
                                <span class="text-xl font-extrabold text-primary dark:text-white tracking-tight">
                                    <?= $lead['estimated_cost'] > 0 ? '₺' . number_format($lead['estimated_cost'], 0, ',', '.') : 'Belirtilmemiş' ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-4 mb-4 text-sm text-slate-500">
                            <div class="flex items-center">
                                <span class="material-symbols-outlined text-sm mr-1">schedule</span>
                                <?= date('d.m.Y', strtotime($lead['created_at'])) ?>
                            </div>
                            <div class="flex items-center">
                                <span class="material-symbols-outlined text-sm mr-1">location_on</span>
                                <?= htmlspecialchars($lead['city'] . ', ' . $lead['district']) ?>
                            </div>
                            <div class="flex items-center">
                                <span class="material-symbols-outlined text-sm mr-1">person</span>
                                <?= $lead['offer_count'] ?> Teklif Verildi
                            </div>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row justify-between items-end sm:items-center gap-4 mt-6">
                            <div class="flex flex-wrap gap-2">
                                <span class="px-3 py-1 bg-slate-100 dark:bg-slate-800 rounded-full text-xs font-medium text-slate-600 dark:text-slate-300">
                                    <?= htmlspecialchars($lead['category_name']) ?>
                                </span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <button class="p-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition" title="Kaydet">
                                    <span class="material-symbols-outlined text-slate-400">bookmark_border</span>
                                </button>
                                <a href="../demand-details.php?id=<?= $lead['id'] ?>" class="bg-primary text-white px-8 py-2.5 rounded-lg font-bold hover:bg-primary/90 transition shadow-sm">
                                    Teklif Ver
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Sayfalama -->
            <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-10 space-x-2">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>" class="w-10 h-10 rounded-lg flex items-center justify-center transition font-medium <?= $i === $page ? 'bg-primary text-white font-bold' : 'border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>