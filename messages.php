<?php
require_once 'config/db.php';
require_once 'includes/mail-helper.php';
require_once 'includes/push-helper.php'; // Push helper eklendi
session_start();

// Konuşmayı Silme İşlemi
if (isset($_GET['delete_offer_id'])) {
    $offerIdToDelete = $_GET['delete_offer_id'];
    $userId = $_SESSION['user_id'];

    // Güvenlik: Kullanıcının bu sohbete dahil olup olmadığını kontrol et
    $checkStmt = $pdo->prepare("
        SELECT o.id FROM offers o
        JOIN demands d ON o.demand_id = d.id
        WHERE o.id = ? AND (o.user_id = ? OR d.user_id = ?)
    ");
    $checkStmt->execute([$offerIdToDelete, $userId, $userId]);
    
    if ($checkStmt->fetch()) {
        // Kullanıcı yetkili, mesajları sil
        // Mesajları tamamen silmek yerine, silen taraf için işaretle
        $updateStmt = $pdo->prepare("UPDATE messages SET deleted_by_sender = IF(sender_id = ?, 1, deleted_by_sender), deleted_by_receiver = IF(receiver_id = ?, 1, deleted_by_receiver) WHERE offer_id = ?");
        $updateStmt->execute([$userId, $userId, $offerIdToDelete]);

        
        header("Location: messages.php?status=deleted");
        exit;
    } else {
        header("Location: messages.php?status=error");
        exit;
    }
}

// Mesaj Gönderme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiverId = $_POST['receiver_id'];
    $offerIdPost = $_POST['offer_id'];
    $messageText = trim($_POST['message']);

    // Sadece mesaj metni veya dosya varsa devam et
    if (!empty($messageText) || (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK)) {
        $userId = $_SESSION['user_id'];
        
        // Dosya Yükleme İşlemi
        $attachmentPath = null;
        $fileName = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['attachment']['size'] > 10485760) {
                header("Location: messages.php?offer_id=" . $offerIdPost . "&status=error&msg=file_too_large");
                exit;
            }
            $uploadDir = 'uploads/messages/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileTmpPath = $_FILES['attachment']['tmp_name'];
            $fileName = basename($_FILES['attachment']['name']); // Güvenlik için basename
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'webp'];
            
            // MIME Type Kontrolü
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $fileTmpPath);
            finfo_close($finfo);
            
            $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/webp'];

            if (in_array($fileExtension, $allowedExtensions) && in_array($mimeType, $allowedMimes)) {
                $newFileName = uniqid('msg_', true) . '.' . $fileExtension;
                if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
                    $attachmentPath = $uploadDir . $newFileName;
                }
            }
        }

        // Mesaj metni boşsa ve dosya yüklendiyse, mesaj metnine dosya adını yaz
        if (empty($messageText) && $attachmentPath) {
            $messageText = "Dosya gönderildi: " . $fileName;
        }

        // Mesajı ve dosya yolunu veritabanına kaydet
        $stmt = $pdo->prepare("INSERT INTO messages (offer_id, sender_id, receiver_id, message, attachment) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$offerIdPost, $userId, $receiverId, $messageText, $attachmentPath]);
        
        // Alıcı Bilgilerini Çek ve Mail Gönder
        $stmtReceiver = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
        $stmtReceiver->execute([$receiverId]);
        $receiver = $stmtReceiver->fetch();

        if ($receiver) {
            sendEmail($receiver['email'], 'new_message', [
                'name' => $receiver['first_name'] . ' ' . $receiver['last_name'],
                'sender_name' => $_SESSION['user_name'],
                'link' => getBaseUrl() . '/messages.php?offer_id=' . $offerIdPost
            ]);

            // Alıcıya Push Bildirim Gönder
            sendPushNotification(
                $receiverId,
                'Yeni Mesaj',
                $_SESSION['user_name'] . ' size bir mesaj gönderdi.',
                getBaseUrl() . '/messages.php?offer_id=' . $offerIdPost
            );
        }

        // Sayfayı yenile (POST tekrarını önlemek için redirect)
        header("Location: messages.php?offer_id=" . $offerIdPost);
        exit;
    }
}

