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
                        $isHidden = $index !== 0 ? 'hidden' : '';
                    ?>
                        <div class="step-content <?= $isHidden ?>" data-step="<?= $index ?>">
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const steps = document.querySelectorAll('.step-content');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const submitBtn = document.getElementById('submit-btn');
    let currentStep = 0;

    function updateStep() {
        steps.forEach((step, index) => {
            if (index === currentStep) {
                step.classList.remove('hidden');
            } else {
                step.classList.add('hidden');
            }
        });

        // Buton yönetimi
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
            currentStep++;
            updateStep();
        }
    });

    prevBtn?.addEventListener('click', () => {
        if (currentStep > 0) {
            currentStep--;
            updateStep();
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
