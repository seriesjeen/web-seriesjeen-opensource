<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * Vigloo: /drama/{id} → data.payload.{episodeCount, seasons[], …}.
 *   Episodes live under /drama/{id}/season/{season_id}/episodes
 *   Stream: /getstream?videoId=
 */
final class ViglooAdapter extends BaseAdapter
{
    private array $cache = [];

    private function fetch(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        $resp = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId));
        return $this->cache[$seriesId] = ($resp['data']['payload'] ?? $resp['data'] ?? $resp);
    }

    public function detail(string $seriesId): array
    {
        $p = $this->fetch($seriesId);
        return [
            'title'         => (string)($p['title'] ?? ''),
            'description'   => $p['description'] ?? $p['synopsis'] ?? $p['logLine'] ?? null,
            'cover'         => $p['thumbnail'] ?? $p['bannerImage'] ?? $p['titleImage'] ?? null,
            'episode_count' => isset($p['episodeCount']) ? (int)$p['episodeCount'] : null,
            'genre'         => self::flattenGenre($p['genres'] ?? $p['genre'] ?? null),
            'extras'        => $p,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $p = $this->fetch($seriesId);
        $seasons = $p['seasons'] ?? [];
        $eps = [];
        $offset = 0;
        foreach ($seasons as $season) {
            if (!is_array($season)) continue;
            $sid = $season['id'] ?? null;
            if ($sid === null) continue;
            try {
                $r = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId)
                                        . '/season/' . rawurlencode((string)$sid) . '/episodes');
                // Vigloo response: data.{status, payloads:[...], seasonInfo}
                $list = $r['data']['payloads'] ?? $r['data']['payload'] ?? $r['data'] ?? [];
                if (!is_array($list) || !array_is_list($list)) continue;

                foreach ($list as $i => $ep) {
                    if (!is_array($ep)) continue;
                    $n = (int)($ep['episodeNumber'] ?? $ep['number'] ?? $ep['episode'] ?? ($i + 1));
                    $eps[] = [
                        'episode'  => $offset + $n,
                        'id'       => (string)($ep['id'] ?? $ep['videoId'] ?? ''),
                        'locked'   => !($ep['isFree'] ?? true) && !($ep['isUnlocked'] ?? false),
                        'cover'    => $ep['thumbnail'] ?? null,
                        'duration' => isset($ep['durationInSeconds']) ? (int)$ep['durationInSeconds'] : null,
                        'sources'  => [],
                        'subtitles'=> [],
                        'lazy'     => true,
                    ];
                }
                $offset += (int)($season['episodeCount'] ?? 0);
            } catch (\Throwable) { /* skip season */ }
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps, 'lazy' => true];
    }

    public function playEpisode(string $seriesId, int $episode): array
    {
        // Resolve seasonId + videoId via cached episodes() lookup
        $p = $this->fetch($seriesId);
        $seasons = $p['seasons'] ?? [];
        $offset = 0;
        $found = ['seasonId' => null, 'videoId' => null, 'ep' => null];
        foreach ($seasons as $season) {
            $sid = $season['id'] ?? null; if (!$sid) continue;
            $count = (int)($season['episodeCount'] ?? 0);
            if ($episode <= $offset + $count) {
                $ep = $episode - $offset;
                $r = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId)
                                        . '/season/' . rawurlencode((string)$sid) . '/episodes');
                $list = $r['data']['payloads'] ?? $r['data']['payload'] ?? $r['data'] ?? [];
                $row = $list[$ep - 1] ?? null;
                if (is_array($row) && !empty($row['id'])) {
                    $found = ['seasonId' => $sid, 'videoId' => $row['id'], 'ep' => $ep];
                }
                break;
            }
            $offset += $count;
        }
        if (!$found['videoId']) return ['episode'=>$episode,'locked'=>false,'sources'=>[],'subtitles'=>[]];

        // /getstream needs videoId + seasonId + ep — also returns signed CloudFront cookies
        // (browser can't read those cross-origin, so the resulting m3u8 may not play directly —
        //  best-effort: hand the URL to the player and accept that some platforms need server proxy)
        $resp = $this->api->getJson($this->basePath() . '/getstream', [
            'videoId'  => $found['videoId'],
            'seasonId' => $found['seasonId'],
            'ep'       => $found['ep'],
        ]);
        $url = $resp['url'] ?? $resp['data']['url'] ?? null;
        // CloudFront-signed delivery → forward signed cookies through the proxy
        $cookies = $resp['cookies'] ?? [];
        if (!is_array($cookies)) $cookies = [];

        return [
            'episode' => $episode,
            'id'      => (string)$found['videoId'],
            'locked'  => false,
            'sources' => $url ? [[
                'quality'=>'auto', 'codec'=>'h264', 'url'=>(string)$url,
                'cookies' => $cookies,  // consumed by StreamProxy::wrapPayload
            ]] : [],
            'subtitles' => [],
        ];
    }
}
