<?php
declare(strict_types=1);

namespace App\Services\Adapters;

use App\Http\SeriesApiClient;
use App\Services\PlatformAdapter;

abstract class BaseAdapter implements PlatformAdapter
{
    public function __construct(
        protected readonly SeriesApiClient $api,
        protected readonly string $slug,
    ) {
    }

    public function slug(): string { return $this->slug; }

    protected function basePath(): string { return '/api/platform/' . $this->slug; }

    protected function filterQuery(array $filters): array
    {
        $out = [];
        if (!empty($filters['page'])) $out['page'] = (int)$filters['page'];
        if (!empty($filters['page_size'])) $out['page_size'] = (int)$filters['page_size'];
        if (!empty($filters['locale'])) $out['locale'] = (string)$filters['locale'];
        return $out;
    }

    protected function normalizeListResponse(array $resp): array
    {
        return [
            'platform_id' => $resp['platform_id'] ?? null,
            'page'        => (int)($resp['page'] ?? 1),
            'page_size'   => (int)($resp['page_size'] ?? count($resp['items'] ?? [])),
            'total'       => isset($resp['total']) ? (int)$resp['total'] : null,
            'items'       => array_map(fn($i) => $this->normalizeListItem(is_array($i) ? $i : []), $resp['items'] ?? []),
        ];
    }

    /** A broad set of fields used across upstream APIs for episode count. */
    protected const COUNT_FIELDS = [
        'episode_count', 'total_episodes', 'totalEpisodes', 'totalEpisode',
        'episodes', 'episode_num', 'chapterCount', 'chapter_count',
        'current_count', 'latestEpisodeNumber', 'episodeNum',
    ];

    protected function normalizeListItem(array $i): array
    {
        $count = null;
        foreach (self::COUNT_FIELDS as $f) {
            if (isset($i[$f]) && (is_int($i[$f]) || ctype_digit((string)$i[$f]))) {
                $count = (int)$i[$f];
                break;
            }
        }
        return [
            'series_id'    => (string)($i['series_id'] ?? $i['drama_id'] ?? $i['id'] ?? $i['videoid'] ?? ''),
            'title'        => (string)($i['title'] ?? $i['name'] ?? ''),
            'cover'        => $i['cover'] ?? $i['image'] ?? $i['thumbnail'] ?? null,
            'episode_count'=> $count,
            'genre'        => $i['genre'] ?? $i['category'] ?? null,
            'description'  => $i['description'] ?? $i['summary'] ?? null,
        ];
    }

    public function listSeries(array $filters): array
    {
        $q = $this->filterQuery($filters);
        $path = !empty($filters['keyword'])
            ? $this->basePath() . '/search'
            : (!empty($filters['genre'])
                ? $this->basePath() . '/genre/' . (int)$filters['genre']
                : $this->basePath() . '/list');
        if (!empty($filters['keyword'])) $q['keyword'] = (string)$filters['keyword'];
        return $this->normalizeListResponse($this->api->getJson($path, $q));
    }

    public function genres(?string $locale = null): array
    {
        try {
            $resp = $this->api->getJson($this->basePath() . '/genres', $locale ? ['locale' => $locale] : []);
        } catch (\Throwable) {
            return [];
        }
        $items = $resp['items'] ?? $resp['genres'] ?? $resp['data'] ?? $resp ?? [];
        if (!is_array($items)) return [];
        $out = [];
        foreach ($items as $g) {
            if (!is_array($g)) continue;
            $id = $g['id'] ?? $g['genre_id'] ?? null;
            $name = $g['name'] ?? $g['title'] ?? $g['genre'] ?? null;
            if ($id !== null && $name) $out[] = ['id' => $id, 'name' => (string)$name];
        }
        return $out;
    }

    public function detail(string $seriesId): array
    {
        $resp = $this->api->getJson($this->basePath() . '/detail/' . rawurlencode($seriesId));
        return $this->normalizeDetail($resp);
    }

