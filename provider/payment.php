<?php
require_once '../config/db.php';

// Oturum kontrolü
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['provider', 'admin'])) {
    header("Location: ../login.php");
    exit;
}

$packageId = $_REQUEST['package_id'] ?? null;

if (!$packageId) {
    header("Location: buy-package.php");
    exit;
}

// Paketi Çek
$stmt = $pdo->prepare("SELECT * FROM subscription_packages WHERE id = ? AND is_active = 1");
$stmt->execute([$packageId]);
$package = $stmt->fetch();

if (!$package) {
    die("Paket bulunamadı.");
}

// Fiyat Hesaplamaları
$price = $package['price'];
$vatRate = 0.20; // %20 KDV
$vatAmount = $price * $vatRate;
$totalAmount = $price + $vatAmount;

// Site ayarlarını çek
$siteSettings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $siteSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}
$siteTitle = $siteSettings['site_title'] ?? 'iyiteklif';

// Ödeme Yöntemi Ayarları
$ccActive = ($siteSettings['payment_cc_active'] ?? '0') == '1';
$bankActive = ($siteSettings['payment_bank_active'] ?? '0') == '1';
$bankHolder = $siteSettings['payment_bank_holder'] ?? '';
$bankIban = $siteSettings['payment_bank_iban'] ?? '';
?>
<?php
$pageTitle = "Güvenli Ödeme";
$pathPrefix = '../'; // Üst dizine çıkmak için
require_once '../includes/header.php';
?>

