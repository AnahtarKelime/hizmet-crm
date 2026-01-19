<?php
require_once '../config/db.php';
require_once '../includes/mail-helper.php';

// Ödeme Onaylama İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_payment'])) {
    $transactionId = $_POST['transaction_id'];
    
    try {
        $pdo->beginTransaction();

        // İşlemi ve Paket Bilgisini Çek
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending'");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();

        if ($transaction && $transaction['package_id']) {
            // Paketi Çek
            $pkgStmt = $pdo->prepare("SELECT * FROM subscription_packages WHERE id = ?");
            $pkgStmt->execute([$transaction['package_id']]);
            $package = $pkgStmt->fetch();

            if ($package) {
                // Mevcut abonelik durumunu kontrol et
                $stmtCheck = $pdo->prepare("SELECT subscription_ends_at FROM provider_details WHERE user_id = ?");
                $stmtCheck->execute([$transaction['user_id']]);
                $currentDetails = $stmtCheck->fetch();

                // Bitiş tarihini hesapla (Mevcut süre varsa üstüne ekle)
                $currentEndDate = ($currentDetails && $currentDetails['subscription_ends_at'] && new DateTime($currentDetails['subscription_ends_at']) > new DateTime()) 
                    ? $currentDetails['subscription_ends_at'] 
                    : date('Y-m-d H:i:s');
                
                $endDate = date('Y-m-d H:i:s', strtotime($currentEndDate . " +{$package['duration_days']} days"));
                $subType = ($package['price'] > 0) ? 'premium' : 'free';
                $offerCredit = $package['offer_credit'];

                // Kredileri ve süreyi güncelle (Üstüne ekle)
                $updStmt = $pdo->prepare("
                    UPDATE provider_details 
                    SET subscription_type = ?, subscription_ends_at = ?, 
                        remaining_offer_credit = IF(remaining_offer_credit = -1 OR ? = -1, -1, remaining_offer_credit + ?)
                    WHERE user_id = ?");
                $updStmt->execute([$subType, $endDate, $offerCredit, $offerCredit, $transaction['user_id']]);

                // İşlem Durumunu Güncelle
                $pdo->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?")->execute([$transactionId]);
                
                // Hizmet Verene Mail Gönder
                $stmtUser = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
                $stmtUser->execute([$transaction['user_id']]);
                $user = $stmtUser->fetch();

                if ($user) {
                    sendEmail($user['email'], 'payment_approved', [
                        'name' => $user['first_name'] . ' ' . $user['last_name'],
                        'amount' => number_format($transaction['amount'], 2, ',', '.'),
                        'package_name' => $package['name']
                    ]);
                }
                
                $successMsg = "Ödeme onaylandı ve paket kullanıcıya tanımlandı.";
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Hata: " . $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_payment'])) {
    $transactionId = $_POST['transaction_id'];
    $stmt = $pdo->prepare("UPDATE transactions SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$transactionId]);
    $successMsg = "Ödeme reddedildi.";
    header("Refresh:1");
    exit;
}

// Ödemeleri Çek
$sql = "SELECT t.*, u.first_name, u.last_name, u.email 
        FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        ORDER BY t.created_at DESC";

$payments = $pdo->query($sql)->fetchAll();

require_once 'includes/header.php';
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Ödeme Geçmişi</h2>
        <p class="text-slate-500 text-sm">Sistemdeki tüm finansal işlemler ve paket satın alımları.</p>
    </div>
</div>

<?php if (isset($successMsg)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
<?php endif; ?>
<?php if (isset($errorMsg)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left text-sm text-slate-600">
        <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
            <tr>
                <th class="px-6 py-4">ID</th>
                <th class="px-6 py-4">Kullanıcı</th>
                <th class="px-6 py-4">İşlem Tipi</th>
                <th class="px-6 py-4">Açıklama</th>
                <th class="px-6 py-4">Tutar</th>
                <th class="px-6 py-4">Tarih</th>
                <th class="px-6 py-4">Durum</th>
                <th class="px-6 py-4 text-right">İşlem</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-slate-500">Henüz kayıtlı işlem bulunmuyor.</td>
                </tr>
            <?php else: ?>
                <?php foreach($payments as $payment): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 font-mono text-xs text-slate-400">#<?= $payment['id'] ?></td>
                    <td class="px-6 py-4">
                        <div class="font-bold text-slate-800"><?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?></div>
                        <div class="text-xs text-slate-500"><?= htmlspecialchars($payment['email']) ?></div>
                    </td>
                    <td class="px-6 py-4">
                        <?php
                        $typeLabels = [
                            'subscription_payment' => ['label' => 'Paket Satın Alımı', 'class' => 'bg-blue-100 text-blue-700'],
                            'deposit' => ['label' => 'Bakiye Yükleme', 'class' => 'bg-green-100 text-green-700'],
                            'lead_fee' => ['label' => 'Teklif Ücreti', 'class' => 'bg-orange-100 text-orange-700'],
                            'refund' => ['label' => 'İade', 'class' => 'bg-red-100 text-red-700']
                        ];
                        $typeInfo = $typeLabels[$payment['type']] ?? ['label' => $payment['type'], 'class' => 'bg-gray-100 text-gray-700'];
                        ?>
                        <span class="px-2 py-1 rounded text-xs font-bold <?= $typeInfo['class'] ?>">
                            <?= $typeInfo['label'] ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-slate-700">
                        <?= htmlspecialchars($payment['description']) ?>
                    </td>
                    <td class="px-6 py-4 font-bold text-slate-800">
                        <?= number_format($payment['amount'], 2, ',', '.') ?> ₺
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-500">
                        <?= date('d.m.Y H:i', strtotime($payment['created_at'])) ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php if($payment['status'] == 'pending'): ?>
                            <span class="px-2 py-1 rounded text-xs font-bold bg-yellow-100 text-yellow-700">Bekliyor</span>
                        <?php elseif($payment['status'] == 'approved'): ?>
                            <span class="px-2 py-1 rounded text-xs font-bold bg-green-100 text-green-700">Onaylı</span>
                        <?php else: ?>
                            <span class="px-2 py-1 rounded text-xs font-bold bg-red-100 text-red-700">Red</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <?php if($payment['status'] == 'pending'): ?>
                            <div class="flex gap-2 justify-end">
                                <form method="POST" onsubmit="return confirm('Bu ödemeyi onaylamak istiyor musunuz?');">
                                    <input type="hidden" name="approve_payment" value="1">
                                    <input type="hidden" name="transaction_id" value="<?= $payment['id'] ?>">
                                    <button type="submit" class="text-green-600 hover:text-green-800 font-bold text-xs bg-green-50 px-3 py-1.5 rounded transition-colors">Onayla</button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Bu ödemeyi reddetmek istiyor musunuz?');">
                                    <input type="hidden" name="reject_payment" value="1">
                                    <input type="hidden" name="transaction_id" value="<?= $payment['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800 font-bold text-xs bg-red-50 px-3 py-1.5 rounded transition-colors">Reddet</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>