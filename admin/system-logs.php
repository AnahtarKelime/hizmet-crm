<?php
require_once '../config/db.php';
require_once 'includes/header.php';

$logFile = '../error_log.txt';

// Logları Temizle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    file_put_contents($logFile, '');
    $successMsg = "Sistem günlükleri başarıyla temizlendi.";
}

// Log Dosyasını Oku
$logContent = '';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
}

$filter = $_GET['filter'] ?? 'all';
if ($filter === 'email') {
    $lines = explode("\n", $logContent);
    $filteredLines = array_filter($lines, function($line) {
        return stripos($line, '[Email Error]') !== false || stripos($line, 'SMTP') !== false || stripos($line, 'PHPMailer') !== false;
    });
    $logContent = implode("\n", $filteredLines);
}
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Sistem Günlükleri</h2>
        <p class="text-slate-500 text-sm">Sistem hatalarını ve log kayıtlarını inceleyin.</p>
    </div>
    <div class="flex items-center gap-3">
        <div class="flex bg-white rounded-lg border border-slate-200 p-1">
            <a href="system-logs.php" class="px-3 py-1.5 rounded-md text-xs font-bold transition-colors <?= $filter == 'all' ? 'bg-slate-100 text-slate-800' : 'text-slate-500 hover:text-slate-700' ?>">Tümü</a>
            <a href="system-logs.php?filter=email" class="px-3 py-1.5 rounded-md text-xs font-bold transition-colors <?= $filter == 'email' ? 'bg-red-50 text-red-600' : 'text-slate-500 hover:text-slate-700' ?>">E-posta Hataları</a>
        </div>
        <form method="POST" onsubmit="return confirm('Log dosyasını temizlemek istediğinize emin misiniz?');">
            <input type="hidden" name="clear_logs" value="1">
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors">
                <span class="material-symbols-outlined text-lg">delete_sweep</span>
                Temizle
            </button>
        </form>
    </div>
</div>

<?php if (isset($successMsg)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6"><?= $successMsg ?></div>
<?php endif; ?>

<div class="bg-slate-900 text-slate-300 p-6 rounded-xl shadow-sm border border-slate-800 font-mono text-xs overflow-x-auto h-[600px] overflow-y-auto whitespace-pre-wrap leading-relaxed">
<?php if (empty(trim($logContent))): ?>
<span class="text-slate-500 italic">Henüz kaydedilmiş bir hata günlüğü bulunmuyor.</span>
<?php else: ?>
<?= htmlspecialchars($logContent) ?>
<?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>