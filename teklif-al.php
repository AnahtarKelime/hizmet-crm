<?php
require_once 'config/db.php';

$serviceSlug = $_GET['service'] ?? '';
$locationSlug = $_GET['location'] ?? '';

// Google Maps Parametreleri
$gAddress = $_GET['address'] ?? ($_COOKIE['g_address'] ?? '');
$gLat = $_GET['lat'] ?? ($_COOKIE['g_lat'] ?? '');
$gLng = $_GET['lng'] ?? ($_COOKIE['g_lng'] ?? '');
$gCity = $_GET['city'] ?? ($_COOKIE['g_city'] ?? '');
$gDistrict = $_GET['district'] ?? ($_COOKIE['g_district'] ?? '');

// Eğer URL'den konum gelmediyse ve kullanıcı giriş yapmışsa, kayıtlı adresini kullan
if (empty($gAddress) && empty($locationSlug) && isset($_SESSION['user_id'])) {
    $stmtUserLoc = $pdo->prepare("SELECT address_text, latitude, longitude, city, district FROM users WHERE id = ?");
    $stmtUserLoc->execute([$_SESSION['user_id']]);
    $userLoc = $stmtUserLoc->fetch();

    if ($userLoc && !empty($userLoc['address_text'])) {
        $gAddress = $userLoc['address_text'];
        $gLat = $userLoc['latitude'];
        $gLng = $userLoc['longitude'];
        $gCity = $userLoc['city'];
        $gDistrict = $userLoc['district'];
    }
}

// Kategori kontrolü
$category = null;
$location = null;
$questions = [];

// Lokasyon kontrolü ve varsayılan atama
if (empty($locationSlug)) {
    // Eğer Google verisi yoksa varsayılan bir slug ata
    // Varsayılan lokasyon ataması kaldırıldı.
}

if ($locationSlug) {
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE slug = ?");
    $stmt->execute([$locationSlug]);
    $location = $stmt->fetch();
    
    if (!$location) {
        $stmt = $pdo->query("SELECT * FROM locations LIMIT 1");
        $location = $stmt->fetch();
        if ($location) {
            $locationSlug = $location['slug'];
        }
    }
}

if ($serviceSlug) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1");
    $stmt->execute([$serviceSlug]);
    $category = $stmt->fetch();

    if ($category) {
        $stmt = $pdo->prepare("SELECT * FROM category_questions WHERE category_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$category['id']]);
        $questions = $stmt->fetchAll();
    }
}

$displayLocation = $gAddress ? $gAddress : ($location ? $location['district'] . ' / ' . $location['city'] : '');
if ($gDistrict && $gCity) {
    $displayLocation = $gDistrict . ' / ' . $gCity;
}

$pageTitle = ($category) ? ($displayLocation ? $displayLocation . ' ' : '') . $category['name'] . " Talebi Oluştur" : "Talep Oluştur";

require_once 'includes/header.php';

?>

<style>
@keyframes progress-shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
.animate-progress-shimmer {
    animation: progress-shimmer 2s infinite linear;
}
@keyframes progress-stripes {
    from { background-position: 1rem 0; }
    to { background-position: 0 0; }
}
.animate-progress-stripes {
    animation: progress-stripes 1s linear infinite;
}
</style>

