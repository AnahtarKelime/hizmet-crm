<?php
require_once '../config/db.php';
require_once 'includes/header.php';

$userId = $_GET['id'] ?? null;

if (!$userId) {
    header("Location: applications.php");
    exit;
}

// Durum Güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'];
    
    try {
        $pdo->beginTransaction();
        
        // Durumu güncelle
        $stmt = $pdo->prepare("UPDATE provider_details SET application_status = ? WHERE user_id = ?");
        $stmt->execute([$newStatus, $userId]);

        // Eğer onaylandıysa, kullanıcıyı doğrulanmış yap ve rolünü provider olarak güncelle
        if ($newStatus === 'approved') {
            $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, role = 'provider' WHERE id = ?");
            $stmt->execute([$userId]);

            // Kullanıcı bilgilerini çek
            $stmtUser = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $userData = $stmtUser->fetch();

            // Bildirim Gönder (Mail fonksiyonu sunucu yapılandırması gerektirir)
            $message = "Sayın " . $userData['first_name'] . " " . $userData['last_name'] . ",\n\nHizmet veren başvurunuz onaylanmıştır. Sisteme giriş yaparak işlemlerinize devam edebilirsiniz.";
            // mail($userData['email'], "Başvurunuz Onaylandı", $message);
        }

        $pdo->commit();
        $successMsg = "Başvuru durumu güncellendi.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Hata: " . $e->getMessage();
    }
}

// Kullanıcı ve Başvuru Bilgilerini Çek
$stmt = $pdo->prepare("
    SELECT u.*, pd.business_name, pd.application_status 
    FROM users u 
    JOIN provider_details pd ON u.id = pd.user_id 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Kategorileri Çek
$stmt = $pdo->prepare("
    SELECT c.name 
    FROM provider_service_categories psc 
    JOIN categories c ON psc.category_id = c.id 
    WHERE psc.user_id = ?
");
$stmt->execute([$userId]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Bölgeleri Çek
$stmt = $pdo->prepare("SELECT * FROM provider_service_areas WHERE user_id = ?");
$stmt->execute([$userId]);
$areas = $stmt->fetchAll();

// Evrakları Çek
$stmt = $pdo->prepare("SELECT * FROM provider_documents WHERE user_id = ?");
$stmt->execute([$userId]);
$documents = $stmt->fetchAll();
?>

<div class="flex items-center gap-4 mb-6">
    <a href="applications.php" class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-colors">
        <span class="material-symbols-outlined">arrow_back</span>
    </a>
    <h2 class="text-2xl font-bold text-slate-800">Başvuru Detayı: <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
</div>

<?php if (isset($successMsg)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Sol Kolon: Bilgiler -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Kişisel Bilgiler -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <h3 class="font-bold text-slate-800 mb-4 border-b pb-2">Kişisel Bilgiler</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><span class="text-slate-500 block">Ad Soyad</span> <span class="font-medium"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span></div>
                <div><span class="text-slate-500 block">E-posta</span> <span class="font-medium"><?= htmlspecialchars($user['email']) ?></span></div>
                <div><span class="text-slate-500 block">Telefon</span> <span class="font-medium"><?= htmlspecialchars($user['phone']) ?></span></div>
                <div><span class="text-slate-500 block">İşletme Adı</span> <span class="font-medium"><?= htmlspecialchars($user['business_name'] ?? '-') ?></span></div>
            </div>
        </div>

        <!-- Hizmet Detayları -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <h3 class="font-bold text-slate-800 mb-4 border-b pb-2">Hizmet Detayları</h3>
            <div class="mb-4">
                <h4 class="text-sm font-bold text-slate-700 mb-2">Kategoriler</h4>
                <div class="flex flex-wrap gap-2">
                    <?php foreach($categories as $cat): ?>
                        <span class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-bold"><?= htmlspecialchars($cat) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <h4 class="text-sm font-bold text-slate-700 mb-2">Hizmet Bölgeleri</h4>
                <ul class="list-disc list-inside text-sm text-slate-600">
                    <?php foreach($areas as $area): ?>
                        <li><?= htmlspecialchars($area['city']) ?> <?= $area['districts'] ? '('.htmlspecialchars($area['districts']).')' : '(Tüm Şehir)' ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Evraklar -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <h3 class="font-bold text-slate-800 mb-4 border-b pb-2">Yüklenen Evraklar</h3>
            <div class="space-y-3">
                <?php foreach($documents as $doc): ?>
                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-100">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-slate-400">description</span>
                            <span class="text-sm font-medium text-slate-700"><?= htmlspecialchars($doc['document_type']) ?></span>
                        </div>
                        <a href="../<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-xs font-bold flex items-center gap-1">
                            Görüntüle <span class="material-symbols-outlined text-sm">open_in_new</span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Sağ Kolon: İşlemler -->
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 h-fit sticky top-6">
        <h3 class="font-bold text-slate-800 mb-4">Başvuru İşlemi</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="update_status" value="1">
            <button type="submit" name="status" value="approved" class="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition-colors flex items-center justify-center gap-2"><span class="material-symbols-outlined">check_circle</span> Onayla</button>
            <button type="submit" name="status" value="incomplete" class="w-full py-3 bg-orange-500 hover:bg-orange-600 text-white font-bold rounded-lg transition-colors flex items-center justify-center gap-2"><span class="material-symbols-outlined">warning</span> Eksik Evrak</button>
            <button type="submit" name="status" value="rejected" class="w-full py-3 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg transition-colors flex items-center justify-center gap-2"><span class="material-symbols-outlined">cancel</span> Reddet</button>
        </form>
        <div class="mt-4 pt-4 border-t border-slate-100 text-xs text-slate-500">
            Onaylandığında kullanıcıya bildirim gönderilir ve hizmet veren statüsü aktifleşir.
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>