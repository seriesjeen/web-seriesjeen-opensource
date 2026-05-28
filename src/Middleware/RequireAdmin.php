<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class RequireAdmin implements Middleware
{
    public function handle(Request $request): void
    {
        if (Session::get('role') === 'admin') {
            return;
        }

        if ($request->wantsJson()) {
            Response::json(['error' => 'Forbidden: Admins only'], 403);
        }
        Session::flash('flash_error', 'คุณไม่มีสิทธิ์เข้าถึงระบบหลังบ้าน');
        Response::redirect('/');
    }
}
