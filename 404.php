<?php
require_once __DIR__ . '/config/db.php';

// URL ve Yönlendirme Kontrolü
$requestUri = $_SERVER['REQUEST_URI'];

try {
    // 1. Yönlendirme Kontrolü
    // Tabloların varlığını kontrol etmeden sorgu yapıyoruz, hata olursa catch yakalar (Henüz db onarımı yapılmadıysa)
    $stmt = $pdo->prepare("SELECT target_url, status_code FROM redirects WHERE source_url = ?");
    $stmt->execute([$requestUri]);
    $redirect = $stmt->fetch();

    if ($redirect) {
        header("Location: " . $redirect['target_url'], true, $redirect['status_code']);
        exit;
    }

    // 2. Loglama (Yönlendirme yoksa log tut)
    $stmt = $pdo->prepare("INSERT INTO 404_logs (url, hit_count, last_hit_at) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE hit_count = hit_count + 1, last_hit_at = NOW()");
    $stmt->execute([$requestUri]);

} catch (PDOException $e) {
    // Tablolar henüz oluşturulmamış olabilir, sessizce devam et
}

$pageTitle = "Sayfa Bulunamadı";
// 404 Header'ı gönder
http_response_code(404);
require_once __DIR__ . '/includes/header.php';
?>

<main class="flex flex-col items-center justify-center min-h-[70vh] bg-background-light dark:bg-background-dark px-4 text-center py-20">
    <div class="mb-8 relative">
        <div class="w-40 h-40 bg-slate-200 dark:bg-slate-800 rounded-full flex items-center justify-center animate-pulse">
            <span class="material-symbols-outlined text-8xl text-slate-400 dark:text-slate-600">search_off</span>
        </div>
        <div class="absolute -bottom-2 -right-2 bg-red-500 text-white w-16 h-16 rounded-full flex items-center justify-center font-black text-xl border-4 border-white dark:border-background-dark shadow-lg">
            404
        </div>
    </div>
    
    <h1 class="text-4xl md:text-6xl font-black text-slate-900 dark:text-white mb-4 tracking-tight">
        Ops! Sayfa Kayıp.
    </h1>
    
    <p class="text-lg text-slate-600 dark:text-slate-400 max-w-lg mb-10 leading-relaxed">
        Aradığınız sayfayı bulamıyoruz. Silinmiş, taşınmış veya bağlantı hatalı olabilir.
    </p>
    
    <div class="flex flex-col sm:flex-row gap-4">
        <a href="index.php" class="px-8 py-4 bg-primary text-white rounded-xl font-bold hover:bg-primary/90 transition-all shadow-lg shadow-primary/20 flex items-center justify-center gap-2 group">
            <span class="material-symbols-outlined group-hover:-translate-x-1 transition-transform">arrow_back</span>
            Anasayfaya Dön
        </a>
        <a href="iletisim.php" class="px-8 py-4 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 rounded-xl font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition-all flex items-center justify-center gap-2">
            <span class="material-symbols-outlined">support_agent</span>
            Bize Ulaşın
        </a>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>