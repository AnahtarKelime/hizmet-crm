<?php
require_once '../config/db.php';
session_start();

// Yetki Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'provider') {
    header("Location: ../login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$pageTitle = "Kazandığım İşler";
$pathPrefix = '../';
require_once '../includes/header.php';

// Kazandığım İşleri Çek
$stmt = $pdo->prepare("
    SELECT 
        o.*, 
        d.title as demand_title, d.created_at as demand_date,
        c.name as category_name,
        l.city, l.district,
        u.first_name, u.last_name, u.phone, u.email, u.avatar_url,
        (SELECT id FROM reviews WHERE offer_id = o.id AND reviewer_id = o.user_id LIMIT 1) as review_id
    FROM offers o
    JOIN demands d ON o.demand_id = d.id
    JOIN categories c ON d.category_id = c.id
    JOIN locations l ON d.location_id = l.id
    JOIN users u ON d.user_id = u.id
    WHERE o.user_id = ? AND o.status = 'accepted'
    ORDER BY o.created_at DESC
");
$stmt->execute([$userId]);
$wonJobs = $stmt->fetchAll();
?>

<main class="max-w-7xl mx-auto px-4 py-12 min-h-[60vh]">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800">Kazandığım İşler</h1>
            <p class="text-slate-500">Teklifiniz kabul edilen ve tamamlanan işleriniz.</p>
        </div>
        <a href="leads.php" class="px-6 py-3 bg-slate-100 text-slate-700 rounded-xl font-bold hover:bg-slate-200 transition-all">
            İş Fırsatlarına Dön
        </a>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'rated_success'): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined">check_circle</span> Değerlendirmeniz başarıyla kaydedildi.
        </div>
    <?php endif; ?>

    <?php if (empty($wonJobs)): ?>
        <div class="text-center py-20 bg-white rounded-2xl border border-slate-100 shadow-sm">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-green-50 text-green-500 rounded-full mb-6">
                <span class="material-symbols-outlined text-4xl">workspace_premium</span>
            </div>
            <h2 class="text-xl font-bold text-slate-700 mb-2">Henüz kazanılan iş yok</h2>
            <p class="text-slate-500 mb-6">Verdiğiniz teklifler kabul edildiğinde burada listelenecektir.</p>
            <a href="leads.php" class="px-6 py-3 bg-primary text-white rounded-xl font-bold hover:bg-primary/90 transition-all">
                İş Fırsatlarını İncele
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($wonJobs as $job): ?>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-all group relative overflow-hidden">
                    <div class="absolute top-0 right-0 bg-green-500 text-white text-[10px] font-bold px-3 py-1 rounded-bl-xl">
                        KAZANILDI
                    </div>
                    
                    <div class="flex items-center gap-4 mb-4">
                        <?php if (!empty($job['avatar_url'])): ?>
                            <img src="../<?= htmlspecialchars($job['avatar_url']) ?>" class="w-12 h-12 rounded-full object-cover border border-slate-200">
                        <?php else: ?>
                            <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-lg border border-slate-200">
                                <?= mb_substr($job['first_name'], 0, 1) . mb_substr($job['last_name'], 0, 1) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h3 class="font-bold text-slate-800"><?= htmlspecialchars($job['first_name'] . ' ' . $job['last_name']) ?></h3>
                            <p class="text-xs text-slate-500">Müşteri</p>
                        </div>
                    </div>

                    <div class="mb-4">
                        <span class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-bold uppercase tracking-wider inline-block mb-2">
                            <?= htmlspecialchars($job['category_name']) ?>
                        </span>
                        <h4 class="font-bold text-slate-800 line-clamp-2 mb-1"><?= htmlspecialchars($job['demand_title']) ?></h4>
                        <p class="text-slate-500 text-sm flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">location_on</span>
                            <?= htmlspecialchars($job['city'] . ' / ' . $job['district']) ?>
                        </p>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-4 mb-4 border border-slate-100">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-bold text-slate-500 uppercase">Kazanılan Tutar</span>
                            <span class="font-black text-lg text-primary"><?= number_format($job['price'], 2, ',', '.') ?> ₺</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold text-slate-500 uppercase">Tarih</span>
                            <span class="text-xs font-bold text-slate-700"><?= date('d.m.Y', strtotime($job['created_at'])) ?></span>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <a href="tel:<?= htmlspecialchars($job['phone']) ?>" class="flex-1 py-2.5 bg-green-50 text-green-700 font-bold rounded-lg hover:bg-green-100 transition-colors flex items-center justify-center gap-2 text-sm">
                            <span class="material-symbols-outlined text-sm">call</span> Ara
                        </a>
                        <a href="../messages.php?offer_id=<?= $job['id'] ?>" class="flex-1 py-2.5 bg-blue-50 text-blue-700 font-bold rounded-lg hover:bg-blue-100 transition-colors flex items-center justify-center gap-2 text-sm">
                            <span class="material-symbols-outlined text-sm">chat</span> Mesaj
                        </a>
                    </div>

                    <?php if (!$job['review_id']): ?>
                        <a href="rate-customer.php?offer_id=<?= $job['id'] ?>" class="block w-full mt-3 py-2.5 bg-yellow-50 text-yellow-700 font-bold rounded-lg hover:bg-yellow-100 transition-colors text-center text-sm flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-sm">star</span> Müşteriyi Değerlendir
                        </a>
                    <?php else: ?>
                        <div class="mt-3 py-2 text-center text-xs text-green-600 font-bold bg-green-50 rounded-lg border border-green-100">Değerlendirme Yapıldı</div>
                    <?php endif; ?>
                    
                    <a href="../demand-details.php?id=<?= $job['demand_id'] ?>" class="block w-full mt-2 py-2.5 text-center text-slate-500 font-bold text-sm hover:text-primary transition-colors">
                        Detayları Görüntüle
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>