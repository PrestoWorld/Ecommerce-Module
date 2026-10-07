<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Contracts;

interface AuthRepositoryInterface
{
    public function findApplication(string $appId): ?array;

    public function findToken(string $appId, string $businessId, string $token): ?array;

    public function saveToken(string $appId, string $businessId, string $token, int $expiresAt): void;

    public function touchToken(string $appId, string $businessId, int $now): void;

    public function saveApplication(string $appId, string $secretKey, string $name): void;
}
