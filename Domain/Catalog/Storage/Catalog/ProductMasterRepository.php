<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\Catalog;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductMasterRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\AbstractRepository;

final class ProductMasterRepository extends AbstractRepository implements ProductMasterRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);

        $query = $this->applyFilters(
            $this->db->select('*')->from($this->table('product_masters')),
            $businessId,
            $filters,
        );

        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();

        $total = $this->applyFilters(
            $this->db->select('*')->from($this->table('product_masters')),
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
        $row = $this->db->select('*')->from($this->table('product_masters'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function findBySku(string $businessId, string $sku): ?array
    {
        $row = $this->db->select('*')->from($this->table('product_masters'))
            ->where('business_id', $businessId)
            ->where('sku', $sku)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function findByIsbn(string $businessId, string $isbn): ?array
    {
        $row = $this->db->select('*')->from($this->table('product_masters'))
            ->where('business_id', $businessId)
            ->where(fn (SelectQuery $q) => $q
                ->where('isbn_13', $isbn)
                ->orWhere('isbn_10', $isbn))
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        if ($externalId === '') {
            $externalId = 'master_' . bin2hex(random_bytes(8));
        }
        $now = time();

        // Build JSON fields
        $authors = isset($data['authors']) && is_array($data['authors']) ? json_encode($data['authors']) : ($data['authors'] ?? null);
        $translators = isset($data['translators']) && is_array($data['translators']) ? json_encode($data['translators']) : ($data['translators'] ?? null);
        $coverImages = isset($data['cover_images']) && is_array($data['cover_images']) ? json_encode($data['cover_images']) : ($data['cover_images'] ?? null);
        $tags = isset($data['tags']) && is_array($data['tags']) ? json_encode($data['tags']) : ($data['tags'] ?? null);
        $categories = isset($data['categories']) && is_array($data['categories']) ? json_encode($data['categories']) : ($data['categories'] ?? null);
        $subjectCodes = isset($data['subject_codes']) && is_array($data['subject_codes']) ? json_encode($data['subject_codes']) : ($data['subject_codes'] ?? null);

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'sku' => (string) ($data['sku'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'subtitle' => $data['subtitle'] ?? null,
            'authors' => $authors,
            'translators' => $translators,
            'publisher' => $data['publisher'] ?? null,
            'original_publisher' => $data['original_publisher'] ?? null,
            'publication_year' => $data['publication_year'] ?? null,
            'isbn_13' => $data['isbn_13'] ?? null,
            'isbn_10' => $data['isbn_10'] ?? null,
            'issn' => $data['issn'] ?? null,
            'qd_xb' => $data['qd_xb'] ?? null,
            'xn_dk_xb' => $data['xn_dk_xb'] ?? null,
            'language' => (string) ($data['language'] ?? 'vi'),
            'page_count' => $data['page_count'] ?? null,
            'dimensions' => $data['dimensions'] ?? null,
            'weight' => $data['weight'] ?? null,
            'binding_type' => $data['binding_type'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,
            'cover_images' => $coverImages,
            'table_of_contents' => $data['table_of_contents'] ?? null,
            'description' => $data['description'] ?? null,
            'description_short' => $data['description_short'] ?? null,
            'tags' => $tags,
            'categories' => $categories,
            'subject_codes' => $subjectCodes,
            'slug' => $data['slug'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'meta_keywords' => $data['meta_keywords'] ?? null,
            'status' => (string) ($data['status'] ?? 'draft'),
            'payload' => $this->encode($data['payload'] ?? []),
            'created_at' => (int) ($data['created_at'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $now),
            'published_at' => $data['published_at'] ?? null,
        ];

        $existing = $this->db->select('id')->from($this->table('product_masters'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($existing === false) {
            $result = $this->db->insert($this->table('product_masters'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $existing = (array) $existing;
            $id = $this->int($existing, 'id');
            $this->db->update($this->table('product_masters'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['payload'] = $this->decode($values['payload']);
        $values['authors'] = $this->decodeJson($values['authors']);
        $values['translators'] = $this->decodeJson($values['translators']);
        $values['cover_images'] = $this->decodeJson($values['cover_images']);
        $values['tags'] = $this->decodeJson($values['tags']);
        $values['categories'] = $this->decodeJson($values['categories']);
        $values['subject_codes'] = $this->decodeJson($values['subject_codes']);

        return $values;
    }

    public function delete(string $businessId, string $externalId): bool
    {
        $result = $this->db->delete($this->table('product_masters'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run();

        return $result > 0;
    }

    public function getVariants(string $businessId, string $masterExternalId): array
    {
        $master = $this->find($businessId, $masterExternalId);
        if (!$master) {
            return [];
        }

        $rows = $this->db->select('*')->from($this->table('product_variants'))
            ->where('business_id', $businessId)
            ->where('master_id', $master['id'])
            ->orderBy('edition', 'ASC')
            ->run()->fetchAll();

        return $this->rows($rows);
    }

    public function getChannelProducts(string $businessId, string $masterExternalId): array
    {
        $master = $this->find($businessId, $masterExternalId);
        if (!$master) {
            return [];
        }

        $rows = $this->db->select('*')->from($this->table('channel_products'))
            ->where('business_id', $businessId)
            ->where('master_id', $master['id'])
            ->run()->fetchAll();

        return $this->rows($rows);
    }

    private function applyFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);

        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $keyword = $filters['keyword'];
            $query->where(fn (SelectQuery $q) => $q
                ->where('title', 'LIKE', '%' . $keyword . '%')
                ->orWhere('sku', 'LIKE', '%' . $keyword . '%')
                ->orWhere('external_id', 'LIKE', '%' . $keyword . '%')
                ->orWhere('isbn_13', 'LIKE', '%' . $keyword . '%')
                ->orWhere('isbn_10', 'LIKE', '%' . $keyword . '%')
                ->orWhere('authors', 'LIKE', '%' . $keyword . '%'));
        }

        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['publisher']) && is_string($filters['publisher']) && $filters['publisher'] !== '') {
            $query->where('publisher', $filters['publisher']);
        }

        if (isset($filters['publication_year']) && is_numeric($filters['publication_year'])) {
            $query->where('publication_year', (int) $filters['publication_year']);
        }

        if (isset($filters['isbn']) && is_string($filters['isbn']) && $filters['isbn'] !== '') {
            $isbn = $filters['isbn'];
            $query->where(fn (SelectQuery $q) => $q
                ->where('isbn_13', $isbn)
                ->orWhere('isbn_10', $isbn));
        }

        return $query;
    }

    private function ordering(array $sort): array
    {
        $column = 'created_at';
        $direction = 'DESC';

        foreach ($sort as $key => $value) {
            $key = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            if (!in_array($key, ['created_at', 'id', 'updated_at', 'title', 'sku', 'publication_year', 'publisher'], true)) {
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