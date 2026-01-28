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

$isLoggedIn = isset($_SESSION['user_id']);

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
        
        // Soru tiplerinin konfigürasyonunu (CSS/JS/RenderAs) çek
        $typesConfig = [];
        try {
            $stmtTypes = $pdo->query("SELECT type_key, render_as, custom_css, custom_js FROM question_types");
            $typesConfig = $stmtTypes->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
        } catch (Exception $e) {}
    }
}

$totalSteps = count($questions) + ($isLoggedIn ? 0 : 1);

$displayLocation = $gAddress ? $gAddress : ($location ? $location['district'] . ' / ' . $location['city'] : '');
if ($gDistrict && $gCity) {
    $displayLocation = $gDistrict . ' / ' . $gCity;
}
$isLocationRequired = isset($category['is_location_required']) ? (bool)$category['is_location_required'] : true;

if ($category) {
    if (!empty($category['seo_title'])) {
        $pageTitle = $category['seo_title'];
    } else {
        $pageTitle = ($displayLocation ? $displayLocation . ' ' : '') . $category['name'] . " Fiyat Teklifi Alın";
    }
    
    if (!empty($category['seo_description'])) {
        $siteDescription = $category['seo_description'];
    }
} else {
    $pageTitle = "Talep Oluştur";
}

require_once 'includes/header.php';

?>

<!-- Dinamik Soru Tipi Stilleri -->
<?php if (!empty($questions) && !empty($typesConfig)): ?>
<style>
    <?php 
    $injectedTypes = [];
    foreach ($questions as $q) {
        if (isset($typesConfig[$q['input_type']]) && !in_array($q['input_type'], $injectedTypes)) {
            echo $typesConfig[$q['input_type']]['custom_css'] . "\n";
            $injectedTypes[] = $q['input_type'];
        }
    } 
    ?>
