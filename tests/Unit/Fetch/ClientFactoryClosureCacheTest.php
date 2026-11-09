<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\CrawlerX\Services\ClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use ReflectionProperty;

final class ClientFactoryClosureCacheTest extends TestCase
{
    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_per_request_before_request_closures_do_not_accumulate_cached_clients(): void
    {
        ClientBuilder::clearFake();
        $factory = new ClientFactory();
        $cachedClients = new ReflectionProperty(ClientFactory::class, 'clients');
        $before = $cachedClients->getValue();

        $factory->factory([
            'max_response_bytes' => 1024,
            'before_request' => static function (RequestInterface $request): void {
            },
        ], 'closure-cache-test');
        $factory->factory([
            'max_response_bytes' => 1024,
            'before_request' => static function (RequestInterface $request): void {
            },
        ], 'closure-cache-test');

        self::assertSame(array_keys($before), array_keys($cachedClients->getValue()));
    }
}
