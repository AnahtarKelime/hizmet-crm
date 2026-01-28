<?php
require_once '../config/db.php';
require_once 'includes/header.php';

$uploadDir = '../uploads/';
$subDirs = ['categories', 'documents', 'messages']; // Alt klasörler

$sort = $_GET['sort'] ?? 'date_desc';
$search = $_GET['search'] ?? '';

// Dosya Silme İşlemi
if (isset($_POST['delete_file'])) {
    $fileToDelete = $_POST['delete_file'];
    // Güvenlik: Sadece uploads klasörü içindeki dosyalara izin ver
    $realPath = realpath($uploadDir . $fileToDelete);
    
    if ($realPath && strpos($realPath, realpath($uploadDir)) === 0 && file_exists($realPath)) {
        if (unlink($realPath)) {
            $successMsg = "Dosya başarıyla silindi.";
        } else {
            $errorMsg = "Dosya silinirken hata oluştu.";
        }
    } else {
        $errorMsg = "Geçersiz dosya yolu.";
    }
}

// Toplu Dosya Silme İşlemi
if (isset($_POST['bulk_delete']) && isset($_POST['selected_files'])) {
    $filesToDelete = $_POST['selected_files'];
    $deletedCount = 0;
    $errorCount = 0;

    foreach ($filesToDelete as $fileToDelete) {
        // Güvenlik: Sadece uploads klasörü içindeki dosyalara izin ver
        $realPath = realpath($uploadDir . $fileToDelete);
        
        if ($realPath && strpos($realPath, realpath($uploadDir)) === 0 && file_exists($realPath)) {
            if (unlink($realPath)) {
                $deletedCount++;
            } else {
                $errorCount++;
            }
        }
    }

    if ($deletedCount > 0) $successMsg = "$deletedCount dosya başarıyla silindi.";
    if ($errorCount > 0) $errorMsg = "$errorCount dosya silinemedi.";
}

// Dosya Yükleme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['new_file'])) {
    $targetFolder = $_POST['target_folder'] ?? '';
    
    // Hedef klasör kontrolü
    $targetDir = $uploadDir;
    if (in_array($targetFolder, $subDirs)) {
        $targetDir .= $targetFolder . '/';
    }
    
    // Klasör yoksa oluştur
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $fileTmpPath = $_FILES['new_file']['tmp_name'];
    $fileName = $_FILES['new_file']['name'];
    $fileSize = $_FILES['new_file']['size'];
    
    // Güvenlik: Dosya uzantısı kontrolü
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));
    $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip');
    
    if ($fileSize > 10485760) {
        $errorMsg = "Dosya boyutu 10MB'dan büyük olamaz.";
    } elseif (in_array($fileExtension, $allowedfileExtensions)) {
        $newFileName = uniqid('upload_', true) . '.' . $fileExtension;
        $dest_path = $targetDir . $newFileName;

        if(move_uploaded_file($fileTmpPath, $dest_path)) {
            $successMsg = "Dosya başarıyla yüklendi.";
        } else {
            $errorMsg = "Dosya yüklenirken bir hata oluştu.";
        }
    } else {
        $errorMsg = "İzin verilmeyen dosya uzantısı: " . $fileExtension;
    }
}

// Dosyaları Listeleme Fonksiyonu
function getFiles($dir, $relativePath = '', $sort = 'date_desc', $search = '') {
    $files = [];
    if (is_dir($dir)) {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item != '.' && $item != '..') {
                $path = $dir . $item;
                if (is_file($path)) {
                    $files[] = [
                        'name' => $item,
                        'path' => $relativePath . $item,
                        'size' => filesize($path),
                        'date' => filemtime($path),
                        'type' => pathinfo($path, PATHINFO_EXTENSION)
                    ];
                }
            }
        }
    }

    // Arama Filtresi
    if (!empty($search)) {
        $files = array_filter($files, function($file) use ($search) {
            return stripos($file['name'], $search) !== false;
        });
    }

    // Sıralama
    usort($files, function($a, $b) use ($sort) {
        switch ($sort) {
            case 'date_asc':
                return $a['date'] <=> $b['date'];
            case 'date_desc':
                return $b['date'] <=> $a['date'];
            case 'size_asc':
                return $a['size'] <=> $b['size'];
            case 'size_desc':
                return $b['size'] <=> $a['size'];
            case 'name_desc':
                return strcasecmp($b['name'], $a['name']);
            case 'name_asc':
                return strcasecmp($a['name'], $b['name']);
            default:
                return $b['date'] <=> $a['date'];
        }
    });

    return $files;
}

