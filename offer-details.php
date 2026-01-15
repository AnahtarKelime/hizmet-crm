<?php
require_once 'config/db.php';

// Oturumu başlat (header.php'den önce işlem yaptığımız için gerekli)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$offerId = $_GET['id'] ?? null;

if (!$offerId) {
    header("Location: my-demands.php");
    exit;
}

// Teklif ve ilgili talep detaylarını çek
$stmt = $pdo->prepare("
    SELECT 
        o.*, 
        d.title as demand_title, d.user_id as demand_owner_id,
        u.first_name, u.last_name, u.email, u.phone, u.avatar_url,
        pd.business_name, pd.bio
    FROM offers o
    JOIN demands d ON o.demand_id = d.id
    JOIN users u ON o.user_id = u.id
    LEFT JOIN provider_details pd ON u.id = pd.user_id
    WHERE o.id = ?
");
$stmt->execute([$offerId]);
$offer = $stmt->fetch();

// İşlem Yönetimi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'accept') {
        try {
            $pdo->beginTransaction();
            // 1. Bu teklifi kabul et
            $stmt = $pdo->prepare("UPDATE offers SET status = 'accepted' WHERE id = ?");
            $stmt->execute([$offerId]);
            
            // 2. Talebin durumunu güncelle (approved -> completed veya in_progress olabilir, şimdilik completed diyelim)
            $stmt = $pdo->prepare("UPDATE demands SET status = 'completed' WHERE id = ?");
            $stmt->execute([$offer['demand_id']]);
            
            $pdo->commit();
            $successMsg = "Teklif başarıyla kabul edildi. Hizmet veren ile iletişime geçebilirsiniz.";
            // Sayfayı yenile
            header("Refresh:2");
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMsg = "İşlem sırasında hata oluştu: " . $e->getMessage();
        }
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE offers SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$offerId]);
        $successMsg = "Teklif reddedildi.";
        header("Refresh:2");
    }
}

$pageTitle = "Teklif Detayları";
require_once 'includes/header.php';

