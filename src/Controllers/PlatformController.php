<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\ApiException;
use App\Services\PlatformRegistry;

final class PlatformController
{
    public function list(Request $request, array $args): void
    {
        $slug = strtolower($args['platform']);
        if (!PlatformRegistry::knows($slug) || !PlatformRegistry::userHas($slug)) {
            Response::html(View::render('errors/404'), 404);
        }

        $filters = [
            'page'      => $request->queryInt('page', 1) ?: 1,
            'page_size' => 24,
            'locale'    => $request->query('locale'),
            'keyword'   => $request->query('q'),
            'genre'     => $request->queryInt('genre'),
        ];

        $adapter = PlatformRegistry::resolve($slug);
        $error = null;
        $result = ['platform_id' => null, 'page' => 1, 'page_size' => 24, 'total' => null, 'items' => []];
        $genres = [];

        try {
            $result = $adapter->listSeries($filters);
        } catch (ApiException $e) {
            $error = $e->getMessage();
        }
        try {
            $genres = $adapter->genres($filters['locale']);
        } catch (\Throwable) { /* ignore */ }

        Response::html(View::render('platform/list', [
            'slug'    => $slug,
            'display' => PlatformRegistry::display($slug),
            'filters' => $filters,
            'result'  => $result,
            'genres'  => $genres,
            'error'   => $error,
        ]));
    }

    public function genres(Request $request, array $args): void
    {
        $slug = strtolower($args['platform']);
        if (!PlatformRegistry::knows($slug) || !PlatformRegistry::userHas($slug)) {
            Response::html(View::render('errors/404'), 404);
        }
        $adapter = PlatformRegistry::resolve($slug);
        $genres = $adapter->genres($request->query('locale'));
        Response::html(View::render('platform/genres', [
            'slug' => $slug, 'display' => PlatformRegistry::display($slug), 'genres' => $genres,
        ]));
    }
}
