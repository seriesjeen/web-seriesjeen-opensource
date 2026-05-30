<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * DramaWave (id 26) — provider captain (mydramawave backend). Flow C:
 *   /drama/{id}              → data.info.{name, desc, cover, episode_count, content_tags,
 *                                         episode_list:[{index, video_url, m3u8_url,
 *                                         external_audio_h264_m3u8, external_audio_h265_m3u8,
 *                                         subtitle_list[], unlock, duration, video_type}]}
 *   /drama/{id}/play/{ep}    → data.{<same episode shape>}  (per-episode fallback)
 */
final class DramawaveAdapter extends BaseAdapter
{
    private array $cache = [];

    private function fetchInfo(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        $resp = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId));
        $info = $resp['data']['info'] ?? $resp['data'] ?? $resp;
        return $this->cache[$seriesId] = (is_array($info) ? $info : []);
    }

    public function detail(string $seriesId): array
    {
        $i = $this->fetchInfo($seriesId);
        $tags = array_filter(array_merge((array)($i['tag'] ?? []), (array)($i['content_tags'] ?? [])));
        return [
            'title'         => (string)($i['name'] ?? $i['title'] ?? self::findTitle($i) ?? ''),
            'description'   => $i['desc'] ?? $i['description'] ?? self::findDescription($i),
            'cover'         => $i['cover'] ?? self::findCover($i),
            'episode_count' => isset($i['episode_count']) ? (int)$i['episode_count'] : self::findCountAnywhere($i),
            'genre'         => $tags ? implode(', ', $tags) : null,
            'extras'        => $i,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $i = $this->fetchInfo($seriesId);
        $eps = [];
        foreach (($i['episode_list'] ?? []) as $k => $ep) {
            if (!is_array($ep)) continue;
            $sources = self::parseEpisodeSources($ep);
            $eps[] = [
                'episode'  => (int)($ep['index'] ?? ($k + 1)),
                'id'       => (string)($ep['id'] ?? ''),
                'locked'   => !($ep['unlock'] ?? true) && empty($sources),
                'duration' => isset($ep['duration']) ? (int)$ep['duration'] : null,
                'cover'    => $ep['cover'] ?? null,
                'sources'  => $sources,
                'subtitles'=> self::parseSubtitles($ep['subtitle_list'] ?? []),
                'lazy'     => empty($sources),
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps, 'lazy' => true];
    }

    public function playEpisode(string $seriesId, int $episode): array
    {
        try {
            $resp = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId) . '/play/' . $episode);
        } catch (\Throwable) {
            return ['episode' => $episode, 'locked' => false, 'sources' => [], 'subtitles' => []];
        }
        $d = $resp['data'] ?? $resp;
        if (!is_array($d)) $d = [];
        return [
            'episode'   => (int)($d['index'] ?? $episode),
            'id'        => (string)($d['id'] ?? ''),
            'locked'    => false,
            'cover'     => $d['cover'] ?? null,
            'sources'   => self::parseEpisodeSources($d),
            'subtitles' => self::parseSubtitles($d['subtitle_list'] ?? []),
        ];
    }

    /** @param array<string,mixed> $ep */
    public static function parseEpisodeSources(array $ep): array
    {
        $sources = [];
        if (!empty($ep['external_audio_h264_m3u8'])) $sources[] = ['quality'=>'auto','codec'=>'h264','url'=>(string)$ep['external_audio_h264_m3u8']];
        if (!empty($ep['external_audio_h265_m3u8'])) $sources[] = ['quality'=>'auto','codec'=>'h265','url'=>(string)$ep['external_audio_h265_m3u8']];
        if (!empty($ep['m3u8_url']))  $sources[] = ['quality'=>'auto','codec'=>'h264','url'=>(string)$ep['m3u8_url']];
        if (empty($sources) && !empty($ep['video_url'])) $sources[] = ['quality'=>'auto','codec'=>'h264','url'=>(string)$ep['video_url']];
        return $sources;
    }

    /** @param array<int,mixed> $list */
    public static function parseSubtitles(array $list): array
    {
        $out = [];
        foreach ($list as $s) {
            if (!is_array($s)) continue;
            $out[] = [
                'lang'  => (string)($s['language'] ?? ''),
                'label' => (string)($s['display_name'] ?? $s['language'] ?? ''),
                'vtt'   => $s['vtt'] ?? null,
                'srt'   => $s['subtitle'] ?? null,
            ];
        }
        return $out;
    }
}
