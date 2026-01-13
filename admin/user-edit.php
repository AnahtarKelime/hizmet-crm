<?php
require_once '../config/db.php';
require_once 'includes/header.php';

$userId = $_GET['id'] ?? null;

if (!$userId) {
    header("Location: users.php");
    exit;
}

// Kullanıcı Bilgilerini Çek
$stmt = $pdo->prepare("
    SELECT u.*, pd.business_name, pd.bio, pd.subscription_type, pd.subscription_ends_at 
    FROM users u 
    LEFT JOIN provider_details pd ON u.id = pd.user_id 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    echo "<div class='p-8 text-center text-red-500'>Kullanıcı bulunamadı.</div>";
    require_once 'includes/footer.php';
    exit;
}

// Güncelleme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Temel Bilgiler
        $firstName = $_POST['first_name'];
        $lastName = $_POST['last_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $role = $_POST['role'];
        $isVerified = isset($_POST['is_verified']) ? 1 : 0;

        $sql = "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, role=?, is_verified=?";
        $params = [$firstName, $lastName, $email, $phone, $role, $isVerified];

        // Şifre Değişikliği (Eğer girildiyse)
        if (!empty($_POST['password'])) {
            $sql .= ", password=?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id=?";
        $params[] = $userId;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Hizmet Veren Detayları (Eğer rol provider ise)
        if ($role === 'provider') {
            $businessName = $_POST['business_name'] ?? null;
            $bio = $_POST['bio'] ?? null;
            $subType = $_POST['subscription_type'] ?? 'free';
            
            // Provider kaydı var mı kontrol et
            $checkStmt = $pdo->prepare("SELECT id FROM provider_details WHERE user_id = ?");
            $checkStmt->execute([$userId]);
            
            if ($checkStmt->fetch()) {
                $pStmt = $pdo->prepare("UPDATE provider_details SET business_name=?, bio=?, subscription_type=? WHERE user_id=?");
                $pStmt->execute([$businessName, $bio, $subType, $userId]);
            } else {
                $pStmt = $pdo->prepare("INSERT INTO provider_details (user_id, business_name, bio, subscription_type) VALUES (?, ?, ?, ?)");
                $pStmt->execute([$userId, $businessName, $bio, $subType]);
            }
        }

        $pdo->commit();
        $successMsg = "Kullanıcı bilgileri başarıyla güncellendi.";
        
        // Verileri tazelemek için sayfayı yenile (veya tekrar sorgula)
        header("Refresh:0"); 
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Hata oluştu: " . $e->getMessage();
    }
}
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="users.php" class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <h2 class="text-2xl font-bold text-slate-800">Kullanıcı Düzenle: <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Ad</label>
                <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Soyad</label>
                <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">E-posta</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Telefon</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Rol</label>
                <select name="role" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="customer" <?= $user['role'] == 'customer' ? 'selected' : '' ?>>Müşteri</option>
                    <option value="provider" <?= $user['role'] == 'provider' ? 'selected' : '' ?>>Hizmet Veren</option>
                    <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Yönetici</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Yeni Şifre (Değiştirmek istemiyorsanız boş bırakın)</label>
                <input type="password" name="password" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="******">
            </div>
        </div>

        <div class="flex items-center gap-3 p-4 border border-slate-200 rounded-lg bg-slate-50">
            <input type="checkbox" name="is_verified" value="1" <?= $user['is_verified'] ? 'checked' : '' ?> class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500">
            <div>
                <span class="block font-bold text-slate-700">Onaylı Hesap</span>
                <span class="text-xs text-slate-500">Kullanıcı e-posta/telefon doğrulamasını tamamlamış mı?</span>
            </div>
        </div>

        <div class="pt-6 border-t border-slate-100 flex justify-end gap-4">
            <a href="users.php" class="px-6 py-3 rounded-lg text-slate-600 font-bold hover:bg-slate-100 transition-colors">İptal</a>
            <button type="submit" class="px-8 py-3 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">Değişiklikleri Kaydet</button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>