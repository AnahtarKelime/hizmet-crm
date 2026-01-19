<?php
ob_start();
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Ayarları Kaydet
if (isset($_GET['clear_maps_keys'])) {
    // Google Maps anahtarlarını veritabanından sil
    $pdo->exec("UPDATE settings SET setting_value = '' WHERE setting_key IN ('google_maps_api_key', 'google_maps_geo_api_key')");
    header("Location: social-login-settings.php?success=1");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['settings'])) {
            // Checkbox için özel kontrol
            $_POST['settings']['google_login_active'] = isset($_POST['settings']['google_login_active']) ? '1' : '0';
            $_POST['settings']['facebook_login_active'] = isset($_POST['settings']['facebook_login_active']) ? '1' : '0';

            foreach ($_POST['settings'] as $key => $value) {
                $value = trim($value);
                // Eski kayıtları sil (Duplicate key veya güncelleme sorunlarını önlemek için)
                $delStmt = $pdo->prepare("DELETE FROM settings WHERE setting_key = ?");
                $delStmt->execute([$key]);
                
                // Yeni değeri ekle
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
        }

        $pdo->commit();
        header("Location: social-login-settings.php?success=1");
        exit;
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
if (isset($_GET['success'])) {
    $successMsg = "İşlem başarıyla tamamlandı.";
}

require_once 'includes/header.php';

$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$rootHost = str_replace('www.', '', $host);
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Google & Sosyal Giriş Ayarları</h2>
            <p class="text-slate-500 text-sm">Google Maps API ve sosyal medya giriş entegrasyonlarını yönetin.</p>
        </div>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 space-y-8">
        
        <!-- Google Maps API -->
        <div class="p-6 border border-slate-200 rounded-xl bg-slate-50">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-bold text-slate-700 flex items-center gap-2">
                    <span class="material-symbols-outlined text-red-500">map</span>
                    Google Maps API
                </h4>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Google Maps & Places API Anahtarı</label>
                <div class="flex gap-2">
                    <input type="text" id="google_maps_api_key" name="settings[google_maps_api_key]" value="<?= htmlspecialchars($settings['google_maps_api_key'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="AIza..." autocomplete="off">
                    <button type="button" onclick="testGoogleMapsApiKey()" class="bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-4 py-2 rounded-lg font-bold text-sm whitespace-nowrap transition-colors flex items-center gap-2">
                        <span class="material-symbols-outlined text-lg">check_circle</span> Test Et
                    </button>
                </div>
                <div class="mt-1 flex justify-between items-center">
                    <p class="text-xs text-slate-500">Harita gösterimi (Maps JavaScript API) ve konum otomatik tamamlama (Places API) için kullanılan ana anahtardır.</p>
                    <a href="?clear_maps_keys=1" onclick="return confirm('Harita API anahtarlarını veritabanından silmek istediğinize emin misiniz?')" class="text-xs text-red-500 hover:underline font-bold">Anahtarları Temizle</a>
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-bold text-slate-700 mb-2">Geocoding API Anahtarı (Opsiyonel)</label>
                <div class="flex gap-2">
                    <input type="text" id="google_maps_geo_api_key" name="settings[google_maps_geo_api_key]" value="<?= htmlspecialchars($settings['google_maps_geo_api_key'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="AIza... (Boş bırakılırsa üstteki anahtar kullanılır)" autocomplete="off">
                    <button type="button" onclick="testGeocodingApiKey()" class="bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-4 py-2 rounded-lg font-bold text-sm whitespace-nowrap transition-colors flex items-center gap-2">
                        <span class="material-symbols-outlined text-lg">check_circle</span> Test Et
                    </button>
                </div>
                <p class="text-xs text-slate-500 mt-1">Otomatik konum bulma özelliği için kullanılır. Ayrı bir kota/anahtar kullanmak isterseniz doldurun.</p>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-bold text-slate-700 mb-2">URL İmzalama Gizli Anahtarı (Signing Secret)</label>
                <input type="text" name="settings[google_maps_signing_secret]" value="<?= htmlspecialchars($settings['google_maps_signing_secret'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="vNIXE0xsc..." autocomplete="off">
                <p class="text-xs text-slate-500 mt-1">Static Maps API gibi servisler için URL imzalama gerekiyorsa buraya giriniz (Opsiyonel).</p>
            </div>
        </div>

        <div>
            <div class="p-6 border border-slate-200 rounded-xl bg-slate-50">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-bold text-slate-700 flex items-center gap-2">
                        <svg class="w-6 h-6" viewBox="0 0 24 24"><path fill="currentColor" d="M21.35,11.1H12.18V13.83H18.69C18.36,17.64 15.19,19.27 12.19,19.27C8.36,19.27 5,16.25 5,12C5,7.9 8.2,5 12,5C14.6,5 16.1,6.05 17.1,6.95L19.25,4.85C17.1,2.95 14.8,2 12,2C6.48,2 2,6.48 2,12C2,17.52 6.48,22 12,22C17.52,22 21.7,17.52 21.7,12.33C21.7,11.87 21.5,11.35 21.35,11.1Z"></path></svg>
                        Google ile Giriş
                    </h4>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="settings[google_login_active]" value="1" class="sr-only peer" <?= ($settings['google_login_active'] ?? '0') == '1' ? 'checked' : '' ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900">Aktif</span>
                    </label>
                </div>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Google Client ID</label>
                        <input type="text" name="settings[google_client_id]" value="<?= htmlspecialchars($settings['google_client_id'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Google API Console'dan alınan Client ID" autocomplete="off">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Google Client Secret</label>
                        <input type="text" name="settings[google_client_secret]" value="<?= htmlspecialchars($settings['google_client_secret'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Google API Console'dan alınan Client Secret" autocomplete="off">
                    </div>
                    <div class="text-xs text-slate-500 bg-indigo-50 p-3 rounded-lg">
                        <strong>Yetkilendirilmiş yönlendirme URI'si:</strong> 
                        <br>Aşağıdaki adreslerin <strong>hepsini</strong> Google API Console'a ekleyiniz:
                        <ul class="list-disc list-inside mt-1 space-y-1">
                            <li><code class="font-mono bg-indigo-100 p-1 rounded"><?= $protocol . $rootHost . '/google-callback.php' ?></code></li>
                            <li><code class="font-mono bg-indigo-100 p-1 rounded"><?= $protocol . 'www.' . $rootHost . '/google-callback.php' ?></code></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="p-6 border border-slate-200 rounded-xl bg-slate-50">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-bold text-slate-700 flex items-center gap-2">
                        <svg class="w-6 h-6 text-[#1877F2]" viewBox="0 0 24 24"><path fill="currentColor" d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg>
                        Facebook ile Giriş
                    </h4>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="settings[facebook_login_active]" value="1" class="sr-only peer" <?= ($settings['facebook_login_active'] ?? '0') == '1' ? 'checked' : '' ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900">Aktif</span>
                    </label>
                </div>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Facebook App ID</label>
                        <input type="text" name="settings[facebook_app_id]" value="<?= htmlspecialchars($settings['facebook_app_id'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Facebook for Developers'dan alınan App ID" autocomplete="off">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Facebook App Secret</label>
                        <input type="text" name="settings[facebook_app_secret]" value="<?= htmlspecialchars($settings['facebook_app_secret'] ?? '') ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Facebook for Developers'dan alınan App Secret" autocomplete="off">
                    </div>
                    <div class="text-xs text-slate-500 bg-indigo-50 p-3 rounded-lg">
                        <strong>Geçerli OAuth Yönlendirme URI'si:</strong> 
                        <br>Aşağıdaki adreslerin <strong>hepsini</strong> Facebook Uygulama Ayarlarına ekleyiniz:
                        <ul class="list-disc list-inside mt-1 space-y-1">
                            <li><code class="font-mono bg-indigo-100 p-1 rounded"><?= $protocol . $rootHost . '/facebook-callback.php' ?></code></li>
                            <li><code class="font-mono bg-indigo-100 p-1 rounded"><?= $protocol . 'www.' . $rootHost . '/facebook-callback.php' ?></code></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-6 border-t border-slate-100 flex justify-end">
            <button type="submit" class="px-8 py-3 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">Ayarları Kaydet</button>
        </div>
    </form>
</div>

<script>
function testGoogleMapsApiKey() {
    const apiKey = document.getElementById('google_maps_api_key').value.trim();
    if (!apiKey) {
        alert('Lütfen önce bir API anahtarı girin.');
        return;
    }

    const scriptId = 'google-maps-test-script';
    const oldScript = document.getElementById(scriptId);
    if (oldScript) oldScript.remove();

    // Global hata yakalayıcı
    window.gm_authFailure = function() {
        alert('HATA: API Anahtarı geçersiz veya yetkilendirme hatası (Referrer kısıtlaması veya Faturalandırma hesabı eksik olabilir).');
    };

    const script = document.createElement('script');
    script.id = scriptId;
    script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&callback=googleMapsTestSuccess`;
    script.async = true;
    script.defer = true;
    
    window.googleMapsTestSuccess = function() {
        alert('BAŞARILI: Google Maps API bağlantısı sağlandı.');
    };

    script.onerror = function() {
        alert('HATA: Script yüklenemedi. İnternet bağlantınızı kontrol edin.');
    };

    document.body.appendChild(script);
}

function testGeocodingApiKey() {
    let apiKey = document.getElementById('google_maps_geo_api_key').value.trim();
    const mainApiKey = document.getElementById('google_maps_api_key').value.trim();
    
    if (!apiKey) {
        if (mainApiKey) {
            if (!confirm('Geocoding anahtarı boş. Ana API anahtarı (' + mainApiKey.substring(0, 5) + '...) kullanılarak test edilsin mi?')) {
                return;
            }
            apiKey = mainApiKey;
        } else {
            alert('Lütfen test etmek için bir API anahtarı girin.');
            return;
        }
    }

    // Test URL (Istanbul coordinates)
    const url = `https://maps.googleapis.com/maps/api/geocode/json?latlng=41.0082,28.9784&key=${apiKey}`;

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'OK') {
                alert('BAŞARILI: Geocoding API bağlantısı sağlandı.\nSonuç: ' + (data.results[0] ? data.results[0].formatted_address : 'Adres bulundu'));
            } else {
                let errorMsg = 'HATA: API yanıtı başarısız.\nDurum: ' + data.status + '\nMesaj: ' + (data.error_message || 'Detay yok');
                if (data.status === 'REQUEST_DENIED' && (data.error_message && data.error_message.includes('not activated'))) {
                    errorMsg += '\n\nÇÖZÜM: Google Cloud Console > APIs & Services > Library menüsünden "Geocoding API" servisini bulup ETKİNLEŞTİRİN (Enable).';
                }
                alert(errorMsg);
            }
        })
        .catch(error => {
            alert('HATA: İstek gönderilemedi.\n' + error);
        });
}
</script>

<?php require_once 'includes/footer.php'; ?>