<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Http;

use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductMasterRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductVariantRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Contracts\StockDriverInterface;
use Witals\Framework\Application;
use Witals\Framework\Http\Request;
use Witals\Framework\Http\Response;

/**
 * Admin REST API for the WooCommerce-like product management screen.
 *
 * Routes (registered by Ecommerce\Module::boot):
 *   GET    /api/admin/products              list (search/filters/pagination)
 *   GET    /api/admin/products/taxonomies   distinct categories + tags
 *   POST   /api/admin/products              create (master + variants)
 *   POST   /api/admin/products/bulk         bulk publish/draft/trash/restore/delete
 *   GET    /api/admin/products/{id}         detail (master + variants)
 *   PUT    /api/admin/products/{id}         update (master + variants sync)
 *   DELETE /api/admin/products/{id}         permanent delete (master + variants)
 */
final class AdminProductsController
{
    private const PREFIX = '/api/admin/products';

    public function __construct(
        protected Application $app,
        protected ProductMasterRepositoryInterface $masters,
        protected ProductVariantRepositoryInterface $variants,
        protected StockDriverInterface $stock,
    ) {
    }

    public function handle(Request $request, ?string $id = null): Response
    {
        $method = strtoupper($request->method());
        $path = rtrim($request->path(), '/');

        try {
            if ($path === self::PREFIX) {
                return match ($method) {
                    'GET' => $this->listProducts($request),
                    'POST' => $this->createProduct($request),
                    default => $this->error('Method not allowed', 405),
                };
            }

            if ($path === self::PREFIX . '/taxonomies') {
                return $method === 'GET'
                    ? $this->taxonomies()
                    : $this->error('Method not allowed', 405);
            }

            if ($path === self::PREFIX . '/bulk') {
                return $method === 'POST'
                    ? $this->bulkAction($request)
                    : $this->error('Method not allowed', 405);
            }

            if ($id !== null && $id !== '') {
                return match ($method) {
                    'GET' => $this->getProduct($id),
                    'PUT', 'PATCH' => $this->updateProduct($request, $id),
                    'DELETE' => $this->deleteProduct($id),
                    default => $this->error('Method not allowed', 405),
                };
            }

            return $this->error('Not found', 404);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->error('Internal error: ' . $e->getMessage(), 500);
        }
    }

    // ── List ───────────────────────────────────────────────────────

    private function listProducts(Request $request): Response
    {
        $bid = $this->businessId();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $view = (string) $request->query('view', 'all');
        $filters = [];
        switch ($view) {
            case 'published':
                $filters['status'] = 'active';
                break;
            case 'draft':
                $filters['status'] = 'draft';
                break;
            case 'trash':
                $filters['status'] = 'archived';
                break;
            default:
                $filters['statuses'] = ['active', 'draft'];
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $filters['keyword'] = $q;
        }
        $category = trim((string) $request->query('category', ''));
        if ($category !== '') {
            $filters['category'] = $category;
        }
        $tag = trim((string) $request->query('tag', ''));
        if ($tag !== '') {
            $filters['tag'] = $tag;
        }

        $orderby = (string) $request->query('orderby', 'date');
        $order = strtolower((string) $request->query('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sort = match ($orderby) {
            'title' => ['title' => $order],
            'sku' => ['sku' => $order],
            default => ['created_at' => $order],
        };

        $result = $this->masters->search($bid, $filters, $offset, $perPage, $sort);
        $items = array_map(fn (array $row) => $this->mapListItem($bid, $row), $result['items']);

        $counts = [
            'all' => $this->countWith($bid, ['statuses' => ['active', 'draft']]),
            'published' => $this->countWith($bid, ['status' => 'active']),
            'draft' => $this->countWith($bid, ['status' => 'draft']),
            'trash' => $this->countWith($bid, ['status' => 'archived']),
        ];

        return Response::json([
            'success' => true,
            'data' => [
                'items' => $items,
                'total' => (int) $result['total'],
                'page' => $page,
                'perPage' => $perPage,
                'counts' => $counts,
            ],
        ]);
    }

    private function countWith(string $bid, array $filters): int
    {
        $result = $this->masters->search($bid, $filters, 0, 1);

        return (int) $result['total'];
    }

    // ── Taxonomies ─────────────────────────────────────────────────

    private function taxonomies(): Response
    {
        return Response::json([
            'success' => true,
            'data' => $this->masters->aggregateTaxonomies($this->businessId()),
        ]);
    }

    // ── Detail ─────────────────────────────────────────────────────

    private function getProduct(string $id): Response
    {
        $bid = $this->businessId();
        $master = $this->masters->find($bid, $id);
        if (!$master) {
            return $this->error('Product not found', 404);
        }

        return Response::json([
            'success' => true,
            'data' => $this->mapDetail($bid, $master),
        ]);
    }

    // ── Create ─────────────────────────────────────────────────────

    private function createProduct(Request $request): Response
    {
        $bid = $this->businessId();
        $input = $request->getJson() ?? [];

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return $this->error('Title is required', 422);
        }

        $now = time();
        $status = in_array($input['status'] ?? null, ['draft', 'active', 'archived'], true)
            ? (string) $input['status']
            : 'draft';

        $masterData = $this->mapMasterInput($input, $title);
        $masterData['status'] = $status;
        $masterData['created_at'] = $now;
        $masterData['updated_at'] = $now;
        $masterData['published_at'] = $status === 'active' ? $now : null;

        $master = $this->masters->save($bid, $masterData);

        $this->syncVariants($bid, $master, $input['variants'] ?? []);

        return Response::json([
            'success' => true,
            'data' => $this->mapDetail($bid, $this->masters->find($bid, $master['external_id'])),
        ], 201);
    }

