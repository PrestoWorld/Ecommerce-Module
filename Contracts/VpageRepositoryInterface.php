<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Contracts;

interface VpageRepositoryInterface
{
    public function pages(string $businessId): array;

    public function savePage(string $businessId, array $data): void;

    public function conversations(string $businessId, array $filters, int $size, array $cursor = []): array;

    public function saveConversation(string $businessId, array $data): void;

    public function messages(string $businessId, string $conversationId, array $filters, int $size, array $cursor = []): array;

    public function saveMessage(string $businessId, array $data): void;
}
