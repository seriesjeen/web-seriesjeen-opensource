<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * StardustTV (id 258) — provider captain. Flow B (slug routing token + embedded sources):
 *   /detail/drama/{drama_id} → data.{title, poster, intro, totalEpisodes,
 *                                    episodes:[{id, sort, duration, h264, h265, is_vip}]}
 *   /detail/drama/{drama_id}/episode/{ep} → per-episode fallback (h264/h265)
 *
 * The slug segment is a routing token only; any value ("drama") routes correctly.
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
        $count = isset($d['totalEpisodes']) ? (int)$d['totalEpisodes'] : self::findCountAnywhere($d);
        return [
            'title'         => (string)($d['title'] ?? self::findTitle($d) ?? ''),
            'description'   => $d['intro'] ?? $d['description'] ?? $d['desc'] ?? self::findDescription($d),
            'cover'         => $d['poster'] ?? $d['cover'] ?? self::findCover($d),
            'episode_count' => $count,
            'genre'         => $d['genre'] ?? null,
            'extras'        => $d,
        ];
    }

    private function parseSources(array $ep): array
    {
        $sources = [];
        if (!empty($ep['h264'])) $sources[] = ['quality'=>'auto','codec'=>'h264','url'=>(string)$ep['h264']];
        if (!empty($ep['h265'])) $sources[] = ['quality'=>'auto','codec'=>'h265','url'=>(string)$ep['h265']];
        if (empty($sources)) {
            $url = $ep['url'] ?? $ep['videoUrl'] ?? $ep['video_url'] ?? $ep['m3u8_url'] ?? null;
            if ($url) $sources[] = ['quality'=>'auto','codec'=>'h264','url'=>(string)$url];
        }
        return $sources;
    }

    public function episodes(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        $list = $d['episodes'] ?? $d['episode_list'] ?? $d['list'] ?? [];
        $eps = [];
        foreach ($list as $i => $ep) {
            if (!is_array($ep)) continue;
            $sources = $this->parseSources($ep);
            $eps[] = [
                'episode'  => (int)($ep['sort'] ?? $ep['episode'] ?? $ep['episodeNumber'] ?? ($i + 1)),
                'id'       => (string)($ep['id'] ?? ''),
                'locked'   => !empty($ep['locked']) || !empty($ep['is_lock']),
                'duration' => isset($ep['duration']) ? (int)$ep['duration'] : null,
                'sources'  => $sources,
                'subtitles'=> [],
                'lazy'     => empty($sources),
            ];
        }
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
        // Prefer the embedded sources resolved in detail.
        foreach (($this->episodes($seriesId)['episodes'] ?? []) as $ep) {
            if ((int)($ep['episode'] ?? 0) === $episode && !empty($ep['sources'])) {
                return ['episode'=>$episode, 'locked'=>!empty($ep['locked']), 'sources'=>$ep['sources'], 'subtitles'=>[]];
            }
        }
        try {
            $resp = $this->api->getJson($this->basePath() . '/detail/' . self::SLUG . '/' . rawurlencode($seriesId) . '/episode/' . $episode);
            $d = $resp['data'] ?? $resp;
            return ['episode'=>$episode, 'locked'=>!empty($d['locked']), 'sources'=>$this->parseSources($d), 'subtitles'=>[]];
        } catch (\Throwable) {
            return ['episode'=>$episode, 'locked'=>false, 'sources'=>[], 'subtitles'=>[]];
        }
    }
}
