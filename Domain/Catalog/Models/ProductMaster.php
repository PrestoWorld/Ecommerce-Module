<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Models;

use JsonSerializable;

/**
 * Product Master - Canonical book information (immutable/common across all variants)
 * 
 * Contains: ISBN, ISSN, QĐXB, XNĐKXB, title, authors, publisher, cover, TOC, description
 * One master per unique book title/edition combination
 */
final class ProductMaster implements JsonSerializable
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $sku,
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly array $authors,
        public readonly array $translators,
        public readonly ?string $publisher,
        public readonly ?string $originalPublisher,
        public readonly ?int $publicationYear,
        public readonly ?string $isbn13,
        public readonly ?string $isbn10,
        public readonly ?string $issn,
        public readonly ?string $qdXb,
        public readonly ?string $xnDkXb,
        public readonly string $language,
        public readonly ?int $pageCount,
        public readonly ?string $dimensions,
        public readonly ?int $weight,
        public readonly ?string $bindingType,
        public readonly ?string $coverImage,
        public readonly array $coverImages,
        public readonly ?string $tableOfContents,
        public readonly ?string $description,
        public readonly ?string $descriptionShort,
        public readonly array $tags,
        public readonly array $categories,
        public readonly array $subjectCodes,
        public readonly ?string $slug,
        public readonly ?string $metaTitle,
        public readonly ?string $metaDescription,
        public readonly ?string $metaKeywords,
        public readonly string $status, // draft, active, archived
        public readonly array $payload,
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly ?int $publishedAt,
        public readonly ?int $id = null,
        public readonly ?string $businessId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            externalId: (string) ($data['external_id'] ?? $data['externalId'] ?? ''),
            sku: (string) ($data['sku'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            subtitle: $data['subtitle'] ?? null,
            authors: is_array($data['authors'] ?? null) ? $data['authors'] : [],
            translators: is_array($data['translators'] ?? null) ? $data['translators'] : [],
            publisher: $data['publisher'] ?? null,
            originalPublisher: $data['original_publisher'] ?? $data['originalPublisher'] ?? null,
            publicationYear: $data['publication_year'] ?? $data['publicationYear'] ?? null,
            isbn13: $data['isbn_13'] ?? $data['isbn13'] ?? null,
            isbn10: $data['isbn_10'] ?? $data['isbn10'] ?? null,
            issn: $data['issn'] ?? null,
            qdXb: $data['qd_xb'] ?? $data['qdXb'] ?? null,
            xnDkXb: $data['xn_dk_xb'] ?? $data['xnDkXb'] ?? null,
            language: (string) ($data['language'] ?? 'vi'),
            pageCount: $data['page_count'] ?? $data['pageCount'] ?? null,
            dimensions: $data['dimensions'] ?? null,
            weight: $data['weight'] ?? null,
            bindingType: $data['binding_type'] ?? $data['bindingType'] ?? null,
            coverImage: $data['cover_image'] ?? $data['coverImage'] ?? null,
            coverImages: is_array($data['cover_images'] ?? $data['coverImages'] ?? null) ? $data['cover_images'] ?? $data['coverImages'] : [],
            tableOfContents: $data['table_of_contents'] ?? $data['tableOfContents'] ?? null,
            description: $data['description'] ?? null,
            descriptionShort: $data['description_short'] ?? $data['descriptionShort'] ?? null,
            tags: is_array($data['tags'] ?? null) ? $data['tags'] : [],
            categories: is_array($data['categories'] ?? null) ? $data['categories'] : [],
            subjectCodes: is_array($data['subject_codes'] ?? $data['subjectCodes'] ?? null) ? $data['subject_codes'] ?? $data['subjectCodes'] : [],
            slug: $data['slug'] ?? null,
            metaTitle: $data['meta_title'] ?? $data['metaTitle'] ?? null,
            metaDescription: $data['meta_description'] ?? $data['metaDescription'] ?? null,
            metaKeywords: $data['meta_keywords'] ?? $data['metaKeywords'] ?? null,
            status: (string) ($data['status'] ?? 'draft'),
            payload: is_array($data['payload'] ?? null) ? $data['payload'] : [],
            createdAt: (int) ($data['created_at'] ?? $data['createdAt'] ?? 0),
            updatedAt: (int) ($data['updated_at'] ?? $data['updatedAt'] ?? 0),
            publishedAt: $data['published_at'] ?? $data['publishedAt'] ?? null,
            id: $data['id'] ?? null,
            businessId: $data['business_id'] ?? $data['businessId'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->businessId,
            'external_id' => $this->externalId,
            'sku' => $this->sku,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'authors' => $this->authors,
            'translators' => $this->translators,
            'publisher' => $this->publisher,
            'original_publisher' => $this->originalPublisher,
            'publication_year' => $this->publicationYear,
            'isbn_13' => $this->isbn13,
            'isbn_10' => $this->isbn10,
            'issn' => $this->issn,
            'qd_xb' => $this->qdXb,
            'xn_dk_xb' => $this->xnDkXb,
            'language' => $this->language,
            'page_count' => $this->pageCount,
            'dimensions' => $this->dimensions,
            'weight' => $this->weight,
            'binding_type' => $this->bindingType,
            'cover_image' => $this->coverImage,
            'cover_images' => $this->coverImages,
            'table_of_contents' => $this->tableOfContents,
            'description' => $this->description,
            'description_short' => $this->descriptionShort,
            'tags' => $this->tags,
            'categories' => $this->categories,
            'subject_codes' => $this->subjectCodes,
            'slug' => $this->slug,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'meta_keywords' => $this->metaKeywords,
            'status' => $this->status,
            'payload' => $this->payload,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'published_at' => $this->publishedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function getPrimaryIsbn(): ?string
    {
        return $this->isbn13 ?? $this->isbn10;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getAuthorNames(): string
    {
        return implode(', ', array_map(fn ($a) => is_array($a) ? ($a['name'] ?? '') : (string) $a, $this->authors));
    }
}