<main class="max-w-3xl mx-auto px-4 py-12 min-h-[60vh]">
    <?php if ($category): ?>
        <div class="bg-white p-8 rounded-2xl shadow-xl border border-slate-100">
            <!-- Progress Bar -->
            <?php if (!empty($questions)): ?>
            <div class="mb-8 relative">
                <div class="flex justify-between text-sm font-bold text-primary dark:text-accent mb-2 uppercase tracking-wider">
                    <span id="step-indicator">Adım 1 / <?= count($questions) ?></span>
                    <span id="progress-percentage">0%</span>
                </div>
                <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-5 overflow-hidden shadow-inner border border-slate-200/50 dark:border-slate-600 p-1">
                    <div id="progress-bar" class="bg-gradient-to-r from-primary to-indigo-600 h-full rounded-full transition-all duration-500 ease-out w-0 relative overflow-hidden shadow-md" style="width: <?= 100/count($questions) ?>%">
                        <div class="absolute inset-0 w-full h-full bg-[linear-gradient(45deg,rgba(255,255,255,0.15)_25%,transparent_25%,transparent_50%,rgba(255,255,255,0.15)_50%,rgba(255,255,255,0.15)_75%,transparent_75%,transparent)] bg-[length:1rem_1rem] animate-progress-stripes"></div>
                        <div class="absolute top-0 left-0 bottom-0 right-0 bg-white/30 w-full h-full animate-progress-shimmer skew-x-[-20deg]"></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-slate-100">
                <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-3xl"><?= $category['icon'] ?></span>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-slate-800"><?= htmlspecialchars($category['name']) ?></h1>
                    <p class="text-slate-500 font-medium text-sm">
                        <?php if($displayLocation): ?>
                            <span class="text-primary font-bold"><?= htmlspecialchars($displayLocation) ?></span> bölgesinde en iyi teklifleri al.
                        <?php else: ?>
                            En iyi teklifleri al.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <!-- Form Container with Overlay for Location Lock -->
            <div id="form-container" class="relative transition-all duration-300">
            <form id="wizard-form" action="save-demand.php" method="POST">
                <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                <input type="hidden" name="location_slug" value="<?= htmlspecialchars($location['slug'] ?? ($locationSlug ?: 'genel')) ?>">
                
                <!-- Google Maps Verileri -->
                <input type="hidden" name="g_address" value="<?= htmlspecialchars($gAddress) ?>">
                <input type="hidden" name="g_lat" value="<?= htmlspecialchars($gLat) ?>">
                <input type="hidden" name="g_lng" value="<?= htmlspecialchars($gLng) ?>">
                <input type="hidden" name="g_city" value="<?= htmlspecialchars($gCity) ?>">
                <input type="hidden" name="g_district" value="<?= htmlspecialchars($gDistrict) ?>">

                <?php if (empty($questions)): ?>
                    <div class="text-center py-8 text-slate-500">Bu kategori için henüz soru tanımlanmamış.</div>
                <?php else: ?>
                    <?php foreach ($questions as $index => $q): 
                        $options = $q['options'] ? json_decode($q['options'], true) : [];
                        $isHidden = $index !== 0 ? 'hidden opacity-0' : 'opacity-100 translate-x-0';
                    ?>
                        <div class="step-content transition-all duration-300 ease-in-out transform <?= $isHidden ?>" data-step="<?= $index ?>">
                            <div class="mb-6">
                                <label class="block text-xl font-bold text-slate-800 mb-4">
                                    <?= htmlspecialchars($q['question_text']) ?>
                                    <?php if($q['is_required']): ?><span class="text-red-500">*</span><?php endif; ?>
                                </label>

                                <?php if ($q['input_type'] === 'text'): ?>
                                    <input type="text" name="answers[<?= $q['id'] ?>]" class="w-full rounded-xl border-slate-200 py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent" <?= $q['is_required'] ? 'required' : '' ?>>
                                
                                <?php elseif ($q['input_type'] === 'number'): ?>
                                    <input type="number" name="answers[<?= $q['id'] ?>]" class="w-full rounded-xl border-slate-200 py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent" <?= $q['is_required'] ? 'required' : '' ?>>

                                <?php elseif ($q['input_type'] === 'textarea'): ?>
                                    <textarea name="answers[<?= $q['id'] ?>]" rows="4" class="w-full rounded-xl border-slate-200 py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent" <?= $q['is_required'] ? 'required' : '' ?>></textarea>

                                <?php elseif ($q['input_type'] === 'select'): ?>
                                    <select name="answers[<?= $q['id'] ?>]" class="w-full rounded-xl border-slate-200 py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent" <?= $q['is_required'] ? 'required' : '' ?>>
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($options as $opt): ?>
                                            <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                <?php elseif ($q['input_type'] === 'radio'): ?>
                                    <div class="space-y-3">
                                        <?php foreach ($options as $opt): ?>
                                            <label class="flex items-center p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors">
                                                <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= htmlspecialchars($opt) ?>" class="text-primary focus:ring-primary" <?= $q['is_required'] ? 'required' : '' ?>>
                                                <span class="ml-3 font-medium text-slate-700"><?= htmlspecialchars($opt) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                <?php elseif ($q['input_type'] === 'checkbox'): ?>
                                    <div class="space-y-3">
                                        <?php foreach ($options as $opt): ?>
                                            <label class="flex items-center p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors">
                                                <input type="checkbox" name="answers[<?= $q['id'] ?>][]" value="<?= htmlspecialchars($opt) ?>" class="rounded text-primary focus:ring-primary">
                                                <span class="ml-3 font-medium text-slate-700"><?= htmlspecialchars($opt) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                <?php elseif ($q['input_type'] === 'date'): ?>
                                    <input type="date" name="answers[<?= $q['id'] ?>]" class="w-full rounded-xl border-slate-200 py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent" <?= $q['is_required'] ? 'required' : '' ?>>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="flex justify-between mt-8 pt-6 border-t border-slate-100">
                        <button type="button" id="prev-btn" class="hidden px-6 py-3 rounded-xl font-bold text-slate-600 hover:bg-slate-100 transition-colors">
                            Geri
                        </button>
                        <button type="button" id="next-btn" class="ml-auto px-8 py-3 bg-primary text-white rounded-xl font-bold hover:bg-primary/90 transition-all shadow-lg">
                            Devam Et
                        </button>
                        <button type="submit" id="submit-btn" class="hidden ml-auto px-8 py-3 bg-green-600 text-white rounded-xl font-bold hover:bg-green-700 transition-all shadow-lg">
                            Talebi Oluştur
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    <?php else: ?>
        <div class="text-center py-20">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-red-50 text-red-500 rounded-full mb-6">
                <span class="material-symbols-outlined text-4xl">search_off</span>
            </div>
            <h2 class="text-3xl font-bold text-slate-900 mb-4">Hizmet Bulunamadı</h2>
            <p class="text-slate-500 mb-8 text-lg">Aradığınız hizmet kategorisi şu anda aktif değil veya bulunamadı.</p>
            <a href="index.php" class="inline-flex items-center gap-2 px-8 py-4 bg-primary text-white rounded-xl font-bold hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined">arrow_back</span>
                Anasayfaya Dön
            </a>
        </div>
    <?php endif; ?>
