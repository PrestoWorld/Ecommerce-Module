<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Services;

/**
 * Contract for calling Nhanh.vn Open API endpoints.
 */
interface NhanhClientInterface
{
    public function baseUrl(string $host): string;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $headers
     *
     * @return array{status: int, body: array<string, mixed>, raw: string}
     */
    public function post(string $host, string $path, array $query = [], array $body = [], array $headers = []): array;

    public function getAccessToken(string $appId, string $accessCode, string $secretKey): array;

    public function checkAccessToken(string $appId, string $businessId, string $accessToken): array;
}
