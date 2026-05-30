<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * DramaBox (id 27) — provider captain/dramaboxv4. Flow B:
 *   /drama/{book_id}            → data.{bookName, bookCover, introduction, chapterCount, tags}
 *   /drama/{book_id}/episodes   → data.{totalEpisodes, episodes:[{chapterId, episode, chapterName}]}  (no URLs → lazy)
 *   /play?bookId=&episode=      → data.{videos:{540,720,1080}, subtitles:[...]}
 */
final class DramaboxAdapter extends BaseAdapter
{
    private array $cache = [];

    private function fetchDetail(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        $resp = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId));
        return $this->cache[$seriesId] = ($resp['data'] ?? $resp);
    }

    public function detail(string $seriesId): array
    {
        $d = $this->fetchDetail($seriesId);
        return [
            'title'         => (string)($d['bookName'] ?? self::findTitle($d) ?? ''),
            'description'   => $d['introduction'] ?? $d['desc'] ?? self::findDescription($d),
            'cover'         => $d['bookCover'] ?? $d['coverWap'] ?? self::findCover($d),
            'episode_count' => isset($d['chapterCount']) ? (int)$d['chapterCount'] : self::findCountAnywhere($d),
            'genre'         => self::flattenGenre($d['tags'] ?? $d['tagV3s'] ?? null),
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $resp = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId) . '/episodes');
        $d = $resp['data'] ?? $resp;
        $eps = [];
        foreach (($d['episodes'] ?? []) as $i => $ep) {
            if (!is_array($ep)) continue;
            $eps[] = [
                'episode'  => (int)($ep['episode'] ?? $ep['chapterIndex'] ?? ($i + 1)),
                'id'       => (string)($ep['chapterId'] ?? ''),
                'locked'   => !empty($ep['isCharge']) && (int)$ep['isCharge'] !== 0,
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
            $resp = $this->api->getJson($this->basePath() . '/play', ['bookId' => $seriesId, 'episode' => $episode]);
        } catch (\Throwable) {
            return ['episode' => $episode, 'locked' => false, 'sources' => [], 'subtitles' => []];
        }
        $d = $resp['data'] ?? $resp;

        $sources = [];
        $videos = $d['videos'] ?? [];
        if (is_array($videos)) {
            $seen = [];
            foreach (['1080', '720', '540', '480', '360'] as $q) {
                if (!empty($videos[$q])) { $sources[] = ['quality'=>$q,'codec'=>'h264','url'=>(string)$videos[$q]]; $seen[$q] = true; }
            }
            foreach ($videos as $q => $url) {
                if (is_string($url) && $url !== '' && empty($seen[(string)$q])) {
                    $sources[] = ['quality'=>(string)$q,'codec'=>'h264','url'=>$url];
                }
            }
        }

        return [
            'episode'   => $episode,
            'locked'    => !empty($d['locked']),
            'sources'   => $sources,
            'subtitles' => $this->parseSubtitles($d['subtitles'] ?? []),
        ];
    }

    private function parseSubtitles(array $list): array
    {
        $out = [];
        foreach ($list as $s) {
            if (!is_array($s)) continue;
            $url = $s['url'] ?? $s['subtitle'] ?? $s['vtt'] ?? null;
            if (!$url) continue;
            $url = (string)$url;
            $isVtt = str_contains(strtolower($url), '.vtt');
            $out[] = [
                'lang'  => (string)($s['language'] ?? $s['lang'] ?? ''),
                'label' => (string)($s['display_name'] ?? $s['language'] ?? $s['lang'] ?? ''),
                'vtt'   => $isVtt ? $url : ($s['vtt'] ?? null),
                'srt'   => $isVtt ? null : $url,
            ];
        }
        return $out;
    }
}
