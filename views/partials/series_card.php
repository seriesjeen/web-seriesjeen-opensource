<?php
use App\Core\View;
$slug = $slug ?? '';
$item = $item ?? [];
$href = '/p/' . urlencode($slug) . '/series/' . urlencode((string)($item['series_id'] ?? ''));
?>
<a href="<?= View::e($href) ?>" class="group block glass-card rounded-2xl overflow-hidden animate-fade-in">
    <div class="aspect-[2/3] bg-slate-950 overflow-hidden relative">
        <?php if (!empty($item['cover'])): ?>
            <img src="<?= View::e($item['cover']) ?>" alt="" referrerpolicy="no-referrer" loading="lazy"
                 class="w-full h-full object-cover group-hover:scale-105 transition duration-500 ease-out">
            <!-- Add a subtle premium gradient shadow inside the cover card -->
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent opacity-80 group-hover:opacity-60 transition duration-300"></div>
        <?php else: ?>
            <div class="w-full h-full grid place-items-center bg-slate-900 text-slate-700 text-4xl">🎬</div>
        <?php endif ?>
        <?php if (!empty($item['episode_count'])): ?>
            <div class="absolute bottom-2.5 right-2.5 bg-black/70 backdrop-blur-md text-[10px] font-bold text-white px-2 py-0.5 rounded-lg border border-white/10 tracking-wide uppercase">EP <?= (int)$item['episode_count'] ?></div>
        <?php endif ?>
    </div>
    <div class="p-3.5">
        <div class="text-sm font-semibold line-clamp-2 leading-tight text-slate-200 group-hover:text-white transition duration-300"><?= View::e($item['title'] ?? '—') ?></div>
        <?php if (!empty($item['genre'])): ?>
            <div class="text-xs text-slate-500 group-hover:text-slate-400 mt-1.5 line-clamp-1 transition duration-300"><?= View::e($item['genre']) ?></div>
        <?php endif ?>
    </div>
</a>
