<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/** BiliTV: /short/{id} detail, /short/{id}/episode list, /stream/{id}/{ep}?quality=720 */
final class BilitvAdapter extends BaseAdapter
{
    public function detail(string $seriesId): array
    {
        $resp = $this->api->getJson($this->basePath() . '/short/' . rawurlencode($seriesId));
        $d = $resp['data'] ?? $resp;
        if (!is_array($d)) $d = [];

        // BiliTV detail rarely carries episode_count — derive from /episode endpoint length
        $count = self::findCountAnywhere($d);
        if ($count === null) {
            try {
                $epResp = $this->api->getJson($this->basePath() . '/short/' . rawurlencode($seriesId) . '/episode');
                $list = $epResp['list'] ?? $epResp['data']['list'] ?? $epResp['data'] ?? [];
                if (is_array($list) && array_is_list($list)) $count = count($list);
            } catch (\Throwable) { /* ignore */ }
        }

        return [
            'title'         => (string)($d['title'] ?? $d['name'] ?? self::findTitle($d) ?? ''),
            'description'   => $d['summary'] ?? $d['description'] ?? $d['desc'] ?? self::findDescription($d),
            'cover'         => $d['cover'] ?? $d['image'] ?? self::findCover($d),
            'episode_count' => $count,
            'genre'         => self::flattenGenre($d['genre'] ?? $d['tag'] ?? null),
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $resp = $this->api->getJson($this->basePath() . '/short/' . rawurlencode($seriesId) . '/episode');
        // Bilitv shape: {"list":[{episode:int, locked:bool}, ...]}
        $list = $resp['list'] ?? $resp['data']['list'] ?? $resp['data'] ?? $resp['episodes'] ?? [];
        if (!is_array($list) || !array_is_list($list)) $list = [];

        $eps = [];
        foreach ($list as $i => $ep) {
            if (!is_array($ep)) continue;
            $eps[] = [
                'episode' => (int)($ep['episode'] ?? $ep['number'] ?? ($i + 1)),
                'locked'  => (bool)($ep['locked'] ?? false),
                'duration'=> isset($ep['duration']) ? (int)$ep['duration'] : null,
                'cover'   => $ep['cover'] ?? null,
                'sources' => [],
                'subtitles' => [],
                'lazy'    => true,
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps, 'lazy' => true];
    }

    public function playEpisode(string $seriesId, int $episode): array
    {
        $sources = [];
        foreach (['1080', '720', '540', '360'] as $q) {
            try {
                $resp = $this->api->getJson($this->basePath() . '/stream/' . rawurlencode($seriesId) . '/' . $episode, ['quality' => $q]);
                $d = $resp['data'] ?? $resp;
                $url = $d['m3u8_url'] ?? $d['url'] ?? $d['stream'] ?? null;
                if ($url) $sources[] = ['quality' => $q, 'codec' => 'h264', 'url' => (string)$url];
            } catch (\Throwable) { /* skip */ }
        }

        $subs = [];
        try {
            $sub = $this->api->getJson($this->basePath() . '/subtitle/' . rawurlencode($seriesId) . '/' . $episode, ['format' => 'vtt']);
            foreach (($sub['data'] ?? $sub['subtitles'] ?? []) as $s) {
                if (!is_array($s)) continue;
                $subs[] = [
                    'lang' => (string)($s['language'] ?? $s['lang'] ?? ''),
                    'label'=> (string)($s['display_name'] ?? $s['label'] ?? $s['language'] ?? ''),
                    'vtt'  => $s['vtt'] ?? $s['url'] ?? null,
                ];
            }
        } catch (\Throwable) { /* no subs */ }

        return ['episode' => $episode, 'locked' => false, 'sources' => $sources, 'subtitles' => $subs];
    }
}
