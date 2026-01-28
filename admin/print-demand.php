<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$demandId = $_GET['id'] ?? null;
if (!$demandId) die("Talep ID gerekli.");

// Talep Verileri
$stmt = $pdo->prepare("
    SELECT 
        d.*, 
        u.first_name, u.last_name, u.email, u.phone,
        c.name as category_name, 
        l.city, l.district, l.neighborhood 
    FROM demands d
    LEFT JOIN users u ON d.user_id = u.id
    LEFT JOIN categories c ON d.category_id = c.id
    LEFT JOIN locations l ON d.location_id = l.id
    WHERE d.id = ?
");
$stmt->execute([$demandId]);
$demand = $stmt->fetch();

if (!$demand) die("Talep bulunamadı.");

// Cevaplar
$stmt = $pdo->prepare("
    SELECT 
        da.answer_text, 
        cq.question_text 
    FROM demand_answers da
    LEFT JOIN category_questions cq ON da.question_id = cq.id
    WHERE da.demand_id = ?
");
$stmt->execute([$demandId]);
$answers = $stmt->fetchAll();

// Site Ayarları (Logo vb.)
$settings = [];
$stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmtSettings->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$siteTitle = $settings['site_title'] ?? 'Hizmet CRM';
$logoUrl = !empty($settings['site_logo']) ? '../' . $settings['site_logo'] : null;

// Konum Formatlama
$locationText = !empty($demand['address_text']) ? $demand['address_text'] : ($demand['city'] . ' / ' . $demand['district'] . ' / ' . $demand['neighborhood']);

// Cevapları Düzenle (Konum verilerini metne çevir)
$formattedAnswers = [];
foreach ($answers as $ans) {
    $decoded = json_decode($ans['answer_text']);
    if (json_last_error() === JSON_ERROR_NONE && isset($decoded->address)) {
        $formattedAnswers[] = ['q' => $ans['question_text'], 'a' => $decoded->address];
    } else {
        $formattedAnswers[] = ['q' => $ans['question_text'], 'a' => $ans['answer_text']];
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İş Emri / Sözleşme - #<?= $demand['id'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; -webkit-print-color-adjust: exact; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body class="bg-gray-100 p-4 md:p-8">

    <div class="max-w-[210mm] mx-auto bg-white shadow-lg rounded-xl overflow-hidden print:shadow-none print:max-w-none">
        <!-- Toolbar -->
        <div class="bg-slate-800 text-white p-4 flex justify-between items-center no-print">
            <div class="text-sm font-medium">Önizleme Modu</div>
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg font-bold text-sm flex items-center gap-2 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Yazdır / PDF İndir
            </button>
        </div>

        <div class="p-8 md:p-12">
            <!-- Header -->
            <div class="flex justify-between items-start border-b-2 border-slate-100 pb-8 mb-8">
                <div>
                    <?php if($logoUrl): ?>
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="h-12 object-contain mb-4">
                    <?php else: ?>
                        <h1 class="text-2xl font-black text-slate-900 mb-2"><?= htmlspecialchars($siteTitle) ?></h1>
                    <?php endif; ?>
                    <p class="text-slate-500 text-sm">Hizmet Talep Formu ve İş Emri</p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-slate-500 mb-1">Talep No</div>
                    <div class="text-2xl font-mono font-bold text-slate-900">#<?= $demand['id'] ?></div>
                    <div class="text-sm text-slate-500 mt-2">Tarih: <?= date('d.m.Y', strtotime($demand['created_at'])) ?></div>
                </div>
            </div>

            <!-- Taraflar -->
            <div class="grid grid-cols-2 gap-12 mb-12">
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Hizmet Alan (Müşteri)</h3>
                    <div class="text-slate-800 font-bold text-lg mb-1"><?= htmlspecialchars($demand['first_name'] . ' ' . $demand['last_name']) ?></div>
                    <div class="text-slate-600 text-sm"><?= htmlspecialchars($demand['phone']) ?></div>
                    <div class="text-slate-600 text-sm"><?= htmlspecialchars($demand['email']) ?></div>
                    <div class="text-slate-600 text-sm mt-2"><?= htmlspecialchars($locationText) ?></div>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Hizmet Veren (Yüklenici)</h3>
                    <div class="border-2 border-dashed border-slate-200 rounded-lg p-4 h-full flex items-center justify-center text-slate-400 text-sm text-center">
                        Bu alan teklif onaylandığında<br>doldurulacaktır.
                    </div>
                </div>
            </div>

            <!-- İş Detayları -->
            <div class="mb-12">
                <h3 class="text-lg font-bold text-slate-900 mb-6 flex items-center gap-2">
                    <span class="w-2 h-6 bg-blue-600 rounded-full"></span>
                    İş Detayları ve Şartlar
                </h3>
                
                <div class="bg-slate-50 rounded-xl p-6 border border-slate-100">
                    <div class="grid grid-cols-1 gap-6">
                        <div class="border-b border-slate-200 pb-4 last:border-0 last:pb-0">
                            <span class="block text-xs font-bold text-slate-500 uppercase mb-1">Hizmet Kategorisi</span>
                            <span class="text-slate-900 font-medium"><?= htmlspecialchars($demand['category_name']) ?></span>
                        </div>
                        <div class="border-b border-slate-200 pb-4 last:border-0 last:pb-0">
                            <span class="block text-xs font-bold text-slate-500 uppercase mb-1">Talep Başlığı</span>
                            <span class="text-slate-900 font-medium"><?= htmlspecialchars($demand['title']) ?></span>
                        </div>
                        
                        <?php foreach($formattedAnswers as $ans): ?>
                        <div class="border-b border-slate-200 pb-4 last:border-0 last:pb-0">
                            <span class="block text-xs font-bold text-slate-500 uppercase mb-1"><?= htmlspecialchars($ans['q']) ?></span>
                            <span class="text-slate-900 font-medium whitespace-pre-wrap"><?= htmlspecialchars($ans['a']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- İmza Alanı -->
            <div class="grid grid-cols-2 gap-12 mt-20 page-break-inside-avoid">
                <div>
                    <div class="border-t-2 border-slate-300 pt-4">
                        <p class="font-bold text-slate-900 text-sm">Hizmet Alan (İmza)</p>
                        <p class="text-xs text-slate-500 mt-1">Yukarıdaki bilgilerin doğruluğunu onaylıyorum.</p>
                    </div>
                </div>
                <div>
                    <div class="border-t-2 border-slate-300 pt-4">
                        <p class="font-bold text-slate-900 text-sm">Hizmet Veren (İmza)</p>
                        <p class="text-xs text-slate-500 mt-1">İşi belirtilen şartlarda yapmayı taahhüt ediyorum.</p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-16 pt-8 border-t border-slate-100 text-center text-xs text-slate-400">
                <p>Bu belge <?= htmlspecialchars($siteTitle) ?> platformu üzerinden oluşturulmuştur. Ön sözleşme niteliğindedir.</p>
                <p class="mt-1"><?= date('d.m.Y H:i') ?> tarihinde oluşturuldu.</p>
            </div>
        </div>
    </div>

</body>
</html>