<?php
require_once '../config/db.php';
session_start();

// Güvenlik Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Yedek İndirme İşlemi
if (isset($_POST['download_backup'])) {
    // Bellek ve süre limitini artır
    ini_set('memory_limit', '512M');
    set_time_limit(0);

    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $sqlScript = "-- Hizmet CRM Veritabanı Yedeği\n";
    $sqlScript .= "-- Tarih: " . date('d.m.Y H:i:s') . "\n\n";
    $sqlScript .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // Tablo yapısını al
        $sqlScript .= "DROP TABLE IF EXISTS `$table`;\n";
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $sqlScript .= $row[1] . ";\n\n";

        // Verileri al
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $columnCount = $stmt->columnCount();

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $sqlScript .= "INSERT INTO `$table` VALUES(";
            for ($j = 0; $j < $columnCount; $j++) {
                if (isset($row[$j])) {
                    // Tırnak işaretlerini kaçır ve yeni satırları düzelt
                    $row[$j] = addslashes($row[$j]);
                    $row[$j] = str_replace("\n", "\\n", $row[$j]);
                    $sqlScript .= '"' . $row[$j] . '"';
                } else {
                    $sqlScript .= 'NULL';
                }
                if ($j < ($columnCount - 1)) {
                    $sqlScript .= ',';
                }
            }
            $sqlScript .= ");\n";
        }
        $sqlScript .= "\n";
    }

    $sqlScript .= "\nSET FOREIGN_KEY_CHECKS=1;";

    $backupFilename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';

    // İndirme başlıkları
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header("Content-disposition: attachment; filename=\"" . $backupFilename . "\"");
    echo $sqlScript;
    exit;
}

require_once 'includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Veritabanı Yedekleme</h2>
            <p class="text-slate-500 text-sm">Sistem verilerinin yedeğini alın ve indirin.</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center">
        <div class="inline-flex items-center justify-center w-24 h-24 bg-indigo-50 text-indigo-600 rounded-full mb-6 shadow-sm">
            <span class="material-symbols-outlined text-5xl">database</span>
        </div>
        <h3 class="text-xl font-bold text-slate-800 mb-3">Tam Yedek Oluştur</h3>
        <p class="text-slate-500 mb-8 max-w-lg mx-auto leading-relaxed">
            Bu işlem veritabanındaki tüm tabloları (kullanıcılar, talepler, teklifler, ayarlar vb.) içeren bir SQL dosyası oluşturur ve bilgisayarınıza indirir. Düzenli yedek almanız önerilir.
        </p>
        
        <form method="POST">
            <button type="submit" name="download_backup" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 px-10 rounded-xl transition-all shadow-lg shadow-indigo-200 flex items-center justify-center gap-3 mx-auto hover:-translate-y-1">
                <span class="material-symbols-outlined text-2xl">download</span>
                Yedeği İndir (.sql)
            </button>
        </form>
        
        <p class="text-xs text-slate-400 mt-6">
            <span class="font-bold">Not:</span> Büyük veritabanlarında işlem birkaç saniye sürebilir.
        </p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>