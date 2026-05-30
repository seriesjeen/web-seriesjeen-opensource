<?php
declare(strict_types=1);

namespace App\Services\Adapters;

/**
 * DotDrama (id 33) / FunDrama detail — provider captain. Flow A (single request returns everything),
 * but the response uses ROTATING obfuscated keys. Real data lives at e.g. `dgiv.bswitc.{rand}` (meta)
 * and `dgiv.ebeer[]` (episodes), where each episode carries `pphys[]` of video variants.
 *
 * Observed (probed 2026-05-30):
 *   meta container : {dcup=series_id, nseri=title, dwill=desc, pday=cover, ewood=episode_count, lweek=lang}
 *   episode item   : {ewheel=episode_no, pphys:[{Mopp/Bcold=url, Dbag=quality(540P), Ctan=codec(h264), Dissue=duration}]}
 *
 * Because key names rotate, everything is resolved by VALUE heuristics rather than key names.
 */
class DotdramaAdapter extends BaseAdapter
{
    protected array $cache = [];

    protected function fetch(string $seriesId): array
    {
        if (isset($this->cache[$seriesId])) return $this->cache[$seriesId];
        return $this->cache[$seriesId] = $this->api->getJson($this->basePath() . '/drama/' . rawurlencode($seriesId));
    }

    public function detail(string $seriesId): array
    {
        $resp = $this->fetch($seriesId);
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
     * where keys are short pseudo-random strings and values include at least one
     * natural-language string and one integer.
     */
    private function findObfuscatedContainer(mixed $v, int $depth = 0): array
    {
        if ($depth > 6 || !is_array($v)) return [];
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
        foreach ($inner as $v) {
            if (is_string($v)) {
                if (preg_match('#^https?://#', $v)) $urls[] = $v;
                else $strings[] = $v;
            } elseif (is_int($v) && $v > 0 && $v < 5000) {
                $integers[] = $v;
            }
        }

        $isNatural = fn($s) => mb_strlen($s) >= 3
            && (str_contains($s, ' ')
                || preg_match('/[\x{0E00}-\x{0E7F}\x{3000}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', $s));

        $naturalStrings = array_values(array_filter($strings, $isNatural));
        $titleCandidates = array_values(array_filter($naturalStrings, fn($s) => mb_strlen($s) <= 80));
        $descCandidates  = array_values(array_filter($naturalStrings, fn($s) => mb_strlen($s) > 80));

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
        $resp = $this->fetch($seriesId);
        $list = $this->findEpisodeArray($resp);
        $eps = [];
        foreach ($list as $k => $row) {
            if (!is_array($row)) continue;
            $sources = $this->extractSources($row);
            // episode number = smallest reasonable int directly in the row
            $num = null;
            foreach ($row as $v) {
                if (is_int($v) && $v > 0 && $v < 10000) { $num = $v; break; }
            }
            $eps[] = [
                'episode'  => $num ?? ($k + 1),
                'id'       => $this->firstIdString($row),
                'locked'   => false,
                'sources'  => $sources,
                'subtitles'=> [],
                'lazy'     => empty($sources),
            ];
        }
        // de-dup by episode number, keep first
        $byNum = [];
        foreach ($eps as $e) { $byNum[$e['episode']] = $e; }
        $eps = array_values($byNum);
        usort($eps, fn($a, $b) => $a['episode'] <=> $b['episode']);

        if (empty($eps)) {
            $count = $this->detail($seriesId)['episode_count'] ?? 0;
            for ($i = 1; $i <= $count; $i++) {
                $eps[] = ['episode'=>$i, 'locked'=>false, 'sources'=>[], 'subtitles'=>[], 'lazy'=>true];
            }
        }
        return ['series_id' => $seriesId, 'episodes' => $eps];
    }

    /** Find the list-of-dicts that represents episodes (each item carries a nested array of video variants). */
    private function findEpisodeArray(mixed $v, int $depth = 0): array
    {
        if ($depth > 6 || !is_array($v)) return [];
        if (array_is_list($v) && count($v) > 0 && is_array($v[0])) {
            // Does item[0] contain a nested list whose first element has an http url?
            foreach ($v[0] as $sub) {
                if (is_array($sub) && array_is_list($sub) && !empty($sub) && is_array($sub[0])) {
                    foreach ($sub[0] as $sv) {
                        if (is_string($sv) && preg_match('#^https?://#', $sv)) return $v;
                    }
                }
            }
        }
        foreach ($v as $sub) {
            if (is_array($sub)) {
                $r = $this->findEpisodeArray($sub, $depth + 1);
                if (!empty($r)) return $r;
            }
        }
        return [];
    }

    /** Extract video sources from an obfuscated episode row (its nested pphys[] of variants). */
    private function extractSources(array $row): array
    {
        $sources = [];
        foreach ($row as $sub) {
            if (!is_array($sub) || !array_is_list($sub)) continue;
            foreach ($sub as $variant) {
                if (!is_array($variant)) continue;
                $url = null; $quality = 'auto'; $codec = 'h264';
                foreach ($variant as $val) {
                    if (is_string($val)) {
                        if ($url === null && preg_match('#^https?://#', $val)) $url = $val;
                        elseif (preg_match('/^\d{3,4}p$/i', $val)) $quality = strtolower(rtrim($val, 'pP'));
                        elseif (preg_match('/^h26[45]$/i', $val)) $codec = strtolower($val);
                    }
                }
                if ($url) $sources[] = ['quality'=>$quality, 'codec'=>$codec, 'url'=>$url];
            }
            if (!empty($sources)) break; // first nested list is the variants array
        }
        return $sources;
    }

    private function firstIdString(array $row): string
    {
        foreach ($row as $v) {
            if (is_string($v) && preg_match('/^[a-f0-9]{16,}$|^\d{6,}$/i', $v)) return $v;
        }
        return '';
    }
}
