<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Contracts;

interface WebhookRepositoryInterface
{
    public function storeEvent(string $event, string $businessId, array $payload): int;

    public function pendingEvents(int $limit): array;

    public function markEventProcessed(int $id, ?string $error = null): void;

    public function enqueue(string $event, string $businessId, array $payload): int;

    public function dueOutbox(int $limit): array;

    public function markOutbox(int $id, string $status, ?string $error = null): void;
}
