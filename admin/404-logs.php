<?php
require_once '../config/db.php';

// Yönlendirme Ekleme İşlemi
if (isset($_POST['redirect_to_home'])) {
    $url = $_POST['url'];
    $logId = $_POST['log_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Redirects tablosuna ekle (Anasayfaya 302 ile yönlendir)
        $stmt = $pdo->prepare("INSERT INTO redirects (source_url, target_url, status_code) VALUES (?, '/', 302)");
        $stmt->execute([$url]);
        
        // Log tablosundan sil (Artık 404 değil)
        $stmt = $pdo->prepare("DELETE FROM 404_logs WHERE id = ?");
        $stmt->execute([$logId]);
        
        $pdo->commit();
        $successMsg = "URL başarıyla anasayfaya yönlendirildi.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Hata: " . $e->getMessage();
    }
}

// Silme İşlemi
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM 404_logs WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: 404-logs.php?msg=deleted");
    exit;
}

// Tümünü Temizle
if (isset($_POST['clear_all'])) {
    $pdo->exec("TRUNCATE TABLE 404_logs");
    $successMsg = "Tüm loglar temizlendi.";
}

require_once 'includes/header.php';

if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') {
    $successMsg = "Log kaydı başarıyla silindi.";
}

// Sıralama Parametreleri
$sort = $_GET['sort'] ?? 'date_desc';
$orderBy = 'last_hit_at DESC';

switch ($sort) {
    case 'hit_desc':
        $orderBy = 'hit_count DESC';
        break;
    case 'hit_asc':
        $orderBy = 'hit_count ASC';
        break;
    case 'date_asc':
        $orderBy = 'last_hit_at ASC';
        break;
    case 'date_desc':
    default:
        $orderBy = 'last_hit_at DESC';
        break;
}

// Logları Çek
$logs = [];
$chartLabels = [];
$chartValues = [];

try {
    $logs = $pdo->query("SELECT * FROM 404_logs ORDER BY $orderBy")->fetchAll();
    
    // Grafik Verisi (En çok hata veren 10 URL)
    $chartData = $pdo->query("SELECT url, hit_count FROM 404_logs ORDER BY hit_count DESC LIMIT 10")->fetchAll();
    foreach ($chartData as $data) {
        $chartLabels[] = mb_strimwidth($data['url'], 0, 50, '...');
        $chartValues[] = $data['hit_count'];
    }
} catch (PDOException $e) {
    $errorMsg = "Tablo bulunamadı. Lütfen <a href='repair-db.php' class='underline font-bold'>Veritabanı Onar</a> sayfasını ziyaret edin.";
}
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">404 Hata Logları</h2>
        <p class="text-slate-500 text-sm">Bulunamayan sayfaları izleyin ve yönlendirin.</p>
    </div>
    <?php if (!empty($logs)): ?>
    <form method="POST" onsubmit="return confirm('Tüm logları silmek istediğinize emin misiniz?');">
        <input type="hidden" name="clear_all" value="1">
        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors">
            <span class="material-symbols-outlined text-lg">delete_sweep</span>
            Tümünü Temizle
        </button>
    </form>
    <?php endif; ?>
</div>

<?php if (isset($successMsg)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
<?php endif; ?>
<?php if (isset($errorMsg)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
<?php endif; ?>

<?php if (!empty($chartLabels)): ?>
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
    <h3 class="font-bold text-slate-800 mb-4">En Sık Karşılaşılan 404 Hataları</h3>
    <div class="h-64 w-full">
        <canvas id="logsChart"></canvas>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('logsChart');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Hata Sayısı',
                data: <?= json_encode($chartValues) ?>,
                backgroundColor: '#4f46e5',
                borderRadius: 4,
                barThickness: 20
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                },
                x: {
                    ticks: { autoSkip: false, maxRotation: 45, minRotation: 45 }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
</script>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left text-sm text-slate-600">
        <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
            <tr>
                <th class="px-6 py-4">URL</th>
                <th class="px-6 py-4">
                    <a href="?sort=<?= $sort === 'hit_desc' ? 'hit_asc' : 'hit_desc' ?>" class="flex items-center gap-1 hover:text-indigo-600 group">
                        Tekrar Sayısı
                        <?php if($sort === 'hit_desc'): ?>
                            <span class="material-symbols-outlined text-sm">arrow_downward</span>
                        <?php elseif($sort === 'hit_asc'): ?>
                            <span class="material-symbols-outlined text-sm">arrow_upward</span>
                        <?php else: ?>
                            <span class="material-symbols-outlined text-sm text-slate-300 group-hover:text-indigo-400">unfold_more</span>
                        <?php endif; ?>
                    </a>
                </th>
                <th class="px-6 py-4 hidden md:table-cell">
                    <a href="?sort=<?= $sort === 'date_desc' ? 'date_asc' : 'date_desc' ?>" class="flex items-center gap-1 hover:text-indigo-600 group">
                        Son Erişim
                        <?php if($sort === 'date_desc'): ?>
                            <span class="material-symbols-outlined text-sm">arrow_downward</span>
                        <?php elseif($sort === 'date_asc'): ?>
                            <span class="material-symbols-outlined text-sm">arrow_upward</span>
                        <?php else: ?>
                            <span class="material-symbols-outlined text-sm text-slate-300 group-hover:text-indigo-400">unfold_more</span>
                        <?php endif; ?>
                    </a>
                </th>
                <th class="px-6 py-4 text-right">İşlemler</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="4" class="px-6 py-8 text-center text-slate-500">Henüz 404 hatası kaydedilmemiş.</td>
                </tr>
            <?php else: ?>
                <?php foreach($logs as $log): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 font-mono text-xs text-slate-600 break-all">
                        <a href="<?= htmlspecialchars($log['url']) ?>" target="_blank" class="hover:text-indigo-600 hover:underline"><?= htmlspecialchars($log['url']) ?></a>
                    </td>
                    <td class="px-6 py-4 font-bold text-slate-800"><?= $log['hit_count'] ?></td>
                    <td class="px-6 py-4 text-xs text-slate-500 hidden md:table-cell"><?= date('d.m.Y H:i', strtotime($log['last_hit_at'])) ?></td>
                    <td class="px-6 py-4 text-right flex justify-end gap-2">
                        <form method="POST">
                            <input type="hidden" name="redirect_to_home" value="1">
                            <input type="hidden" name="url" value="<?= htmlspecialchars($log['url']) ?>">
                            <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                            <button type="submit" class="bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-3 py-1.5 rounded text-xs font-bold transition-colors flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">turn_right</span> Anasayfaya Yönlendir
                            </button>
                        </form>
                        <a href="404-logs.php?delete=<?= $log['id'] ?>" onclick="return confirm('Silmek istediğinize emin misiniz?')" class="text-red-500 hover:text-red-700 p-1.5 rounded hover:bg-red-50 transition-colors" title="Logu Sil">
                            <span class="material-symbols-outlined text-lg">delete</span>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>