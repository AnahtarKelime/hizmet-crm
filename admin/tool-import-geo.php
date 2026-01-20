<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Türkçe karakter uyumlu slug fonksiyonu
if (!function_exists('makeSlug')) {
    function makeSlug($text) {
        $find = ['Ç', 'Ş', 'Ğ', 'Ü', 'İ', 'Ö', 'ç', 'ş', 'ğ', 'ü', 'ö', 'ı', '+', '#'];
        $replace = ['c', 's', 'g', 'u', 'i', 'o', 'c', 's', 'g', 'u', 'o', 'i', 'plus', 'sharp'];
        $text = strtolower(str_replace($find, $replace, $text));
        $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        return trim($text, '-');
    }
}

$message = '';

// XML Dosyası Yükleme ve İşleme
if (isset($_POST['upload_xml']) && isset($_FILES['xml_file'])) {
    $file = $_FILES['xml_file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

    if ($ext !== 'xml') {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6'>Sadece .xml dosyaları yüklenebilir.</div>";
    } else {
        try {
            // Büyük dosyalar için limitleri artır
            ini_set('memory_limit', '2048M');
            set_time_limit(0);

            $xml = simplexml_load_file($file['tmp_name']);
            
            if ($xml === false) {
                 throw new Exception("XML dosyası okunamadı veya geçersiz format.");
            }

            $pdo->beginTransaction();
            
            // Mevcut verileri temizle (Temiz kurulum)
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo->exec("TRUNCATE TABLE locations");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            $stmt = $pdo->prepare("INSERT IGNORE INTO locations (city, district, neighborhood, slug) VALUES (?, ?, ?, ?)");
            
            $count = 0;
            // XML Yapısı: row(İl) -> towns(İlçe) -> districts(Semt) -> quarters(Mahalle)
            foreach ($xml->row as $cityRow) {
                $city = (string)$cityRow->name;
                
                if (isset($cityRow->towns)) {
                    foreach ($cityRow->towns as $townRow) {
                        $district = (string)$townRow->name;
                        
                        if (isset($townRow->districts)) {
                            foreach ($townRow->districts as $districtRow) {
                                // Semt ismini atlıyoruz, doğrudan mahalleye iniyoruz
                                if (isset($districtRow->quarters)) {
                                    foreach ($districtRow->quarters as $quarterRow) {
                                        $neighborhood = (string)$quarterRow->name;
                                        $slug = makeSlug($city . '-' . $district . '-' . $neighborhood);
                                        
                                        $stmt->execute([$city, $district, $neighborhood, $slug]);
                                        $count++;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $pdo->commit();
            $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6'><strong class='font-bold'>İşlem Başarılı!</strong> <span class='block sm:inline'>XML dosyasından toplam $count adet lokasyon başarıyla aktarıldı.</span></div>";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6'>XML İşleme Hatası: " . $e->getMessage() . "</div>";
        }
    }
}

// SQL Dosyası Yükleme ve Çalıştırma
if (isset($_POST['upload_sql']) && isset($_FILES['sql_file'])) {
    $file = $_FILES['sql_file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

    if ($ext !== 'sql') {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6'>Sadece .sql dosyaları yüklenebilir.</div>";
    } else {
        try {
            $sqlContent = file_get_contents($file['tmp_name']);
            
            // Yorumları temizle ve sorguları ayır
            $lines = explode("\n", $sqlContent);
            $cleanSql = "";
            foreach ($lines as $line) {
                if (substr(trim($line), 0, 2) != '--' && trim($line) != '') {
                    $cleanSql .= $line . "\n";
                }
            }

            $queries = explode(";", $cleanSql);
            
            $pdo->beginTransaction();
            
            // Çakışmaları önlemek için önce tabloları temizle
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo->exec("DROP TABLE IF EXISTS `neighbourhood`");
            $pdo->exec("DROP TABLE IF EXISTS `district`");
            $pdo->exec("DROP TABLE IF EXISTS `city`");
            $pdo->exec("DROP TABLE IF EXISTS `country`");

            foreach ($queries as $index => $query) {
                $query = trim($query);
                if ($query != "") {
                    // PostgreSQL'e özgü veya MySQL'de hata verebilecek komutları atla
                    if (
                        stripos($query, 'SET ') === 0 ||
                        stripos($query, 'SELECT pg_catalog') === 0 ||
                        stripos($query, 'CREATE EXTENSION') === 0 ||
                        stripos($query, 'COMMENT ON') === 0 ||
                        stripos($query, 'BEGIN') === 0 ||
                        stripos($query, 'COMMIT') === 0 ||
                        stripos($query, 'ROLLBACK') === 0
                    ) {
                        continue;
                    }

                    // PostgreSQL -> MySQL Dönüşümü
                    $query = str_replace(['"public".', 'public.'], '', $query); // Şema ön ekini kaldır
                    $query = str_replace('"', '`', $query); // Çift tırnakları backtick yap
                    
                    // Tip ve Syntax dönüşümleri
                    $query = str_ireplace('character varying', 'varchar', $query);
                    $query = str_ireplace(' USING btree ', ' ', $query); // Index tipini temizle
                    $query = str_ireplace(' true', ' 1', $query); // Boolean true -> 1
                    $query = str_ireplace(' false', ' 0', $query); // Boolean false -> 0
                    
                    try {
                        $pdo->exec($query);
                    } catch (PDOException $e) {
                        // Hata durumunda işlemi durdur ve detay ver
                        throw new Exception("Sorgu Hatası (Sıra: $index): " . $e->getMessage() . " <br><strong>Hatalı Sorgu Özeti:</strong> <pre class='text-xs mt-2 bg-red-50 p-2 rounded'>" . htmlspecialchars(substr($query, 0, 300)) . "...</pre>");
                    }
                }
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $pdo->commit();
            
            $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6'>SQL dosyası başarıyla yüklendi ve çalıştırıldı. Şimdi 2. Adım ile verileri aktarabilirsiniz.</div>";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6'>SQL Hatası: " . $e->getMessage() . "</div>";
        }
    }
}

if (isset($_POST['run_migration'])) {
    try {
        // Kaynak tabloların varlığını kontrol et
        $check = $pdo->query("SHOW TABLES LIKE 'city'");
        if ($check->rowCount() == 0) {
            throw new Exception("Kaynak tablolar (city, district, neighbourhood) bulunamadı. Lütfen önce SQL verisini veritabanına yükleyin.");
        }

        // İşlem uzun sürebilir
        ini_set('memory_limit', '512M');
        set_time_limit(600); // 10 dakika

        $pdo->beginTransaction();

        // Mevcut locations tablosunu temizle (Temiz kurulum için)
        // Not: Eğer mevcut verileri korumak istiyorsanız bu satırı kaldırın.
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("TRUNCATE TABLE locations");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        // Verileri birleştirerek çek
        $sql = "
            SELECT 
                c.name as city, 
                d.name as district, 
                n.name as neighborhood 
            FROM neighbourhood n
            JOIN district d ON n.district_id = d.id
            JOIN city c ON d.city_id = c.id
        ";
        
        $stmt = $pdo->query($sql);
        $insertStmt = $pdo->prepare("INSERT INTO locations (city, district, neighborhood, slug) VALUES (?, ?, ?, ?)");
        
        $count = 0;
        while ($row = $stmt->fetch()) {
            // Slug oluştur: izmir-karsiyaka-bostanli-mah
            $slug = makeSlug($row['city'] . '-' . $row['district'] . '-' . $row['neighborhood']);
            $insertStmt->execute([$row['city'], $row['district'], $row['neighborhood'], $slug]);
            $count++;
            
            // Her 1000 kayıtta bir commit yapıp transaction'ı yenileyebiliriz ama
            // veri bütünlüğü için tek transaction daha güvenlidir.
        }

        $pdo->commit();
        $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6'>
                        <strong class='font-bold'>İşlem Başarılı!</strong>
                        <span class='block sm:inline'>Toplam $count adet lokasyon sisteme aktarıldı.</span>
                    </div>";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6'>
                        <strong class='font-bold'>Hata Oluştu:</strong>
                        <span class='block sm:inline'>" . $e->getMessage() . "</span>
                    </div>";
    }
}
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Lokasyon Verisi Aktarımı</h2>
            <p class="text-slate-500 text-sm">Harici tablolardan (city, district, neighbourhood) sistem tablosuna (locations) veri aktarımı.</p>
        </div>
    </div>

    <?= $message ?>

    <!-- XML İçe Aktarma (Hızlı Yöntem) -->
    <div class="bg-blue-50 rounded-xl shadow-sm border border-blue-200 p-8 mb-8">
        <h3 class="font-bold text-blue-800 text-lg mb-4">XML ile Hızlı İçe Aktarma</h3>
        <p class="text-blue-600 mb-6 text-sm">Elinizdeki <code>.xml</code> formatındaki il/ilçe/mahalle verisini doğrudan sisteme aktarmak için burayı kullanın. Bu işlem <code>locations</code> tablosunu temizleyip yeniden doldurur.</p>
        
        <form method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-4 items-end">
            <div class="flex-1 w-full">
                <label class="block text-sm font-bold text-blue-700 mb-2">XML Dosyası Seçin</label>
                <input type="file" name="xml_file" accept=".xml" required class="block w-full text-sm text-blue-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200 border border-blue-300 rounded-lg p-1">
            </div>
            <button type="submit" name="upload_xml" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined">code</span> XML Yükle ve İşle
            </button>
        </form>
    </div>

    <!-- Adım 1: SQL Yükleme -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 mb-8">
        <h3 class="font-bold text-slate-800 text-lg mb-4">1. Adım: Kaynak SQL Dosyasını Yükle</h3>
        <p class="text-slate-600 mb-6 text-sm">Elinizdeki il, ilçe ve mahalle verilerini içeren <code>.sql</code> dosyasını buradan yükleyin. Bu işlem veritabanında gerekli tabloları (city, district, neighbourhood) oluşturup verileri dolduracaktır.</p>
        
        <form method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-4 items-end">
            <div class="flex-1 w-full">
                <label class="block text-sm font-bold text-slate-700 mb-2">SQL Dosyası Seçin</label>
                <input type="file" name="sql_file" accept=".sql" required class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-slate-300 rounded-lg p-1">
            </div>
            <button type="submit" name="upload_sql" class="w-full sm:w-auto bg-slate-800 hover:bg-slate-900 text-white font-bold py-3 px-6 rounded-lg transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined">upload_file</span> Yükle ve Çalıştır
            </button>
        </form>
    </div>

    <!-- Adım 2: Veri Aktarımı -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8">
        <div class="flex items-start gap-4 mb-6">
            <div>
                <h3 class="font-bold text-slate-800 text-lg">2. Adım: Verileri Sisteme Aktar</h3>
                <p class="text-slate-600 mt-1 text-sm">Bu işlem, yüklediğiniz kaynak tablolardaki (city, district, neighbourhood) verileri sistemin kullandığı <code>locations</code> tablosuna dönüştürerek aktarır. <strong class="text-red-500">Mevcut lokasyon verileri silinecektir.</strong></p>
            </div>
        </div>

        <form method="POST">
            <button type="submit" name="run_migration" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 rounded-xl transition-all shadow-lg shadow-indigo-200 flex items-center justify-center gap-2" onclick="return confirm('Mevcut lokasyon verileri silinecek ve yeniden yüklenecek. Onaylıyor musunuz?')">
                <span class="material-symbols-outlined">sync</span>
                Verileri Aktar ve Eşitle
            </button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>