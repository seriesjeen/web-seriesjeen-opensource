<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * BiliTV (id 129) — provider dramabos. Flow B:
 *   /short/{id}                  → data.{title, cover_img, desc, cate_name}
 *   /episode/{id}                → data.{list:[{episode, locked}], total}  (no URLs → lazy)
 *   /stream/{id}/{ep}?quality=   → data.{allQualities:{1080,720,478}, url, subtitle}
 *   /subtitle/{id}/{ep}?format=  → subtitle track (404 = no subs, normal)
 */
final class BilitvAdapter extends BaseAdapter
{
    public function detail(string $seriesId): array
    {
        $resp = $this->api->getJson($this->basePath() . '/short/' . rawurlencode($seriesId));
        $d = $resp['data'] ?? $resp;
        if (!is_array($d)) $d = [];

        $count = self::findCountAnywhere($d);
        if ($count === null) {
            try {
                $epResp = $this->api->getJson($this->basePath() . '/episode/' . rawurlencode($seriesId));
                $list = $epResp['data']['list'] ?? $epResp['list'] ?? $epResp['data'] ?? [];
                if (is_array($list) && array_is_list($list)) $count = count($list);
            } catch (\Throwable) { /* ignore */ }
        }

        return [
            'title'         => (string)($d['title'] ?? $d['name'] ?? self::findTitle($d) ?? ''),
            'description'   => $d['desc'] ?? $d['summary'] ?? $d['description'] ?? self::findDescription($d),
            'cover'         => $d['cover_img'] ?? $d['cover'] ?? $d['image'] ?? self::findCover($d),
            'episode_count' => $count,
            'genre'         => self::flattenGenre($d['cate_name'] ?? $d['genre'] ?? $d['tag'] ?? null),
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $resp = $this->api->getJson($this->basePath() . '/episode/' . rawurlencode($seriesId));
        $list = $resp['data']['list'] ?? $resp['list'] ?? $resp['data'] ?? $resp['episodes'] ?? [];
        if (!is_array($list) || !array_is_list($list)) $list = [];

        $eps = [];
        foreach ($list as $i => $ep) {
            if (!is_array($ep)) continue;
            $eps[] = [
                'episode' => (int)($ep['episode'] ?? $ep['number'] ?? ($i + 1)),
                'locked'  => (bool)($ep['locked'] ?? false),
                'duration'=> isset($ep['duration']) ? (int)$ep['duration'] : null,
                'cover'   => $ep['cover'] ?? null,
                'sources' => [],
                'subtitles' => [],
                'lazy'    => true,
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps, 'lazy' => true];
    }

    public function playEpisode(string $seriesId, int $episode): array
    {
        $sources = [];
        try {
            $resp = $this->api->getJson($this->basePath() . '/stream/' . rawurlencode($seriesId) . '/' . $episode, ['quality' => '720']);
            $d = $resp['data'] ?? $resp;
            $all = $d['allQualities'] ?? [];
            if (is_array($all)) {
                foreach (['1080', '720', '540', '478', '360'] as $q) {
                    if (!empty($all[$q])) $sources[] = ['quality'=>$q, 'codec'=>'h264', 'url'=>(string)$all[$q]];
                }
                foreach ($all as $q => $url) {
                    if (is_string($url) && $url !== '' && !in_array((string)$q, ['1080','720','540','478','360'], true)) {
                        $sources[] = ['quality'=>(string)$q, 'codec'=>'h264', 'url'=>$url];
                    }
                }
            }
            if (empty($sources) && !empty($d['url'])) {
                $sources[] = ['quality'=>'auto', 'codec'=>'h264', 'url'=>(string)$d['url']];
            }
        } catch (\Throwable) { /* skip */ }

        $subs = [];
        try {
            $sub = $this->api->getJson($this->basePath() . '/subtitle/' . rawurlencode($seriesId) . '/' . $episode, ['format' => 'vtt']);
            foreach (($sub['data'] ?? $sub['subtitles'] ?? []) as $s) {
                if (!is_array($s) || empty($s['url'] ?? $s['vtt'] ?? null)) continue;
                $subs[] = [
                    'lang'  => (string)($s['language'] ?? $s['lang'] ?? ''),
                    'label' => (string)($s['display_name'] ?? $s['label'] ?? $s['language'] ?? ''),
                    'vtt'   => $s['vtt'] ?? $s['url'] ?? null,
                ];
            }
        } catch (\Throwable) { /* no subs — normal for bilitv */ }

        return ['episode' => $episode, 'locked' => false, 'sources' => $sources, 'subtitles' => $subs];
    }
}
