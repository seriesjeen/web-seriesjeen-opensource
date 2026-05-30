<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * ShortWave (id 259) — provider captain. Flow B:
 *   /drama/{id}                  → data.{drama_title, drama_description, drama_cover, total_episodes,
 *                                        episodes:[{chapter_id, chapter_index, chapter_name, is_free, cover}]}
 *   /stream/{id}/{chapter_id}    → data.{stream_url, subtitles:[{language, label, url}], next_chapter_id}
 */
final class ShortwaveAdapter extends BaseAdapter
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
        $list = $d['episodes'] ?? [];
        return [
            'title'         => (string)($d['drama_title'] ?? $d['title'] ?? ''),
            'description'   => $d['drama_description'] ?? $d['description'] ?? null,
            'cover'         => $d['drama_cover'] ?? $d['cover'] ?? null,
            'episode_count' => isset($d['total_episodes']) ? (int)$d['total_episodes'] : (is_array($list) ? count($list) : null),
            'genre'         => self::flattenGenre($d['drama_tags'] ?? null),
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $d = $this->fetch($seriesId);
        $eps = [];
        foreach (($d['episodes'] ?? []) as $i => $ep) {
            if (!is_array($ep)) continue;
            $eps[] = [
                'episode'  => (int)($ep['chapter_index'] ?? ($i + 1)),
                'id'       => (string)($ep['chapter_id'] ?? ''),
                'locked'   => isset($ep['is_free']) ? !$ep['is_free'] : false,
                'cover'    => $ep['cover'] ?? $ep['first_frame'] ?? null,
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
        $d = $this->fetch($seriesId);
        $chapterId = null;
        foreach (($d['episodes'] ?? []) as $i => $ep) {
            if (!is_array($ep)) continue;
            if ((int)($ep['chapter_index'] ?? ($i + 1)) === $episode) { $chapterId = (string)($ep['chapter_id'] ?? ''); break; }
        }
        if (!$chapterId) return ['episode'=>$episode,'locked'=>false,'sources'=>[],'subtitles'=>[]];

        try {
            $resp = $this->api->getJson($this->basePath() . '/stream/' . rawurlencode($seriesId) . '/' . rawurlencode($chapterId));
        } catch (\Throwable) {
            return ['episode'=>$episode,'locked'=>false,'sources'=>[],'subtitles'=>[]];
        }
        $s = $resp['data'] ?? $resp;
        $url = $s['stream_url'] ?? $s['url'] ?? null;

        $subs = [];
        foreach (($s['subtitles'] ?? []) as $sub) {
            if (!is_array($sub) || empty($sub['url'])) continue;
            $u = (string)$sub['url'];
            $fmt = strtolower((string)($sub['format'] ?? ''));
            $isVtt = $fmt === 'webvtt' || $fmt === 'vtt' || str_contains(strtolower($u), '.vtt');
            $subs[] = [
                'lang'  => (string)($sub['language'] ?? $sub['lang'] ?? ''),
                'label' => (string)($sub['label'] ?? $sub['language'] ?? ''),
                'vtt'   => $isVtt ? $u : null,
                'srt'   => $isVtt ? null : $u,
            ];
        }

        return [
            'episode'  => $episode,
            'id'       => $chapterId,
            'locked'   => false,
            'sources'  => $url ? [['quality'=>'auto', 'codec'=>'h264', 'url'=>(string)$url]] : [],
            'subtitles'=> $subs,
        ];
    }
}
