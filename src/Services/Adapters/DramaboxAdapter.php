<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * Dramabox uses query-style endpoints: ?bookId=
 *   /detail?bookId=...           → returns book metadata (no episodes)
 *   /allepisode?bookId=...       → returns ROOT array of {chapterIndex,videoUrl,chapterName,isCharge}
 */
final class DramaboxAdapter extends BaseAdapter
{
    public function detail(string $seriesId): array
    {
        $resp = $this->api->getJson($this->basePath() . '/detail', ['bookId' => $seriesId]);
        $count = self::findCountAnywhere($resp);
        return [
            'title'         => (string)($resp['bookName'] ?? self::findTitle($resp) ?? ''),
            'description'   => $resp['introduction'] ?? $resp['desc'] ?? self::findDescription($resp),
            'cover'         => $resp['coverWap'] ?? $resp['cover'] ?? self::findCover($resp),
            'episode_count' => $count,
            'genre'         => self::flattenGenre($resp['tags'] ?? $resp['tagV3s'] ?? null),
            'extras'        => $resp,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $resp = $this->api->getJson($this->basePath() . '/allepisode', ['bookId' => $seriesId]);
        $list = array_is_list($resp) ? $resp : ($resp['data'] ?? []);
        $eps = [];
        foreach ($list as $i => $ep) {
            if (!is_array($ep)) continue;
            $eps[] = [
                'episode'  => (int)($ep['chapterIndex'] ?? $ep['chapter_index'] ?? ($i + 1)),
                'id'       => (string)($ep['chapterId'] ?? ''),
                'locked'   => !empty($ep['isCharge']) && (int)$ep['isCharge'] !== 0,
                'sources'  => !empty($ep['videoUrl'])
                                ? [['quality'=>'auto', 'codec'=>'h264', 'url'=>(string)$ep['videoUrl']]]
                                : [],
                'subtitles'=> [],
            ];
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps];
    }
}
