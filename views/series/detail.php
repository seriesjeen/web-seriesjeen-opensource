<?php
use App\Core\View;
View::layout('layouts/main');
View::title($detail['title']);
View::start('content');
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <a href="/p/<?= View::e($slug) ?>" class="text-xs text-slate-500 hover:text-slate-300">← <?= View::e($display) ?></a>

    <div class="mt-4 grid md:grid-cols-[260px_1fr] gap-8 items-start animate-fade-in">
        <div class="rounded-2xl overflow-hidden bg-slate-950 aspect-[2/3] max-w-[260px] border border-slate-800/80 shadow-2xl relative group">
            <?php if (!empty($detail['cover'])): ?>
                <img src="<?= View::e($detail['cover']) ?>" referrerpolicy="no-referrer" class="w-full h-full object-cover group-hover:scale-105 transition duration-700 ease-out" alt="">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/70 via-transparent to-transparent"></div>
            <?php endif ?>
        </div>
        <div class="flex flex-col justify-start">
            <h1 class="text-3xl font-bold tracking-tight text-white font-heading"><?= View::e($detail['title']) ?></h1>
            <?php if (!empty($detail['genre'])): ?>
                <div class="mt-2.5">
                    <span class="inline-flex items-center text-xs font-semibold text-brand-500 bg-brand-500/10 px-3 py-1 rounded-full border border-brand-500/20"><?= View::e($detail['genre']) ?></span>
                </div>
            <?php endif ?>
            <?php if (!empty($detail['episode_count'])): ?>
                <div class="text-xs font-bold text-slate-500 tracking-wider uppercase mt-4 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                    จำนวน <?= (int)$detail['episode_count'] ?> ตอน
                </div>
            <?php endif ?>
            <?php if (!empty($detail['description'])): ?>
                <div class="mt-5 p-4 bg-slate-900/30 border border-slate-900 rounded-2xl">
                    <p class="text-sm text-slate-350 leading-relaxed whitespace-pre-line"><?= View::e($detail['description']) ?></p>
                </div>
            <?php endif ?>
        </div>
    </div>

    <h2 class="text-lg font-bold tracking-tight text-white font-heading mt-10 mb-5 animate-fade-in flex items-center gap-2">
        <i class="fa-solid fa-circle-play text-brand-500 text-base"></i>
        เลือกรับชมตอน (Select Episode)
    </h2>
    <?php if (empty($episodes)): ?>
        <div class="bg-slate-900/30 border border-slate-900 rounded-2xl p-8 text-center text-slate-500 text-sm animate-fade-in">ยังไม่มีรายการตอนสำหรับเรื่องนี้</div>
    <?php else: ?>
    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 gap-3.5 animate-fade-in">
        <?php foreach ($episodes as $ep): ?>
            <?php
                $n = (int)($ep['episode'] ?? 0);
                $locked = !empty($ep['locked']);
                $href = '/p/' . urlencode($slug) . '/watch/' . urlencode($series_id) . '/' . $n;
            ?>
            <a href="<?= $locked ? '#' : View::e($href) ?>"
               onclick="<?= $locked ? "Swal.fire({icon: 'warning', title: 'ตอนถูกล็อก!', text: 'ตอนนี้ยังไม่เปิดให้รับชมในบัญชีของคุณ', confirmButtonColor: 'var(--brand-color)', background: '#090d16', color: '#cbd5e1'}); return false;" : "" ?>"
               class="group relative overflow-hidden rounded-2xl border transition-all duration-300 active:scale-95 flex flex-col justify-between p-3.5 min-h-[96px] shadow-lg
                      <?= $locked ? 'bg-slate-950/40 border-slate-900/60 text-slate-500 cursor-not-allowed' : 'bg-slate-900/40 border-slate-800/80 text-slate-200 hover:text-white hover:border-brand-500 hover:-translate-y-1 hover:shadow-brand-500/10' ?>">
                
                <!-- Glow gradient overlay on hover -->
                <?php if (!$locked): ?>
                    <div class="absolute inset-0 bg-gradient-to-br from-brand-500/0 to-indigo-500/0 group-hover:from-brand-500/5 group-hover:to-indigo-500/5 transition duration-300 pointer-events-none"></div>
                <?php endif ?>

                <!-- Episode Card Header -->
                <div class="flex items-center justify-between w-full relative z-10">
                    <span class="text-[9px] tracking-wider uppercase font-extrabold text-slate-500 group-hover:text-brand-400 transition">EPISODE</span>
                    <?php if ($locked): ?>
                        <i class="fa-solid fa-lock text-[10px] text-slate-650"></i>
                    <?php else: ?>
                        <i class="fa-solid fa-circle-play text-slate-500 group-hover:text-brand-500 transform group-hover:scale-110 transition duration-300"></i>
                    <?php endif ?>
                </div>
                
                <!-- Episode Title and Caption -->
                <div class="mt-3 relative z-10">
                    <span class="text-lg font-black font-heading tracking-tight block">ตอนที่ <?= $n ?></span>
                    <span class="text-[9px] font-bold text-slate-500 group-hover:text-slate-350 transition"><?= $locked ? 'ปิดรับชมอยู่' : 'คลิกเพื่อรับชม' ?></span>
                </div>
            </a>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>
<?php View::stop();
