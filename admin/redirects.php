<?php
require_once '../config/db.php';

// Ekleme ve Düzenleme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $source_url = trim($_POST['source_url'] ?? '');
    $target_url = trim($_POST['target_url'] ?? '');
    $status_code = intval($_POST['status_code'] ?? 302);
    $id = $_POST['redirect_id'] ?? '';

    if (!empty($source_url) && !empty($target_url)) {
        try {
            if ($id) {
                // Güncelleme
                $stmt = $pdo->prepare("UPDATE redirects SET source_url = ?, target_url = ?, status_code = ? WHERE id = ?");
                $stmt->execute([$source_url, $target_url, $status_code, $id]);
                $msg = 'updated';
            } else {
                // Ekleme
                $stmt = $pdo->prepare("INSERT INTO redirects (source_url, target_url, status_code) VALUES (?, ?, ?)");
                $stmt->execute([$source_url, $target_url, $status_code]);
                $msg = 'added';
            }
            header("Location: redirects.php?msg=" . $msg);
            exit;
        } catch (PDOException $e) {
            $errorMsg = "Hata: " . $e->getMessage();
        }
    } else {
        $errorMsg = "Kaynak ve Hedef URL zorunludur.";
    }
}

// Silme İşlemi
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM redirects WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: redirects.php?msg=deleted");
    exit;
}

require_once 'includes/header.php';

// Yönlendirmeleri Çek
$redirects = $pdo->query("SELECT * FROM redirects ORDER BY created_at DESC")->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Yönlendirmeler (301/302)</h2>
        <p class="text-slate-500 text-sm">Eski URL'leri yeni sayfalara yönlendirin.</p>
    </div>
    <button onclick="openModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors">
        <span class="material-symbols-outlined text-lg">add_link</span>
        Yeni Yönlendirme
    </button>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">
        <?php
        if ($_GET['msg'] == 'added') echo "Yönlendirme başarıyla eklendi.";
        elseif ($_GET['msg'] == 'updated') echo "Yönlendirme başarıyla güncellendi.";
        elseif ($_GET['msg'] == 'deleted') echo "Yönlendirme silindi.";
        ?>
    </div>
<?php endif; ?>
<?php if (isset($errorMsg)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"><?= $errorMsg ?></div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">Kaynak URL (Eski)</th>
                    <th class="px-6 py-4">Hedef URL (Yeni)</th>
                    <th class="px-6 py-4 hidden sm:table-cell">Kod</th>
                    <th class="px-6 py-4 hidden md:table-cell">Tarih</th>
                    <th class="px-6 py-4 text-right">İşlemler</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($redirects)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-500">Henüz yönlendirme eklenmemiş.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($redirects as $r): ?>
                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="px-6 py-4 font-mono text-xs text-slate-600 break-all">
                                <?= htmlspecialchars($r['source_url']) ?>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-indigo-600 break-all">
                                <a href="<?= htmlspecialchars($r['target_url']) ?>" target="_blank" class="hover:underline"><?= htmlspecialchars($r['target_url']) ?></a>
                            </td>
                            <td class="px-6 py-4 hidden sm:table-cell">
                                <span class="px-2 py-1 rounded text-xs font-bold <?= $r['status_code'] == 301 ? 'bg-purple-100 text-purple-700' : 'bg-yellow-100 text-yellow-700' ?>">
                                    <?= $r['status_code'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-400 hidden md:table-cell">
                                <?= date('d.m.Y', strtotime($r['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 text-right flex justify-end gap-2">
                                <button onclick='editRedirect(<?= json_encode($r) ?>)' class="text-indigo-600 hover:text-indigo-800 p-1 rounded hover:bg-indigo-50 transition-colors" title="Düzenle">
                                    <span class="material-symbols-outlined text-sm">edit</span>
                                </button>
                                <a href="redirects.php?delete=<?= $r['id'] ?>" onclick="return confirm('Silmek istediğinize emin misiniz?')" class="text-red-500 hover:text-red-700 p-1 rounded hover:bg-red-50 transition-colors" title="Sil">
                                    <span class="material-symbols-outlined text-sm">delete</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="redirectModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all scale-100">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-800" id="modalTitle">Yeni Yönlendirme Ekle</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="redirect_id" id="redirectId">
            
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Kaynak URL (Eski)</label>
                <input type="text" name="source_url" id="inputSource" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="/eski-sayfa">
                <p class="text-xs text-slate-500 mt-1">Site içi yol (örn: /hakkimizda) veya tam URL.</p>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Hedef URL (Yeni)</label>
                <input type="text" name="target_url" id="inputTarget" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="/yeni-sayfa">
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Yönlendirme Tipi</label>
                <select name="status_code" id="inputCode" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="301">301 - Kalıcı Yönlendirme (SEO için önerilir)</option>
                    <option value="302">302 - Geçici Yönlendirme</option>
                </select>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closeModal()" class="px-4 py-2 text-slate-600 font-bold hover:bg-slate-50 rounded-lg transition-colors text-sm">İptal</button>
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition-colors text-sm">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('redirectModal').classList.remove('hidden');
        document.getElementById('modalTitle').textContent = 'Yeni Yönlendirme Ekle';
        document.getElementById('redirectId').value = '';
        document.getElementById('inputSource').value = '';
        document.getElementById('inputTarget').value = '';
        document.getElementById('inputCode').value = '301';
    }

    function closeModal() {
        document.getElementById('redirectModal').classList.add('hidden');
    }

    function editRedirect(r) {
        openModal();
        document.getElementById('modalTitle').textContent = 'Yönlendirmeyi Düzenle';
        document.getElementById('redirectId').value = r.id;
        document.getElementById('inputSource').value = r.source_url;
        document.getElementById('inputTarget').value = r.target_url;
        document.getElementById('inputCode').value = r.status_code;
    }

    // Modal dışına tıklayınca kapatma
    document.getElementById('redirectModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>