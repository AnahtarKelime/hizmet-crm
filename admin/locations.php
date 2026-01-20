<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Slug Oluşturma Fonksiyonu
function slugify($text) {
    $find = ['Ç', 'Ş', 'Ğ', 'Ü', 'İ', 'Ö', 'ç', 'ş', 'ğ', 'ü', 'ö', 'ı', '+', '#'];
    $replace = ['c', 's', 'g', 'u', 'i', 'o', 'c', 's', 'g', 'u', 'o', 'i', 'plus', 'sharp'];
    $text = strtolower(str_replace($find, $replace, $text));
    $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

$successMsg = '';
$errorMsg = '';

// Form İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_city'])) {
            $city = trim($_POST['city_name']);
            if ($city) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM locations WHERE city = ?");
                $stmt->execute([$city]);
                if ($stmt->fetchColumn() == 0) {
                    $slug = slugify($city . '-merkez-merkez');
                    $stmt = $pdo->prepare("INSERT INTO locations (city, district, neighborhood, slug) VALUES (?, 'Merkez', 'Merkez', ?)");
                    $stmt->execute([$city, $slug]);
                    $successMsg = "İl başarıyla eklendi.";
                } else {
                    $errorMsg = "Bu il zaten mevcut.";
                }
            }
        } elseif (isset($_POST['add_district'])) {
            $city = $_POST['city_ref'];
            $district = trim($_POST['district_name']);
            if ($city && $district) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM locations WHERE city = ? AND district = ?");
                $stmt->execute([$city, $district]);
                if ($stmt->fetchColumn() == 0) {
                    $slug = slugify($city . '-' . $district . '-merkez');
                    $stmt = $pdo->prepare("INSERT INTO locations (city, district, neighborhood, slug) VALUES (?, ?, 'Merkez', ?)");
                    $stmt->execute([$city, $district, $slug]);
                    $successMsg = "İlçe başarıyla eklendi.";
                } else {
                    $errorMsg = "Bu ilçe zaten mevcut.";
                }
            }
        } elseif (isset($_POST['add_neighborhood'])) {
            $city = $_POST['city_ref'];
            $district = $_POST['district_ref'];
            $neighborhood = trim($_POST['neighborhood_name']);
            if ($city && $district && $neighborhood) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM locations WHERE city = ? AND district = ? AND neighborhood = ?");
                $stmt->execute([$city, $district, $neighborhood]);
                if ($stmt->fetchColumn() == 0) {
                    $slug = slugify($city . '-' . $district . '-' . $neighborhood);
                    $stmt = $pdo->prepare("INSERT INTO locations (city, district, neighborhood, slug) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$city, $district, $neighborhood, $slug]);
                    $successMsg = "Mahalle başarıyla eklendi.";
                } else {
                    $errorMsg = "Bu mahalle zaten mevcut.";
                }
            }
        } elseif (isset($_POST['edit_neighborhood'])) {
            $id = $_POST['neighborhood_id'];
            $newName = trim($_POST['neighborhood_name']);
            
            if ($id && $newName) {
                // Mevcut kaydı çekip slug oluşturmak için şehir ve ilçe bilgisini alalım
                $stmt = $pdo->prepare("SELECT city, district FROM locations WHERE id = ?");
                $stmt->execute([$id]);
                $loc = $stmt->fetch();
                
                if ($loc) {
                    $newSlug = slugify($loc['city'] . '-' . $loc['district'] . '-' . $newName);
                    // Slug çakışması kontrolü yapılabilir ama şimdilik basit tutuyoruz
                    $stmt = $pdo->prepare("UPDATE locations SET neighborhood = ?, slug = ? WHERE id = ?");
                    $stmt->execute([$newName, $newSlug, $id]);
                    $successMsg = "Mahalle başarıyla güncellendi.";
                } else {
                    $errorMsg = "Kayıt bulunamadı.";
                }
            }
        } elseif (isset($_POST['edit_city'])) {
            $oldName = $_POST['city_old_name'];
            $newName = trim($_POST['city_new_name']);
            
            if ($oldName && $newName && $oldName !== $newName) {
                $stmt = $pdo->prepare("UPDATE locations SET city = ? WHERE city = ?");
                $stmt->execute([$newName, $oldName]);
                
                header("Location: locations.php?city=" . urlencode($newName) . "&msg=city_updated");
                exit;
            }
        } elseif (isset($_POST['edit_district'])) {
            $cityRef = $_POST['city_ref'];
            $oldName = $_POST['district_old_name'];
            $newName = trim($_POST['district_new_name']);
            
            if ($cityRef && $oldName && $newName && $oldName !== $newName) {
                $stmt = $pdo->prepare("UPDATE locations SET district = ? WHERE city = ? AND district = ?");
                $stmt->execute([$newName, $cityRef, $oldName]);
                
                header("Location: locations.php?city=" . urlencode($cityRef) . "&district=" . urlencode($newName) . "&msg=district_updated");
                exit;
            }
        }
    } catch (PDOException $e) {
        $errorMsg = "Veritabanı hatası: " . $e->getMessage();
    }
}

