<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * iDrama: /drama/{id} returns full detail INCLUDING episode_list with play_info_list (HLS qualities).
 *   episode_list[].play_info_list[] = [{bitrate, codec, definition, format, play_url, ...}]
 *   episode_list[].subtitle_url_list[]
 */
final class IdramaAdapter extends BaseAdapter
{
    private array $cache = [];

    private function fetch(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        return $this->cache[$seriesId] = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId));
    }

    public function detail(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        return [
            'title'         => (string)($d['short_play_name'] ?? $d['title'] ?? self::findTitle($d) ?? ''),
            'description'   => $d['short_play_describ'] ?? $d['description'] ?? self::findDescription($d),
            'cover'         => $d['cover_url'] ?? $d['compress_cover_url'] ?? null,
            'episode_count' => isset($d['current_count']) ? (int)$d['current_count'] : (isset($d['total_count']) ? (int)$d['total_count'] : null),
            'genre'         => self::flattenGenre($d['content_tag'] ?? null),
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        $list = $d['episode_list'] ?? [];
        $eps = [];
        foreach ($list as $ep) {
            if (!is_array($ep)) continue;
            $sources = [];
            foreach (($ep['play_info_list'] ?? []) as $p) {
                if (!is_array($p) || empty($p['play_url'])) continue;
                $sources[] = [
                    'quality' => (string)($p['definition'] ?? 'auto'),
                    'codec'   => (string)($p['codec'] ?? 'h264'),
                    'url'     => (string)$p['play_url'],
                ];
            }
            $subs = [];
            foreach (($ep['subtitle_url_list'] ?? $ep['subtitles'] ?? []) as $s) {
                if (!is_array($s)) continue;
                $url = $s['url'] ?? $s['subtitle_url'] ?? null;
                if (!$url) continue;
                $isVtt = str_ends_with(strtolower(parse_url((string)$url, PHP_URL_PATH) ?? ''), '.vtt');
                $subs[] = [
                    'lang'  => (string)($s['language'] ?? $s['lang'] ?? ''),
                    'label' => (string)($s['language_name'] ?? $s['language'] ?? ''),
                    'vtt'   => $isVtt ? (string)$url : null,
                    'srt'   => $isVtt ? null : (string)$url,
                ];
            }
            $eps[] = [
                'episode'  => (int)($ep['episode_order'] ?? 0),
                'id'       => (string)($ep['episode_id'] ?? ''),
                'locked'   => !empty($ep['need_pay']) || (($ep['free_type'] ?? 0) > 0 && empty($sources)),
                'cover'    => $ep['episode_cover'] ?? null,
                'sources'  => $sources,
                'subtitles'=> $subs,
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps];
    }
}
