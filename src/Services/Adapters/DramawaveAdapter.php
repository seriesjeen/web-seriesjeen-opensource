<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * DramaWave: /drama/{id} returns the full catalog AND episode list with sources embedded.
 *   data.{cover, episode_count, items:[{1080p_mp4, 720p_mp4, 540p_mp4, m3u8_path, subtitle_list, serial_number, ...}]}
 */
final class DramawaveAdapter extends BaseAdapter
{
    private array $cache = [];

    private function fetch(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        $resp = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId));
        return $this->cache[$seriesId] = ($resp['data'] ?? $resp);
    }

    public function detail(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        return [
            'title'         => (string)($d['title'] ?? $d['name'] ?? self::findTitle($d) ?? ''),
            'description'   => $d['description'] ?? $d['desc'] ?? self::findDescription($d),
            'cover'         => $d['cover'] ?? self::findCover($d),
            'episode_count' => isset($d['episode_count']) ? (int)$d['episode_count'] : null,
            'genre'         => $d['genre'] ?? null,
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        $items = $d['items'] ?? [];
        $eps = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $sources = [];
            if (!empty($item['m3u8_path']))   $sources[] = ['quality'=>'auto','codec'=>'h264','url'=>(string)$item['m3u8_path']];
            if (!empty($item['540p_mp4']))    $sources[] = ['quality'=>'540','codec'=>'h264','url'=>(string)$item['540p_mp4']];
            if (!empty($item['720p_mp4']))    $sources[] = ['quality'=>'720','codec'=>'h264','url'=>(string)$item['720p_mp4']];
            if (!empty($item['1080p_mp4']))   $sources[] = ['quality'=>'1080','codec'=>'h264','url'=>(string)$item['1080p_mp4']];

            $subs = [];
            foreach (($item['subtitle_list'] ?? []) as $s) {
                if (!is_array($s)) continue;
                $subs[] = [
                    'lang'  => (string)($s['language'] ?? ''),
                    'label' => (string)($s['display_name'] ?? $s['language'] ?? ''),
                    'vtt'   => $s['vtt'] ?? null,
                    'srt'   => $s['subtitle'] ?? null,
                ];
            }

            $eps[] = [
                'episode'  => (int)($item['serial_number'] ?? 0),
                'id'       => (string)($item['id'] ?? ''),
                'locked'   => ($item['video_type'] ?? 'free') !== 'free' && empty($sources),
                'cover'    => $item['cover'] ?? null,
                'duration' => isset($item['duration']) ? (int)$item['duration'] : null,
                'sources'  => $sources,
                'subtitles'=> $subs,
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps];
    }
}
