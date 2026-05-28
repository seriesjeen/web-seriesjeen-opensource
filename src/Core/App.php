<?php
declare(strict_types=1);

namespace App\Core;

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\PlatformController;
use App\Controllers\SeriesController;
use App\Controllers\PlayerController;
use App\Controllers\ApiController;
use App\Middleware\RequireAuth;
use App\Middleware\RequireAdmin;
use App\Middleware\CsrfCheck;
use App\Controllers\AdminController;
use App\Core\Database;
use Dotenv\Dotenv;

final class App
{
    public function __construct(public readonly string $basePath)
    {
    }

    public function run(): void
    {
        if (is_file($this->basePath . '/.env')) {
            Dotenv::createImmutable($this->basePath)->safeLoad();
        }

        // Initialize Database
        Database::init();

        Session::start($_ENV['SESSION_NAME'] ?? 'seriesjeen_sess');

        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) return false;
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([$this, 'handleException']);

        $router = new Router();
        $this->registerRoutes($router);

        $router->dispatch(new Request());
    }

    private function registerRoutes(Router $router): void
    {
        // Public
        $router->get('/login',  [AuthController::class, 'showLogin']);
        $router->post('/login', [AuthController::class, 'doLogin'], [CsrfCheck::class]);
        $router->post('/logout',[AuthController::class, 'logout'],  [CsrfCheck::class, RequireAuth::class]);

        // Authed pages
        $auth = [RequireAuth::class];
        $router->get('/',                                    [HomeController::class, 'index'], $auth);
        $router->get('/p/{platform}',                        [PlatformController::class, 'list'], $auth);
        $router->get('/p/{platform}/genres',                 [PlatformController::class, 'genres'], $auth);
        $router->get('/p/{platform}/series/{seriesId}',      [SeriesController::class, 'detail'], $auth);
        $router->get('/p/{platform}/watch/{seriesId}/{ep}',  [PlayerController::class, 'watch'], $auth);

        // Admin area
        $admin = [RequireAuth::class, RequireAdmin::class];
        $router->get('/admin',              [AdminController::class, 'index'], $admin);
        $router->get('/admin/settings',     [AdminController::class, 'settings'], $admin);
        $router->post('/admin/keys/create', [AdminController::class, 'createKey'], array_merge($admin, [CsrfCheck::class]));
        $router->post('/admin/keys/delete', [AdminController::class, 'deleteKey'], array_merge($admin, [CsrfCheck::class]));
        $router->post('/admin/keys/reset-hwid', [AdminController::class, 'resetHwid'], array_merge($admin, [CsrfCheck::class]));
        $router->post('/admin/settings/update', [AdminController::class, 'updateSettings'], array_merge($admin, [CsrfCheck::class]));

        // JSON API
        $router->get('/api/melolo/key/{vid}',                       [ApiController::class, 'meloloKey'], $auth);
        $router->get('/api/p/{platform}/search',                    [ApiController::class, 'search'], $auth);
        // Generic media proxy (CORS bypass) — HMAC-signed by StreamProxy
        $router->get('/proxy/stream',                               [ApiController::class, 'proxyStream'], $auth);
        $router->get('/proxy/m3u8',                                 [ApiController::class, 'proxyM3u8'],   $auth);
        $router->get('/proxy/vtt',                                  [ApiController::class, 'proxyVtt'],    $auth);
    }

    public function handleException(\Throwable $e): void
    {
        error_log('[App] ' . $e::class . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
        $status = $e instanceof \App\Http\ApiException ? $e->httpStatus : 500;

        if ((new Request())->wantsJson()) {
            Response::json([
                'error' => $e->getMessage(),
                'trace' => $debug ? explode("\n", $e->getTraceAsString()) : null,
            ], $status);
        }

        try {
            Response::html(View::render('errors/500', [
                'message' => $debug ? $e->getMessage() : 'มีบางอย่างผิดพลาด',
                'trace' => $debug ? $e->getTraceAsString() : null,
            ]), $status);
        } catch (\Throwable) {
            http_response_code($status);
            echo '<h1>Error</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
            exit;
        }
    }
}