$allFiles = [];
// Ana klasördeki dosyalar
$allFiles['Ana Klasör'] = getFiles($uploadDir, '', $sort, $search);

// Alt klasörlerdeki dosyalar
foreach ($subDirs as $subDir) {
    $allFiles[ucfirst($subDir)] = getFiles($uploadDir . $subDir . '/', $subDir . '/', $sort, $search);
}

$activeTab = $_GET['tab'] ?? 'Ana Klasör';
if (!array_key_exists($activeTab, $allFiles)) {
    $activeTab = 'Ana Klasör';
}
$files = $allFiles[$activeTab];
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Dosya Yöneticisi</h2>
        <p class="text-slate-500 text-sm">Yüklenen tüm dosyaları buradan yönetebilirsiniz.</p>
    </div>
    <div class="flex items-center gap-3">
        <button onclick="document.getElementById('uploadModal').classList.remove('hidden')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors">
            <span class="material-symbols-outlined text-lg">upload_file</span>
            Dosya Yükle
        </button>
        <form method="GET" class="flex items-center gap-2">
            <?php if (!empty($activeTab)): ?>
                <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
            <?php endif; ?>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Dosya ara..." class="rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500 py-2 px-3 w-48">
            
            <select name="sort" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500 py-2 pl-3 pr-8 cursor-pointer">
                <option value="date_desc" <?= $sort == 'date_desc' ? 'selected' : '' ?>>Tarih (Yeni > Eski)</option>
                <option value="date_asc" <?= $sort == 'date_asc' ? 'selected' : '' ?>>Tarih (Eski > Yeni)</option>
                <option value="size_desc" <?= $sort == 'size_desc' ? 'selected' : '' ?>>Boyut (Büyük > Küçük)</option>
                <option value="size_asc" <?= $sort == 'size_asc' ? 'selected' : '' ?>>Boyut (Küçük > Büyük)</option>
                <option value="name_asc" <?= $sort == 'name_asc' ? 'selected' : '' ?>>İsim (A-Z)</option>
                <option value="name_desc" <?= $sort == 'name_desc' ? 'selected' : '' ?>>İsim (Z-A)</option>
            </select>
        </form>
    </div>
</div>

