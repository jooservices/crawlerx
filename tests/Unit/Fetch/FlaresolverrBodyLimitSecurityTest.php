<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponse;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Exceptions\CrawlFetchException;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Guard\RobotsGuard;
use JOOservices\CrawlerX\Fetch\Handlers\FlaresolverrFetchHandler;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class FlaresolverrBodyLimitSecurityTest extends TestCase
{
    public function test_default_factory_uses_configured_flaresolverr_when_robots_are_disabled(): void
    {
        $originalUrl = getenv('FLARESOLVERR_URL');
        $originalProxy = getenv('CRAWLERX_FLARESOLVERR_PROXY_URL');
        $originalRespect = getenv('CRAWLERX_RESPECT_ROBOTS');
        putenv('FLARESOLVERR_URL=https://flaresolverr.test/v1');
        putenv('CRAWLERX_FLARESOLVERR_PROXY_URL=http://ssrf-egress.test:8080');
        putenv('CRAWLERX_RESPECT_ROBOTS=false');

        try {
            ClientBuilder::fake();
            ClientBuilder::respond('POST', 'https://flaresolverr.test/v1', (new TestResponseSequence())
                ->push(TestResponse::make(200, [], $this->response('<html><body>solved page</body></html>'))));
            $factory = new \ReflectionMethod(CrawlerXFactory::class, 'defaultFetchChain');
            $chain = $factory->invoke(null);
            $result = $chain->fetch('https://example.test/page', $this->profile(), [FetchMethod::Flaresolverr]);

            self::assertTrue($result->ok);
            self::assertSame('<html><body>solved page</body></html>', $result->body);
            self::assertCount(1, ClientBuilder::recorded());
            $payload = json_decode(ClientBuilder::recorded()[0]->request->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(['url' => 'http://ssrf-egress.test:8080'], $payload['proxy']);
        } finally {
            ClientBuilder::clearFake();
            CrawlerXFactory::reset();
            $this->restoreEnvironment('FLARESOLVERR_URL', $originalUrl);
            $this->restoreEnvironment('CRAWLERX_FLARESOLVERR_PROXY_URL', $originalProxy);
            $this->restoreEnvironment('CRAWLERX_RESPECT_ROBOTS', $originalRespect);
        }
    }

    public function test_default_factory_skips_flaresolverr_without_an_egress_proxy(): void
    {
        $originalUrl = getenv('FLARESOLVERR_URL');
        $originalProxy = getenv('CRAWLERX_FLARESOLVERR_PROXY_URL');
        $originalRespect = getenv('CRAWLERX_RESPECT_ROBOTS');
        putenv('FLARESOLVERR_URL=https://flaresolverr.test/v1');
        putenv('CRAWLERX_FLARESOLVERR_PROXY_URL');
        putenv('CRAWLERX_RESPECT_ROBOTS=false');

        try {
            ClientBuilder::fake();
            CrawlerXFactory::reset();
            $factory = new \ReflectionMethod(CrawlerXFactory::class, 'defaultFetchChain');
            $chain = $factory->invoke(null);

            try {
                $chain->fetch('https://example.test/page', $this->profile(), [FetchMethod::Flaresolverr]);
                self::fail('FlareSolverr must not run without its SSRF egress proxy.');
            } catch (CrawlFetchException $exception) {
                self::assertSame('SSRF egress proxy unavailable', $exception->fetch?->attempts[0]['error']);
                self::assertCount(0, ClientBuilder::recorded());
            }
        } finally {
            ClientBuilder::clearFake();
            CrawlerXFactory::reset();
            $this->restoreEnvironment('FLARESOLVERR_URL', $originalUrl);
            $this->restoreEnvironment('CRAWLERX_FLARESOLVERR_PROXY_URL', $originalProxy);
            $this->restoreEnvironment('CRAWLERX_RESPECT_ROBOTS', $originalRespect);
        }
    }

    public function test_json_envelope_may_exceed_the_page_limit_when_the_decoded_page_fits(): void
    {
        $page = '<html><body>' . str_repeat('x', 90) . '</body></html>';
        $raw = $this->response($page);
        self::assertGreaterThan(128, strlen($raw));

        $result = $this->handler($raw)->fetch('https://example.test/page', $this->profile(), FetchMethod::Flaresolverr);

        self::assertTrue($result->ok);
        self::assertSame($page, $result->body);
    }

    public function test_decoded_page_larger_than_the_configured_limit_is_rejected(): void
    {
        $page = '<html><body>' . str_repeat('x', 150) . '</body></html>';

        $result = $this->handler($this->response($page))->fetch('https://example.test/page', $this->profile(), FetchMethod::Flaresolverr);

        self::assertFalse($result->ok);
        self::assertSame('response_too_large', $result->error);
    }

    public function test_guarded_chain_skips_flaresolverr_because_it_cannot_check_redirect_hops(): void
    {
        $handler = new class implements FetchMethodHandler {
            public int $calls = 0;

            public function supports(FetchMethod $method): bool
            {
                return $method === FetchMethod::Flaresolverr;
            }

            public function fetch(
                string $url,
                SiteProfileDto $profile,
                FetchMethod $method,
                ?CrawlOptionsDto $options = null,
            ): FetchResultDto {
                ++$this->calls;

                return new FetchResultDto(true, '<html><body>fetched</body></html>', 200, $method, 1, false, $url);
            }
        };
        $chain = new FetchFallbackChain(
            [FetchMethod::Flaresolverr->value => $handler],
            robotsGuard: new RobotsGuard(static fn(string $url): ?string => ''),
        );

        try {
            $chain->fetch('https://example.test/page', $this->profile(), [FetchMethod::Flaresolverr]);
            self::fail('The redirect-unaware handler should be skipped.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::Network, $exception->errorCode);
            self::assertSame('skipped', $exception->fetch?->attempts[0]['outcome']);
            self::assertSame('redirect guard unavailable', $exception->fetch?->attempts[0]['error']);
            self::assertSame(0, $handler->calls);
        }
    }

    private function handler(string $response): FlaresolverrFetchHandler
    {
        $client = new class ($response) implements ClientInterface {
            public function __construct(private readonly string $response)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response(200, [], $this->response);
            }
        };

        return new FlaresolverrFetchHandler(
            new FetchRuntimeConfig(flaresolverrUrl: 'http://flaresolverr.test/v1', maxResponseBytes: 128),
            $client,
        );
    }

    private function response(string $page): string
    {
        return json_encode([
            'status' => 'ok',
            'solution' => [
                'status' => 200,
                'url' => 'https://example.test/page',
                'response' => $page,
                'cookies' => [],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function restoreEnvironment(string $name, string|false $value): void
    {
        if (is_string($value)) {
            putenv($name . '=' . $value);

            return;
        }

        putenv($name);
    }

    private function profile(): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'flaresolverr-security-test',
            displayName: 'FlareSolverr security test',
            baseUrl: 'https://example.test',
            fetchProfile: FetchProfile::BrowserLikely,
            fetchChain: [FetchMethod::Flaresolverr],
            http: new HttpProfileDto(),
        );
    }
}
