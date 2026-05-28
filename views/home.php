<?php
use App\Core\View;
View::layout('layouts/main');
View::title('หน้าแรก');
View::start('content');
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <header class="mb-8">
        <h1 class="text-3xl font-bold">เลือกแพลตฟอร์ม</h1>
    </header>

    <?php if (empty($platforms)): ?>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-8 text-center">
            <p class="text-slate-400">API key ของคุณยังไม่มีสิทธิ์ใช้แพลตฟอร์มใดเลย</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
            <?php foreach ($platforms as $p): ?>
                <a href="/p/<?= View::e($p['slug']) ?>" class="group block glass-card rounded-2xl p-4 animate-fade-in">
                    <div class="aspect-square rounded-xl overflow-hidden bg-slate-950/80 mb-3.5 relative">
                        <?php if (!empty($p['image'])): ?>
                            <img src="<?= View::e($p['image']) ?>" alt="<?= View::e($p['display']) ?>" referrerpolicy="no-referrer"
                                class="w-full h-full object-cover group-hover:scale-105 transition duration-500 ease-out">
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-slate-950/50 via-transparent to-transparent opacity-60">
                            </div>
                        <?php else: ?>
                            <div class="w-full h-full grid place-items-center text-3xl font-bold bg-slate-900 text-slate-500">
                                <?= View::e(mb_substr($p['display'], 0, 2)) ?></div>
                        <?php endif ?>
                    </div>
                    <div class="text-sm font-semibold truncate text-slate-200 group-hover:text-white transition duration-300">
                        <?= View::e($p['display']) ?></div>
                    <div class="text-xs text-slate-500 group-hover:text-slate-400 mt-1 transition duration-300">
                        <?php if ($p['days_remaining'] > 365): ?>
                            <span
                                class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">
                                <span class="w-1 h-1 rounded-full bg-emerald-400"></span>
                                ตลอดชีพ
                            </span>
                        <?php else: ?>
                            เหลือ <span class="text-slate-350 font-bold"><?= number_format($p['days_remaining']) ?></span> วัน
                        <?php endif ?>
                    </div>
                </a>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>
<?php View::stop();
