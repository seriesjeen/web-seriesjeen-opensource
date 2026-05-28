<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Server-side media proxy — wraps cross-origin CDN URLs through PHP backend to eliminate
 * browser CORS errors. URLs are HMAC-signed so only the application can issue them.
 *
 * Usage from any adapter:
 *   $sources[] = ['quality'=>'720', 'url' => StreamProxy::wrap($cdnUrl)];
 * Or batch via wrapPayload() — called centrally in PlayerController.
 */
final class StreamProxy
{
    private static ?string $secret = null;

    private static function secret(): string
    {
        if (self::$secret !== null) return self::$secret;

        $k = $_ENV['APP_KEY'] ?? '';
        if (!is_string($k) || strlen($k) < 32) {
            // Auto-generate persistent key in storage/ if not configured
            $file = dirname(__DIR__, 2) . '/storage/.proxy-key';
            if (is_file($file)) {
                $k = trim((string)file_get_contents($file));
            }
            if (!is_string($k) || strlen($k) < 32) {
                $k = bin2hex(random_bytes(32));
                @file_put_contents($file, $k);
                @chmod($file, 0600);
            }
        }
        return self::$secret = $k;
    }

    public static function sign(string $url): string
    {
        return hash_hmac('sha256', $url, self::secret());
    }

    public static function verify(string $url, string $sig, string $cookiesB64 = '', string $hlsKeyB64 = ''): bool
    {
        if (!is_string($sig) || strlen($sig) !== 64) return false;
        $payload = $url . '|' . $cookiesB64 . '|' . $hlsKeyB64;
        return hash_equals(self::sign($payload), $sig);
    }

