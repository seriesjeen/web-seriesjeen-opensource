<?php
declare(strict_types=1);

namespace App\Services\Adapters;

final class ShortmaxAdapter extends BaseAdapter
{
    public function episodes(string $seriesId): array
    {
        $resp = $this->api->getJson($this->basePath() . '/alleps/' . rawurlencode($seriesId));
        $data = $resp['data'] ?? $resp;
        $eps = $data['episodes'] ?? [];
        $out = [];
        foreach ($eps as $ep) {
            if (!is_array($ep)) continue;
            $sources = [];
            $video = $ep['video'] ?? [];
            foreach (['video_480' => '480', 'video_720' => '720', 'video_1080' => '1080'] as $key => $q) {
                if (!empty($video[$key])) {
                    $sources[] = ['quality' => $q, 'codec' => 'h264', 'url' => (string)$video[$key]];
                }
            }
            $out[] = [
                'episode'   => (int)($ep['episode'] ?? 0),
                'id'        => (string)($ep['id'] ?? ''),
                'locked'    => (bool)($ep['locked'] ?? false),
                'duration'  => isset($ep['duration']) ? (int)$ep['duration'] : null,
                'cover'     => $ep['cover'] ?? null,
                'sources'   => $sources,
                'subtitles' => [],
            ];
        }
        return ['series_id' => $seriesId, 'episodes' => $out];
    }
}
