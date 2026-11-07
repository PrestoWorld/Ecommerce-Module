<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Services;

use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\GroqClientInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductMasterRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductVariantRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ChannelProductRepositoryInterface;

/**
 * AI Audit Service - chuẩn hóa thông tin sản phẩm qua Groq AI.
 *
 * Logic:
 * - Chỉ xử lý records: ai_audit=false VÀ manual_audit=false
 * - Sau khi AI xử lý thành công -> set ai_audit=true
 * - manual_audit=true: dữ liệu do người xử lý, ưu tiên cao nhất, loại bỏ khỏi AI tools, chỉ cho chỉnh sửa thủ công
 */
final class AiAuditService
{
    public function __construct(
        private readonly GroqClientInterface $groq,
        private readonly ProductMasterRepositoryInterface $masters,
        private readonly ProductVariantRepositoryInterface $variants,
        private readonly ChannelProductRepositoryInterface $channels,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->groq->isConfigured();
    }

    /**
     * Xử lý một batch sản phẩm đang chờ audit AI.
     *
     * @return array{processed: int, failed: int, pending: int, retry_after: ?int}
     */
    public function processPending(string $businessId, int $limit = 20): array
    {
        $processed = 0;
        $failed = 0;
        $retryAfter = null;

        if ($limit < 1) {
            $limit = 1;
        }

        // Tìm ProductMaster chưa audit
        $masters = $this->findPendingMasters($businessId, $limit);

        foreach ($masters as $master) {
            if (!is_array($master)) {
                continue;
            }

            $result = $this->processMaster($businessId, $master);

            if ($result['ok']) {
                $processed++;
                continue;
            }

            $failed++;
            $retryAfter = $result['retry_after'] ?? $retryAfter;

            if ($retryAfter !== null) {
                break;
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
            'has_more' => $this->findPendingMasters($businessId, 1) !== [],
            'retry_after' => $retryAfter,
        ];
    }

    /**
     * Xử lý một ProductMaster cụ thể.
     *
     * @param array<string, mixed> $master
     * @return array{ok: bool, master: ?array, retry_after: ?int, error: ?string}
     */
    public function processMaster(string $businessId, array $master): array
    {
        $externalId = (string) ($master['external_id'] ?? '');

        if ($externalId === '') {
            return $this->error('ai-audit: thiếu external_id', null);
        }

        // Skip nếu đã audit hoặc manual audit
        if (!empty($master['ai_audit'])) {
            return ['ok' => true, 'master' => $master, 'retry_after' => null, 'error' => null];
        }
        if (!empty($master['manual_audit'])) {
            return ['ok' => true, 'master' => $master, 'retry_after' => null, 'error' => null];
        }

        if (!$this->groq->isConfigured()) {
            // Không có key: đánh dấu đã thử -> không quét lại vô hạn
            $this->markAiAuditFailed($businessId, $externalId);

            return $this->error('ai-audit: GROQ_API_KEY chưa cấu hình', null);
        }

        $normalized = $this->normalizeMaster($master);

        if ($normalized === null) {
            $this->markAiAuditFailed($businessId, $externalId);

            return $this->error('ai-audit: Groq không trả kết quả', null);
        }

        // Merge normalized data và set ai_audit = true
        $updated = array_merge($master, $normalized, ['ai_audit' => true]);
        $saved = $this->masters->save($businessId, $updated);

        return ['ok' => true, 'master' => $saved, 'retry_after' => null, 'error' => null];
    }

    /**
     * Tìm các ProductMaster chưa audit (ai_audit=false AND manual_audit=false).
     *
     * @return list<array<string, mixed>>
     */
    private function findPendingMasters(string $businessId, int $limit): array
    {
        $rows = $this->masters->search($businessId, [
            'ai_audit' => false,
            'manual_audit' => false,
        ], 0, $limit);

        return $rows['items'] ?? [];
    }

    /**
     * Gọi Groq để chuẩn hóa thông tin ProductMaster.
     */
    private function normalizeMaster(array $master): ?array
    {
        $result = $this->groq->complete($this->prompt($master));

        if (!$result['ok']) {
            return null;
        }

        $normalized = json_decode($result['text'], true);

        return is_array($normalized) ? $normalized : null;
    }

    /**
     * Tạo prompt cho Groq.
     *
     * @return list<array{role: string, content: string}>
     */
    private function prompt(array $master): array
    {
        $title = (string) ($master['title'] ?? '');
        $description = (string) ($master['description'] ?? '');
        $descriptionShort = (string) ($master['description_short'] ?? '');
        $authors = is_array($master['authors'] ?? null) ? implode(', ', array_map(fn($a) => is_array($a) ? ($a['name'] ?? '') : (string)$a, $master['authors'])) : '';
        $categories = is_array($master['categories'] ?? null) ? implode(', ', $master['categories']) : '';
        $publisher = (string) ($master['publisher'] ?? '');
        $isbn13 = (string) ($master['isbn_13'] ?? '');
        $tags = is_array($master['tags'] ?? null) ? implode(', ', $master['tags']) : '';

        return [
            [
                'role' => 'system',
                'content' => 'You are a book catalog normalization expert. Given raw product data, return a JSON object with corrected/standardized fields. Only return the JSON object, nothing else. Fields you may correct: title, description, description_short, authors (array of {name}), categories (array of strings), publisher, isbn_13, tags (array of strings). Preserve language (Vietnamese). Fix typos, standardize casing, correct ISBN format, normalize author names to "First Last" format, expand abbreviations.'
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'title' => $title,
                    'description' => $description,
                    'description_short' => $descriptionShort,
                    'authors' => $authors,
                    'categories' => $categories,
                    'publisher' => $publisher,
                    'isbn_13' => $isbn13,
                    'tags' => $tags,
                ], JSON_UNESCAPED_UNICODE),
            ],
        ];
    }

    /**
     * Đánh dấu audit AI thất bại (tăng retry count hoặc set flag để skip).
     */
    private function markAiAuditFailed(string $businessId, string $externalId): void
    {
        // Có thể lưu vào payload để track retries, hoặc thêm cột retry_count
        // Tạm thời không làm gì, sẽ được xử lý ở batch tiếp theo
    }

    private function error(string $message, ?int $retryAfter): array
    {
        return ['ok' => false, 'master' => null, 'retry_after' => $retryAfter, 'error' => $message];
    }
}