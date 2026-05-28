<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\PlatformRegistry;

final class HomeController
{
    public function index(Request $request, array $args): void
    {
        $platforms = Session::get('platforms', []);

        // enrich with display name from registry
        foreach ($platforms as &$p) {
            $p['display'] = PlatformRegistry::display($p['slug']);
        }
        unset($p);

        Response::html(View::render('home', [
            'platforms' => $platforms,
        ]));
    }
}
