<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Services;

use PrestoWorld\Modules\Ecommerce\Contracts\VpageRepositoryInterface;

/**
 * Creates a 1:1 conversation (seller ↔ buyer) with an initial message
 * whenever an order is successfully created/synced.
 *
 * Idempotent: conversation/message external ids derive from the order so
 * re-syncing the same order updates the row instead of duplicating it.
 */
final class OrderConversationService
{
    public function __construct(
        private readonly VpageRepositoryInterface $conversations,
    ) {}

    /**
     * @param array<string, mixed> $order
     */
    public function ensureForOrder(string $businessId, array $order): void
    {
        $externalId = (string) ($order['external_id'] ?? $order['id'] ?? '');
        if ($externalId === '') {
            return;
        }

        $now = time();
        $code = (string) ($order['code'] ?? '');
        if ($code === '') {
            $code = $externalId;
        }

        $conversationExternalId = 'order-' . $externalId;
        $customerName = (string) ($order['customer_name'] ?? '');
        $customerId = (string) ($order['customer_mobile'] ?? '');

        try {
            $this->conversations->saveConversation($businessId, [
                'external_id' => $conversationExternalId,
                'channel' => 'order',
                'customer_name' => $customerName,
                'customer_id' => $customerId,
                'last_message_at' => $now,
                'created_at' => (int) ($order['created_at'] ?? $now),
                'payload' => [
                    'order_code' => $code,
                    'order_external_id' => $externalId,
                    'source' => (string) ($order['source'] ?? ''),
                    'subsite_id' => (string) ($order['subsite_id'] ?? ''),
                ],
            ]);

            $this->conversations->saveMessage($businessId, [
                'conversation_external_id' => $conversationExternalId,
                'external_id' => 'order-created-' . $externalId,
                'direction' => 'out',
                'message_type' => 'text',
                'content' => 'Đơn hàng #' . $code . ' đã được tạo thành công. Cảm ơn bạn đã mua hàng!',
                'sent_at' => $now,
                'payload' => [
                    'order_code' => $code,
                    'order_external_id' => $externalId,
                ],
            ]);
        } catch (\Throwable) {
            // Messaging is best-effort — never block order sync.
        }
    }
}