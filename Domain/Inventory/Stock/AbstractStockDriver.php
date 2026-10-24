<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Inventory\Stock;

use Cycle\Database\DatabaseInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\StockDriverInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\StockMovementRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\StockRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\WebhookRepositoryInterface;

/**
 * Template-method base for stock drivers.
 *
 * Owns the shared balance layer (pw_stock_items) and the variant mirror
 * (pw_product_variants.stock_*). Subclasses only decide where the journal
 * lives: subclass writes via recordJournal(), reads via movements().
 */
abstract class AbstractStockDriver implements StockDriverInterface
{
    public function __construct(
        protected DatabaseInterface $db,
        protected string $prefix,
        protected string $businessId,
        protected StockRepositoryInterface $stocks,
        protected StockMovementRepositoryInterface $stockMovements,
        protected WebhookRepositoryInterface $webhooks,
    ) {
    }

    /**
     * Persist a journal entry for a balance change.
     *
     * @param array<string, mixed> $event
     */
    abstract protected function recordJournal(array $event): void;

    public function materialize(string $variantExternalId): ?array
    {
        $existing = $this->stocks->findByVariantOption($this->businessId, $variantExternalId);
        if ($existing !== []) {
            return $existing[0];
        }

        $variant = $this->variantRow($variantExternalId);
        if ($variant === null) {
            return null;
        }

        $onHand = max(0, (int) ($variant['stock_on_hand'] ?? 0));
        $reserved = max(0, (int) ($variant['stock_reserved'] ?? 0));
        $available = max(0, $onHand - $reserved);
        $reorder = max(0, (int) ($variant['reorder_point'] ?? 0));

        return $this->stocks->save($this->businessId, [
            'external_id' => 'stock_' . bin2hex(random_bytes(8)),
            'variant_option_external_id' => $variantExternalId,
            'location_id' => '',
            'location_code' => 'default',
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
            'quantity_available' => $available,
            'reorder_point' => $reorder,
            'status' => $this->calculateStatus($available, $reorder),
        ]);
    }

    public function getStock(string $variantExternalId): array
    {
        $variant = $this->variantRow($variantExternalId);
        $locations = $this->stocks->findByVariantOption($this->businessId, $variantExternalId);

        if ($locations === []) {
            $onHand = max(0, (int) ($variant['stock_on_hand'] ?? 0));
            $reserved = max(0, (int) ($variant['stock_reserved'] ?? 0));
        } else {
            $aggregate = $this->stocks->getAggregateStock($this->businessId, $variantExternalId);
            $onHand = max(0, (int) ($aggregate['total_on_hand'] ?? 0));
            $reserved = max(0, (int) ($aggregate['total_reserved'] ?? 0));
        }

        $reorder = max(0, (int) ($variant['reorder_point'] ?? 0));
        $available = max(0, $onHand - $reserved);

        return [
            'driver' => $this->name(),
            'variantExternalId' => $variantExternalId,
            'masterExternalId' => $variant['master_external_id'] ?? null,
            'sku' => (string) ($variant['sku'] ?? ''),
            'title' => (string) ($variant['master_title'] ?? ''),
            'onHand' => $onHand,
            'reserved' => $reserved,
            'available' => $available,
            'reorderPoint' => $reorder,
            'status' => $this->calculateStatus($available, $reorder),
            'locations' => array_map(static fn (array $row): array => [
                'locationId' => (string) ($row['location_id'] ?? ''),
                'locationCode' => (string) ($row['location_code'] ?? ''),
                'onHand' => (int) ($row['quantity_on_hand'] ?? 0),
                'reserved' => (int) ($row['quantity_reserved'] ?? 0),
                'available' => (int) ($row['quantity_available'] ?? 0),
            ], $locations),
        ];
    }

