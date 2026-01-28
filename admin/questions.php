<?php
require_once '../config/db.php';
require_once 'includes/header.php';

$categoryId = $_GET['category_id'] ?? null;

// Kategori ID yoksa kategori listesini göster
if (!$categoryId) {
    // Sayfalama ve Arama
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';

    $where = "";
    $params = [];
    if ($search) {
        $where = "WHERE name LIKE ?";
        $params[] = "%$search%";
    }

    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM categories $where");
    $totalStmt->execute($params);
    $totalCategories = $totalStmt->fetchColumn();
    $totalPages = ceil($totalCategories / $limit);

    $sql = "SELECT * FROM categories $where ORDER BY name ASC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll();
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

        <!-- Arama Kutusu -->
        <form method="GET" class="mb-6 relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Kategori ara..." class="w-full pl-10 pr-4 py-3 rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
        </form>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <?php if (empty($categories)): ?>
                <div class="p-8 text-center text-slate-500">Henüz hiç kategori eklenmemiş.</div>
            <?php else: ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach($categories as $cat): ?>
                    <a href="questions.php?category_id=<?= $cat['id'] ?>" class="category-item block p-4 hover:bg-slate-50 transition-colors group">
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
                
                <!-- Sayfalama -->
                <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-center">
                    <div class="flex gap-2">
                        <?php 
                        $queryParams = $_GET;
                        unset($queryParams['page']);
                        $queryString = http_build_query($queryParams);
                        $baseUrl = '?' . ($queryString ? $queryString . '&' : '');
                        ?>
                        
                        <?php if ($page > 1): ?>
                            <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" class="w-8 h-8 flex items-center justify-center bg-white border border-slate-200 rounded hover:bg-slate-100 text-slate-600 transition-colors"><span class="material-symbols-outlined text-sm">chevron_left</span></a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="<?= $baseUrl ?>page=<?= $i ?>" class="w-8 h-8 flex items-center justify-center border rounded font-medium text-sm transition-colors <?= $i === $page ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" class="w-8 h-8 flex items-center justify-center bg-white border border-slate-200 rounded hover:bg-slate-100 text-slate-600 transition-colors"><span class="material-symbols-outlined text-sm">chevron_right</span></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
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

// Silme İşlemi
if (isset($_GET['delete_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM category_questions WHERE id = ?");
        $stmt->execute([$_GET['delete_id']]);
        header("Location: questions.php?category_id=" . $categoryId . "&msg=deleted");
        exit;
    } catch (Exception $e) {
        $errorMsg = "Silme hatası: " . $e->getMessage();
    }
}

// Yeni Soru Ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    try {
        $qText = trim($_POST['new_question_text']);
        $qType = $_POST['new_input_type'];
        $qRequired = isset($_POST['new_is_required']) ? 1 : 0;
        $qSort = intval($_POST['new_sort_order']);
        $qOptionsStr = $_POST['new_options'] ?? '';
        
        $qOptionsJson = null;
        if (!empty($qOptionsStr)) {
            $opts = array_filter(array_map('trim', explode("\n", $qOptionsStr)));
            if (!empty($opts)) {
                $qOptionsJson = json_encode(array_values($opts), JSON_UNESCAPED_UNICODE);
            }
        }

        if (!empty($qText)) {
            $stmt = $pdo->prepare("INSERT INTO category_questions (category_id, question_text, input_type, options, is_required, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$categoryId, $qText, $qType, $qOptionsJson, $qRequired, $qSort]);
            $successMsg = "Yeni soru başarıyla eklendi.";
        }
    } catch (Exception $e) {
        $errorMsg = "Ekleme hatası: " . $e->getMessage();
    }
}

// İşlem: Soruları Güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_questions'])) {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            foreach ($_POST['questions'] as $qId => $qData) {
                $questionText = trim($qData['question_text']);
                $inputType = $qData['input_type'];
                $isRequired = isset($qData['is_required']) ? 1 : 0;
                $sortOrder = intval($qData['sort_order']);
                $optionsStr = $qData['options'] ?? '';
                
                // Seçenekleri işle (Satır satır)
                $optionsJson = null;
                if (!empty($optionsStr)) {
                    $optionsArray = array_filter(array_map('trim', explode("\n", $optionsStr)));
                    if (!empty($optionsArray)) {
                        $optionsJson = json_encode(array_values($optionsArray), JSON_UNESCAPED_UNICODE);
                    }
                }

                $stmtUpdate = $pdo->prepare("UPDATE category_questions SET question_text = ?, input_type = ?, options = ?, is_required = ?, sort_order = ? WHERE id = ?");
                $stmtUpdate->execute([$questionText, $inputType, $optionsJson, $isRequired, $sortOrder, $qId]);
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

// Soru tiplerini veritabanından çek
$inputTypes = [];
try {
    $stmtTypes = $pdo->query("SELECT type_key, name FROM question_types WHERE is_active = 1 ORDER BY sort_order ASC");
    while ($row = $stmtTypes->fetch()) {
        $inputTypes[$row['type_key']] = $row['name'];
    }
} catch (Exception $e) {
    // Tablo yoksa varsayılanları kullan (Güvenlik için)
    $inputTypes = ['text' => 'Kısa Metin', 'textarea' => 'Uzun Metin', 'select' => 'Seçim'];
}
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
        <button onclick="openModal('addQuestionModal')" class="ml-auto bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors">
            <span class="material-symbols-outlined text-lg">add_circle</span> Yeni Soru Ekle
        </button>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">Soru başarıyla silindi.</div>
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
                                <div class="flex items-center bg-white border border-slate-300 rounded-lg px-2 py-1">
                                    <span class="text-xs text-slate-500 mr-2 font-bold">Sıra:</span>
                                    <input type="number" name="questions[<?= $q['id'] ?>][sort_order]" value="<?= $q['sort_order'] ?>" class="w-12 text-xs border-0 p-0 focus:ring-0 text-center font-bold text-slate-700 bg-transparent">
                                </div>
                                
                                <select name="questions[<?= $q['id'] ?>][input_type]" class="text-xs border-slate-300 rounded-lg py-1 pl-2 pr-6 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                                    <?php foreach($inputTypes as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= $q['input_type'] == $key ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>

                                <label class="flex items-center gap-1 cursor-pointer bg-white border border-slate-300 rounded-lg px-2 py-1">
                                    <input type="checkbox" name="questions[<?= $q['id'] ?>][is_required]" value="1" <?= $q['is_required'] ? 'checked' : '' ?> class="rounded text-indigo-600 focus:ring-indigo-500 w-3 h-3">
                                    <span class="text-xs font-bold text-slate-600">Zorunlu</span>
                                </label>
                            </div>
                            <a href="questions.php?category_id=<?= $categoryId ?>&delete_id=<?= $q['id'] ?>" onclick="return confirm('Bu soruyu silmek istediğinize emin misiniz?')" class="text-red-400 hover:text-red-600 p-1 rounded hover:bg-red-50 transition-colors" title="Soruyu Sil">
                                <span class="material-symbols-outlined text-lg">delete</span>
                            </a>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Soru Başlığı</label>
                                <input type="text" name="questions[<?= $q['id'] ?>][question_text]" value="<?= htmlspecialchars($q['question_text']) ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>
                            
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
                                    <textarea name="questions[<?= $q['id'] ?>][options]" rows="3" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono placeholder:text-slate-300" placeholder="Sadece Seçim/Radio/Checkbox için gereklidir."><?= htmlspecialchars($optionsText) ?></textarea>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Add Question Modal -->
<div id="addQuestionModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all scale-100">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-800">Yeni Soru Ekle</h3>
            <button onclick="closeModal('addQuestionModal')" class="text-slate-400 hover:text-slate-600 transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="add_question" value="1">
            
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Soru Metni</label>
                <input type="text" name="new_question_text" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Örn: Kaç adet ihtiyacınız var?">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Soru Tipi</label>
                    <select name="new_input_type" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <?php foreach($inputTypes as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Sıralama</label>
                    <input type="number" name="new_sort_order" value="<?= count($questions) + 1 ?>" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Seçenekler (Opsiyonel)</label>
                <textarea name="new_options" rows="3" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono" placeholder="Seçim listesi, radio veya checkbox için her satıra bir seçenek yazın."></textarea>
            </div>

            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="new_is_required" value="1" checked class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                <span class="text-sm font-bold text-slate-700">Bu soru zorunlu olsun</span>
            </label>

            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closeModal('addQuestionModal')" class="px-4 py-2 text-slate-600 font-bold hover:bg-slate-50 rounded-lg transition-colors text-sm">İptal</button>
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition-colors text-sm">Ekle</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
</script>

<?php require_once 'includes/footer.php'; ?>