<?php
require_once 'config/db.php';

$serviceSlug = $_GET['service'] ?? '';
$locationSlug = $_GET['location'] ?? '';

// Google Maps Parametreleri
$gAddress = $_GET['address'] ?? '';
$gLat = $_GET['lat'] ?? '';
$gLng = $_GET['lng'] ?? '';
$gCity = $_GET['city'] ?? '';
$gDistrict = $_GET['district'] ?? '';

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

<main class="max-w-3xl mx-auto px-4 py-12 min-h-[60vh]">
    <?php if ($category): ?>
        <div class="bg-white p-8 rounded-2xl shadow-xl border border-slate-100">
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
            
            <form id="wizard-form" action="save-demand.php" method="POST">
                <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                <input type="hidden" name="location_slug" value="<?= htmlspecialchars($location['slug'] ?? $locationSlug) ?>">
                
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

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script>
const isLoggedIn = <?= json_encode($isLoggedIn) ?>;
document.addEventListener('DOMContentLoaded', () => {
    const steps = document.querySelectorAll('.step-content');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const submitBtn = document.getElementById('submit-btn');
    let currentStep = 0;

    // LocalStorage İşlemleri
    const form = document.getElementById('wizard-form');
    const categoryId = form ? form.querySelector('input[name="category_id"]').value : null;
    const storageKey = categoryId ? 'demand_draft_' + categoryId : null;

    if (form && storageKey) {
        // Verileri Geri Yükle
        const savedData = localStorage.getItem(storageKey);
        if (savedData) {
            try {
                const data = JSON.parse(savedData);
                Object.keys(data).forEach(key => {
                    const value = data[key];
                    const inputs = form.querySelectorAll(`[name="${key}"]`);
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

                // Eğer kullanıcı giriş yapmışsa ve veri geri yüklendiyse bildirim göster
                if (isLoggedIn) {
                    showWelcomeBackNotification();
                }
            } catch (e) { console.error('Form verileri yüklenemedi', e); }
        }

        // Verileri Kaydet
        form.addEventListener('input', () => {
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
            localStorage.setItem(storageKey, JSON.stringify(data));
        });

        // Submit Kontrolü
        form.addEventListener('submit', (e) => {
            if (!isLoggedIn) {
                e.preventDefault();
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

    nextBtn?.addEventListener('click', () => {
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
            changeStep('prev');
        }
    });
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
