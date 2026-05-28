<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * FunDrama:
 *   /drama/{id}                → meta in data.ddriv.btra (obfuscated keys: nsin=title, dentra=desc, eshe=count)
 *   /drama/{id}/episodes       → {success, data:{episodes:[{episode, id, videos:[{quality,url,duration}]}]}}
 */
final class FundramaAdapter extends DotdramaAdapter
{
    public function episodes(string $seriesId): array
    {
        try {
            $resp = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId) . '/episodes');
        } catch (\Throwable) {
            // fall back to count-only list from detail
            return parent::episodes($seriesId);
        }
        $list = $resp['data']['episodes'] ?? $resp['episodes'] ?? [];
        $eps = [];
        foreach ($list as $ep) {
            if (!is_array($ep)) continue;
            $sources = [];
            foreach (($ep['videos'] ?? []) as $v) {
                if (!is_array($v) || empty($v['url'])) continue;
                $sources[] = [
                    'quality' => (string)($v['quality'] ?? 'auto'),
                    'codec'   => 'h264',
                    'url'     => (string)$v['url'],
                ];
            }
            $eps[] = [
                'episode'  => (int)($ep['episode'] ?? 0),
                'id'       => (string)($ep['id'] ?? ''),
                'locked'   => false,
                'duration' => isset($ep['duration']) ? (int)$ep['duration'] : null,
                'sources'  => $sources,
                'subtitles'=> [],
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps];
    }
}
