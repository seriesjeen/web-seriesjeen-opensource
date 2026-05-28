<?php
declare(strict_types=1);

namespace App\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

final class SeriesApiClient
{
    private Client $client;

    public function __construct(
        private readonly string $apiKey,
        ?string $baseUri = null,
        ?string $userAgent = null,
        ?int $timeoutSeconds = null,
    ) {
        $baseUri ??= $_ENV['SERIES_API_BASE_URL'] ?? 'https://api.seriesjeen.online';
        $userAgent ??= $_ENV['SERIES_API_USER_AGENT']
            ?? 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $timeoutSeconds ??= (int)($_ENV['SERIES_API_TIMEOUT'] ?? 15);

        $this->client = new Client([
            'base_uri' => $baseUri,
            'timeout'  => $timeoutSeconds,
            'http_errors' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'User-Agent'    => $userAgent,
                'Accept'        => 'application/json',
            ],
        ]);
    }

    /** @return array<string,mixed> */
    public function getJson(string $path, array $query = []): array
    {
        try {
            $response = $this->client->request('GET', $path, ['query' => $query]);
        } catch (GuzzleException $e) {
            throw new ApiException('Network error: ' . $e->getMessage(), 503);
        }

        $status = $response->getStatusCode();
        $body = (string)$response->getBody();
        $decoded = json_decode($body, true);

        if ($status >= 400) {
            $msg = is_array($decoded) && isset($decoded['detail'])
                ? (is_string($decoded['detail']) ? $decoded['detail'] : json_encode($decoded['detail']))
                : 'HTTP ' . $status;
            throw new ApiException($msg, $status, is_array($decoded) ? $decoded : null);
        }

        if (!is_array($decoded)) {
            throw new ApiException('Invalid JSON response', 502);
        }

        return $decoded;
    }
}
