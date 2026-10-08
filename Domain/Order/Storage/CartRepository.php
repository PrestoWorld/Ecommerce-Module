<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Order\Storage;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\Ecommerce\Domain\Order\Contracts\CartRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Storage\AbstractRepository;

final class CartRepository extends AbstractRepository implements CartRepositoryInterface
{
    public function find(string $businessId, string $customerId): ?array
    {
        $row = $this->db->select('*')->from($this->table('carts'))
            ->where('business_id', $businessId)
            ->where('customer_external_id', $customerId)
            ->run()->fetch();

        if ($row === false) {
            return null;
        }

        $row = (array) $row;
        $row['items'] = $this->decodeJson($row['items'] ?? '[]');
        $row['metadata'] = $this->decodeJson($row['metadata'] ?? '{}');

        return $row;
    }

    public function save(string $businessId, array $data): array
    {
        $customerId = (string) ($data['customer_external_id'] ?? $data['customerId'] ?? '');
        if ($customerId === '') {
            throw new \InvalidArgumentException('customer_external_id is required');
        }
        $now = time();

        $items = $data['items'] ?? [];
        $itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE);

        $values = [
            'business_id' => $businessId,
            'customer_external_id' => $customerId,
            'items' => $itemsJson,
            'metadata' => json_encode($data['metadata'] ?? []),
            'updated_at' => $now,
            'expires_at' => $data['expires_at'] ?? ($now + 86400 * 30), // 30 days
        ];

        $existing = $this->db->select('id')->from($this->table('carts'))
            ->where('business_id', $businessId)
            ->where('customer_external_id', $customerId)
            ->run()->fetch();

        if ($existing === false) {
            $values['created_at'] = $now;
            $result = $this->db->insert($this->table('carts'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $existing = (array) $existing;
            $id = $this->int($existing, 'id');
            $this->db->update($this->table('carts'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['items'] = $this->decodeJson($values['items']);
        $values['metadata'] = $this->decodeJson($values['metadata']);

        return $values;
    }

    public function addItem(string $businessId, string $customerId, array $itemData): array
    {
        $cart = $this->find($businessId, $customerId);
        if (!$cart) {
            $cart = ['items' => [], 'metadata' => []];
        }

        $itemKey = $itemData['item_key'] ?? $itemData['variant_option_external_id'] ?? $itemData['variant_external_id'] ?? uniqid('item_');
        $itemData['item_key'] = $itemKey;
        $itemData['added_at'] = time();

        $items = $cart['items'] ?? [];
        $found = false;
        foreach ($items as &$item) {
            if ($item['item_key'] === $itemKey) {
                $item['quantity'] = ($item['quantity'] ?? 1) + ($itemData['quantity'] ?? 1);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $items[] = $itemData;
        }

        return $this->save($businessId, ['customer_external_id' => $customerId, 'items' => $items, 'metadata' => $cart['metadata']]);
    }

    public function updateItem(string $businessId, string $customerId, string $itemKey, array $itemData): array
    {
        $cart = $this->find($businessId, $customerId);
        if (!$cart) {
            throw new \InvalidArgumentException('Cart not found');
        }

        $items = $cart['items'] ?? [];
        foreach ($items as &$item) {
            if ($item['item_key'] === $itemKey) {
                $item = array_merge($item, $itemData);
                $item['item_key'] = $itemKey;
                break;
            }
        }

        return $this->save($businessId, ['customer_external_id' => $customerId, 'items' => $items, 'metadata' => $cart['metadata']]);
    }

    public function removeItem(string $businessId, string $customerId, string $itemKey): array
    {
        $cart = $this->find($businessId, $customerId);
        if (!$cart) {
            throw new \InvalidArgumentException('Cart not found');
        }

        $items = array_filter($cart['items'] ?? [], fn ($item) => $item['item_key'] !== $itemKey);

        return $this->save($businessId, ['customer_external_id' => $customerId, 'items' => array_values($items), 'metadata' => $cart['metadata']]);
    }

    public function clear(string $businessId, string $customerId): bool
    {
        $result = $this->db->delete($this->table('carts'))
            ->where('business_id', $businessId)
            ->where('customer_external_id', $customerId)
            ->run();

        return $result > 0;
    }
}