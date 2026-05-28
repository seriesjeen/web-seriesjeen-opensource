<?php
declare(strict_types=1);

namespace App\Services\Adapters;

use App\Http\ApiException;

/**
 * Smart fallback adapter for platforms without a dedicated class.
 *
 * detail() — probes /detail/{id}, /drama/{id}, /short/{id}, /book/{id} until one returns
 * episodes() — probes embedded patterns (episode_list, videos, items, episodes, rows, list,
 *              shortPlayEpisodeList, episodes endpoint variants), then falls back to deriving
 *              an episode-count-only list from the detail's count field.
 */
final class GenericAdapter extends BaseAdapter
{
    private array $detailCache = [];

    private const DETAIL_PATHS = ['/detail/', '/drama/', '/short/', '/book/'];

    private function fetchDetailRaw(string $seriesId): array
    {
        if (isset($this->detailCache[$seriesId])) return $this->detailCache[$seriesId];

        foreach (self::DETAIL_PATHS as $prefix) {
            try {
                $resp = $this->api->getJson($this->basePath() . $prefix . rawurlencode($seriesId));
                return $this->detailCache[$seriesId] = $resp;
            } catch (ApiException $e) {
                if ($e->httpStatus !== 404) throw $e;
            }
        }
        throw new ApiException('Detail endpoint not found for ' . $this->slug, 404);
    }

    public function detail(string $seriesId): array
    {
        return $this->normalizeDetail($this->fetchDetailRaw($seriesId));
    }

    public function episodes(string $seriesId): array
    {
        // 1) Try to extract from the detail response (many platforms embed episodes there)
        try {
            $raw = $this->fetchDetailRaw($seriesId);
            $eps = $this->extractEpisodeList($raw);
            if (!empty($eps)) return ['series_id' => $seriesId, 'episodes' => $eps];
        } catch (\Throwable) { /* fall through */ }

        // 2) Try dedicated episode endpoints
        $candidates = [
            '/alleps/' . rawurlencode($seriesId),
            '/allepisode/' . rawurlencode($seriesId),
            '/episodes/' . rawurlencode($seriesId),
            '/episode/' . rawurlencode($seriesId),
            '/drama/' . rawurlencode($seriesId) . '/episodes',
            '/short/' . rawurlencode($seriesId) . '/episode',
            '/book/' . rawurlencode($seriesId) . '/chapters',
            '/drama/' . rawurlencode($seriesId) . '/videos',
        ];
        foreach ($candidates as $path) {
            try {
                $resp = $this->api->getJson($this->basePath() . $path);
                $eps = $this->extractEpisodeList($resp);
                if (!empty($eps)) return ['series_id' => $seriesId, 'episodes' => $eps];
            } catch (ApiException $e) {
                if ($e->httpStatus !== 404) continue;
            }
        }

        // 3) Last resort — derive a sequence from the detail's episode_count field
        try {
            $d = $this->detail($seriesId);
            $count = $d['episode_count'] ?? 0;
            if ($count > 0) {
                $eps = [];
                for ($i = 1; $i <= $count; $i++) {
                    $eps[] = ['episode' => $i, 'locked' => false, 'sources' => [], 'subtitles' => [], 'lazy' => true];
                }
                return ['series_id' => $seriesId, 'episodes' => $eps, 'lazy' => true];
            }
        } catch (\Throwable) { /* fall through */ }

        return ['series_id' => $seriesId, 'episodes' => []];
    }

    /**
     * Recursively scan a response tree for an array of episode-like objects.
     * Returns a normalized list or [] if no candidate is found.
     */
    protected function extractEpisodeList(mixed $tree, int $depth = 0): array
    {
        if ($depth > 6 || !is_array($tree)) return [];

        // Direct list at this level?
        if (array_is_list($tree) && count($tree) > 0 && $this->looksLikeEpisodeList($tree)) {
            return $this->normalizeEpisodeList($tree);
        }

        // Known field names that often hold episode lists
        $keys = [
            'episode_list', 'episodes', 'videos', 'chapters', 'items',
            'rows', 'list', 'episodeList', 'allEpisode',
            'shortPlayEpisodeList', 'chapter_list', 'episodeInfos',
        ];
        foreach ($keys as $k) {
            if (isset($tree[$k]) && is_array($tree[$k]) && array_is_list($tree[$k]) && count($tree[$k]) > 0 && $this->looksLikeEpisodeList($tree[$k])) {
                return $this->normalizeEpisodeList($tree[$k]);
            }
        }

        // Recurse
        foreach ($tree as $v) {
            if (is_array($v)) {
                $r = $this->extractEpisodeList($v, $depth + 1);
                if (!empty($r)) return $r;
            }
        }
        return [];
    }

    private function looksLikeEpisodeList(array $list): bool
    {
        $first = $list[0] ?? null;
        if (!is_array($first)) return false;
        // Heuristic: episode-like items contain one of these fields
        foreach (['episode', 'episodeNo', 'episode_no', 'episode_number', 'episodeNumber',
                  'index', 'chapter_index', 'chapterIndex', 'order', 'no', 'number'] as $f) {
            if (array_key_exists($f, $first)) return true;
        }
        // Or a media url + id-like field
        $hasMedia = false;
        foreach (['url', 'm3u8_url', 'videoUrl', 'video_url', 'external_audio_h264_m3u8', 'stream_url', 'video'] as $f) {
            if (!empty($first[$f])) { $hasMedia = true; break; }
        }
        $hasId = false;
        foreach (['id', 'vid', 'episodeId', 'chapter_id', 'chapterId'] as $f) {
            if (isset($first[$f])) { $hasId = true; break; }
        }
        return $hasMedia && $hasId;
    }