</main>

<!-- Welcome Back Toast -->
<div id="welcome-toast" class="fixed bottom-5 right-5 bg-white dark:bg-slate-800 border-l-4 border-primary shadow-2xl rounded-r-xl p-4 transform translate-y-20 opacity-0 transition-all duration-500 z-50 flex items-center gap-4 hidden">
    <div class="text-primary bg-primary/10 rounded-full p-2">
        <span class="material-symbols-outlined">history_edu</span>
    </div>
    <div>
        <h4 class="font-bold text-slate-800 dark:text-white text-sm">Hoşgeldiniz</h4>
        <p class="text-slate-600 dark:text-slate-400 text-xs">Kaldığınız yerden devam edebilirsiniz.</p>
    </div>
    <button onclick="dismissToast()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
        <span class="material-symbols-outlined text-lg">close</span>
    </button>
</div>

<!-- Location Selection Modal -->
<div id="location-modal" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-slate-900/80 backdrop-blur-sm p-4 transition-opacity duration-300 opacity-0">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden relative transform scale-95 transition-transform duration-300">
        <button onclick="closeLocationModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors z-10">
            <span class="material-symbols-outlined">close</span>
        </button>
        <div class="p-8 text-center">
            <div class="w-20 h-20 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mx-auto mb-6 animate-pulse">
                <span class="material-symbols-outlined text-4xl">location_on</span>
            </div>
            <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-2">Konumunuzu Belirleyin</h3>
            <p class="text-slate-500 dark:text-slate-400 mb-8 text-sm leading-relaxed">Size en yakın ve en uygun hizmet verenleri bulabilmemiz için konum bilgisine ihtiyacımız var.</p>
            
            <!-- Hata Mesajı Alanı -->
            <div id="location-error-msg" class="hidden mb-4 p-3 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm rounded-lg border border-red-100 dark:border-red-800 font-medium transition-all"></div>
            
            <div class="relative mb-4">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="material-symbols-outlined text-slate-400">search</span>
                </div>
                <input type="text" id="modal-location-search" class="w-full pl-10 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all placeholder:text-slate-400" placeholder="İl, ilçe veya mahalle ara...">
            </div>
            
            <button id="btn-save-location" disabled class="w-full py-3.5 bg-primary text-white font-bold rounded-xl transition-all shadow-lg hover:bg-primary/90 flex items-center justify-center gap-2 opacity-50 cursor-not-allowed">
                Konumu Kaydet
            </button>
        </div>
    </div>
