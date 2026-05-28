<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * Dramabite: /drama/{cid} → {id, title, desc, cover, episodes (int), tags}
 *   /episodes/{cid} → ROOT array of {vid, title, url}
 *   /play/{cid}/{vid} → resolves single episode
 */
final class DramabiteAdapter extends BaseAdapter
{
    public function detail(string $seriesId): array
    {
        $resp = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId));
        return [
            'title'         => (string)($resp['title'] ?? ''),
            'description'   => $resp['desc'] ?? null,
            'cover'         => $resp['cover'] ?? null,
            'episode_count' => isset($resp['episodes']) && is_int($resp['episodes']) ? (int)$resp['episodes'] : null,
            'genre'         => self::flattenGenre($resp['tags'] ?? null),
            'extras'        => $resp,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $resp = $this->api->getJson($this->basePath() . '/episodes/' . rawurlencode($seriesId));
        $list = is_array($resp) && array_is_list($resp) ? $resp : ($resp['data'] ?? []);
        $eps = [];
        foreach ($list as $i => $ep) {
            if (!is_array($ep)) continue;
            $eps[] = [
                'episode'  => (int)($ep['vid'] ?? $ep['episode'] ?? $i + 1),
                'id'       => (string)($ep['vid'] ?? ''),
                'locked'   => false,
                'sources'  => !empty($ep['url'])
                                ? [['quality'=>'auto','codec'=>'h264','url'=>(string)$ep['url']]]
                                : [],
                'subtitles'=> [],
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps];
    }
}
