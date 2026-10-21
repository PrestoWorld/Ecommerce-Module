<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Inventory\Contracts;

/**
 * Strategy port for stock management backends.
 *
 * Implementations:
 *   - EcommerceStockDriver (default): pw_stock_items balance + pw_stock_movements journal
 *   - InventoryStockDriver (modules/Inventory): pw_stock_items balance + professional
 *     pw_inventory_movements documents as the journal
 *
 * Invariant (both drivers): quantity_on_hand = opening balance + Σ(movement deltas).
 * The journal is the cause, the balance is the result.
 */
interface StockDriverInterface
{
    /** Driver identifier, e.g. "ecommerce" or "inventory". */
    public function name(): string;

    /**
     * Ensure a balance row exists for the variant, seeded from the current
     * variant values (opening balance, no journal entry). Returns null when
     * the variant does not exist.
     *
     * @return array<string, mixed>|null
     */
    public function materialize(string $variantExternalId): ?array;

    /**
     * Full stock state for one variant (aggregate across locations).
     *
     * @return array<string, mixed>
     */
    public function getStock(string $variantExternalId): array;

    /**
     * Paginated stock list joined with product catalog data.
     *
     * @param array<string, mixed> $filters q, status (instock|lowstock|outofstock), sort, order
     * @return array{items: list<array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function listItems(array $filters, int $page, int $perPage): array;

    /**
     * Manual adjustment of the on-hand quantity.
     *
     * @param string $operation add|subtract|set
     * @return array<string, mixed> refreshed stock state
     */
    public function adjust(string $variantExternalId, int $quantity, string $operation, string $reason, ?string $referenceId = null): array;

    /**
     * Force the balance to match the given variant values (called by the
     * products screen) and journal the difference.
     *
     * @return array<string, mixed> refreshed stock state
     */
    public function syncVariant(string $variantExternalId, int $onHand, int $reserved, string $reason): array;

    /**
     * Paginated movement journal entries.
     *
     * @param array<string, mixed> $filters variantExternalId, type, q, page...
     * @return array{items: list<array<string, mixed>>, total: int, page: int, perPage: int, driver: string}
     */
    public function movements(array $filters, int $page, int $perPage): array;
}
