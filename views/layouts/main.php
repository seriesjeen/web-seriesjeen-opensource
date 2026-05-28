<?php
use App\Core\View;
use App\Core\Session;
$user = Session::get('user');
$pageTitle = View::title() ?: 'SeriesJeen Player';
$hideNav = View::hideNav();
?><!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title><?= View::e($pageTitle) ?> · <?= View::e(\App\Core\Database::getSettingValue('web_name', 'SeriesJeen')) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎬</text></svg>">
    
    <!-- Google Fonts: Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <?php
    $themeColor = \App\Core\Database::getSettingValue('web_theme_color', '#6366f1');
    $themeColor600 = \App\Core\Database::adjustBrightness($themeColor, -20);
    $themeColor700 = \App\Core\Database::adjustBrightness($themeColor, -40);
    $themeColor100 = \App\Core\Database::adjustBrightness($themeColor, 120);
    $themeColor50 = \App\Core\Database::adjustBrightness($themeColor, 140);

    $gradientColor = \App\Core\Database::getSettingValue('web_gradient_color', '#a855f7');
    $gradientColor600 = \App\Core\Database::adjustBrightness($gradientColor, -20);
    $gradientColor700 = \App\Core\Database::adjustBrightness($gradientColor, -40);
    $gradientColor100 = \App\Core\Database::adjustBrightness($gradientColor, 120);
    $gradientColor50 = \App\Core\Database::adjustBrightness($gradientColor, 140);

    $navbarColor = \App\Core\Database::getSettingValue('web_navbar_color', '#080c1c');
    $footerColor = \App\Core\Database::getSettingValue('web_footer_color', '#060814');
    $bgColor = \App\Core\Database::getSettingValue('web_bg_color', '#060814');
    ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Prompt"', 'sans-serif'],
                        heading: ['"Prompt"', 'sans-serif'],
                    },
                    colors: {
                        brand: { 
                            50: '<?= $themeColor50 ?>', 
                            100: '<?= $themeColor100 ?>', 
                            500: '<?= $themeColor ?>', 
                            600: '<?= $themeColor600 ?>', 
                            700: '<?= $themeColor700 ?>' 
                        },
                        indigo: { 
                            50: '<?= $gradientColor50 ?>', 
                            100: '<?= $gradientColor100 ?>', 
                            500: '<?= $gradientColor ?>', 
                            600: '<?= $gradientColor600 ?>', 
                            700: '<?= $gradientColor700 ?>' 
                        }
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --brand-color: <?= $themeColor ?>;
            --brand-glow: <?= $themeColor100 ?>;
            --plyr-color-main: <?= $themeColor ?> !important;
        }

        body {
            background-color: <?= $bgColor ?> !important;
        }

        .glowing-blob-1 {
            background-color: <?= $themeColor ?> !important;
            opacity: 0.08 !important;
        }

        .glowing-blob-2 {
            background-color: <?= $gradientColor ?> !important;
            opacity: 0.08 !important;
        }

        .glass-nav {
            background-color: <?= $navbarColor ?>BF !important; /* BF is 75% opacity in hex */
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        footer {
            background-color: <?= $footerColor ?> !important;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.min.css">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- SweetAlert2 Dark Theme -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-dark@4/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <?php
    $isAdminPage = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin');
    if ($isAdminPage):
    ?>
    <!-- jQuery & DataTables (Admin Only) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    
    <style>
        /* DataTables Cyberpunk Glow Overrides */
        .dataTables_wrapper {
            color: #94a3b8 !important;
            font-family: inherit;
        }
        .dataTables_wrapper .dataTables_length {
            margin-bottom: 1.5rem !important;
            color: #94a3b8 !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
        }
        .dataTables_wrapper .dataTables_length select {
            background-color: #090d16 !important;
            border: 1px solid #1e293b !important;
            border-radius: 0.75rem !important;
            color: #cbd5e1 !important;
            padding: 0.25rem 1.5rem 0.25rem 0.75rem !important;
            outline: none !important;
            margin: 0 0.5rem !important;
        }
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 1.5rem !important;
            color: #94a3b8 !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
        }
        .dataTables_wrapper .dataTables_filter input {
            background-color: #090d16 !important;
            border: 1px solid #1e293b !important;
            border-radius: 0.75rem !important;
            color: #f8fafc !important;
            padding: 0.375rem 0.75rem !important;
            outline: none !important;
            margin-left: 0.5rem !important;
            transition: all 0.3s ease;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: rgba(99, 102, 241, 0.8) !important;
            box-shadow: 0 0 10px rgba(99, 102, 241, 0.15) !important;
        }
        .dataTables_wrapper .dataTables_info {
            color: #64748b !important;
            font-size: 0.75rem !important;
            font-weight: 500 !important;
            padding-top: 1rem !important;
        }
        .dataTables_wrapper .dataTables_paginate {
            padding-top: 1rem !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            background: #090d16 !important;
            border: 1px solid #1e293b !important;
            border-radius: 0.75rem !important;
            color: #94a3b8 !important;
            padding: 0.375rem 0.75rem !important;
            margin-left: 0.25rem !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            transition: all 0.2s ease;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #4f46e5 !important;
            border-color: #4f46e5 !important;
            color: white !important;
            box-shadow: 0 0 10px rgba(79, 70, 229, 0.4) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current, 
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #6366f1 !important;
            border-color: #6366f1 !important;
            color: white !important;
            box-shadow: 0 0 10px rgba(99, 102, 241, 0.4) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            background: #090d16 !important;
            border-color: #0f172a !important;
            color: #475569 !important;
            cursor: default !important;
            box-shadow: none !important;
        }
        table.dataTable {
            border-collapse: collapse !important;
            width: 100% !important;
            margin: 1.5rem 0 !important;
            border-bottom: 1px solid #1e293b !important;
        }
        table.dataTable thead th {
            border-bottom: 2px solid #1e293b !important;
            padding: 1rem 1.5rem !important;
            color: #94a3b8 !important;
            font-weight: 750 !important;
            font-size: 0.7rem !important;
            letter-spacing: 0.05em !important;
            text-transform: uppercase !important;
        }
        table.dataTable tbody td {
            padding: 1rem 1.5rem !important;
            border-bottom: 1px solid rgba(30, 41, 59, 0.5) !important;
        }
        
        /* Webkit Datetime Local Picker Icon Color Fix */
        input[type="datetime-local"]::-webkit-calendar-picker-indicator {
            filter: invert(0.85) sepia(1) saturate(5) hue-rotate(200deg) !important;
            cursor: pointer;
            border-radius: 0.375rem;
            padding: 0.125rem;
            transition: all 0.2s ease;
        }
        input[type="datetime-local"]::-webkit-calendar-picker-indicator:hover {
            filter: invert(1) sepia(1) saturate(5) hue-rotate(200deg) !important;
            background-color: rgba(99, 102, 241, 0.25) !important;
        }
    </style>
    <?php endif; ?>
    
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>
    
    <!-- DevTools Block & Content Protection -->
    <script>
        (function() {
            // Prevent mouse Right-Click Context Menu
            document.addEventListener('contextmenu', function (e) {
                e.preventDefault();
            });

            // Prevent Developer Tools shortcut keys
            document.addEventListener('keydown', function (e) {
                // Block F12 (keyCode 123)
                if (e.keyCode === 123 || e.key === 'F12') {
                    e.preventDefault();
                    return false;
                }

                // Block Ctrl+Shift+I / Cmd+Opt+I (DevTools Panel)
                if (((e.ctrlKey || e.metaKey) && e.shiftKey && (e.keyCode === 73 || e.code === 'KeyI')) || 
                    (e.metaKey && e.altKey && (e.keyCode === 73 || e.code === 'KeyI'))) {
                    e.preventDefault();
                    return false;
                }

                // Block Ctrl+Shift+J / Cmd+Opt+J (Console Panel)
                if (((e.ctrlKey || e.metaKey) && e.shiftKey && (e.keyCode === 74 || e.code === 'KeyJ')) || 
                    (e.metaKey && e.altKey && (e.keyCode === 74 || e.code === 'KeyJ'))) {
                    e.preventDefault();
                    return false;
                }

                // Block Ctrl+Shift+C / Cmd+Opt+C (Inspect Element)
                if (((e.ctrlKey || e.metaKey) && e.shiftKey && (e.keyCode === 67 || e.code === 'KeyC')) || 
                    (e.metaKey && e.altKey && (e.keyCode === 67 || e.code === 'KeyC'))) {
                    e.preventDefault();
                    return false;
                }

                // Block Ctrl+U / Cmd+U (View Source)
                if ((e.ctrlKey || e.metaKey) && (e.keyCode === 85 || e.code === 'KeyU')) {
                    e.preventDefault();
                    return false;
                }

                // Block Ctrl+S / Cmd+S (Save Page)
                if ((e.ctrlKey || e.metaKey) && (e.keyCode === 83 || e.code === 'KeyS')) {
                    e.preventDefault();
                    return false;
                }
            });

            // Anti-Debugging / Debugger Loop Trap (Only on non-admin pages to allow admin debugging)
            if (!window.location.pathname.startsWith('/admin')) {
                const trap = function() {
                    try {
                        (function() {
                            return false;
                        }
                        .constructor("debugger")
                        .call());
                    } catch (e) {}
                };
                
                const startTrap = function() {
                    // Constant trigger interval to ensure instant pause upon DevTools opening
                    setInterval(trap, 50);
                };
                
                if (document.readyState === 'complete') {
                    startTrap();
                } else {
                    window.addEventListener('load', startTrap);
                }
            }
        })();
    </script>
</head>
<body class="font-sans bg-[#060814] text-slate-100 text-[15px] min-h-screen flex flex-col antialiased relative overflow-x-hidden">
    <!-- Glowing background elements for premium look -->
    <div class="fixed top-[-10%] left-[-15%] w-[60%] h-[60%] glowing-blob-1 rounded-full blur-[140px] pointer-events-none z-[-1]"></div>
    <div class="fixed bottom-[-10%] right-[-15%] w-[60%] h-[60%] glowing-blob-2 rounded-full blur-[140px] pointer-events-none z-[-1]"></div>
<?php if (!$hideNav): ?>
    <?= View::include('partials/navbar', ['user' => $user]) ?>
<?php endif ?>

<?= View::include('partials/flash') ?>

<main class="flex-1 w-full">
    <?= View::section('content') ?>
</main>

<footer class="border-t border-slate-800/80 py-6 mt-8 text-center text-slate-500 text-xs">
    <?php
    $dbSettings = \App\Core\Database::getSettings();
    $webName = $dbSettings['web_name']['value'] ?? 'SeriesJeen';
    $lineVal = $dbSettings['contact_line']['value'] ?? '';
    $lineShow = !empty($dbSettings['contact_line']['is_visible']);
    $fbVal = $dbSettings['contact_facebook']['value'] ?? '';
    $fbShow = !empty($dbSettings['contact_facebook']['is_visible']);
    $othVal = $dbSettings['contact_other']['value'] ?? '';
    $othShow = !empty($dbSettings['contact_other']['is_visible']);

    $footerText = $dbSettings['web_footer_text']['value'] ?? 'api.seriesjeen.online';
    $footerUrl = $dbSettings['web_footer_url']['value'] ?? 'https://api.seriesjeen.online';
    ?>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between max-w-7xl mx-auto px-4 gap-4">
        <div class="text-left font-medium">
            <?= View::e($webName) ?> Player · <a class="hover:text-brand-500 transition duration-200" href="<?= View::e($footerUrl) ?>" target="_blank" rel="noopener"><?= View::e($footerText) ?></a>
        </div>
        
        <?php if ($lineShow || $fbShow || $othShow): ?>
        <div class="flex flex-wrap items-center gap-3 justify-center sm:justify-end">
            <span class="text-[10px] uppercase font-bold tracking-wider text-slate-650">ช่องทางการติดต่อ:</span>
            <?php if ($lineShow && $lineVal !== ''): ?>
                <a href="<?= View::e(str_starts_with($lineVal, 'http') ? $lineVal : 'https://line.me/R/ti/p/' . ltrim($lineVal, '@')) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-bold hover:bg-emerald-500/20 transition text-[10px] tracking-wide">
                    🟢 LINE: <?= View::e($lineVal) ?>
                </a>
            <?php endif ?>
            <?php if ($fbShow && $fbVal !== ''): ?>
                <a href="<?= View::e($fbVal) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 font-bold hover:bg-indigo-500/20 transition text-[10px] tracking-wide">
                    🔵 Facebook
                </a>
            <?php endif ?>
            <?php if ($othShow && $othVal !== ''): ?>
                <a href="<?= View::e($othVal) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-800 border border-slate-700 text-slate-300 font-bold hover:bg-slate-700 transition text-[10px] tracking-wide">
                    🌐 ช่องทางอื่น ๆ
                </a>
            <?php endif ?>
        </div>
        <?php endif ?>
    </div>
</footer>

<script src="/public/assets/js/app.js"></script>
<?= View::section('scripts') ?>
</body>
</html>
