<?php
require_once '../config/db.php';
require_once 'includes/header.php';

$categoryId = $_GET['category_id'] ?? null;

// Kategori ID yoksa kategori listesini göster
if (!$categoryId) {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
    ?>
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center">
                <span class="material-symbols-outlined">quiz</span>
            </div>
            <div>
                <h2 class="text-2xl font-bold text-slate-800">Soru Yönetimi</h2>
                <p class="text-slate-500 text-sm">Sorularını düzenlemek istediğiniz kategoriyi seçin.</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <?php if (empty($categories)): ?>
                <div class="p-8 text-center text-slate-500">Henüz hiç kategori eklenmemiş.</div>
            <?php else: ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach($categories as $cat): ?>
                    <a href="questions.php?category_id=<?= $cat['id'] ?>" class="block p-4 hover:bg-slate-50 transition-colors group">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-colors">
                                    <span class="material-symbols-outlined"><?= $cat['icon'] ?: 'category' ?></span>
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-800 group-hover:text-indigo-700 transition-colors"><?= htmlspecialchars($cat['name']) ?></h4>
                                    <p class="text-xs text-slate-500"><?= $cat['is_active'] ? 'Aktif' : 'Pasif' ?></p>
                                </div>
                            </div>
                            <span class="material-symbols-outlined text-slate-300 group-hover:text-indigo-400 transition-colors">arrow_forward_ios</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    require_once 'includes/footer.php';
    exit;
}

// Kategori Bilgisi
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$categoryId]);
$category = $stmt->fetch();

if (!$category) {
    echo "<div class='p-8 text-center text-red-500'>Kategori bulunamadı.</div>";
    require_once 'includes/footer.php';
    exit;
}

// İşlem: Soruları Güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_questions'])) {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            foreach ($_POST['questions'] as $qId => $qData) {
                $questionText = trim($qData['question_text']);
                $optionsStr = $qData['options'] ?? '';
                
                // Seçenekleri işle (Satır satır)
                $optionsJson = null;
                if (!empty($optionsStr)) {
                    $optionsArray = array_filter(array_map('trim', explode("\n", $optionsStr)));
                    if (!empty($optionsArray)) {
                        $optionsJson = json_encode(array_values($optionsArray), JSON_UNESCAPED_UNICODE);
                    }
                }

                $stmtUpdate = $pdo->prepare("UPDATE category_questions SET question_text = ?, options = ? WHERE id = ?");
                $stmtUpdate->execute([$questionText, $optionsJson, $qId]);
            }
        }
        
        $pdo->commit();
        $successMsg = "Sorular başarıyla güncellendi.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Hata: " . $e->getMessage();
    }
}

// Soruları Çek
$stmt = $pdo->prepare("SELECT * FROM category_questions WHERE category_id = ? ORDER BY sort_order ASC");
$stmt->execute([$categoryId]);
$questions = $stmt->fetchAll();
?>

<div class="max-w-5xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="categories.php" class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Soru Yönetimi</h2>
            <p class="text-slate-500 text-sm">Kategori: <?= htmlspecialchars($category['name']) ?></p>
        </div>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
            <h3 class="font-bold text-slate-800">Sorular</h3>
            <button type="button" onclick="document.getElementById('questionsForm').submit()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-indigo-700 transition-colors">Değişiklikleri Kaydet</button>
        </div>
        
        <form method="POST" id="questionsForm" class="p-6 space-y-6">
            <input type="hidden" name="update_questions" value="1">
            
            <?php if (empty($questions)): ?>
                <div class="text-center text-slate-500 py-8">Bu kategoriye ait soru bulunamadı.</div>
            <?php else: ?>
                <?php foreach ($questions as $q): ?>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex items-center gap-2">
                                <span class="bg-slate-200 text-slate-600 text-xs font-bold px-2 py-1 rounded">Sıra: <?= $q['sort_order'] ?></span>
                                <span class="bg-blue-100 text-blue-700 text-xs font-bold px-2 py-1 rounded uppercase"><?= $q['input_type'] ?></span>
                                <?php if($q['is_required']): ?>
                                    <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded">Zorunlu</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Soru Başlığı</label>
                                <input type="text" name="questions[<?= $q['id'] ?>][question_text]" value="<?= htmlspecialchars($q['question_text']) ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>
                            
                            <?php if (in_array($q['input_type'], ['select', 'radio', 'checkbox'])): ?>
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-2">Seçenekler (Her satıra bir tane)</label>
                                    <?php
                                        $optionsText = "";
                                        if (!empty($q['options'])) {
                                            $opts = json_decode($q['options'], true);
                                            if (is_array($opts)) {
                                                $optionsText = implode("\n", $opts);
                                            }
                                        }
                                    ?>
                                    <textarea name="questions[<?= $q['id'] ?>][options]" rows="4" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono"><?= htmlspecialchars($optionsText) ?></textarea>
                                </div>
                            <?php else: ?>
                                <div class="flex items-center justify-center text-slate-400 text-sm italic bg-white rounded-lg border border-dashed border-slate-300 h-full">
                                    Bu soru tipi için seçenek gerekmez.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>