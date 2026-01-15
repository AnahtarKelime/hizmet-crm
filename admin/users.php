<?php
require_once '../config/db.php';
require_once 'includes/header.php';

// Kullanıcıları Çek
$stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
$users = $stmt->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Kullanıcılar</h2>
        <p class="text-slate-500 text-sm">Sistemdeki tüm kayıtlı kullanıcıları yönetin.</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">ID</th>
                    <th class="px-6 py-4">Kullanıcı</th>
                    <th class="px-6 py-4">İletişim</th>
                    <th class="px-6 py-4">Rol</th>
                    <th class="px-6 py-4">Kayıt / Bağlantı</th>
                    <th class="px-6 py-4">Kayıt Tarihi</th>
                    <th class="px-6 py-4 text-right">İşlemler</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach($users as $user): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 font-mono text-xs text-slate-400">#<?= $user['id'] ?></td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($user['avatar_url'])): ?>
                                <img src="<?= htmlspecialchars($user['avatar_url']) ?>" class="w-10 h-10 rounded-full object-cover border border-slate-200">
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-sm border border-slate-200">
                                    <?= mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="font-bold text-slate-800"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars($user['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex flex-col gap-1">
                            <?php if($user['phone']): ?>
                                <span class="flex items-center gap-1 text-xs"><span class="material-symbols-outlined text-[14px]">phone</span> <?= htmlspecialchars($user['phone']) ?></span>
                            <?php endif; ?>
                            <?php if($user['city']): ?>
                                <span class="flex items-center gap-1 text-xs"><span class="material-symbols-outlined text-[14px]">location_on</span> <?= htmlspecialchars($user['city'] . ($user['district'] ? '/' . $user['district'] : '')) ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <?php if($user['role'] === 'admin'): ?>
                            <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-bold">Yönetici</span>
                        <?php elseif($user['role'] === 'provider'): ?>
                            <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs font-bold">Hizmet Veren</span>
                        <?php else: ?>
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-bold">Müşteri</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <?php 
                            $hasSocial = false;
                            if (!empty($user['google_id'])): $hasSocial = true; ?>
                                <span class="w-8 h-8 rounded-full bg-white border border-slate-200 flex items-center justify-center text-red-500 shadow-sm" title="Google ile Bağlı">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="currentColor" d="M21.35,11.1H12.18V13.83H18.69C18.36,17.64 15.19,19.27 12.19,19.27C8.36,19.27 5,16.25 5,12C5,7.9 8.2,5 12,5C14.6,5 16.1,6.05 17.1,6.95L19.25,4.85C17.1,2.95 14.8,2 12,2C6.48,2 2,6.48 2,12C2,17.52 6.48,22 12,22C17.52,22 21.7,17.52 21.7,12.33C21.7,11.87 21.5,11.35 21.35,11.1Z"></path></svg>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($user['facebook_id'])): $hasSocial = true; ?>
                                <span class="w-8 h-8 rounded-full bg-white border border-slate-200 flex items-center justify-center text-[#1877F2] shadow-sm" title="Facebook ile Bağlı">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="currentColor" d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg>
                                </span>
                            <?php endif; ?>

                            <?php if (!$hasSocial): ?>
                                <span class="w-8 h-8 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 shadow-sm" title="E-posta ile Kayıt">
                                    <span class="material-symbols-outlined text-lg">mail</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-500">
                        <?= isset($user['created_at']) ? date('d.m.Y H:i', strtotime($user['created_at'])) : '-' ?>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <a href="user-edit.php?id=<?= $user['id'] ?>" class="text-slate-400 hover:text-indigo-600 transition-colors inline-block">
                            <span class="material-symbols-outlined">edit</span>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>