<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * RapidTV (id 257) — provider captain. Flow A/B (single request returns everything):
 *   /drama/{id}            → {title, description, poster, totalEpisodes,
 *                             episodes:[{episode, episodeId, videos:[{quality, url, duration}]}]}
 *   /drama/{id}/episodes   → ROOT array of the same episode objects (alias)
 */
final class RapidtvAdapter extends BaseAdapter
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
        $root = $d['data'] ?? $d;
        return [
            'title'         => (string)($root['title'] ?? self::findTitle($root) ?? ''),
            'description'   => $root['description'] ?? self::findDescription($root),
            'cover'         => $root['poster'] ?? $root['cover'] ?? self::findCover($root),
            'episode_count' => isset($root['totalEpisodes']) ? (int)$root['totalEpisodes'] : self::findCountAnywhere($root),
            'genre'         => $root['genre'] ?? null,
            'extras'        => $root,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        $list = $d['episodes'] ?? $d['data']['episodes'] ?? [];
        if (empty($list)) {
            // /drama/{id}/episodes returns a ROOT array
            try {
                $alt = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId) . '/episodes');
                $list = array_is_list($alt) ? $alt : ($alt['episodes'] ?? $alt['data'] ?? []);
            } catch (\Throwable) { /* ignore */ }
        }

        $eps = [];
        foreach ($list as $k => $ep) {
            if (!is_array($ep)) continue;
            $sources = [];
            foreach (($ep['videos'] ?? []) as $v) {
                if (!is_array($v) || empty($v['url'])) continue;
                $sources[] = [
                    'quality' => (string)($v['quality'] ?? 'auto'),
                    'codec'   => 'h264',
                    'url'     => (string)$v['url'],
                ];
            }
            $eps[] = [
                'episode'  => (int)($ep['episode'] ?? ($k + 1)),
                'id'       => (string)($ep['episodeId'] ?? $ep['id'] ?? ''),
                'locked'   => false,
                'duration' => isset($ep['duration']) ? (int)$ep['duration'] : null,
                'sources'  => $sources,
                'subtitles'=> [],
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps];
    }
}
