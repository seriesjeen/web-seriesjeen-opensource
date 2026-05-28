<?php
use App\Core\View;
use App\Core\Csrf;
use App\Core\Session;

$path = $_SERVER['REQUEST_URI'] ?? '/';
$currentLocale = $_GET['locale'] ?? Session::get('preferred_locale', 'th');
$user = $user ?? null;
$platforms = Session::get('platforms', []);

$LOCALES = [
    'th' => '🇹🇭 ภาษาไทย',
    'en' => '🇬🇧 English',
    'zh' => '🇨🇳 中文',
    'ja' => '🇯🇵 日本語',
    'ko' => '🇰🇷 한국어',
    'id' => '🇮🇩 Bahasa',
    'ms' => '🇲🇾 Melayu',
    'vi' => '🇻🇳 Tiếng Việt',
    'es' => '🇪🇸 Español',
    'pt' => '🇵🇹 Português',
    'fr' => '🇫🇷 Français',
    'de' => '🇩🇪 Deutsch',
    'ar' => '🇸🇦 العربية',
    'hi' => '🇮🇳 हिन्दी',
];
?>
<?php
$isAdminPage = str_starts_with($path, '/admin');
$webName = \App\Core\Database::getSettingValue('web_name', 'SeriesJeen');
$logoUrl = \App\Core\Database::getSettingValue('web_logo_url', '');
$logoWidth = \App\Core\Database::getSettingValue('web_logo_width', '32');
?>
<nav class="glass-nav sticky top-0 z-40 transition-all duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center gap-4 h-16">
        <?php if ($isAdminPage): ?>
            <!-- Admin Panel Logo -->
            <a href="/admin" class="flex items-center gap-2 font-bold text-lg shrink-0 tracking-tight text-white hover:opacity-90 transition">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= View::e($logoUrl) ?>" style="width: <?= View::e($logoWidth) ?>px; height: auto;" class="object-contain rounded" alt="Logo">
                <?php else: ?>
                    <span class="text-2xl filter drop-shadow-[0_0_10px_rgba(99,102,241,0.5)]">⚙️</span>
                <?php endif; ?>
                <span class="hidden sm:inline font-heading bg-gradient-to-r from-white via-indigo-200 to-indigo-500 bg-clip-text text-transparent"><?= View::e($webName) ?> <span class="text-[10px] uppercase font-extrabold px-2 py-0.5 rounded bg-indigo-500/10 border border-indigo-500/25 text-indigo-400 ml-1">Admin Panel</span></span>
            </a>
            
            <!-- Spacer to push right buttons -->
            <div class="flex-1"></div>
            
            <!-- Back to Main Site button -->
            <a href="/" class="hidden md:inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-800 bg-slate-900/60 hover:bg-slate-850 hover:border-slate-700 text-slate-350 hover:text-white text-xs font-bold transition-all duration-300">
                <i class="fa-solid fa-house text-indigo-500 text-[10px]"></i>
                กลับสู่หน้าเว็บซีรีส์ (Main Website)
            </a>
        <?php else: ?>
            <!-- Standard Logo -->
            <a href="/" class="flex items-center gap-2 font-bold text-lg shrink-0 tracking-tight text-white hover:opacity-90 transition">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= View::e($logoUrl) ?>" style="width: <?= View::e($logoWidth) ?>px; height: auto;" class="object-contain rounded" alt="Logo">
                <?php else: ?>
                    <span class="text-2xl filter drop-shadow-[0_0_10px_rgba(99,102,241,0.5)]">🎬</span>
                <?php endif; ?>
                <span class="hidden sm:inline font-heading bg-gradient-to-r from-white via-indigo-200 to-brand-500 bg-clip-text text-transparent"><?= View::e($webName) ?></span>
            </a>

            <?php if (count($platforms) > 0): ?>
            <form action="<?= View::e($path === '/' ? '/p/freereels' : preg_replace('#\?.*#', '', $path)) ?>" method="GET" class="flex-1 max-w-2xl">
                <div class="relative">
                    <input
                        type="search"
                        name="q"
                        value="<?= View::e($_GET['q'] ?? '') ?>"
                        placeholder="ค้นหาซีรีส์..."
                        class="w-full bg-slate-900/60 border border-slate-800/80 focus:border-brand-500/80 focus:ring-1 focus:ring-brand-500/80 outline-none rounded-xl pl-10 pr-4 py-2 text-sm placeholder:text-slate-500 text-slate-100 transition input-glow"
                        autocomplete="off"
                    >
                    <svg class="absolute left-3.5 top-2.5 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <?php if (!empty($_GET['locale'])): ?><input type="hidden" name="locale" value="<?= View::e($_GET['locale']) ?>"><?php endif ?>
                </div>
            </form>
            <?php endif ?>
        <?php endif ?>

        <div x-data="{open:false}" class="relative">
            <button @click="open=!open" class="flex items-center gap-1 px-3 py-2 rounded-xl hover:bg-slate-800/80 active:scale-95 text-sm transition font-medium border border-transparent hover:border-slate-800">
                <span><?= View::e($LOCALES[$currentLocale] ?? '🌐 Locale') ?></span>
                <svg class="w-4 h-4 text-slate-400" fill="currentColor" viewBox="0 0 20 20"><path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/></svg>
            </button>
            <div x-show="open" @click.outside="open=false" x-transition.origin.top.right class="absolute right-0 top-full mt-2 w-52 bg-slate-900/95 backdrop-blur-xl border border-slate-800/80 rounded-xl shadow-2xl py-1.5 max-h-80 overflow-y-auto z-50" x-cloak>
                <?php foreach ($LOCALES as $code => $label):
                    $q = $_GET;
                    $q['locale'] = $code;
                    $href = preg_replace('#\?.*#', '', $path) . '?' . http_build_query($q);
                ?>
                    <a href="<?= View::e($href) ?>" class="block px-3 py-1.5 text-sm hover:bg-slate-700 <?= $code === $currentLocale ? 'text-brand-500 font-semibold' : '' ?>"><?= View::e($label) ?></a>
                <?php endforeach ?>
            </div>
        </div>

        <?php if (Session::get('user_code')): ?>
            <div x-data="{open:false}" class="relative">
                <button @click="open=!open" class="flex items-center gap-2 rounded-full hover:bg-slate-800/80 active:scale-95 px-1 py-1 transition border border-transparent hover:border-slate-800">
                    <div class="w-8 h-8 rounded-full bg-brand-600 grid place-items-center text-xs font-bold text-white shadow-lg shadow-brand-500/20">
                        <svg class="w-4.5 h-4.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                </button>
                <div x-show="open" @click.outside="open=false" x-transition.origin.top.right class="absolute right-0 top-full mt-2 w-64 bg-slate-900/95 backdrop-blur-xl border border-slate-800/80 rounded-xl shadow-2xl p-2 z-50" x-cloak>
                    <div class="px-2.5 py-2 border-b border-slate-800 mb-1.5">
                        <div class="text-xs text-slate-500 font-bold uppercase tracking-wider">รหัสเข้าใช้งาน</div>
                        <div class="text-sm font-bold truncate text-white font-mono mt-1 select-all"><?= View::e(Session::get('user_code')) ?></div>
                        <div class="text-xs text-slate-400 mt-1.5 flex items-center gap-1.5 font-medium">
                            <span class="w-1.5 h-1.5 rounded-full <?= Session::get('role') === 'admin' ? 'bg-indigo-500 animate-pulse' : 'bg-slate-500' ?>"></span>
                            สิทธิ์: <?= Session::get('role') === 'admin' ? 'ผู้ดูแลระบบ (Admin)' : 'ผู้ใช้ทั่วไป (User)' ?>
                        </div>
                    </div>
                    <?php if (Session::get('role') === 'admin'): ?>
                    <a href="/admin" class="block w-full text-left px-2.5 py-2 text-sm text-indigo-400 hover:text-indigo-300 hover:bg-indigo-500/10 rounded-lg transition-colors flex items-center gap-2 mb-1.5 font-semibold">
                        <i class="fa-solid fa-user-shield text-indigo-500"></i>
                        ระบบหลังบ้าน (Admin)
                    </a>
                    <?php endif ?>
                    <form action="/logout" method="POST">
                        <?= Csrf::field() ?>
                        <button type="submit" class="w-full text-left px-2.5 py-2 text-sm text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-lg transition-colors flex items-center gap-2">
                            <i class="fa-solid fa-right-from-bracket text-red-400"></i>
                            ออกจากระบบ
                        </button>
                    </form>
                </div>
            </div>
        <?php endif ?>
    </div>
</nav>
