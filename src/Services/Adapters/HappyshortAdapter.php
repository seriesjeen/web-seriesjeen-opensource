<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * Happyshort uses query-style ?id=
 *   /detail?id=...     → {episode_stats:{total,locked,free}, video:{...}, lang}
 *   /episodes?id=...   → {episodes:[{id,lock,name,order,stream_url}], total, video_id}
 *   /play              → on-demand source resolution
 */
final class HappyshortAdapter extends BaseAdapter
{
    public function detail(string $seriesId): array
    {
        $resp = $this->api->getJson($this->basePath() . '/detail', ['id' => $seriesId]);
        $v = $resp['video'] ?? [];
        $count = (int)($resp['episode_stats']['total'] ?? $v['episode_num'] ?? 0);
        return [
            'title'         => (string)($v['name'] ?? $v['title'] ?? ''),
            'description'   => $v['introduce'] ?? null,
            'cover'         => $v['big_cover'] ?? $v['cover'] ?? null,
            'episode_count' => $count ?: null,
            'genre'         => $v['tag'] ?? $v['category'] ?? null,
            'extras'        => $resp,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $resp = $this->api->getJson($this->basePath() . '/episodes', ['id' => $seriesId]);
        $list = $resp['episodes'] ?? [];
        $eps = [];
        foreach ($list as $ep) {
            if (!is_array($ep)) continue;
            $eps[] = [
                'episode'  => (int)($ep['order'] ?? 0),
                'id'       => (string)($ep['id'] ?? ''),
                'locked'   => !empty($ep['lock']) && (int)$ep['lock'] === 1,
                'sources'  => [],
                'subtitles'=> [],
                'lazy'     => true,
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps, 'lazy' => true];
    }

    public function playEpisode(string $seriesId, int $episode): array
    {
        // /play?id=&ep= → {cdn_url: "https://.../{id}/{ep}.mp4?signed..."}
        try {
            $resp = $this->api->getJson($this->basePath() . '/play', ['id' => $seriesId, 'ep' => $episode]);
        } catch (\Throwable) {
            return ['episode'=>$episode,'locked'=>false,'sources'=>[],'subtitles'=>[]];
        }
        $d = $resp['data'] ?? $resp;
        $url = $d['cdn_url'] ?? $d['url'] ?? $d['m3u8_url'] ?? $d['stream_url'] ?? null;
        return [
            'episode' => $episode,
            'locked'  => !empty($d['locked']),
            'sources' => $url ? [['quality'=>'auto', 'codec'=>'h264', 'url'=>(string)$url]] : [],
            'subtitles' => [],
        ];
    }
}