    // ── Update ─────────────────────────────────────────────────────

    private function updateProduct(Request $request, string $id): Response
    {
        $bid = $this->businessId();
        $existing = $this->masters->find($bid, $id);
        if (!$existing) {
            return $this->error('Product not found', 404);
        }

        // Merge request over the full existing shape so omitted fields survive
        $input = array_merge($this->masterToInput($existing), $request->getJson() ?? []);
        $now = time();

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return $this->error('Title is required', 422);
        }

        $status = $existing['status'];
        if (isset($input['status']) && in_array($input['status'], ['draft', 'active', 'archived'], true)) {
            $status = (string) $input['status'];
        }

        $masterData = $this->mapMasterInput($input, $title);
        $masterData['external_id'] = $id;
        $masterData['status'] = $status;
        $masterData['created_at'] = (int) ($existing['created_at'] ?? $now);
        $masterData['updated_at'] = $now;
        $masterData['published_at'] = $existing['published_at'] ?? null;
        if ($status === 'active' && empty($masterData['published_at'])) {
            $masterData['published_at'] = $now;
        }

        $master = $this->masters->save($bid, $masterData);

        if (array_key_exists('variants', $request->getJson() ?? [])) {
            $this->syncVariants($bid, $master, is_array($input['variants'] ?? null) ? $input['variants'] : []);
        }

