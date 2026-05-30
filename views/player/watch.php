<?php
use App\Core\View;
View::layout('layouts/main');
View::title($detail['title'] . ' EP ' . $episode);
View::start('content');

$maxEp = $detail['episode_count'] ?? null;
$nextEp = ($maxEp === null || $episode < $maxEp) ? $episode + 1 : null;

$payload = [
    'platform'  => $slug,
    'series_id' => $series_id,
    'episode'   => $episode,
    'sources'   => array_values($current['sources'] ?? []),
    'subtitles' => array_values($current['subtitles'] ?? []),
    'is_melolo' => $slug === 'melolo',
    'poster'    => $current['cover'] ?? $detail['cover'] ?? null,
    'next_episode' => $nextEp,
    'next_episode_url' => $nextEp ? '/p/' . urlencode($slug) . '/watch/' . urlencode($series_id) . '/' . $nextEp : null,
];

usort($episodes, fn($a, $b) => ($a['episode'] ?? 0) <=> ($b['episode'] ?? 0));
?>
<div class="max-w-[1536px] mx-auto px-4 sm:px-6 lg:px-8 py-4">
    <a href="/p/<?= View::e($slug) ?>/series/<?= View::e($series_id) ?>" class="text-xs text-slate-500 hover:text-slate-300">← <?= View::e($detail['title']) ?></a>

    <div class="mt-4 grid lg:grid-cols-[1fr_340px] gap-6 items-start animate-fade-in">
        <div>
            <!-- Video wrapper with premium border-glow shadow -->
            <div id="video-container" class="bg-black rounded-2xl overflow-hidden shadow-[0_20px_50px_rgba(0,0,0,0.8)] border border-slate-850 relative group" style="aspect-ratio: 16/9;">
                <video id="player" playsinline controls crossorigin="anonymous"
                       <?php if (!empty($payload['poster'])): ?>poster="<?= View::e($payload['poster']) ?>"<?php endif ?>>
                </video>
            </div>

            <div id="watch-info-container" class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-900/20 border border-slate-900/60 rounded-2xl p-4">
                <div>
                    <h1 class="font-bold text-lg text-white font-heading"><?= View::e($detail['title']) ?></h1>
                    <div class="text-xs text-slate-500 mt-1 flex items-center gap-1.5 font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-brand-500 animate-pulse"></span>
                        กำลังรับชมตอนที่ <?= $episode ?>
                    </div>
                </div>
                <div class="flex gap-2 items-center">
                    <?php
                    $prevEp = $episode > 1 ? $episode - 1 : null;
                    $maxEp = $detail['episode_count'] ?? null;
                    $nextEp = ($maxEp === null || $episode < $maxEp) ? $episode + 1 : null;
                    ?>
                    <?php if ($prevEp): ?>
                        <a href="/p/<?= View::e($slug) ?>/watch/<?= View::e($series_id) ?>/<?= $prevEp ?>" class="px-4 py-2 rounded-xl text-xs font-semibold border border-slate-800 bg-slate-900/60 hover:bg-slate-800 text-slate-300 hover:text-white transition duration-300 active:scale-95">ก่อนหน้า</a>
                    <?php endif ?>
                    <?php if ($nextEp): ?>
                        <a href="/p/<?= View::e($slug) ?>/watch/<?= View::e($series_id) ?>/<?= $nextEp ?>" class="px-4 py-2 rounded-xl text-xs font-bold bg-brand-600 hover:bg-brand-700 text-white shadow-lg shadow-brand-500/25 hover:shadow-brand-500/35 transition duration-300 active:scale-95">ตอนถัดไป →</a>
                    <?php endif ?>
                </div>
            </div>

            <div id="player-status" class="mt-3 text-xs text-slate-500 px-1"></div>
        </div>

        <aside id="episodes-sidebar" class="glass-card rounded-2xl p-5 border border-slate-800/80 max-h-[80vh] overflow-y-auto shadow-2xl">
            <h3 class="text-xs font-bold tracking-wider uppercase mb-4 text-slate-400 px-1 flex items-center justify-between font-heading">
                <span class="flex items-center gap-1.5">
                    <i class="fa-solid fa-list-ul text-brand-500"></i>
                    รายการตอนทั้งหมด
                </span>
                <span class="px-2.5 py-0.5 rounded-full bg-brand-500/10 text-brand-400 border border-brand-500/20 text-[9px] font-bold">
                    <?= (int)$detail['episode_count'] ?> ตอน
                </span>
            </h3>
            <div class="grid grid-cols-4 sm:grid-cols-5 lg:grid-cols-4 gap-2">
                <?php foreach ($episodes as $ep):
                    $n = (int)($ep['episode'] ?? 0);
                    if ($n === 0) continue;
                    $isCurrent = $n === $episode;
                    $href = '/p/' . urlencode($slug) . '/watch/' . urlencode($series_id) . '/' . $n;
                ?>
                    <a href="<?= View::e($href) ?>"
                       class="relative text-xs aspect-square rounded-xl border transition-all duration-300 flex items-center justify-center font-bold active:scale-95 overflow-hidden group/item
                              <?= $isCurrent ? 'active-genre border-brand-500 shadow-md shadow-brand-500/20' : 'bg-slate-900/40 border-slate-800 hover:border-brand-500 text-slate-300 hover:text-white hover:shadow-lg hover:shadow-brand-500/5' ?>">

                        <!-- Mini overlay glow effect on hover -->
                        <?php if (!$isCurrent): ?>
                            <div class="absolute inset-0 bg-gradient-to-tr from-brand-500/0 to-indigo-500/0 group-hover/item:from-brand-500/5 group-hover/item:to-indigo-500/5 transition duration-300 pointer-events-none"></div>
                        <?php endif ?>

                        <span class="relative z-10 font-black">EP <?= $n ?></span>

                        <?php if ($isCurrent): ?>
                            <i class="fa-solid fa-circle-play text-[8px] absolute top-1 right-1.5 text-white/95 animate-pulse"></i>
                        <?php endif ?>
                    </a>
                <?php endforeach ?>
            </div>
        </aside>
    </div>
</div>

<script id="episode-data" type="application/json"><?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<?php View::stop();
View::start('scripts');
?>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.15/dist/hls.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.polyfilled.min.js"></script>
<script src="/public/assets/js/melolo.js"></script>
<script src="/public/assets/js/player.js"></script>
<?php View::stop();
