<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Models;

use JsonSerializable;

/**
 * Channel Product - Distribution/Channel level variant
 * 
 * Channel-specific overrides: custom content, images, pricing
 * Used for Shopee, TikTok Shop, Lazada, Website, BookPress, Dropship partners
 */
final class ChannelProduct implements JsonSerializable
{
    public function __construct(
        public readonly string $externalId,
        public readonly ?int $variantOptionId,
        public readonly int $variantId,
        public readonly int $masterId,
        public readonly string $sku,
        public readonly string $channel,
        public readonly ?string $channelAccountId,
        public readonly ?string $title,
        public readonly ?string $subtitle,
        public readonly ?string $description,
        public readonly ?string $descriptionShort,
        public readonly ?string $coverImage,
        public readonly array $images,
        public readonly array $tags,
        public readonly array $attributes,
        public readonly int $salePrice,
        public readonly int $compareAtPrice,
        public readonly ?int $promotionPrice,
        public readonly ?int $promotionStart,
        public readonly ?int $promotionEnd,
        public readonly string $currency,
        public readonly int $channelStock,
        public readonly bool $stockSyncEnabled,
        public readonly ?int $lastSyncedAt,
        public readonly ?string $channelProductId,
        public readonly ?string $channelVariantId,
        public readonly ?string $channelCategoryId,
        public readonly ?string $channelUrl,
        public readonly ?string $slug,
        public readonly ?string $metaTitle,
        public readonly ?string $metaDescription,
        public readonly string $status, // draft, active, inactive, pending_review, rejected
        public readonly string $publishStatus, // unpublished, published, pending, failed, rejected
        public readonly array $payload,
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly ?int $publishedAt,
        public readonly ?int $id = null,
        public readonly ?string $businessId = null,
        public readonly ?string $variantOptionExternalId = null,
        public readonly ?string $variantExternalId = null,
        public readonly ?string $masterExternalId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            externalId: (string) ($data['external_id'] ?? $data['externalId'] ?? ''),
            variantOptionId: $data['variant_option_id'] ?? $data['variantOptionId'] ?? null,
            variantId: (int) ($data['variant_id'] ?? $data['variantId'] ?? 0),
            masterId: (int) ($data['master_id'] ?? $data['masterId'] ?? 0),
            sku: (string) ($data['sku'] ?? ''),
            channel: (string) ($data['channel'] ?? ''),
            channelAccountId: $data['channel_account_id'] ?? $data['channelAccountId'] ?? null,
            title: $data['title'] ?? null,
            subtitle: $data['subtitle'] ?? null,
            description: $data['description'] ?? null,
            descriptionShort: $data['description_short'] ?? $data['descriptionShort'] ?? null,
            coverImage: $data['cover_image'] ?? $data['coverImage'] ?? null,
            images: is_array($data['images'] ?? null) ? $data['images'] : [],
            tags: is_array($data['tags'] ?? null) ? $data['tags'] : [],
            attributes: is_array($data['attributes'] ?? null) ? $data['attributes'] : [],
            salePrice: (int) ($data['sale_price'] ?? $data['salePrice'] ?? 0),
            compareAtPrice: (int) ($data['compare_at_price'] ?? $data['compareAtPrice'] ?? 0),
            promotionPrice: $data['promotion_price'] ?? $data['promotionPrice'] ?? null,
            promotionStart: $data['promotion_start'] ?? $data['promotionStart'] ?? null,
            promotionEnd: $data['promotion_end'] ?? $data['promotionEnd'] ?? null,
            currency: (string) ($data['currency'] ?? 'VND'),
            channelStock: (int) ($data['channel_stock'] ?? $data['channelStock'] ?? 0),
            stockSyncEnabled: (bool) ($data['stock_sync_enabled'] ?? $data['stockSyncEnabled'] ?? true),
            lastSyncedAt: $data['last_synced_at'] ?? $data['lastSyncedAt'] ?? null,
            channelProductId: $data['channel_product_id'] ?? $data['channelProductId'] ?? null,
            channelVariantId: $data['channel_variant_id'] ?? $data['channelVariantId'] ?? null,
            channelCategoryId: $data['channel_category_id'] ?? $data['channelCategoryId'] ?? null,
            channelUrl: $data['channel_url'] ?? $data['channelUrl'] ?? null,
            slug: $data['slug'] ?? null,
            metaTitle: $data['meta_title'] ?? $data['metaTitle'] ?? null,
            metaDescription: $data['meta_description'] ?? $data['metaDescription'] ?? null,
            status: (string) ($data['status'] ?? 'draft'),
            publishStatus: (string) ($data['publish_status'] ?? $data['publishStatus'] ?? 'unpublished'),
            payload: is_array($data['payload'] ?? null) ? $data['payload'] : [],
            createdAt: (int) ($data['created_at'] ?? $data['createdAt'] ?? 0),
            updatedAt: (int) ($data['updated_at'] ?? $data['updatedAt'] ?? 0),
            publishedAt: $data['published_at'] ?? $data['publishedAt'] ?? null,
            id: $data['id'] ?? null,
            businessId: $data['business_id'] ?? $data['businessId'] ?? null,
            variantOptionExternalId: $data['variant_option_external_id'] ?? $data['variantOptionExternalId'] ?? null,
            variantExternalId: $data['variant_external_id'] ?? $data['variantExternalId'] ?? null,
            masterExternalId: $data['master_external_id'] ?? $data['masterExternalId'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->businessId,
            'external_id' => $this->externalId,
            'variant_option_id' => $this->variantOptionId,
            'variant_id' => $this->variantId,
            'master_id' => $this->masterId,
            'variant_option_external_id' => $this->variantOptionExternalId,
            'variant_external_id' => $this->variantExternalId,
            'master_external_id' => $this->masterExternalId,
            'sku' => $this->sku,
            'channel' => $this->channel,
            'channel_account_id' => $this->channelAccountId,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'description_short' => $this->descriptionShort,
            'cover_image' => $this->coverImage,
            'images' => $this->images,
            'tags' => $this->tags,
            'attributes' => $this->attributes,
            'sale_price' => $this->salePrice,
            'compare_at_price' => $this->compareAtPrice,
            'promotion_price' => $this->promotionPrice,
            'promotion_start' => $this->promotionStart,
            'promotion_end' => $this->promotionEnd,
            'currency' => $this->currency,
            'channel_stock' => $this->channelStock,
            'stock_sync_enabled' => $this->stockSyncEnabled,
            'last_synced_at' => $this->lastSyncedAt,
            'channel_product_id' => $this->channelProductId,
            'channel_variant_id' => $this->channelVariantId,
            'channel_category_id' => $this->channelCategoryId,
            'channel_url' => $this->channelUrl,
            'slug' => $this->slug,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'status' => $this->status,
            'publish_status' => $this->publishStatus,
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

    public function isPublished(): bool
    {
        return $this->publishStatus === 'published';
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->isPublished();
    }

    public function hasActivePromotion(): bool
    {
        if (!$this->promotionPrice || !$this->promotionStart || !$this->promotionEnd) {
            return false;
        }
        $now = time();
        return $now >= $this->promotionStart && $now <= $this->promotionEnd;
    }

    public function getEffectivePrice(): int
    {
        if ($this->hasActivePromotion()) {
            return $this->promotionPrice;
        }
        return $this->salePrice;
    }

    public function getDiscountPercent(): float
    {
        if ($this->compareAtPrice <= 0) {
            return 0.0;
        }
        $effective = $this->getEffectivePrice();
        return round((($this->compareAtPrice - $effective) / $this->compareAtPrice) * 100, 2);
    }

    public function needsStockSync(): bool
    {
        return $this->stockSyncEnabled && $this->isPublished();
    }
}