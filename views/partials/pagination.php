<?php
use App\Core\View;
$page = (int)($page ?? 1);
$pageSize = (int)($page_size ?? 20);
$total = $total ?? null;
$baseQuery = $base_query ?? [];

if ($total === null) {
    // total unknown — show prev/next only
    $totalPages = null;
} else {
    $totalPages = max(1, (int)ceil($total / max(1, $pageSize)));
}

$mkUrl = function ($p) use ($baseQuery) {
    $q = $baseQuery; $q['page'] = $p;
    return '?' . http_build_query($q);
};
?>
<div class="flex items-center justify-center gap-3 mt-10 text-sm animate-fade-in">
    <a href="<?= View::e($mkUrl(max(1, $page - 1))) ?>"
       class="px-4 py-2 rounded-xl border border-slate-800 bg-slate-900/60 hover:bg-slate-850 hover:border-slate-700 text-slate-300 hover:text-white transition duration-300 active:scale-95 shadow-md shadow-black/10 <?= $page <= 1 ? 'opacity-30 pointer-events-none' : '' ?>">
       <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
    </a>
    <span class="px-4 py-2 rounded-xl bg-slate-900/40 border border-slate-800/40 text-slate-400 font-semibold tracking-wide text-xs">
        หน้า <?= $page ?><?= $totalPages !== null ? ' จาก ' . $totalPages : '' ?>
    </span>
    <a href="<?= View::e($mkUrl($totalPages !== null ? min($totalPages, $page + 1) : $page + 1)) ?>"
       class="px-4 py-2 rounded-xl border border-slate-800 bg-slate-900/60 hover:bg-slate-850 hover:border-slate-700 text-slate-300 hover:text-white transition duration-300 active:scale-95 shadow-md shadow-black/10 <?= ($totalPages !== null && $page >= $totalPages) ? 'opacity-30 pointer-events-none' : '' ?>">
       <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
    </a>
</div>
