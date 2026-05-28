<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * StardustTV requires two path segments: {slug}/{drama_id}. The slug field in the response
 * is always empty in practice — the URL slug appears to be a routing token rather than data.
 * Any reasonable value (e.g. "drama") routes correctly.
 *
 *   /detail/{slug}/{drama_id}                  → meta + episodes
 *   /detail/{slug}/{drama_id}/episode/{ep}     → per-episode stream
 */
final class StardusttvAdapter extends BaseAdapter
{
    private const SLUG = 'drama';
    private array $cache = [];

    private function fetch(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        $resp = $this->api->getJson($this->basePath() . '/detail/' . self::SLUG . '/' . rawurlencode($seriesId));
        return $this->cache[$seriesId] = ($resp['data'] ?? $resp);
    }

    public function detail(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        $count = self::findCountAnywhere($d);
        return [
            'title'         => (string)($d['title'] ?? self::findTitle($d) ?? ''),
            'description'   => $d['description'] ?? $d['desc'] ?? self::findDescription($d),
            'cover'         => $d['poster'] ?? $d['cover'] ?? self::findCover($d),
            'episode_count' => $count,
            'genre'         => $d['genre'] ?? null,
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        // Find an array of episodes anywhere in the response
        $list = $d['episodes'] ?? $d['episode_list'] ?? $d['list'] ?? [];
        if (empty($list)) {
            foreach ($d as $v) {
                if (is_array($v) && array_is_list($v) && count($v) > 0 && is_array($v[0])
                    && (isset($v[0]['episode']) || isset($v[0]['episodeNumber']) || isset($v[0]['index']))) {
                    $list = $v;
                    break;
                }
            }
        }
        $eps = [];
        foreach ($list as $i => $ep) {
            if (!is_array($ep)) continue;
            $url = $ep['url'] ?? $ep['videoUrl'] ?? $ep['video_url'] ?? $ep['m3u8_url'] ?? null;
            $eps[] = [
                'episode'  => (int)($ep['episode'] ?? $ep['episodeNumber'] ?? $ep['index'] ?? ($i + 1)),
                'id'       => (string)($ep['id'] ?? $ep['episode_id'] ?? ''),
                'locked'   => !empty($ep['locked']) || !empty($ep['is_lock']),
                'cover'    => $ep['cover'] ?? null,
                'duration' => isset($ep['duration']) ? (int)$ep['duration'] : null,
                'sources'  => $url ? [['quality'=>'auto','codec'=>'h264','url'=>(string)$url]] : [],
                'subtitles'=> [],
                'lazy'     => $url === null,
            ];
        }
        // If no episode list, derive from episode_count
        if (empty($eps)) {
            $count = self::findCountAnywhere($d) ?? 0;
            for ($i = 1; $i <= $count; $i++) {
                $eps[] = ['episode'=>$i, 'locked'=>false, 'sources'=>[], 'subtitles'=>[], 'lazy'=>true];
            }
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps, 'lazy' => true];
    }

    public function playEpisode(string $seriesId, int $episode): array
    {
        try {
            $resp = $this->api->getJson($this->basePath() . '/detail/' . self::SLUG . '/' . rawurlencode($seriesId) . '/episode/' . $episode);
            $d = $resp['data'] ?? $resp;
            // Response has {episode, h264 (proxied url), h265?, ...}
            $sources = [];
            if (!empty($d['h264'])) $sources[] = ['quality'=>'auto','codec'=>'h264','url'=>(string)$d['h264']];
            if (!empty($d['h265'])) $sources[] = ['quality'=>'auto','codec'=>'h265','url'=>(string)$d['h265']];
            if (empty($sources) && !empty($d['url'])) $sources[] = ['quality'=>'auto','codec'=>'h264','url'=>(string)$d['url']];
            return [
                'episode' => $episode,
                'locked'  => !empty($d['locked']),
                'sources' => $sources,
                'subtitles' => [],
            ];
        } catch (\Throwable) {
            return ['episode'=>$episode,'locked'=>false,'sources'=>[],'subtitles'=>[]];
        }
    }
}
