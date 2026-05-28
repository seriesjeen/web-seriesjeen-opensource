<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\ApiException;
use App\Services\PlatformRegistry;

final class SeriesController
{
    public function detail(Request $request, array $args): void
    {
        $slug = strtolower($args['platform']);
        $seriesId = (string)$args['seriesId'];
        if (!PlatformRegistry::knows($slug) || !PlatformRegistry::userHas($slug)) {
            Response::html(View::render('errors/404'), 404);
        }
        $adapter = PlatformRegistry::resolve($slug);

        try {
            $detail = $adapter->detail($seriesId);
        } catch (ApiException $e) {
            Response::html(View::render('errors/500', ['message' => $e->getMessage()]), $e->httpStatus);
        }

        try {
            $episodes = $adapter->episodes($seriesId);
        } catch (ApiException $e) {
            $episodes = ['series_id' => $seriesId, 'episodes' => []];
        }

        Response::html(View::render('series/detail', [
            'slug'      => $slug,
            'display'   => PlatformRegistry::display($slug),
            'series_id' => $seriesId,
            'detail'    => $detail,
            'episodes'  => $episodes['episodes'] ?? [],
        ]));
    }
}
