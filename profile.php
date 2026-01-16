<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$successMsg = '';
$errorMsg = '';

// Profil Güncelleme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($firstName) || empty($lastName) || empty($email)) {
        $errorMsg = 'Lütfen zorunlu alanları doldurun.';
    } else {
        // Şifre değişikliği varsa kontrol et
        $passwordSql = "";
        $params = [$firstName, $lastName, $phone, $email];

        if (!empty($newPassword)) {
            $stmtUser = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $currentUser = $stmtUser->fetch();

            if (!empty($currentUser['password']) && !password_verify($currentPassword, $currentUser['password'])) {
                $errorMsg = 'Mevcut şifreniz hatalı.';
            } elseif ($newPassword !== $confirmPassword) {
                $errorMsg = 'Yeni şifreler eşleşmiyor.';
            } elseif (strlen($newPassword) < 6) {
                $errorMsg = 'Yeni şifre en az 6 karakter olmalıdır.';
            } else {
                $passwordSql = ", password = ?";
                $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
        }

        // Avatar Yükleme
        if (empty($errorMsg) && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileTmpPath = $_FILES['avatar']['tmp_name'];
            $fileName = $_FILES['avatar']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($fileExtension, $allowedExtensions)) {
                $newFileName = 'avatar_' . $userId . '_' . uniqid() . '.' . $fileExtension;
                $dest_path = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $stmtAvatar = $pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
                    $stmtAvatar->execute([$dest_path, $userId]);
                }
            } else {
                $errorMsg = 'Sadece JPG, PNG ve WEBP formatları kabul edilir.';
            }
        }

        if (empty($errorMsg)) {
            try {
                $params[] = $userId;
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, email = ? $passwordSql WHERE id = ?");
                $stmt->execute($params);
                
                // Session'ı güncelle
                $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                $successMsg = 'Profil bilgileriniz başarıyla güncellendi.';
            } catch (PDOException $e) {
                $errorMsg = 'Güncelleme sırasında bir hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

// Kullanıcı bilgilerini çek
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$pageTitle = "Profil Ayarları";
require_once 'includes/header.php';
?>

<style>
    .toggle-checkbox:checked + .toggle-label { background-color: #fbbd23; }
    .toggle-checkbox:checked + .toggle-label:after { left: calc(100% - 2px); transform: translateX(-100%); }
    .toggle-label:after { content: ""; position: absolute; top: 2px; left: 2px; width: 1.25rem; height: 1.25rem; background: white; border-radius: 90px; transition: 0.3s; }
</style>

<main class="flex flex-1 justify-center py-8 px-4 md:px-10 lg:px-20 bg-background-light dark:bg-background-dark min-h-[80vh]">
    <div class="layout-content-container flex flex-col max-w-[1200px] flex-1">
        
        <!-- Page Heading -->
        <div class="flex flex-wrap justify-between gap-3 p-4 mb-6">
            <div class="flex min-w-72 flex-col gap-2">
                <h1 class="text-primary dark:text-white text-4xl font-black leading-tight tracking-[-0.033em]">Profil ve Ayarlar</h1>
                <p class="text-slate-500 dark:text-slate-400 text-base font-normal leading-normal">Kişisel bilgilerinizi güncelleyin ve bildirim tercihlerinizi yönetin.</p>
            </div>
        </div>

        <?php if ($successMsg): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 mx-4"><?= $successMsg ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6 mx-4"><?= $errorMsg ?></div>
        <?php endif; ?>

        <div class="flex flex-col lg:flex-row gap-8 px-4">
            <!-- Sidebar Navigation -->
            <aside class="flex w-full lg:w-72 flex-col gap-4 shrink-0">
                <div class="flex flex-col bg-white dark:bg-slate-900 rounded-xl p-4 shadow-sm border border-slate-200 dark:border-slate-800">
                    <div class="mb-4 px-3 flex flex-col items-center text-center">
                        <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="Profil" class="w-20 h-20 rounded-full object-cover border-2 border-slate-100 mb-3">
                        <?php else: ?>
                            <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 font-black text-2xl border-2 border-slate-200 mb-3">
                                <?= mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1) ?>
                            </div>
                        <?php endif; ?>
                        <h3 class="text-primary dark:text-white text-base font-bold"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h3>
                        <p class="text-slate-500 text-xs"><?= htmlspecialchars($user['email']) ?></p>
                    </div>
                    <nav class="flex flex-col gap-1">
                        <a class="flex items-center gap-3 px-3 py-3 rounded-lg bg-primary text-white" href="#profile">
                            <span class="material-symbols-outlined">person</span>
                            <span class="text-sm font-medium">Profil Bilgileri</span>
                        </a>
                        <a class="flex items-center gap-3 px-3 py-3 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" href="#notifications">
                            <span class="material-symbols-outlined">notifications</span>
                            <span class="text-sm font-medium">Bildirim Ayarları</span>
                        </a>
                        <a class="flex items-center gap-3 px-3 py-3 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" href="#password">
                            <span class="material-symbols-outlined">lock</span>
                            <span class="text-sm font-medium">Şifre Değiştir</span>
                        </a>
                        <?php if ($user['role'] === 'provider'): ?>
                        <a class="flex items-center gap-3 px-3 py-3 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" href="provider/leads.php">
                            <span class="material-symbols-outlined">work</span>
                            <span class="text-sm font-medium">İş Fırsatları</span>
                        </a>
                        <?php endif; ?>
                        <a class="flex items-center gap-3 px-3 py-3 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors mt-2" href="logout.php">
                            <span class="material-symbols-outlined">logout</span>
                            <span class="text-sm font-medium">Çıkış Yap</span>
                        </a>
                    </nav>
                </div>
            </aside>

            <!-- Settings Content -->
            <div class="flex-1 flex flex-col gap-8">
                <form method="POST" id="profileForm" enctype="multipart/form-data">
                    <!-- Profil Bilgileri Section -->
                    <section id="profile" class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden mb-8">
                        <h2 class="text-primary dark:text-white text-xl font-bold px-6 py-5 border-b border-slate-100 dark:border-slate-800">Profil Bilgileri</h2>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Avatar Upload -->
                            <div class="md:col-span-2 flex items-center gap-4 mb-2">
                                <div class="relative group cursor-pointer" onclick="document.getElementById('avatarInput').click()">
                                    <?php if (!empty($user['avatar_url'])): ?>
                                        <img src="<?= htmlspecialchars($user['avatar_url']) ?>" class="w-20 h-20 rounded-full object-cover border-2 border-slate-200 group-hover:opacity-75 transition-opacity" id="avatarPreview">
                                    <?php else: ?>
                                        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 font-black text-2xl border-2 border-slate-200 group-hover:bg-slate-200 transition-colors" id="avatarPreviewPlaceholder">
                                            <?= mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                        <span class="material-symbols-outlined text-slate-600 bg-white/80 rounded-full p-1">edit</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Profil Fotoğrafı</label>
                                    <input type="file" name="avatar" id="avatarInput" class="hidden" accept="image/*" onchange="previewImage(this)">
                                    <button type="button" onclick="document.getElementById('avatarInput').click()" class="text-sm text-primary font-bold hover:underline">Fotoğrafı Değiştir</button>
                                    <p class="text-xs text-slate-500 mt-1">JPG, PNG veya WEBP. Maks. 2MB.</p>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2">
                                <p class="text-slate-700 dark:text-slate-300 text-sm font-semibold">Ad</p>
                                <input name="first_name" class="w-full rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white h-12 px-4 focus:ring-primary focus:border-primary" placeholder="Adınız" value="<?= htmlspecialchars($user['first_name']) ?>"/>
                            </div>
                            <div class="flex flex-col gap-2">
                                <p class="text-slate-700 dark:text-slate-300 text-sm font-semibold">Soyad</p>
                                <input name="last_name" class="w-full rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white h-12 px-4 focus:ring-primary focus:border-primary" placeholder="Soyadınız" value="<?= htmlspecialchars($user['last_name']) ?>"/>
                            </div>
                            <div class="flex flex-col gap-2">
                                <p class="text-slate-700 dark:text-slate-300 text-sm font-semibold">Telefon Numarası</p>
                                <input name="phone" class="w-full rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white h-12 px-4 focus:ring-primary focus:border-primary" placeholder="05XX XXX XX XX" value="<?= htmlspecialchars($user['phone']) ?>"/>
                            </div>
                            <div class="flex flex-col gap-2">
                                <p class="text-slate-700 dark:text-slate-300 text-sm font-semibold">E-posta Adresi</p>
                                <input name="email" class="w-full rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white h-12 px-4 focus:ring-primary focus:border-primary" placeholder="email@örnek.com" value="<?= htmlspecialchars($user['email']) ?>"/>
                            </div>
                        </div>
                    </section>

                    <!-- Şifre Değiştir Section -->
                    <section id="password" class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden mb-8">
                        <h2 class="text-primary dark:text-white text-xl font-bold px-6 py-5 border-b border-slate-100 dark:border-slate-800">Şifre Değiştir</h2>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="flex flex-col gap-2 md:col-span-2">
                                <p class="text-slate-700 dark:text-slate-300 text-sm font-semibold">Mevcut Şifre</p>
                                <input type="password" name="current_password" class="w-full rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white h-12 px-4 focus:ring-primary focus:border-primary" placeholder="******"/>
                                <p class="text-xs text-slate-500">Şifrenizi değiştirmek istemiyorsanız boş bırakın.</p>
                            </div>
                            <div class="flex flex-col gap-2">
                                <p class="text-slate-700 dark:text-slate-300 text-sm font-semibold">Yeni Şifre</p>
                                <input type="password" name="new_password" class="w-full rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white h-12 px-4 focus:ring-primary focus:border-primary" placeholder="******"/>
                            </div>
                            <div class="flex flex-col gap-2">
                                <p class="text-slate-700 dark:text-slate-300 text-sm font-semibold">Yeni Şifre Tekrar</p>
                                <input type="password" name="confirm_password" class="w-full rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white h-12 px-4 focus:ring-primary focus:border-primary" placeholder="******"/>
                            </div>
                        </div>
                    </section>

                    <!-- Bildirim Ayarları Section -->
                    <section id="notifications" class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden mb-8">
                        <h2 class="text-primary dark:text-white text-xl font-bold px-6 py-5 border-b border-slate-100 dark:border-slate-800">Bildirim Ayarları</h2>
                        <div class="p-6">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left">
                                    <thead>
                                        <tr class="text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-100 dark:border-slate-800">
                                            <th class="py-3 font-semibold">Bildirim Türü</th>
                                            <th class="py-3 font-semibold text-center">E-posta</th>
                                            <th class="py-3 font-semibold text-center">Push</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <tr>
                                            <td class="py-5">
                                                <div class="flex flex-col">
                                                    <span class="text-slate-800 dark:text-white font-medium">Yeni Teklif Geldiğinde</span>
                                                    <span class="text-xs text-slate-500">Hizmet talebinize yeni bir teklif verildiğinde haberdar olun.</span>
                                                </div>
                                            </td>
                                            <td class="py-5 text-center">
                                                <div class="relative inline-block w-10 align-middle select-none">
                                                    <input checked="" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer hidden" id="email-offer" name="notif_email_offer" type="checkbox"/>
                                                    <label class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer relative" for="email-offer"></label>
                                                </div>
                                            </td>
                                            <td class="py-5 text-center">
                                                <div class="relative inline-block w-10 align-middle select-none">
                                                    <input checked="" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer hidden" id="push-offer" name="notif_push_offer" type="checkbox"/>
                                                    <label class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer relative" for="push-offer"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="py-5">
                                                <div class="flex flex-col">
                                                    <span class="text-slate-800 dark:text-white font-medium">Mesaj Aldığımda</span>
                                                    <span class="text-xs text-slate-500">Bir profesyonelden mesaj geldiğinde anında bildirim alın.</span>
                                                </div>
                                            </td>
                                            <td class="py-5 text-center">
                                                <div class="relative inline-block w-10 align-middle select-none">
                                                    <input checked="" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer hidden" id="email-msg" name="notif_email_msg" type="checkbox"/>
                                                    <label class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer relative" for="email-msg"></label>
                                                </div>
                                            </td>
                                            <td class="py-5 text-center">
                                                <div class="relative inline-block w-10 align-middle select-none">
                                                    <input checked="" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer hidden" id="push-msg" name="notif_push_msg" type="checkbox"/>
                                                    <label class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer relative" for="push-msg"></label>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <!-- Action Button Area -->
                    <div class="flex justify-end pt-4 pb-12">
                        <button type="submit" class="flex items-center justify-center rounded-xl h-14 bg-accent hover:bg-yellow-500 text-primary px-10 text-base font-bold leading-normal tracking-wide shadow-lg hover:scale-[1.02] transition-transform active:scale-95">
                            Değişiklikleri Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById('avatarPreview');
            if (img) {
                img.src = e.target.result;
            } else {
                var placeholder = document.getElementById('avatarPreviewPlaceholder');
                if(placeholder) placeholder.innerHTML = `<img src="${e.target.result}" class="w-full h-full rounded-full object-cover">`;
            }
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
<?php require_once 'includes/footer.php'; ?>