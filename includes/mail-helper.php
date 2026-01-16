<?php
require_once __DIR__ . '/../config/db.php';

function sendEmail($to, $templateKey, $placeholders = []) {
    global $pdo;

    // Site Ayarlarını Çek
    $settings = [];
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {}

    $siteTitle = $settings['site_title'] ?? 'Hizmet Pazaryeri';
    $fromEmail = $settings['contact_email'] ?? 'noreply@' . $_SERVER['HTTP_HOST'];

    // Şablonu Çek
    $stmt = $pdo->prepare("SELECT subject, body FROM email_templates WHERE template_key = ?");
    $stmt->execute([$templateKey]);
    $template = $stmt->fetch();

    if (!$template) return false;

    $subject = $template['subject'];
    $body = $template['body'];

    // Değişkenleri Yerleştir
    foreach ($placeholders as $key => $value) {
        $subject = str_replace('{{' . $key . '}}', $value, $subject);
        $body = str_replace('{{' . $key . '}}', $value, $body);
    }

    // HTML Wrapper (Tasarım)
    $htmlContent = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f6f6f8; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
            .header { background-color: #1a2a6c; padding: 20px; text-align: center; }
            .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: bold; }
            .content { padding: 30px; color: #333333; line-height: 1.6; }
            .button { display: inline-block; background-color: #fbbd23; color: #1a2a6c; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 20px; }
            .footer { background-color: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #888888; border-top: 1px solid #eeeeee; }
            a { color: #1a2a6c; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . htmlspecialchars($siteTitle) . '</h1>
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

    // Header Bilgileri
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . $siteTitle . " <" . $fromEmail . ">" . "\r\n";
    $headers .= "Reply-To: " . $fromEmail . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Gönderim (Localhost'ta mail server kurulu değilse çalışmaz, canlıda çalışır)
    // Not: Prodüksiyon ortamında PHPMailer kullanılması önerilir.
    return @mail($to, $subject, $htmlContent, $headers);
}

// Base URL Helper
function getBaseUrl() {
    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    
    // Alt klasörde çalışıyorsa onu da alalım
    $path = dirname($_SERVER['PHP_SELF']);
    
    // Windows ters slash düzeltmesi
    $path = str_replace('\\', '/', $path);
    
    // Eğer path kök dizin değilse ve dosya adı içeriyorsa temizle
    // Basitçe domain'i döndürelim, daha güvenli.
    // Projeniz bir alt klasördeyse (örn: /hizmet-crm/) burayı manuel ayarlayabilirsiniz.
    // Örn: return "http://localhost/hizmet-crm";
    
    return $protocol . $domainName . '/hizmet-crm'; // Localhost için manuel ayar
}
?>