        return Response::json([
            'success' => true,
            'data' => $this->mapDetail($bid, $this->masters->find($bid, $master['external_id'])),
        ]);
    }

    // ── Delete ─────────────────────────────────────────────────────

    private function deleteProduct(string $id): Response
    {
        $bid = $this->businessId();
        $master = $this->masters->find($bid, $id);
        if (!$master) {
            return $this->error('Product not found', 404);
        }

        foreach ($this->variants->findByMaster($bid, $id) as $variant) {
            $this->variants->delete($bid, (string) $variant['external_id']);
        }

        $this->masters->delete($bid, $id);

        return Response::json(['success' => true, 'data' => ['deleted' => $id]]);
    }

    // ── Bulk ───────────────────────────────────────────────────────

    private function bulkAction(Request $request): Response
    {
        $bid = $this->businessId();
        $input = $request->getJson() ?? [];

        $action = (string) ($input['action'] ?? '');
        $ids = is_array($input['ids'] ?? null) ? $input['ids'] : [];
        $ids = array_values(array_filter(array_map('strval', $ids), fn ($v) => $v !== ''));

        if ($ids === []) {
            return $this->error('No product ids provided', 422);
        }

        $updated = 0;
        $deleted = 0;
        $now = time();

        foreach ($ids as $id) {
            $master = $this->masters->find($bid, $id);
            if (!$master) {
                continue;
            }

            switch ($action) {
                case 'publish':
                    $master['status'] = 'active';
                    $master['published_at'] = $master['published_at'] ?: $now;
                    $this->saveStatus($bid, $master, $now);
                    $updated++;
                    break;

                case 'draft':
                    $master['status'] = 'draft';
                    $this->saveStatus($bid, $master, $now);
                    $updated++;
                    break;

                case 'trash':
                    $payload = is_array($master['payload'] ?? null) ? $master['payload'] : [];
                    if (($master['status'] ?? '') !== 'archived') {
                        $payload['previous_status'] = $master['status'];
                    }
                    $master['payload'] = $payload;
                    $master['status'] = 'archived';
                    $this->saveStatus($bid, $master, $now);
                    $updated++;
                    break;

                case 'restore':
                    $payload = is_array($master['payload'] ?? null) ? $master['payload'] : [];
                    $previous = $payload['previous_status'] ?? 'draft';
                    unset($payload['previous_status'], $payload['trashed_at']);
                    $master['payload'] = $payload;
                    $master['status'] = in_array($previous, ['draft', 'active'], true) ? $previous : 'draft';
                    $this->saveStatus($bid, $master, $now);
                    $updated++;
                    break;

                case 'delete':
                    foreach ($this->variants->findByMaster($bid, $id) as $variant) {
                        $this->variants->delete($bid, (string) $variant['external_id']);
                    }
                    $this->masters->delete($bid, $id);
                    $deleted++;
                    break;

                default:
                    return $this->error('Unknown bulk action: ' . $action, 422);
            }
        }

        return Response::json([
            'success' => true,
            'data' => ['updated' => $updated, 'deleted' => $deleted],
        ]);
    }

    private function saveStatus(string $bid, array $master, int $now): array
    {
        $master['updated_at'] = $now;

        return $this->masters->save($bid, $master);
    }

    // ── Variant sync ───────────────────────────────────────────────

    /**
     * Create/update/delete variants so the stored set matches $inputs.
     *
     * @param list<array<string, mixed>> $inputs
     */
    private function syncVariants(string $bid, array $master, array $inputs): void
    {
        $existingByExternal = [];
        foreach ($this->variants->findByMaster($bid, (string) $master['external_id']) as $variant) {
            $existingByExternal[(string) $variant['external_id']] = $variant;
        }

        $seen = [];
        $hasDefault = false;

        foreach ($inputs as $input) {
            if (!is_array($input)) {
                continue;
            }

            $externalId = trim((string) ($input['external_id'] ?? ''));
            $data = $this->mapVariantInput($input);

            if ($externalId !== '' && isset($existingByExternal[$externalId])) {
                $existing = $existingByExternal[$externalId];
                $data['external_id'] = $externalId;
                $data['created_at'] = (int) ($existing['created_at'] ?? time());
                try {
                    $this->stock->materialize($externalId);
                } catch (\Throwable) {
                }
            } elseif ($externalId !== '') {
                $data['external_id'] = $externalId;
            }

            $data['master_id'] = (int) $master['id'];

            if (!empty($input['is_default'])) {
                $hasDefault = true;
                $data['is_default'] = true;
            }

            $saved = $this->variants->save($bid, $data);
            $seen[(string) $saved['external_id']] = true;

            try {
                $this->stock->syncVariant(
                    (string) $saved['external_id'],
                    (int) ($data['stock_on_hand'] ?? 0),
                    (int) ($data['stock_reserved'] ?? 0),
                    'Cập nhật từ danh mục sản phẩm',
                );
            } catch (\Throwable) {
            }
        }

        // Delete variants that were removed from the payload
        foreach ($existingByExternal as $externalId => $variant) {
            if (!isset($seen[$externalId])) {
                $this->variants->delete($bid, $externalId);
            }
        }

        // Guarantee exactly one default variant
        if (!$hasDefault) {
            $default = $this->variants->findDefaultByMaster($bid, (string) $master['external_id']);
            if (!$default) {
                $first = $this->variants->findByMaster($bid, (string) $master['external_id']);
                if ($first !== []) {
                    $firstData = $first[0];
                    $firstData['is_default'] = true;
                    $this->variants->save($bid, $firstData);
                }
            }
        }
    }

    // ── Mapping ────────────────────────────────────────────────────

    /**
     * Convert a stored master row (snake_case + JSON strings) back into the
     * camelCase input shape used by mapMasterInput().
     *
     * @param array<string, mixed> $master
     * @return array<string, mixed>
     */
    private function masterToInput(array $master): array
    {
        $payload = is_array($master['payload'] ?? null) ? $master['payload'] : [];

        return [
            'title' => (string) ($master['title'] ?? ''),
            'slug' => (string) ($master['slug'] ?? ''),
            'sku' => (string) ($master['sku'] ?? ''),
            'subtitle' => $master['subtitle'] ?? null,
            'authors' => $this->decodeList($master['authors'] ?? null),
            'translators' => $this->decodeList($master['translators'] ?? null),
            'publisher' => $master['publisher'] ?? null,
            'originalPublisher' => $master['original_publisher'] ?? null,
            'publicationYear' => $master['publication_year'] ?? null,
            'isbn13' => $master['isbn_13'] ?? null,
            'isbn10' => $master['isbn_10'] ?? null,
            'issn' => $master['issn'] ?? null,
            'language' => (string) ($master['language'] ?? 'vi'),
            'pageCount' => $master['page_count'] ?? null,
            'dimensions' => $master['dimensions'] ?? null,
            'weight' => $master['weight'] ?? null,
            'bindingType' => $master['binding_type'] ?? null,
            'coverImage' => $master['cover_image'] ?? null,
            'coverImages' => $this->decodeList($master['cover_images'] ?? null),
            'tableOfContents' => $master['table_of_contents'] ?? null,
            'description' => $master['description'] ?? null,
            'shortDescription' => $master['description_short'] ?? null,
            'tags' => $this->decodeList($master['tags'] ?? null),
            'categories' => $this->decodeList($master['categories'] ?? null),
            'subjectCodes' => $this->decodeList($master['subject_codes'] ?? null),
            'metaTitle' => $master['meta_title'] ?? null,
            'metaDescription' => $master['meta_description'] ?? null,
            'metaKeywords' => $master['meta_keywords'] ?? null,
            'catalogVisibility' => $payload['catalog_visibility'] ?? 'visible',
            'featured' => (bool) ($payload['featured'] ?? false),
            'menuOrder' => (int) ($payload['menu_order'] ?? 0),
            'purchaseNote' => $payload['purchase_note'] ?? null,
            'payload' => $payload,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function mapMasterInput(array $input, string $title): array
    {
        $slug = trim((string) ($input['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->slugify($title);
        }

        $payload = [];
        if (is_array($input['payload'] ?? null)) {
            $payload = $input['payload'];
        }
        if (isset($input['catalogVisibility']) && is_string($input['catalogVisibility'])) {
            $payload['catalog_visibility'] = $input['catalogVisibility'];
        }
        if (isset($input['featured'])) {
            $payload['featured'] = (bool) $input['featured'];
        }
        if (isset($input['menuOrder'])) {
            $payload['menu_order'] = (int) $input['menuOrder'];
        }
        if (isset($input['purchaseNote']) && is_string($input['purchaseNote'])) {
            $payload['purchase_note'] = $input['purchaseNote'];
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'sku' => trim((string) ($input['sku'] ?? '')),
            'subtitle' => $this->nullableString($input['subtitle'] ?? null),
            'authors' => $this->stringList($input['authors'] ?? null),
            'translators' => $this->stringList($input['translators'] ?? null),
            'publisher' => $this->nullableString($input['publisher'] ?? null),
            'original_publisher' => $this->nullableString($input['originalPublisher'] ?? null),
            'publication_year' => $this->nullableInt($input['publicationYear'] ?? null),
            'isbn_13' => $this->nullableString($input['isbn13'] ?? null),
            'isbn_10' => $this->nullableString($input['isbn10'] ?? null),
            'issn' => $this->nullableString($input['issn'] ?? null),
            'language' => (string) ($input['language'] ?? 'vi'),
            'page_count' => $this->nullableInt($input['pageCount'] ?? null),
            'dimensions' => $this->nullableString($input['dimensions'] ?? null),
            'weight' => $this->nullableInt($input['weight'] ?? null),
            'binding_type' => $this->nullableString($input['bindingType'] ?? null),
            'cover_image' => $this->nullableString($input['coverImage'] ?? null),
            'cover_images' => $this->stringList($input['coverImages'] ?? null),
            'table_of_contents' => $this->nullableString($input['tableOfContents'] ?? null),
            'description' => $this->nullableString($input['description'] ?? null),
            'description_short' => $this->nullableString($input['shortDescription'] ?? null),
            'tags' => $this->stringList($input['tags'] ?? null),
            'categories' => $this->stringList($input['categories'] ?? null),
            'subject_codes' => $this->stringList($input['subjectCodes'] ?? null),
            'meta_title' => $this->nullableString($input['metaTitle'] ?? null),
            'meta_description' => $this->nullableString($input['metaDescription'] ?? null),
            'meta_keywords' => $this->nullableString($input['metaKeywords'] ?? null),
            'payload' => $payload,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function mapVariantInput(array $input): array
    {
        $variantPayload = [];
        if (is_array($input['payload'] ?? null)) {
            $variantPayload = $input['payload'];
        }

        $regular = $input['regularPrice'] ?? ($variantPayload['regular_price'] ?? null);
        $sale = $input['salePrice'] ?? ($variantPayload['sale_price'] ?? null);
        $variantPayload['regular_price'] = $regular !== null && $regular !== '' ? (float) $regular : null;
        $variantPayload['sale_price'] = $sale !== null && $sale !== '' ? (float) $sale : null;

        $stockOnHand = (int) ($input['stockOnHand'] ?? 0);
        $stockReserved = (int) ($input['stockReserved'] ?? 0);

        return [
            'sku' => (string) ($input['sku'] ?? ''),
            'barcode' => $this->nullableString($input['barcode'] ?? null),
            'edition' => max(1, (int) ($input['edition'] ?? 1)),
            'edition_name' => $this->nullableString($input['editionName'] ?? null),
            'publication_year' => $this->nullableInt($input['publicationYear'] ?? null),
            'publisher' => $this->nullableString($input['publisher'] ?? null),
            'cover_price' => (int) ($input['coverPrice'] ?? 0),
            'wholesale_price' => (int) ($input['wholesalePrice'] ?? 0),
            'cost_price' => (int) ($input['costPrice'] ?? 0),
            'currency' => (string) ($input['currency'] ?? 'VND'),
            'stock_on_hand' => max(0, $stockOnHand),
            'stock_reserved' => max(0, $stockReserved),
            'stock_available' => max(0, $stockOnHand - $stockReserved),
            'reorder_point' => max(0, (int) ($input['reorderPoint'] ?? 0)),
            'weight' => $this->nullableInt($input['weight'] ?? null),
            'dimensions' => $this->nullableString($input['dimensions'] ?? null),
            'cover_image' => $this->nullableString($input['coverImage'] ?? null),
            'status' => (string) ($input['status'] ?? 'active'),
            'is_default' => !empty($input['is_default']),
            'payload' => $variantPayload,
        ];
    }

    /**
     * @param array<string, mixed> $master
     * @return array<string, mixed>
     */
    private function mapListItem(string $bid, array $master): array
    {
        $default = $this->variants->findDefaultByMaster($bid, (string) $master['external_id']);
        if (!$default) {
            $all = $this->variants->findByMaster($bid, (string) $master['external_id']);
            $default = $all[0] ?? null;
        }

        return [
            'id' => (string) $master['external_id'],
            'title' => (string) ($master['title'] ?? ''),
            'slug' => (string) ($master['slug'] ?? ''),
            'status' => (string) ($master['status'] ?? 'draft'),
            'sku' => (string) ($default['sku'] ?? ($master['sku'] ?? '')),
            'price' => $this->priceOf($default),
            'stock' => $this->stockOf($default),
            'coverImage' => $master['cover_image'] ?? ($default['cover_image'] ?? null),
            'categories' => $this->decodeList($master['categories'] ?? null),
            'tags' => $this->decodeList($master['tags'] ?? null),
            'publishedAt' => $this->nullableInt($master['published_at'] ?? null),
            'createdAt' => (int) ($master['created_at'] ?? 0),
            'updatedAt' => (int) ($master['updated_at'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed>|null $master
     * @return array<string, mixed>
     */
    private function mapDetail(string $bid, ?array $master): array
    {
        if (!$master) {
            return [];
        }

        $variants = array_map(function (array $variant): array {
            $payload = is_array($variant['payload'] ?? null) ? $variant['payload'] : [];

            return [
                'id' => (string) $variant['external_id'],
                'sku' => (string) ($variant['sku'] ?? ''),
                'barcode' => $variant['barcode'] ?? null,
                'edition' => (int) ($variant['edition'] ?? 1),
                'editionName' => $variant['edition_name'] ?? null,
                'publicationYear' => $this->nullableInt($variant['publication_year'] ?? null),
                'publisher' => $variant['publisher'] ?? null,
                'coverPrice' => (int) ($variant['cover_price'] ?? 0),
                'wholesalePrice' => (int) ($variant['wholesale_price'] ?? 0),
                'costPrice' => (int) ($variant['cost_price'] ?? 0),
                'regularPrice' => $payload['regular_price'] ?? null,
                'salePrice' => $payload['sale_price'] ?? null,
                'currency' => (string) ($variant['currency'] ?? 'VND'),
                'stockOnHand' => (int) ($variant['stock_on_hand'] ?? 0),
                'stockReserved' => (int) ($variant['stock_reserved'] ?? 0),
                'stockAvailable' => (int) ($variant['stock_available'] ?? 0),
                'reorderPoint' => (int) ($variant['reorder_point'] ?? 0),
                'weight' => $this->nullableInt($variant['weight'] ?? null),
                'dimensions' => $variant['dimensions'] ?? null,
                'coverImage' => $variant['cover_image'] ?? null,
                'status' => (string) ($variant['status'] ?? 'active'),
                'isDefault' => (bool) ($variant['is_default'] ?? false),
                'stock' => $this->stockOf($variant),
            ];
        }, $this->variants->findByMaster($bid, (string) $master['external_id']));

        $payload = is_array($master['payload'] ?? null) ? $master['payload'] : [];
        $default = null;
        foreach ($variants as $variant) {
            if ($variant['isDefault']) {
                $default = $variant;
                break;
            }
        }
        $default ??= $variants[0] ?? null;

        $price = ['regular' => 0.0, 'sale' => null, 'currency' => 'VND'];
        $stock = ['onHand' => 0, 'reserved' => 0, 'available' => 0, 'status' => 'outofstock'];
        if ($default) {
            $regular = $default['regularPrice'];
            if ($regular === null || $regular === '' || (float) $regular <= 0) {
                $regular = (float) $default['coverPrice'];
            }
            $sale = $default['salePrice'];
            $price = [
                'regular' => (float) $regular,
                'sale' => $sale !== null && $sale !== '' && (float) $sale > 0 ? (float) $sale : null,
                'currency' => (string) $default['currency'],
            ];
            $stock = $default['stock'];
        }

        return [
            'id' => (string) $master['external_id'],
            'title' => (string) ($master['title'] ?? ''),
            'slug' => (string) ($master['slug'] ?? ''),
            'status' => (string) ($master['status'] ?? 'draft'),
            'sku' => (string) ($master['sku'] ?? ''),
            'subtitle' => $master['subtitle'] ?? null,
            'description' => $master['description'] ?? null,
            'shortDescription' => $master['description_short'] ?? null,
            'coverImage' => $master['cover_image'] ?? null,
            'coverImages' => $this->decodeList($master['cover_images'] ?? null),
            'categories' => $this->decodeList($master['categories'] ?? null),
            'tags' => $this->decodeList($master['tags'] ?? null),
            'authors' => $this->decodeList($master['authors'] ?? null),
            'translators' => $this->decodeList($master['translators'] ?? null),
            'publisher' => $master['publisher'] ?? null,
            'originalPublisher' => $master['original_publisher'] ?? null,
            'publicationYear' => $this->nullableInt($master['publication_year'] ?? null),
            'isbn13' => $master['isbn_13'] ?? null,
            'isbn10' => $master['isbn_10'] ?? null,
            'issn' => $master['issn'] ?? null,
            'language' => (string) ($master['language'] ?? 'vi'),
            'pageCount' => $this->nullableInt($master['page_count'] ?? null),
            'bindingType' => $master['binding_type'] ?? null,
            'dimensions' => $master['dimensions'] ?? null,
            'weight' => $this->nullableInt($master['weight'] ?? null),
            'tableOfContents' => $master['table_of_contents'] ?? null,
            'subjectCodes' => $this->decodeList($master['subject_codes'] ?? null),
            'metaTitle' => $master['meta_title'] ?? null,
            'metaDescription' => $master['meta_description'] ?? null,
            'metaKeywords' => $master['meta_keywords'] ?? null,
            'catalogVisibility' => $payload['catalog_visibility'] ?? 'visible',
            'featured' => (bool) ($payload['featured'] ?? false),
            'menuOrder' => (int) ($payload['menu_order'] ?? 0),
            'purchaseNote' => $payload['purchase_note'] ?? null,
            'price' => $price,
            'stock' => $stock,
            'variants' => $variants,
            'defaultVariantId' => $default['id'] ?? null,
            'publishedAt' => $this->nullableInt($master['published_at'] ?? null),
            'createdAt' => (int) ($master['created_at'] ?? 0),
            'updatedAt' => (int) ($master['updated_at'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed>|null $variant
     * @return array{regular: float, sale: float|null, currency: string}
     */
    private function priceOf(?array $variant): array
    {
        if (!$variant) {
            return ['regular' => 0.0, 'sale' => null, 'currency' => 'VND'];
        }

        $payload = is_array($variant['payload'] ?? null) ? $variant['payload'] : [];
        $regular = $payload['regular_price'] ?? null;
        if ($regular === null || $regular === '' || (float) $regular <= 0) {
            $regular = (float) ($variant['cover_price'] ?? ($variant['wholesale_price'] ?? 0));
        }
        $sale = $payload['sale_price'] ?? null;
        $sale = $sale !== null && $sale !== '' && (float) $sale > 0 ? (float) $sale : null;

        return [
            'regular' => (float) $regular,
            'sale' => $sale,
            'currency' => (string) ($variant['currency'] ?? 'VND'),
        ];
    }

    /**
     * @param array<string, mixed>|null $variant
     * @return array{onHand: int, reserved: int, available: int, status: string}
     */
    private function stockOf(?array $variant): array
    {
        if (!$variant) {
            return ['onHand' => 0, 'reserved' => 0, 'available' => 0, 'status' => 'outofstock'];
        }

        $onHand = (int) ($variant['stock_on_hand'] ?? 0);
        $reserved = (int) ($variant['stock_reserved'] ?? 0);
        $available = (int) ($variant['stock_available'] ?? max(0, $onHand - $reserved));
        $reorderPoint = (int) ($variant['reorder_point'] ?? 0);
        $active = (string) ($variant['status'] ?? 'active') === 'active';

        $status = match (true) {
            !$active, $available <= 0 => 'outofstock',
            $reorderPoint > 0 && $available <= $reorderPoint => 'lowstock',
            default => 'instock',
        };

        return [
            'onHand' => $onHand,
            'reserved' => $reserved,
            'available' => $available,
            'status' => $status,
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function businessId(): string
    {
        $value = $this->app->config('catalog.headless.business_id', '');
        if (!is_string($value) || $value === '') {
            $value = (string) $this->app->config('ecommerce.headless.business_id', '');
        }

        return is_string($value) ? $value : '';
    }

    private function error(string $message, int $status): Response
    {
        return Response::json(['success' => false, 'error' => $message], $status);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $item = trim($item);
                if ($item !== '') {
                    $items[] = $item;
                }
            }
        }

        return array_values(array_unique($items));
    }

    private function decodeList(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function slugify(string $title): string
    {
        // Keep Unicode letters/digits (Vietnamese-safe), collapse the rest to "-"
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $title) ?? '';
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'san-pham-' . substr(bin2hex(random_bytes(4)), 0, 8);
        }

        return mb_strtolower($slug, 'UTF-8');
    }
}
