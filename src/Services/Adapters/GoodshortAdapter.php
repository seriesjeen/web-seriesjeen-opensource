<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * GoodShort: two ways to get streaming URLs:
 *   1. /chapters/{id}                → ALL chapters with `cdn` field pointing to a non-encrypted
 *                                      m3u8 (path: /mts/...). Plain HLS, no key needed.
 *   2. /rawurl/{id}?q=720p           → ALL chapters with `m3u8` field pointing to an AES-128
 *                                      encrypted m3u8 (path: /ets/...). Response includes a
 *                                      single `videoKey` (base64) used for the whole book.
 *                                      The m3u8 has `URI="local://..."` placeholder that we
 *                                      replace with `URI="data:...;base64,KEY"` via StreamProxy.
 *
 * Order: try /chapters first (no decrypt overhead). If a chapter has no cdn, fall back to
 * /rawurl with key injection.
 */
final class GoodshortAdapter extends BaseAdapter
{
    private array $cache = [];
    private array $chaptersCache = [];
    private ?array $rawurlCache = null;

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

    /** Cache /rawurl payload (encrypted variant + AES key). */
    private function fetchRawurl(string $seriesId): ?array
    {
        if ($this->rawurlCache !== null) return $this->rawurlCache;
        try {
            $r = $this->api->getJson($this->basePath() . '/rawurl/' . rawurlencode($seriesId), ['q' => '720p']);
            return $this->rawurlCache = ($r['data'] ?? null);
        } catch (\Throwable) {
            return $this->rawurlCache = null;
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
        // First try /chapters (plain m3u8)
        foreach ($this->fetchChapters($seriesId) as $ep) {
            if (!is_array($ep)) continue;
            $idx = (int)($ep['index'] ?? -1) + 1;
            if ($idx !== $episode) continue;
            $sources = $this->parseSourcesFromChapter($ep);
            if (!empty($sources)) {
                return ['episode'=>$episode, 'locked'=>false, 'sources'=>$sources, 'subtitles'=>[]];
            }
        }

        // Fallback: /rawurl encrypted variant with AES key injection (per goodshort-proxy.js spec)
        $raw = $this->fetchRawurl($seriesId);
        if ($raw) {
            $videoKey = $raw['videoKey'] ?? null;
            foreach (($raw['episodes'] ?? []) as $ep) {
                if (!is_array($ep)) continue;
                $idx = (int)($ep['index'] ?? -1) + 1;
                if ($idx !== $episode) continue;
                $sources = [];
                foreach (($ep['allVideos'] ?? []) as $v) {
                    if (!is_array($v) || empty($v['rawUrl'])) continue;
                    $sources[] = [
                        'quality' => (string)($v['type'] ?? 'auto'),
                        'codec'   => 'h264',
                        'url'     => (string)$v['rawUrl'],
                        'hls_key' => $videoKey,  // StreamProxy will inject into m3u8 as data URI
                    ];
                }
                if (!empty($ep['m3u8']) && empty($sources)) {
                    $sources[] = [
                        'quality' => 'auto',
                        'codec'   => 'h264',
                        'url'     => (string)$ep['m3u8'],
                        'hls_key' => $videoKey,
                    ];
                }
                if (!empty($sources)) {
                    return ['episode'=>$episode, 'locked'=>false, 'sources'=>$sources, 'subtitles'=>[]];
                }
            }
        }

        return ['episode'=>$episode, 'locked'=>false, 'sources'=>[], 'subtitles'=>[]];
    }
}
