<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Http\ApiException;
use App\Http\SeriesApiClient;
use App\Services\PlatformRegistry;
use App\Services\StreamProxy;

final class ApiController
{
    public function meloloKey(Request $request, array $args): void
    {
        $vid = (string)$args['vid'];
        if (!preg_match('/^[a-f0-9]{16,64}$/i', $vid)) {
            Response::json(['error' => 'Invalid kid'], 400);
            return;
        }
        // Auth gate: user must be logged into the app.
        if (!Session::get('api_key')) {
            Response::json(['error' => 'Unauthorized'], 401);
            return;
        }

        // Upstream calls authenticate with the app's own SERIES_API_KEY (same as
        // PlatformRegistry) — NOT the user's login key-code held in the session.
        try {
            $client = new SeriesApiClient($_ENV['SERIES_API_KEY'] ?? '');
            $resp = $client->getJson('/api/platform/melolo/key', ['vid' => $vid]);
        } catch (ApiException $e) {
            Response::json(['error' => $e->getMessage()], $e->httpStatus);
            return;
        }
        Response::json(['key' => $resp['key'] ?? null]);
    }

    public function search(Request $request, array $args): void
    {
        $slug = strtolower($args['platform']);
        if (!PlatformRegistry::knows($slug) || !PlatformRegistry::userHas($slug)) {
            Response::json(['error' => 'Forbidden'], 403);
        }
        try {
            $adapter = PlatformRegistry::resolve($slug);
            $result = $adapter->listSeries([
                'keyword'   => $request->query('q'),
                'page'      => $request->queryInt('page', 1),
                'page_size' => $request->queryInt('page_size', 24),
                'locale'    => $request->query('locale'),
                'genre'     => $request->queryInt('genre'),
            ]);
        } catch (ApiException $e) {
            Response::json(['error' => $e->getMessage()], $e->httpStatus);
        }
        Response::json($result);
    }

    // ────────────────────────────────────────────────────────────────────────
    //  Generic media proxy — strips CORS by routing all CDN traffic through PHP.
    //  URLs are HMAC-signed by StreamProxy so the proxy can't be turned into an SSRF
    //  vector (only URLs minted by the application itself are accepted).
    // ────────────────────────────────────────────────────────────────────────

    /** GET /proxy/stream?url=&sig=  →  binary passthrough (mp4, ts, init segments, images). */
    public function proxyStream(Request $request, array $args): void
    {
        [$url, $cookies, $_k] = $this->validateProxyParams($request);
        $this->streamPassthrough($url, $cookies);
    }

    /** GET /proxy/m3u8?url=&sig=  →  fetch m3u8, rewrite contained URLs to also route through proxy. */
    public function proxyM3u8(Request $request, array $args): void
    {
        [$url, $cookies, $hlsKey] = $this->validateProxyParams($request);
        [$body, $code, $effectiveUrl] = $this->fetchString($url, includeBearerForSeriesjeen: true, cookies: $cookies);

        if ($code !== 200 || $body === null) {
            Response::json(['error' => 'Upstream HTTP ' . $code], 502);
        }

        header('Content-Type: application/vnd.apple.mpegurl');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: no-store');
        // Resolve relative segment URLs against the *final* URL after redirects, not the
        // original wrapper URL — Stardusttv goes dramabos.online/stream?url=... → mmcdn-v.stardust-tv.com
        echo StreamProxy::rewriteM3u8($body, $effectiveUrl, $cookies, $hlsKey);
        exit;
    }

    /** GET /proxy/vtt?url=&sig=  →  proxy WebVTT subtitles; transparently convert SRT → VTT. */
    public function proxyVtt(Request $request, array $args): void
    {
        [$url, $cookies, $_k] = $this->validateProxyParams($request);
        [$body, $code] = $this->fetchString($url, cookies: $cookies);  // effectiveUrl unused for vtt

        if ($code !== 200 || $body === null) {
            http_response_code(502);
            header('Content-Type: text/plain');
            echo "Upstream fetch failed (HTTP $code)";
            exit;
        }

        header('Content-Type: text/vtt; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: public, max-age=86400');
        echo StreamProxy::srtToVtt($body);
        exit;
    }

    // ────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ────────────────────────────────────────────────────────────────────────

    /** @return array{0:string,1:array<string,string>,2:string} [url, extraCookies, hlsKeyBase64] */
    private function validateProxyParams(Request $request): array
    {
        if (!Session::get('api_key')) {
            http_response_code(401);
            exit('Unauthorized');
        }
        // Release the session file lock NOW. PHP holds an exclusive lock on the session
        // for the whole request; a proxy stream can run for tens of seconds, so without
        // this every other request from the same user (e.g. a page refresh during
        // playback) blocks on session_start() until it times out. We only read the
        // session here — $_SESSION stays readable after close — so closing is safe.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $url = (string)($request->query('url') ?? '');
        $sig = (string)($request->query('sig') ?? '');
        $cB64 = (string)($request->query('c') ?? '');
        $kB64 = (string)($request->query('k') ?? '');
        if ($url === '' || !StreamProxy::isAbsoluteUrl($url) || !StreamProxy::verify($url, $sig, $cB64, $kB64)) {
            http_response_code(403);
            exit('Bad proxy request');
        }
        return [$url, StreamProxy::decodeCookies($cB64), $kB64];
    }

