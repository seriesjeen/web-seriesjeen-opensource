<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;

final class CsrfCheck implements Middleware
{
    public function handle(Request $request): void
    {
        if ($request->method === 'GET') return;
        $token = $request->post('_csrf') ?? $request->header('X-CSRF-Token');
        if (!Csrf::verify($token)) {
            if ($request->wantsJson()) {
                Response::json(['error' => 'Invalid CSRF token'], 419);
            }
            Response::html('<h1>419 — CSRF token mismatch</h1>', 419);
        }
    }
}
