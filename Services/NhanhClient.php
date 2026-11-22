<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Services;

use Symfony\Component\HttpClient\HttpClient;
use Witals\Framework\Application;

final class NhanhClient implements NhanhClientInterface
{
    public function __construct(
        private Application $app,
    ) {
    }

    public function baseUrl(string $host): string
    {
        $hosts = $this->app->config('ecommerce.upstream.hosts', []);

        return (string) ($hosts[$host]['base_url'] ?? '');
    }

    public function post(
        string $host,
        string $path,
        array $query = [],
        array $body = [],
        array $headers = [],
    ): array {
        $client = HttpClient::create([
            'base_uri' => $this->baseUrl($host),
            'timeout' => 120,
            'headers' => $headers,
        ]);

        $maxRetries = 3;
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                $response = $client->request('POST', $path, [
                    'query' => $query,
                    'json' => $body,
                ]);

                $status = $response->getStatusCode();
                $raw = $response->getContent(false);
                $decoded = json_decode($raw, true);

                // Retry on 5xx server errors
                if ($status >= 500 && $attempt < $maxRetries - 1) {
                    $attempt++;
                    sleep(pow(2, $attempt));
                    continue;
                }

                return [
                    'status' => $status,
                    'body' => is_array($decoded) ? $decoded : [],
                    'raw' => $raw,
                ];
            } catch (\Throwable $e) {
                $lastException = $e;
                $attempt++;
                if ($attempt < $maxRetries) {
                    sleep(pow(2, $attempt));
                    continue;
                }
                throw $e;
            }
        }

        throw $lastException ?? new \RuntimeException('Max retries exceeded');
    }

    /**
     * POST /v3.0/app/getaccesstoken?appId=X
     * Header: Authorization: <secretKey>, Content-Type: application/json
     * Body: {accessCode, verifyCode?}
     */
    public function getAccessToken(string $appId, string $accessCode, string $secretKey, string $verifyCode = ''): array
    {
        $body = [
            'accessCode' => $accessCode,
        ];
        if ($verifyCode !== '') {
            $body['verifyCode'] = $verifyCode;
        }

        return $this->post('pos', '/v3.0/app/getaccesstoken', [
            'appId' => $appId,
        ], $body, [
            'Content-Type' => 'application/json',
            'Authorization' => $secretKey,
        ]);
    }

    /**
     * POST /v3.0/openapi/checkaccesstoken?appId=X&businessId=Y
     * Header: Authorization: <accessToken>, Content-Type: application/json
     * Body: {secretKey} (optional)
     */
    public function checkAccessToken(string $appId, string $businessId, string $accessToken, string $secretKey = ''): array
    {
        $body = $secretKey !== '' ? ['secretKey' => $secretKey] : [];

        return $this->post('pos', '/v3.0/openapi/checkaccesstoken', [
            'appId' => $appId,
            'businessId' => $businessId,
        ], $body, [
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken,
        ]);
    }

    /**
     * POST to any v3.0 endpoint with proper Authorization header.
     *
     * @param array<string, mixed> $query  appId, businessId go here (URL params)
     * @param array<string, mixed> $body   filters, paginator, etc.
     *
     * @return array{status: int, body: array<string, mixed>, raw: string}
     */
    public function postWithAuth(
        string $host,
        string $path,
        string $accessToken,
        array $query = [],
        array $body = [],
    ): array {
        return $this->post($host, $path, $query, $body, [
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken,
        ]);
    }
}