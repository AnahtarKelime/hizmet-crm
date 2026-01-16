USE `hizmet_crm`;

CREATE TABLE IF NOT EXISTS `pages` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` LONGTEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mevcut Sayfaların İçeriklerini Ekle
INSERT INTO `pages` (`slug`, `title`, `content`) VALUES
('hakkimizda', 'Hakkımızda', '<!-- Hero Section -->
    <div class="w-full bg-white dark:bg-background-dark">
        <div class="max-w-[1200px] mx-auto @container py-10 px-4">
            <div class="@[480px]:p-4">
                <div class="flex min-h-[400px] flex-col gap-6 bg-cover bg-center bg-no-repeat @[480px]:gap-8 rounded-xl items-center justify-center p-8 text-center" data-alt="Modern office with professionals shaking hands" style=''background-image: linear-gradient(rgba(26, 42, 107, 0.85) 0%, rgba(26, 42, 107, 0.95) 100%), url("https://lh3.googleusercontent.com/aida-public/AB6AXuBR_xVYbvggNisAm6-o9prT41tFTr8y815f5HinQvqrXTgOHDMSdFMvz7ZXh6dU1p70HzRcOcw_ucuq1Hh9U8d7VbVmDKu3tMLfidqWBSLSl6AL2o-RanHesqMFVpd6XjXedmpEveEz1ssnGk42hVD6LHpDiutmr6GFWmgcLfOG5VtPdI46VqcRA87yTEKu792ARQ3lMPZw3ccBzmD2GA-p2ps1JpRINsYmEMRkQVrS8UyVZsR5bdzHsHk4hnqvyhKoTDMGXMX5LHY");''>
                    <div class="flex flex-col gap-4 max-w-3xl">
                        <h1 class="text-white text-4xl font-black leading-tight tracking-[-0.033em] @[480px]:text-6xl">
                            Güvenli Hizmet Almanın Adresi
                        </h1>
                        <p class="text-white/90 text-base font-normal leading-relaxed @[480px]:text-xl">
                            Türkiye''nin en güvenilir hizmet platformuyla tanışın. Profesyonellik ve şeffaflıkla her adımda yanınızdayız.
                        </p>
                    </div>
                    <div class="flex gap-4">
                        <a href="index.php" class="flex min-w-[160px] cursor-pointer items-center justify-center overflow-hidden rounded-lg h-12 px-6 bg-white text-primary text-base font-bold transition-transform hover:scale-105">
                            Hemen Keşfet
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Hakkımızda Section -->
    <section class="py-16 px-4 bg-white dark:bg-background-dark border-b border-gray-100 dark:border-gray-800">
        <div class="max-w-[1200px] mx-auto">
            <div class="text-center mb-12">
                <h2 class="text-primary dark:text-white text-3xl font-bold mb-4">Hakkımızda</h2>
                <p class="text-gray-600 dark:text-gray-400 text-lg max-w-2xl mx-auto">
                    Vizyonumuz, Türkiye genelinde her noktaya ulaşarak kullanıcılarımıza en kaliteli, hızlı ve güvenilir yerel hizmetleri ulaştırmaktır.
                </p>
            </div>
            <div class="mt-12">
                <h3 class="text-primary dark:text-white text-xl font-bold mb-8 text-center uppercase tracking-widest">Neden Biz?</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="p-8 bg-background-light dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700">
                        <div class="text-primary mb-4">
                            <span class="material-symbols-outlined text-4xl">visibility</span>
                        </div>
                        <h4 class="text-lg font-bold mb-2">Şeffaflık</h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">Fiyatlandırmadan sürece kadar her aşamada tam şeffaflık sağlıyoruz. Gizli maliyetlere son.</p>
                    </div>
                    <div class="p-8 bg-background-light dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700">
                        <div class="text-primary mb-4">
                            <span class="material-symbols-outlined text-4xl">location_on</span>
                        </div>
                        <h4 class="text-lg font-bold mb-2">Yerellik</h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">Türkiye''nin her yerinde yerel uzmanlarla çalışarak toplumsal ekonomiyi destekliyoruz.</p>
                    </div>
                    <div class="p-8 bg-background-light dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700">
                        <div class="text-primary mb-4">
                            <span class="material-symbols-outlined text-4xl">verified_user</span>
                        </div>
                        <h4 class="text-lg font-bold mb-2">Profesyonellik</h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">Sadece yetkinliği onaylanmış profesyonellerle çalışarak iş kalitesini garanti altına alıyoruz.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>'),
('kullanim-kosullari', 'Kullanım Koşulları', '<div class="max-w-4xl mx-auto px-4 py-16">
        <div class="prose prose-slate dark:prose-invert max-w-none prose-headings:font-bold prose-a:text-primary hover:prose-a:text-accent">
            <h3>1. Giriş ve Taraflar</h3>
            <p>Bu Kullanıcı Sözleşmesi ("Sözleşme"), Platform ile kullanıcı arasında akdedilmiştir.</p>
            <h3>2. Hizmetin Tanımı</h3>
            <p>Platform, hizmet almak isteyenler ile hizmet verenleri bir araya getiren bir pazaryeridir.</p>
            <h3>3. Üyelik</h3>
            <p>Kullanıcı, üyelik bilgilerinin doğruluğunu taahhüt eder.</p>
            <!-- İçeriğin devamı admin panelinden düzenlenebilir -->
        </div>
    </div>'),
('gizlilik-politikasi', 'Gizlilik Politikası', '<div class="max-w-4xl mx-auto px-4 py-16">
        <div class="prose prose-slate dark:prose-invert max-w-none prose-headings:font-bold prose-a:text-primary hover:prose-a:text-accent">
            <h3>1. Veri Sorumlusu</h3>
            <p>Kişisel verileriniz KVKK kapsamında işlenmektedir.</p>
            <h3>2. Toplanan Veriler</h3>
            <p>Kimlik, iletişim ve işlem güvenliği bilgileri toplanmaktadır.</p>
            <!-- İçeriğin devamı admin panelinden düzenlenebilir -->
        </div>
    </div>')
ON DUPLICATE KEY UPDATE title=VALUES(title);