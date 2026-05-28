<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * Dotdrama (and Rapidtv) return obfuscated-keyed responses. Real data lives at
 *   dgiv.bswitc.{random-keys} — the keys are stable per-platform but meaningless names.
 *
 * Observed mapping on dotdrama (probed 2026-05-24):
 *   dgiv.bswitc.dcup    → series_id (string)
 *   dgiv.bswitc.dwill   → description (Thai/CJK string)
 *   dgiv.bswitc.ewood   → episode_count (int)
 *   dgiv.bswitc.nseri   → title (string)
 *   dgiv.bswitc.pday    → cover URL prefix (string starting with https://)
 *
 * Since the upstream may rotate these key names, this adapter also falls back to a
 * value-based scan: longest string with http prefix → cover, longest CJK string → desc,
 * shortest CJK string → title, smallest reasonable int → episode_count.
 */
class DotdramaAdapter extends BaseAdapter
{
    public function detail(string $seriesId): array
    {
        $resp = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId));
        $inner = $this->findObfuscatedContainer($resp);
        $info = $this->inferFields($inner);
        return [
            'title'         => $info['title'],
            'description'   => $info['description'],
            'cover'         => $info['cover'],
            'episode_count' => $info['episode_count'],
            'genre'         => null,
            'extras'        => $inner,
        ];
    }

    /**
     * Locate the obfuscated leaf container — the deepest array of scalar key-value pairs
     * where keys are short pseudo-random strings (no English word boundaries) and values
     * include at least one natural-language string and one integer.
     */
    private function findObfuscatedContainer(mixed $v, int $depth = 0): array
    {
        if ($depth > 6 || !is_array($v)) return [];
        // Score this level: are most values scalars (not nested arrays)?
        $scalars = 0; $hasText = false; $hasInt = false;
        foreach ($v as $val) {
            if (is_string($val) || is_int($val) || is_null($val)) $scalars++;
            if (is_string($val) && mb_strlen($val) > 30) $hasText = true;
            if (is_int($val) && $val > 0 && $val < 5000) $hasInt = true;
        }
        if ($scalars >= 5 && $hasText && $hasInt) return $v;

        foreach ($v as $sub) {
            if (is_array($sub)) {
                $r = $this->findObfuscatedContainer($sub, $depth + 1);
                if (!empty($r)) return $r;
            }
        }
        return [];
    }

    /** Value-based field inference for obfuscated-key responses. */
    private function inferFields(array $inner): array
    {
        $strings = [];
        $integers = [];
        $urls = [];
        foreach ($inner as $k => $v) {
            if (is_string($v)) {
                if (preg_match('#^https?://#', $v)) $urls[] = $v;
                else $strings[$k] = $v;
            } elseif (is_int($v) && $v > 0 && $v < 5000) {
                $integers[] = $v;
            }
        }

        // Detect "natural-language" strings: contain a space OR a CJK/Thai character.
        // Enum values like "PUB", "SELF_OWNED", "NULL" never match.
        $isNatural = fn($s) => mb_strlen($s) >= 3
            && (str_contains($s, ' ')
                || preg_match('/[\x{0E00}-\x{0E7F}\x{3000}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', $s));

        $naturalStrings = array_values(array_filter($strings, $isNatural));
        $titleCandidates = array_filter($naturalStrings, fn($s) => mb_strlen($s) <= 80);
        $descCandidates  = array_filter($naturalStrings, fn($s) => mb_strlen($s) > 80);

        usort($titleCandidates, fn($a, $b) => mb_strlen($a) <=> mb_strlen($b));
        usort($descCandidates,  fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        sort($integers);

        return [
            'title'         => $titleCandidates ? reset($titleCandidates) : '',
            'description'   => $descCandidates ? reset($descCandidates) : null,
            'cover'         => $urls[0] ?? null,
            'episode_count' => $integers ? end($integers) : null,
        ];
    }

    public function episodes(string $seriesId): array
    {
        $d = $this->detail($seriesId);
        $count = $d['episode_count'] ?? 0;
        $eps = [];
        for ($i = 1; $i <= $count; $i++) {
            $eps[] = ['episode' => $i, 'locked' => false, 'sources' => [], 'subtitles' => [], 'lazy' => true];
        }
        return ['series_id' => $seriesId, 'episodes' => $eps, 'lazy' => true];
    }

    public function playEpisode(string $seriesId, int $episode): array
    {
        try {
            // /drama/{id}/{ep} returns plain JSON: {drama_id, episode, total_episodes, video_hd, video_url}
            $resp = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId) . '/' . $episode);
        } catch (\Throwable) {
            return ['episode'=>$episode,'locked'=>false,'sources'=>[],'subtitles'=>[]];
        }
        $sources = [];
        if (!empty($resp['video_url'])) $sources[] = ['quality'=>'auto','codec'=>'h264','url'=>(string)$resp['video_url']];
        if (!empty($resp['video_hd']))  $sources[] = ['quality'=>'hd','codec'=>'h264','url'=>(string)$resp['video_hd']];

        // Last-resort: scan for any m3u8/mp4 URL in the response
        if (empty($sources)) {
            $url = $this->findUrl($resp);
            if ($url) $sources[] = ['quality'=>'auto','codec'=>'h264','url'=>$url];
        }

        return [
            'episode'  => $episode,
            'locked'   => false,
            'sources'  => $sources,
            'subtitles'=> [],
        ];
    }

    private function findUrl(mixed $v, int $depth = 0): ?string
    {
        if ($depth > 8) return null;
        if (is_string($v) && preg_match('#^https?://.*\.(m3u8|mp4)#i', $v)) return $v;
        if (is_array($v)) foreach ($v as $sub) {
            $r = $this->findUrl($sub, $depth + 1);
            if ($r) return $r;
        }
        return null;
    }
}
