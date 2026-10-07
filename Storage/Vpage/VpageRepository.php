<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Storage\Vpage;

use PrestoWorld\Modules\Ecommerce\Contracts\VpageRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Storage\AbstractRepository;
use Cycle\Database\Query\SelectQuery;

final class VpageRepository extends AbstractRepository implements VpageRepositoryInterface
{
    public function pages(string $businessId): array
    {
        $rows = $this->db->select('*')->from($this->table('pages'))
            ->where('business_id', $businessId)
            ->orderBy('id', 'ASC')
            ->run()->fetchAll();

        return array_map(fn (mixed $row) => $this->decoded($row), $rows);
    }

    public function savePage(string $businessId, array $data): void
    {
        $externalId = (string) ($data['external_id'] ?? $data['id']);
        $now = time();

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'name' => (string) ($data['name'] ?? ''),
            'channel' => (string) ($data['channel'] ?? ''),
            'payload' => $this->encode($data['payload'] ?? $data),
            'updated_at' => (int) ($data['updated_at'] ?? $now),
        ];

        $existing = $this->db->select('id')->from($this->table('pages'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($existing === false) {
            $values['created_at'] = $now;
            $this->db->insert($this->table('pages'))->values($values)->run();
        } else {
            $existing = (array) $existing;
            $this->db->update($this->table('pages'), $values, ['id' => $this->int($existing, 'id')])->run();
        }
    }

    public function conversations(string $businessId, array $filters, int $size, array $cursor = []): array
    {
        $query = $this->db->select('*')->from($this->table('conversations'))
            ->where('business_id', $businessId)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit($size);

        $this->applyCursor($query, $cursor);
        $this->applyConversationFilters($query, $filters);

        return array_map(fn (mixed $row) => $this->decoded($row), $query->run()->fetchAll());
    }

    public function saveConversation(string $businessId, array $data): void
    {
        $externalId = (string) ($data['external_id'] ?? $data['id']);
        $now = time();

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'channel' => (string) ($data['channel'] ?? ''),
            'page_id' => (string) ($data['page_id'] ?? $data['pageId'] ?? ''),
            'customer_name' => (string) ($data['customer_name'] ?? ''),
            'customer_id' => (string) ($data['customer_id'] ?? ''),
            'last_message_at' => $this->ts($data['last_message_at'] ?? $data['lastMessageAt'] ?? null),
            'payload' => $this->encode($data['payload'] ?? $data),
            'created_at' => $this->ts($data['created_at'] ?? $data['createdAt'] ?? $data['timestamp'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $now),
        ];

        $existing = $this->db->select('id')->from($this->table('conversations'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($existing === false) {
            $this->db->insert($this->table('conversations'))->values($values)->run();
        } else {
            $existing = (array) $existing;
            $this->db->update($this->table('conversations'), $values, ['id' => $this->int($existing, 'id')])->run();
        }
    }

    public function messages(string $businessId, string $conversationId, array $filters, int $size, array $cursor = []): array
    {
        $query = $this->db->select('*')->from($this->table('messages'))
            ->where('business_id', $businessId)
            ->where('conversation_external_id', $conversationId)
            ->orderBy('sent_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit($size);

        $this->applyCursor($query, $cursor, 'sent_at');

        if (isset($filters['type']) && is_string($filters['type']) && $filters['type'] !== '') {
            $query->where('message_type', $filters['type']);
        }

        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $query->where('content', 'LIKE', '%' . $filters['keyword'] . '%');
        }

        return array_map(fn (mixed $row) => $this->decoded($row), $query->run()->fetchAll());
    }

    public function saveMessage(string $businessId, array $data): void
    {
        $conversationId = (string) ($data['conversation_external_id'] ?? $data['conversationId'] ?? '');
        $externalId = (string) ($data['external_id'] ?? $data['id']);

        $values = [
            'business_id' => $businessId,
            'conversation_external_id' => $conversationId,
            'external_id' => $externalId,
            'direction' => (string) ($data['direction'] ?? ''),
            'message_type' => (string) ($data['message_type'] ?? 'text'),
            'content' => (string) ($data['content'] ?? ''),
            'sent_at' => $this->ts($data['sent_at'] ?? $data['sentAt'] ?? $data['timestamp'] ?? null),
            'payload' => $this->encode($data['payload'] ?? $data),
        ];

        $existing = $this->db->select('id')->from($this->table('messages'))
            ->where('business_id', $businessId)
            ->where('conversation_external_id', $conversationId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($existing === false) {
            $this->db->insert($this->table('messages'))->values($values)->run();
        } else {
            $existing = (array) $existing;
            $this->db->update($this->table('messages'), $values, ['id' => $this->int($existing, 'id')])->run();
        }
    }

    private function applyConversationFilters(SelectQuery $query, array $filters): void
    {
        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $query->where('customer_name', 'LIKE', '%' . $filters['keyword'] . '%');
        }

        if (isset($filters['pageId']) && is_string($filters['pageId']) && $filters['pageId'] !== '') {
            $query->where('page_id', $filters['pageId']);
        }
    }

    private function applyCursor(SelectQuery $query, array $cursor, string $column = 'created_at'): void
    {
        if (count($cursor) < 2) {
            return;
        }

        $ts = (int) $cursor[0];
        $id = (int) $cursor[1];

        $query->where(fn (SelectQuery $q) => $q
            ->where($column, '<', $ts)
            ->orWhere(fn (SelectQuery $q2) => $q2
                ->where($column, $ts)
                ->where('id', '<', $id)));
    }
}