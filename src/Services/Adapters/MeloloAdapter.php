<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * Melolo: /video?id=&ep= → qualityList[] with kid (AES-128 key id)
 * /key?vid=<kid> → 32-char hex AES-128 key
 * Files are .mp4 (encrypted) on v16-ml.melolostatic.com
 */
final class MeloloAdapter extends BaseAdapter
{
    private array $detailCache = [];

    private function fetchDetail(string $seriesId): array
    {
        if (isset($this->detailCache[$seriesId])) return $this->detailCache[$seriesId];
        return $this->detailCache[$seriesId] = $this->api->getJson(
            $this->basePath() . '/detail/' . rawurlencode($seriesId)
        );
    }

    public function detail(string $seriesId): array
    {
        $d = $this->fetchDetail($seriesId);
        $count = isset($d['episodes']) && is_int($d['episodes'])
            ? (int)$d['episodes']
            : (isset($d['videos']) ? count($d['videos']) : null);
        return [
            'title'         => (string)($d['title'] ?? ''),
            'description'   => $d['intro'] ?? $d['description'] ?? null,
            'cover'         => $d['cover'] ?? null,
            'episode_count' => $count,
            'genre'         => $d['genre'] ?? null,
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $d = $this->fetchDetail($seriesId);
        $eps = [];
        foreach (($d['videos'] ?? []) as $v) {
            if (!is_array($v)) continue;
            $eps[] = [
                'episode'  => (int)($v['episode'] ?? 0),
                'id'       => (string)($v['vid'] ?? ''),
                'locked'   => false,
                'duration' => isset($v['duration']) ? (int)$v['duration'] : null,
                'cover'    => null,
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
        $resp = $this->api->getJson($this->basePath() . '/video', [
            'id' => $seriesId,
            'ep' => $episode,
        ]);

        $sources = [];
        foreach (($resp['qualityList'] ?? []) as $q) {
            if (!is_array($q)) continue;
            $sources[] = [
                'quality' => (string)($q['label'] ?? 'auto'),
                'codec'   => 'h264',
                'url'     => (string)($q['url'] ?? ''),
                'kid'     => (string)($q['kid'] ?? ''),
                'mime'    => 'video/mp4',
                'size'    => $q['size'] ?? null,
            ];
        }

        return [
            'episode' => $episode,
            'locked'  => !empty($resp['locked']),
            'sources' => $sources,
            'subtitles' => [],
        ];
    }
}
