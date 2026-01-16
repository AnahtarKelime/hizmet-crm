<?php
require_once '../config/db.php';
require_once '../includes/mail-helper.php';


// Güncelleme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Temel Bilgiler
        $userId = $_GET['id'] ?? null; // POST işleminde ID'yi tekrar al
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
            $offerCredit = $_POST['offer_credit'] ?? 0;
            
            // Provider kaydı var mı kontrol et
            $checkStmt = $pdo->prepare("SELECT id FROM provider_details WHERE user_id = ?");
            $checkStmt->execute([$userId]);
            
            // Eski durumu kontrol et (Mail göndermek için)
            $oldStatus = 'none';
            if ($checkStmt->fetch()) {
                $stmtStatus = $pdo->prepare("SELECT application_status FROM provider_details WHERE user_id = ?");
                $stmtStatus->execute([$userId]);
                $oldStatus = $stmtStatus->fetchColumn();

                $pStmt = $pdo->prepare("UPDATE provider_details SET business_name=?, bio=?, subscription_type=?, remaining_offer_credit=? WHERE user_id=?");
                $pStmt->execute([$businessName, $bio, $subType, $offerCredit, $userId]);
            } else {
                $pStmt = $pdo->prepare("INSERT INTO provider_details (user_id, business_name, bio, subscription_type, remaining_offer_credit) VALUES (?, ?, ?, ?, ?)");
                $pStmt->execute([$userId, $businessName, $bio, $subType, $offerCredit]);
            }

            // Eğer durum 'approved' olduysa mail gönder (Burada formda application_status alanı olmadığı için varsayılan olarak provider rolüne geçişi onay kabul edebiliriz veya form'a status alanı ekleyebiliriz. Mevcut kodda status alanı yok, bu yüzden rol provider ise ve verified ise onaylandı sayabiliriz.)
            // Ancak daha doğru olanı, admin panelinde bir "Onayla" butonu olmasıdır. Mevcut yapıda rol 'provider' yapıldığında onaylanmış sayalım.
            if ($role === 'provider' && $isVerified && $oldStatus !== 'approved') {
                 sendEmail($email, 'provider_approved', [
                    'name' => $firstName . ' ' . $lastName,
                    'link' => getBaseUrl() . '/provider/leads.php'
                ]);
                // Provider details tablosunda statusu güncellemek gerekebilir
                $pdo->prepare("UPDATE provider_details SET application_status = 'approved' WHERE user_id = ?")->execute([$userId]);
            }

            // Hizmet Bölgelerini Güncelle
            $city = $_POST['city'] ?? '';
            $districts = $_POST['districts'] ?? ''; // Virgülle ayrılmış string veya array gelebilir, burada basit text input varsayıyoruz veya select
            
            // Önce eskileri temizle (Tek bölge varsayımıyla)
            $pdo->prepare("DELETE FROM provider_service_areas WHERE user_id = ?")->execute([$userId]);
            $stmtArea = $pdo->prepare("INSERT INTO provider_service_areas (user_id, city, districts) VALUES (?, ?, ?)");
            $stmtArea->execute([$userId, $city, $districts]);

            // Hizmet Kategorisini Güncelle
            $categoryId = $_POST['category_id'] ?? null;
            if ($categoryId) {
                $pdo->prepare("DELETE FROM provider_service_categories WHERE user_id = ?")->execute([$userId]);
                $stmtCat = $pdo->prepare("INSERT INTO provider_service_categories (user_id, category_id) VALUES (?, ?)");
                $stmtCat->execute([$userId, $categoryId]);
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

require_once 'includes/header.php';

$userId = $_GET['id'] ?? null;

if (!$userId) {
    header("Location: users.php");
    exit;
}

// Kullanıcı Bilgilerini Çek
$stmt = $pdo->prepare("
    SELECT u.*, pd.business_name, pd.bio, pd.subscription_type, pd.subscription_ends_at, pd.remaining_offer_credit 
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

// Şehirleri Çek
$cities = $pdo->query("SELECT DISTINCT city FROM locations ORDER BY city ASC")->fetchAll(PDO::FETCH_COLUMN);
$districtsData = $pdo->query("SELECT city, district FROM locations ORDER BY district ASC")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

// Kategorileri Çek
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Eğer kullanıcı hizmet veren ise son tekliflerini çek
$recentOffers = [];
if ($user['role'] === 'provider') {
    $stmtOffers = $pdo->prepare("
        SELECT o.*, d.title as demand_title, d.id as demand_id, d.status as demand_status
        FROM offers o
        JOIN demands d ON o.demand_id = d.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT 20
    ");
    $stmtOffers->execute([$userId]);
    $recentOffers = $stmtOffers->fetchAll();

    // Hizmet Bölgesini Çek
    $stmtArea = $pdo->prepare("SELECT * FROM provider_service_areas WHERE user_id = ? LIMIT 1");
    $stmtArea->execute([$userId]);
    $providerArea = $stmtArea->fetch();

    // Hizmet Kategorisini Çek
    $stmtCat = $pdo->prepare("SELECT category_id FROM provider_service_categories WHERE user_id = ? LIMIT 1");
    $stmtCat->execute([$userId]);
    $providerCategoryId = $stmtCat->fetchColumn();
}

// Kullanıcının Taleplerini Çek
$stmtDemands = $pdo->prepare("
    SELECT d.*, c.name as category_name, l.city, l.district 
    FROM demands d
    LEFT JOIN categories c ON d.category_id = c.id
    LEFT JOIN locations l ON d.location_id = l.id
    WHERE d.user_id = ?
    ORDER BY d.created_at DESC
");
$stmtDemands->execute([$userId]);
$userDemands = $stmtDemands->fetchAll();
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

        <?php if ($user['role'] === 'provider'): ?>
        <div class="p-6 border border-slate-200 rounded-lg bg-slate-50 space-y-4">
            <h3 class="font-bold text-slate-800 border-b border-slate-200 pb-2">Hizmet Veren Detayları</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">İşletme Adı</label>
                    <input type="text" name="business_name" value="<?= htmlspecialchars($user['business_name'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Abonelik Tipi</label>
                    <select name="subscription_type" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="free" <?= ($user['subscription_type'] ?? 'free') == 'free' ? 'selected' : '' ?>>Ücretsiz</option>
                        <option value="premium" <?= ($user['subscription_type'] ?? '') == 'premium' ? 'selected' : '' ?>>Premium</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-slate-700 mb-2">Biyografi</label>
                    <textarea name="bio" rows="3" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Teklif Kredisi (Bakiye)</label>
                    <input type="number" name="offer_credit" value="<?= htmlspecialchars($user['remaining_offer_credit'] ?? 0) ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-slate-500 mt-1">Sınırsız kredi için -1 giriniz.</p>
                </div>
            </div>
        </div>

        <div class="p-6 border border-slate-200 rounded-lg bg-slate-50 space-y-4">
            <h3 class="font-bold text-slate-800 border-b border-slate-200 pb-2">Hizmet Kategorisi</h3>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Kategori</label>
                <select name="category_id" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Seçiniz...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($providerCategoryId == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="p-6 border border-slate-200 rounded-lg bg-slate-50 space-y-4">
            <h3 class="font-bold text-slate-800 border-b border-slate-200 pb-2">Hizmet Bölgesi</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Şehir</label>
                    <select name="city" id="citySelect" onchange="updateDistricts()" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Seçiniz...</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= htmlspecialchars($city) ?>" <?= ($providerArea['city'] ?? '') == $city ? 'selected' : '' ?>><?= htmlspecialchars($city) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">İlçe (Opsiyonel)</label>
                    <select name="districts" id="districtSelect" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tüm Şehir</option>
                        <?php if (!empty($providerArea['city']) && isset($districtsData[$providerArea['city']])): ?>
                            <?php foreach ($districtsData[$providerArea['city']] as $district): ?>
                                <option value="<?= htmlspecialchars($district) ?>" <?= ($providerArea['districts'] ?? '') == $district ? 'selected' : '' ?>><?= htmlspecialchars($district) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Belirli bir ilçe seçilmezse tüm şehir geçerli olur.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="pt-6 border-t border-slate-100 flex justify-end gap-4">
            <a href="users.php" class="px-6 py-3 rounded-lg text-slate-600 font-bold hover:bg-slate-100 transition-colors">İptal</a>
            <button type="submit" class="px-8 py-3 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">Değişiklikleri Kaydet</button>
        </div>
    </form>

    <?php if ($user['role'] === 'provider'): ?>
    <!-- Hizmet Veren Teklif Geçmişi -->
    <div class="mt-10">
        <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-indigo-600">history_edu</span>
            Son Verilen Teklifler (Son 20)
        </h3>
        
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4">Talep</th>
                        <th class="px-6 py-4">Teklif Tutarı</th>
                        <th class="px-6 py-4">Mesaj</th>
                        <th class="px-6 py-4">Tarih</th>
                        <th class="px-6 py-4">Durum</th>
                        <th class="px-6 py-4 text-right">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($recentOffers)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500">Bu kullanıcı henüz hiç teklif vermemiş.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($recentOffers as $offer): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-800 truncate max-w-[200px]" title="<?= htmlspecialchars($offer['demand_title']) ?>">
                                    <?= htmlspecialchars($offer['demand_title']) ?>
                                </div>
                                <div class="text-xs text-slate-400">ID: #<?= $offer['demand_id'] ?></div>
                            </td>
                            <td class="px-6 py-4 font-bold text-slate-700">
                                <?= number_format($offer['price'], 2, ',', '.') ?> ₺
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-xs text-slate-500 truncate max-w-[250px]" title="<?= htmlspecialchars($offer['message']) ?>">
                                    <?= htmlspecialchars($offer['message']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500">
                                <?= date('d.m.Y H:i', strtotime($offer['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php
                                $statusClass = match($offer['status']) {
                                    'accepted' => 'bg-green-100 text-green-700',
                                    'rejected' => 'bg-red-100 text-red-700',
                                    default => 'bg-yellow-100 text-yellow-700'
                                };
                                $statusLabel = match($offer['status']) {
                                    'accepted' => 'Kabul Edildi',
                                    'rejected' => 'Reddedildi',
                                    default => 'Beklemede'
                                };
                                ?>
                                <span class="px-2 py-1 rounded text-xs font-bold <?= $statusClass ?>"><?= $statusLabel ?></span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="../offer-details.php?id=<?= $offer['id'] ?>" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium text-xs bg-indigo-50 px-3 py-1.5 rounded transition-colors">
                                    Görüntüle
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Kullanıcı Talep Geçmişi -->
    <div class="mt-10">
        <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-indigo-600">format_list_bulleted</span>
            Hizmet Talepleri
        </h3>
        
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4">ID</th>
                        <th class="px-6 py-4">Başlık</th>
                        <th class="px-6 py-4">Kategori</th>
                        <th class="px-6 py-4">Lokasyon</th>
                        <th class="px-6 py-4">Tarih</th>
                        <th class="px-6 py-4">Durum</th>
                        <th class="px-6 py-4 text-right">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($userDemands)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-slate-500">Bu kullanıcı henüz hiç talep oluşturmamış.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($userDemands as $demand): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 font-mono text-xs text-slate-400">#<?= $demand['id'] ?></td>
                            <td class="px-6 py-4 font-medium text-slate-800">
                                <?= htmlspecialchars($demand['title']) ?>
                            </td>
                            <td class="px-6 py-4">
                                <?= htmlspecialchars($demand['category_name']) ?>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500">
                                <?= htmlspecialchars($demand['city'] . ' / ' . $demand['district']) ?>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500">
                                <?= date('d.m.Y H:i', strtotime($demand['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php
                                $statusClass = match($demand['status']) {
                                    'approved' => 'bg-green-100 text-green-700',
                                    'completed' => 'bg-blue-100 text-blue-700',
                                    'cancelled' => 'bg-red-100 text-red-700',
                                    default => 'bg-yellow-100 text-yellow-700'
                                };
                                $statusLabel = match($demand['status']) {
                                    'approved' => 'Onaylandı',
                                    'completed' => 'Tamamlandı',
                                    'cancelled' => 'İptal',
                                    'pending' => 'Beklemede',
                                    default => $demand['status']
                                };
                                ?>
                                <span class="px-2 py-1 rounded text-xs font-bold <?= $statusClass ?>"><?= $statusLabel ?></span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="demand-details.php?id=<?= $demand['id'] ?>" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium text-xs bg-indigo-50 px-3 py-1.5 rounded transition-colors">
                                    Detay
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const districtsData = <?= json_encode($districtsData) ?>;

    function updateDistricts() {
        const citySelect = document.getElementById('citySelect');
        const districtSelect = document.getElementById('districtSelect');
        const selectedCity = citySelect.value;

        // İlçeleri temizle
        districtSelect.innerHTML = '<option value="">Tüm Şehir</option>';

        if (selectedCity && districtsData[selectedCity]) {
            districtsData[selectedCity].forEach(district => {
                const option = document.createElement('option');
                option.value = district;
                option.textContent = district;
                districtSelect.appendChild(option);
            });
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>