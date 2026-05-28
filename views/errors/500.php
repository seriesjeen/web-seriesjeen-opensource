<?php
use App\Core\View;
View::layout('layouts/main');
View::title('เกิดข้อผิดพลาด');
View::start('content');
?>
<div class="max-w-4xl mx-auto px-4 py-10">
    <div class="text-center mb-6">
        <div class="text-6xl mb-4">⚠️</div>
        <h1 class="text-3xl font-bold mb-2">เกิดข้อผิดพลาด</h1>
        <p class="text-slate-400"><?= View::e($message ?? 'มีบางอย่างผิดพลาด') ?></p>
    </div>
    <?php if (!empty($trace)): ?>
        <details class="bg-slate-900 border border-slate-800 rounded-xl p-4 text-xs font-mono">
            <summary class="cursor-pointer text-slate-400">Stack trace (debug only)</summary>
            <pre class="mt-3 text-slate-500 whitespace-pre-wrap break-all"><?= View::e($trace) ?></pre>
        </details>
    <?php endif ?>
    <div class="text-center mt-6">
        <a href="/" class="inline-block bg-brand-600 hover:bg-brand-700 px-4 py-2 rounded-lg text-sm font-semibold">กลับหน้าแรก</a>
    </div>
</div>
<?php View::stop();
