<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponse;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Contracts\DetailCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Services\AdapterExecutor;
use JOOservices\CrawlerX\Services\ClientFactory;
use PHPUnit\Framework\TestCase;

final class SeededCaptchaUnwrappedContentCrawlTest extends TestCase
{
    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_adapter_uses_a_successful_browser_seed_with_unwrapped_page_content_and_captcha_form(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/gallery/1';
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())
            ->push(TestResponse::make(403, [], 'plain HTTP fallback must not replace the seed')));
        $body = '<html><body><h1>Gallery</h1><p>Article content remains available.</p>'
            . '<form><div class="g-recaptcha" data-sitekey="example"></div></form></body></html>';
        $fetch = new FetchResultDto(
            ok: true,
            body: $body,
            status: 200,
            methodUsed: FetchMethod::Playwright,
            elapsedMs: 1,
            challengeDetected: false,
            finalUrl: $url,
        );

        $result = (new AdapterExecutor())->detail(
            new SeededCaptchaUnwrappedContentAdapter(new ClientFactory()),
            new CrawlRequestDto(url: $url, type: CrawlType::Detail, fetch: $fetch),
        );

        self::assertSame($body, $result->meta['body']);
        self::assertCount(0, ClientBuilder::recorded());
    }
}

final class SeededCaptchaUnwrappedContentAdapter extends AbstractBaseCrawler implements DetailCapable
{
    public function name(): string
    {
        return 'seeded-captcha-unwrapped-content';
    }

    public function detail(CrawlRequestDto $request): CrawlItemResultDto
    {
        $body = (string) $this->client->get($request->url)->toPsrResponse()->getBody();

        return new CrawlItemResultDto($request->url, 'test', ['body' => $body]);
    }
}
