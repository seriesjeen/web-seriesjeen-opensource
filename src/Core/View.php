<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    private static string $layout = '';
    private static array $sections = [];
    private static string $currentSection = '';
    private static string $title = '';
    private static bool $hideNav = false;

    public static function title(?string $value = null): string
    {
        if ($value !== null) self::$title = $value;
        return self::$title;
    }

    public static function hideNav(?bool $value = null): bool
    {
        if ($value !== null) self::$hideNav = $value;
        return self::$hideNav;
    }

    public static function render(string $template, array $data = []): string
    {
        $viewsPath = dirname(__DIR__, 2) . '/views';
        $file = $viewsPath . '/' . ltrim($template, '/') . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $template");
        }

        self::$layout = '';
        self::$sections = [];
        self::$title = '';
        self::$hideNav = false;

        $content = self::renderFile($file, $data);

        if (self::$layout !== '') {
            $layoutFile = $viewsPath . '/' . self::$layout . '.php';
            if (!is_file($layoutFile)) {
                throw new \RuntimeException('Layout not found: ' . self::$layout);
            }
            // Only set 'content' section if view didn't explicitly start one
            if (!isset(self::$sections['content'])) {
                self::$sections['content'] = $content;
            }
            $content = self::renderFile($layoutFile, $data);
        }

        return $content;
    }

    private static function renderFile(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }

    public static function layout(string $name): void
    {
        self::$layout = $name;
    }

    public static function start(string $name): void
    {
        self::$currentSection = $name;
        ob_start();
    }

    public static function stop(): void
    {
        self::$sections[self::$currentSection] = ob_get_clean();
        self::$currentSection = '';
    }

    public static function section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function include(string $partial, array $data = []): string
    {
        $viewsPath = dirname(__DIR__, 2) . '/views';
        $file = $viewsPath . '/' . ltrim($partial, '/') . '.php';
        if (!is_file($file)) return '';
        return self::renderFile($file, $data);
    }
}
