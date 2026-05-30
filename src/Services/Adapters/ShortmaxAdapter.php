<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * ShortMax (id 16) — provider captain. Flow A/C:
 *   /detail/{id}        → data.{id, name, cover, episodes (int), summary}  (count only → lazy)
 *   /play/{id}?ep=      → data.{episode, total, video:{video_480, video_720, video_1080}}
 */
final class ShortmaxAdapter extends BaseAdapter
{
    private array $cache = [];

    private function fetchDetail(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        $resp = $this->api->getJson($this->basePath() . '/detail/' . rawurlencode($seriesId));
        return $this->cache[$seriesId] = ($resp['data'] ?? $resp);
    }

    public function detail(string $seriesId): array
    {
        $d = $this->fetchDetail($seriesId);
        return [
            'title'         => (string)($d['name'] ?? $d['title'] ?? self::findTitle($d) ?? ''),
            'description'   => $d['summary'] ?? $d['description'] ?? self::findDescription($d),
            'cover'         => $d['cover'] ?? self::findCover($d),
            'episode_count' => isset($d['episodes']) && (is_int($d['episodes']) || ctype_digit((string)$d['episodes'])) ? (int)$d['episodes'] : self::findCountAnywhere($d),
            'genre'         => $d['genre'] ?? $d['category'] ?? null,
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $count = (int)($this->detail($seriesId)['episode_count'] ?? 0);
        $eps = [];
        for ($i = 1; $i <= $count; $i++) {
            $eps[] = ['episode'=>$i, 'locked'=>false, 'sources'=>[], 'subtitles'=>[], 'lazy'=>true];
        }
        return ['series_id' => $seriesId, 'episodes' => $eps, 'lazy' => true];
    }

    public function playEpisode(string $seriesId, int $episode): array
    {
        try {
            $resp = $this->api->getJson($this->basePath() . '/play/' . rawurlencode($seriesId), ['ep' => $episode]);
        } catch (\Throwable) {
            return ['episode' => $episode, 'locked' => false, 'sources' => [], 'subtitles' => []];
        }
        $d = $resp['data'] ?? $resp;
        $video = $d['video'] ?? [];
        $sources = [];
        foreach (['video_1080' => '1080', 'video_720' => '720', 'video_480' => '480', 'video_540' => '540'] as $key => $q) {
            if (!empty($video[$key])) {
                $sources[] = ['quality' => $q, 'codec' => 'h264', 'url' => (string)$video[$key]];
            }
        }
        return ['episode' => $episode, 'locked' => !empty($d['locked']), 'sources' => $sources, 'subtitles' => []];
    }
}
