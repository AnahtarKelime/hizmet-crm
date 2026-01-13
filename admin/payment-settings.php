<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Ayarları Kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['settings'])) {
            foreach ($_POST['settings'] as $key => $value) {
                // Varsa güncelle, yoksa ekle (ON DUPLICATE KEY UPDATE)
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
        }

        $pdo->commit();
        $successMsg = "Ödeme ayarları başarıyla güncellendi.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Hata oluştu: " . $e->getMessage();
    }
}

// Mevcut Ayarları Çek
$settings = [];
$stmt = $pdo->query("SELECT * FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Ödeme Ayarları</h2>
            <p class="text-slate-500 text-sm">Banka havalesi ve kredi kartı ödeme yöntemlerini yapılandırın.</p>
        </div>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 space-y-8">
        
        <!-- Ödeme Ayarları -->
        <div>
            <!-- Banka Havalesi -->
            <div class="mb-6 p-6 border border-slate-200 rounded-xl bg-slate-50">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-bold text-slate-700 flex items-center gap-2">
                        <span class="material-symbols-outlined text-indigo-600">account_balance</span>
                        Banka Havalesi / EFT
                    </h4>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="settings[payment_bank_active]" value="0">
                        <input type="checkbox" name="settings[payment_bank_active]" value="1" class="sr-only peer" <?= ($settings['payment_bank_active'] ?? '0') == '1' ? 'checked' : '' ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900">Aktif</span>
                    </label>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Hesap Sahibi</label>
                        <input type="text" name="settings[payment_bank_holder]" value="<?= htmlspecialchars($settings['payment_bank_holder'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Örn: İyiteklif Bilişim A.Ş.">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">IBAN</label>
                        <input type="text" name="settings[payment_bank_iban]" value="<?= htmlspecialchars($settings['payment_bank_iban'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="TR00 0000 0000 0000 0000 0000 00">
                    </div>
                </div>
            </div>

            <!-- Kredi Kartı (Iyzico) -->
            <div class="p-6 border border-slate-200 rounded-xl bg-slate-50">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-bold text-slate-700 flex items-center gap-2">
                        <span class="material-symbols-outlined text-indigo-600">credit_card</span>
                        Kredi Kartı (Iyzico)
                    </h4>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="settings[payment_cc_active]" value="0">
                        <input type="checkbox" name="settings[payment_cc_active]" value="1" class="sr-only peer" <?= ($settings['payment_cc_active'] ?? '0') == '1' ? 'checked' : '' ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900">Aktif</span>
                    </label>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-slate-700 mb-2">Base URL (Sandbox/Production)</label>
                        <input type="text" name="settings[payment_iyzico_base_url]" value="<?= htmlspecialchars($settings['payment_iyzico_base_url'] ?? 'https://sandbox-api.iyzipay.com') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">API Key</label>
                        <input type="text" name="settings[payment_iyzico_api_key]" value="<?= htmlspecialchars($settings['payment_iyzico_api_key'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Secret Key</label>
                        <input type="text" name="settings[payment_iyzico_secret_key]" value="<?= htmlspecialchars($settings['payment_iyzico_secret_key'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-6 border-t border-slate-100 flex justify-end">
            <button type="submit" class="px-8 py-3 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">Ayarları Kaydet</button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>