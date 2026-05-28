<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * Velolo: /drama/{id} → data.{videoInfo, episodesInfo, watchInfo}
 *   videoInfo.{title, description, cover}
 *   episodesInfo.{total, episodes[]?}
 */
final class VeloloAdapter extends BaseAdapter
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
        $v = $d['videoInfo'] ?? [];
        $e = $d['episodesInfo'] ?? [];
        $count = isset($e['total']) ? (int)$e['total'] : null;
        return [
            'title'         => (string)($v['title'] ?? $v['name'] ?? self::findTitle($d) ?? ''),
            'description'   => $v['description'] ?? $v['summary'] ?? self::findDescription($d),
            'cover'         => $v['cover'] ?? $v['image'] ?? self::findCover($d),
            'episode_count' => $count,
            'genre'         => $v['genre'] ?? null,
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        $rows = $d['episodesInfo']['rows'] ?? [];
        $eps = [];
        foreach ($rows as $i => $ep) {
            if (!is_array($ep)) continue;
            $sources = [];
            if (!empty($ep['videoAddress'])) {
                $sources[] = ['quality'=>'auto', 'codec'=>'h264', 'url'=>(string)$ep['videoAddress']];
            }
            $subs = [];
            if (!empty($ep['zimu']) && is_string($ep['zimu'])) {
                $subs[] = [
                    'lang'  => 'auto',
                    'label' => 'Subtitle',
                    'srt'   => (string)$ep['zimu'],
                    'vtt'   => null,
                ];
            }
            $eps[] = [
                'episode'  => (int)($ep['orderNumber'] ?? ($i + 1)) + 1,  // Velolo uses 0-based orderNumber
                'id'       => (string)($ep['id'] ?? ''),
                'locked'   => !empty($ep['isLock']),
                'sources'  => $sources,
                'subtitles'=> $subs,
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps];
    }
}
