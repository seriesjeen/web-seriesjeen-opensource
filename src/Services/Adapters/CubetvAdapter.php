<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * CubeTV: /detail/{videoid} → data.{cover, summary, latestEpisodeNumber, …}.
 *   /episodes/{videoid} → {rows[], total} with HLS URLs in each row.
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
        return [
            'title'         => (string)($d['title'] ?? $d['name'] ?? self::findTitle($d) ?? ''),
            'description'   => $d['summary'] ?? $d['description'] ?? null,
            'cover'         => $d['cover'] ?? null,
            'episode_count' => isset($d['latestEpisodeNumber']) ? (int)$d['latestEpisodeNumber'] : null,
            'genre'         => $d['genre'] ?? $d['category'] ?? null,
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        if (!isset($this->episodesCache[$seriesId])) {
            $this->episodesCache[$seriesId] = $this->api->getJson($this->basePath() . '/episodes/' . rawurlencode($seriesId));
        }
        $rows = $this->episodesCache[$seriesId]['rows'] ?? [];
        $eps = [];
        foreach ($rows as $i => $ep) {
            if (!is_array($ep)) continue;
            $sources = [];
            // Cubetv: videoUrls[] = [{codec:"H.264", quality:"sd", url}]
            foreach (($ep['videoUrls'] ?? []) as $v) {
                if (!is_array($v) || empty($v['url'])) continue;
                $sources[] = [
                    'quality' => (string)($v['quality'] ?? 'auto'),
                    'codec'   => strtolower(str_replace('.', '', (string)($v['codec'] ?? 'h264'))),
                    'url'     => (string)$v['url'],
                ];
            }
            $subs = [];
            foreach (($ep['subtitles'] ?? []) as $s) {
                if (!is_array($s) || empty($s['url'])) continue;
                $url = (string)$s['url'];
                $isVtt = str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?? ''), '.vtt');
                $subs[] = [
                    'lang'  => (string)($s['lang'] ?? $s['language'] ?? ''),
                    'label' => (string)($s['lang'] ?? ''),
                    'vtt'   => $isVtt ? $url : null,
                    'srt'   => $isVtt ? null : $url,
                ];
            }
            $eps[] = [
                'episode'  => (int)($ep['episodeNumber'] ?? $ep['order'] ?? $i + 1),
                'id'       => (string)($ep['episodeid'] ?? $ep['id'] ?? ''),
                'locked'   => isset($ep['lockStatus']) && (int)$ep['lockStatus'] === 1 && empty($sources),
                'duration' => isset($ep['duration']) ? (int)$ep['duration'] : null,
                'cover'    => $ep['cover'] ?? null,
                'sources'  => $sources,
                'subtitles'=> $subs,
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps];
    }
}