// Silme İşlemi (Tekil Kayıt)
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM locations WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: locations.php?msg=deleted");
    exit;
}

/* Eski Silme İşlemleri (Geriye dönük uyumluluk veya temizlik için kaldırılabilir)
// Silme İşlemi (Mahalle)
if (isset($_GET['delete_neighborhood'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM locations WHERE id = ?");
        $stmt->execute([$_GET['delete_neighborhood']]);
        $successMsg = "Mahalle silindi.";
    } catch (PDOException $e) {
        $errorMsg = "Silme hatası: " . $e->getMessage();
    }
}

// Silme İşlemi (İl)
if (isset($_GET['delete_city'])) {
    $cityToDelete = $_GET['delete_city'];
    try {
        $stmt = $pdo->prepare("DELETE FROM locations WHERE city = ?");
        $stmt->execute([$cityToDelete]);
        if ($selectedCity === $cityToDelete) {
            header("Location: locations.php?msg=city_deleted");
            exit;
        }
        $successMsg = "İl ve bağlı tüm veriler silindi.";
    } catch (PDOException $e) {
        $errorMsg = "Silme hatası: " . $e->getMessage();
    }
}

// Silme İşlemi (İlçe)
if (isset($_GET['delete_district']) && isset($_GET['city_ref'])) {
    $districtToDelete = $_GET['delete_district'];
    $cityRef = $_GET['city_ref'];
    try {
        $stmt = $pdo->prepare("DELETE FROM locations WHERE city = ? AND district = ?");
        $stmt->execute([$cityRef, $districtToDelete]);
        if ($selectedDistrict === $districtToDelete) {
            header("Location: locations.php?city=" . urlencode($cityRef) . "&msg=district_deleted");
            exit;
        }
        $successMsg = "İlçe ve bağlı tüm veriler silindi.";
    } catch (PDOException $e) {
        $errorMsg = "Silme hatası: " . $e->getMessage();
    }
}
*/

// Verileri Çek
// Şehir listesi (Modal için)
$cities = $pdo->query("SELECT DISTINCT city FROM locations ORDER BY city ASC")->fetchAll(PDO::FETCH_COLUMN);

// İstatistikler
$totalLocations = $pdo->query("SELECT COUNT(*) FROM locations")->fetchColumn();

if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'deleted') $successMsg = "Kayıt başarıyla silindi.";
    if ($_GET['msg'] == 'city_updated') $successMsg = "İl adı başarıyla güncellendi.";
    if ($_GET['msg'] == 'district_updated') $successMsg = "İlçe adı başarıyla güncellendi.";
}
?>