    protected function normalizeDetail(array $resp): array
    {
        $d = $resp['data'] ?? $resp['item'] ?? $resp;
        if (!is_array($d)) $d = $resp;
        $count = self::findCountAnywhere($d) ?? self::findCountAnywhere($resp);
        return [
            'title'        => self::findTitle($d) ?? self::findTitle($resp) ?? '',
            'description'  => self::findDescription($d) ?? self::findDescription($resp),
            'cover'        => self::findCover($d) ?? self::findCover($resp),
            'episode_count'=> $count,
            'genre'        => self::flattenGenre($d['genre'] ?? $d['category'] ?? $d['tag'] ?? $d['content_tags'] ?? null),
            'extras'       => $d,
        ];
    }

    /** Recursively scan a tree for an integer count under known field names. */
    protected static function findCountAnywhere(mixed $v, int $depth = 0): ?int
    {
        if ($depth > 6 || !is_array($v)) return null;
        foreach (self::COUNT_FIELDS as $f) {
            if (array_key_exists($f, $v) && (is_int($v[$f]) || (is_string($v[$f]) && ctype_digit($v[$f])))) {
                $n = (int)$v[$f];
                if ($n > 0 && $n < 10000) return $n;
            }
        }
        foreach ($v as $sub) {
            if (is_array($sub)) {
                $r = self::findCountAnywhere($sub, $depth + 1);
                if ($r !== null) return $r;
            }
        }
        return null;
    }

    protected static function findTitle(mixed $v, int $depth = 0): ?string
    {
        if ($depth > 6 || !is_array($v)) return null;
        foreach (['title', 'name', 'bookName', 'shortPlayName', 'series_name', 'drama_name',
                  'playletName', 'cn_name', 'show_title', 'videoName', 'short_play_name',
                  'video_name', 'movie_name', 'chapter_title'] as $f) {
            if (isset($v[$f]) && is_string($v[$f]) && trim($v[$f]) !== '') return $v[$f];
        }
        foreach ($v as $sub) {
            if (is_array($sub)) {
                $r = self::findTitle($sub, $depth + 1);
                if ($r !== null) return $r;
            }
        }
        return null;
    }

    protected static function findDescription(mixed $v, int $depth = 0): ?string
    {
        if ($depth > 5 || !is_array($v)) return null;
        foreach (['description', 'summary', 'desc', 'intro', 'introduction', 'synopsis'] as $f) {
            if (isset($v[$f]) && is_string($v[$f]) && $v[$f] !== '') return $v[$f];
        }
        foreach ($v as $sub) {
            if (is_array($sub)) {
                $r = self::findDescription($sub, $depth + 1);
                if ($r !== null) return $r;
            }
        }
        return null;
    }

    protected static function findCover(mixed $v, int $depth = 0): ?string
    {
        if ($depth > 5 || !is_array($v)) return null;
        foreach (['cover', 'cover_url', 'coverWap', 'image', 'thumbnail', 'big_cover', 'coverImage', 'cover_image_url', 'bookDetailCover'] as $f) {
            if (isset($v[$f]) && is_string($v[$f]) && str_starts_with($v[$f], 'http')) return $v[$f];
        }
        foreach ($v as $sub) {
            if (is_array($sub)) {
                $r = self::findCover($sub, $depth + 1);
                if ($r !== null) return $r;
            }
        }
        return null;
    }

    protected static function flattenGenre(mixed $g): ?string
    {
        if (!$g) return null;
        if (is_string($g)) return $g;
        if (!is_array($g)) return null;
        $parts = [];
        foreach ($g as $item) {
            if (is_string($item)) { $parts[] = $item; continue; }
            if (is_array($item)) {
                $parts[] = (string)($item['name'] ?? $item['title'] ?? $item['tag_local'] ?? $item['tagName'] ?? $item['tag'] ?? '');
            }
        }
        $parts = array_filter(array_map('trim', $parts));
        return $parts ? implode(', ', $parts) : null;
    }
}
