<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * GoodShort (id 102) — provider captain. Flow B + unlock:
 *   /book/{id}                       → data.book.{bookName, bookDetailCover, introduction, chapterCount, labels}
 *   /chapters/{id}                   → data.list:[{id, index, charged, image, cdn (plain /mts/ m3u8), multiVideos[]}]
 *   /play/{id}/{chapter_id}?q=720p   → {m3u8 (encrypted /ets/ m3u8), k (AES-128 key, base64), s}
 *
 * Strategy: prefer the plain `cdn` m3u8 embedded in /chapters (no decrypt). If a chapter has no
 * cdn (locked/charged), fall back to /play which yields an encrypted m3u8 + key; the key is handed
 * to StreamProxy via `hls_key` so it gets inlined into the manifest as a data URI for hls.js.
 */
final class GoodshortAdapter extends BaseAdapter
{
    private array $cache = [];
    private array $chaptersCache = [];

    private function fetch(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        return $this->cache[$seriesId] = $this->api->getJson($this->basePath() . '/book/' . rawurlencode($seriesId));
    }

    private function fetchChapters(string $seriesId): array
    {
        if (isset($this->chaptersCache[$seriesId])) return $this->chaptersCache[$seriesId];
        try {
            $r = $this->api->getJson($this->basePath() . '/chapters/' . rawurlencode($seriesId));
            return $this->chaptersCache[$seriesId] = ($r['data']['list'] ?? []);
        } catch (\Throwable) {
            return $this->chaptersCache[$seriesId] = [];
        }
    }

    public function detail(string $seriesId): array
    {
        $d = $this->fetch($seriesId)['data'] ?? [];
        $book = $d['book'] ?? [];
        return [
            'title'         => (string)($book['bookName'] ?? ''),
            'description'   => $book['introduction'] ?? null,
            'cover'         => $book['bookDetailCover'] ?? $book['cover'] ?? null,
            'episode_count' => isset($book['chapterCount']) ? (int)$book['chapterCount'] : null,
            'genre'         => self::flattenGenre($book['labels'] ?? null),
            'extras'        => $book,
        ];
    }

    private function parseSourcesFromChapter(array $ep): array
    {
        $sources = [];
        if (!empty($ep['cdn'])) {
            $sources[] = ['quality'=>'auto', 'codec'=>'h264', 'url'=>(string)$ep['cdn']];
        }
        foreach (($ep['multiVideos'] ?? []) as $mv) {
            if (!is_array($mv) || empty($mv['filePath'])) continue;
            $sources[] = [
                'quality' => (string)($mv['type'] ?? 'auto'),
                'codec'   => 'h264',
                'url'     => (string)$mv['filePath'],
            ];
        }
        return $sources;
    }

    public function episodes(string $seriesId): array
    {
        $count = (int)($this->fetch($seriesId)['data']['book']['chapterCount'] ?? 0);
        $list = $this->fetchChapters($seriesId);

        $byIndex = [];
        foreach ($list as $ep) {
            if (!is_array($ep)) continue;
            $idx = (int)($ep['index'] ?? -1) + 1;
            $byIndex[$idx] = [
                'id'      => (string)($ep['id'] ?? ''),
                'cover'   => $ep['image'] ?? null,
                'locked'  => !empty($ep['charged']),
                'sources' => $this->parseSourcesFromChapter($ep),
            ];
        }

        $eps = [];
        $total = max($count, count($byIndex));
        for ($i = 1; $i <= $total; $i++) {
            $eps[] = array_merge(
                ['episode'=>$i, 'locked'=>false, 'sources'=>[], 'subtitles'=>[], 'lazy'=>true],
                $byIndex[$i] ?? []
            );
        }
        return ['series_id' => $seriesId, 'episodes' => $eps, 'lazy' => true];
    }

    /** Lazy fetch — called by PlayerController when episodes()[ep].sources is empty. */
    public function playEpisode(string $seriesId, int $episode): array
    {
        $chapterId = null;
        foreach ($this->fetchChapters($seriesId) as $ep) {
            if (!is_array($ep)) continue;
            $idx = (int)($ep['index'] ?? -1) + 1;
            if ($idx !== $episode) continue;
            // Plain m3u8 embedded in the chapter — no decrypt needed.
            $sources = $this->parseSourcesFromChapter($ep);
            if (!empty($sources)) {
                return ['episode'=>$episode, 'locked'=>false, 'sources'=>$sources, 'subtitles'=>[]];
            }
            $chapterId = (string)($ep['id'] ?? '');
            break;
        }
        if (!$chapterId) return ['episode'=>$episode, 'locked'=>false, 'sources'=>[], 'subtitles'=>[]];

        // Encrypted variant: /play/{id}/{chapter_id} → {m3u8, k}. The key is injected into the
        // manifest as a data URI by StreamProxy (replacing the upstream `local://` placeholder).
        try {
            $resp = $this->api->getJson($this->basePath() . '/play/' . rawurlencode($seriesId) . '/' . rawurlencode($chapterId), ['q' => '720p']);
        } catch (\Throwable) {
            return ['episode'=>$episode, 'locked'=>false, 'sources'=>[], 'subtitles'=>[]];
        }
        $p = $resp['data'] ?? $resp;
        $key = $p['k'] ?? $p['videoKey'] ?? null;
        $sources = [];
        foreach (['m3u8', 'cdn', 'url', 'video_url'] as $f) {
            if (!empty($p[$f]) && is_string($p[$f])) {
                $src = ['quality'=>'auto', 'codec'=>'h264', 'url'=>(string)$p[$f]];
                if ($key && is_string($key) && str_contains((string)$p[$f], '/ets/')) $src['hls_key'] = $key;
                $sources[] = $src;
                break;
            }
        }
        return ['episode'=>$episode, 'locked'=>false, 'sources'=>$sources, 'subtitles'=>[]];
    }
}
