<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * DramaNova:
 *   /detail/{id}     → drama meta (data.totalEpisodes)
 *   /episodes/{id}   → {drama:{...meta}, rows:[{episodeNumber,videoUrl,videoUrl720,videoUrl1080,subtitles[]}], total}
 */
final class DramanovaAdapter extends BaseAdapter
{
    private array $cache = [];

    private function fetch(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        return $this->cache[$seriesId] = $this->api->getJson($this->basePath() . '/episodes/' . rawurlencode($seriesId));
    }

    public function detail(string $seriesId): array
    {
        $r = $this->fetch($seriesId);
        $meta = $r['drama'] ?? [];
        $total = (int)($r['total'] ?? count($r['rows'] ?? []));
        return [
            'title'         => (string)($meta['title'] ?? $meta['name'] ?? self::findTitle($r) ?? ''),
            'description'   => $meta['synopsis'] ?? $meta['description'] ?? self::findDescription($r),
            'cover'         => $meta['cover'] ?? self::findCover($r),
            'episode_count' => $total,
            'genre'         => $meta['genre'] ?? null,
            'extras'        => $meta,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $r = $this->fetch($seriesId);
        $rows = $r['rows'] ?? [];
        $eps = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $sources = [];
            if (!empty($row['videoUrl']))      $sources[] = ['quality'=>'auto','codec'=>'h264','url'=>(string)$row['videoUrl']];
            if (!empty($row['videoUrl720']))   $sources[] = ['quality'=>'720','codec'=>'h264','url'=>(string)$row['videoUrl720']];
            if (!empty($row['videoUrl1080']))  $sources[] = ['quality'=>'1080','codec'=>'h264','url'=>(string)$row['videoUrl1080']];

            $subs = [];
            foreach (($row['subtitles'] ?? []) as $s) {
                if (!is_array($s) || empty($s['url'])) continue;
                $url = (string)$s['url'];
                $isVtt = str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?? ''), '.vtt');
                $subs[] = [
                    'lang'  => (string)($s['language'] ?? $s['lang'] ?? ''),
                    'label' => (string)($s['language'] ?? ''),
                    'vtt'   => $isVtt ? $url : null,
                    'srt'   => $isVtt ? null : $url,
                ];
            }

            $eps[] = [
                'episode'  => (int)($row['episodeNumber'] ?? 0),
                'id'       => (string)($row['fileId'] ?? ''),
                'locked'   => isset($row['lockStatus']) && (int)$row['lockStatus'] === 1 && empty($sources),
                'cover'    => $row['poster'] ?? null,
                'duration' => isset($row['duration']) ? (int)$row['duration'] : null,
                'sources'  => $sources,
                'subtitles'=> $subs,
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps];
    }
}
