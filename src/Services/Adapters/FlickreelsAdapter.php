<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * Flickreels has a single all-in-one endpoint:
 *   /batchload/{drama_id} → {status_code, msg, data:{playlet_id, title, cover, total, list:[
 *     {chapter_id, chapter_num, chapter_title, hls_url, play_url, is_lock, chapter_cover}
 *   ]}}
 */
final class FlickreelsAdapter extends BaseAdapter
{
    private array $cache = [];

    private function fetch(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        $resp = $this->api->getJson($this->basePath() . '/batchload/' . rawurlencode($seriesId));
        return $this->cache[$seriesId] = ($resp['data'] ?? $resp);
    }

    public function detail(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        return [
            'title'         => (string)($d['title'] ?? ''),
            'description'   => $d['description'] ?? null,
            'cover'         => $d['cover'] ?? $d['process_cover'] ?? null,
            'episode_count' => isset($d['total']) ? (int)$d['total'] : null,
            'genre'         => null,
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        $list = $d['list'] ?? [];
        $eps = [];
        foreach ($list as $ep) {
            if (!is_array($ep)) continue;
            $sources = [];
            // hls_url is often empty — fall back to play_url (mp4)
            foreach (['hls_url' => 'h264', 'play_url' => 'h264'] as $field => $codec) {
                if (!empty($ep[$field])) {
                    $sources[] = ['quality'=>'auto','codec'=>$codec,'url'=>(string)$ep[$field]];
                }
            }
            $eps[] = [
                'episode'  => (int)($ep['chapter_num'] ?? 0),
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
