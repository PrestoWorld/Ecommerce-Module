<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Storage\Affiliate;

use PrestoWorld\Modules\Ecommerce\Contracts\AffiliateRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Storage\AbstractRepository;
use Cycle\Database\Query\SelectQuery;

final class AffiliateRepository extends AbstractRepository implements AffiliateRepositoryInterface
{
    private const PRODUCT_SORTABLE = [
        'commissionRate' => ['commission_max', 'DESC'],
        'productSalesPrice' => ['price_max', 'DESC'],
        'commission' => ['commission_max', 'DESC'],
        'createdAt' => ['created_at', 'DESC'],
        'id' => ['id', 'DESC'],
    ];

    public function searchProducts(array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->productOrdering($sort);

        $query = $this->applyProductFilters(
            $this->db->select('*')->from($this->table('affiliate_products')),
            $filters,
        );

        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();

        $total = $this->applyProductFilters(
            $this->db->select('*')->from($this->table('affiliate_products')),
            $filters,
        )->count();

        return [
            'items' => array_map(fn (mixed $row) => $this->payloadOrCamel($row), $items),
            'total' => $total,
        ];
    }

    public function findProduct(string $channel, string $externalId): ?array
    {
        $row = $this->db->select('*')->from($this->table('affiliate_products'))
            ->where('channel', $channel)
            ->where('external_id', $externalId)
            ->run()->fetch();

        return $row === false ? null : $this->payloadOrCamel($row);
    }

    public function saveProduct(array $data): void
    {
        $channel = (string) ($data['channel'] ?? '');
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        if ($channel === '' || $externalId === '') {
            return;
        }

        $now = time();
        $values = [
            'channel' => $channel,
            'external_id' => $externalId,
            'name' => (string) ($data['name'] ?? ''),
            'image' => isset($data['image']) ? (string) $data['image'] : null,
            'price_min' => (int) ($data['price_min'] ?? $data['priceMin'] ?? 0),
            'price_max' => (int) ($data['price_max'] ?? $data['priceMax'] ?? 0),
            'commission_min' => (int) ($data['commission_min'] ?? $data['commissionMin'] ?? 0),
            'commission_max' => (int) ($data['commission_max'] ?? $data['commissionMax'] ?? 0),
            'shop_name' => (string) ($data['shop_name'] ?? $data['shopName'] ?? ''),
            'link_detail' => isset($data['link_detail']) ? (string) $data['link_detail'] : null,
            'deep_link' => isset($data['deep_link']) ? (string) $data['deep_link'] : null,
            'one_link' => isset($data['one_link']) ? (string) $data['one_link'] : null,
            'payload' => $this->encode($data['payload'] ?? $data),
            'created_at' => (int) ($data['created_at'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $now),
        ];

        $existing = $this->db->select('id')->from($this->table('affiliate_products'))
            ->where('channel', $channel)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($existing === false) {
            $this->db->insert($this->table('affiliate_products'))->values($values)->run();
        } else {
            $this->db->update($this->table('affiliate_products'), $values, ['id' => $this->int($existing, 'id')])->run();
        }
    }

    public function publishers(array $filters, int $offset, int $size): array
    {
        $query = $this->db->select('*')->from($this->table('affiliate_publishers'))
            ->orderBy('id', 'ASC')
            ->offset($offset)
            ->limit($size);

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $query->where('name', 'LIKE', '%' . $filters['keyword'] . '%');
        }

        $rows = $query->run()->fetchAll();

        $totalQuery = $this->db->select('*')->from($this->table('affiliate_publishers'));
        if (isset($filters['status']) && $filters['status'] !== '') {
            $totalQuery->where('status', (int) $filters['status']);
        }
        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $totalQuery->where('name', 'LIKE', '%' . $filters['keyword'] . '%');
        }

