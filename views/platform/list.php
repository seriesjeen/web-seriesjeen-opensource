<?php
use App\Core\View;
View::layout('layouts/main');
View::title($display . ' — ค้นหาและกรอง');
View::start('content');

$baseQuery = array_filter([
    'q'      => $filters['keyword'] ?? null,
    'locale' => $filters['locale']  ?? null,
    'genre'  => $filters['genre']   ?? null,
], fn($v) => $v !== null && $v !== '');
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <header class="flex items-baseline justify-between mb-4">
        <div>
            <a href="/" class="text-xs text-slate-500 hover:text-slate-300">← กลับ</a>
            <h1 class="text-2xl font-bold flex items-center gap-2"><?= View::e($display) ?>
                <?php if (!empty($filters['keyword'])): ?>
                    <span class="text-base text-slate-500 font-normal">· ค้นหา "<?= View::e($filters['keyword']) ?>"</span>
                <?php endif ?>
            </h1>
        </div>
        <?php if (!empty($result['total'])): ?>
            <div class="text-sm text-slate-400"><?= number_format($result['total']) ?> เรื่อง</div>
        <?php endif ?>
    </header>

    <?php if (!empty($genres)): ?>
    <div class="mb-5 flex gap-2 flex-wrap items-center animate-fade-in">
        <a href="<?= View::e('?' . http_build_query(array_diff_key($baseQuery, ['genre' => 1]))) ?>"
           class="text-xs px-3 py-1.5 rounded-full border transition-all duration-300 font-medium <?= empty($filters['genre']) ? 'active-genre' : 'border-slate-800/80 bg-slate-900/40 hover:bg-slate-800/50 hover:border-slate-700 text-slate-400 hover:text-slate-200' ?>">ทั้งหมด</a>
        <?php foreach (array_slice($genres, 0, 24) as $g):
            $q = $baseQuery; $q['genre'] = $g['id']; unset($q['page']);
            $active = (string)($filters['genre'] ?? '') === (string)$g['id'];
        ?>
            <a href="?<?= View::e(http_build_query($q)) ?>"
               class="text-xs px-3 py-1.5 rounded-full border transition-all duration-300 font-medium <?= $active ? 'active-genre' : 'border-slate-800/80 bg-slate-900/40 hover:bg-slate-800/50 hover:border-slate-700 text-slate-400 hover:text-slate-200' ?>">
                <?= View::e($g['name']) ?>
            </a>
        <?php endforeach ?>
    </div>
    <?php endif ?>

    <?php if (!empty($error)): ?>
        <div class="bg-red-900/40 border border-red-700 text-red-200 px-4 py-3 rounded-lg text-sm mb-4">
            ⚠️ <?= View::e($error) ?>
        </div>
    <?php endif ?>

    <?php if (empty($result['items'])): ?>
        <div class="text-center py-20 text-slate-500">ไม่พบเรื่องตามเงื่อนไขที่เลือก</div>
    <?php else: ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
        <?php foreach ($result['items'] as $item): ?>
            <?= View::include('partials/series_card', ['slug' => $slug, 'item' => $item]) ?>
        <?php endforeach ?>
    </div>

    <?= View::include('partials/pagination', [
        'page'       => $result['page'],
        'page_size'  => $result['page_size'],
        'total'      => $result['total'],
        'base_query' => $baseQuery,
    ]) ?>
    <?php endif ?>
</div>
<?php View::stop();
