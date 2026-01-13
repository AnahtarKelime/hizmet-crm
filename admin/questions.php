<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Kategori Seçimi
$selectedCategoryId = $_GET['category_id'] ?? null;

// Kategorileri Çek
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Yeni Soru Ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $categoryId = $_POST['category_id'];
    $questionText = $_POST['question_text'];
    $inputType = $_POST['input_type'];
    $options = $_POST['options'] ? json_encode(array_map('trim', explode(',', $_POST['options'])), JSON_UNESCAPED_UNICODE) : null;
    $isRequired = isset($_POST['is_required']) ? 1 : 0;
    $sortOrder = $_POST['sort_order'] ?? 0;

    $stmt = $pdo->prepare("INSERT INTO category_questions (category_id, question_text, input_type, options, is_required, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$categoryId, $questionText, $inputType, $options, $isRequired, $sortOrder]);
    
    header("Location: questions.php?category_id=" . $categoryId);
    exit;
}

// Soruyu Silme
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM category_questions WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: questions.php?category_id=" . $selectedCategoryId);
    exit;
}

// Seçilen Kategoriye Ait Soruları Çek
$questions = [];
if ($selectedCategoryId) {
    $stmt = $pdo->prepare("SELECT * FROM category_questions WHERE category_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$selectedCategoryId]);
    $questions = $stmt->fetchAll();
}
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Dinamik Sorular</h2>
        <p class="text-slate-500 text-sm">Hizmet talebi oluşturulurken sorulacak soruları yönetin.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Sol Kolon: Kategori Seçimi ve Soru Ekleme Formu -->
    <div class="space-y-6">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <label class="block text-sm font-bold text-slate-700 mb-2">Kategori Seçin</label>
            <select onchange="window.location.href='questions.php?category_id='+this.value" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Seçiniz...</option>
                <?php foreach($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $selectedCategoryId == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($selectedCategoryId): ?>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Yeni Soru Ekle</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="add_question" value="1">
                <input type="hidden" name="category_id" value="<?= $selectedCategoryId ?>">
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Soru Metni</label>
                    <input type="text" name="question_text" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Giriş Tipi</label>
                    <select name="input_type" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="text">Kısa Metin (Text)</option>
                        <option value="number">Sayı (Number)</option>
                        <option value="textarea">Uzun Metin (Textarea)</option>
                        <option value="select">Seçim Listesi (Select)</option>
                        <option value="radio">Tekli Seçim (Radio)</option>
                        <option value="checkbox">Çoklu Seçim (Checkbox)</option>
                        <option value="date">Tarih (Date)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Seçenekler
                        <span class="text-xs text-slate-400 font-normal ml-1">(Select, Radio, Checkbox için virgülle ayırın)</span>
                    </label>
                    <textarea name="options" rows="2" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Örn: Evet, Hayır, Belirsiz"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Sıra No</label>
                        <input type="number" name="sort_order" value="0" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>
                    <div class="flex items-end pb-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_required" value="1" checked class="rounded text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-medium text-slate-700">Zorunlu Alan</span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-lg transition-colors text-sm">
                    Soruyu Ekle
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sağ Kolon: Soru Listesi -->
    <div class="lg:col-span-2">
        <?php if ($selectedCategoryId): ?>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800">Mevcut Sorular</h3>
                    <span class="text-xs font-medium bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full"><?= count($questions) ?> Soru</span>
                </div>
                
                <?php if (empty($questions)): ?>
                    <div class="p-8 text-center text-slate-500">
                        Bu kategori için henüz soru eklenmemiş.
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($questions as $q): ?>
                            <div class="p-4 hover:bg-slate-50 transition-colors flex items-start gap-4 group">
                                <div class="w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 font-bold text-xs flex-shrink-0">
                                    <?= $q['sort_order'] ?>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <h4 class="font-bold text-slate-800"><?= htmlspecialchars($q['question_text']) ?></h4>
                                        <?php if($q['is_required']): ?>
                                            <span class="text-[10px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded font-bold">Zorunlu</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center gap-4 text-xs text-slate-500">
                                        <span class="flex items-center gap-1 bg-slate-100 px-2 py-0.5 rounded">
                                            <span class="material-symbols-outlined text-[14px]">input</span>
                                            <?= ucfirst($q['input_type']) ?>
                                        </span>
                                        <?php if($q['options']): ?>
                                            <span class="flex items-center gap-1" title="<?= htmlspecialchars($q['options']) ?>">
                                                <span class="material-symbols-outlined text-[14px]">list</span>
                                                <?= count(json_decode($q['options'], true)) ?> Seçenek
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                    <a href="questions.php?category_id=<?= $selectedCategoryId ?>&delete=<?= $q['id'] ?>" onclick="return confirm('Silmek istediğinize emin misiniz?')" class="text-red-400 hover:text-red-600 p-2">
                                        <span class="material-symbols-outlined">delete</span>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="bg-slate-50 border-2 border-dashed border-slate-200 rounded-xl p-12 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-full mb-4 shadow-sm">
                    <span class="material-symbols-outlined text-3xl text-slate-400">arrow_back</span>
                </div>
                <h3 class="text-lg font-bold text-slate-700 mb-2">Kategori Seçimi Yapın</h3>
                <p class="text-slate-500 max-w-sm mx-auto">Soruları listelemek ve yeni soru eklemek için lütfen sol taraftan bir kategori seçin.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>