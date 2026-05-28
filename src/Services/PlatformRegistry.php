<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Http\SeriesApiClient;
use App\Services\Adapters\GenericAdapter;

final class PlatformRegistry
{
    private static ?array $config = null;

    public static function config(): array
    {
        if (self::$config === null) {
            $path = dirname(__DIR__, 2) . '/config/platforms.php';
            self::$config = is_file($path) ? require $path : [];
        }
        return self::$config;
    }

    public static function knows(string $slug): bool
    {
        return array_key_exists(strtolower($slug), self::config());
    }

    public static function display(string $slug): string
    {
        $cfg = self::config()[strtolower($slug)] ?? null;
        return $cfg['display'] ?? ucfirst($slug);
    }

    public static function userHas(string $slug): bool
    {
        $platforms = Session::get('platforms', []);
        foreach ((array)$platforms as $p) {
            if (strtolower((string)($p['slug'] ?? '')) === strtolower($slug)) return true;
        }
        return false;
    }

    public static function resolve(string $slug): PlatformAdapter
    {
        $slug = strtolower($slug);
        $cfg = self::config()[$slug] ?? null;
        $adapterClass = $cfg['adapter'] ?? GenericAdapter::class;

        $apiKey = Session::get('api_key');
        if (!is_string($apiKey) || $apiKey === '') {
            throw new \RuntimeException('No API key in session');
        }
        $client = new SeriesApiClient($apiKey);
        return new $adapterClass($client, $slug);
    }
}
