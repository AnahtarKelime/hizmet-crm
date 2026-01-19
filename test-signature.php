<?php
require_once 'includes/google-maps-signature.php';

$url = $_POST['url'] ?? '';
$secret = $_POST['secret'] ?? '';
$signedUrl = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($url && $secret) {
        try {
            $signedUrl = signGoogleMapsUrl($url, $secret);
        } catch (Exception $e) {
            $error = "Hata: " . $e->getMessage();
        }
    } else {
        $error = "Lütfen URL ve Gizli Anahtar alanlarını doldurun.";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Google Maps URL İmzalama Testi</title>
    <style>body{font-family:sans-serif;padding:20px;max-width:800px;margin:0 auto;background:#f9f9f9;}.container{background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}input{width:100%;padding:12px;margin-bottom:15px;border:1px solid #ddd;border-radius:5px;box-sizing:border-box;}button{padding:12px 24px;background:#333;color:white;border:none;border-radius:5px;cursor:pointer;font-weight:bold;}.result{background:#e8f0fe;padding:15px;word-break:break-all;margin-top:20px;border-radius:5px;color:#1967d2;}.error{background:#fce8e6;color:#c5221f;padding:15px;margin-top:20px;border-radius:5px;}</style>
</head>
<body>
<div class="container">
    <h1>URL İmzalama Aracı</h1>
    <form method="POST">
        <label>İmzalanacak URL (API Key veya Client ID dahil):</label>
        <input type="text" name="url" value="<?= htmlspecialchars($url) ?>" placeholder="https://maps.googleapis.com/maps/api/staticmap?center=..." required>
        
        <label>İmzalama Gizli Anahtarı (Signing Secret):</label>
        <input type="text" name="secret" value="<?= htmlspecialchars($secret) ?>" placeholder="vNIXE0xsc..." required>
        
        <button type="submit">İmzala</button>
    </form>

    <?php if($signedUrl): ?>
        <div class="result">
            <strong>İmzalanmış URL:</strong><br>
            <a href="<?= htmlspecialchars($signedUrl) ?>" target="_blank"><?= htmlspecialchars($signedUrl) ?></a>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
</div>
</body>
</html>