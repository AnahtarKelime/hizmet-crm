<?php
require_once 'config/db.php';

$successMsg = '';
$errorMsg = '';

// Form Gönderimi İşleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $errorMsg = 'Lütfen tüm alanları doldurun.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'Geçerli bir e-posta adresi girin.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $message]);
            $successMsg = 'Mesajınız başarıyla gönderildi. En kısa sürede size dönüş yapacağız.';
        } catch (Exception $e) {
            $errorMsg = 'Mesaj gönderilirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
        }
    }
}

// İletişim Bilgilerini Çek
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('contact_email', 'contact_phone', 'contact_address')");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$pageTitle = "İletişim";
require_once 'includes/header.php';
?>

<main class="flex-1 bg-white dark:bg-background-dark">
    <!-- Header -->
    <div class="bg-slate-50 dark:bg-slate-900 py-16 border-b border-slate-200 dark:border-slate-800">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h1 class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white mb-4">Bize Ulaşın</h1>
            <p class="text-slate-500 dark:text-slate-400 text-lg">Sorularınız, önerileriniz veya destek talepleriniz için buradayız.</p>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 py-16">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- İletişim Bilgileri -->
            <div class="space-y-8">
                <div>
                    <h3 class="text-2xl font-bold text-slate-800 dark:text-white mb-6">İletişim Bilgileri</h3>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed mb-8">
                        Aşağıdaki iletişim kanallarından bize doğrudan ulaşabilir veya yandaki formu doldurarak mesaj bırakabilirsiniz. Ekibimiz en kısa sürede size geri dönüş yapacaktır.
                    </p>
                </div>

                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary shrink-0">
                            <span class="material-symbols-outlined">mail</span>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800 dark:text-white">E-posta</h4>
                            <p class="text-slate-600 dark:text-slate-400"><?= htmlspecialchars($settings['contact_email'] ?? 'destek@iyiteklif.com') ?></p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary shrink-0">
                            <span class="material-symbols-outlined">phone</span>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800 dark:text-white">Telefon</h4>
                            <p class="text-slate-600 dark:text-slate-400"><?= htmlspecialchars($settings['contact_phone'] ?? '0850 123 45 67') ?></p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary shrink-0">
                            <span class="material-symbols-outlined">location_on</span>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800 dark:text-white">Adres</h4>
                            <p class="text-slate-600 dark:text-slate-400"><?= htmlspecialchars($settings['contact_address'] ?? 'İstanbul, Türkiye') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- İletişim Formu -->
            <div class="bg-white dark:bg-slate-800 p-8 rounded-2xl shadow-lg border border-slate-100 dark:border-slate-700">
                <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-6">Mesaj Gönderin</h3>
                
                <?php if ($successMsg): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
                <?php endif; ?>
                <?php if ($errorMsg): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="text" name="name" placeholder="Adınız Soyadınız" required class="w-full rounded-xl border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent">
                        <input type="email" name="email" placeholder="E-posta Adresiniz" required class="w-full rounded-xl border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <input type="text" name="subject" placeholder="Konu" required class="w-full rounded-xl border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent">
                    <textarea name="message" rows="5" placeholder="Mesajınız..." required class="w-full rounded-xl border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    <button type="submit" class="w-full bg-primary text-white font-bold py-4 rounded-xl hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">Gönder</button>
                </form>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>