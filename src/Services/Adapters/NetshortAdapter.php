<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * NetShort (id 25) — provider captain. Flow C:
 *   /drama/{id}            → data.{title, cover, description, labels, totalEpisodes,
 *                                  episodes:[{episodeNo, episodeId, cover, isLocked}]}  (no URLs → lazy)
 *   /watch/{id}/{ep}       → data.{episodeId, videos:[{quality,url}], subtitles:[{language,format,url}]}
 */
final class NetshortAdapter extends BaseAdapter
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
            'title'         => (string)($d['title'] ?? $d['shortPlayName'] ?? self::findTitle($d) ?? ''),
            'description'   => $d['description'] ?? null,
            'cover'         => $d['cover'] ?? $d['shortPlayCover'] ?? self::findCover($d),
            'episode_count' => isset($d['totalEpisodes']) ? (int)$d['totalEpisodes'] : (isset($d['totalEpisode']) ? (int)$d['totalEpisode'] : self::findCountAnywhere($d)),
            'genre'         => self::flattenGenre($d['labels'] ?? $d['shortPlayLabels'] ?? null),
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        $list = $d['episodes'] ?? $d['shortPlayEpisodeList'] ?? [];
        $eps = [];
        foreach ($list as $i => $ep) {
            if (!is_array($ep)) continue;
            $eps[] = [
                'episode'  => (int)($ep['episodeNo'] ?? ($i + 1)),
                'id'       => (string)($ep['episodeId'] ?? ''),
                'locked'   => (bool)($ep['isLocked'] ?? $ep['isLock'] ?? false),
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
        try {
            $resp = $this->api->getJson($this->basePath() . '/watch/' . rawurlencode($seriesId) . '/' . $episode);
        } catch (\Throwable) {
            return ['episode' => $episode, 'locked' => false, 'sources' => [], 'subtitles' => []];
        }
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
        // single-url fallback shapes
        if (empty($sources)) {
            $url = $d['videoUrl'] ?? $d['m3u8_url'] ?? null;
            if ($url) $sources[] = ['quality'=>'auto','codec'=>'h264','url'=>(string)$url];
        }

        $subs = [];
        foreach (($d['subtitles'] ?? []) as $s) {
            if (!is_array($s) || empty($s['url'])) continue;
            $u = (string)$s['url'];
            $fmt = strtolower((string)($s['format'] ?? ''));
            $isVtt = $fmt === 'webvtt' || $fmt === 'vtt' || str_contains(strtolower($u), '.vtt') || str_contains($u, 'text_vtt');
            $subs[] = [
                'lang'  => (string)($s['language'] ?? $s['lang'] ?? ''),
                'label' => (string)($s['language'] ?? $s['lang'] ?? ''),
                'vtt'   => $isVtt ? $u : null,
                'srt'   => $isVtt ? null : $u,
            ];
        }

        return [
            'episode'   => $episode,
            'locked'    => false,
            'sources'   => $sources,
            'subtitles' => $subs,
        ];
    }
}
