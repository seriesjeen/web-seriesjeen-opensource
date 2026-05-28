<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * Shortwave has NO detail endpoint — only:
 *   /drama/{id}/episodes  → data[] of {chapter_id, chapter_index, chapter_name, first_frame, is_free}
 *   /stream/{id}/{chapter_id}/v.m3u8  → playable stream
 *   /unlock?drama_id=&chapter_id=  → unlock a chapter
 *
 * Synthesizes detail from the list response (which has title/cover) and the episodes response.
 */
final class ShortwaveAdapter extends BaseAdapter
{
    private array $cache = [];

    private function fetchEpisodes(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        $resp = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId) . '/episodes');
        return $this->cache[$seriesId] = $resp;
    }

    public function detail(string $seriesId): array
    {
        // Shortwave has no /detail endpoint — the title/cover live in the catalog response.
        // Inspect a few list pages to find the matching series.
        $title = '';
        $cover = null;
        $description = null;
        for ($page = 1; $page <= 5 && $title === ''; $page++) {
            try {
                $r = $this->api->getJson($this->basePath() . '/list', ['page' => $page, 'page_size' => 100]);
                foreach (($r['items'] ?? []) as $item) {
                    if ((string)($item['series_id'] ?? '') === $seriesId) {
                        $title       = (string)($item['title'] ?? '');
                        $cover       = $item['cover'] ?? null;
                        $description = $item['description'] ?? null;
                        break 2;
                    }
                }
            } catch (\Throwable) { break; }
        }

        $eps = $this->fetchEpisodes($seriesId);
        $list = $eps['data'] ?? [];
        return [
            'title'         => $title,
            'description'   => $description,
            'cover'         => $cover,
            'episode_count' => is_array($list) ? count($list) : null,
            'genre'         => null,
            'extras'        => ['raw' => $eps],
        ];
    }

    public function episodes(string $seriesId): array
    {
        $resp = $this->fetchEpisodes($seriesId);
        $list = $resp['data'] ?? [];
        $eps = [];
        foreach ($list as $ep) {
            if (!is_array($ep)) continue;
            $eps[] = [
                'episode'  => (int)($ep['chapter_index'] ?? 0),
                'id'       => (string)($ep['chapter_id'] ?? ''),
                'locked'   => isset($ep['is_free']) ? ((int)$ep['is_free']) === 0 : false,
                'cover'    => $ep['first_frame'] ?? null,
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
        // Shortwave's m3u8 endpoint requires Bearer auth — return the upstream URL directly
        // and let the generic /proxy/m3u8 wrapper add the Authorization header (it auto-detects
        // api.seriesjeen.online and injects Bearer from the session).
        $resp = $this->fetchEpisodes($seriesId);
        $chapterId = null;
        foreach (($resp['data'] ?? []) as $ep) {
            if ((int)($ep['chapter_index'] ?? 0) === $episode) {
                $chapterId = (string)($ep['chapter_id'] ?? '');
                break;
            }
        }
        if (!$chapterId) return ['episode'=>$episode,'locked'=>false,'sources'=>[],'subtitles'=>[]];

        $base = $_ENV['SERIES_API_BASE_URL'] ?? 'https://api.seriesjeen.online';
        $upstream = $base . '/api/platform/shortwave/stream/' . rawurlencode($seriesId)
                  . '/' . rawurlencode($chapterId) . '/v.m3u8';

        return [
            'episode'  => $episode,
            'id'       => $chapterId,
            'locked'   => false,
            // PlayerController + StreamProxy::wrapPayload will wrap this into /proxy/m3u8?...
            'sources'  => [['quality'=>'auto', 'codec'=>'h264', 'url'=>$upstream]],
            'subtitles'=> [],
        ];
    }
}
