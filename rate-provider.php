<?php
require_once 'config/db.php';
session_start();

// Giriş Kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$offerId = $_GET['offer_id'] ?? null;

if (!$offerId) {
    header("Location: my-demands.php");
    exit;
}

// Teklif ve Talep Bilgilerini Çek (Güvenlik Kontrolü ile)
$stmt = $pdo->prepare("
    SELECT 
        o.id as offer_id, o.price,
        d.id as demand_id, d.title as demand_title, d.created_at as demand_date,
        u.id as provider_id, u.first_name, u.last_name, u.avatar_url,
        pd.business_name,
        (SELECT id FROM reviews WHERE offer_id = o.id AND reviewer_id = :reviewer_id LIMIT 1) as existing_review_id
    FROM offers o
    JOIN demands d ON o.demand_id = d.id
    JOIN users u ON o.user_id = u.id
    LEFT JOIN provider_details pd ON u.id = pd.user_id
    WHERE o.id = :offer_id AND d.user_id = :reviewer_id_check AND o.status = 'accepted'
");
$stmt->execute(['offer_id' => $offerId, 'reviewer_id' => $userId, 'reviewer_id_check' => $userId]);
$job = $stmt->fetch();

if (!$job) {
    die("Bu işlem için yetkiniz yok veya talep tamamlanmamış.");
}

if ($job['existing_review_id']) {
    header("Location: demand-details.php?id=" . $job['demand_id'] . "&msg=already_rated");
    exit;
}

// Form Gönderimi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = $_POST['rating'] ?? 0;
    $ratingSpeed = $_POST['rating_speed'] ?? 0;
    $ratingComm = $_POST['rating_comm'] ?? 0;
    $ratingPrice = $_POST['rating_price'] ?? 0;
    $comment = trim($_POST['comment'] ?? '');

    if ($rating > 0) {
        try {
            $criteria = json_encode([
                'speed' => $ratingSpeed,
                'communication' => $ratingComm,
                'price_performance' => $ratingPrice
            ]);

            $stmt = $pdo->prepare("INSERT INTO reviews (offer_id, reviewer_id, reviewed_id, rating, criteria_ratings, comment) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$offerId, $userId, $job['provider_id'], $rating, $criteria, $comment]);

            header("Location: demand-details.php?id=" . $job['demand_id'] . "&status=success&msg=Değerlendirmeniz+alındı");
            exit;
        } catch (Exception $e) {
            $error = "Hata oluştu: " . $e->getMessage();
        }
    } else {
        $error = "Lütfen genel bir puan veriniz.";
    }
}

$pageTitle = "Hizmet Değerlendirme";
require_once 'includes/header.php';
?>

<style>
    .star-rating .star { color: #d1d5db; cursor: pointer; transition: color 0.2s; }
    .star-rating .star.filled { color: #fbbd23; }
    .star-rating .star:hover { color: #fbbd23; }
</style>

<main class="flex flex-1 justify-center py-8 px-4 md:px-10 lg:px-20 min-h-[80vh]">
    <div class="layout-content-container flex flex-col max-w-[800px] flex-1">
        
        <!-- İş Özeti Kartı -->
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 p-6 mb-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="size-16 rounded-lg bg-slate-50 dark:bg-slate-800 flex items-center justify-center overflow-hidden border border-slate-200 dark:border-slate-700 shrink-0">
                    <span class="material-symbols-outlined text-primary text-3xl">construction</span>
                </div>
                <div>
                    <h1 class="text-slate-900 dark:text-white text-xl md:text-2xl font-bold"><?= htmlspecialchars($job['demand_title']) ?></h1>
                    <p class="text-slate-500 dark:text-slate-400 text-sm font-medium flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">verified</span> 
                        <?= htmlspecialchars($job['business_name'] ?: $job['first_name'] . ' ' . $job['last_name']) ?>
                    </p>
                </div>
            </div>
            <div class="text-left sm:text-right w-full sm:w-auto border-t sm:border-t-0 border-slate-100 pt-4 sm:pt-0">
                <p class="text-xs text-slate-500 dark:text-slate-500 uppercase tracking-wider font-bold">Talep Tarihi</p>
                <p class="text-slate-900 dark:text-slate-300 font-semibold"><?= date('d F Y', strtotime($job['demand_date'])) ?></p>
            </div>
        </div>

        <!-- Değerlendirme Formu -->
        <form method="POST" class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-100 dark:border-slate-800">
                <h2 class="text-slate-900 dark:text-white text-xl font-bold">Deneyiminizi Değerlendirin</h2>
                <p class="text-slate-500 text-sm">Aldığınız hizmet kalitesini 5 üzerinden puanlayın.</p>
            </div>
            
            <?php if(isset($error)): ?>
                <div class="mx-8 mt-6 p-4 bg-red-50 text-red-600 rounded-lg text-sm font-bold">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="p-8 space-y-8">
                <!-- Genel Puan -->
                <div class="flex flex-col items-center gap-3">
                    <span class="text-slate-900 dark:text-slate-300 font-semibold">Genel Puanınız</span>
                    <div class="star-rating flex gap-2" data-input="rating">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <span class="material-symbols-outlined text-4xl star" data-value="<?= $i ?>">star</span>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" id="rating" required>
                </div>

                <!-- Detaylı Puanlar -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-8 border-t border-slate-100 dark:border-slate-800">
                    <!-- Hız -->
                    <div class="flex flex-col items-center gap-2">
                        <span class="text-slate-900 dark:text-slate-400 text-sm font-bold">Hız</span>
                        <div class="star-rating flex gap-1" data-input="rating_speed">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <span class="material-symbols-outlined text-xl star" data-value="<?= $i ?>">star</span>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating_speed" id="rating_speed">
                    </div>
                    
                    <!-- İletişim -->
                    <div class="flex flex-col items-center gap-2">
                        <span class="text-slate-900 dark:text-slate-400 text-sm font-bold">İletişim</span>
                        <div class="star-rating flex gap-1" data-input="rating_comm">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <span class="material-symbols-outlined text-xl star" data-value="<?= $i ?>">star</span>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating_comm" id="rating_comm">
                    </div>

                    <!-- Fiyat/Performans -->
                    <div class="flex flex-col items-center gap-2">
                        <span class="text-slate-900 dark:text-slate-400 text-sm font-bold">Fiyat/Performans</span>
                        <div class="star-rating flex gap-1" data-input="rating_price">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <span class="material-symbols-outlined text-xl star" data-value="<?= $i ?>">star</span>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating_price" id="rating_price">
                    </div>
                </div>

                <!-- Yorum Alanı -->
                <div class="flex flex-col gap-3 pt-6">
                    <label class="text-slate-900 dark:text-slate-300 font-semibold" for="comment">Deneyiminiz hakkında daha fazla bilgi verin</label>
                    <textarea name="comment" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-4 focus:ring-primary focus:border-primary resize-none placeholder:text-slate-400" id="comment" placeholder="Hizmet kalitesi, profesyonellik ve süreç hakkında görüşlerinizi paylaşın..." rows="4"></textarea>
                </div>
            </div>

            <div class="px-8 py-6 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                <button type="submit" class="flex items-center justify-center rounded-xl h-12 bg-primary hover:bg-primary/90 text-white px-8 text-base font-bold shadow-lg transition-all">
                    Değerlendirmeyi Gönder
                </button>
            </div>
        </form>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ratingGroups = document.querySelectorAll('.star-rating');

        ratingGroups.forEach(group => {
            const stars = group.querySelectorAll('.star');
            const inputId = group.dataset.input;
            const input = document.getElementById(inputId);

            stars.forEach(star => {
                star.addEventListener('click', () => {
                    const value = parseInt(star.dataset.value);
                    input.value = value;
                    
                    // Yıldızları güncelle
                    stars.forEach(s => {
                        if (parseInt(s.dataset.value) <= value) {
                            s.classList.add('filled');
                        } else {
                            s.classList.remove('filled');
                        }
                    });
                });
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>