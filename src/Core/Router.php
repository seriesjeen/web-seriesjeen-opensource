<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:array{0:string,1:string}, middleware:array<string>, params:array<string>}> */
    private array $routes = [];

    public function get(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    private function add(string $method, string $pattern, array $handler, array $middleware): void
    {
        $params = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) use (&$params) {
            $params[] = $m[1];
            return '([^/]+)';
        }, $pattern);
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => '#^' . $regex . '$#u',
            'handler' => $handler,
            'middleware' => $middleware,
            'params' => $params,
        ];
    }

    public function dispatch(Request $request): void
    {
        $matchedMethod = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) continue;
            $matchedMethod = true;
            if ($route['method'] !== $request->method) continue;

            $args = [];
            foreach ($route['params'] as $i => $name) {
                $args[$name] = urldecode($m[$i + 1] ?? '');
            }

            foreach ($route['middleware'] as $mwClass) {
                /** @var \App\Middleware\Middleware $mw */
                $mw = new $mwClass();
                $mw->handle($request);
            }

            [$class, $method] = $route['handler'];
            $controller = new $class();
            $controller->$method($request, $args);
            return;
        }

        if ($matchedMethod && !$request->wantsJson()) {
            Response::html('<h1>405 Method Not Allowed</h1>', 405);
        }
        if ($request->wantsJson()) {
            Response::json(['error' => 'Not Found'], 404);
        }
        Response::html(View::render('errors/404'), 404);
    }
}