        return [
            'items' => array_map(fn (mixed $row) => $this->payloadOrCamel($row), $rows),
            'total' => $totalQuery->count(),
        ];
    }

    public function savePublisher(array $data): void
    {
        $id = (int) ($data['external_id'] ?? $data['id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $now = time();
        $values = [
            'id' => $id,
            'name' => (string) ($data['name'] ?? ''),
            'creator_id' => (string) ($data['creator_id'] ?? $data['creatorId'] ?? ''),
            'status' => (int) ($data['status'] ?? 1),
            'payload' => $this->encode($data['payload'] ?? $data),
            'created_at' => (int) ($data['created_at'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $now),
        ];

        $existing = $this->db->select('id')->from($this->table('affiliate_publishers'))
            ->where('id', $id)
            ->run()->fetch();

        if ($existing === false) {
            $this->db->insert($this->table('affiliate_publishers'))->values($values)->run();
        } else {
            $this->db->update($this->table('affiliate_publishers'), $values, ['id' => $id])->run();
        }
    }

    public function orders(array $filters, int $size, array $cursor = []): array
    {
        $query = $this->db->select('*')->from($this->table('affiliate_orders'))
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit($size);

        $this->applyOrderCursor($query, $cursor);
        $this->applyOrderFilters($query, $filters);

        $rows = $query->run()->fetchAll();

        return array_map(function (mixed $row) {
            $item = $this->payloadOrCamel($row);

            if (array_key_exists('orderCreatedAt', $item) && !array_key_exists('createdAt', $item)) {
                $item['createdAt'] = $item['orderCreatedAt'];
            }

            return $item;
        }, $rows);
    }

    public function saveOrder(array $data): void
    {
        $ecomOrderId = (string) ($data['ecom_order_id'] ?? $data['ecomOrderId'] ?? '');
        if ($ecomOrderId === '') {
            return;
        }

        $createdAt = $this->ts($data['created_at'] ?? $data['createdAt'] ?? $data['orderCreatedAt'] ?? time());
        $now = time();

        $values = [
            'publisher_id' => (string) ($data['publisher_id'] ?? $data['publisherId'] ?? ''),
            'publisher_name' => (string) ($data['publisher_name'] ?? $data['publisherName'] ?? ''),
            'partner_id' => (string) ($data['partner_id'] ?? $data['partnerId'] ?? ''),
            'partner_name' => (string) ($data['partner_name'] ?? $data['partnerName'] ?? ''),
            'custom_param' => (string) ($data['custom_param'] ?? $data['customParam'] ?? ''),
            'ecom_order_id' => $ecomOrderId,
            'amount' => (int) ($data['amount'] ?? 0),
            'commission_amount' => (int) ($data['commission_amount'] ?? $data['commissionAmount'] ?? 0),
            'commission_currency' => (string) ($data['commission_currency'] ?? $data['commissionCurrency'] ?? ''),
            'status' => (string) ($data['status'] ?? ''),
            'payload' => $this->encode($data['payload'] ?? $data),
            'created_at' => (int) ($createdAt ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $now),
        ];

        $existing = $this->db->select('id')->from($this->table('affiliate_orders'))
            ->where('ecom_order_id', $ecomOrderId)
            ->run()->fetch();

        if ($existing === false) {
            $this->db->insert($this->table('affiliate_orders'))->values($values)->run();
        } else {
            $existing = (array) $existing;
            $this->db->update($this->table('affiliate_orders'), $values, ['id' => $this->int($existing, 'id')])->run();
        }
    }

    public function saveShareLink(array $data): void
    {
        $code = (string) ($data['code'] ?? '');
        if ($code === '') {
            return;
        }

        $values = [
            'code' => $code,
            'channel' => (string) ($data['channel'] ?? ''),
            'product_external_id' => (string) ($data['product_external_id'] ?? $data['productExternalId'] ?? ''),
            'publisher_id' => (string) ($data['publisher_id'] ?? $data['publisherId'] ?? ''),
            'custom_param' => (string) ($data['custom_param'] ?? $data['customParam'] ?? ''),
            'payload' => $this->encode($data['payload'] ?? $data),
            'created_at' => (int) ($data['created_at'] ?? time()),
        ];

        $this->db->insert($this->table('share_links'))->values($values)->run();
    }

    public function findShareLink(string $code): ?array
    {
        $row = $this->db->select('*')->from($this->table('share_links'))
            ->where('code', $code)
            ->run()->fetch();

        if ($row === false) {
            return null;
        }

        $row = (array) $row;
        $row['payload'] = $this->decode(is_string($row['payload'] ?? null) ? $row['payload'] : null);

        return $row;
    }

    private function applyProductFilters(SelectQuery $query, array $filters): SelectQuery
    {
        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $query->where('name', 'LIKE', '%' . $filters['keyword'] . '%');
        }

        if (isset($filters['channel']) && is_string($filters['channel']) && $filters['channel'] !== '') {
            $query->where('channel', $filters['channel']);
        }

        if (isset($filters['priceFrom'])) {
            $query->where('price_max', '>=', (int) $filters['priceFrom']);
        }
        if (isset($filters['priceTo'])) {
            $query->where('price_min', '<=', (int) $filters['priceTo']);
        }

        return $query;
    }

    private function productOrdering(array $sort): array
    {
        foreach ($sort as $key => $value) {
            if (!isset(self::PRODUCT_SORTABLE[$key])) {
                continue;
            }
            [$column, $default] = self::PRODUCT_SORTABLE[$key];
            $direction = strtolower((string) $value) === 'asc' ? 'ASC' : $default;

            return [$column, $direction];
        }

        return ['created_at', 'DESC'];
    }

    private function applyOrderFilters(SelectQuery $query, array $filters): void
    {
        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['publisherId']) && $filters['publisherId'] !== '') {
            $query->where('publisher_id', (string) $filters['publisherId']);
        }

        if (isset($filters['ecomOrderId']) && $filters['ecomOrderId'] !== '') {
            $query->where('ecom_order_id', (string) $filters['ecomOrderId']);
        }

        foreach (['createdAtFrom' => 'created_at', 'updatedAtFrom' => 'updated_at'] as $from => $column) {
            if (isset($filters[$from]) || isset($filters[$from === 'createdAtFrom' ? 'orderCreatedAtFrom' : 'orderUpdatedAtFrom'])) {
                $value = $filters[$from] ?? $filters[$from === 'createdAtFrom' ? 'orderCreatedAtFrom' : 'orderUpdatedAtFrom'] ?? null;
                if ($value !== null) {
                    $query->where($column, '>=', (int) $value);
                }
            }
        }

        foreach (['createdAtTo' => 'created_at', 'updatedAtTo' => 'updated_at'] as $to => $column) {
            if (isset($filters[$to]) || isset($filters[$to === 'createdAtTo' ? 'orderCreatedAtTo' : 'orderUpdatedAtTo'])) {
                $value = $filters[$to] ?? $filters[$to === 'createdAtTo' ? 'orderCreatedAtTo' : 'orderUpdatedAtTo'] ?? null;
                if ($value !== null) {
                    $query->where($column, '<=', (int) $value);
                }
            }
        }
    }

    private function applyOrderCursor(SelectQuery $query, array $cursor): void
    {
        if (count($cursor) < 2) {
            return;
        }

        $ts = (int) $cursor[0];
        $id = (int) $cursor[1];

        $query->where(fn (SelectQuery $q) => $q
            ->where('created_at', '<', $ts)
            ->orWhere(fn (SelectQuery $q2) => $q2
                ->where('created_at', $ts)
                ->where('id', '<', $id)));
    }

    private function payloadOrCamel(mixed $row): array
    {
        $row = $this->decoded($row);

        return isset($row['payload']) && $row['payload'] !== [] ? $row['payload'] : $this->camel($row);
    }
}