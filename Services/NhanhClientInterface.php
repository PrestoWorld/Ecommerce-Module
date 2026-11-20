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

    public function getAccessToken(string $appId, string $accessCode, string $secretKey, string $verifyCode = ''): array;

    public function checkAccessToken(string $appId, string $businessId, string $accessToken, string $secretKey = ''): array;

    /**
     * POST to any v3.0 endpoint with Authorization header.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     *
     * @return array{status: int, body: array<string, mixed>, raw: string}
     */
    public function postWithAuth(string $host, string $path, string $accessToken, array $query = [], array $body = []): array;
}
