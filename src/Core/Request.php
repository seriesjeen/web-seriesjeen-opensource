<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public readonly string $method;
    public readonly string $path;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $this->path = '/' . trim($path, '/');
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $val = $_GET[$key] ?? null;
        if ($val === null || $val === '') return $default;
        return is_string($val) ? $val : $default;
    }

    public function queryInt(string $key, ?int $default = null): ?int
    {
        $v = $this->query($key);
        return $v !== null && ctype_digit($v) ? (int)$v : $default;
    }

    public function post(string $key, ?string $default = null): ?string
    {
        $val = $_POST[$key] ?? null;
        return is_string($val) ? $val : $default;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $name = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$name] ?? $default;
    }

    public function wantsJson(): bool
    {
        $accept = $this->header('Accept', '') ?? '';
        $x = $this->header('X-Requested-With', '');
        return str_contains($accept, 'application/json') || $x === 'XMLHttpRequest' || str_starts_with($this->path, '/api/');
    }

    public function clientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
