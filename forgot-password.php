<?php
require_once 'config/db.php';
$pageTitle = "Şifremi Unuttum";
require_once 'includes/header.php';
?>

<main class="flex items-center justify-center min-h-[calc(100vh-4rem)] bg-slate-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-lg bg-white p-8 rounded-2xl shadow-xl border border-slate-100">
        
        <!-- Adım 1: E-posta Girişi -->
        <div id="step-1" class="flex flex-col gap-6">
            <div class="text-center">
                <div class="mx-auto w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-primary text-3xl">lock_reset</span>
                </div>
                <h1 class="text-slate-900 tracking-tight text-3xl font-bold leading-tight mb-2">Şifrenizi mi Unuttunuz?</h1>
                <p class="text-slate-600 text-base font-normal leading-normal px-4">E-posta adresinizi girin, size şifre sıfırlama talimatlarını gönderelim.</p>
            </div>
            
            <form class="flex flex-col gap-4 mt-4" onsubmit="event.preventDefault(); showStep2();">
                <label class="flex flex-col w-full">
                    <p class="text-slate-900 text-sm font-medium leading-normal pb-2">E-posta Adresi</p>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl">mail</span>
                        <input type="email" required class="flex w-full rounded-xl border-slate-200 bg-white h-14 pl-12 pr-4 placeholder:text-slate-400 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all" placeholder="ornek@eposta.com"/>
                    </div>
                </label>
                <button type="submit" class="flex w-full cursor-pointer items-center justify-center overflow-hidden rounded-xl h-12 px-5 bg-primary text-white text-base font-bold leading-normal tracking-[0.015em] hover:bg-primary/90 transition-all mt-2 shadow-lg shadow-primary/20">
                    <span>Talimat Gönder</span>
                </button>
            </form>
        </div>

        <!-- Adım 2: Doğrulama Kodu (Başlangıçta Gizli) -->
        <div id="step-2" class="flex flex-col gap-6 hidden">
            <div class="text-center">
                <div class="mx-auto w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-primary text-3xl">verified_user</span>
                </div>
                <h1 class="text-slate-900 tracking-tight text-3xl font-bold leading-tight mb-2">Doğrulama Kodu</h1>
                <p class="text-slate-600 text-base font-normal leading-normal px-4">
                    Lütfen e-posta adresinize gönderilen 6 haneli kodu girin.
                </p>
            </div>
            
            <div class="flex flex-col gap-6 mt-4">
                <div class="flex justify-between gap-2 px-2">
                    <input class="w-12 h-14 text-center text-2xl font-bold rounded-xl border border-slate-200 bg-white text-slate-900 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all shadow-sm otp-input" maxlength="1" type="text" autofocus/>
                    <input class="w-12 h-14 text-center text-2xl font-bold rounded-xl border border-slate-200 bg-white text-slate-900 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all shadow-sm otp-input" maxlength="1" type="text"/>
                    <input class="w-12 h-14 text-center text-2xl font-bold rounded-xl border border-slate-200 bg-white text-slate-900 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all shadow-sm otp-input" maxlength="1" type="text"/>
                    <input class="w-12 h-14 text-center text-2xl font-bold rounded-xl border border-slate-200 bg-white text-slate-900 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all shadow-sm otp-input" maxlength="1" type="text"/>
                    <input class="w-12 h-14 text-center text-2xl font-bold rounded-xl border border-slate-200 bg-white text-slate-900 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all shadow-sm otp-input" maxlength="1" type="text"/>
                    <input class="w-12 h-14 text-center text-2xl font-bold rounded-xl border border-slate-200 bg-white text-slate-900 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all shadow-sm otp-input" maxlength="1" type="text"/>
                </div>
                <button class="flex w-full cursor-pointer items-center justify-center overflow-hidden rounded-xl h-12 px-5 bg-primary text-white text-base font-bold leading-normal tracking-[0.015em] hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                    <span>Kodu Doğrula</span>
                </button>
                <div class="text-center text-sm">
                    <p class="text-slate-500">Kod ulaşmadı mı? 
                        <button class="text-primary font-bold hover:underline">Tekrar Gönder (00:59)</button>
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer Back Link -->
        <div class="mt-8 pt-6 border-t border-slate-100 flex justify-center">
            <a class="flex items-center gap-2 text-primary font-semibold text-sm hover:gap-3 transition-all" href="login.php">
                <span class="material-symbols-outlined text-base">arrow_back</span>
                Giriş sayfasına geri dön
            </a>
        </div>
    </div>
</main>

<script>
    function showStep2() {
        document.getElementById('step-1').classList.add('hidden');
        document.getElementById('step-2').classList.remove('hidden');
        
        // İlk inputa odaklan
        const inputs = document.querySelectorAll('.otp-input');
        if(inputs.length > 0) inputs[0].focus();
    }

    // OTP Input Otomatik Geçiş
    const inputs = document.querySelectorAll('.otp-input');
    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            if (e.target.value.length === 1 && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && e.target.value.length === 0 && index > 0) {
                inputs[index - 1].focus();
            }
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>