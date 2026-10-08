<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Order\Storage;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\Ecommerce\Domain\Order\Contracts\OrderRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Storage\AbstractRepository;

final class OrderRepository extends AbstractRepository implements OrderRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);

        $query = $this->applyFilters(
            $this->db->select('*')->from($this->table('orders')),
            $businessId,
            $filters,
        );

        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();

        $total = $this->applyFilters(
            $this->db->select('*')->from($this->table('orders')),
            $businessId,
            $filters,
        )->count();

        return [
            'items' => $this->rows($items),
            'total' => $total,
        ];
    }

    public function find(string $businessId, string $externalId): ?array
    {
        $row = $this->db->select('*')->from($this->table('orders'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function findByCode(string $businessId, string $code): ?array
    {
        $row = $this->db->select('*')->from($this->table('orders'))
            ->where('business_id', $businessId)
            ->where('code', $code)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function findByChannelOrderId(string $businessId, string $channelOrderId): ?array
    {
        $row = $this->db->select('*')->from($this->table('orders'))
            ->where('business_id', $businessId)
            ->where('channel_order_id', $channelOrderId)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        if ($externalId === '') {
            $externalId = 'ord_' . bin2hex(random_bytes(8));
        }
        $now = time();

        // Generate order code if not provided
        $code = $data['code'] ?? 'ORD-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        $items = $data['items'] ?? [];
        $itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE);

        // Calculate totals from items if not provided
        $subtotal = $data['subtotal'] ?? array_sum(array_map(fn ($i) => $i['total_price'] ?? 0, $items));
        $taxTotal = $data['tax_total'] ?? array_sum(array_map(fn ($i) => $i['tax_amount'] ?? 0, $items));
        $discountTotal = $data['discount_total'] ?? array_sum(array_map(fn ($i) => $i['discount_amount'] ?? 0, $items));
        $shippingFee = $data['shipping_fee'] ?? 0;
        $total = $subtotal + $taxTotal - $discountTotal + $shippingFee;

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'code' => $code,
            'customer_id' => (string) ($data['customer_id'] ?? $data['customerId'] ?? ''),
            'customer_external_id' => (string) ($data['customer_external_id'] ?? $data['customerExternalId'] ?? ''),
            'status' => (string) ($data['status'] ?? 'draft'),
            'source' => (string) ($data['source'] ?? 'manual'),
            'channel_order_id' => $data['channel_order_id'] ?? $data['channelOrderId'] ?? null,
            'items' => $itemsJson,
            'subtotal' => (int) $subtotal,
            'tax_total' => (int) $taxTotal,
            'discount_total' => (int) $discountTotal,
            'shipping_fee' => (int) $shippingFee,
            'total' => (int) $total,
            'billing_address' => isset($data['billing_address']) ? json_encode($data['billing_address']) : null,
            'shipping_address' => isset($data['shipping_address']) ? json_encode($data['shipping_address']) : null,
            'customer_note' => $data['customer_note'] ?? $data['customerNote'] ?? null,
            'internal_note' => $data['internal_note'] ?? $data['internalNote'] ?? null,
            'metadata' => json_encode($data['metadata'] ?? []),
            'created_at' => (int) ($data['created_at'] ?? $data['createdAt'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $data['updatedAt'] ?? $now),
            'confirmed_at' => $data['confirmed_at'] ?? $data['confirmedAt'] ?? null,
            'shipped_at' => $data['shipped_at'] ?? $data['shippedAt'] ?? null,
            'delivered_at' => $data['delivered_at'] ?? $data['deliveredAt'] ?? null,
            'cancelled_at' => $data['cancelled_at'] ?? $data['cancelledAt'] ?? null,
            'cancelled_reason' => $data['cancelled_reason'] ?? $data['cancelledReason'] ?? null,
        ];

        $existing = $this->db->select('id')->from($this->table('orders'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($existing === false) {
            $result = $this->db->insert($this->table('orders'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $existing = (array) $existing;
            $id = $this->int($existing, 'id');
            $this->db->update($this->table('orders'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['items'] = $this->decodeJson($values['items']);
        $values['billing_address'] = $this->decodeJson($values['billing_address']);
        $values['shipping_address'] = $this->decodeJson($values['shipping_address']);
        $values['metadata'] = $this->decodeJson($values['metadata']);

        return $values;
    }

    public function updateStatus(string $businessId, string $externalId, string $status, array $additionalData = []): array
    {
        $order = $this->find($businessId, $externalId);
        if (!$order) {
            throw new \InvalidArgumentException("Order not found: $externalId");
        }

        $validTransitions = [
            'draft' => ['pending', 'cancelled'],
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['processing', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped' => ['delivered', 'returned'],
            'delivered' => ['returned', 'refunded'],
            'cancelled' => [],
            'returned' => ['refunded'],
            'refunded' => [],
        ];

        if (!in_array($status, $validTransitions[$order['status']] ?? [], true)) {
            throw new \InvalidArgumentException("Invalid status transition: {$order['status']} -> $status");
        }

        $values = [
            'status' => $status,
            'updated_at' => time(),
            ...$additionalData,
        ];

        // Set timestamps based on status
        $timestampFields = [
            'confirmed' => 'confirmed_at',
            'shipped' => 'shipped_at',
            'delivered' => 'delivered_at',
            'cancelled' => 'cancelled_at',
        ];

        if (isset($timestampFields[$status])) {
            $values[$timestampFields[$status]] = time();
        }

        $this->db->update($this->table('orders'), $values, ['id' => $order['id']])->run();

        return $this->find($businessId, $externalId) ?? [];
    }

    public function cancel(string $businessId, string $externalId, string $reason): array
    {
        return $this->updateStatus($businessId, $externalId, 'cancelled', [
            'cancelled_reason' => $reason,
        ]);
    }

    public function getCustomerOrders(string $businessId, string $customerExternalId, int $offset = 0, int $size = 20): array
    {
        $query = $this->db->select('*')->from($this->table('orders'))
            ->where('business_id', $businessId)
            ->where('customer_external_id', $customerExternalId)
            ->orderBy('created_at', 'DESC')
            ->offset($offset)
            ->limit($size);

        $items = $query->run()->fetchAll();
        $total = (clone $query)->count();

        return [
            'items' => $this->rows($items),
            'total' => $total,
        ];
    }

    private function applyFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);

        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['source']) && is_string($filters['source']) && $filters['source'] !== '') {
            $query->where('source', $filters['source']);
        }

        if (isset($filters['customer_external_id']) && is_string($filters['customer_external_id']) && $filters['customer_external_id'] !== '') {
            $query->where('customer_external_id', $filters['customer_external_id']);
        }

        if (isset($filters['date_from']) && is_numeric($filters['date_from'])) {
            $query->where('created_at', '>=', (int) $filters['date_from']);
        }

        if (isset($filters['date_to']) && is_numeric($filters['date_to'])) {
            $query->where('created_at', '<=', (int) $filters['date_to']);
        }

        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $keyword = $filters['keyword'];
            $query->where(fn (SelectQuery $q) => $q
                ->where('code', 'LIKE', '%' . $keyword . '%')
                ->where('external_id', 'LIKE', '%' . $keyword . '%')
                ->where('channel_order_id', 'LIKE', '%' . $keyword . '%'));
        }

        return $query;
    }

    private function ordering(array $sort): array
    {
        $column = 'created_at';
        $direction = 'DESC';

        foreach ($sort as $key => $value) {
            $key = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            if (!in_array($key, ['created_at', 'id', 'updated_at', 'code', 'total', 'status'], true)) {
                continue;
            }
            $column = $key;
            $direction = strtolower((string) $value) === 'asc' ? 'ASC' : 'DESC';
            break;
        }

        return [$column, $direction];
    }

    private function decodeJson(?string $value): array
    {
        if (!$value) {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}