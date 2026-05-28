<?php
use App\Core\View;
use App\Core\Csrf;
use App\Core\Database;

$webName = Database::getSettingValue('web_name', 'SeriesJeen');
$logoUrl = Database::getSettingValue('web_logo_url', '');
$logoWidth = Database::getSettingValue('web_logo_width', '32');

View::layout('layouts/main');
View::title('เข้าสู่ระบบ');
View::hideNav(true);
View::start('content');
?>
<div class="min-h-[calc(100vh-3rem)] grid place-items-center px-4 py-10">
    <div class="w-full max-w-md animate-fade-in">
        <div class="text-center mb-8 flex flex-col items-center justify-center">
            <?php if ($logoUrl !== ''): ?>
                <img src="<?= View::e($logoUrl) ?>" style="width: <?= View::e($logoWidth) ?>px; height: auto;" class="object-contain rounded mb-3.5 filter drop-shadow-[0_0_15px_rgba(99,102,241,0.4)]" alt="Logo">
            <?php else: ?>
                <div class="text-5xl mb-3.5 filter drop-shadow-[0_0_15px_rgba(99,102,241,0.4)]">🎬</div>
            <?php endif; ?>
            <h1
                class="text-2xl font-bold font-heading bg-gradient-to-r from-white via-indigo-200 to-brand-500 bg-clip-text text-transparent">
                <?= View::e($webName) ?></h1>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-4 bg-red-900/40 border border-red-700 text-red-200 px-4 py-3 rounded-xl text-sm">
                ⚠️ <?= View::e($error) ?>
            </div>
        <?php endif ?>

        <form method="POST" action="/login" class="glass-card rounded-2xl p-6 space-y-5 shadow-2xl">
            <?= Csrf::field() ?>
            <div>
                <label for="api_key" class="block text-sm font-bold mb-2 text-slate-300">รหัสเข้าใช้งาน (Access Key
                    Code)</label>
                <input type="password" id="api_key" name="api_key" required autofocus autocomplete="off"
                    placeholder="SJ-XXXX-XXXX"
                    class="w-full bg-slate-950/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl px-4 py-3 text-sm font-mono text-slate-100 placeholder:text-slate-600 transition input-glow">

            </div>
            <button type="submit"
                class="w-full bg-brand-600 hover:bg-brand-700 active:scale-95 rounded-xl py-3 font-semibold text-sm transition-all duration-300 text-white shadow-lg shadow-brand-500/25 hover:shadow-brand-500/35">
                เข้าสู่ระบบ
            </button>

            <!-- Gorgeous glowing banner for API Rental / Trial -->
            <div class="pt-2">
                <div class="bg-brand-500/10 border border-brand-500/25 rounded-2xl p-4 text-center">
                    <p class="text-xs text-slate-350 font-medium">
                        ยังไม่มีคีย์ หรือต้องการทดลองใช้งานคีย์ฟรี?
                    </p>
                    <a href="https://rental.seriesjeen.online" target="_blank" rel="noopener"
                        class="inline-flex items-center gap-1.5 mt-2 text-xs font-bold text-brand-400 hover:text-brand-300 transition group">
                        <span>สั่งซื้อหรือสมัครทดลองใช้คีย์ API ที่นี่</span>
                        <svg class="w-3.5 h-3.5 transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </form>

        <?php
        $loginDesc = Database::getSettingValue('web_login_description', "คีย์ API ลับของระบบสตรีมมิ่งจะถูกเก็บไว้ที่ฝั่งเซิร์ฟเวอร์อย่างปลอดภัยโดยไม่เปิดเผยแก่ผู้ใช้ทั่วไป\nรหัสเข้าใช้งานของท่านได้รับการคุ้มครองและควบคุมความปลอดภัยผ่านระบบฐานข้อมูลส่วนกลาง");
        if ($loginDesc !== ''):
        ?>
            <p class="text-center text-xs leading-relaxed text-slate-500 mt-6 whitespace-pre-line">
                <?= View::e($loginDesc) ?>
            </p>
        <?php endif ?>
    </div>
</div>
<?php View::stop();