// Teklif Kabul Etme İşlemi (Sadece Müşteri İçin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_offer'])) {
    $offerIdToAccept = $_POST['offer_id'];
    $userId = $_SESSION['user_id'];

    // Güvenlik: Sadece teklifin yapıldığı talebin sahibi (müşteri) kabul edebilir
    $checkStmt = $pdo->prepare("
        SELECT o.id, o.demand_id FROM offers o
        JOIN demands d ON o.demand_id = d.id
        WHERE o.id = ? AND d.user_id = ?
    ");
    $checkStmt->execute([$offerIdToAccept, $userId]);
    $offerData = $checkStmt->fetch();

    if ($offerData) {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE offers SET status = 'accepted' WHERE id = ?")->execute([$offerIdToAccept]);
        $pdo->prepare("UPDATE demands SET status = 'completed' WHERE id = ?")->execute([$offerData['demand_id']]);
        $pdo->commit();
        
        header("Location: messages.php?offer_id=" . $offerIdToAccept . "&status=accepted");
        exit;
    }
}

$pageTitle = "Mesajlarım";
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$offerId = $_GET['offer_id'] ?? null;

// Sohbet Listesini Çek (Sol Sidebar İçin)
$chatListStmt = $pdo->prepare("
    SELECT 
        o.id as offer_id, 
        d.title as demand_title,
        u_provider.id as provider_id, u_provider.first_name as p_name, u_provider.last_name as p_surname, u_provider.avatar_url as p_avatar, pd.business_name,
        u_customer.id as customer_id, u_customer.first_name as c_name, u_customer.last_name as c_surname, u_customer.avatar_url as c_avatar,
        (SELECT message FROM messages WHERE offer_id = o.id AND ( (sender_id = :uid_lm_s AND deleted_by_sender = 0) OR (receiver_id = :uid_lm_r AND deleted_by_receiver = 0) ) ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM messages WHERE offer_id = o.id AND ( (sender_id = :uid_lmt_s AND deleted_by_sender = 0) OR (receiver_id = :uid_lmt_r AND deleted_by_receiver = 0) ) ORDER BY created_at DESC LIMIT 1) as last_message_time,
        (SELECT COUNT(*) FROM messages WHERE offer_id = o.id AND receiver_id = :uid_uc AND is_read = 0 AND deleted_by_receiver = 0) as unread_count
    FROM offers o
    JOIN demands d ON o.demand_id = d.id
    JOIN users u_provider ON o.user_id = u_provider.id
    LEFT JOIN provider_details pd ON u_provider.id = pd.user_id
    JOIN users u_customer ON d.user_id = u_customer.id
    WHERE o.user_id = :uid_main_p OR d.user_id = :uid_main_c
    HAVING last_message IS NOT NULL
    ORDER BY last_message_time DESC
");
$chatListStmt->execute([
    'uid_lm_s' => $userId, 
    'uid_lm_r' => $userId, 
    'uid_lmt_s' => $userId, 
    'uid_lmt_r' => $userId, 
    'uid_uc' => $userId, 
    'uid_main_p' => $userId, 
    'uid_main_c' => $userId
]);
$chatList = $chatListStmt->fetchAll();

// Otomatik Seçim: Eğer offerId yoksa ve liste varsa, ilkini (en güncelini) seç
if (!$offerId && !empty($chatList)) {
    $offerId = $chatList[0]['offer_id'];
}

// Aktif Sohbet Bilgilerini Çek (Eğer offer_id varsa)
$activeChat = null;
$messages = [];
if ($offerId) {
    // Teklif ve Karşı Taraf Bilgilerini Çek
    $stmt = $pdo->prepare("
        SELECT 
            o.*, 
            d.title as demand_title,
            u_provider.id as provider_id, u_provider.first_name as p_name, u_provider.last_name as p_surname, u_provider.avatar_url as p_avatar, pd.business_name,
            u_customer.id as customer_id, u_customer.first_name as c_name, u_customer.last_name as c_surname, u_customer.avatar_url as c_avatar
        FROM offers o
        JOIN demands d ON o.demand_id = d.id
        JOIN users u_provider ON o.user_id = u_provider.id
        LEFT JOIN provider_details pd ON u_provider.id = pd.user_id
        JOIN users u_customer ON d.user_id = u_customer.id
        WHERE o.id = :offer_id AND (o.user_id = :uid1 OR d.user_id = :uid2)
    ");
    $stmt->execute(['offer_id' => $offerId, 'uid1' => $userId, 'uid2' => $userId]);
    $activeChat = $stmt->fetch();

    if ($activeChat) {
        // Karşı tarafı belirle
        $isProvider = ($userId == $activeChat['provider_id']);
        $isCustomer = ($userId == $activeChat['customer_id']);
        $chatPartnerId = $isProvider ? $activeChat['customer_id'] : $activeChat['provider_id'];
        $chatPartnerName = $isProvider ? $activeChat['c_name'] . ' ' . $activeChat['c_surname'] : ($activeChat['business_name'] ?: $activeChat['p_name'] . ' ' . $activeChat['p_surname']);
        $chatPartnerAvatar = $isProvider ? $activeChat['c_avatar'] : $activeChat['p_avatar'];
        
        // Mesajları Çek
        $stmt = $pdo->prepare("
            SELECT * FROM messages 
            WHERE offer_id = :offer_id AND ( (sender_id = :uid1 AND deleted_by_sender = 0) OR (receiver_id = :uid2 AND deleted_by_receiver = 0) )
            ORDER BY created_at ASC
        ");
        $stmt->execute(['offer_id' => $offerId, 'uid1' => $userId, 'uid2' => $userId]);
        $messages = $stmt->fetchAll();

        // Okundu olarak işaretle (Gelen mesajlar)
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE offer_id = ? AND receiver_id = ?");
        $stmt->execute([$offerId, $userId]);

        // Teklif mesajını ilk mesaj olarak ekle (Sanal Mesaj)
        if (!empty($activeChat['message'])) {
            $offerMessage = [
                'id' => 'offer_msg', // Benzersiz bir ID
                'sender_id' => $activeChat['provider_id'], // Gönderen her zaman hizmet veren
                'message' => $activeChat['message'], // offers tablosundaki message sütunu
                'created_at' => $activeChat['created_at'], // Teklif tarihi
                'attachment' => null
            ];
            array_unshift($messages, $offerMessage); // En başa ekle
        }
    }
}
?>

<style>
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
</style>

<main class="max-w-[1200px] mx-auto px-4 py-6 min-h-[80vh]">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 mb-6">
        <a class="hover:text-primary dark:hover:text-accent" href="index.php">Ana Sayfa</a>
        <span class="material-symbols-outlined text-xs">chevron_right</span>
        <span class="text-slate-900 dark:text-white font-medium">Mesajlarım</span>
    </nav>

    <?php if (isset($_GET['status']) && $_GET['status'] === 'accepted'): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">Teklif başarıyla kabul edildi.</div>
    <?php endif; ?>
    <?php if (isset($_GET['status']) && $_GET['status'] === 'deleted'): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">Sohbet başarıyla silindi.</div>
    <?php elseif (isset($_GET['status']) && $_GET['status'] === 'error'): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6">
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'file_too_large'): ?>
                Yüklenen dosya 10MB'dan büyük olamaz.
            <?php else: ?>
                İşlem sırasında bir hata oluştu veya yetkiniz yok.
            <?php endif; ?>
        </div>
    <?php endif; ?>


    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Sol Kolon: Sohbet Listesi -->
        <div class="lg:col-span-3 flex flex-col bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden h-[650px]">
            <div class="p-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50">
                <h3 class="font-bold text-slate-800 dark:text-white">Sohbetler</h3>
            </div>
            <div class="flex-1 overflow-y-auto">
                <?php if (empty($chatList)): ?>
                    <div class="p-6 text-center text-slate-500 text-sm">Henüz bir sohbetiniz yok.</div>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($chatList as $chat): 
                            $isProviderInList = ($userId == $chat['provider_id']);
                            $listPartnerName = $isProviderInList ? $chat['c_name'] . ' ' . $chat['c_surname'] : ($chat['business_name'] ?: $chat['p_name'] . ' ' . $chat['p_surname']);
                            $listPartnerAvatar = $isProviderInList ? $chat['c_avatar'] : $chat['p_avatar'];
                            $isActive = ($offerId == $chat['offer_id']);
                        ?>
                        <li class="group relative">
                            <a href="messages.php?offer_id=<?= $chat['offer_id'] ?>" class="block p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors <?= $isActive ? 'bg-slate-50 dark:bg-slate-800 border-l-4 border-primary' : '' ?>">
                                <div class="flex items-center gap-3">
                                    <?php if ($listPartnerAvatar): ?>
                                        <img src="<?= htmlspecialchars($listPartnerAvatar) ?>" class="w-10 h-10 rounded-full object-cover border border-slate-200" alt="">
                                    <?php else: ?>
                                        <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center text-slate-500 font-bold text-sm">
                                            <?= mb_substr($listPartnerName, 0, 1) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between items-start mb-0.5">
                                            <h4 class="font-bold text-sm text-slate-800 dark:text-white truncate pr-2"><?= htmlspecialchars($listPartnerName) ?></h4>
                                            <?php if ($chat['last_message_time']): ?>
                                                <span class="text-[10px] text-slate-400 whitespace-nowrap"><?= date('d.m H:i', strtotime($chat['last_message_time'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-xs text-slate-500 truncate mb-0.5"><?= htmlspecialchars($chat['demand_title']) ?></p>
                                        <div class="flex justify-between items-center">
                                            <p class="text-xs text-slate-400 truncate max-w-[140px]">
                                                <?= htmlspecialchars($chat['last_message'] ?? 'Henüz mesaj yok') ?>
                                            </p>
                                            <?php if ($chat['unread_count'] > 0): ?>
                                                <span class="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?= $chat['unread_count'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                            <a href="messages.php?delete_offer_id=<?= $chat['offer_id'] ?>" onclick="return confirm('Bu sohbeti silmek istediğinize emin misiniz? Tüm mesajlar kalıcı olarak silinecektir.')" class="absolute top-3 right-3 p-1.5 rounded-full text-slate-400 hover:bg-red-100 hover:text-red-600 opacity-0 group-hover:opacity-100 transition-all z-10" title="Sohbeti Sil">
                                <span class="material-symbols-outlined text-sm">delete</span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$activeChat): ?>
            <div class="lg:col-span-9 flex flex-col items-center justify-center bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 h-[650px] text-center p-8">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-slate-100 dark:bg-slate-800 text-slate-400 rounded-full mb-6">
                    <span class="material-symbols-outlined text-4xl">chat</span>
                </div>
                <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-2">Sohbet Seçilmedi</h2>
                <p class="text-slate-500 mb-6 max-w-md">Mesajlaşmak için sol taraftaki listeden bir sohbet seçin veya teklif detaylarından "Mesaj Gönder" butonuna tıklayın.</p>
                <a href="my-demands.php" class="px-6 py-3 bg-primary text-white rounded-xl font-bold hover:bg-primary/90 transition-all">Taleplerime Git</a>
            </div>
        <?php else: ?>
            
            <!-- Orta Kolon: Mesajlaşma Arayüzü -->
            <div class="lg:col-span-6 flex flex-col bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden h-[650px]">
                <!-- Chat Header -->
                <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
                    <div class="flex items-center gap-3">
                        <?php if ($chatPartnerAvatar): ?>
                            <img src="<?= htmlspecialchars($chatPartnerAvatar) ?>" class="w-10 h-10 rounded-full object-cover border border-slate-200" alt="">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold text-lg">
                                <?= mb_substr($chatPartnerName, 0, 1) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white leading-none"><?= htmlspecialchars($chatPartnerName) ?></h3>
                            <p class="text-xs text-slate-500 font-medium mt-1 flex items-center gap-1">
                                <?= htmlspecialchars($activeChat['demand_title']) ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="messages.php?delete_offer_id=<?= $offerId ?>" onclick="return confirm('Bu sohbeti silmek istediğinize emin misiniz? Tüm mesajlar kalıcı olarak silinecektir.')" class="text-slate-400 hover:text-red-600 p-2 rounded-full transition-colors" title="Sohbeti Sil">
                            <span class="material-symbols-outlined">delete</span>
                        </a>
                        <a href="offer-details.php?id=<?= $offerId ?>" class="text-slate-400 hover:text-primary p-2 rounded-full transition-colors" title="Teklif Detayları">
                            <span class="material-symbols-outlined">info</span>
                        </a>
                    </div>
                </div>

                <!-- Chat Messages Area -->
                <div class="flex-1 p-6 space-y-6 overflow-y-auto max-h-[500px] bg-slate-50/30" id="chatContainer">
                    <?php if (empty($messages)): ?>
                        <div class="text-center text-slate-400 text-sm py-10">Henüz mesaj yok. İlk mesajı siz gönderin!</div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): 
                            $isMe = ($msg['sender_id'] == $userId);
                        ?>
                            <div class="flex items-end gap-3 max-w-[80%] <?= $isMe ? 'ml-auto justify-end' : '' ?>">
                                <?php if (!$isMe): ?>
                                    <?php if ($chatPartnerAvatar): ?>
                                        <img src="<?= htmlspecialchars($chatPartnerAvatar) ?>" class="w-8 h-8 rounded-full object-cover border border-slate-200 shrink-0" alt="">
                                    <?php else: ?>
                                        <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center text-slate-500 font-bold text-xs shrink-0">
                                            <?= mb_substr($chatPartnerName, 0, 1) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <div class="flex flex-col gap-1 <?= $isMe ? 'items-end' : 'items-start' ?>">
                                    <div class="text-sm font-normal leading-relaxed px-4 py-3 shadow-sm <?= $isMe ? 'bg-primary text-white rounded-2xl rounded-br-none' : 'bg-white border border-slate-100 text-slate-800 rounded-2xl rounded-bl-none' ?>">
                                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                        <?php if (!empty($msg['attachment'])): ?>
                                            <div class="mt-2 pt-2 border-t border-white/20">
                                                <a href="<?= htmlspecialchars($msg['attachment']) ?>" target="_blank" class="flex items-center gap-2 text-xs font-bold hover:underline">
                                                    <span class="material-symbols-outlined text-sm">attachment</span> Dosyayı Görüntüle
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-[10px] text-slate-400">
                                        <?= date('H:i', strtotime($msg['created_at'])) ?>
                                        <?php if ($isMe): ?>
                                            • <?= isset($msg['is_read']) && $msg['is_read'] ? 'Görüldü' : 'İletildi' ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Chat Input Area -->
                <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900">
                    <form method="POST" enctype="multipart/form-data" class="flex items-center gap-2 bg-slate-50 dark:bg-slate-800 rounded-xl px-4 py-2 border border-slate-200 dark:border-slate-700">
                        <input type="hidden" name="send_message" value="1">
                        <input type="hidden" name="offer_id" value="<?= $offerId ?>">
                        <input type="hidden" name="receiver_id" value="<?= $chatPartnerId ?>">
                        
                        <input type="file" name="attachment" id="fileInput" class="hidden" onchange="document.getElementById('fileNameDisplay').textContent = this.files[0].name">
                        <button type="button" onclick="document.getElementById('fileInput').click()" class="text-slate-400 hover:text-primary transition-colors" title="Dosya Ekle">
                            <span class="material-symbols-outlined">attach_file</span>
                        </button>
                        <input name="message" class="flex-1 bg-transparent border-none focus:ring-0 text-sm py-2 text-slate-700 dark:text-slate-200 placeholder:text-slate-400" placeholder="Mesajınızı yazın..." type="text" autocomplete="off"/>
                        <button type="submit" class="bg-primary hover:bg-yellow-500 text-white w-9 h-9 rounded-lg flex items-center justify-center transition-colors shadow-sm">
                            <span class="material-symbols-outlined text-sm">send</span>
                        </button>
                    </form>
                    <div id="fileNameDisplay" class="text-xs text-slate-500 mt-1 px-4"></div>
                </div>
            </div>

            <!-- Sağ Kolon: Teklif Özeti -->
            <aside class="lg:col-span-3 sticky top-24 space-y-6">
                <!-- Main Quote Card -->
                <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-6">
                            <div class="flex gap-4">
                                <?php if ($chatPartnerAvatar): ?>
                                    <img src="<?= htmlspecialchars($chatPartnerAvatar) ?>" class="w-12 h-12 rounded-lg object-cover border border-slate-200" alt="">
                                <?php else: ?>
                                    <div class="w-12 h-12 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-xl border border-slate-200">
                                        <?= mb_substr($chatPartnerName, 0, 1) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h4 class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($chatPartnerName) ?></h4>
                                    <div class="flex items-center gap-1 mt-1 text-primary">
                                        <span class="material-symbols-outlined text-[16px] fill-1">star</span>
                                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300">4.9</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4 mb-8">
                            <div class="flex justify-between items-end">
                                <span class="text-xs text-slate-500 uppercase font-bold tracking-wider">Teklif Tutarı</span>
                                <div class="text-right">
                                    <span class="text-3xl font-black text-slate-900 dark:text-white"><?= number_format($activeChat['price'], 0, ',', '.') ?></span>
                                    <span class="text-lg font-bold text-slate-900 dark:text-white">TL</span>
                                </div>
                            </div>
                            <div class="h-px bg-slate-100 dark:bg-slate-800"></div>
                        </div>
                        
                        <?php if ($activeChat['status'] === 'pending'): ?>
                            <?php if (isset($isCustomer) && $isCustomer): ?>
                                <form method="POST">
                                    <input type="hidden" name="accept_offer" value="1">
                                    <input type="hidden" name="offer_id" value="<?= $offerId ?>">
                                    <button type="submit" class="w-full bg-primary hover:bg-yellow-500 text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/20 transition-all flex items-center justify-center gap-2" onclick="return confirm('Bu teklifi kabul etmek istiyor musunuz?')">
                                        <span>Teklifi Kabul Et</span>
                                        <span class="material-symbols-outlined">check_circle</span>
                                    </button>
                                </form>
                            <?php else: // Provider view for pending offer ?>
                                <div class="w-full bg-yellow-100 text-yellow-700 font-bold py-4 rounded-xl text-center flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined">hourglass_top</span> Teklif Cevap Bekliyor
                                </div>
                            <?php endif; ?>
                        <?php elseif ($activeChat['status'] === 'accepted'): ?>
                            <div class="w-full bg-green-100 text-green-700 font-bold py-4 rounded-xl text-center flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined">verified</span> Kabul Edildi
                            </div>
                        <?php elseif ($activeChat['status'] === 'rejected'): ?>
                            <div class="w-full bg-red-100 text-red-700 font-bold py-4 rounded-xl text-center">
                                Reddedildi
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
        <?php endif; ?>
    </div>
</main>

<script>
    // Sohbet alanını en alta kaydır
    const chatContainer = document.getElementById('chatContainer');
    if (chatContainer) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
</script>

<?php require_once 'includes/footer.php'; ?>
