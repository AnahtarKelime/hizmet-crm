<?php
require_once '../config/db.php';
require_once 'includes/header.php';

$logs = [];

function checkAndAddColumn($pdo, $table, $column, $definition) {
    try {
        // Tablo var mı kontrol et
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() == 0) return null;

        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            return "<div class='flex items-center gap-2 text-green-600 bg-green-50 p-2 rounded border border-green-100 mb-2'><span class='material-symbols-outlined'>check_circle</span> Sütun eklendi: <strong>$table.$column</strong></div>";
        }
    } catch (PDOException $e) {
        return "<div class='flex items-center gap-2 text-red-600 bg-red-50 p-2 rounded border border-red-100 mb-2'><span class='material-symbols-outlined'>error</span> Hata ($table.$column): " . $e->getMessage() . "</div>";
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repair'])) {
    // 1. Kritik Tabloları Oluştur (Eksikse)
    $tables = [
        "CREATE TABLE IF NOT EXISTS `offers` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `demand_id` int(11) NOT NULL,
          `user_id` int(11) NOT NULL,
          `price` decimal(10,2) NOT NULL,
          `message` text NOT NULL,
          `status` enum('pending','accepted','rejected') DEFAULT 'pending',
          `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        
        "CREATE TABLE IF NOT EXISTS `lead_access_logs` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `demand_id` int(11) NOT NULL,
          `user_id` int(11) NOT NULL,
          `access_type` varchar(50) NOT NULL,
          `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_access` (`demand_id`,`user_id`,`access_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `email_templates` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `template_key` varchar(50) NOT NULL,
          `name` varchar(100) NOT NULL,
          `subject` varchar(255) NOT NULL,
          `body` longtext NOT NULL,
          `variables` varchar(255) DEFAULT NULL,
          `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `template_key` (`template_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `announcements` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `title` varchar(255) NOT NULL,
          `message` text NOT NULL,
          `target_role` enum('all','customer','provider') DEFAULT 'all',
          `is_active` tinyint(1) DEFAULT 1,
          `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // Settings tablosu için unique index (Eğer yoksa)
        // Not: Bu işlem mevcut duplicate kayıtlar varsa hata verebilir, bu yüzden IGNORE kullanıyoruz veya manuel temizlik gerekebilir.
        // Ancak basit bir ALTER TABLE komutu yeterli olacaktır.
        // "ALTER TABLE `settings` ADD UNIQUE INDEX `unique_key` (`setting_key`);"
        // Bu komutu doğrudan exec ile çalıştırmak yerine, try-catch içinde deneyeceğiz.
        // Aşağıdaki döngüde değil, özel bir blokta çalıştıracağız.
    ];

    foreach ($tables as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            $logs[] = "<div class='text-red-600'>Tablo oluşturma hatası: " . $e->getMessage() . "</div>";
        }
    }

    // Settings tablosuna unique index ekle (Duplicate key sorununu kökten çözmek için)
    try {
        // Önce index var mı kontrol etmeye gerek yok, varsa hata verir catch yakalar
        $pdo->exec("ALTER TABLE `settings` ADD UNIQUE INDEX `unique_setting_key` (`setting_key`)");
        $logs[] = "<div class='flex items-center gap-2 text-green-600 bg-green-50 p-2 rounded border border-green-100 mb-2'><span class='material-symbols-outlined'>check_circle</span> Settings tablosuna Unique Index eklendi.</div>";
    } catch (PDOException $e) {
        // Index zaten varsa veya duplicate data varsa buraya düşer, sessizce geçebiliriz veya loglayabiliriz.
        // $logs[] = "<div class='text-yellow-600'>Settings index uyarısı: " . $e->getMessage() . "</div>";
    }

    // 2. Eksik Sütunları Kontrol Et ve Ekle
    $columnsToCheck = [
        ['offers', 'is_read', 'TINYINT(1) DEFAULT 0'],
        ['offers', 'provider_read', 'TINYINT(1) DEFAULT 0'],
        ['offers', 'service_agreement', 'TEXT DEFAULT NULL'],
        ['offers', 'payment_plan', 'TEXT DEFAULT NULL'],
        ['offers', 'payment_details', 'TEXT DEFAULT NULL'],
        ['demands', 'estimated_cost', 'DECIMAL(10,2) DEFAULT 0.00'],
        ['demands', 'is_archived', 'TINYINT(1) DEFAULT 0'],
        ['users', 'google_id', 'VARCHAR(255) DEFAULT NULL'],
        ['users', 'facebook_id', 'VARCHAR(255) DEFAULT NULL'],
        ['users', 'avatar_url', 'VARCHAR(255) DEFAULT NULL'],
        ['users', 'is_verified', 'TINYINT(1) DEFAULT 0'],
        ['users', 'address_text', 'VARCHAR(255) DEFAULT NULL'],
        ['users', 'latitude', 'DECIMAL(10,8) DEFAULT NULL'],
        ['users', 'longitude', 'DECIMAL(11,8) DEFAULT NULL'],
        ['provider_details', 'application_status', "ENUM('none','pending','approved','rejected','incomplete') DEFAULT 'none'"],
        ['provider_details', 'subscription_type', "ENUM('free','premium') DEFAULT 'free'"],
        ['provider_details', 'remaining_offer_credit', "INT(11) DEFAULT 0"],
        ['provider_details', 'latitude', "DECIMAL(10,8) DEFAULT NULL"],
        ['provider_details', 'longitude', "DECIMAL(11,8) DEFAULT NULL"],
        ['provider_details', 'address_text', "VARCHAR(255) DEFAULT NULL"],
        ['categories', 'tracking_code_head', 'TEXT DEFAULT NULL'],
        ['categories', 'tracking_code_body', 'TEXT DEFAULT NULL'],
        ['contact_messages', 'is_read', 'TINYINT(1) DEFAULT 0'],
        ['reviews', 'is_approved', 'TINYINT(1) DEFAULT 0'],
        ['reviews', 'criteria_ratings', 'JSON DEFAULT NULL'],
    ];

    foreach ($columnsToCheck as $col) {
        $result = checkAndAddColumn($pdo, $col[0], $col[1], $col[2]);
        if ($result) $logs[] = $result;
    }

    if (empty($logs)) {
        $logs[] = "<div class='flex items-center gap-2 text-green-700 bg-green-50 p-4 rounded-xl border border-green-200'><span class='material-symbols-outlined'>check_circle</span> Veritabanı yapısı güncel. Eksik tablo veya sütun bulunamadı.</div>";
    }
}
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Veritabanı Onarımı</h2>
            <p class="text-slate-500 text-sm">Eksik tablo ve sütunları otomatik olarak kontrol eder ve ekler.</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8">
        <div class="mb-6">
            <p class="text-slate-600 mb-4">Bu işlem, sistemin düzgün çalışması için gerekli olan veritabanı yapısını kontrol eder. Eğer eksik bir sütun (örneğin: <code>is_read</code>) veya tablo varsa, veri kaybı olmadan bunları ekler.</p>
            
            <form method="POST">
                <button type="submit" name="repair" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-lg shadow-indigo-200 flex items-center gap-2">
                    <span class="material-symbols-outlined">build</span>
                    Veritabanını Kontrol Et ve Onar
                </button>
            </form>
        </div>

        <?php if (!empty($logs)): ?>
            <div class="mt-6 border-t border-slate-100 pt-6">
                <h3 class="font-bold text-slate-800 mb-4">İşlem Sonuçları</h3>
                <div class="space-y-2">
                    <?php foreach ($logs as $log) echo $log; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>