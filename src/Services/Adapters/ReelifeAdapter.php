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
        // /play/{book_id}/{episode} returns {bookId, chapterId, standbyUrls:[mp4...]}
        $resp = $this->api->getJson($this->basePath() . '/play/' . rawurlencode($seriesId) . '/' . $episode);
        $d = $resp['data'] ?? $resp;
        $sources = [];
        foreach (($d['standbyUrls'] ?? []) as $url) {
            if (!is_string($url)) continue;
            // Guess quality from URL (540p.mp4 → '540')
            $q = preg_match('#([0-9]{3,4})p\.#', $url, $m) ? $m[1] : 'auto';
            $sources[] = ['quality'=>$q, 'codec'=>'h264', 'url'=>$url];
        }
        // Single URL fallback
        foreach (['m3u8_url', 'url', 'videoUrl', 'cdn'] as $f) {
            if (empty($sources) && !empty($d[$f])) {
                $sources[] = ['quality'=>'auto', 'codec'=>'h264', 'url'=>(string)$d[$f]];
            }
        }
        return ['episode'=>$episode, 'locked'=>false, 'sources'=>$sources, 'subtitles'=>[]];
    }
}
