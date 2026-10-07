<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Tests\Storage;

use PrestoWorld\Modules\Ecommerce\Storage\Vpage\VpageRepository;

class VpageRepositoryTest extends RepositoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTable(self::PREFIX . 'pages', static function ($table): void {
            $table->string('business_id', 64);
            $table->string('external_id', 128);
            $table->string('name', 255)->defaultValue('');
            $table->string('channel', 32)->defaultValue('');
            $table->text('payload');
            $table->bigInteger('created_at');
            $table->bigInteger('updated_at');
            $table->index(['business_id', 'external_id']);
        });

        $this->createTable(self::PREFIX . 'conversations', static function ($table): void {
            $table->string('business_id', 64);
            $table->string('external_id', 128);
            $table->string('channel', 32)->defaultValue('');
            $table->string('page_id', 128)->defaultValue('');
            $table->string('customer_name', 255)->defaultValue('');
            $table->string('customer_id', 128)->defaultValue('');
            $table->bigInteger('last_message_at')->nullable();
            $table->text('payload');
            $table->bigInteger('created_at');
            $table->bigInteger('updated_at');
            $table->index(['business_id', 'external_id']);
        });

        $this->createTable(self::PREFIX . 'messages', static function ($table): void {
            $table->string('business_id', 64);
            $table->string('conversation_external_id', 128);
            $table->string('external_id', 128);
            $table->string('direction', 16)->defaultValue('');
            $table->string('message_type', 32)->defaultValue('text');
            $table->text('content');
            $table->bigInteger('sent_at')->nullable();
            $table->text('payload');
            $table->index(['business_id', 'conversation_external_id', 'external_id']);
        });
    }

    private function repo(): VpageRepository
    {
        return new VpageRepository($this->db, self::PREFIX);
    }

    public function test_pages_roundtrip(): void
    {
        $repo = $this->repo();
        $repo->savePage('biz1', ['id' => 'P1', 'name' => 'Fanpage', 'channel' => 'facebook']);
        $repo->savePage('biz1', ['id' => 'P1', 'name' => 'Fanpage v2', 'channel' => 'facebook']);

        $pages = $repo->pages('biz1');

        $this->assertCount(1, $pages);
        $this->assertSame('P1', $pages[0]['external_id']);
        $this->assertSame('Fanpage v2', $pages[0]['name']);
    }

    public function test_conversations_keyset_cursor(): void
    {
        $repo = $this->repo();
        $repo->saveConversation('biz1', ['id' => 'C1', 'customer_name' => 'A', 'created_at' => 1000]);
        $repo->saveConversation('biz1', ['id' => 'C2', 'customer_name' => 'B', 'created_at' => 2000]);
        $repo->saveConversation('biz1', ['id' => 'C3', 'customer_name' => 'C', 'created_at' => 3000]);

        $page = $repo->conversations('biz1', [], 2, []);

        $this->assertCount(2, $page);
        $this->assertSame('C3', $page[0]['external_id']);
        $this->assertSame('C2', $page[1]['external_id']);

        $next = $repo->conversations('biz1', [], 2, [2000, $page[1]['id']]);

        $this->assertCount(1, $next);
        $this->assertSame('C1', $next[0]['external_id']);
    }

    public function test_conversations_filter_by_page_and_keyword(): void
    {
        $repo = $this->repo();
        $repo->saveConversation('biz1', ['id' => 'C1', 'customer_name' => 'Lan', 'page_id' => 'PG1', 'created_at' => 100]);
        $repo->saveConversation('biz1', ['id' => 'C2', 'customer_name' => 'Hoa', 'page_id' => 'PG2', 'created_at' => 200]);
        $repo->saveConversation('biz1', ['id' => 'C3', 'customer_name' => 'Lan', 'page_id' => 'PG1', 'created_at' => 300]);

        $filtered = $repo->conversations('biz1', ['pageId' => 'PG1', 'keyword' => 'lan'], 10, []);

        $this->assertCount(2, $filtered);
    }

    public function test_messages_keyset_and_type_filter(): void
    {
        $repo = $this->repo();
        $repo->saveMessage('biz1', ['conversationId' => 'C1', 'id' => 'M1', 'message_type' => 'text', 'content' => 'hi', 'sent_at' => 10]);
        $repo->saveMessage('biz1', ['conversationId' => 'C1', 'id' => 'M2', 'message_type' => 'image', 'content' => '', 'sent_at' => 20]);
        $repo->saveMessage('biz1', ['conversationId' => 'C2', 'id' => 'M3', 'message_type' => 'text', 'content' => 'bye', 'sent_at' => 30]);

        $textMessages = $repo->messages('biz1', 'C1', ['type' => 'text'], 10, []);

        $this->assertCount(1, $textMessages);
        $this->assertSame('M1', $textMessages[0]['external_id']);

        $all = $repo->messages('biz1', 'C1', [], 10, []);

        $this->assertCount(2, $all);
        $this->assertSame('M2', $all[0]['external_id']);
    }

    public function test_messages_cursor(): void
    {
        $repo = $this->repo();
        for ($i = 1; $i <= 3; $i++) {
            $repo->saveMessage('biz1', ['conversationId' => 'C1', 'id' => 'M' . $i, 'content' => 'x', 'sent_at' => $i * 100]);
        }

        $page = $repo->messages('biz1', 'C1', [], 2, []);

        $this->assertCount(2, $page);
        $this->assertSame('M3', $page[0]['external_id']);

        $next = $repo->messages('biz1', 'C1', [], 2, [300, $page[0]['id']]);

        $this->assertSame('M2', $next[0]['external_id']);

        $last = $repo->messages('biz1', 'C1', [], 2, [200, $page[1]['id']]);

        $this->assertSame('M1', $last[0]['external_id']);
    }
}