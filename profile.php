<?php
require_once 'config/db.php';
$pageTitle = "Profilim";
require_once 'includes/header.php';

if (!$isLoggedIn) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Kullanıcı bilgilerini çek
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Eğer provider ise, detayları ve aboneliği çek
$providerDetails = null;
if ($user['role'] === 'provider') {
    $stmt = $pdo->prepare("SELECT * FROM provider_details WHERE user_id = ?");
    $stmt->execute([$userId]);
    $providerDetails = $stmt->fetch();
}
?>

<main class="max-w-4xl mx-auto px-4 py-12 min-h-[60vh]">
    <div class="flex items-center gap-6 mb-10">
        <?php if (!empty($user['avatar_url'])): ?>
            <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="Profil Resmi" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-md">
        <?php else: ?>
            <div class="w-24 h-24 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 font-black text-4xl border-4 border-white shadow-md">
                <?= mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1) ?>
            </div>
        <?php endif; ?>
        <div>
            <h1 class="text-3xl font-black text-slate-800"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h1>
            <p class="text-slate-500"><?= htmlspecialchars($user['email']) ?></p>
        </div>
    </div>

    <?php if ($providerDetails): ?>
    <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-200 mb-8">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Hizmet Veren Bilgileri</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                <p class="text-sm text-slate-500 font-bold mb-1">Abonelik Paketi</p>
                <p class="text-lg font-bold text-primary capitalize flex items-center gap-2">
                    <?php if($providerDetails['subscription_type'] === 'premium'): ?>
                        <span class="material-symbols-outlined text-accent">diamond</span> Premium
                    <?php else: ?>
                        <span class="material-symbols-outlined text-slate-400">workspace_premium</span> Ücretsiz
                    <?php endif; ?>
                </p>
            </div>
            <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                <p class="text-sm text-slate-500 font-bold mb-1">Abonelik Bitiş Tarihi</p>
                <p class="text-lg font-bold text-slate-700">
                    <?= $providerDetails['subscription_ends_at'] ? date('d.m.Y', strtotime($providerDetails['subscription_ends_at'])) : 'Yok' ?>
                </p>
            </div>
            <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                <p class="text-sm text-slate-500 font-bold mb-1">Kalan Teklif Kredisi</p>
                <p class="text-lg font-bold text-slate-700">
                    <?= $providerDetails['remaining_offer_credit'] == -1 ? 'Sınırsız' : $providerDetails['remaining_offer_credit'] ?>
                </p>
            </div>
        </div>
        <div class="mt-6 flex gap-4">
            <a href="provider/leads.php" class="px-6 py-3 bg-primary text-white rounded-xl font-bold">İş Fırsatlarını Gör</a>
            <a href="provider/buy-package.php" class="px-6 py-3 bg-slate-100 text-slate-700 rounded-xl font-bold">Paket Yükselt</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="text-center">
        <a href="logout.php" class="text-red-500 hover:text-red-700 font-bold">Çıkış Yap</a>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>