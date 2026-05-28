<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;

final class AuthController
{
    public function showLogin(Request $request, array $args): void
    {
        if (Session::get('api_key')) {
            Response::redirect('/');
        }
        Response::html(View::render('auth/login', [
            'error' => Session::flash('login_error'),
            'old_key_mask' => '',
        ]));
    }

    public function doLogin(Request $request, array $args): void
    {
        $key = trim((string)$request->post('api_key'));
        $result = (new AuthService())->validateKey($key);

        if (!$result['ok']) {
            Session::flash('login_error', $result['error'] ?? 'เข้าสู่ระบบไม่สำเร็จ');
            Response::redirect('/login');
        }

        session_regenerate_id(true);
        Session::put('api_key', $_ENV['SERIES_API_KEY'] ?? '');
        Session::put('user_code', $key);
        Session::put('role', $result['role'] ?? 'user');
        Session::put('user', $result['user']);
        Session::put('platforms', $result['platforms']);
        Session::put('logged_in_at', date('c'));

        if (($result['role'] ?? 'user') === 'admin') {
            Response::redirect('/admin');
        }

        $intended = Session::flash('intended');
        Response::redirect(is_string($intended) && $intended !== '' ? $intended : '/');
    }

    public function logout(Request $request, array $args): void
    {
        Session::destroy();
        Response::redirect('/login');
    }
}