<main class="flex-1 max-w-6xl mx-auto w-full px-6 py-10 min-h-[60vh]">
        <div class="flex items-center gap-3 mb-10 border-b border-slate-100 pb-6">
            <div class="p-2 bg-primary text-white rounded-lg">
                <span class="material-symbols-outlined text-2xl">credit_card</span>
            </div>
            <h1 class="text-3xl font-extrabold text-primary">Ödeme Bilgileri</h1>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            <!-- Sol Taraf: Ödeme Formu -->
            <div class="lg:col-span-2 space-y-8">
                
                <?php if ($ccActive && $bankActive): ?>
                    <!-- Tab Switcher -->
                    <div class="flex p-1 bg-slate-100 dark:bg-slate-800 rounded-xl mb-6">
                        <button id="tab-cc" onclick="switchTab('cc')" class="flex-1 py-3 rounded-lg text-sm font-bold transition-all bg-white dark:bg-slate-700 text-primary shadow-sm">
                            <span class="material-symbols-outlined align-middle mr-2 text-lg">credit_card</span> Kredi Kartı
                        </button>
                        <button id="tab-bank" onclick="switchTab('bank')" class="flex-1 py-3 rounded-lg text-sm font-bold transition-all text-slate-500 hover:text-slate-700 dark:text-slate-400">
                            <span class="material-symbols-outlined align-middle mr-2 text-lg">account_balance</span> Havale / EFT
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($ccActive): ?>
                <div id="content-cc" class="<?= ($ccActive && $bankActive) ? '' : '' ?>">
                    <form action="process-payment.php" method="POST" class="bg-white dark:bg-slate-900 rounded-xl p-8 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
                    <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                    <input type="hidden" name="payment_method" value="cc">
                    
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Kart Üzerindeki İsim</label>
                        <input class="form-input rounded-lg border-slate-200 dark:border-slate-700 bg-transparent py-3 px-4 w-full form-input-focus" placeholder="Ad Soyad" type="text" required/>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Kart Numarası</label>
                        <div class="relative">
                            <input class="form-input rounded-lg border-slate-200 dark:border-slate-700 bg-transparent py-3 px-4 w-full pr-12 form-input-focus" placeholder="0000 0000 0000 0000" type="text" required/>
                            <div class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"><span class="material-symbols-outlined">credit_card</span></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Son Kullanma Tarihi</label>
                            <input class="form-input rounded-lg border-slate-200 dark:border-slate-700 bg-transparent py-3 px-4 w-full form-input-focus" placeholder="AA / YY" type="text" required/>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">CVV</label>
                            <input class="form-input rounded-lg border-slate-200 dark:border-slate-700 bg-transparent py-3 px-4 w-full form-input-focus" placeholder="***" type="password" required/>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-primary hover:bg-[#f2b312] text-night-blue font-black py-4 rounded-xl shadow-lg transition-all transform hover:-translate-y-1 active:scale-95 flex items-center justify-center gap-2 text-lg mt-4">
                        Ödemeyi Tamamla
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </button>
                    </form>
                    
                    <div class="flex items-center justify-center gap-6 grayscale opacity-60 mt-6">
                        <div class="flex items-center gap-1 font-bold text-slate-500"><span class="material-symbols-outlined text-2xl">credit_card</span> MasterCard</div>
                        <div class="flex items-center gap-1 font-bold text-slate-500"><span class="material-symbols-outlined text-2xl">payments</span> VISA</div>
                        <div class="flex items-center gap-2 text-green-600 font-semibold text-sm"><span class="material-symbols-outlined">verified</span> SSL Secured</div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($bankActive): ?>
                <div id="content-bank" class="<?= ($ccActive && $bankActive) ? 'hidden' : '' ?>">
                    <div class="bg-white dark:bg-slate-900 rounded-xl p-8 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2 bg-slate-100 dark:bg-slate-800 rounded-lg">
                                <span class="material-symbols-outlined text-2xl text-slate-600 dark:text-slate-300">account_balance</span>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 dark:text-white">Banka Havalesi / EFT</h3>
                                <p class="text-xs text-slate-500">Lütfen aşağıdaki hesaba ödemeyi gerçekleştirin.</p>
                            </div>
                        </div>

                        <div class="p-5 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700 space-y-4">
                            <div>
                                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mb-1">Alıcı Adı</p>
                                <p class="font-bold text-slate-800 dark:text-white text-lg flex items-center justify-between">
                                    <?= htmlspecialchars($bankHolder) ?>
                                    <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($bankHolder) ?>')" class="text-primary hover:text-accent text-xs font-normal flex items-center gap-1"><span class="material-symbols-outlined text-sm">content_copy</span> Kopyala</button>
                                </p>
                            </div>
                            <div class="h-px bg-slate-200 dark:bg-slate-700"></div>
                            <div>
                                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mb-1">IBAN</p>
                                <p class="font-mono font-bold text-slate-800 dark:text-white text-lg break-all flex items-center justify-between gap-2">
                                    <?= htmlspecialchars($bankIban) ?>
                                    <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($bankIban) ?>')" class="text-primary hover:text-accent text-xs font-normal flex items-center gap-1 whitespace-nowrap"><span class="material-symbols-outlined text-sm">content_copy</span> Kopyala</button>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-4 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 rounded-lg text-sm">
                            <span class="material-symbols-outlined text-xl shrink-0">info</span>
                            <p>Havale açıklama kısmına <strong>Sipariş No: #<?= time() ?></strong> yazmayı unutmayınız. Ödemeniz kontrol edildikten sonra paketiniz aktifleşecektir.</p>
                        </div>

                        <form action="process-payment.php" method="POST">
                            <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                            <input type="hidden" name="payment_method" value="bank">
                            <button type="submit" class="w-full bg-accent hover:bg-yellow-400 text-primary font-black py-4 rounded-xl shadow-lg transition-all transform hover:-translate-y-1 active:scale-95 flex items-center justify-center gap-2 text-lg mt-4">
                                Ödemeyi Yaptım, Onayla
                                <span class="material-symbols-outlined">check_circle</span>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!$ccActive && !$bankActive): ?>
                    <div class="bg-red-50 border border-red-200 text-red-600 p-6 rounded-xl text-center">
                        <span class="material-symbols-outlined text-4xl mb-2">error</span>
                        <p class="font-bold">Aktif ödeme yöntemi bulunmamaktadır.</p>
                        <p class="text-sm">Lütfen site yönetimi ile iletişime geçiniz.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sağ Taraf: Sipariş Özeti -->
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xl overflow-hidden sticky top-24">
                    <div class="bg-primary p-6 text-white">
                        <h2 class="text-xl font-bold">Sipariş Özeti</h2>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($package['name']) ?></h3>
                                <p class="text-xs text-slate-500 mt-1"><?= $package['duration_days'] ?> Günlük Abonelik</p>
                            </div>
                            <span class="font-bold"><?= number_format($price, 2, ',', '.') ?> TL</span>
                        </div>
                        <div class="space-y-3 border-t border-slate-100 dark:border-slate-800 pt-6 text-sm text-slate-600 dark:text-slate-400">
                            <div class="flex justify-between"><span>Ara Toplam</span><span><?= number_format($price, 2, ',', '.') ?> TL</span></div>
                            <div class="flex justify-between"><span>KDV (%18)</span><span><?= number_format($vatAmount, 2, ',', '.') ?> TL</span></div>
                        </div>
                        <div class="flex justify-between items-center border-t border-slate-100 dark:border-slate-800 pt-6">
                            <span class="text-lg font-bold text-slate-900 dark:text-white">Toplam</span>
                            <span class="text-2xl font-black text-primary dark:text-accent"><?= number_format($totalAmount, 2, ',', '.') ?> TL</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</main>

<script>
    function switchTab(tab) {
        const contentCC = document.getElementById('content-cc');
        const contentBank = document.getElementById('content-bank');
        const tabCC = document.getElementById('tab-cc');
        const tabBank = document.getElementById('tab-bank');

        if (tab === 'cc') {
            contentCC.classList.remove('hidden');
            contentBank.classList.add('hidden');
            
            tabCC.classList.add('bg-white', 'dark:bg-slate-700', 'text-primary', 'shadow-sm');
            tabCC.classList.remove('text-slate-500', 'hover:text-slate-700', 'dark:text-slate-400');
            
            tabBank.classList.remove('bg-white', 'dark:bg-slate-700', 'text-primary', 'shadow-sm');
            tabBank.classList.add('text-slate-500', 'hover:text-slate-700', 'dark:text-slate-400');
        } else {
            contentCC.classList.add('hidden');
            contentBank.classList.remove('hidden');

            tabBank.classList.add('bg-white', 'dark:bg-slate-700', 'text-primary', 'shadow-sm');
            tabBank.classList.remove('text-slate-500', 'hover:text-slate-700', 'dark:text-slate-400');
            
            tabCC.classList.remove('bg-white', 'dark:bg-slate-700', 'text-primary', 'shadow-sm');
            tabCC.classList.add('text-slate-500', 'hover:text-slate-700', 'dark:text-slate-400');
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>