    private function normalizeEpisodeList(array $list): array
    {
        $out = [];
        foreach (array_values($list) as $i => $ep) {
            if (!is_array($ep)) continue;
            $epNum = $ep['episode'] ?? $ep['episodeNo'] ?? $ep['episode_no'] ?? $ep['episode_number']
                    ?? $ep['episodeNumber'] ?? $ep['index'] ?? $ep['chapter_index'] ?? $ep['chapterIndex']
                    ?? $ep['order'] ?? $ep['no'] ?? $ep['number'] ?? ($i + 1);

            $sources = [];
            // shortmax-like nested video object
            foreach (['video_480' => '480', 'video_720' => '720', 'video_1080' => '1080'] as $k => $q) {
                if (!empty($ep['video'][$k])) $sources[] = ['quality'=>$q, 'codec'=>'h264', 'url'=>(string)$ep['video'][$k]];
            }
            // direct URL fields
            foreach (['external_audio_h264_m3u8' => 'h264', 'external_audio_h265_m3u8' => 'h265'] as $f => $codec) {
                if (!empty($ep[$f])) $sources[] = ['quality'=>'auto', 'codec'=>$codec, 'url'=>(string)$ep[$f]];
            }
            if (empty($sources)) {
                foreach (['m3u8_url', 'videoUrl', 'video_url', 'url', 'hls'] as $f) {
                    if (!empty($ep[$f])) { $sources[] = ['quality'=>'auto', 'codec'=>'h264', 'url'=>(string)$ep[$f]]; break; }
                }
            }

            $locked = false;
            if (isset($ep['locked'])) $locked = (bool)$ep['locked'];
            elseif (isset($ep['lock'])) $locked = ((int)$ep['lock']) === 1;
            elseif (isset($ep['unlock'])) $locked = !$ep['unlock'];
            elseif (isset($ep['isLock'])) $locked = (bool)$ep['isLock'];
            elseif (isset($ep['is_free'])) $locked = ((int)$ep['is_free']) === 0;

            $out[] = [
                'episode'  => (int)$epNum,
                'id'       => (string)($ep['id'] ?? $ep['vid'] ?? $ep['episodeId'] ?? $ep['chapterId'] ?? $ep['chapter_id'] ?? ''),
                'locked'   => $locked,
                'duration' => isset($ep['duration']) ? (int)$ep['duration'] : null,
                'cover'    => $ep['cover'] ?? $ep['first_frame'] ?? null,
                'sources'  => $sources,
                'subtitles'=> [],
                'lazy'     => empty($sources),
            ];
        }
        usort($out, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return $out;
    }

    /** Generic per-episode fetch via the most common /play|/stream pattern. */
    public function playEpisode(string $seriesId, int $episode): array
    {
        $candidates = [
            '/play/' . rawurlencode($seriesId) . '/' . $episode,
            '/stream/' . rawurlencode($seriesId) . '/' . $episode,
            '/drama/' . rawurlencode($seriesId) . '/play/' . $episode,
            '/drama/' . rawurlencode($seriesId) . '/' . $episode,
        ];
        foreach ($candidates as $path) {
            try {
                $resp = $this->api->getJson($this->basePath() . $path);
                return $this->parsePlay($episode, $resp);
            } catch (ApiException $e) {
                if ($e->httpStatus !== 404) continue;
            }
        }
        return ['episode' => $episode, 'locked' => false, 'sources' => [], 'subtitles' => []];
    }

    private function parsePlay(int $episode, array $resp): array
    {
        $d = $resp['data']['data'] ?? $resp['data'] ?? $resp;
        if (!is_array($d)) $d = [];
        $sources = [];
        if (!empty($d['external_audio_h264_m3u8'])) $sources[] = ['quality'=>'auto','codec'=>'h264','url'=>(string)$d['external_audio_h264_m3u8']];
        if (!empty($d['external_audio_h265_m3u8'])) $sources[] = ['quality'=>'auto','codec'=>'h265','url'=>(string)$d['external_audio_h265_m3u8']];
        if (empty($sources) && !empty($d['m3u8_url'])) $sources[] = ['quality'=>'auto','codec'=>'h264','url'=>(string)$d['m3u8_url']];
        foreach (['video_480'=>'480','video_720'=>'720','video_1080'=>'1080'] as $k=>$q) {
            if (!empty($d['video'][$k])) $sources[] = ['quality'=>$q,'codec'=>'h264','url'=>(string)$d['video'][$k]];
        }
        $subs = [];
        foreach (($d['subtitle_list'] ?? []) as $s) {
            if (!is_array($s)) continue;
            $subs[] = [
                'lang' => (string)($s['language'] ?? ''),
                'label'=> (string)($s['display_name'] ?? $s['language'] ?? ''),
                'vtt'  => $s['vtt'] ?? null,
                'srt'  => $s['subtitle'] ?? null,
            ];
        }
        return ['episode'=>$episode, 'locked'=>(bool)($d['locked'] ?? false), 'sources'=>$sources, 'subtitles'=>$subs];
    }
}
