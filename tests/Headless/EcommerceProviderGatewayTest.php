<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Tests\Headless;

use PrestoWorld\Modules\Ecommerce\Headless\EcommerceProvider;
use PrestoWorld\Modules\Ecommerce\Tests\Headless\Support\InMemoryCustomerRepository;
use PrestoWorld\Modules\Ecommerce\Tests\Headless\Support\InMemoryOrderRepository;
use PrestoWorld\Modules\Ecommerce\Tests\Headless\Support\InMemoryProductRepository;
use PrestoWorld\Modules\Ecommerce\Tests\TestCase;
use PrestoWorld\Modules\HeadlessCMS\Auth\ApiKeyAuthenticator;
use PrestoWorld\Modules\HeadlessCMS\Contracts\ApiKeyRepositoryInterface;
use PrestoWorld\Modules\HeadlessCMS\Http\Gateway;
use PrestoWorld\Modules\HeadlessCMS\Http\RequestContext;
use PrestoWorld\Modules\HeadlessCMS\Registry\ResourceRegistry;
use Witals\Framework\Http\Response;

final class EcommerceProviderGatewayTest extends TestCase
{
    private const SECRET = 'hls_integration_secret';

    private InMemoryProductRepository $products;

    protected function setUp(): void
    {
        parent::setUp();

        $this->products = new InMemoryProductRepository();
        $this->products->items = [
            'p1' => ['external_id' => 'p1', 'name' => 'Ao thun', 'price' => 100],
            'p2' => ['external_id' => 'p2', 'name' => 'Quan jean', 'price' => 200],
        ];
    }

    public function test_public_list_through_gateway(): void
    {
        $response = $this->gateway()->handle($this->ctx('GET'));

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->payload($response);
        self::assertSame(2, $payload['meta']['total']);
        self::assertSame('Ao thun', $payload['data'][0]['name']);
    }

    public function test_read_through_gateway(): void
    {
        $response = $this->gateway()->handle($this->ctx('GET', 'p1'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('p1', $this->payload($response)['data']['external_id']);
    }

    public function test_create_without_key_is_unauthorized(): void
    {
        $response = $this->gateway($this->authenticator(null))
            ->handle($this->ctx('POST', null, ['external_id' => 'p3', 'name' => 'New']));

        self::assertSame(401, $response->getStatusCode());
    }

    public function test_create_with_valid_key_persists(): void
    {
        $response = $this->gateway($this->authenticator(self::SECRET))
            ->handle($this->ctx('POST', null, ['external_id' => 'p9', 'name' => 'New'], self::SECRET));

        self::assertSame(201, $response->getStatusCode());
        self::assertArrayHasKey('p9', $this->products->items);
    }

    private function gateway(?ApiKeyAuthenticator $auth = null): Gateway
    {
        $registry = new ResourceRegistry();
        $registry->register(new EcommerceProvider(
            $this->products,
            new InMemoryOrderRepository(),
            new InMemoryCustomerRepository(),
            'biz-1',
        ));

        return new Gateway($registry, $auth, 20, 100, true, 'X-API-Key');
    }

    private function authenticator(?string $secret): ApiKeyAuthenticator
    {
        return new ApiKeyAuthenticator(new class($secret) implements ApiKeyRepositoryInterface {
            public function __construct(private ?string $secret)
            {
            }

            public function findByPrefix(string $prefix): ?array
            {
                if ($this->secret === null) {
                    return null;
                }

                return [
                    'key_hash' => hash('sha256', $this->secret),
                    'status' => 1,
                    'scopes' => ['*'],
                    'expires_at' => 0,
                ];
            }
        });
    }

    /**
     * @param array<string, mixed> $body
     */
    private function ctx(string $method, ?string $key = null, array $body = [], ?string $apiKey = null): RequestContext
    {
        return new RequestContext($method, 'ecommerce', 'products', $key, [], $body, $apiKey);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Response $response): array
    {
        $decoded = json_decode($response->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }
}