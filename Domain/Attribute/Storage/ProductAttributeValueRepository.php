<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Attribute\Storage;

use PrestoWorld\Modules\Ecommerce\Domain\Attribute\Contracts\ProductAttributeValueRepositoryInterface;

final class ProductAttributeValueRepository extends AbstractRepository implements ProductAttributeValueRepositoryInterface
{
    public function search(string $businessId, array $filters = [], int $offset = 0, int $limit = 50): array
    {
        $offset = max(0, $offset);
        $limit = max(1, min(200, $limit));

        $query = $this->db->select('*')->from($this->table('product_attribute_values'))->where('business_id', $businessId);
        if (!empty($filters['attribute_code'])) {
            $query->where('attribute_code', (string) $filters['attribute_code']);
        }
        if (!empty($filters['product_external_id'])) {
            $query->where('product_external_id', (string) $filters['product_external_id']);
        }
        if (isset($filters['warehouse_code'])) {
            $query->where('warehouse_code', $this->scopeValue($filters['warehouse_code']));
        }

        $count = clone $query;
        $items = $query->orderBy('created_at', 'DESC')->offset($offset)->limit($limit)->run()->fetchAll();

        return ['items' => $this->rows($items), 'total' => (int) $count->count()];
    }

    public function findByProduct(string $businessId, string $productExternalId): array
    {
        $rows = $this->db->select('*')
            ->from($this->table('product_attribute_values'))
            ->where('business_id', $businessId)
            ->where('product_external_id', $productExternalId)
            ->orderBy('created_at', 'ASC')
            ->run()
            ->fetchAll();

        return $this->rows($rows);
    }

    public function save(string $businessId, array $data): array
    {
        $attributeCode = strtolower(trim((string) ($data['attribute_code'] ?? '')));
        $productExternalId = trim((string) ($data['product_external_id'] ?? ''));
        if ($attributeCode === '' || $productExternalId === '') {
            throw new \InvalidArgumentException('attribute_code và product_external_id là bắt buộc.');
        }

        $variantExternalId = $this->scopeValue($data['variant_external_id'] ?? '');
        $optionExternalId = $this->scopeValue($data['option_external_id'] ?? '');
        $warehouseCode = $this->scopeValue($data['warehouse_code'] ?? '');
        $storeCode = $this->scopeValue($data['store_code'] ?? '');

        // Attribute warehouse_link không truyền warehouse_code tường minh →
        // lấy từ value (value chính là mã kho).
        $value = $data['value'] ?? null;
        if ($warehouseCode === '' && is_string($value) && trim($value) !== ''
            && $this->looksLikeWarehouseContext($data)) {
            $warehouseCode = trim($value);
        }

        $now = time();
        $values = [
            'business_id' => $businessId,
            'attribute_code' => $attributeCode,
            'product_external_id' => $productExternalId,
            'variant_external_id' => $variantExternalId,
            'option_external_id' => $optionExternalId,
            'warehouse_code' => $warehouseCode,
            'store_code' => $storeCode,
            'value' => is_array($value) ? $this->encode($value) : ($value !== null && $value !== '' ? (string) $value : null),
            'value_percent' => isset($data['value_percent']) && $data['value_percent'] !== null && $data['value_percent'] !== ''
                ? (int) $data['value_percent']
                : null,
            'updated_at' => $now,
        ];

        $existing = $this->db->select('id')
            ->from($this->table('product_attribute_values'))
            ->where('business_id', $businessId)
            ->where('attribute_code', $attributeCode)
            ->where('product_external_id', $productExternalId)
            ->where('variant_external_id', $variantExternalId)
            ->where('option_external_id', $optionExternalId)
            ->where('warehouse_code', $warehouseCode)
            ->run()
            ->fetch();

        if ($existing === false) {
            $id = $this->uuid();
            $this->db->insert($this->table('product_attribute_values'))
                ->values(['id' => $id, 'created_at' => $now] + $values)
                ->run();
            $values['id'] = $id;
            $values['created_at'] = $now;
        } else {
            $id = $this->strId($existing);
            $this->db->update($this->table('product_attribute_values'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        return $values;
    }

    public function delete(string $businessId, string $id): bool
    {
        $result = $this->db->delete($this->table('product_attribute_values'))
            ->where('business_id', $businessId)
            ->where('id', $id)
            ->run();

        return $result > 0;
    }

    private function looksLikeWarehouseContext(array $data): bool
    {
        if ($this->scopeValue($data['warehouse_code'] ?? '') !== '') {
            return true;
        }

        $declaredType = strtolower(trim((string) ($data['attribute_type'] ?? '')));

        return $declaredType === 'warehouse_link';
    }
}
