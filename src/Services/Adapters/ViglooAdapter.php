<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * Vigloo (id 166) — provider captain (CloudFront-protected). Flow D (signed cookies):
 *   /drama/{id}                                  → {drama:{title, description, thumbnailExpanded,
 *                                                          episodeCount, seasons:[{id, episodeCount, appFreeEpCount}]}}
 *   /drama/{id}/season/{season_id}/episodes      → {payloads:[{id, episodeNumber, duration, price}]}
 *   /getstream?seasonId=&ep=                     → {payload:{url, cookies:{CloudFront-Policy,-Signature,-Key-Pair-Id}}}
 *
 * The m3u8 needs the CloudFront cookies set; we forward them to StreamProxy via the `cookies` opt.
 */
final class ViglooAdapter extends BaseAdapter
{
    private array $cache = [];
    private array $seasonEpCache = [];

    private function fetch(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        $resp = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId));
        return $this->cache[$seriesId] = ($resp['drama'] ?? $resp['data']['payload'] ?? $resp['data'] ?? $resp);
    }

    public function detail(string $seriesId): array
    {
        $p = $this->fetch($seriesId);
        return [
            'title'         => (string)($p['title'] ?? ''),
            'description'   => $p['description'] ?? $p['synopsis'] ?? $p['logLine'] ?? null,
            'cover'         => $p['thumbnailExpanded'] ?? $p['thumbnail'] ?? $p['bannerImage'] ?? $p['titleImage'] ?? null,
            'episode_count' => isset($p['episodeCount']) ? (int)$p['episodeCount'] : null,
            'genre'         => self::flattenGenre($p['genres'] ?? $p['genre'] ?? null),
            'extras'        => $p,
        ];
    }

    /** @return array<int,mixed> payloads of a season (cached) */
    private function seasonEpisodes(string $seriesId, string $seasonId): array
    {
        $k = $seriesId . '|' . $seasonId;
        if (isset($this->seasonEpCache[$k])) return $this->seasonEpCache[$k];
        try {
            $r = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId)
                                    . '/season/' . rawurlencode($seasonId) . '/episodes');
            $list = $r['payloads'] ?? $r['data']['payloads'] ?? $r['data']['payload'] ?? $r['data'] ?? [];
            return $this->seasonEpCache[$k] = (is_array($list) && array_is_list($list) ? $list : []);
        } catch (\Throwable) {
            return $this->seasonEpCache[$k] = [];
        }
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
            $list = $this->seasonEpisodes($seriesId, (string)$sid);
            foreach ($list as $i => $ep) {
                if (!is_array($ep)) continue;
                $n = (int)($ep['episodeNumber'] ?? $ep['number'] ?? ($i + 1));
                $eps[] = [
                    'episode'  => $offset + $n,
                    'id'       => (string)($ep['id'] ?? $ep['videoId'] ?? ''),
                    'locked'   => isset($ep['price']) ? ((int)$ep['price'] > 0) : false,
                    'cover'    => $ep['thumbnail'] ?? null,
                    'duration' => isset($ep['duration']) ? (int)$ep['duration'] : (isset($ep['durationInSeconds']) ? (int)$ep['durationInSeconds'] : null),
                    'sources'  => [],
                    'subtitles'=> [],
                    'lazy'     => true,
                    'season_id'=> (string)$sid,
                    'season_ep'=> $n,
                ];
            }
            $offset += (int)($season['episodeCount'] ?? count($list));
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps, 'lazy' => true];
    }

    public function playEpisode(string $seriesId, int $episode): array
    {
        // Map the global episode number → (seasonId, season-local ep).
        $p = $this->fetch($seriesId);
        $seasons = $p['seasons'] ?? [];
        $offset = 0;
        $seasonId = null; $localEp = null;
        foreach ($seasons as $season) {
            if (!is_array($season)) continue;
            $sid = $season['id'] ?? null; if ($sid === null) continue;
            $count = (int)($season['episodeCount'] ?? 0);
            if ($count <= 0) $count = count($this->seasonEpisodes($seriesId, (string)$sid));
            if ($episode <= $offset + $count) {
                $seasonId = (string)$sid;
                $localEp = $episode - $offset;
                break;
            }
            $offset += $count;
        }
        if ($seasonId === null) return ['episode'=>$episode,'locked'=>false,'sources'=>[],'subtitles'=>[]];

        try {
            $resp = $this->api->getJson($this->basePath() . '/getstream', [
                'seasonId' => $seasonId,
                'ep'       => $localEp,
            ]);
        } catch (\Throwable) {
            return ['episode'=>$episode,'locked'=>false,'sources'=>[],'subtitles'=>[]];
        }
        $payload = $resp['payload'] ?? $resp['data']['payload'] ?? $resp['data'] ?? $resp;
        $url = $payload['url'] ?? null;
        $cookies = $payload['cookies'] ?? [];
        if (!is_array($cookies)) $cookies = [];

        return [
            'episode' => $episode,
            'locked'  => false,
            'sources' => $url ? [[
                'quality' => 'auto', 'codec' => 'h264', 'url' => (string)$url,
                'cookies' => $cookies,  // consumed by StreamProxy::wrapPayload
            ]] : [],
            'subtitles' => [],
        ];
    }
}
