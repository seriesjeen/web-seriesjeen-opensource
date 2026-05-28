<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * Netshort: /drama/{series_id} returns data.totalEpisode + data.shortPlayEpisodeList[]
 * Each episode entry has {episodeNo, episodeId, isLock, playVoucher (url), sdkVid}
 * The playVoucher is the playable URL directly.
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
            'title'         => (string)($d['shortPlayName'] ?? ''),
            'description'   => null,
            'cover'         => $d['shortPlayCover'] ?? null,
            'episode_count' => isset($d['totalEpisode']) ? (int)$d['totalEpisode'] : null,
            'genre'         => self::flattenGenre($d['shortPlayLabels'] ?? null),
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        $eps = [];
        foreach (($d['shortPlayEpisodeList'] ?? []) as $ep) {
            if (!is_array($ep)) continue;
            $eps[] = [
                'episode'  => (int)($ep['episodeNo'] ?? 0),
                'id'       => (string)($ep['episodeId'] ?? ''),
                'locked'   => (bool)($ep['isLock'] ?? false),
                'sources'  => !empty($ep['playVoucher'])
                                ? [['quality'=>'auto', 'codec'=>'h264', 'url'=>(string)$ep['playVoucher']]]
                                : [],
                'subtitles'=> [],
                'lazy'     => empty($ep['playVoucher']),
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps, 'lazy' => true];
    }

    public function playEpisode(string $seriesId, int $episode): array
    {
        // /drama/{id} only fills playVoucher for EP 1 — use /watch/{id}/{ep} for the rest
        try {
            $resp = $this->api->getJson($this->basePath() . '/watch/' . rawurlencode($seriesId) . '/' . $episode);
        } catch (\Throwable) {
            return ['episode'=>$episode,'locked'=>false,'sources'=>[],'subtitles'=>[]];
        }
        $d = $resp['data'] ?? $resp;
        $url = $d['videoUrl'] ?? $d['playVoucher'] ?? $d['m3u8_url'] ?? null;

        $subs = [];
        foreach (($d['subtitles'] ?? []) as $s) {
            if (!is_array($s) || empty($s['url'])) continue;
            $u = (string)$s['url'];
            $isVtt = str_contains($u, '.vtt') || (str_contains($u, 'mime_type=text_vtt'));
            $subs[] = [
                'lang'  => (string)($s['lang'] ?? $s['language'] ?? ''),
                'label' => (string)($s['lang'] ?? ''),
                'vtt'   => $isVtt ? $u : null,
                'srt'   => $isVtt ? null : $u,
            ];
        }

        return [
            'episode'   => $episode,
            'locked'    => empty($d['status']),
            'sources'   => $url ? [['quality'=>'auto','codec'=>'h264','url'=>(string)$url]] : [],
            'subtitles' => $subs,
        ];
    }
}