// Güvenlik: Sadece talebin sahibi veya Admin bu teklifi görebilir
if (!$offer || ($offer['demand_owner_id'] != $userId && (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin'))) {
    echo "<div class='max-w-7xl mx-auto px-4 py-12'><div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded'>Teklif bulunamadı veya görüntüleme yetkiniz yok.</div></div>";
    require_once 'includes/footer.php';
    exit;
}
?>

<main class="max-w-5xl mx-auto px-4 py-12 min-h-[60vh]">
    <!-- Üst Başlık ve Geri Dön -->
    <div class="flex items-center gap-4 mb-8">
        <a href="demand-details.php?id=<?= $offer['demand_id'] ?>" class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div>
            <h1 class="text-2xl font-black text-slate-800">Teklif Detayları</h1>
            <p class="text-slate-500 text-sm">Talep: <?= htmlspecialchars($offer['demand_title']) ?></p>
        </div>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Sol Kolon: Teklif İçeriği -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Hizmet Veren Bilgisi ve Fiyat -->
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-6 bg-slate-50 rounded-bl-2xl border-b border-l border-slate-100">
                    <p class="text-xs text-slate-500 font-bold uppercase tracking-wider mb-1">Teklif Tutarı</p>
                    <p class="text-3xl font-black text-primary"><?= number_format($offer['price'], 2, ',', '.') ?> ₺</p>
                </div>

                <div class="flex items-center gap-4 mb-6">
                    <?php if (!empty($offer['avatar_url'])): ?>
                        <img src="<?= htmlspecialchars($offer['avatar_url']) ?>" alt="Hizmet Veren" class="w-16 h-16 rounded-full object-cover border-2 border-white shadow-sm">
                    <?php else: ?>
                        <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 font-bold text-xl border-2 border-white shadow-sm">
                            <?= substr($offer['first_name'], 0, 1) . substr($offer['last_name'], 0, 1) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h2 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($offer['business_name'] ?: $offer['first_name'] . ' ' . $offer['last_name']) ?></h2>
                        <div class="flex items-center gap-1 text-sm text-slate-500">
                            <span class="material-symbols-outlined text-[16px] text-yellow-500 fill-1">star</span>
                            <span>4.8 (12 Değerlendirme)</span>
                            <span class="mx-2">•</span>
                            <span class="text-green-600 font-medium">Onaylı Hizmet Veren</span>
                        </div>
                    </div>
                </div>

                <div class="prose prose-slate max-w-none">
                    <h3 class="text-lg font-bold text-slate-800 mb-2">Hizmet Veren Mesajı</h3>
                    <p class="text-slate-600 leading-relaxed bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <?= nl2br(htmlspecialchars($offer['message'])) ?>
                    </p>
                </div>
            </div>

            <!-- Hizmet Sözleşmesi ve Detaylar -->
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100">
                <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">description</span>
                    Hizmet Sözleşmesi & Kapsam
                </h3>
                
                <?php if (!empty($offer['service_agreement'])): ?>
                    <div class="text-sm text-slate-600 space-y-4 leading-relaxed">
                        <?= nl2br(htmlspecialchars($offer['service_agreement'])) ?>
                    </div>
                <?php else: ?>
                    <p class="text-slate-400 italic text-sm">Bu teklif için özel bir hizmet sözleşmesi belirtilmemiş. Standart platform kuralları geçerlidir.</p>
                <?php endif; ?>
            </div>

            <!-- Ödeme Planı -->
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100">
                <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">payments</span>
                    Ödeme Planı & Detaylar
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <h4 class="font-bold text-slate-700 mb-2 text-sm">Ödeme Planı</h4>
                        <p class="text-sm text-slate-600"><?= !empty($offer['payment_plan']) ? nl2br(htmlspecialchars($offer['payment_plan'])) : 'Belirtilmemiş' ?></p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <h4 class="font-bold text-slate-700 mb-2 text-sm">Ödeme Yöntemleri</h4>
                        <p class="text-sm text-slate-600"><?= !empty($offer['payment_details']) ? nl2br(htmlspecialchars($offer['payment_details'])) : 'Belirtilmemiş' ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sağ Kolon: Aksiyonlar -->
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 sticky top-24">
                <h3 class="font-bold text-slate-800 mb-4">İşlemler</h3>
                
                <?php if ($offer['status'] === 'pending'): ?>
                    <div class="space-y-3">
                        <form method="POST">
                            <input type="hidden" name="action" value="accept">
                            <button type="submit" class="w-full py-3 bg-primary text-white font-bold rounded-xl hover:bg-primary/90 shadow-lg shadow-primary/20 transition-all flex items-center justify-center gap-2" onclick="return confirm('Bu teklifi kabul etmek istediğinize emin misiniz?')">
                                <span class="material-symbols-outlined">check_circle</span>
                                Teklifi Kabul Et
                            </button>
                        </form>
                        
                        <a href="messages.php?offer_id=<?= $offer['id'] ?>" class="w-full py-3 bg-white text-slate-700 font-bold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined">chat</span>
                            Mesaj Gönder
                        </a>

                        <form method="POST">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="w-full py-3 bg-white text-red-600 font-bold rounded-xl border border-red-100 hover:bg-red-50 transition-all flex items-center justify-center gap-2" onclick="return confirm('Bu teklifi reddetmek istediğinize emin misiniz?')">
                                <span class="material-symbols-outlined">cancel</span>
                                Teklifi Reddet
                            </button>
                        </form>
                    </div>
                <?php elseif ($offer['status'] === 'accepted'): ?>
                    <div class="space-y-3">
                        <div class="bg-green-50 text-green-700 p-4 rounded-xl text-center font-bold border border-green-200">
                            Bu teklif kabul edildi.
                        </div>
                        <a href="messages.php?offer_id=<?= $offer['id'] ?>" class="w-full py-3 bg-white text-slate-700 font-bold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined">chat</span>
                            Mesaj Gönder
                        </a>
                    </div>
                <?php else: ?>
                    <div class="bg-red-50 text-red-700 p-4 rounded-xl text-center font-bold border border-red-200">
                        Bu teklif reddedildi.
                    </div>
                <?php endif; ?>
                
                <p class="text-xs text-slate-400 mt-4 text-center">Teklifi kabul ettiğinizde hizmet veren ile iletişim bilgileriniz paylaşılacaktır.</p>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>