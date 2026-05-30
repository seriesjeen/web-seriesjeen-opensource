<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * MicroDrama (id 146) — provider captain. Flow A (single request returns everything):
 *   /drama/{id} → {drama:{id, title, description, total_episodes, cover},
 *                  episodes:[{index, videos:[{quality, url, width, height}]}]}
 */
final class MicrodramaAdapter extends BaseAdapter
{
    private array $cache = [];

    private function fetch(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        return $this->cache[$seriesId] = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId));
    }

    public function detail(string $seriesId): array
    {
        $resp = $this->fetch($seriesId);
        $d = $resp['drama'] ?? $resp['data'] ?? $resp;
        return [
            'title'         => (string)($d['title'] ?? $d['name'] ?? self::findTitle($resp) ?? ''),
            'description'   => $d['description'] ?? $d['desc'] ?? self::findDescription($resp),
            'cover'         => $d['cover'] ?? $d['image'] ?? self::findCover($resp),
            'episode_count' => isset($d['total_episodes']) ? (int)$d['total_episodes'] : self::findCountAnywhere($resp),
            'genre'         => $d['genre'] ?? null,
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $resp = $this->fetch($seriesId);
        $list = $resp['episodes'] ?? [];
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
                'episode'  => (int)($ep['index'] ?? ($k + 1)),
                'id'       => (string)($ep['id'] ?? ''),
                'locked'   => false,
                'sources'  => $sources,
                'subtitles'=> [],
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps];
    }
}
