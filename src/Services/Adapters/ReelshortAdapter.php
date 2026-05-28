<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * Reelshort:
 *   /detail/{id}       → meta + `chapters` (integer, total count)
 *   /chapters/{id}     → {chapters:[{id,name,duration,is_lock}], lang}
 *   /allepisodes/{id}  → episode list with sources (where available)
 */
final class ReelshortAdapter extends BaseAdapter
{
    private array $detailCache = [];
    private array $chaptersCache = [];

    private function fetchDetail(string $seriesId): array
    {
        if (!isset($this->detailCache[$seriesId])) {
            $this->detailCache[$seriesId] = $this->api->getJson($this->basePath() . '/detail/' . rawurlencode($seriesId));
        }
        return $this->detailCache[$seriesId];
    }

    private function fetchChapters(string $seriesId): array
    {
        if (!isset($this->chaptersCache[$seriesId])) {
            $this->chaptersCache[$seriesId] = $this->api->getJson($this->basePath() . '/chapters/' . rawurlencode($seriesId));
        }
        return $this->chaptersCache[$seriesId];
    }

    public function detail(string $seriesId): array
    {
        $d = $this->fetchDetail($seriesId);
        $count = isset($d['chapters']) && (is_int($d['chapters']) || ctype_digit((string)$d['chapters']))
                    ? (int)$d['chapters']
                    : null;
        return [
            'title'         => (string)($d['title'] ?? ''),
            'description'   => is_string($d['desc'] ?? null) ? $d['desc'] : null,
            'cover'         => is_string($d['pic'] ?? null) ? $d['pic'] : null,
            'episode_count' => $count,
            'genre'         => self::flattenGenre($d['theme'] ?? null),
            'extras'        => $d,
        ];
    }

    private array $allepisodesCache = [];

    private function fetchAllEpisodes(string $seriesId): array
    {
        if (isset($this->allepisodesCache[$seriesId])) return $this->allepisodesCache[$seriesId];
        try {
            $resp = $this->api->getJson($this->basePath() . '/allepisodes/' . rawurlencode($seriesId));
            return $this->allepisodesCache[$seriesId] = $resp['episodes'] ?? [];
        } catch (\Throwable) {
            return $this->allepisodesCache[$seriesId] = [];
        }
    }

    public function episodes(string $seriesId): array
    {
        $list = $this->fetchAllEpisodes($seriesId);
        // /allepisodes/{id} → {episodes:[{episode, id, name, duration, streams:[{quality, url}]}]}
        $eps = [];
        foreach ($list as $i => $ep) {
            if (!is_array($ep)) continue;
            $sources = [];
            foreach (($ep['streams'] ?? []) as $s) {
                if (!is_array($s) || empty($s['url'])) continue;
                $url = (string)$s['url'];
                // Detect h264 vs h265 from URL path
                $codec = str_contains($url, '/h265/') ? 'h265' : 'h264';
                $sources[] = [
                    'quality' => (string)($s['quality'] ?? 'auto'),
                    'codec'   => $codec,
                    'url'     => $url,
                ];
            }
            $eps[] = [
                'episode'  => (int)($ep['episode'] ?? $i + 1),
                'id'       => (string)($ep['id'] ?? ''),
                'locked'   => !empty($ep['is_lock']) || !empty($ep['locked']),
                'duration' => isset($ep['duration']) ? (int)$ep['duration'] : null,
                'sources'  => $sources,
                'subtitles'=> [],
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);

        // Fallback to /chapters/{id} if /allepisodes returns nothing
        if (empty($eps)) {
            $resp = $this->fetchChapters($seriesId);
            foreach (($resp['chapters'] ?? []) as $i => $ep) {
                if (!is_array($ep)) continue;
                $eps[] = [
                    'episode'  => $i + 1,
                    'id'       => (string)($ep['id'] ?? ''),
                    'locked'   => isset($ep['is_lock']) && (int)$ep['is_lock'] === 1,
                    'duration' => isset($ep['duration']) ? (int)$ep['duration'] : null,
                    'sources'  => [],
                    'subtitles'=> [],
                    'lazy'     => true,
                ];
            }
        }
        return ['series_id' => $seriesId, 'episodes' => $eps];
    }
}
