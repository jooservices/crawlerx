<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponse;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\PlaywrightProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use PHPUnit\Framework\TestCase;

final class GuardedFallbackDefaultFactoryTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $environment = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'CRAWLERX_RESPECT_ROBOTS',
            'CRAWLERX_PLAYWRIGHT_SCRIPT',
            'PLAYWRIGHT_URL',
            'FLARESOLVERR_URL',
            'CRAWLERX_FLARESOLVERR_PROXY_URL',
        ] as $name) {
            $this->environment[$name] = getenv($name);
        }

        putenv('CRAWLERX_RESPECT_ROBOTS=true');
        putenv('PLAYWRIGHT_URL');
        ClientBuilder::clearFake();
        CrawlerXFactory::reset();
    }

    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        CrawlerXFactory::reset();
        foreach ($this->environment as $name => $value) {
            if (is_string($value)) {
                putenv($name . '=' . $value);
            } else {
                putenv($name);
            }
        }
        parent::tearDown();
    }

    public function test_default_factory_runs_an_allowed_browser_fallback_with_robots_enabled(): void
    {
        $script = tempnam(sys_get_temp_dir(), 'crawlerx-browser-fallback-');
        self::assertNotFalse($script);
        file_put_contents($script, <<<'JS'
            const fs = require('node:fs');
            const configPath = process.argv.find((argument) => argument.startsWith('--config='))?.slice(9);
            const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
            process.stdout.write(JSON.stringify({
                html: '<html><body>allowed browser fallback</body></html>',
                status: config.respectRobots === true ? 200 : 500,
                finalUrl: config.url,
            }));
            JS);
        putenv('CRAWLERX_PLAYWRIGHT_SCRIPT=' . $script);
        ClientBuilder::fake();

        try {
            $chain = $this->defaultChain();
            $result = $chain->fetch(
                'https://example.test/page',
                $this->profile(FetchMethod::Playwright),
                [FetchMethod::Playwright],
            );

            self::assertTrue($result->ok);
            self::assertSame(FetchMethod::Playwright, $result->methodUsed);
            self::assertSame('<html><body>allowed browser fallback</body></html>', $result->body);
            self::assertSame('ok', $result->attempts[0]['outcome']);
        } finally {
            unlink($script);
        }
    }

    public function test_default_factory_runs_flaresolverr_when_its_guarded_egress_proxy_is_configured(): void
    {
        putenv('FLARESOLVERR_URL=https://flaresolverr.test/v1');
        putenv('CRAWLERX_FLARESOLVERR_PROXY_URL=http://policy-egress.test:8080');
        ClientBuilder::fake();
        ClientBuilder::respond('POST', 'https://flaresolverr.test/v1', (new TestResponseSequence())
            ->push(TestResponse::make(200, [], $this->flareResponse())));

        $result = $this->defaultChain()->fetch(
            'https://example.test/page',
            $this->profile(FetchMethod::Flaresolverr),
            [FetchMethod::Flaresolverr],
        );

        self::assertTrue($result->ok);
        self::assertSame(FetchMethod::Flaresolverr, $result->methodUsed);
        self::assertSame('<html><body>allowed FlareSolverr fallback</body></html>', $result->body);
        self::assertCount(1, ClientBuilder::recorded());
        $payload = json_decode(
            ClientBuilder::recorded()[0]->request->getBody()->getContents(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertSame(['url' => 'http://policy-egress.test:8080'], $payload['proxy']);
    }

    private function defaultChain(): FetchFallbackChain
    {
        $factory = new \ReflectionMethod(CrawlerXFactory::class, 'defaultFetchChain');
        $chain = $factory->invoke(null);
        self::assertInstanceOf(FetchFallbackChain::class, $chain);

        return $chain;
    }

    private function flareResponse(): string
    {
        return json_encode([
            'status' => 'ok',
            'solution' => [
                'status' => 200,
                'url' => 'https://example.test/page',
                'response' => '<html><body>allowed FlareSolverr fallback</body></html>',
                'cookies' => [],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function profile(FetchMethod $method): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'guarded-fallback-test',
            displayName: 'Guarded fallback test',
            baseUrl: 'https://example.test',
            fetchProfile: FetchProfile::BrowserLikely,
            fetchChain: [$method],
            http: new HttpProfileDto(),
            playwright: new PlaywrightProfileDto(userAgent: 'CrawlerX guarded fallback test'),
        );
    }
}
