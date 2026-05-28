<?php
use App\Core\View;
View::layout('layouts/main');
View::title($display . ' — หมวดหมู่');
View::start('content');
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <a href="/p/<?= View::e($slug) ?>" class="text-xs text-slate-500 hover:text-slate-300">← กลับ</a>
    <h1 class="text-2xl font-bold mb-4"><?= View::e($display) ?> — หมวดหมู่</h1>
    <div class="flex gap-2 flex-wrap">
        <?php foreach ($genres as $g): ?>
            <a href="/p/<?= View::e($slug) ?>?genre=<?= View::e((string)$g['id']) ?>"
               class="px-4 py-2 bg-slate-900 border border-slate-800 hover:border-brand-500 rounded-full text-sm">
                <?= View::e($g['name']) ?>
            </a>
        <?php endforeach ?>
    </div>
</div>
<?php View::stop();
