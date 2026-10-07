<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Services;

use Symfony\Component\HttpClient\HttpClient;
use Witals\Framework\Application;

final class NhanhClient
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
            'timeout' => 30,
            'headers' => $headers,
        ]);

        $response = $client->request('POST', $path, [
            'query' => $query,
            'json' => $body,
        ]);

        $status = $response->getStatusCode();
        $raw = $response->getContent(false);
        $decoded = json_decode($raw, true);

        return [
            'status' => $status,
            'body' => is_array($decoded) ? $decoded : [],
            'raw' => $raw,
        ];
    }

    public function getAccessToken(string $appId, string $secretKey): array
    {
        return $this->post('pos', '/v3.0/openapi/getaccesstoken', [
            'appId' => $appId,
        ], [
            'appSecret' => $secretKey,
        ]);
    }

    public function checkAccessToken(string $appId, string $businessId, string $accessToken): array
    {
        return $this->post('pos', '/v3.0/openapi/checkaccesstoken', [
            'appId' => $appId,
            'businessId' => $businessId,
            'accessToken' => $accessToken,
        ]);
    }
}