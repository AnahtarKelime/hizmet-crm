<?php
require_once 'config/db.php';

// Ayarları veritabanından çekmeye çalış, yoksa varsayılanı kullan
$apiKey = '';

try {
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'google_maps_api_key'");
    $dbKey = $stmt->fetchColumn();
    if (!empty($dbKey)) {
        $apiKey = trim($dbKey);
    }
} catch (Exception $e) {
    // DB hatası olursa varsayılan kalır
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Maps API Test</title>
    <style>
        body { font-family: sans-serif; padding: 30px; max-width: 800px; margin: 0 auto; background: #f9f9f9; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; color: #333; }
        input[type="text"] { width: 100%; padding: 15px; font-size: 16px; border: 2px solid #ddd; border-radius: 5px; box-sizing: border-box; margin-bottom: 20px; }
        input[type="text"]:focus { border-color: #4285f4; outline: none; }
        #log { background: #333; color: #0f0; padding: 15px; border-radius: 5px; font-family: monospace; height: 300px; overflow-y: auto; white-space: pre-wrap; font-size: 13px; }
        .status { padding: 10px; margin-bottom: 20px; border-radius: 5px; font-weight: bold; }
        .status.info { background: #e8f0fe; color: #1967d2; }
        .status.error { background: #fce8e6; color: #c5221f; }
        
        /* Google Autocomplete Dropdown Fix */
        .pac-container { z-index: 999999 !important; }
    </style>
</head>
<body>

<div class="container">
    <h1>Google Maps API Test Aracı</h1>
    
    <div class="status info">
        Kullanılan API Anahtarı: 
        <span style="font-family: monospace; background: rgba(0,0,0,0.05); padding: 2px 5px; rounded: 3px;">
            <?= htmlspecialchars(substr($apiKey, 0, 5) . '...' . substr($apiKey, -5)) ?>
        </span>
    </div>

    <label for="autocomplete">Adres Arama Testi:</label>
    <input id="autocomplete" type="text" placeholder="Bir adres yazmaya başlayın (Örn: İstiklal Caddesi)...">
    <button onclick="testPredictions()" style="padding: 10px 20px; background: #333; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">API Durumunu Detaylı Kontrol Et</button>

    <h3>Hata Ayıklama Konsolu (Log)</h3>
    <div id="log">Sayfa yükleniyor...</div>
</div>

<script>
    function log(message, type = 'info') {
        const logDiv = document.getElementById('log');
        const time = new Date().toLocaleTimeString();
        const color = type === 'error' ? '#ff6b6b' : (type === 'success' ? '#69f0ae' : '#0f0');
        logDiv.innerHTML += `<div style="color:${color}">[${time}] ${message}</div>`;
        logDiv.scrollTop = logDiv.scrollHeight;
        console.log(`[${type.toUpperCase()}] ${message}`);
    }

    // Global Hata Yakalayıcı (API Yükleme Hataları için)
    window.gm_authFailure = function() {
        log("KRİTİK HATA: Google Maps API kimlik doğrulaması başarısız oldu (gm_authFailure).", 'error');
        log("OLASI NEDENLER:", 'error');
        log("1. API Anahtarı geçersiz veya silinmiş.", 'error');
        log("2. Google Cloud Console'da 'Maps JavaScript API' ve 'Places API' etkinleştirilmemiş.", 'error');
        log("3. API Anahtarı kısıtlamaları (HTTP Referrer) bu domaini engelliyor.", 'error');
        log("4. Projeye bağlı bir faturalandırma hesabı yok (Billing Account).", 'error');
    };

    window.initAutocomplete = function() {
        log("Google Maps API başarıyla yüklendi (Callback tetiklendi).", 'success');
        
        const input = document.getElementById("autocomplete");
        const options = {
            componentRestrictions: { country: "tr" },
            fields: ["formatted_address", "geometry", "name"],
            types: ["geocode"] 
        };

        try {
            const autocomplete = new google.maps.places.Autocomplete(input, options);
            log("Autocomplete nesnesi başlatıldı. Input alanına yazmayı deneyin.", 'success');

            autocomplete.addListener("place_changed", () => {
                const place = autocomplete.getPlace();
                if (!place.geometry) {
                    log(`UYARI: '${input.value}' için detay bulunamadı veya listeden seçilmedi.`, 'error');
                    return;
                }
                log(`BAŞARILI: Yer seçildi -> ${place.name || place.formatted_address}`, 'success');
                log(`Koordinatlar: ${place.geometry.location.lat()}, ${place.geometry.location.lng()}`);
            });

        } catch (e) {
            log("HATA: Autocomplete başlatılırken istisna oluştu: " + e.message, 'error');
        }
    };

    window.testPredictions = function() {
        log("API durumu kontrol ediliyor...", 'info');
        const service = new google.maps.places.AutocompleteService();
        service.getPlacePredictions({ input: 'istanbul', componentRestrictions: { country: 'tr' } }, (predictions, status) => {
            if (status !== google.maps.places.PlacesServiceStatus.OK && status !== google.maps.places.PlacesServiceStatus.ZERO_RESULTS) {
                 log(`API HATASI: Tahminler alınamadı. Durum Kodu: ${status}`, 'error');
                 if (status === 'REQUEST_DENIED') log('-> SEBEP: API Anahtarı geçersiz, yetkiler eksik veya faturalandırma hesabı bağlı değil.', 'error');
                 if (status === 'OVER_QUERY_LIMIT') log('-> SEBEP: Kota aşıldı veya faturalandırma etkin değil.', 'error');
                 if (status === 'i') log('-> SEBEP: Referrer kısıtlaması hatası olabilir.', 'error');
            } else {
                 log(`API BAŞARILI: ${predictions ? predictions.length : 0} tahmin alındı. API çalışıyor.`, 'success');
            }
        });
    };
</script>

<script 
    src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($apiKey) ?>&libraries=places&callback=initAutocomplete" 
    async 
    defer
    onerror="log('HATA: Google Maps script dosyası yüklenemedi. İnternet bağlantınızı veya reklam engelleyiciyi kontrol edin.', 'error')"
></script>

</body>
</html>