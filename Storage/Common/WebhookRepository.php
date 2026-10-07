<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Storage\Common;

use PrestoWorld\Modules\Ecommerce\Contracts\WebhookRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Storage\AbstractRepository;

final class WebhookRepository extends AbstractRepository implements WebhookRepositoryInterface
{
    public function storeEvent(string $event, string $businessId, array $payload): int
    {
        $result = $this->db->insert($this->table('webhook_events'))->values([
            'event' => $event,
            'business_id' => $businessId,
            'payload' => $this->encode($payload),
            'received_at' => time(),
            'processed_at' => null,
            'attempts' => 0,
            'last_error' => null,
        ])->run();

        return (int) $result;
    }

    public function pendingEvents(int $limit): array
    {
        return $this->db->select('*')->from($this->table('webhook_events'))
            ->where('processed_at', null)
            ->orderBy('id', 'ASC')
            ->limit($limit)
            ->run()->fetchAll();
    }

    public function markEventProcessed(int $id, ?string $error = null): void
    {
        $row = $this->db->select('attempts')->from($this->table('webhook_events'))
            ->where('id', $id)
            ->run()->fetch();

        $attempts = $row === false ? 1 : $this->int($row, 'attempts') + 1;

        $this->db->update($this->table('webhook_events'), [
            'processed_at' => $error === null ? time() : null,
            'attempts' => $attempts,
            'last_error' => $error,
        ], ['id' => $id])->run();
    }

    public function enqueue(string $event, string $businessId, array $payload): int
    {
        $result = $this->db->insert($this->table('webhook_outbox'))->values([
            'event' => $event,
            'business_id' => $businessId,
            'payload' => $this->encode($payload),
            'status' => 'pending',
            'attempts' => 0,
            'last_error' => null,
            'created_at' => time(),
        ])->run();

        return (int) $result;
    }

    public function dueOutbox(int $limit): array
    {
        return $this->db->select('*')->from($this->table('webhook_outbox'))
            ->where('status', 'pending')
            ->orWhere(fn ($q) => $q->where('status', 'failed')->where('attempts', '<', 3))
            ->orderBy('id', 'ASC')
            ->limit($limit)
            ->run()->fetchAll();
    }

    public function markOutbox(int $id, string $status, ?string $error = null): void
    {
        $row = $this->db->select('attempts')->from($this->table('webhook_outbox'))
            ->where('id', $id)
            ->run()->fetch();

        $attempts = ($row === false ? 0 : $this->int($row, 'attempts')) + 1;

        $this->db->update($this->table('webhook_outbox'), [
            'status' => $status,
            'attempts' => $attempts,
            'last_error' => $error,
        ], ['id' => $id])->run();
    }
}