<?php
require_once 'config/db.php';
$pageTitle = "Nasıl Çalışır?";
require_once 'includes/header.php';
?>

<style>
    .dashed-path {
        background-image: url(https://lh3.googleusercontent.com/aida-public/AB6AXuAPwACzXyZdGZwqU-3XIrAWnkoClXXAueDsutsm36X5b-AHr0CTQS4HHMZUc_z-crtFsaKHUeBzdrF1z2xkh59rRAi6zQHC2e4-rXJXWlbrgee_a7aisCC5bbP6mNxUY662Pf6g3ddtX_HgAD7_QPxf1DSuFItuOS3d9CJIvCPBELIW9LNZva-OAt6l3mBjfw7rPaRNpf4kQV0N1U3ORYSC2t9Cvj-DSSt0Vu2Jni2mJIOTO1Mcgjvynher6ol00-OTALimyrg8dt4);
        background-repeat: repeat-x;
        background-position: center
    }
</style>

<main class="flex flex-col items-center justify-center py-16 px-4 sm:px-10 lg:px-40 bg-white dark:bg-background-dark">
    <div class="max-w-[1100px] w-full">
        <!-- Section Header -->
        <div class="text-center mb-16">
            <h1 class="text-primary dark:text-white text-4xl font-bold leading-tight tracking-tight mb-4">Nasıl Çalışır?</h1>
            <p class="text-gray-500 dark:text-gray-400 text-lg max-w-2xl mx-auto">Hizmet almanın en kolay yolu. Sadece birkaç adımda hayalinizdeki hizmete kavuşun.</p>
        </div>
        <!-- Process Steps -->
        <div class="relative">
            <!-- Connecting Line (Desktop Only) -->
            <div class="absolute top-24 left-0 w-full h-0.5 dashed-path opacity-50 hidden lg:block -z-0"></div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 relative z-10">
                <!-- Step 1 -->
                <div class="flex flex-col items-center group">
                    <div class="w-48 h-48 mb-8 rounded-full bg-background-light dark:bg-gray-800 flex items-center justify-center border-4 border-white dark:border-background-dark shadow-xl transition-transform group-hover:scale-105">
                        <div class="relative w-32 h-32 bg-white dark:bg-gray-700 rounded-xl shadow-sm border border-gray-100 dark:border-gray-600 p-4 overflow-hidden">
                            <div class="w-full h-2 bg-gray-100 dark:bg-gray-600 rounded mb-2"></div>
                            <div class="w-3/4 h-2 bg-gray-100 dark:bg-gray-600 rounded mb-4"></div>
                            <div class="space-y-2">
                                <div class="w-full h-8 bg-primary/10 dark:bg-primary/20 rounded flex items-center px-2">
                                    <div class="w-full h-1 bg-primary/40 rounded"></div>
                                </div>
                                <div class="w-full h-8 bg-accent/10 dark:bg-accent/20 rounded flex items-center px-2">
                                    <div class="w-2/3 h-1 bg-accent/40 rounded"></div>
                                </div>
                            </div>
                            <div class="absolute -top-1 -right-1">
                                <span class="material-symbols-outlined text-accent text-4xl drop-shadow-sm">edit_note</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-center px-4">
                        <h3 class="text-primary dark:text-white text-xl font-bold mb-3">1. İhtiyacını Belirt</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">Hangi hizmete ihtiyacın olduğunu detaylandır, sadece birkaç dakikada talebini oluştur.</p>
                    </div>
                </div>
                <!-- Step 2 -->
                <div class="flex flex-col items-center group">
                    <div class="w-48 h-48 mb-8 rounded-full bg-background-light dark:bg-gray-800 flex items-center justify-center border-4 border-white dark:border-background-dark shadow-xl transition-transform group-hover:scale-105">
                        <div class="relative w-36 h-36 flex flex-col gap-2 items-center justify-center">
                            <!-- Mini Card 1 -->
                            <div class="w-28 h-10 bg-white dark:bg-gray-700 rounded-lg shadow-md border border-gray-100 dark:border-gray-600 flex items-center justify-between px-3 -rotate-3 transition-transform group-hover:rotate-0">
                                <div class="flex flex-col">
                                    <div class="w-8 h-1 bg-gray-200 dark:bg-gray-500 rounded"></div>
                                    <div class="flex gap-0.5 mt-1">
                                        <span class="material-symbols-outlined text-[10px] text-accent fill-1">star</span>
                                        <span class="material-symbols-outlined text-[10px] text-accent fill-1">star</span>
                                        <span class="material-symbols-outlined text-[10px] text-accent fill-1">star</span>
                                    </div>
                                </div>
                                <span class="text-primary dark:text-white text-[10px] font-bold">450₺</span>
                            </div>
                            <!-- Mini Card 2 -->
                            <div class="w-32 h-12 bg-white dark:bg-gray-700 rounded-lg shadow-lg border-2 border-primary/20 dark:border-primary/40 flex items-center justify-between px-3 z-10 scale-110">
                                <div class="flex flex-col">
                                    <div class="w-12 h-1.5 bg-primary dark:bg-white rounded"></div>
                                    <div class="flex gap-0.5 mt-1">
                                        <span class="material-symbols-outlined text-xs text-accent fill-1">star</span>
                                        <span class="material-symbols-outlined text-xs text-accent fill-1">star</span>
                                        <span class="material-symbols-outlined text-xs text-accent fill-1">star</span>
                                        <span class="material-symbols-outlined text-xs text-accent fill-1">star</span>
                                        <span class="material-symbols-outlined text-xs text-accent fill-1">star</span>
                                    </div>
                                </div>
                                <span class="text-primary text-xs font-black">390₺</span>
                            </div>
                            <span class="material-symbols-outlined absolute top-0 right-2 text-accent text-3xl">compare_arrows</span>
                        </div>
                    </div>
                    <div class="text-center px-4">
                        <h3 class="text-primary dark:text-white text-xl font-bold mb-3">2. Teklifleri Karşılaştır</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">Uzmanlardan gelen fiyat tekliflerini, gerçek müşteri yorumlarını ve puanlarını incele.</p>
                    </div>
                </div>
                <!-- Step 3 -->
                <div class="flex flex-col items-center group">
                    <div class="w-48 h-48 mb-8 rounded-full bg-background-light dark:bg-gray-800 flex items-center justify-center border-4 border-white dark:border-background-dark shadow-xl transition-transform group-hover:scale-105">
                        <div class="relative w-32 h-32 flex flex-col items-center justify-center">
                            <div class="w-24 h-24 bg-white dark:bg-gray-700 rounded-full shadow-inner flex items-center justify-center border-4 border-primary/10">
                                <span class="material-symbols-outlined text-primary text-6xl">verified</span>
                            </div>
                            <div class="absolute -bottom-2 flex gap-1 bg-white dark:bg-gray-800 px-3 py-1.5 rounded-full shadow-lg border border-gray-100 dark:border-gray-600">
                                <span class="material-symbols-outlined text-accent text-xl fill-1">star</span>
                                <span class="material-symbols-outlined text-accent text-xl fill-1">star</span>
                                <span class="material-symbols-outlined text-accent text-xl fill-1">star</span>
                                <span class="material-symbols-outlined text-accent text-xl fill-1">star</span>
                                <span class="material-symbols-outlined text-accent text-xl fill-1">star</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-center px-4">
                        <h3 class="text-primary dark:text-white text-xl font-bold mb-3">3. Hizmeti Al ve Değerlendir</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">Sana en uygun uzmanı seç, hizmeti güvenle al ve deneyimini yıldızlarla puanla.</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Call to Action -->
        <div class="mt-20 flex flex-col items-center">
            <a href="index.php" class="group flex min-w-[240px] cursor-pointer items-center justify-center overflow-hidden rounded-xl h-14 px-8 bg-primary text-white text-lg font-bold leading-normal tracking-[0.015em] hover:bg-primary/90 transition-all duration-300 shadow-xl shadow-primary/20">
                <span class="truncate">Hemen Başla</span>
                <span class="material-symbols-outlined ml-2 transition-transform group-hover:translate-x-1">trending_flat</span>
            </a>
            <p class="mt-4 text-gray-400 dark:text-gray-500 text-xs font-medium uppercase tracking-widest">Binlerce uzman sizi bekliyor</p>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>