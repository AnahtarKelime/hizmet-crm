<?php
require_once __DIR__ . '/../config/db.php';

// PHPMailer Sınıflarını Dahil Et (Composer veya Manuel)
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',      // Proje içi vendor
    __DIR__ . '/../../vendor/autoload.php',   // Üst dizin vendor (XAMPP root vb.)
    $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php' // Web root vendor
];

$classLoaded = false;

foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        // Autoload.php var ama composer klasörü ve gerekli dosyalar var mı?
        $composerDir = dirname($path) . '/composer';
        if (is_dir($composerDir) && file_exists($composerDir . '/autoload_real.php')) {
            require_once $path;
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $classLoaded = true;
                break;
            }
        }
    }
}

if (!$classLoaded) {
    // Manuel Yükleme Kontrolü (Vendor içi veya Includes içi)
    $manualPaths = [
        __DIR__ . '/PHPMailer/src/PHPMailer.php',
        __DIR__ . '/phpmailer/src/PHPMailer.php',
        __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php',
        __DIR__ . '/../vendor/PHPMailer-master/src/PHPMailer.php',
        __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php',
        __DIR__ . '/../../vendor/PHPMailer/src/PHPMailer.php',
        __DIR__ . '/../../vendor/PHPMailer-master/src/PHPMailer.php',
        __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php',
        // Ekstra yollar (Kullanıcı yanlış yere yüklediyse)
        $_SERVER['DOCUMENT_ROOT'] . '/vendor/phpmailer/phpmailer/src/PHPMailer.php',
        $_SERVER['DOCUMENT_ROOT'] . '/PHPMailer/src/PHPMailer.php'
    ];

    foreach ($manualPaths as $path) {
        if (file_exists($path)) {
            $dir = dirname($path);
            require_once $dir . '/Exception.php';
            require_once $dir . '/PHPMailer.php';
            require_once $dir . '/SMTP.php';
            $classLoaded = true;
            break;
        }
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $templateKey, $placeholders = [], &$errorInfo = null) {
    global $pdo;
    $errorInfo = "Bilinmeyen hata (İşlem başlatılamadı)"; // Varsayılan hata mesajı

    // Site Ayarlarını Çek
    $settings = [];
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {}

    $siteTitle = $settings['site_title'] ?? 'Hizmet Pazaryeri';
    
    // SMTP Ayarları
    $smtpHost = $settings['smtp_host'] ?? '';
    $smtpPort = $settings['smtp_port'] ?? 587;
    $smtpUser = $settings['smtp_username'] ?? '';
    $smtpPass = $settings['smtp_password'] ?? '';
    $smtpSecure = $settings['smtp_secure'] ?? 'tls';
    $fromName = $settings['smtp_from_name'] ?? $siteTitle;
    $fromEmail = $settings['smtp_from_email'] ?? ($settings['contact_email'] ?? 'noreply@' . $_SERVER['HTTP_HOST']);
    
    // E-posta Görünüm Ayarları
    $emailHeaderBg = $settings['email_header_bg_color'] ?? '#1a2a6c';
    $emailHeaderText = $settings['email_header_text_color'] ?? '#ffffff';
    $emailLogo = $settings['email_logo'] ?? '';
    $emailPrimaryColor = $settings['theme_color_primary'] ?? '#1a2a6c';
    $emailAccentColor = $settings['theme_color_accent'] ?? '#fbbd23';

    // Şablonu Çek
    $stmt = $pdo->prepare("SELECT subject, body FROM email_templates WHERE template_key = ?");
    $stmt->execute([$templateKey]);
    $template = $stmt->fetch();

    if (!$template) {
        $errorInfo = "Şablon veritabanında bulunamadı: " . htmlspecialchars($templateKey);
        return false;
    }

    $subject = $template['subject'];
    $body = $template['body'];

    // Değişkenleri Yerleştir
    foreach ($placeholders as $key => $value) {
        // {{key}} formatı
        $subject = str_replace('{{' . $key . '}}', $value, $subject);
        $body = str_replace('{{' . $key . '}}', $value, $body);
        // {key} formatı (Alternatif)
        $subject = str_replace('{' . $key . '}', $value, $subject);
        $body = str_replace('{' . $key . '}', $value, $body);
    }

    // Header Başlığı (Test amaçlı welcome şablonu için özel başlık)
    $headerTitle = htmlspecialchars($siteTitle);
    if ($templateKey === 'welcome') {
        $headerTitle = 'İYİ TEKLİF';
        
        // Popüler Kategorileri Ekle
        try {
            $stmtCats = $pdo->query("SELECT name, slug FROM categories WHERE is_active = 1 AND is_featured = 1 ORDER BY id ASC LIMIT 5");
            $popCats = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
            
            if ($popCats) {
                $body .= '<div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                            <h3 style="color: #333; font-size: 16px; margin-bottom: 10px;">Popüler Hizmetler</h3>
                            <ul style="padding-left: 20px; color: #555;">';
                foreach ($popCats as $cat) {
                    $catLink = getBaseUrl() . '/teklif-al.php?service=' . $cat['slug'];
                    $body .= '<li style="margin-bottom: 5px;"><a href="' . $catLink . '" style="color: ' . htmlspecialchars($emailPrimaryColor) . '; text-decoration: none;">' . htmlspecialchars($cat['name']) . '</a></li>';
                }
                $body .= '</ul></div>';
            }
        } catch (Exception $e) {}

        // Hoşgeldin e-postasına giriş butonu ekle
        $homeLink = getBaseUrl();
        $body .= '<div style="text-align: center; margin-top: 30px;">
                    <a href="' . $homeLink . '" class="button">Siteyi Ziyaret Et</a>
                  </div>';
    }

    // Header İçeriği (Logo veya Metin)
    $headerContent = '<h1>' . $headerTitle . '</h1>';
    if (!empty($emailLogo)) {
        $logoUrl = getBaseUrl() . '/' . $emailLogo;
        $headerContent = '<img src="' . $logoUrl . '" alt="' . htmlspecialchars($siteTitle) . '" style="max-height: 60px; border: 0; outline: none; text-decoration: none;">';
    }

    // HTML Wrapper (Tasarım)
    $htmlContent = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f6f6f8; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
            .header { background-color: ' . htmlspecialchars($emailHeaderBg) . '; padding: 20px; text-align: center; }
            .header h1 { color: ' . htmlspecialchars($emailHeaderText) . '; margin: 0; font-size: 24px; font-weight: bold; }
            .content { padding: 30px; color: #333333; line-height: 1.6; }
            .button { display: inline-block; background-color: ' . htmlspecialchars($emailAccentColor) . '; color: ' . htmlspecialchars($emailPrimaryColor) . '; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 20px; }
            .footer { background-color: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #888888; border-top: 1px solid #eeeeee; }
            a { color: ' . htmlspecialchars($emailPrimaryColor) . '; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                ' . $headerContent . '
            </div>
            <div class="content">
                ' . $body . '
            </div>
            <div class="footer">
                &copy; ' . date('Y') . ' ' . htmlspecialchars($siteTitle) . '. Tüm hakları saklıdır.<br>
                Bu e-posta otomatik olarak gönderilmiştir.
            </div>
        </div>
    </body>
    </html>';

    // PHPMailer Kullanımı
    if (!empty($smtpHost) && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $mail = new PHPMailer(true);
        try {
            // Sunucu Ayarları
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = $smtpSecure;
            $mail->Port       = $smtpPort;
            $mail->CharSet    = 'UTF-8';

            // Alıcılar
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->addReplyTo($fromEmail, $fromName);

            // İçerik
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlContent;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<p>'], ["\n", "\n\n"], $body));

            $mail->send();
            return true;
        } catch (\Exception $e) {
            // Hata durumunda false dön
            $msg = $e->getMessage();
            $errorInfo = !empty($mail->ErrorInfo) ? $mail->ErrorInfo : (!empty($msg) ? $msg : "PHPMailer hatası oluştu ancak detay yok.");
            error_log("[Email Error] Alıcı: $to | Şablon: $templateKey | Hata: " . $errorInfo);
            return false;
        }
    } else {
        // Fallback: PHP mail() fonksiyonu
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . $fromName . " <" . $fromEmail . ">" . "\r\n";
        $headers .= "Reply-To: " . $fromEmail . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        $result = @mail($to, $subject, $htmlContent, $headers);
        if (!$result) {
            $reason = "Bilinmeyen";
            if (empty($smtpHost)) {
                $reason = "SMTP Ayarları Girilmemiş";
            } elseif (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $reason = "PHPMailer Kütüphanesi Bulunamadı. Lütfen 'vendor' klasörünü sunucuya yükleyin veya PHPMailer dosyalarını 'includes/PHPMailer' klasörüne atın.";
            }
            
            $errorInfo = "PHP mail() fonksiyonu başarısız oldu. (SMTP Kullanılamadı: $reason)";
            error_log("[Email Error] Alıcı: $to | Şablon: $templateKey | Hata: $errorInfo");
        }
        return $result;
    }
}

// Base URL Helper
function getBaseUrl() {
    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    
    // Localhost veya IP adresi ise alt klasörü ekle (Geliştirme ortamı)
    if ($domainName === 'localhost' || $domainName === '127.0.0.1') {
        return $protocol . $domainName . '/hizmet-crm';
    }
    
    // Canlı ortamda (iyiteklif.com.tr) direkt domaini döndür
    return $protocol . $domainName;
}
?>