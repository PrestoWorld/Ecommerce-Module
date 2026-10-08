<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Order\Storage;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\Ecommerce\Domain\Order\Contracts\QuoteRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Storage\AbstractRepository;

final class QuoteRepository extends AbstractRepository implements QuoteRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);

        $query = $this->applyFilters(
            $this->db->select('*')->from($this->table('quotes')),
            $businessId,
            $filters,
        );

        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();

        $total = $this->applyFilters(
            $this->db->select('*')->from($this->table('quotes')),
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
        $row = $this->db->select('*')->from($this->table('quotes'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        if ($externalId === '') {
            $externalId = 'quot_' . bin2hex(random_bytes(8));
        }
        $now = time();

        $code = $data['code'] ?? 'QUOT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        $items = $data['items'] ?? [];
        $itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE);

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
            'items' => $itemsJson,
            'subtotal' => (int) $subtotal,
            'tax_total' => (int) $taxTotal,
            'discount_total' => (int) $discountTotal,
            'shipping_fee' => (int) $shippingFee,
            'total' => (int) $total,
            'valid_until' => $data['valid_until'] ?? $data['validUntil'] ?? ($now + 86400 * 30),
            'notes' => $data['notes'] ?? null,
            'metadata' => json_encode($data['metadata'] ?? []),
            'created_at' => (int) ($data['created_at'] ?? $data['createdAt'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $data['updatedAt'] ?? $now),
            'converted_at' => $data['converted_at'] ?? $data['convertedAt'] ?? null,
            'converted_order_id' => $data['converted_order_id'] ?? $data['convertedOrderId'] ?? null,
        ];

        $existing = $this->db->select('id')->from($this->table('quotes'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($existing === false) {
            $result = $this->db->insert($this->table('quotes'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $existing = (array) $existing;
            $id = $this->int($existing, 'id');
            $this->db->update($this->table('quotes'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['items'] = $this->decodeJson($values['items']);
        $values['metadata'] = $this->decodeJson($values['metadata']);

        return $values;
    }

    public function convertToOrder(string $businessId, string $quoteExternalId, array $orderData): array
    {
        $quote = $this->find($businessId, $quoteExternalId);
        if (!$quote) {
            throw new \InvalidArgumentException("Quote not found: $quoteExternalId");
        }

        if ($quote['status'] !== 'accepted' && $quote['status'] !== 'draft') {
            throw new \InvalidArgumentException("Quote cannot be converted: status is {$quote['status']}");
        }

        $orderData = array_merge([
            'external_id' => 'ord_' . bin2hex(random_bytes(8)),
            'customer_id' => $quote['customer_id'],
            'customer_external_id' => $quote['customer_external_id'],
            'status' => 'draft',
            'source' => 'quote',
            'items' => $quote['items'],
            'subtotal' => $quote['subtotal'],
            'tax_total' => $quote['tax_total'],
            'discount_total' => $quote['discount_total'],
            'shipping_fee' => $quote['shipping_fee'],
            'total' => $quote['total'],
            'metadata' => array_merge($quote['metadata'] ?? [], ['quote_id' => $quote['external_id']]),
        ], $orderData);

        // This would typically use OrderRepository to save
        // For now, return the merged data
        $this->db->update($this->table('quotes'), [
            'status' => 'converted',
            'converted_at' => time(),
            'converted_order_id' => $orderData['external_id'],
        ], ['id' => $quote['id']])->run();

        return $orderData;
    }

    private function applyFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);

        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['customer_external_id']) && is_string($filters['customer_external_id']) && $filters['customer_external_id'] !== '') {
            $query->where('customer_external_id', $filters['customer_external_id']);
        }

        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $keyword = $filters['keyword'];
            $query->where(fn (SelectQuery $q) => $q
                ->where('code', 'LIKE', '%' . $keyword . '%')
                ->where('external_id', 'LIKE', '%' . $keyword . '%'));
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