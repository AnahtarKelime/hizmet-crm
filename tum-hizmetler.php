<?php
require_once 'config/db.php';
$pageTitle = "Tüm Hizmetler";
require_once 'includes/header.php';

// Tüm aktif kategorileri çek
$stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC");
$allCategories = $stmt->fetchAll();

// Kategorileri gruplamak için basit bir mantık (Örn: İlk harfe göre veya sabit gruplar)
// Şimdilik veritabanında 'group' alanı olmadığı için hepsini tek bir listede veya
// manuel olarak oluşturduğumuz sanal gruplara dağıtarak gösterebiliriz.
// Daha iyi bir deneyim için kategorileri alfabetik olarak gruplayalım.

$groupedCategories = [];
foreach ($allCategories as $cat) {
    $firstLetter = mb_substr($cat['name'], 0, 1, 'UTF-8');
    // Türkçe karakter düzeltmeleri (İ -> I gibi basit gruplama için)
    $groupKey = mb_strtoupper($firstLetter, 'UTF-8');
    $groupedCategories[$groupKey][] = $cat;
}
ksort($groupedCategories);
?>

<style>
    .category-tile-overlay {
        background: linear-gradient(to top, rgba(26, 42, 108, 0.8) 0%, rgba(26, 42, 108, 0.2) 100%);
    }
    .sticky-sidebar {
        top: 100px;
    }
    .index-link.active {
        border-left: 3px solid #fbbd23;
        color: #1a2a6c;
        padding-left: 1rem;
        font-weight: 700;
    }
</style>

<main>
    <section class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-100 dark:border-slate-800 py-16">
        <div class="max-w-[1440px] mx-auto px-4 text-center">
            <h2 class="text-3xl md:text-5xl font-black text-primary dark:text-white mb-8 tracking-tight">
                Aradığınız hizmeti yüzlerce seçenek arasından kolayca bulun
            </h2>
            <div class="max-w-3xl mx-auto relative group">
                <div class="absolute inset-y-0 left-6 flex items-center pointer-events-none">
                    <span class="material-symbols-outlined text-slate-400 group-focus-within:text-primary text-3xl transition-colors">search</span>
                </div>
                <input id="service-search-page" class="w-full bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 focus:border-accent focus:ring-4 focus:ring-accent/10 rounded-2xl py-6 pl-16 pr-6 text-xl font-medium shadow-xl transition-all" placeholder="Hizmet veya kategori ara..." type="text"/>
            </div>
        </div>
    </section>

    <div class="max-w-[1440px] mx-auto px-4 py-16">
        <div class="flex flex-col lg:flex-row gap-12">
            <!-- Sol Sidebar: İndeks -->
            <aside class="hidden lg:block w-72 shrink-0">
                <div class="sticky sticky-sidebar bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-8 shadow-sm">
                    <h5 class="text-primary dark:text-accent font-black uppercase text-xs tracking-widest mb-8 pb-4 border-b border-slate-50 dark:border-slate-800">Alfabetik İndeks</h5>
                    <nav class="space-y-2 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                        <?php foreach ($groupedCategories as $letter => $cats): ?>
                            <a class="index-link flex items-center justify-between text-slate-500 hover:text-primary dark:hover:text-accent font-semibold transition-all group py-1" href="#group-<?= $letter ?>">
                                <div class="flex items-center gap-3">
                                    <span class="w-6 h-6 rounded bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-xs font-bold"><?= $letter ?></span>
                                    <span class="text-sm">ile başlayanlar</span>
                                </div>
                                <span class="text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded-sm font-bold"><?= count($cats) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </aside>

            <!-- Sağ Taraf: Hizmet Listesi -->
            <div class="flex-grow space-y-16">
                <?php foreach ($groupedCategories as $letter => $cats): ?>
                    <section class="scroll-mt-32" id="group-<?= $letter ?>">
                        <div class="flex items-center gap-4 mb-8 pb-4 border-b border-slate-100 dark:border-slate-800">
                            <span class="flex items-center justify-center w-12 h-12 rounded-xl bg-primary text-white text-2xl font-black shadow-lg shadow-primary/20"><?= $letter ?></span>
                            <h3 class="text-2xl font-bold text-primary dark:text-white">ile başlayan hizmetler</h3>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
                            <?php foreach ($cats as $cat): ?>
                                <a class="group relative aspect-[4/3] rounded-2xl overflow-hidden bg-slate-100 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 border border-slate-200 dark:border-slate-700" href="teklif-al.php?service=<?= $cat['slug'] ?>">
                                    <?php if($cat['is_featured']): ?>
                                        <div class="absolute top-3 left-3 z-10">
                                            <span class="bg-accent text-primary text-[10px] font-black px-2 py-1 rounded-md shadow-md uppercase tracking-wide">Popüler</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // Görsel varsa onu kullan, yoksa placeholder
                                    $bgImage = !empty($cat['image']) && file_exists($cat['image']) 
                                        ? htmlspecialchars($cat['image']) 
                                        : "https://placehold.co/600x800/1a2a6c/FFF?text=" . urlencode($cat['name']);
                                    ?>
                                    <img alt="<?= htmlspecialchars($cat['name']) ?>" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" src="<?= $bgImage ?>"/>
                                    
                                    <div class="absolute inset-0 category-tile-overlay flex items-end p-4">
                                        <h4 class="text-white font-bold text-sm md:text-base leading-tight"><?= htmlspecialchars($cat['name']) ?></h4>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<script>
    // Sayfa içi arama fonksiyonu
    document.getElementById('service-search-page').addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase();
        const sections = document.querySelectorAll('section[id^="group-"]');
        
        sections.forEach(section => {
            const cards = section.querySelectorAll('a.group');
            let hasVisibleCard = false;

            cards.forEach(card => {
                const title = card.querySelector('h4').textContent.toLowerCase();
                if (title.includes(query)) {
                    card.style.display = 'block';
                    hasVisibleCard = true;
                } else {
                    card.style.display = 'none';
                }
            });

            section.style.display = hasVisibleCard ? 'block' : 'none';
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>