<?php if (isset($successMsg)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
<?php endif; ?>
<?php if (isset($errorMsg)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
<?php endif; ?>

<form id="bulkDeleteForm" method="POST">
    <input type="hidden" name="bulk_delete" value="1">
</form>

<!-- Tabs -->
<div class="flex border-b border-slate-200 mb-6 overflow-x-auto">
    <?php foreach ($allFiles as $folderName => $folderFiles): ?>
        <a href="?tab=<?= urlencode($folderName) ?>&sort=<?= $sort ?>&search=<?= urlencode($search) ?>" class="px-6 py-3 text-sm font-bold whitespace-nowrap border-b-2 transition-colors flex items-center gap-2 <?= $activeTab === $folderName ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
            <span class="material-symbols-outlined text-lg"><?= $folderName == 'Ana Klasör' ? 'folder_open' : 'folder' ?></span>
            <?= htmlspecialchars($folderName) ?>
            <span class="ml-1 text-xs <?= $activeTab === $folderName ? 'bg-indigo-100 text-indigo-600' : 'bg-slate-100 text-slate-500' ?> px-2 py-0.5 rounded-full"><?= count($folderFiles) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
        <div class="flex items-center gap-3">
            <input type="checkbox" id="selectAll" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer" title="Tümünü Seç">
            <h3 class="font-bold text-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-indigo-600">folder</span>
                <?= htmlspecialchars($activeTab) ?>
            </h3>
        </div>
        <div class="flex items-center gap-3">
            <button type="submit" form="bulkDeleteForm" id="bulkDeleteBtn" onclick="return confirm('Seçili dosyaları silmek istediğinize emin misiniz?')" class="hidden text-red-600 hover:text-red-800 font-bold text-xs bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded transition-colors flex items-center gap-1">
                <span class="material-symbols-outlined text-sm">delete</span> Seçilileri Sil
            </button>
            <span class="text-xs font-medium bg-slate-200 text-slate-600 px-2 py-1 rounded-full"><?= count($files) ?> Dosya</span>
        </div>
    </div>
    
    <?php if (empty($files)): ?>
        <div class="p-8 text-center text-slate-500 text-sm">Bu klasörde dosya bulunmuyor.</div>
    <?php else: ?>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 p-6">
            <?php foreach ($files as $file): 
                $isImage = in_array(strtolower($file['type']), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            ?>
                <div class="group relative border border-slate-200 rounded-lg p-2 hover:shadow-md transition-all bg-white">
                    <div class="absolute top-2 left-2 z-10">
                        <input type="checkbox" name="selected_files[]" value="<?= htmlspecialchars($file['path']) ?>" form="bulkDeleteForm" class="file-checkbox rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer bg-white/80 backdrop-blur-sm">
                    </div>
                    <div class="aspect-square bg-slate-50 rounded-md mb-2 overflow-hidden flex items-center justify-center relative">
                        <?php if ($isImage): ?>
                            <img src="../uploads/<?= htmlspecialchars($file['path']) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($file['name']) ?>">
                        <?php else: ?>
                            <span class="material-symbols-outlined text-4xl text-slate-400">description</span>
                        <?php endif; ?>
                        
                        <!-- Overlay Actions -->
                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                            <a href="../uploads/<?= htmlspecialchars($file['path']) ?>" target="_blank" class="p-1.5 bg-white rounded-full text-slate-700 hover:text-indigo-600 transition-colors" title="Görüntüle">
                                <span class="material-symbols-outlined text-sm">visibility</span>
                            </a>
                            <form method="POST" onsubmit="return confirm('Bu dosyayı silmek istediğinize emin misiniz?');">
                                <input type="hidden" name="delete_file" value="<?= htmlspecialchars($file['path']) ?>">
                                <button type="submit" class="p-1.5 bg-white rounded-full text-slate-700 hover:text-red-600 transition-colors" title="Sil">
                                    <span class="material-symbols-outlined text-sm">delete</span>
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="px-1">
                        <p class="text-xs font-medium text-slate-700 truncate" title="<?= htmlspecialchars($file['name']) ?>">
                            <?= htmlspecialchars($file['name']) ?>
                        </p>
                        <div class="flex justify-between items-center mt-1 text-[10px] text-slate-400">
                            <span><?= strtoupper($file['type']) ?></span>
                            <span><?= round($file['size'] / 1024, 1) ?> KB</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.file-checkbox');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');

        function updateBulkDeleteBtn() {
            const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
            if (anyChecked) bulkDeleteBtn.classList.remove('hidden');
            else bulkDeleteBtn.classList.add('hidden');
        }

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(cb => cb.checked = this.checked);
                updateBulkDeleteBtn();
            });
        }

        checkboxes.forEach(cb => cb.addEventListener('change', updateBulkDeleteBtn));
    });
</script>

<!-- Upload Modal -->
<div id="uploadModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-800">Yeni Dosya Yükle</h3>
            <button onclick="document.getElementById('uploadModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Hedef Klasör</label>
                <select name="target_folder" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">Ana Klasör</option>
                    <?php foreach ($subDirs as $dir): ?>
                        <option value="<?= htmlspecialchars($dir) ?>"><?= ucfirst($dir) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Dosya Seç</label>
                <input type="file" name="new_file" required class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="document.getElementById('uploadModal').classList.add('hidden')" class="px-4 py-2 text-slate-600 font-bold hover:bg-slate-50 rounded-lg transition-colors text-sm">İptal</button>
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition-colors text-sm">Yükle</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>