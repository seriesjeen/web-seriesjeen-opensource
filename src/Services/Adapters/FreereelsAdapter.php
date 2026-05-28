<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * FreeReels: /drama/{id} returns rich detail INCLUDING the full episode_list with
 * embedded m3u8 sources + 24-language subtitles. /play/{ep} is a per-episode endpoint
 * used as fallback if the embedded data is empty/expired.
 */
final class FreereelsAdapter extends BaseAdapter
{
    private array $detailCache = [];

    private function fetchDetailRaw(string $seriesId): array
    {
        if (isset($this->detailCache[$seriesId])) return $this->detailCache[$seriesId];
        $resp = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId));
        $info = $resp['data']['data']['info'] ?? $resp['data']['data'] ?? $resp['data'] ?? $resp;
        return $this->detailCache[$seriesId] = is_array($info) ? $info : [];
    }

    public function detail(string $seriesId): array
    {
        $i = $this->fetchDetailRaw($seriesId);
        $tags = array_filter(array_merge((array)($i['tag'] ?? []), (array)($i['content_tags'] ?? [])));
        return [
            'title'         => (string)($i['name'] ?? ''),
            'description'   => $i['desc'] ?? null,
            'cover'         => $i['cover'] ?? null,
            'episode_count' => isset($i['episode_count']) ? (int)$i['episode_count'] : null,
            'genre'         => $tags ? implode(', ', $tags) : null,
            'extras'        => $i,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $i = $this->fetchDetailRaw($seriesId);
        $eps = [];
        foreach (($i['episode_list'] ?? []) as $ep) {
            if (!is_array($ep)) continue;
            $eps[] = [
                'episode'  => (int)($ep['index'] ?? 0),
                'id'       => (string)($ep['id'] ?? ''),
                'locked'   => !($ep['unlock'] ?? true),
                'duration' => isset($ep['duration']) ? (int)$ep['duration'] : null,
                'cover'    => $ep['cover'] ?? null,
                'sources'  => $this->parseEpisodeSources($ep),
                'subtitles'=> $this->parseSubtitles($ep['subtitle_list'] ?? []),
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps];
    }

    private function parseEpisodeSources(array $ep): array
    {
        $sources = [];
        if (!empty($ep['external_audio_h264_m3u8'])) $sources[] = ['quality'=>'auto','codec'=>'h264','url'=>(string)$ep['external_audio_h264_m3u8']];
        if (!empty($ep['external_audio_h265_m3u8'])) $sources[] = ['quality'=>'auto','codec'=>'h265','url'=>(string)$ep['external_audio_h265_m3u8']];
        if (empty($sources) && !empty($ep['m3u8_url'])) $sources[] = ['quality'=>'auto','codec'=>'h264','url'=>(string)$ep['m3u8_url']];
        return $sources;
    }

    private function parseSubtitles(array $list): array
    {
        $out = [];
        foreach ($list as $s) {
            if (!is_array($s)) continue;
            $out[] = [
                'lang' => (string)($s['language'] ?? ''),
                'label'=> (string)($s['display_name'] ?? $s['language'] ?? ''),
                'vtt'  => $s['vtt'] ?? null,
                'srt'  => $s['subtitle'] ?? null,
            ];
        }
        return $out;
    }

    /** Fallback per-episode fetch if embedded data is missing. */
    public function playEpisode(string $seriesId, int $episode): array
    {
        $resp = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId) . '/play/' . $episode);
        $d = $resp['data']['data'] ?? $resp['data'] ?? $resp;
        if (!is_array($d)) $d = [];
        return [
            'episode'   => (int)($d['episode_number'] ?? $episode),
            'id'        => (string)($d['episode_id'] ?? ''),
            'locked'    => false,
            'cover'     => $d['cover'] ?? null,
            'sources'   => $this->parseEpisodeSources($d),
            'subtitles' => $this->parseSubtitles($d['subtitle_list'] ?? []),
        ];
    }
}