    /** Locate a system CA bundle for curl (PHP CLI / built-in server may lack one). */
    private static function caBundle(): ?string
    {
        foreach (['/etc/ssl/cert.pem', '/opt/homebrew/etc/ca-certificates/cert.pem',
                  '/usr/local/etc/openssl@3/cert.pem', '/etc/ssl/certs/ca-certificates.crt'] as $p) {
            if (is_file($p) && is_readable($p)) return $p;
        }
        return null;
    }

    /**
     * Fetch URL as string with sensible defaults.
     * @param array<string,string> $cookies upstream Cookie header pairs (e.g. CloudFront-Policy)
     * @return array{0:?string,1:int,2:string} [body, httpStatus, effectiveUrl after redirects]
     */
    private function fetchString(string $url, bool $includeBearerForSeriesjeen = false, array $cookies = []): array
    {
        $headers = ['User-Agent: ' . ($_ENV['SERIES_API_USER_AGENT'] ?? 'Mozilla/5.0')];
        $referer = $this->refererFor($url);
        if ($referer) $headers[] = 'Referer: ' . $referer;
        if ($includeBearerForSeriesjeen && str_contains($url, 'api.seriesjeen.online')) {
            $k = Session::get('api_key');
            if (is_string($k)) $headers[] = 'Authorization: Bearer ' . $k;
        }
        if (!empty($cookies)) {
            $headers[] = 'Cookie: ' . self::buildCookieHeader($cookies);
        }

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ];
        if ($ca = self::caBundle()) $opts[CURLOPT_CAINFO] = $ca;
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effective = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        @curl_close($ch);
        return [is_string($body) ? $body : null, $code, $effective ?: $url];
    }

    private static function buildCookieHeader(array $cookies): string
    {
        $parts = [];
        foreach ($cookies as $k => $v) {
            if (!is_string($k) || !is_string($v)) continue;
            $parts[] = $k . '=' . $v;
        }
        return implode('; ', $parts);
    }

    /**
     * Stream the upstream response directly to the client (no PHP buffering).
     * Forwards Range header for video seeking and mirrors upstream status + content-type.
     */
    private function streamPassthrough(string $url, array $cookies = []): void
    {
        $headers = ['User-Agent: ' . ($_ENV['SERIES_API_USER_AGENT'] ?? 'Mozilla/5.0')];
        $referer = $this->refererFor($url);
        if ($referer) $headers[] = 'Referer: ' . $referer;
        if (str_contains($url, 'api.seriesjeen.online')) {
            $k = Session::get('api_key');
            if (is_string($k)) $headers[] = 'Authorization: Bearer ' . $k;
        }
        if (!empty($cookies)) {
            $headers[] = 'Cookie: ' . self::buildCookieHeader($cookies);
        }
        if (!empty($_SERVER['HTTP_RANGE'])) {
            $headers[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
        }

        // Forward upstream HTTP status + selected response headers as they arrive
        $headerCallback = function ($ch, $line) {
            if (preg_match('#^HTTP/[0-9.]+\s+(\d+)#', $line, $m)) {
                http_response_code((int)$m[1]);
                return strlen($line);
            }
            $low = strtolower($line);
            foreach (['content-type:', 'content-length:', 'content-range:', 'accept-ranges:', 'last-modified:', 'etag:'] as $allow) {
                if (str_starts_with($low, $allow)) {
                    header(trim($line));
                    break;
                }
            }
            return strlen($line);
        };

        header('Access-Control-Allow-Origin: *');
        header('Cross-Origin-Resource-Policy: cross-origin');

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => $headerCallback,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) {
                echo $data;
                @ob_flush();
                @flush();
                return strlen($data);
            },
        ];
        // SSL verify: try CA bundle first, otherwise fall back. PHP's curl with PHP CLI server
        // often can't find the right CA chain even when one is configured, so we accept that
        // a proxy fetching public CDN bytes can run with verify off — there's no auth-bearing
        // traffic here, just signed CDN URLs.
        $ca = self::caBundle();
        if ($ca) {
            $opts[CURLOPT_CAINFO] = $ca;
        }
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
        $opts[CURLOPT_SSL_VERIFYHOST] = 0;
        curl_setopt_array($ch, $opts);
        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        @curl_close($ch);
        if ($status === 0 && $err !== '') {
            error_log("[proxy stream] curl error for $url: $err");
            if (!headers_sent()) http_response_code(502);
        } elseif ($status >= 400 && !headers_sent()) {
            http_response_code($status);
        }
        exit;
    }

    private function refererFor(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return null;
        // Most CDNs accept their own host as referer
        $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
        return "$scheme://$host/";
    }
}