</div>

<script>
    // Modal Functions
    function openLocationModal() {
        const modal = document.getElementById('location-modal');
        if(modal) {
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.querySelector('div').classList.remove('scale-95');
                modal.querySelector('div').classList.add('scale-100');
            }, 10);
        }
    }

    function closeLocationModal() {
        const modal = document.getElementById('location-modal');
        if(modal) {
            modal.classList.add('opacity-0');
            modal.querySelector('div').classList.remove('scale-100');
            modal.querySelector('div').classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
    }

    // Google Maps Init
    let tempSelectedPlace = null;

    window.initLocationServices = function() {
        // URL'de hata varsa (Backend'den dönüş) modalı aç ve uyarı ver
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('error') === 'location_missing') {
            setTimeout(openLocationModal, 500);
            const errorDiv = document.getElementById('location-error-msg');
            if (errorDiv) {
                errorDiv.textContent = 'Talep oluşturmak için lütfen konumunuzu seçiniz.';
                errorDiv.classList.remove('hidden');
            }
        }

        // Autocomplete
        const input = document.getElementById("modal-location-search");
        const btnSave = document.getElementById('btn-save-location');
        if (input && typeof google !== 'undefined' && google.maps && google.maps.places) {
            const autocomplete = new google.maps.places.Autocomplete(input, {
                componentRestrictions: { country: "tr" },
                fields: ["formatted_address", "geometry", "address_components"],
                types: ["geocode"]
            });

            autocomplete.addListener("place_changed", () => {
                const place = autocomplete.getPlace();
                if (place.geometry) {
                    tempSelectedPlace = place;
                    // Seçim yapıldığında butonu aktif et
                    if (btnSave) {
                        btnSave.disabled = false;
                        btnSave.classList.remove('opacity-50', 'cursor-not-allowed');
                    }
                }
            });
            
            // Kullanıcı elle yazarsa seçimi sıfırla
            input.addEventListener('input', () => {
                tempSelectedPlace = null;
                // En az 3 karakter kontrolü
                if (btnSave) {
                    if (input.value.length >= 3) {
                        btnSave.disabled = false;
                        btnSave.classList.remove('opacity-50', 'cursor-not-allowed');
                    } else {
                        btnSave.disabled = true;
                        btnSave.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                }
            });
        }

        // Save Button Logic
        if (btnSave) {
            btnSave.addEventListener('click', () => {
                const inputVal = document.getElementById('modal-location-search').value;
                const errorDiv = document.getElementById('location-error-msg');
                if (errorDiv) errorDiv.classList.add('hidden');

                if (!inputVal) {
                    if (errorDiv) {
                        errorDiv.textContent = 'Lütfen bir konum arayın.';
                        errorDiv.classList.remove('hidden');
                    }
                    return;
                }

                if (tempSelectedPlace && tempSelectedPlace.geometry) {
                     processLocationData(tempSelectedPlace.formatted_address, tempSelectedPlace.geometry.location.lat(), tempSelectedPlace.geometry.location.lng(), tempSelectedPlace.address_components);
                } else {
                    // Manual geocoding fallback
                    const geocoder = new google.maps.Geocoder();
                    geocoder.geocode({ address: inputVal, componentRestrictions: { country: 'TR' } }, (results, status) => {
                        if (status === "OK" && results[0]) {
                            processLocationData(results[0].formatted_address, results[0].geometry.location.lat(), results[0].geometry.location.lng(), results[0].address_components);
                        } else {
                            if (errorDiv) {
                                errorDiv.textContent = 'Girdiğiniz konum bulunamadı. Lütfen listeden bir seçim yapın.';
                                errorDiv.classList.remove('hidden');
                            }
                        }
                    });
                }
            });
        }
    };

    function processLocationData(address, lat, lng, components) {
        try {
            // Formu Doldur
            const form = document.getElementById('wizard-form');
            let city = "", district = "";
            
            if (components && Array.isArray(components)) {
                for (const component of components) {
                    if (component.types.includes("administrative_area_level_1")) city = component.long_name;
                    if (component.types.includes("administrative_area_level_2")) district = component.long_name;
                }
            }

            if (form) {
                form.querySelector('input[name="g_address"]').value = address;
                form.querySelector('input[name="g_lat"]').value = lat;
                form.querySelector('input[name="g_lng"]').value = lng;
                form.querySelector('input[name="g_city"]').value = city;
                form.querySelector('input[name="g_district"]').value = district;
            }

            // Cookie Kaydet
            const locationName = district || city || "Seçili Konum";
            const maxAge = 60*60*24*30; // 30 gün
            document.cookie = "user_location=" + encodeURIComponent(locationName) + "; path=/; max-age=" + maxAge;
            document.cookie = "g_address=" + encodeURIComponent(address) + "; path=/; max-age=" + maxAge;
            document.cookie = "g_lat=" + lat + "; path=/; max-age=" + maxAge;
            document.cookie = "g_lng=" + lng + "; path=/; max-age=" + maxAge;
            document.cookie = "g_city=" + encodeURIComponent(city) + "; path=/; max-age=" + maxAge;
            document.cookie = "g_district=" + encodeURIComponent(district) + "; path=/; max-age=" + maxAge;

            // Veritabanı Kaydı (Eğer giriş yapmışsa)
            <?php if ($isLoggedIn): ?>
            const formData = new FormData();
            formData.append('address', address);
            formData.append('lat', lat);
            formData.append('lng', lng);
            formData.append('city', city);
            formData.append('district', district);
            fetch('ajax/update-user-location.php', { method: 'POST', body: formData });
            <?php endif; ?>

            // UI Güncelle
            const headerLocText = document.getElementById('header-location-text');
            if (headerLocText) headerLocText.textContent = locationName;

        } catch (e) {
            console.error("Konum işlenirken hata:", e);
        } finally {
            // Modalı Kapat (Her durumda)
            closeLocationModal();
        }
    }

</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($siteSettings['google_maps_api_key'] ?? '') ?>&libraries=places&callback=initLocationServices" async defer></script>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script>
const isLoggedIn = <?= json_encode($isLoggedIn) ?>;
document.addEventListener('DOMContentLoaded', () => {
    const steps = document.querySelectorAll('.step-content');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const submitBtn = document.getElementById('submit-btn');
    let currentStep = 0;
    const totalSteps = steps.length;

    // LocalStorage İşlemleri
    const form = document.getElementById('wizard-form');
    const categoryId = form ? form.querySelector('input[name="category_id"]').value : null;
    const storageKey = categoryId ? 'demand_draft_' + categoryId : null;

    // Autosave Indicator
    let autosaveIndicator = document.getElementById('autosave-indicator');
    if (!autosaveIndicator) {
        autosaveIndicator = document.createElement('div');
        autosaveIndicator.id = 'autosave-indicator';
        autosaveIndicator.className = 'fixed bottom-4 left-4 bg-white dark:bg-slate-800 text-green-600 dark:text-green-400 text-xs font-medium px-3 py-2 rounded-lg shadow-lg border border-slate-100 dark:border-slate-700 flex items-center gap-2 transition-opacity duration-500 opacity-0 z-40 pointer-events-none';
        autosaveIndicator.innerHTML = '<span class="material-symbols-outlined text-sm">cloud_done</span> Taslak kaydedildi';
        document.body.appendChild(autosaveIndicator);
    }

    const showSavedIndicator = () => {
        autosaveIndicator.classList.remove('opacity-0');
        setTimeout(() => {
            autosaveIndicator.classList.add('opacity-0');
        }, 2000);
    };

    // Verileri Kaydetme Fonksiyonu
    const saveToStorage = () => {
        if (!form || !storageKey) return;
        
        const formData = new FormData(form);
        const data = {};
        for (const [key, value] of formData.entries()) {
            if (data.hasOwnProperty(key)) {
                if (!Array.isArray(data[key])) data[key] = [data[key]];
                data[key].push(value);
            } else {
                data[key] = value;
            }
        }
        
        const state = {
            formData: data,
            step: currentStep,
            timestamp: new Date().getTime()
        };
        
        localStorage.setItem(storageKey, JSON.stringify(state));
        showSavedIndicator();
    };

    // Debounce wrapper
    let saveTimeout;
    const debouncedSave = () => {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(saveToStorage, 1000);
    };

    if (form && storageKey) {
        // Verileri Geri Yükle
        const savedData = localStorage.getItem(storageKey);
        if (savedData) {
            try {
                const parsed = JSON.parse(savedData);
                let data = parsed;
                let savedStep = 0;

                // Yeni format kontrolü (formData ve step içeriyor mu?)
                if (parsed.formData) {
                    data = parsed.formData;
                    savedStep = parsed.step || 0;
                }

                Object.keys(data).forEach(key => {
                    const value = data[key];
                    // Özel karakterleri kaçış dizisiyle sarmala
                    const inputs = form.querySelectorAll(`[name="${key.replace(/"/g, '\\"')}"]`);
                    if (inputs.length > 0) {
                        const type = inputs[0].type;
                        if (type === 'radio') {
                            inputs.forEach(input => {
                                if (input.value === value) input.checked = true;
                            });
                        } else if (type === 'checkbox') {
                            const valArray = Array.isArray(value) ? value : [value];
                            inputs.forEach(input => {
                                if (valArray.includes(input.value)) input.checked = true;
                            });
                        } else {
                            inputs[0].value = value;
                        }
                    }
                });

                // Adımı Geri Yükle
                if (savedStep > 0 && savedStep < steps.length) {
                    // İlk adımı gizle
                    steps[0].classList.add('hidden', 'opacity-0');
                    steps[0].classList.remove('opacity-100', 'translate-x-0');
                    
                    // Kayıtlı adımı göster
                    steps[savedStep].classList.remove('hidden', 'opacity-0', 'translate-x-10', '-translate-x-10');
                    steps[savedStep].classList.add('opacity-100', 'translate-x-0');
                    
                    currentStep = parseInt(savedStep);
                    updateButtons();
                    updateProgress();
                }

                // Eğer kullanıcı giriş yapmışsa ve veri geri yüklendiyse bildirim göster
                if (isLoggedIn) {
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.get('status') === 'registered') {
                        const toastTitle = document.querySelector('#welcome-toast h4');
                        const toastDesc = document.querySelector('#welcome-toast p');
                        if (toastTitle) toastTitle.textContent = 'Aramıza Hoşgeldiniz';
                        if (toastDesc) toastDesc.textContent = 'Talebinize kaldığınız yerden devam edebilirsiniz.';
                    }
                    showWelcomeBackNotification();
                }
            } catch (e) { console.error('Form verileri yüklenemedi', e); }
        }

        // Verileri Kaydet
        form.addEventListener('input', debouncedSave);
        form.addEventListener('change', saveToStorage); // Select ve Radio değişimleri için

        // Submit Kontrolü
        form.addEventListener('submit', (e) => {
            if (!isLoggedIn) {
                e.preventDefault();
                saveToStorage(); // Yönlendirmeden önce son durumu kaydet
                const redirectUrl = encodeURIComponent(window.location.href);
                window.location.href = 'login.php?redirect=' + redirectUrl;
            } else {
                e.preventDefault();
                localStorage.removeItem(storageKey);

                // Konfeti Animasyonu
                confetti({
                    particleCount: 150,
                    spread: 70,
                    origin: { y: 0.6 },
                    colors: ['#1a2a6c', '#fbbd23', '#ffffff']
                });

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="material-symbols-outlined animate-spin text-sm">progress_activity</span> Oluşturuluyor...';
                    submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                }

                setTimeout(() => {
                    form.submit();
                }, 1500);
            }
        });
    }

    function changeStep(direction) {
        const currentStepEl = steps[currentStep];
        let nextStepIndex = currentStep;
        
        if (direction === 'next') {
            nextStepIndex++;
        } else {
            nextStepIndex--;
        }

        const nextStepEl = steps[nextStepIndex];

        // Mevcut adımı gizle (Animasyonlu)
        currentStepEl.classList.remove('opacity-100', 'translate-x-0');
        currentStepEl.classList.add('opacity-0');
        currentStepEl.classList.add(direction === 'next' ? '-translate-x-10' : 'translate-x-10');

        setTimeout(() => {
            currentStepEl.classList.add('hidden');
            currentStepEl.classList.remove('-translate-x-10', 'translate-x-10'); // Reset

            // Yeni adımı hazırla
            nextStepEl.classList.remove('hidden');
            nextStepEl.classList.add('opacity-0');
            nextStepEl.classList.add(direction === 'next' ? 'translate-x-10' : '-translate-x-10');
            
            // Reflow tetikle
            void nextStepEl.offsetWidth;

            // Yeni adımı göster (Animasyonlu)
            nextStepEl.classList.remove('opacity-0', 'translate-x-10', '-translate-x-10');
            nextStepEl.classList.add('opacity-100', 'translate-x-0');

            currentStep = nextStepIndex;
            updateButtons();
            updateProgress();
            if (typeof saveToStorage === 'function') saveToStorage(); // Adım değişikliğini kaydet
        }, 300);
    }

    function updateButtons() {
        if (currentStep === 0) {
            prevBtn.classList.add('hidden');
        } else {
            prevBtn.classList.remove('hidden');
        }

        if (currentStep === steps.length - 1) {
            nextBtn.classList.add('hidden');
            submitBtn.classList.remove('hidden');
        } else {
            nextBtn.classList.remove('hidden');
            submitBtn.classList.add('hidden');
        }
    }

    function updateProgress() {
        const progressBar = document.getElementById('progress-bar');
        const stepIndicator = document.getElementById('step-indicator');
        const progressPercentage = document.getElementById('progress-percentage');
        
        if (totalSteps > 0) {
            const progress = ((currentStep + 1) / totalSteps) * 100;
            if (progressBar) progressBar.style.width = `${progress}%`;
            if (stepIndicator) stepIndicator.textContent = `Adım ${currentStep + 1} / ${totalSteps}`;
            if (progressPercentage) progressPercentage.textContent = `${Math.round(progress)}%`;
        }
    }

    nextBtn?.addEventListener('click', (e) => {
        // Konum Kontrolü (Devam Et butonuna basıldığında)
        const addr = document.querySelector('input[name="g_address"]').value;
        if (!addr) {
            e.preventDefault();
            e.stopPropagation();
            openLocationModal();
            return;
        }

        const currentStepEl = steps[currentStep];
        const inputs = currentStepEl.querySelectorAll('input, select, textarea');
        let allValid = true;
        
        for (const input of inputs) {
            if (!input.checkValidity()) {
                allValid = false;
                input.reportValidity();
                break;
            }
        }

        if (allValid && currentStep < steps.length - 1) {
            changeStep('next');
        }
    });

    prevBtn?.addEventListener('click', () => {
        if (currentStep > 0) {
            saveToStorage();
            changeStep('prev');
        }
    });
    
    // Initial Progress Update
    updateProgress();
});

function showWelcomeBackNotification() {
    const toast = document.getElementById('welcome-toast');
    if (toast) {
        toast.classList.remove('hidden');
        // Reflow tetikle
        void toast.offsetWidth;
        toast.classList.remove('translate-y-20', 'opacity-0');
        
        // 5 saniye sonra otomatik kapat
        setTimeout(dismissToast, 5000);
    }
}

function dismissToast() {
    const toast = document.getElementById('welcome-toast');
    if (toast && !toast.classList.contains('translate-y-20')) {
        toast.classList.add('translate-y-20', 'opacity-0');
        setTimeout(() => toast.classList.add('hidden'), 500);
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
