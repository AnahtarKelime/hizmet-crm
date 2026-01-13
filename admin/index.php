<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// İstatistikleri çek
$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'providers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'provider'")->fetchColumn(),
    'demands' => $pdo->query("SELECT COUNT(*) FROM demands")->fetchColumn(),
    'categories' => $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
];
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <div class="text-slate-500 text-sm font-medium mb-1">Toplam Kullanıcı</div>
        <div class="text-3xl font-black text-slate-800"><?= $stats['users'] ?></div>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <div class="text-slate-500 text-sm font-medium mb-1">Hizmet Veren</div>
        <div class="text-3xl font-black text-indigo-600"><?= $stats['providers'] ?></div>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <div class="text-slate-500 text-sm font-medium mb-1">Toplam Talep</div>
        <div class="text-3xl font-black text-emerald-600"><?= $stats['demands'] ?></div>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <div class="text-slate-500 text-sm font-medium mb-1">Aktif Kategoriler</div>
        <div class="text-3xl font-black text-amber-500"><?= $stats['categories'] ?></div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 text-center">
    <div class="inline-flex items-center justify-center w-16 h-16 bg-slate-100 rounded-full mb-4">
        <span class="material-symbols-outlined text-3xl text-slate-400">construction</span>
    </div>
    <h3 class="text-lg font-bold text-slate-800 mb-2">Yönetim Paneline Hoşgeldiniz</h3>
    <p class="text-slate-500 max-w-md mx-auto">Sol menüyü kullanarak hizmetleri, kullanıcıları ve talepleri yönetebilirsiniz. Hizmet arama motorunu geliştirmek için "Kategoriler" bölümünden anahtar kelimeleri düzenleyin.</p>
</div>

<?php require_once 'includes/footer.php'; ?>