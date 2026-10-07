<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Contracts;

interface RateLimitRepositoryInterface
{
    public function hit(string $key, string $url, int $windowStart): int;

    public function lockedUntil(string $key, string $url): int;

    public function lock(string $key, string $url, int $lockedUntil): void;
}
