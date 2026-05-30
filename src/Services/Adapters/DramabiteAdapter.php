<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * DramaBite (id 115) — provider captain. Flow B:
 *   /drama/{cid}        → {id, cover, episodes:[{id, number, title, free}]}  (episode list, no URLs → lazy)
 *   /play/{cid}/{vid}   → resolves a single episode m3u8 (vid = episode's id)
 */
final class DramabiteAdapter extends BaseAdapter
{
    private array $cache = [];

    private function fetch(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        return $this->cache[$seriesId] = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId));
    }

    public function detail(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        $list = $d['episodes'] ?? [];
        // /drama has no title/desc field — derive a base title from the first episode's title.
        $title = (string)($d['title'] ?? '');
        if ($title === '' && !empty($list[0]['title'])) {
            $title = (string)preg_replace('/[-\s]*(EP\.?\s*)?\d+\s*$/i', '', (string)$list[0]['title']);
        }
        return [
            'title'         => $title,
            'description'   => $d['desc'] ?? $d['description'] ?? null,
            'cover'         => $d['cover'] ?? (is_array($list[0] ?? null) ? ($list[0]['cover'] ?? null) : null),
            'episode_count' => is_array($list) ? count($list) : (isset($d['episodes']) && is_int($d['episodes']) ? $d['episodes'] : null),
            'genre'         => self::flattenGenre($d['tags'] ?? null),
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        $list = $d['episodes'] ?? [];
        $eps = [];
        foreach ($list as $i => $ep) {
            if (!is_array($ep)) continue;
            $url = $ep['url'] ?? $ep['hls_url'] ?? null;
            $sources = $url ? [['quality'=>'auto','codec'=>'h264','url'=>(string)$url]] : [];
            $eps[] = [
                'episode'  => (int)($ep['number'] ?? $ep['episode'] ?? ($i + 1)),
                'id'       => (string)($ep['id'] ?? $ep['vid'] ?? ''),
                'locked'   => false,
                'cover'    => $ep['cover'] ?? null,
                'sources'  => $sources,
                'subtitles'=> [],
                'lazy'     => empty($sources),
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps, 'lazy' => true];
    }

    public function playEpisode(string $seriesId, int $episode): array
    {
        // Resolve the episode's vid from the cached drama list, then /play/{cid}/{vid}.
        $d = $this->fetch($seriesId);
        $vid = null;
        foreach (($d['episodes'] ?? []) as $i => $ep) {
            if (!is_array($ep)) continue;
            if ((int)($ep['number'] ?? ($i + 1)) === $episode) { $vid = (string)($ep['id'] ?? ''); break; }
        }
        if ($vid === null || $vid === '') {
            return ['episode' => $episode, 'locked' => false, 'sources' => [], 'subtitles' => []];
        }

        try {
            $resp = $this->api->getJson($this->basePath() . '/play/' . rawurlencode($seriesId) . '/' . rawurlencode($vid));
        } catch (\Throwable) {
            return ['episode' => $episode, 'locked' => false, 'sources' => [], 'subtitles' => []];
        }
        $p = $resp['data'] ?? $resp;
        $sources = [];
        foreach (['video', 'url', 'm3u8', 'm3u8_url', 'hls_url', 'video_url'] as $f) {
            if (!empty($p[$f]) && is_string($p[$f])) { $sources[] = ['quality'=>'auto','codec'=>'h264','url'=>(string)$p[$f]]; break; }
        }
        return ['episode' => $episode, 'locked' => false, 'sources' => $sources, 'subtitles' => []];
    }
}
