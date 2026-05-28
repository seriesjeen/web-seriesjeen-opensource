<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * RapidTV's /drama/{id} endpoint reliably returns an empty stub ({dide:null, mbird:"success.", sdevi:1})
 * for every drama_id we've tested. We therefore reconstruct detail from the catalog `/list` response
 * (which contains title, cover, description, episode_count for the series).
 *
 * Episodes still come from /drama/{id}/{episode} when streaming, but the count we know from the catalog.
 */
final class RapidtvAdapter extends BaseAdapter
{
    private array $catalogCache = [];

    private function findInCatalog(string $seriesId): ?array
    {
        if (isset($this->catalogCache[$seriesId])) return $this->catalogCache[$seriesId];
        // search via /search (cheaper) then fall back to scanning a few pages of /list
        try {
            $r = $this->api->getJson($this->basePath() . '/search', ['keyword' => $seriesId, 'page_size' => 50]);
            foreach (($r['items'] ?? []) as $item) {
                if ((string)($item['series_id'] ?? '') === $seriesId) {
                    return $this->catalogCache[$seriesId] = $item;
                }
            }
        } catch (\Throwable) { /* ignore */ }

        for ($page = 1; $page <= 5; $page++) {
            try {
                $r = $this->api->getJson($this->basePath() . '/list', ['page' => $page, 'page_size' => 100]);
                foreach (($r['items'] ?? []) as $item) {
                    if ((string)($item['series_id'] ?? '') === $seriesId) {
                        return $this->catalogCache[$seriesId] = $item;
                    }
                }
            } catch (\Throwable) { break; }
        }
        return $this->catalogCache[$seriesId] = null;
    }

    public function detail(string $seriesId): array
    {
        $item = $this->findInCatalog($seriesId);
        return [
            'title'         => (string)($item['title'] ?? ''),
            'description'   => $item['description'] ?? null,
            'cover'         => $item['cover'] ?? null,
            'episode_count' => isset($item['episode_count']) ? (int)$item['episode_count'] : null,
            'genre'         => $item['genre'] ?? null,
            'extras'        => $item ?? [],
        ];
    }

    public function episodes(string $seriesId): array
    {
        $item = $this->findInCatalog($seriesId);
        $count = (int)($item['episode_count'] ?? 0);
        $eps = [];
        for ($i = 1; $i <= $count; $i++) {
            $eps[] = ['episode'=>$i, 'locked'=>false, 'sources'=>[], 'subtitles'=>[], 'lazy'=>true];
        }
        return ['series_id' => $seriesId, 'episodes' => $eps, 'lazy' => true];
    }

    public function playEpisode(string $seriesId, int $episode): array
    {
        try {
            $resp = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId) . '/' . $episode);
            $url = $this->findUrl($resp);
            return ['episode'=>$episode, 'locked'=>false,
                    'sources'=>$url ? [['quality'=>'auto','codec'=>'h264','url'=>$url]] : [],
                    'subtitles'=>[]];
        } catch (\Throwable) {
            return ['episode'=>$episode, 'locked'=>false, 'sources'=>[], 'subtitles'=>[]];
        }
    }

    private function findUrl(mixed $v, int $depth = 0): ?string
    {
        if ($depth > 8) return null;
        if (is_string($v) && preg_match('#^https?://.*\.(m3u8|mp4)#i', $v)) return $v;
        if (is_array($v)) foreach ($v as $sub) {
            $r = $this->findUrl($sub, $depth + 1);
            if ($r) return $r;
        }
        return null;
    }
}
