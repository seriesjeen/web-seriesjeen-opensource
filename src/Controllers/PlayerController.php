<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\ApiException;
use App\Services\PlatformRegistry;
use App\Services\StreamProxy;

final class PlayerController
{
    public function watch(Request $request, array $args): void
    {
        $slug = strtolower($args['platform']);
        $seriesId = (string)$args['seriesId'];
        $episode = (int)$args['ep'];

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
        } catch (ApiException) {
            $episodes = ['series_id' => $seriesId, 'episodes' => []];
        }

        // Resolve current episode sources/subtitles
        $current = null;
        foreach ($episodes['episodes'] ?? [] as $ep) {
            if ((int)($ep['episode'] ?? 0) === $episode) { $current = $ep; break; }
        }

        // If lazy (no embedded sources), fetch from playEpisode() if adapter supports
        $needsLazyFetch = !$current || empty($current['sources']);
        if ($needsLazyFetch && method_exists($adapter, 'playEpisode')) {
            try {
                $play = $adapter->playEpisode($seriesId, $episode);
                $current = array_merge($current ?? ['episode' => $episode], $play);
            } catch (ApiException $e) {
                $current = ($current ?? []) + ['episode' => $episode, 'sources' => [], 'subtitles' => []];
            }
        }

        // Route every CDN/api URL through the same-origin proxy so the browser never
        // hits cross-origin media (eliminates CORS / mixed-content errors uniformly).
        $current = StreamProxy::wrapPayload($current ?? ['episode' => $episode, 'sources' => [], 'subtitles' => []]);

        Response::html(View::render('player/watch', [
            'slug'      => $slug,
            'display'   => PlatformRegistry::display($slug),
            'series_id' => $seriesId,
            'detail'    => $detail,
            'episode'   => $episode,
            'current'   => $current,
            'episodes'  => $episodes['episodes'] ?? [],
        ]));
    }
}
