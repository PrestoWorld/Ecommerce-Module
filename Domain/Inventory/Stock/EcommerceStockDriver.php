<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Inventory\Stock;

/**
 * Default stock driver: pw_stock_items balance + pw_stock_movements journal.
 */
final class EcommerceStockDriver extends AbstractStockDriver
{
    private const TYPE_LABELS = [
        'adjustment' => 'Điều chỉnh',
        'receipt' => 'Nhập kho',
        'count' => 'Kiểm kho',
        'reservation' => 'Giữ hàng',
        'release' => 'Nhả hàng',
        'transfer_in' => 'Chuyển đến',
        'transfer_out' => 'Chuyển đi',
    ];

    public function name(): string
    {
        return 'ecommerce';
    }

    public function movements(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset = ($page - 1) * $perPage;

        $result = $this->stockMovements->search($this->businessId, [
            'variant_option_external_id' => (string) ($filters['variantExternalId'] ?? ''),
            'type' => (string) ($filters['type'] ?? ''),
        ], $offset, $perPage, ['created_at' => 'desc']);

        $rows = [];
        foreach ($result['items'] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        $titles = $this->variantTitles(array_map(
            static fn (array $row): string => (string) ($row['variant_option_external_id'] ?? ''),
            $rows,
        ));

        $items = array_map(function (array $row) use ($titles): array {
            $variantExternalId = (string) ($row['variant_option_external_id'] ?? '');
            $delta = (int) ($row['quantity_change'] ?? 0);
            $type = (string) ($row['type'] ?? '');
            $meta = $titles[$variantExternalId] ?? ['sku' => '', 'title' => ''];

            return [
                'id' => (int) ($row['id'] ?? 0),
                'externalId' => (string) ($row['external_id'] ?? ''),
                'variantExternalId' => $variantExternalId,
                'sku' => $meta['sku'],
                'title' => $meta['title'],
                'type' => $type,
                'typeLabel' => self::TYPE_LABELS[$type] ?? $type,
                'direction' => $delta >= 0 ? 'in' : 'out',
                'quantity' => $delta,
                'before' => (int) ($row['quantity_before'] ?? 0),
                'after' => (int) ($row['quantity_after'] ?? 0),
                'reason' => (string) ($row['reason'] ?? ''),
                'referenceId' => $row['reference_id'] ?? null,
                'referenceType' => $row['reference_type'] ?? null,
                'createdAt' => (int) ($row['created_at'] ?? 0),
            ];
        }, $rows);

        return [
            'items' => $items,
            'total' => (int) ($result['total'] ?? 0),
            'page' => $page,
            'perPage' => $perPage,
            'driver' => $this->name(),
        ];
    }

    protected function recordJournal(array $event): void
    {
        $delta = (int) ($event['quantity_change'] ?? 0);
        if ($delta === 0) {
            return;
        }

        $this->stockMovements->save($this->businessId, [
            'stock_item_external_id' => (string) ($event['stock_item_external_id'] ?? ''),
            'variant_option_external_id' => (string) ($event['variant_external_id'] ?? ''),
            'location_id' => (string) ($event['location_id'] ?? ''),
            'type' => 'adjustment',
            'quantity_change' => $delta,
            'quantity_before' => (int) ($event['quantity_before'] ?? 0),
            'quantity_after' => (int) ($event['quantity_after'] ?? 0),
            'reason' => (string) ($event['reason'] ?? ''),
            'reference_id' => $event['reference_id'] ?? null,
            'reference_type' => $event['reference_type'] ?? null,
            'metadata' => [
                'sku' => (string) ($event['sku'] ?? ''),
                'title' => (string) ($event['title'] ?? ''),
            ],
            'created_at' => (int) ($event['created_at'] ?? time()),
        ]);
    }

    /**
     * @param list<string> $variantExternalIds
     * @return array<string, array{sku: string, title: string}>
     */
    private function variantTitles(array $variantExternalIds): array
    {
        $variantExternalIds = array_values(array_unique(array_filter(
            $variantExternalIds,
            static fn (string $id): bool => $id !== '',
        )));
        if ($variantExternalIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($variantExternalIds), '?'));
        $rows = $this->db->query(
            'SELECT v.external_id, v.sku, m.title'
            . ' FROM ' . $this->prefix . 'product_variants v'
            . ' LEFT JOIN ' . $this->prefix . 'product_masters m ON m.id = v.master_id'
            . ' WHERE v.business_id = ? AND v.external_id IN (' . $placeholders . ')',
            array_merge([$this->businessId], $variantExternalIds),
        )->fetchAll();

        $titles = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $titles[(string) ($row['external_id'] ?? '')] = [
                'sku' => (string) ($row['sku'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
            ];
        }

        return $titles;
    }
}
