<?php
use App\Core\Session;
use App\Core\View;

$success = Session::flash('success');
$error = Session::flash('error');
if (!$success && !$error) return;
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 animate-fade-in">
    <?php if ($success): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-2xl text-xs font-semibold flex items-center gap-2 shadow-lg shadow-emerald-500/5">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
            <?= View::e($success) ?>
        </div>
    <?php endif ?>
    <?php if ($error): ?>
        <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-2xl text-xs font-semibold flex items-center gap-2 shadow-lg shadow-red-500/5">
            <span class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse"></span>
            <?= View::e($error) ?>
        </div>
    <?php endif ?>
</div>
