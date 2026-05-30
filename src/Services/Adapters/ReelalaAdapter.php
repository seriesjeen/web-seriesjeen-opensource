<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * ReelAla (id 165) — provider captain. Flow A (single request returns everything, no Thai):
 *   /drama/{id}/videos → data.{playlet_id, title, cover, list:[
 *                          {chapter_id, chapter_num, chapter_cover, chapter_title, hls_url}
 *                        ]}
 */
final class ReelalaAdapter extends BaseAdapter
{
    private array $cache = [];

    private function fetch(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        $resp = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId) . '/videos');
        return $this->cache[$seriesId] = ($resp['data'] ?? $resp);
    }

    public function detail(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        $list = $d['list'] ?? [];
        return [
            'title'         => (string)($d['title'] ?? $d['contract_title'] ?? ''),
            'description'   => $d['description'] ?? $d['introduce'] ?? null,
            'cover'         => $d['cover'] ?? null,
            'episode_count' => is_array($list) ? count($list) : null,
            'genre'         => null,
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        $eps = [];
        foreach (($d['list'] ?? []) as $i => $ep) {
            if (!is_array($ep)) continue;
            $url = $ep['hls_url'] ?? $ep['play_url'] ?? null;
            $sources = $url ? [['quality'=>'auto','codec'=>'h264','url'=>(string)$url]] : [];
            $eps[] = [
                'episode'  => (int)($ep['chapter_num'] ?? ($i + 1)),
                'id'       => (string)($ep['chapter_id'] ?? ''),
                'locked'   => (int)($ep['is_lock'] ?? 0) === 1,
                'cover'    => $ep['chapter_cover'] ?? null,
                'sources'  => $sources,
                'subtitles'=> [],
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps];
    }
}
