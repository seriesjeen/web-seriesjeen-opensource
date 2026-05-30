<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * FlexTV (id 147) — provider captain. Flow B (section-based):
 *   /detail/{id}                 → data.{series_name, cover, description, max_series_no, tag:[{tag_name}]}
 *   /episodes/{id}/videos        → data.{list:[{id (section_id), series_no (episode), video_id,
 *                                               is_charge, video_duration, cover}], play_info}
 *   /play/{id}/{section_id}      → data.{video_url, progressive:[{title, video_url}], subtitle:[]}
 */
final class FlextvAdapter extends BaseAdapter
{
    private array $detailCache = [];
    private array $listCache = [];

    private function fetchDetail(string $seriesId): array
    {
        if (isset($this->detailCache[$seriesId])) return $this->detailCache[$seriesId];
        $resp = $this->api->getJson($this->basePath() . '/detail/' . rawurlencode($seriesId));
        return $this->detailCache[$seriesId] = ($resp['data'] ?? $resp);
    }

    /** Episode/section list from /episodes/{id}/videos. */
    private function fetchList(string $seriesId): array
    {
        if (isset($this->listCache[$seriesId])) return $this->listCache[$seriesId];
        try {
            $resp = $this->api->getJson($this->basePath() . '/episodes/' . rawurlencode($seriesId) . '/videos');
            $list = $resp['data']['list'] ?? [];
            return $this->listCache[$seriesId] = (is_array($list) ? $list : []);
        } catch (\Throwable) {
            return $this->listCache[$seriesId] = [];
        }
    }

    public function detail(string $seriesId): array
    {
        $d = $this->fetchDetail($seriesId);
        return [
            'title'         => (string)($d['series_name'] ?? $d['title'] ?? self::findTitle($d) ?? ''),
            'description'   => $d['description'] ?? self::findDescription($d),
            'cover'         => $d['cover'] ?? self::findCover($d),
            'episode_count' => isset($d['max_series_no']) ? (int)$d['max_series_no'] : self::findCountAnywhere($d),
            'genre'         => self::flattenGenre($d['tag'] ?? null),
            'extras'        => $d,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $list = $this->fetchList($seriesId);
        $eps = [];
        foreach ($list as $i => $ep) {
            if (!is_array($ep)) continue;
            $eps[] = [
                'episode'  => (int)($ep['series_no'] ?? ($i + 1)),
                'id'       => (string)($ep['id'] ?? ''),  // section_id
                'locked'   => (int)($ep['is_charge'] ?? 0) === 1 && (int)($ep['has_pay'] ?? 0) === 0,
                'duration' => isset($ep['video_duration']) ? (int)$ep['video_duration'] : null,
                'cover'    => $ep['cover'] ?? null,
                'sources'  => [],
                'subtitles'=> [],
                'lazy'     => true,
            ];
        }
        // Fall back to a count-only list if /episodes/videos returned nothing.
        if (empty($eps)) {
            $count = (int)($this->detail($seriesId)['episode_count'] ?? 0);
            for ($i = 1; $i <= $count; $i++) {
                $eps[] = ['episode'=>$i, 'locked'=>false, 'sources'=>[], 'subtitles'=>[], 'lazy'=>true];
            }
        }
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);
        return ['series_id' => $seriesId, 'episodes' => $eps, 'lazy' => true];
    }

    public function playEpisode(string $seriesId, int $episode): array
    {
        // Resolve the section_id for this episode number.
        $sectionId = null;
        foreach ($this->fetchList($seriesId) as $i => $ep) {
            if (!is_array($ep)) continue;
            if ((int)($ep['series_no'] ?? ($i + 1)) === $episode) { $sectionId = (string)($ep['id'] ?? ''); break; }
        }
        if (!$sectionId) return ['episode'=>$episode,'locked'=>false,'sources'=>[],'subtitles'=>[]];

        try {
            $resp = $this->api->getJson($this->basePath() . '/play/' . rawurlencode($seriesId) . '/' . rawurlencode($sectionId));
        } catch (\Throwable) {
            return ['episode'=>$episode,'locked'=>false,'sources'=>[],'subtitles'=>[]];
        }
        $d = $resp['data'] ?? $resp;

        $sources = [];
        $seen = [];
        foreach (($d['progressive'] ?? []) as $p) {
            if (!is_array($p) || empty($p['video_url']) || isset($seen[$p['video_url']])) continue;
            $seen[$p['video_url']] = true;
            $q = 'auto';
            if (!empty($p['title']) && preg_match('#(\d{3,4})#', (string)$p['title'], $m)) $q = $m[1];
            $sources[] = ['quality'=>$q, 'codec'=>'h264', 'url'=>(string)$p['video_url']];
        }
        if (empty($sources) && !empty($d['video_url'])) {
            $sources[] = ['quality'=>'auto', 'codec'=>'h264', 'url'=>(string)$d['video_url']];
        }

        $subs = [];
        foreach (($d['subtitle'] ?? $d['subtitles'] ?? []) as $s) {
            if (!is_array($s)) continue;
            $url = $s['url'] ?? $s['subtitle_url'] ?? null;
            if (!$url) continue;
            $isVtt = str_ends_with(strtolower(parse_url((string)$url, PHP_URL_PATH) ?? ''), '.vtt');
            $subs[] = [
                'lang'  => (string)($s['language'] ?? $s['lang'] ?? ''),
                'label' => (string)($s['language'] ?? $s['lang'] ?? ''),
                'vtt'   => $isVtt ? (string)$url : null,
                'srt'   => $isVtt ? null : (string)$url,
            ];
        }

        return ['episode'=>$episode, 'locked'=>false, 'sources'=>$sources, 'subtitles'=>$subs];
    }
}
