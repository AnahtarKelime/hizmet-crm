<?php
require_once '../config/db.php';
session_start();

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$pageTitle = "Başvuru Alındı";
$pathPrefix = '../';
require_once '../includes/header.php';
?>

<main class="max-w-3xl mx-auto px-4 py-20 min-h-[60vh] flex flex-col items-center justify-center text-center">
    <div class="w-24 h-24 bg-green-100 text-green-600 rounded-full flex items-center justify-center mb-8 shadow-sm animate-bounce-short">
        <span class="material-symbols-outlined text-5xl">check_circle</span>
    </div>
    
    <h1 class="text-4xl font-black text-slate-900 dark:text-white mb-4 tracking-tight">Başvurunuz Başarıyla Alındı!</h1>
    
    <p class="text-slate-600 dark:text-slate-400 text-lg max-w-xl mx-auto leading-relaxed mb-10">
        Hizmet veren olma talebiniz sistemimize ulaşmıştır. Ekibimiz bilgilerinizi ve belgelerinizi en kısa sürede inceleyecektir.
    </p>
    
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-8 w-full shadow-sm mb-10 text-left">
        <h3 class="text-slate-900 dark:text-white font-bold text-lg mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">info</span>
            Süreç Hakkında Bilgilendirme
        </h3>
        <ul class="space-y-4">
            <li class="flex gap-4">
                <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 font-bold text-sm">1</div>
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-200">İnceleme</p>
                    <p class="text-sm text-slate-500">Başvurunuz admin paneline düştü ve inceleme sırasına alındı.</p>
                </div>
            </li>
            <li class="flex gap-4">
                <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 font-bold text-sm">2</div>
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-200">Onay / Düzeltme</p>
                    <p class="text-sm text-slate-500">Belgeleriniz uygunsa onaylanacak, eksik varsa size bildirim gönderilecektir.</p>
                </div>
            </li>
            <li class="flex gap-4">
                <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 font-bold text-sm">3</div>
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-200">Hizmet Vermeye Başla</p>
                    <p class="text-sm text-slate-500">Onay sonrası profiliniz aktifleşecek ve teklif vermeye başlayabileceksiniz.</p>
                </div>
            </li>
        </ul>
    </div>

    <div class="flex gap-4">
        <a href="../index.php" class="px-8 py-3 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition-colors">
            Anasayfa
        </a>
        <a href="apply.php" class="px-8 py-3 bg-primary text-white font-bold rounded-xl hover:bg-primary/90 transition-colors shadow-lg shadow-primary/20">
            Başvuru Durumunu Gör
        </a>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>