    public function listItems(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->listConditions($filters);
        $select = $this->listSelect();

        $having = '';
        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['instock', 'lowstock', 'outofstock'], true)) {
            $having = ' HAVING stock_status = ?';
            $params[] = $status;
        }

        $sortMap = [
            'title' => 'm.title',
            'sku' => 'v.sku',
            'on_hand' => 'on_hand',
            'onhand' => 'on_hand',
            'available' => 'available',
            'updated' => 'm.updated_at',
        ];
        $sortKey = strtolower((string) ($filters['sort'] ?? 'title'));
        $sortColumn = $sortMap[$sortKey] ?? 'm.title';
        $direction = strtolower((string) ($filters['order'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $sql = $select . $where . ' GROUP BY v.id, m.id' . $having
            . ' ORDER BY ' . $sortColumn . ' ' . $direction
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $rows = $this->db->query($sql, $params)->fetchAll();

        $countSql = 'SELECT COUNT(*) AS total FROM (' . $select . $where . ' GROUP BY v.id, m.id' . $having . ') AS t';
        $countRow = $this->db->query($countSql, $params)->fetch();

        return [
            'items' => array_map(fn (array $row): array => $this->mapStockRow($row), is_array($rows) ? $rows : []),
            'total' => (int) (is_array($countRow) ? ($countRow['total'] ?? 0) : 0),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public function adjust(string $variantExternalId, int $quantity, string $operation, string $reason, ?string $referenceId = null): array
    {
        $item = $this->materialize($variantExternalId);
        if ($item === null) {
            throw new \InvalidArgumentException('Không tìm thấy biến thể sản phẩm: ' . $variantExternalId);
        }

        $current = max(0, (int) ($item['quantity_on_hand'] ?? 0));
        $reserved = max(0, (int) ($item['quantity_reserved'] ?? 0));
        $amount = abs($quantity);

        $newOnHand = match ($operation) {
            'add' => $current + $amount,
            'subtract' => max(0, $current - $amount),
            'set' => max(0, $quantity),
            default => throw new \InvalidArgumentException('Thao tác không hợp lệ: ' . $operation),
        };

        if ($newOnHand === $current) {
            return $this->getStock($variantExternalId);
        }

        return $this->writeBalance($item, $newOnHand, $reserved, $reason, 'manual', $referenceId);
    }

    public function syncVariant(string $variantExternalId, int $onHand, int $reserved, string $reason): array
    {
        $item = $this->materialize($variantExternalId);
        if ($item === null) {
            return [];
        }

        $before = (int) ($item['quantity_on_hand'] ?? 0);
        $beforeReserved = (int) ($item['quantity_reserved'] ?? 0);
        if ($before === max(0, $onHand) && $beforeReserved === max(0, $reserved)) {
            return $this->getStock($variantExternalId);
        }

        return $this->writeBalance($item, $onHand, $reserved, $reason, 'product_edit');
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function writeBalance(
        array $item,
        int $onHand,
        int $reserved,
        string $reason,
        string $referenceType,
        ?string $referenceId = null,
    ): array {
        $variantExternalId = (string) ($item['variant_option_external_id'] ?? '');
        $before = (int) ($item['quantity_on_hand'] ?? 0);
        $beforeReserved = (int) ($item['quantity_reserved'] ?? 0);
        $stockItemId = (int) ($item['id'] ?? 0);

        $onHand = max(0, $onHand);
        $reserved = max(0, $reserved);
        $available = max(0, $onHand - $reserved);
        $reorder = max(0, (int) ($item['reorder_point'] ?? 0));
        $now = time();

        $this->db->update($this->prefix . 'stock_items', [
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
            'quantity_available' => $available,
            'status' => $this->calculateStatus($available, $reorder),
            'updated_at' => $now,
        ], ['id' => $stockItemId])->run();

        $this->db->update($this->prefix . 'product_variants', [
            'stock_on_hand' => $onHand,
            'stock_reserved' => $reserved,
            'stock_available' => $available,
            'updated_at' => $now,
        ], [
            'business_id' => $this->businessId,
            'external_id' => $variantExternalId,
        ])->run();

        $delta = $onHand - $before;
        if ($delta === 0 && $reserved === $beforeReserved) {
            return $this->getStock($variantExternalId);
        }

        $variant = $this->variantRow($variantExternalId);
        $this->recordJournal([
            'variant_external_id' => $variantExternalId,
            'stock_item_external_id' => (string) ($item['external_id'] ?? ''),
            'location_id' => (string) ($item['location_id'] ?? ''),
            'sku' => (string) ($variant['sku'] ?? ''),
            'title' => (string) ($variant['master_title'] ?? ''),
            'quantity_change' => $delta,
            'quantity_before' => $before,
            'quantity_after' => $onHand,
            'reason' => $reason,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'created_at' => $now,
        ]);

        $this->webhooks->enqueue(
            'stockChange',
            $this->businessId,
            [
                'variantExternalId' => $variantExternalId,
                'sku' => (string) ($variant['sku'] ?? ''),
                'title' => (string) ($variant['master_title'] ?? ''),
                'onHand' => $onHand,
                'reserved' => $reserved,
                'available' => $available,
                'delta' => $delta,
                'before' => $before,
                'after' => $onHand,
                'reason' => $reason,
                'referenceType' => $referenceType,
                'referenceId' => $referenceId,
                'createdAt' => $now,
            ],
        );

        return $this->getStock($variantExternalId);
    }

    protected function calculateStatus(int $available, int $reorderPoint): string
    {
        if ($available <= 0) {
            return 'out_of_stock';
        }
        if ($reorderPoint > 0 && $available <= $reorderPoint) {
            return 'low_stock';
        }

        return 'active';
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function variantRow(string $variantExternalId): ?array
    {
        $row = $this->db->query(
            'SELECT v.*, m.external_id AS master_external_id, m.title AS master_title, m.status AS master_status'
            . ' FROM ' . $this->prefix . 'product_variants v'
            . ' LEFT JOIN ' . $this->prefix . 'product_masters m ON m.id = v.master_id'
            . ' WHERE v.business_id = ? AND v.external_id = ?'
            . ' LIMIT 1',
            [$this->businessId, $variantExternalId],
        )->fetch();

        return is_array($row) ? $row : null;
    }

    private function listSelect(): string
    {
        $onHand = 'COALESCE(SUM(si.quantity_on_hand), v.stock_on_hand)';
        $reserved = 'COALESCE(SUM(si.quantity_reserved), v.stock_reserved)';
        $available = 'GREATEST(' . $onHand . ' - ' . $reserved . ', 0)';
        $reorder = 'COALESCE(MAX(si.reorder_point), v.reorder_point)';

        return 'SELECT v.external_id AS variant_external_id, v.sku, v.barcode,'
            . ' m.external_id AS master_external_id, m.title, m.cover_image, m.status AS master_status,'
            . ' ' . $onHand . ' AS on_hand,'
            . ' ' . $reserved . ' AS reserved,'
            . ' ' . $available . ' AS available,'
            . ' ' . $reorder . ' AS reorder_point,'
            . ' COUNT(si.id) AS location_count,'
            . ' CASE'
            . ' WHEN ' . $available . ' <= 0 THEN \'outofstock\''
            . ' WHEN ' . $reorder . ' > 0 AND ' . $available . ' <= ' . $reorder . ' THEN \'lowstock\''
            . ' ELSE \'instock\''
            . ' END AS stock_status'
            . ' FROM ' . $this->prefix . 'product_variants v'
            . ' INNER JOIN ' . $this->prefix . 'product_masters m ON m.id = v.master_id'
            . ' LEFT JOIN ' . $this->prefix . 'stock_items si ON si.business_id = v.business_id'
            . ' AND si.variant_option_external_id = v.external_id'
            . ' WHERE v.business_id = ?'
            . ' AND (m.status IS NULL OR m.status <> \'archived\')';
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private function listConditions(array $filters): array
    {
        $where = '';
        $params = [$this->businessId];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where .= ' AND (m.title LIKE ? OR v.sku LIKE ?)';
            $params[] = $like;
            $params[] = $like;
        }

        return [$where, $params];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapStockRow(array $row): array
    {
        return [
            'variantExternalId' => (string) ($row['variant_external_id'] ?? ''),
            'masterExternalId' => (string) ($row['master_external_id'] ?? ''),
            'sku' => (string) ($row['sku'] ?? ''),
            'barcode' => $row['barcode'] ?? null,
            'title' => (string) ($row['title'] ?? ''),
            'coverImage' => $row['cover_image'] ?? null,
            'masterStatus' => (string) ($row['master_status'] ?? ''),
            'onHand' => (int) ($row['on_hand'] ?? 0),
            'reserved' => (int) ($row['reserved'] ?? 0),
            'available' => (int) ($row['available'] ?? 0),
            'reorderPoint' => (int) ($row['reorder_point'] ?? 0),
            'locationCount' => (int) ($row['location_count'] ?? 0),
            'status' => (string) ($row['stock_status'] ?? 'instock'),
        ];
    }
}
