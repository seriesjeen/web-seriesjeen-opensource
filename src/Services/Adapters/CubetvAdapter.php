<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * CubeTV (id 260) — provider captain. Flow B:
 *   /detail/{videoid}              → data.{videoName, cover, summary, totalEpisodeNum, latestEpisodeNumber}
 *   /episodes/{videoid}            → data:[{episodeid, episodeNumber, episodeTitle, lockStatus, duration}]  (lazy)
 *   /stream/{videoid}/{episodeid}  → data.{linkInfo:[{linkUrl, codeType, codeRate}],
 *                                          videoCaption:[{language_code, url}]}
 */
final class CubetvAdapter extends BaseAdapter
{
    private array $detailCache = [];
    private array $episodesCache = [];

    public function detail(string $seriesId): array
    {
        if (!isset($this->detailCache[$seriesId])) {
            $resp = $this->api->getJson($this->basePath() . '/detail/' . rawurlencode($seriesId));
            $this->detailCache[$seriesId] = $resp['data'] ?? $resp;
        }
        $d = $this->detailCache[$seriesId];
        $count = $d['totalEpisodeNum'] ?? $d['latestEpisodeNumber'] ?? null;
        return [
            'title'         => (string)($d['videoName'] ?? $d['title'] ?? $d['name'] ?? self::findTitle($d) ?? ''),
            'description'   => $d['summary'] ?? $d['description'] ?? null,
            'cover'         => $d['cover'] ?? null,
            'episode_count' => $count !== null ? (int)$count : null,
            'genre'         => self::flattenGenre($d['tagInfo'] ?? $d['genre'] ?? $d['category'] ?? null),
            'extras'        => $d,
        ];
    }

    private function fetchEpisodes(string $seriesId): array
    {
        if (!isset($this->episodesCache[$seriesId])) {
            $resp = $this->api->getJson($this->basePath() . '/episodes/' . rawurlencode($seriesId));
            $list = $resp['data'] ?? $resp['rows'] ?? $resp;
            $this->episodesCache[$seriesId] = is_array($list) && array_is_list($list) ? $list : [];
        }
        return $this->episodesCache[$seriesId];
    }

    public function episodes(string $seriesId): array
    {
        $rows = $this->fetchEpisodes($seriesId);
        $eps = [];
        foreach ($rows as $i => $ep) {
            if (!is_array($ep)) continue;
            $eps[] = [
                'episode'  => (int)($ep['episodeNumber'] ?? $ep['order'] ?? ($i + 1)),
                'id'       => (string)($ep['episodeid'] ?? $ep['id'] ?? ''),
                'locked'   => isset($ep['lockStatus']) && (int)$ep['lockStatus'] === 1,
                'duration' => isset($ep['duration']) ? (int)$ep['duration'] : null,
                'cover'    => $ep['cover'] ?? null,
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
        $episodeId = null;
        foreach ($this->fetchEpisodes($seriesId) as $i => $ep) {
            if (!is_array($ep)) continue;
            if ((int)($ep['episodeNumber'] ?? ($i + 1)) === $episode) { $episodeId = (string)($ep['episodeid'] ?? $ep['id'] ?? ''); break; }
        }
        if (!$episodeId) return ['episode'=>$episode,'locked'=>false,'sources'=>[],'subtitles'=>[]];

        try {
            $resp = $this->api->getJson($this->basePath() . '/stream/' . rawurlencode($seriesId) . '/' . rawurlencode($episodeId));
        } catch (\Throwable) {
            return ['episode'=>$episode,'locked'=>false,'sources'=>[],'subtitles'=>[]];
        }
        $d = $resp['data'] ?? $resp;

        $sources = [];
        foreach (($d['linkInfo'] ?? $d['videoUrls'] ?? []) as $v) {
            if (!is_array($v)) continue;
            $url = $v['linkUrl'] ?? $v['url'] ?? null;
            if (!$url) continue;
            $sources[] = [
                'quality' => (string)($v['codeRate'] ?? $v['quality'] ?? 'auto'),
                'codec'   => strtolower(str_replace('.', '', (string)($v['codeType'] ?? $v['codec'] ?? 'h264'))),
                'url'     => (string)$url,
            ];
        }

        $subs = [];
        foreach (($d['videoCaption'] ?? $d['subtitles'] ?? []) as $s) {
            if (!is_array($s) || empty($s['url'])) continue;
            $url = (string)$s['url'];
            $isVtt = str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?? ''), '.vtt');
            $subs[] = [
                'lang'  => (string)($s['language_code'] ?? $s['lang'] ?? $s['language'] ?? ''),
                'label' => (string)($s['language_code'] ?? $s['lang'] ?? ''),
                'vtt'   => $isVtt ? $url : null,
                'srt'   => $isVtt ? null : $url,
            ];
        }

        return ['episode'=>$episode, 'locked'=>false, 'sources'=>$sources, 'subtitles'=>$subs];
    }
}