    /** Decode the base64-JSON cookies blob from a proxy URL. */
    public static function decodeCookies(string $cookiesB64): array
    {
        if ($cookiesB64 === '') return [];
        $json = base64_decode($cookiesB64, true);
        if ($json === false) return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function isAbsoluteUrl(string $url): bool
    {
        return (bool)preg_match('#^https?://#i', $url);
    }

    /** Wrap a CDN media URL → same-origin proxy URL (auto-detects m3u8 vs binary). */
    public static function wrap(string $url, array $opts = []): string
    {
        if (!self::isAbsoluteUrl($url)) return $url;
        $isM3u8 = (bool)preg_match('#\.m3u8(\?|$)#i', $url);
        $endpoint = $isM3u8 ? '/proxy/m3u8' : '/proxy/stream';
        $params = ['url' => $url];
        // Optional pass-through: CloudFront cookies (vigloo) + HLS AES-128 key (goodshort etc.).
        // All encoded into the HMAC so they can't be tampered with.
        if (!empty($opts['cookies']) && is_array($opts['cookies'])) {
            $params['c'] = base64_encode(json_encode($opts['cookies']));
        }
        if (!empty($opts['hls_key']) && is_string($opts['hls_key'])) {
            // base64-encoded raw 16-byte AES key; will be injected into the m3u8 as a data URI
            $params['k'] = $opts['hls_key'];
        }
        $signPayload = $url . '|' . ($params['c'] ?? '') . '|' . ($params['k'] ?? '');
        $params['sig'] = self::sign($signPayload);
        return $endpoint . '?' . http_build_query($params);
    }

    public static function wrapVtt(string $url): string
    {
        if (!self::isAbsoluteUrl($url)) return $url;
        return '/proxy/vtt?url=' . rawurlencode($url) . '&sig=' . self::sign($url);
    }

    /**
     * Walk a player payload and wrap every absolute CDN URL through the proxy.
     * Mutates and returns the same shape used by `<script id="episode-data">`.
     */
    public static function wrapPayload(array $payload): array
    {
        if (isset($payload['sources']) && is_array($payload['sources'])) {
            foreach ($payload['sources'] as $i => $src) {
                if (!empty($src['url']) && is_string($src['url']) && self::isAbsoluteUrl($src['url'])) {
                    $opts = [];
                    if (!empty($src['cookies']) && is_array($src['cookies'])) $opts['cookies'] = $src['cookies'];
                    if (!empty($src['hls_key']) && is_string($src['hls_key'])) $opts['hls_key'] = $src['hls_key'];
                    $payload['sources'][$i]['url'] = self::wrap($src['url'], $opts);
                    // strip sensitive opts before serialising to <script id="episode-data">
                    unset($payload['sources'][$i]['cookies'], $payload['sources'][$i]['hls_key']);
                }
            }
        }
        if (isset($payload['subtitles']) && is_array($payload['subtitles'])) {
            foreach ($payload['subtitles'] as $i => $sub) {
                if (!empty($sub['vtt']) && is_string($sub['vtt']) && self::isAbsoluteUrl($sub['vtt'])) {
                    $payload['subtitles'][$i]['vtt'] = self::wrapVtt($sub['vtt']);
                }
                if (!empty($sub['srt']) && is_string($sub['srt']) && self::isAbsoluteUrl($sub['srt'])) {
                    $payload['subtitles'][$i]['srt'] = self::wrapVtt($sub['srt']);
                }
            }
        }
        return $payload;
    }

    /**
     * Rewrite all references inside an HLS m3u8 manifest so segments + nested playlists
     * + alt media + keys flow through this proxy too.
     */
    public static function rewriteM3u8(string $body, string $manifestUrl, array $inheritedCookies = [], string $inheritedHlsKey = ''): string
    {
        $base = self::baseDirOf($manifestUrl);
        $scheme = parse_url($manifestUrl, PHP_URL_SCHEME) ?: 'https';
        $host   = parse_url($manifestUrl, PHP_URL_HOST) ?: '';

        $resolve = function (string $u) use ($base, $scheme, $host): string {
            // Some upstreams (e.g. Shortwave) emit `/api/proxy/ts?url=<encoded-inner>` — but the
            // outer `/api/proxy/ts` endpoint doesn't actually exist (returns 404). Extract the
            // inner CDN URL and use it directly.
            if (preg_match('#^/api/proxy/ts\?url=(.+)$#', $u, $m)) {
                $inner = urldecode($m[1]);
                if (preg_match('#^https?://#i', $inner)) return $inner;
            }
            if (preg_match('#^https?://#i', $u)) return $u;
            if (str_starts_with($u, '//'))      return "$scheme:$u";
            if (str_starts_with($u, '/'))       return "$scheme://$host$u";
            return $base . $u;
        };

        // Cookies + HLS key set by an outer manifest follow into nested playlists + segments.
        $opts = [];
        if (!empty($inheritedCookies)) $opts['cookies'] = $inheritedCookies;
        if ($inheritedHlsKey !== '')   $opts['hls_key'] = $inheritedHlsKey;

        $lines = preg_split('/\r?\n/', $body);
        foreach ($lines as &$line) {
            $trim = trim($line);
            if ($trim === '') continue;

            if ($trim[0] === '#') {
                $line = preg_replace_callback('/URI="([^"]+)"/i', function ($m) use ($resolve, $opts, $inheritedHlsKey) {
                    // HLS AES key URI: `local://...` is a placeholder upstream uses to mark
                    // "key comes out-of-band". If we have a key in opts, inline it as a data URI
                    // so hls.js can decrypt with no extra round trip.
                    if ($inheritedHlsKey !== '' && str_starts_with($m[1], 'local://')) {
                        return 'URI="data:application/octet-stream;base64,' . $inheritedHlsKey . '"';
                    }
                    $abs = $resolve($m[1]);
                    return 'URI="' . self::wrap($abs, $opts) . '"';
                }, $line);
            } else {
                $abs = $resolve($trim);
                $line = self::wrap($abs, $opts);
            }
        }
        return implode("\n", $lines);
    }

    private static function baseDirOf(string $url): string
    {
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) return '';
        $scheme = $parsed['scheme'] ?? 'https';
        $host   = $parsed['host'];
        $path   = $parsed['path'] ?? '/';
        $dir = preg_replace('#/[^/]*$#', '/', $path) ?: '/';
        return "$scheme://$host$dir";
    }

    /** Convert SubRip (.srt) → WebVTT, leave WebVTT untouched. Player needs .vtt. */
    public static function srtToVtt(string $body): string
    {
        $body = ltrim($body, "\xEF\xBB\xBF"); // strip BOM
        if (str_starts_with($body, 'WEBVTT')) return $body;
        $vtt = preg_replace('/(\d{1,2}:\d{2}:\d{2}),(\d{3})/', '$1.$2', $body);
        return "WEBVTT\n\n" . $vtt;
    }
}
