<?php
require_once '../config/db.php';
require_once 'includes/header.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: messages.php");
    exit;
}

// Mesajı Çek
$stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
$stmt->execute([$id]);
$message = $stmt->fetch();

if (!$message) {
    echo "<div class='p-8 text-center text-red-500'>Mesaj bulunamadı.</div>";
    require_once 'includes/footer.php';
    exit;
}

// Okundu olarak işaretle
if (!$message['is_read']) {
    $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?")->execute([$id]);
}

// Mesajı gönderen kullanıcı sistemde kayıtlı mı?
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmtUser->execute([$message['email']]);
$user = $stmtUser->fetch();

// Şablonları Çek
$templates = $pdo->query("SELECT * FROM admin_message_templates ORDER BY title ASC")->fetchAll();

$successMsg = '';
$errorMsg = '';

// Yanıt Gönderme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reply = trim($_POST['reply']);
    
    if (!empty($reply)) {
        if ($user) {
            try {
                $pdo->beginTransaction();

                // 1. Bu kullanıcı için "Destek Talebi" adında bir talep var mı kontrol et
                $stmtDemand = $pdo->prepare("SELECT id FROM demands WHERE user_id = ? AND title = 'Destek Talebi' LIMIT 1");
                $stmtDemand->execute([$user['id']]);
                $demandId = $stmtDemand->fetchColumn();

                // Yoksa oluştur (Dummy kategori ve lokasyon ile)
                if (!$demandId) {
                    $catId = $pdo->query("SELECT id FROM categories LIMIT 1")->fetchColumn();
                    $locId = $pdo->query("SELECT id FROM locations LIMIT 1")->fetchColumn();
                    
                    $stmtCreateDemand = $pdo->prepare("INSERT INTO demands (user_id, category_id, location_id, title, status, created_at) VALUES (?, ?, ?, 'Destek Talebi', 'approved', NOW())");
                    $stmtCreateDemand->execute([$user['id'], $catId, $locId]);
                    $demandId = $pdo->lastInsertId();
                }

                // 2. Admin ile kullanıcı arasında bu talep için bir sohbet (teklif) var mı?
                $adminId = $_SESSION['user_id'];
                $stmtOffer = $pdo->prepare("SELECT id FROM offers WHERE demand_id = ? AND user_id = ? LIMIT 1");
                $stmtOffer->execute([$demandId, $adminId]);
                $offerId = $stmtOffer->fetchColumn();

                // Yoksa oluştur
                if (!$offerId) {
                    $stmtCreateOffer = $pdo->prepare("INSERT INTO offers (demand_id, user_id, price, message, status, created_at) VALUES (?, ?, 0, 'Destek talebiniz için oluşturulmuştur.', 'accepted', NOW())");
                    $stmtCreateOffer->execute([$demandId, $adminId]);
                    $offerId = $pdo->lastInsertId();
                }

                // 3. Mesajı Gönder
                $stmtMsg = $pdo->prepare("INSERT INTO messages (offer_id, sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmtMsg->execute([$offerId, $adminId, $user['id'], $reply]);

                $pdo->commit();
                $successMsg = "Yanıtınız kullanıcının mesaj kutusuna iletildi.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $errorMsg = "Hata oluştu: " . $e->getMessage();
            }
        } else {
            // Kullanıcı kayıtlı değilse sadece bilgi ver (Mail entegrasyonu yapılabilir)
            $successMsg = "Kullanıcı sistemde kayıtlı değil. Yanıtınız kaydedildi ancak kullanıcıya site içi mesaj gönderilemedi (E-posta gönderimi gerektirir).";
        }
    }
}
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="messages.php" class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <h2 class="text-2xl font-bold text-slate-800">Mesaj Detayı</h2>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-8">
        <div class="p-6 border-b border-slate-100 bg-slate-50">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="text-lg font-bold text-slate-800"><?= htmlspecialchars($message['subject']) ?></h3>
                    <div class="text-sm text-slate-500 mt-1">
                        <span class="font-medium text-slate-700"><?= htmlspecialchars($message['name']) ?></span> 
                        &lt;<?= htmlspecialchars($message['email']) ?>&gt;
                        <?php if($user): ?>
                            <span class="ml-2 bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded font-bold">Kayıtlı Kullanıcı</span>
                        <?php else: ?>
                            <span class="ml-2 bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded font-bold">Misafir</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-xs text-slate-400">
                    <?= date('d.m.Y H:i', strtotime($message['created_at'])) ?>
                </div>
            </div>
        </div>
        <div class="p-8 text-slate-700 leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($message['message']) ?></div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="font-bold text-slate-800 mb-4">Yanıtla</h3>
        
        <?php if (!empty($templates)): ?>
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">Hazır Şablon Kullan</label>
                <select onchange="if(this.value) document.getElementById('replyText').value = this.value;" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Şablon Seçiniz...</option>
                    <?php foreach ($templates as $tpl): ?>
                        <option value="<?= htmlspecialchars($tpl['message']) ?>"><?= htmlspecialchars($tpl['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <form method="POST">
            <textarea name="reply" id="replyText" rows="5" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 mb-4" placeholder="Yanıtınızı buraya yazın..." required></textarea>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-lg transition-colors flex items-center gap-2">
                <span class="material-symbols-outlined">send</span> Yanıtı Gönder
            </button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>