</style>
<?php endif; ?>

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
                    <span id="step-indicator">Adım 1 / <?= $totalSteps ?></span>
                    <span id="progress-percentage">0%</span>
                </div>
                <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-5 overflow-hidden shadow-inner border border-slate-200/50 dark:border-slate-600 p-1">
                    <div id="progress-bar" class="bg-gradient-to-r from-primary to-indigo-600 h-full rounded-full transition-all duration-500 ease-out w-0 relative overflow-hidden shadow-md" style="width: <?= 100/$totalSteps ?>%">
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
                        <span id="offer-location-text">
                            <?php if($displayLocation): ?>
                                <span class="text-primary font-bold"><?= htmlspecialchars($displayLocation) ?></span> bölgesinde en iyi teklifleri al.
                            <?php else: ?>
                                En iyi teklifleri al.
                            <?php endif; ?>
                        </span>
                    </p>
                </div>
            </div>

            <!-- Form Container with Overlay for Location Lock -->
            <div id="form-container" class="relative transition-all duration-300">
            <form id="wizard-form" action="save-demand.php" method="POST" enctype="multipart/form-data">
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
                        
                        // Render Tipi Belirleme (Veritabanından gelen render_as veya varsayılan)
                        $rawType = $q['input_type'];
                        $inputType = isset($typesConfig[$rawType]['render_as']) ? $typesConfig[$rawType]['render_as'] : $rawType;

                        if ($inputType === 'text' && (stripos($q['question_text'], 'telefon') !== false)) {
                             $inputType = 'tel';
                        }
                    ?>
                        <div class="step-content transition-all duration-300 ease-in-out transform <?= $isHidden ?>" data-step="<?= $index ?>">
                            <div class="mb-6">
                                <label class="block text-xl font-bold text-slate-800 mb-4">
                                    <?= htmlspecialchars($q['question_text']) ?>
                                    <?php if($q['is_required']): ?><span class="text-red-500">*</span><?php endif; ?>
                                </label>

                                <?php if ($inputType === 'text'): ?>
                                    <input type="text" name="answers[<?= $q['id'] ?>]" class="w-full rounded-xl border-slate-200 py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent" <?= $q['is_required'] ? 'required' : '' ?>>
                                
                                <?php elseif ($inputType === 'tel'): ?>
                                    <input type="tel" name="answers[<?= $q['id'] ?>]" class="w-full rounded-xl border-slate-200 py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent phone-mask" placeholder="05XX XXX XX XX" maxlength="14" <?= $q['is_required'] ? 'required' : '' ?>>

                                <?php elseif ($inputType === 'number'): ?>
                                    <input type="number" name="answers[<?= $q['id'] ?>]" class="w-full rounded-xl border-slate-200 py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent" <?= $q['is_required'] ? 'required' : '' ?>>

                                <?php elseif ($inputType === 'textarea'): ?>
                                    <textarea name="answers[<?= $q['id'] ?>]" rows="4" class="w-full rounded-xl border-slate-200 py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent" <?= $q['is_required'] ? 'required' : '' ?>></textarea>

                                <?php elseif ($inputType === 'select'): ?>
                                    <select name="answers[<?= $q['id'] ?>]" class="w-full rounded-xl border-slate-200 py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent" <?= $q['is_required'] ? 'required' : '' ?>>
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($options as $opt): ?>
                                            <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                <?php elseif ($inputType === 'radio'): ?>
                                    <div class="space-y-3">
                                        <?php foreach ($options as $opt): ?>
                                            <label class="flex items-center p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors">
                                                <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= htmlspecialchars($opt) ?>" class="text-primary focus:ring-primary" <?= $q['is_required'] ? 'required' : '' ?>>
                                                <span class="ml-3 font-medium text-slate-700"><?= htmlspecialchars($opt) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                <?php elseif ($inputType === 'checkbox'): ?>
                                    <div class="space-y-3">
                                        <?php foreach ($options as $opt): ?>
                                            <label class="flex items-center p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors">
                                                <input type="checkbox" name="answers[<?= $q['id'] ?>][]" value="<?= htmlspecialchars($opt) ?>" class="rounded text-primary focus:ring-primary">
                                                <span class="ml-3 font-medium text-slate-700"><?= htmlspecialchars($opt) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                <?php elseif ($inputType === 'date'): ?>
                                    <input type="date" name="answers[<?= $q['id'] ?>]" class="w-full rounded-xl border-slate-200 py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent" <?= $q['is_required'] ? 'required' : '' ?>>
                                
                                <?php elseif ($inputType === 'location' || $inputType === 'location_intl'): ?>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="material-symbols-outlined text-slate-400">location_on</span>
                                        </div>
                                        <input type="text" 
                                               id="location-search-<?= $q['id'] ?>" 
                                               class="w-full rounded-xl border-slate-200 py-3 pl-10 pr-4 focus:ring-2 focus:ring-primary focus:border-transparent location-autocomplete-input validation-proxy" 
                                               placeholder="<?= $inputType === 'location_intl' ? 'Dünya genelinde adres arayın...' : 'Adres arayın...' ?>"
                                               data-question-id="<?= $q['id'] ?>"
                                               data-location-type="<?= $inputType === 'location_intl' ? 'intl' : 'tr' ?>"
                                               <?= $q['is_required'] ? 'data-required="true"' : '' ?>>
                                        <input type="hidden" name="answers[<?= $q['id'] ?>]" id="location-answer-<?= $q['id'] ?>">
                                    </div>

                                <?php elseif ($inputType === 'image'): ?>
                                    <div class="mt-2">
                                        <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-slate-300 rounded-xl cursor-pointer bg-slate-50 hover:bg-slate-100 transition-colors relative overflow-hidden group">
                                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                <span class="material-symbols-outlined text-3xl text-slate-400 mb-2 group-hover:text-primary transition-colors">add_photo_alternate</span>
                                                <p class="mb-1 text-sm text-slate-500"><span class="font-semibold">Görsel yüklemek için tıklayın</span></p>
                                                <p class="text-xs text-slate-400">JPG, PNG veya WEBP</p>
                                            </div>
                                            <input type="file" name="answers[<?= $q['id'] ?>]" class="hidden" accept="image/png, image/jpeg, image/webp" onchange="if(this.files[0]) { this.previousElementSibling.innerHTML = '<span class=\'material-symbols-outlined text-3xl text-green-500 mb-2\'>check_circle</span><p class=\'text-sm font-bold text-slate-700\'>' + this.files[0].name + '</p>'; this.parentElement.classList.add('border-green-500', 'bg-green-50'); }" <?= $q['is_required'] ? 'required' : '' ?>>
                                        </label>
                                    </div>

                                <?php elseif ($inputType === 'file'): ?>
                                    <div class="mt-2">
                                        <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-slate-300 rounded-xl cursor-pointer bg-slate-50 hover:bg-slate-100 transition-colors relative overflow-hidden group">
                                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                <span class="material-symbols-outlined text-3xl text-slate-400 mb-2 group-hover:text-primary transition-colors">upload_file</span>
                                                <p class="mb-1 text-sm text-slate-500"><span class="font-semibold">Dosya yüklemek için tıklayın</span></p>
                                                <p class="text-xs text-slate-400">PDF, Word, Excel, Görsel vb.</p>
                                            </div>
                                            <input type="file" name="answers[<?= $q['id'] ?>]" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.txt,.zip,.rar" onchange="if(this.files[0]) { this.previousElementSibling.innerHTML = '<span class=\'material-symbols-outlined text-3xl text-green-500 mb-2\'>check_circle</span><p class=\'text-sm font-bold text-slate-700\'>' + this.files[0].name + '</p>'; this.parentElement.classList.add('border-green-500', 'bg-green-50'); }" <?= $q['is_required'] ? 'required' : '' ?>>
                                        </label>
                                    </div>

                                <?php elseif ($inputType === 'color'): ?>
                                    <div class="flex items-center gap-4 p-4 border border-slate-200 rounded-xl bg-slate-50">
                                        <input type="color" name="answers[<?= $q['id'] ?>]" class="h-14 w-24 rounded-lg cursor-pointer border border-slate-300 p-1 bg-white shadow-sm" value="#3b82f6" <?= $q['is_required'] ? 'required' : '' ?>>
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold text-slate-700">Renk Seçimi</span>
                                            <span class="text-xs text-slate-500">Kutuya tıklayarak renk paletini açabilirsiniz.</span>
                                        </div>
                                    </div>

                                <?php elseif ($inputType === 'jewelry_box_select'): ?>
                                    <!-- Takı Kutusu Seçimi -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <input type="hidden" name="answers[<?= $q['id'] ?>]" id="jewelry-answer-<?= $q['id'] ?>" <?= $q['is_required'] ? 'required' : '' ?>>
                                        
                                        <!-- Yüzük Kutusu -->
                                        <div class="box-card bg-white rounded-2xl p-6 shadow-sm flex flex-col items-center text-center" onclick="toggleJewelrySelection(this, 'Yüzük Kutusu', <?= $q['id'] ?>)">
                                            <div class="check-badge">
                                                <span class="material-symbols-outlined">check</span>
                                            </div>
                                            <div class="w-24 h-24 mb-4 flex items-center justify-center bg-slate-50 rounded-lg">
                                                <span class="material-symbols-outlined text-5xl text-slate-300">grid_view</span>
                                            </div>
                                            <h3 class="font-bold text-slate-800 mb-1">Küpe/Yüzük Kutusu</h3>
                                            <p class="text-xs text-slate-500 mb-2">Küçük Kare</p>
                                            <span class="text-[10px] font-medium px-2 py-1 bg-slate-100 rounded text-slate-600">5x5x3.5 cm</span>
                                        </div>

                                        <!-- Kolye/Küpe Kutusu -->
                                        <div class="box-card bg-white rounded-2xl p-6 shadow-sm flex flex-col items-center text-center" onclick="toggleJewelrySelection(this, 'Kolye/Küpe Kutusu', <?= $q['id'] ?>)">
                                            <div class="check-badge">
                                                <span class="material-symbols-outlined">check</span>
                                            </div>
                                            <div class="w-24 h-24 mb-4 flex items-center justify-center bg-slate-50 rounded-lg">
                                                <span class="material-symbols-outlined text-5xl text-slate-300">rectangle</span>
                                            </div>
                                            <h3 class="font-bold text-slate-800 mb-1">Kolye Kutusu</h3>
                                            <p class="text-xs text-slate-500 mb-2">Orta Boy Dikdörtgen</p>
                                            <span class="text-[10px] font-medium px-2 py-1 bg-slate-100 rounded text-slate-600">8x10x3.5 cm</span>
                                        </div>

                                        <!-- Takı Seti Kutusu -->
                                        <div class="box-card bg-white rounded-2xl p-6 shadow-sm flex flex-col items-center text-center" onclick="toggleJewelrySelection(this, 'Takı Seti Kutusu', <?= $q['id'] ?>)">
                                            <div class="check-badge">
                                                <span class="material-symbols-outlined">check</span>
                                            </div>
                                            <div class="w-24 h-24 mb-4 flex items-center justify-center bg-slate-50 rounded-lg">
                                                <span class="material-symbols-outlined text-5xl text-slate-300">square</span>
                                            </div>
                                            <h3 class="font-bold text-slate-800 mb-1">Takı Seti Kutusu</h3>
                                            <p class="text-xs text-slate-500 mb-2">Büyük Kare</p>
                                            <span class="text-[10px] font-medium px-2 py-1 bg-slate-100 rounded text-slate-600">16x16x4 cm</span>
                                        </div>

                                        <!-- Dikey Kutu -->
                                        <div class="box-card bg-white rounded-2xl p-6 shadow-sm flex flex-col items-center text-center" onclick="toggleJewelrySelection(this, 'Dikey Kutu', <?= $q['id'] ?>)">
                                            <div class="check-badge">
                                                <span class="material-symbols-outlined">check</span>
                                            </div>
                                            <div class="w-24 h-24 mb-4 flex items-center justify-center bg-slate-50 rounded-lg">
                                                <span class="material-symbols-outlined text-5xl text-slate-300">view_column</span>
                                            </div>
                                            <h3 class="font-bold text-slate-800 mb-1">Dikey Kutu</h3>
                                            <p class="text-xs text-slate-500 mb-2">Tesbih & Bileklik</p>
                                            <span class="text-[10px] font-medium px-2 py-1 bg-slate-100 rounded text-slate-600">22x5x3 cm</span>
                                        </div>
                                    </div>
                                    <div class="mt-4 text-center">
                                        <span class="text-sm font-semibold text-primary" id="jewelry-counter-<?= $q['id'] ?>">0 Model Seçildi</span>
                                    </div>

                                <?php elseif ($inputType === 'car_damage_select'): ?>
                                    <!-- Araç Hasar Seçimi Görsel Bileşeni -->
                                    <div class="car-selector-wrapper">
                                        <style>
                                            .car-part {
                                                transition: all 0.2s ease;
                                                cursor: pointer;
                                                fill: #ffffff;
                                                stroke: #cbd5e1;
                                                stroke-width: 2;
                                            }
                                            .car-part:hover {
                                                fill: #f1f5f9;
                                            }
                                            .car-part.selected {
                                                fill: #4ade80 !important; /* Açık Yeşil */
                                                stroke: #16a34a !important; /* Koyu Yeşil Çerçeve */
                                            }
                                            .car-container svg {
                                                filter: drop-shadow(0 10px 15px rgb(0 0 0 / 0.1));
                                            }
                                        </style>
                                        
                                        <div class="text-center mb-4">
                                            <p class="text-sm text-slate-500">Hasarlı bölgeleri araç üzerinde dokunarak seçiniz.</p>
                                            <div id="selected-parts-display-<?= $q['id'] ?>" class="mt-2 min-h-[24px] text-sm font-bold text-primary"></div>
                                        </div>

                                        <div class="car-container w-full max-w-[300px] mx-auto relative">
                                            <svg class="w-full h-auto" viewBox="0 0 400 800" xmlns="http://www.w3.org/2000/svg">
                                                <g id="car-body-<?= $q['id'] ?>">
                                                    <path class="car-part" d="M100,80 Q200,50 300,80 L310,110 Q200,90 90,110 Z" data-name="Ön Tampon"></path>
                                                    <path class="car-part" d="M90,115 Q200,95 310,115 L320,240 Q200,225 80,240 Z" data-name="Kaput"></path>
                                                    <path class="car-part" d="M50,110 Q80,105 90,115 L80,240 Q45,230 40,150 Z" data-name="Sol Ön Çamurluk"></path>
                                                    <path class="car-part" d="M350,110 Q320,105 310,115 L320,240 Q355,230 360,150 Z" data-name="Sağ Ön Çamurluk"></path>
                                                    <path d="M85,245 Q200,230 315,245 L330,320 Q200,310 70,320 Z" fill="#e2e8f0" pointer-events="none"></path> <!-- Ön Cam -->
                                                    <path class="car-part" d="M75,325 Q200,315 325,325 L335,500 Q200,510 65,500 Z" data-name="Tavan"></path>
                                                    <path class="car-part" d="M40,245 L70,245 L65,370 L35,370 Z" data-name="Sol Ön Kapı"></path>
                                                    <path class="car-part" d="M330,245 L360,245 L365,370 L335,370 Z" data-name="Sağ Ön Kapı"></path>
                                                    <path class="car-part" d="M35,375 L65,375 L60,500 L30,500 Z" data-name="Sol Arka Kapı"></path>
                                                    <path class="car-part" d="M335,375 L365,375 L370,500 L340,500 Z" data-name="Sağ Arka Kapı"></path>
                                                    <path d="M65,505 Q200,515 335,505 L345,560 Q200,570 55,560 Z" fill="#e2e8f0" pointer-events="none"></path> <!-- Arka Cam -->
                                                    <path class="car-part" d="M55,565 Q200,575 345,565 L350,680 Q200,695 50,680 Z" data-name="Bagaj Kapağı"></path>
                                                    <path class="car-part" d="M30,505 Q60,505 60,565 L50,680 Q10,670 20,550 Z" data-name="Sol Arka Çamurluk"></path>
                                                    <path class="car-part" d="M370,505 Q340,505 340,565 L350,680 Q390,670 380,550 Z" data-name="Sağ Arka Çamurluk"></path>
                                                    <path class="car-part" d="M50,685 Q200,700 350,685 L340,715 Q200,730 60,715 Z" data-name="Arka Tampon"></path>
                                                </g>
                                            </svg>
                                        </div>
                                        
                                        <!-- Seçilen parçaları tutacak gizli input -->
                                        <input type="hidden" name="answers[<?= $q['id'] ?>]" id="car-answer-<?= $q['id'] ?>" <?= $q['is_required'] ? 'required' : '' ?>>
                                        
                                        <script>
                                            document.addEventListener('DOMContentLoaded', function() {
                                                const container = document.getElementById('car-body-<?= $q['id'] ?>');
                                                const hiddenInput = document.getElementById('car-answer-<?= $q['id'] ?>');
                                                const display = document.getElementById('selected-parts-display-<?= $q['id'] ?>');
                                                const selectedParts = new Set();

                                                // Varsa eski veriyi yükle (Geri gelindiğinde)
                                                if (hiddenInput.value) {
                                                    const savedParts = hiddenInput.value.split(', ');
                                                    savedParts.forEach(part => {
                                                        if(part) {
                                                            selectedParts.add(part);
                                                            const el = container.querySelector(`[data-name="${part}"]`);
                                                            if(el) el.classList.add('selected');
                                                        }
                                                    });
                                                    updateDisplay();
                                                }

                                                container.querySelectorAll('.car-part').forEach(part => {
                                                    part.addEventListener('click', function() {
                                                        const name = this.getAttribute('data-name');
                                                        
                                                        if (selectedParts.has(name)) {
                                                            selectedParts.delete(name);
                                                            this.classList.remove('selected');
                                                        } else {
                                                            selectedParts.add(name);
                                                            this.classList.add('selected');
                                                        }
                                                        
                                                        // Inputu güncelle
                                                        const partsArray = Array.from(selectedParts);
                                                        hiddenInput.value = partsArray.join(', ');
                                                        
                                                        // Görsel güncelleme ve validasyon tetikleme
                                                        updateDisplay();
                                                        hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                                                        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                                                    });
                                                });

                                                function updateDisplay() {
                                                    if (selectedParts.size > 0) {
                                                        display.textContent = Array.from(selectedParts).join(', ');
                                                        display.classList.remove('text-red-500');
                                                        display.classList.add('text-primary');
                                                    } else {
                                                        display.textContent = 'Henüz seçim yapılmadı';
                                                        display.classList.add('text-slate-400');
                                                    }
                                                }
                                            });
                                        </script>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!$isLoggedIn): ?>
                    <div class="step-content transition-all duration-300 ease-in-out transform hidden opacity-0" data-step="<?= count($questions) ?>">
                        <div class="mb-6">
                            <h3 class="text-xl font-bold text-slate-800 mb-4">İletişim Bilgileri</h3>
                            <p class="text-slate-500 text-sm mb-6">Teklifleri size ulaştırabilmemiz için iletişim bilgilerinizi giriniz. Sizin için otomatik bir hesap oluşturulacaktır.</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-2">Adınız</label>
                                    <input type="text" name="guest_name" class="w-full rounded-xl border-slate-200 py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-2">Soyadınız</label>
                                    <input type="text" name="guest_surname" class="w-full rounded-xl border-slate-200 py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-bold text-slate-700 mb-2">E-posta Adresi</label>
                                <input type="email" name="guest_email" class="w-full rounded-xl border-slate-200 py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent" required>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-bold text-slate-700 mb-2">Telefon Numarası</label>
                                <input type="tel" name="guest_phone" class="w-full rounded-xl border-slate-200 py-3 px-4 focus:ring-2 focus:ring-primary focus:border-transparent phone-mask" placeholder="05XX XXX XX XX" maxlength="14" required>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="flex justify-between mt-8 pt-6 border-t border-slate-100">
                        <button type="button" id="prev-btn" class="hidden px-6 py-3 rounded-xl font-bold text-slate-600 hover:bg-slate-100 transition-colors flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-lg">arrow_back</span> Geri
                        </button>
                        <button type="button" id="next-btn" class="ml-auto px-8 py-3 bg-primary text-white rounded-xl font-bold hover:bg-primary/90 transition-all shadow-lg flex items-center justify-center gap-2">
                            Devam Et <span class="material-symbols-outlined text-lg">arrow_forward</span>
                        </button>
                        <button type="submit" id="submit-btn" class="hidden ml-auto px-8 py-3 bg-green-600 text-white rounded-xl font-bold hover:bg-green-700 transition-all shadow-lg flex items-center justify-center gap-2">
                            Talebi Oluştur <span class="material-symbols-outlined text-lg">check</span>
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

        // In-form Autocomplete
        const locationInputs = document.querySelectorAll('.location-autocomplete-input');
        locationInputs.forEach(input => {
            const locationType = input.dataset.locationType;
            const options = {
                fields: ["formatted_address", "geometry"],
                types: ["geocode"]
            };

            if (locationType !== 'intl') {
                options.componentRestrictions = { country: "tr" };
            }

            const autocomplete = new google.maps.places.Autocomplete(input, options);

            autocomplete.addListener("place_changed", () => {
                const place = autocomplete.getPlace();
                const questionId = input.dataset.questionId;
                const hiddenInput = document.getElementById('location-answer-' + questionId);

                if (place.geometry && hiddenInput) {
                    const locationData = {
                        address: place.formatted_address,
                        lat: place.geometry.location.lat(),
                        lng: place.geometry.location.lng()
                    };
                    hiddenInput.value = JSON.stringify(locationData);
                    input.setCustomValidity(''); // Clear any previous validation error
                    hiddenInput.dispatchEvent(new Event('change', { bubbles: true })); // Trigger autosave

                    // Global konumu da güncelle (Eğer boşsa)
                    const gAddressInput = document.querySelector('input[name="g_address"]');
                    if (gAddressInput && !gAddressInput.value) {
                        gAddressInput.value = place.formatted_address;
                        document.querySelector('input[name="g_lat"]').value = place.geometry.location.lat();
                        document.querySelector('input[name="g_lng"]').value = place.geometry.location.lng();
                        
                        let city = "", district = "";
                        if (place.address_components) {
                            for (const component of place.address_components) {
                                if (component.types.includes("administrative_area_level_1")) city = component.long_name;
                                if (component.types.includes("administrative_area_level_2")) district = component.long_name;
                            }
                        }
                        document.querySelector('input[name="g_city"]').value = city;
                        document.querySelector('input[name="g_district"]').value = district;
                    }
                }
            });

            // If user types manually after selecting, clear the hidden value to force re-selection
            input.addEventListener('input', () => {
                const questionId = input.dataset.questionId;
                const hiddenInput = document.getElementById('location-answer-' + questionId);
                if(hiddenInput) hiddenInput.value = '';
            });
        });

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

            // Sayfadaki tüm konum sorularını da güncelle (Validasyon ve Geçiş İçin Kritik)
            const locationInputs = document.querySelectorAll('.location-autocomplete-input');
            locationInputs.forEach(input => {
                const questionId = input.dataset.questionId;
                const hiddenInput = document.getElementById('location-answer-' + questionId);
                if (input && hiddenInput && !input.value) {
                    input.value = address;
                    const locationData = {
                        address: address,
                        lat: lat,
                        lng: lng
                    };
                    hiddenInput.value = JSON.stringify(locationData);
                    input.setCustomValidity('');
                }
            });

            // Header Metnini Güncelle
            const offerLocText = document.getElementById('offer-location-text');
            if (offerLocText) {
                const locStr = (district && city) ? `${district} / ${city}` : address;
                offerLocText.innerHTML = `<span class="text-primary font-bold">${locStr}</span> bölgesinde en iyi teklifleri al.`;
            }

            // Modal Inputunu Güncelle (Tekrar açıldığında dolu gelsin)
            const modalInput = document.getElementById('modal-location-search');
            if (modalInput) {
                modalInput.value = address;
            }
            
            // Buton durumunu güncellemek için event tetikle
            const addrInput = form.querySelector('input[name="g_address"]');
            if(addrInput) addrInput.dispatchEvent(new Event('change', { bubbles: true }));

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
<script src="https://cdnjs.cloudflare.com/ajax/libs/compressorjs/1.2.1/compressor.min.js"></script>
<script>
const isLoggedIn = <?= json_encode($isLoggedIn) ?>;
const isLocationRequired = <?= json_encode($isLocationRequired) ?>;
document.addEventListener('DOMContentLoaded', () => {
    // Görsel Sıkıştırma (Client-side Compression)
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            // Sadece resim dosyalarını sıkıştır
            if (!file.type.match(/image.*/)) return;

            // Zaten sıkıştırılmışsa tekrar sıkıştırma
            if (file.compressed) return;

            // UI Geri Bildirimi: Optimize Ediliyor
            const label = input.closest('label');
            const contentDiv = input.previousElementSibling;
            if (contentDiv) {
                contentDiv.innerHTML = '<span class="material-symbols-outlined text-3xl text-primary animate-spin mb-2">settings_suggest</span><p class="text-sm font-bold text-slate-700">Görsel optimize ediliyor...</p>';
                if(label) label.classList.remove('border-green-500', 'bg-green-50');
            }

            new Compressor(file, {
                quality: 0.6, // %60 Kalite
                maxWidth: 1920, // Max Genişlik
                maxHeight: 1920, // Max Yükseklik
                success(result) {
                    // Blob'u File objesine çevir
                    const compressedFile = new File([result], file.name, {
                        type: result.type,
                        lastModified: Date.now(),
                    });
                    
                    // İşaretle (Döngüyü önlemek için)
                    compressedFile.compressed = true;

                    // Input değerini güncelle
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(compressedFile);
                    input.files = dataTransfer.files;

                    // UI Geri Bildirimi: Başarılı
                    if (contentDiv) {
                        contentDiv.innerHTML = '<span class="material-symbols-outlined text-3xl text-green-500 mb-2">check_circle</span><p class="text-sm font-bold text-slate-700">' + file.name + '</p><p class="text-xs text-green-600 font-medium">Optimize edildi (' + (result.size / 1024).toFixed(0) + ' KB)</p>';
                        if(label) label.classList.add('border-green-500', 'bg-green-50');
                    }
                },
                error(err) {
                    console.error('Sıkıştırma hatası:', err.message);
                    // Hata durumunda orijinal dosya bilgisiyle güncelle
                    if (contentDiv) {
                         contentDiv.innerHTML = '<span class="material-symbols-outlined text-3xl text-green-500 mb-2">check_circle</span><p class="text-sm font-bold text-slate-700">' + file.name + '</p>';
                         if(label) label.classList.add('border-green-500', 'bg-green-50');
                    }
                },
            });
        });
    });

    const steps = document.querySelectorAll('.step-content');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const submitBtn = document.getElementById('submit-btn');
    let currentStep = 0;
    const totalSteps = steps.length;

    // Sayfa Yüklendiğinde: Eğer global konum varsa, ilgili soru inputlarını doldur
    const initGAddress = document.querySelector('input[name="g_address"]')?.value;
    const initGLat = document.querySelector('input[name="g_lat"]')?.value;
    const initGLng = document.querySelector('input[name="g_lng"]')?.value;

    if (initGAddress && initGLat && initGLng) {
        const locationInputs = document.querySelectorAll('.location-autocomplete-input');
        locationInputs.forEach(input => {
            const questionId = input.dataset.questionId;
            const hiddenInput = document.getElementById('location-answer-' + questionId);
            if (input && hiddenInput && !input.value) {
                input.value = initGAddress;
                const locationData = { address: initGAddress, lat: parseFloat(initGLat), lng: parseFloat(initGLng) };
                hiddenInput.value = JSON.stringify(locationData);
            }
        });
    }

    // LocalStorage İşlemleri
    const form = document.getElementById('wizard-form');
    const categoryId = form ? form.querySelector('input[name="category_id"]').value : null;
    const storageKey = categoryId ? 'demand_draft_' + categoryId : null;

    // Konum Durumuna Göre Buton Güncelleme
    const gAddressInput = document.querySelector('input[name="g_address"]');
    const updateNextButtonState = () => {
        if (!nextBtn) return;
        
        // Mevcut adımda konum sorusu var mı kontrol et
        const currentStepEl = steps[currentStep];
        const locationInput = currentStepEl.querySelector('.location-autocomplete-input');
        const hasLocationQuestion = locationInput !== null || currentStepEl.querySelector('.btn-gps-location') !== null;
        const isLocalFilled = locationInput && locationInput.value.trim() !== '';

        if (isLocationRequired && (!gAddressInput.value || gAddressInput.value.trim() === '') && hasLocationQuestion && !isLocalFilled) {
            nextBtn.innerHTML = '<span class="material-symbols-outlined text-lg animate-pulse">my_location</span> Konumumu Bul';
        } else {
            nextBtn.innerHTML = 'Devam Et <span class="material-symbols-outlined text-lg">arrow_forward</span>';
        }
    };
    
    // Sayfa yüklendiğinde ve adres değiştiğinde kontrol et
    if(gAddressInput) {
        updateNextButtonState();
        gAddressInput.addEventListener('change', updateNextButtonState);
        // MutationObserver ile value değişimini izle (JS ile atamalarda change tetiklenmeyebilir)
        new MutationObserver(updateNextButtonState).observe(gAddressInput, { attributes: true, attributeFilter: ['value'] });
    }
    
    // Yerel konum inputlarını dinle (Kullanıcı elle yazarsa butonu güncelle)
    document.querySelectorAll('.location-autocomplete-input').forEach(input => {
        input.addEventListener('input', updateNextButtonState);
    });

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
            // Misafir girişi aktif edildiği için login kontrolü kaldırıldı
                e.preventDefault();

                // Eğer son adımda değilsek formu gönderme (Enter tuşu koruması)
                if (currentStep < steps.length - 1) {
                    const nextBtn = document.getElementById('next-btn');
                    if (nextBtn && !nextBtn.classList.contains('hidden')) {
                        nextBtn.click();
                    }
                    return;
                }

                // Son adım için doğrulama yap
                const currentStepEl = steps[currentStep];
                const inputs = currentStepEl.querySelectorAll('input, select, textarea');
                let allValid = true;

                const locationInputsInStep = currentStepEl.querySelectorAll('.location-autocomplete-input');
                for (const locInput of locationInputsInStep) {
                    const questionId = locInput.dataset.questionId;
                    const hiddenInput = document.getElementById('location-answer-' + questionId);
                    if (locInput.dataset.required === 'true' && !hiddenInput.value) {
                        allValid = false;
                        locInput.setCustomValidity('Lütfen listeden geçerli bir adres seçin.');
                        locInput.reportValidity();
                        locInput.addEventListener('input', () => locInput.setCustomValidity(''), { once: true });
                        break;
                    }
                }
                if (!allValid) return;

                for (const input of inputs) {
                    if (input.classList.contains('validation-proxy')) {
                        continue; // Skip this input, it's handled by the custom validation above
                    }
                    if (!input.checkValidity()) {
                        allValid = false;
                        input.reportValidity();
                        break;
                    }
                }
                if (!allValid) return;

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

                form.submit();
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

            // GTM Adım Takibi (Form Step Tracking)
            if (direction === 'next') {
                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({
                    'event': 'form_step_complete',
                    'step_number': currentStep + 1 // Tamamlanan adım (1, 2, 3...)
                });
            }

            currentStep = nextStepIndex;
            updateButtons();
            updateNextButtonState(); // Buton durumunu güncelle (Konum sorusu var mı yok mu?)
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
        
        // Mevcut adımda konum sorusu var mı?
        const currentStepEl = steps[currentStep];
        const locationInput = currentStepEl.querySelector('.location-autocomplete-input');
        const hasLocationQuestion = locationInput !== null || currentStepEl.querySelector('.btn-gps-location') !== null;
        const isLocalFilled = locationInput && locationInput.value.trim() !== '';

        // Eğer konum yoksa VE bu adımda konum soruluyorsa VE yerel input boşsa otomatik bulmaya çalış
        if (isLocationRequired && (!addr || addr.trim() === '') && hasLocationQuestion && !isLocalFilled) {
            e.preventDefault();
            e.stopPropagation();
            
            const originalText = nextBtn.innerHTML;
            nextBtn.disabled = true;
            nextBtn.innerHTML = '<span class="material-symbols-outlined animate-spin">progress_activity</span> Konum Bulunuyor...';

            if (!navigator.geolocation) {
                openLocationModal();
                nextBtn.disabled = false;
                nextBtn.innerHTML = originalText;
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const geocoder = new google.maps.Geocoder();
                    
                    geocoder.geocode({ location: { lat: lat, lng: lng } }, (results, status) => {
                        if (status === "OK" && results[0]) {
                            processLocationData(results[0].formatted_address, lat, lng, results[0].address_components);
                            // Kısa bir gecikmeyle sonraki adıma geç
                            setTimeout(() => {
                                nextBtn.disabled = false;
                                changeStep('next');
                            }, 500);
                        } else {
                            openLocationModal(); // Bulunamazsa modal aç
                            nextBtn.disabled = false;
                            nextBtn.innerHTML = originalText;
                        }
                    });
                },
                (error) => {
                    console.warn("Geolocation error:", error);
                    openLocationModal(); // Hata veya red durumunda modal aç
                    nextBtn.disabled = false;
                    nextBtn.innerHTML = originalText;
                },
                { enableHighAccuracy: true, timeout: 8000 }
            );
            return;
        }

        const inputs = currentStepEl.querySelectorAll('input, select, textarea');
        let allValid = true;

        // Custom validation for location inputs
        const locationInputsInStep = currentStepEl.querySelectorAll('.location-autocomplete-input');
        for (const locInput of locationInputsInStep) {
            const questionId = locInput.dataset.questionId;
            const hiddenInput = document.getElementById('location-answer-' + questionId);
            if (locInput.dataset.required === 'true' && !hiddenInput.value) {
                allValid = false;
                locInput.setCustomValidity('Lütfen listeden geçerli bir adres seçin.');
                locInput.reportValidity();
                // Clear custom validity after showing it, so user can type again
                locInput.addEventListener('input', () => locInput.setCustomValidity(''), { once: true });
                break;
            }
        }
        if (!allValid) return; // Stop if location validation failed

        for (const input of inputs) {
            if (input.classList.contains('validation-proxy')) {
                continue; // Skip this input, it's handled by the custom validation above
            }
            if (!input.checkValidity()) {
                allValid = false;
                input.reportValidity();
                break;
            }
        }

        if (allValid && currentStep < steps.length - 1) {
            // YENİ: 1. Adımdan (Step 0) geçerken eğer global konum yoksa, zorla konum iste
            // Bu sayede "Online / Konumsuz" durumunun önüne geçilir.
            const currentAddr = document.querySelector('input[name="g_address"]').value;
            
            if (currentStep === 0 && (!currentAddr || currentAddr.trim() === '')) {
                e.preventDefault();
                e.stopPropagation();
                
                const originalText = nextBtn.innerHTML;
                nextBtn.disabled = true;
                nextBtn.innerHTML = '<span class="material-symbols-outlined animate-spin">progress_activity</span> Konum Alınıyor...';

                if (!navigator.geolocation) {
                    openLocationModal();
                    nextBtn.disabled = false;
                    nextBtn.innerHTML = originalText;
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        const geocoder = new google.maps.Geocoder();
                        
                        geocoder.geocode({ location: { lat: lat, lng: lng } }, (results, status) => {
                            if (status === "OK" && results[0]) {
                                processLocationData(results[0].formatted_address, lat, lng, results[0].address_components);
                                setTimeout(() => {
                                    nextBtn.disabled = false;
                                    nextBtn.innerHTML = originalText;
                                    changeStep('next');
                                }, 500);
                            } else {
                                openLocationModal();
                                nextBtn.disabled = false;
                                nextBtn.innerHTML = originalText;
                            }
                        });
                    },
                    (error) => {
                        console.warn("Geolocation error:", error);
                        // Konum alınamazsa modalı açarak manuel seçtirt
                        openLocationModal();
                        nextBtn.disabled = false;
                        nextBtn.innerHTML = originalText;
                    },
                    { enableHighAccuracy: true, timeout: 8000 }
                );
                return;
            }

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

    // Telefon Maskeleme (0XXX XXX XX XX)
    const phoneInputs = document.querySelectorAll('.phone-mask');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            
            // 0 ile başlamıyorsa ekle
            if (value.length > 0 && value[0] !== '0') {
                value = '0' + value;
            }
            
            if (value.length > 11) value = value.substring(0, 11);

            let formatted = '';
            if (value.length > 0) formatted += value.substring(0, 1);
            if (value.length > 1) formatted += value.substring(1, 4);
            if (value.length > 4) formatted += ' ' + value.substring(4, 7);
            if (value.length > 7) formatted += ' ' + value.substring(7, 9);
            if (value.length > 9) formatted += ' ' + value.substring(9, 11);

            e.target.value = formatted;
        });
    });

    // GTM Begin Checkout (Form Başlatma)
    <?php if ($category): ?>
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
        'event': 'begin_checkout',
        'ecommerce': {
            'items': [{
                'item_id': '<?= $category['id'] ?>',
                'item_name': <?= json_encode($category['name']) ?>,
                'item_category': 'Hizmetler'
            }]
        }
    });
    <?php endif; ?>
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

// Takı Kutusu Seçim Mantığı
function toggleJewelrySelection(element, value, questionId) {
    const input = document.getElementById('jewelry-answer-' + questionId);
    const counter = document.getElementById('jewelry-counter-' + questionId);
    let currentValues = input.value ? input.value.split(',') : [];

    if (element.classList.contains('selected')) {
        element.classList.remove('selected');
        currentValues = currentValues.filter(v => v !== value);
    } else {
        element.classList.add('selected');
        currentValues.push(value);
    }

    input.value = currentValues.join(',');
    if(counter) counter.innerText = currentValues.length + ' Model Seçildi';
    
    // Trigger change event for autosave
    input.dispatchEvent(new Event('change', { bubbles: true }));
}
</script>

<?php require_once 'includes/footer.php'; ?>
