<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * Reelife: /book/{id}/chapters → data.bookVo (meta) + data.chapterList[] (chapters).
 *   chapter shape: {bookId, chapterId, chapterName, chapterImg, isCharge, price, likeNum}
 *   /play/{book_id}/{episode} → resolves the playable stream URL.
 */
final class ReelifeAdapter extends BaseAdapter
{
    private array $cache = [];

    private function fetchChapters(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        $resp = $this->api->getJson($this->basePath() . '/book/' . rawurlencode($seriesId) . '/chapters');
        return $this->cache[$seriesId] = ($resp['data'] ?? $resp);
    }

    public function detail(string $seriesId): array
    {
        $d = $this->fetchChapters($seriesId);
        $book = $d['bookVo'] ?? [];
        $chapters = $d['chapterList'] ?? [];
        return [
            'title'         => (string)($book['bookName'] ?? ''),
            'description'   => $book['introduction'] ?? null,
            'cover'         => $book['coverWap'] ?? null,
            'episode_count' => is_array($chapters) ? count($chapters) : null,
            'genre'         => self::flattenGenre($book['bookTags'] ?? null),
            'extras'        => $book,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $d = $this->fetchChapters($seriesId);
        $list = $d['chapterList'] ?? [];
        $eps = [];
        foreach ($list as $i => $ep) {
            if (!is_array($ep)) continue;
            $eps[] = [
                'episode'  => (int)($ep['chapterId'] ?? $i + 1),
                'id'       => (string)($ep['chapterId'] ?? ''),
                'locked'   => !empty($ep['isCharge']) && (int)$ep['isCharge'] === 1 && ($ep['price'] ?? 0) > 0,
                'cover'    => $ep['chapterImg'] ?? null,
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
        // /play/{book_id}/{episode} → {data:{chapterContentList:[{mp4720p, mp4720pStandByUrl[],
        //                              videoInfoList:[{videoPath/url, ...}]}]}, video_url}
        $resp = $this->api->getJson($this->basePath() . '/play/' . rawurlencode($seriesId) . '/' . $episode);
        $d = $resp['data'] ?? $resp;
        $sources = [];
        $seen = [];
        $add = function (?string $url, string $q = 'auto') use (&$sources, &$seen) {
            if (!is_string($url) || $url === '' || isset($seen[$url])) return;
            if (!preg_match('#^https?://#', $url)) return;
            if ($q === 'auto' && preg_match('#([0-9]{3,4})p#i', $url, $m)) $q = $m[1];
            $seen[$url] = true;
            $sources[] = ['quality' => $q, 'codec' => 'h264', 'url' => $url];
        };

        foreach (($d['chapterContentList'] ?? []) as $c) {
            if (!is_array($c)) continue;
            // primary 720p + standby mirrors
            $add($c['mp4720p'] ?? null, '720');
            foreach ((array)($c['mp4720pStandByUrl'] ?? []) as $u) $add(is_string($u) ? $u : null, '720');
            // multi-quality list
            foreach (($c['videoInfoList'] ?? []) as $vi) {
                if (!is_array($vi)) continue;
                $u = $vi['videoPath'] ?? $vi['url'] ?? $vi['mp4'] ?? null;
                $q = isset($vi['quality']) ? (string)$vi['quality'] : 'auto';
                $add(is_string($u) ? $u : null, $q);
            }
        }
        // Top-level / single-URL fallbacks
        foreach (['video_url', 'm3u8_url', 'url', 'videoUrl', 'cdn'] as $f) {
            if (!empty($resp[$f])) $add((string)$resp[$f]);
            if (!empty($d[$f])) $add((string)$d[$f]);
        }
        foreach ((array)($d['standbyUrls'] ?? []) as $u) $add(is_string($u) ? $u : null);

        return ['episode'=>$episode, 'locked'=>false, 'sources'=>$sources, 'subtitles'=>[]];
    }
}
