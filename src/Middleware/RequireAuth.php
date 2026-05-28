<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class RequireAuth implements Middleware
{
    public function handle(Request $request): void
    {
        if (Session::get('api_key')) return;

        if ($request->wantsJson()) {
            Response::json(['error' => 'Authentication required'], 401);
        }
        Session::flash('intended', $request->path);
        Response::redirect('/login');
    }
}
