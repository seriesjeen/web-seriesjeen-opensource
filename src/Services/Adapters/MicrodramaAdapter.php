<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * MicroDrama:
 *   /drama/{id}                       → meta (data.total_episodes)
 *   /play/{drama_id}/{episode_no}     → {success, data:{videos:[{quality,url}]}}
 */
final class MicrodramaAdapter extends BaseAdapter
{
    public function detail(string $seriesId): array
    {
        $resp = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId));
        $d = $resp['data'] ?? $resp;
        return [
            'title'         => (string)($d['title'] ?? $d['name'] ?? self::findTitle($d) ?? ''),
            'description'   => $d['description'] ?? $d['desc'] ?? self::findDescription($d),
            'cover'         => $d['cover'] ?? $d['image'] ?? self::findCover($d),
            'episode_count' => isset($d['total_episodes']) ? (int)$d['total_episodes'] : self::findCountAnywhere($d),
            'genre'         => $d['genre'] ?? null,
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $d = $this->detail($seriesId);
        $count = $d['episode_count'] ?? 0;
        $eps = [];
        for ($i = 1; $i <= $count; $i++) {
            $eps[] = ['episode'=>$i, 'locked'=>false, 'sources'=>[], 'subtitles'=>[], 'lazy'=>true];
        }
        return ['series_id' => $seriesId, 'episodes' => $eps, 'lazy' => true];
    }

    public function playEpisode(string $seriesId, int $episode): array
    {
        $resp = $this->api->getJson($this->basePath() . '/play/' . rawurlencode($seriesId) . '/' . $episode);
        $d = $resp['data'] ?? $resp;
        $sources = [];
        foreach (($d['videos'] ?? []) as $v) {
            if (!is_array($v) || empty($v['url'])) continue;
            $sources[] = [
                'quality' => (string)($v['quality'] ?? 'auto'),
                'codec'   => 'h264',
                'url'     => (string)$v['url'],
            ];
        }
        return ['episode'=>$episode, 'locked'=>false, 'sources'=>$sources, 'subtitles'=>[]];
    }
}
