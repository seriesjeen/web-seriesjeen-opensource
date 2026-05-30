<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * DramaNova (id 186) — provider captain. Flow D (fileId):
 *   /detail/{id}   → {title, cover, description, totalEpisodes,
 *                     episodes:[{id, number, fileId, free, cover, subtitles:[{lang,label,url}]}]}
 *   /video?id={fileId} → {videos:[{definition, codec, quality, main_url, backup_url}]}  (lazy per episode)
 */
final class DramanovaAdapter extends BaseAdapter
{
    private array $cache = [];

    private function fetch(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        return $this->cache[$seriesId] = $this->api->getJson($this->basePath() . '/detail/' . rawurlencode($seriesId));
    }

    public function detail(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        $list = $d['episodes'] ?? [];
        return [
            'title'         => (string)($d['title'] ?? self::findTitle($d) ?? ''),
            'description'   => $d['description'] ?? $d['synopsis'] ?? self::findDescription($d),
            'cover'         => $d['cover'] ?? self::findCover($d),
            'episode_count' => isset($d['totalEpisodes']) ? (int)$d['totalEpisodes'] : (is_array($list) ? count($list) : null),
            'genre'         => $d['genre'] ?? null,
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        $eps = [];
        foreach (($d['episodes'] ?? []) as $i => $ep) {
            if (!is_array($ep)) continue;
            $eps[] = [
                'episode'  => (int)($ep['number'] ?? ($i + 1)),
                'id'       => (string)($ep['fileId'] ?? $ep['id'] ?? ''),
                'locked'   => isset($ep['free']) ? !$ep['free'] : false,
                'cover'    => is_string($ep['cover'] ?? null) && str_starts_with((string)$ep['cover'], 'http') ? $ep['cover'] : null,
                'sources'  => [],
                'subtitles'=> $this->parseSubtitles($ep['subtitles'] ?? []),
                'lazy'     => true,
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps, 'lazy' => true];
    }

    public function playEpisode(string $seriesId, int $episode): array
    {
        $d = $this->fetch($seriesId);
        $fileId = null; $subs = [];
        foreach (($d['episodes'] ?? []) as $i => $ep) {
            if (!is_array($ep)) continue;
            if ((int)($ep['number'] ?? ($i + 1)) === $episode) {
                $fileId = (string)($ep['fileId'] ?? '');
                $subs = $this->parseSubtitles($ep['subtitles'] ?? []);
                break;
            }
        }
        if (!$fileId) return ['episode' => $episode, 'locked' => false, 'sources' => [], 'subtitles' => $subs];

        try {
            $resp = $this->api->getJson($this->basePath() . '/video', ['id' => $fileId]);
        } catch (\Throwable) {
            return ['episode' => $episode, 'locked' => false, 'sources' => [], 'subtitles' => $subs];
        }
        $sources = [];
        foreach (($resp['videos'] ?? []) as $v) {
            if (!is_array($v)) continue;
            $url = $v['main_url'] ?? $v['backup_url'] ?? $v['url'] ?? null;
            if (!$url) continue;
            $sources[] = [
                'quality' => (string)($v['definition'] ?? $v['quality'] ?? 'auto'),
                'codec'   => (string)($v['codec'] ?? 'h264'),
                'url'     => (string)$url,
            ];
        }
        return ['episode' => $episode, 'locked' => false, 'sources' => $sources, 'subtitles' => $subs];
    }

    private function parseSubtitles(array $list): array
    {
        $out = [];
        foreach ($list as $s) {
            if (!is_array($s)) continue;
            // Some rows put the URL in `url`, others mistakenly in `label`.
            $url = null;
            foreach (['url', 'subtitle', 'label'] as $f) {
                if (!empty($s[$f]) && is_string($s[$f]) && str_starts_with($s[$f], 'http')) { $url = $s[$f]; break; }
            }
            if (!$url) continue;
            $isVtt = str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?? ''), '.vtt');
            $out[] = [
                'lang'  => (string)($s['lang'] ?? $s['language'] ?? ''),
                'label' => (string)($s['lang'] ?? $s['language'] ?? ''),
                'vtt'   => $isVtt ? $url : null,
                'srt'   => $isVtt ? null : $url,
            ];
        }
        return $out;
    }
}
