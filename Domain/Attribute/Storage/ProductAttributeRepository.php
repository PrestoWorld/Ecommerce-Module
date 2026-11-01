<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Attribute\Storage;

use PrestoWorld\Modules\Ecommerce\Domain\Attribute\Contracts\ProductAttributeRepositoryInterface;

final class ProductAttributeRepository extends AbstractRepository implements ProductAttributeRepositoryInterface
{
    private const TYPES = ['warehouse_link', 'condition', 'select', 'number', 'boolean', 'text'];

    public function search(string $businessId, array $filters = [], int $offset = 0, int $limit = 50): array
    {
        $offset = max(0, $offset);
        $limit = max(1, min(200, $limit));

        $query = $this->db->select('*')->from($this->table('product_attributes'))->where('business_id', $businessId);
        if (!empty($filters['code'])) {
            $query->where('code', (string) $filters['code']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', (string) $filters['type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        $count = clone $query;
        $items = $query->orderBy('sort_order', 'ASC')->orderBy('code', 'ASC')->offset($offset)->limit($limit)->run()->fetchAll();

        return ['items' => $this->rows($items), 'total' => (int) $count->count()];
    }

    public function all(string $businessId): array
    {
        $rows = $this->db->select('*')
            ->from($this->table('product_attributes'))
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('code', 'ASC')
            ->run()
            ->fetchAll();

        return $this->rows($rows);
    }

    public function find(string $businessId, string $code): ?array
    {
        $row = $this->db->select('*')
            ->from($this->table('product_attributes'))
            ->where('business_id', $businessId)
            ->where('code', $code)
            ->run()
            ->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function save(string $businessId, array $data): array
    {
        $code = strtolower(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            throw new \InvalidArgumentException('attribute code là bắt buộc.');
        }

        $type = (string) ($data['type'] ?? 'text');
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException(
                'type phải là một trong: ' . implode(', ', self::TYPES) . " (nhận: {$type})"
            );
        }

        $now = time();
        $values = [
            'business_id' => $businessId,
            'code' => $code,
            'name' => (string) ($data['name'] ?? $code),
            'type' => $type,
            'options' => isset($data['options']) && is_array($data['options'])
                ? $this->encode($data['options'])
                : ($data['options'] ?? null),
            'value_source' => $data['value_source'] ?? null,
            'applies_to' => (string) ($data['applies_to'] ?? 'product'),
            'is_combinable' => (bool) ($data['is_combinable'] ?? true),
            'is_required' => (bool) ($data['is_required'] ?? false),
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => (string) ($data['status'] ?? 'active'),
            'updated_at' => $now,
        ];

        $existing = $this->db->select('id')
            ->from($this->table('product_attributes'))
            ->where('business_id', $businessId)
            ->where('code', $code)
            ->run()
            ->fetch();

        if ($existing === false) {
            $id = $this->uuid();
            $this->db->insert($this->table('product_attributes'))
                ->values(['id' => $id, 'created_at' => $now] + $values)
                ->run();
            $values['id'] = $id;
            $values['created_at'] = $now;
        } else {
            $id = $this->strId($existing);
            $this->db->update($this->table('product_attributes'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['options'] = $this->decode(is_string($values['options'] ?? null) ? $values['options'] : null);

        return $values;
    }

    public function delete(string $businessId, string $code): bool
    {
        $result = $this->db->delete($this->table('product_attributes'))
            ->where('business_id', $businessId)
            ->where('code', $code)
            ->run();

        return $result > 0;
    }
}