<!-- Styles & Scripts specific to this page -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.tailwindcss.min.css">
<style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
    
    /* DataTables Customization */
    .dataTables_wrapper .dataTables_length select { padding-right: 2rem; border-radius: 0.5rem; border-color: #e2e8f0; }
    .dataTables_wrapper .dataTables_filter input { border-radius: 0.5rem; border-color: #e2e8f0; padding: 0.5rem 1rem; }
    .dataTables_wrapper .dataTables_filter input:focus { border-color: #4f46e5; outline: none; box-shadow: 0 0 0 1px #4f46e5; }
    table.dataTable.no-footer { border-bottom: 1px solid #e2e8f0; }
    
    /* Pagination Colors */
    .dataTables_wrapper .dataTables_paginate .paginate_button.current, 
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background: #4f46e5 !important;
        color: white !important;
        border-color: #4f46e5 !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #4f46e5 !important;
        color: white !important;
        border-color: #4f46e5 !important;
    }
</style>

<div class="flex flex-col flex-1 px-4 lg:px-10 py-6 max-w-[1440px] mx-auto w-full">
    <!-- PageHeading -->
    <div class="flex flex-wrap justify-between items-end gap-3 pb-6 border-b border-gray-200 dark:border-gray-800 mb-6">
        <div class="flex flex-col gap-1">
            <p class="text-indigo-600 dark:text-white text-3xl font-black leading-tight tracking-[-0.033em]">Lokasyon Yönetimi</p>
            <p class="text-gray-500 dark:text-gray-400 text-base font-normal">Türkiye geneli il, ilçe ve mahalle veritabanı hiyerarşisi.</p>
        </div>
        <div class="flex gap-3">
            <button class="flex items-center gap-2 min-w-[120px] cursor-pointer justify-center rounded-lg h-10 px-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-indigo-600 hover:bg-indigo-50 transition-colors text-sm font-bold">
                <span class="material-symbols-outlined text-sm">cloud_download</span>
                Dışa Aktar
            </button>
            <button class="flex items-center gap-2 min-w-[120px] cursor-pointer justify-center rounded-lg h-10 px-4 bg-indigo-600 hover:bg-indigo-700 transition-colors text-white text-sm font-bold">
                <span class="material-symbols-outlined text-sm">analytics</span>
                Genel Rapor
            </button>
        </div>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
    <?php endif; ?>

    <!-- Main Grid Layout -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <div class="flex gap-3 mb-6">
            <button onclick="openModal('addCityModal')" class="flex items-center gap-1 bg-indigo-50 text-indigo-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-indigo-100 transition-all">
                <span class="material-symbols-outlined text-sm">add</span> Yeni İl Ekle
            </button>
            <button onclick="openModal('addDistrictModal')" class="flex items-center gap-1 bg-indigo-50 text-indigo-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-indigo-100 transition-all">
                <span class="material-symbols-outlined text-sm">add</span> Yeni İlçe Ekle
            </button>
            <button onclick="openModal('addNeighborhoodModal')" class="flex items-center gap-1 bg-indigo-50 text-indigo-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-indigo-100 transition-all">
                <span class="material-symbols-outlined text-sm">add</span> Yeni Mahalle Ekle
            </button>
        </div>

        <table id="locationsTable" class="w-full text-left text-sm text-slate-600">
            <thead class="bg-indigo-600 text-white font-bold border-b border-indigo-600">
                <tr>
                    <th class="px-6 py-4 rounded-tl-lg">ID</th>
                    <th class="px-6 py-4">İl</th>
                    <th class="px-6 py-4">İlçe</th>
                    <th class="px-6 py-4">Mahalle</th>
                    <th class="px-6 py-4">Slug</th>
                    <th class="px-6 py-4 text-right rounded-tr-lg">İşlemler</th>
                </tr>
            </thead>
        </table>
    </div>

    <!-- Footer Summary Info -->
    <div class="mt-8 flex flex-wrap gap-6 items-center justify-between p-6 bg-white dark:bg-[#1c1f2b] rounded-xl border border-gray-200 dark:border-gray-800">
        <div class="flex items-center gap-8">
            <div class="flex flex-col">
                <span class="text-xs text-gray-500 font-medium">Toplam Kayıtlı Lokasyon</span>
                <span class="text-xl font-black text-primary dark:text-white mt-0.5"><?= number_format($totalLocations) ?></span>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 px-4 py-2 rounded-lg flex items-center gap-2">
                <span class="material-symbols-outlined text-lg">info</span>
                <span class="text-xs font-semibold">Tüm değişiklikler anlık olarak yayına alınır.</span>
            </div>
            <a href="locations.php" class="bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 p-2 rounded-lg transition-colors flex items-center justify-center">
                <span class="material-symbols-outlined">refresh</span>
            </a>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.tailwindcss.min.js"></script>
<script>
    $(document).ready(function() {
        new DataTable('#locationsTable', {
            ajax: {
                url: 'get-locations.php',
                type: 'POST'
            },
            processing: true,
            serverSide: true,
            columns: [
                { data: 0 },
                { data: 1 },
                { data: 2 },
                { data: 3 },
                { data: 4 },
                { data: 5, orderable: false }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json'
            }
        });
    });
</script>

<!-- Modals -->
<div id="addCityModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-800">Yeni İl Ekle</h3>
            <button onclick="closeModal('addCityModal')" class="text-slate-400 hover:text-slate-600"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="add_city" value="1">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">İl Adı</label>
                <input type="text" name="city_name" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Örn: İzmir">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('addCityModal')" class="px-4 py-2 text-slate-600 font-bold hover:bg-slate-50 rounded-lg transition-colors text-sm">İptal</button>
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition-colors text-sm">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<div id="addDistrictModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-800">Yeni İlçe Ekle</h3>
            <button onclick="closeModal('addDistrictModal')" class="text-slate-400 hover:text-slate-600"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="add_district" value="1">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">İl Seçin</label>
                <select name="city_ref" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <?php foreach($cities as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">İlçe Adı</label>
                <input type="text" name="district_name" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Örn: Karşıyaka">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('addDistrictModal')" class="px-4 py-2 text-slate-600 font-bold hover:bg-slate-50 rounded-lg transition-colors text-sm">İptal</button>
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition-colors text-sm">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<div id="addNeighborhoodModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-800">Yeni Mahalle Ekle</h3>
            <button onclick="closeModal('addNeighborhoodModal')" class="text-slate-400 hover:text-slate-600"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="add_neighborhood" value="1">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">İl</label>
                    <select name="city_ref" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <?php foreach($cities as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">İlçe</label>
                    <input type="text" name="district_ref" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="İlçe Adı">
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Mahalle Adı</label>
                <input type="text" name="neighborhood_name" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Örn: Bostanlı Mah.">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('addNeighborhoodModal')" class="px-4 py-2 text-slate-600 font-bold hover:bg-slate-50 rounded-lg transition-colors text-sm">İptal</button>
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition-colors text-sm">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal (Generic) -->
<div id="editNeighborhoodModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-800">Lokasyon Düzenle</h3>
            <button onclick="closeModal('editNeighborhoodModal')" class="text-slate-400 hover:text-slate-600"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="edit_neighborhood" value="1">
            <input type="hidden" name="neighborhood_id" id="edit_neighborhood_id">
            <!-- Not: Bu modal şu an sadece mahalle adını güncelliyor, tam düzenleme için geliştirilebilir -->
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Mahalle Adı</label>
                <input type="text" name="neighborhood_name" id="edit_neighborhood_name" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('editNeighborhoodModal')" class="px-4 py-2 text-slate-600 font-bold hover:bg-slate-50 rounded-lg transition-colors text-sm">İptal</button>
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition-colors text-sm">Güncelle</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
}
function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}
function openEditModal(id, city, district, name) {
    document.getElementById('edit_neighborhood_id').value = id;
    document.getElementById('edit_neighborhood_name').value = name;
    openModal('editNeighborhoodModal');
}
</script>

<?php require_once 'includes/footer.php'; ?>