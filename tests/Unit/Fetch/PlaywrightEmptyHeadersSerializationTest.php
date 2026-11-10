<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\BrowserServiceProcessRunner;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Handlers\PlaywrightFamilyFetchHandler;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class PlaywrightEmptyHeadersSerializationTest extends TestCase
{
    public function test_empty_headers_are_serialized_as_an_object(): void
    {
        $script = tempnam(sys_get_temp_dir(), 'crawlerx-pw-empty-headers-');
        self::assertNotFalse($script);
        file_put_contents($script, '// test script');

        try {
            $client = new class implements ClientInterface {
                public mixed $extraHttpHeaders = null;

                public function sendRequest(RequestInterface $request): ResponseInterface
                {
                    $payload = json_decode((string) $request->getBody(), flags: JSON_THROW_ON_ERROR);
                    $config = is_object($payload) ? ($payload->config ?? null) : null;
                    $this->extraHttpHeaders = is_object($config) ? ($config->extraHttpHeaders ?? null) : null;

                    return new Response(200, [], json_encode([
                        'exitCode' => 0,
                        'stdout' => json_encode([
                            'status' => 200,
                            'finalUrl' => 'https://example.test/page',
                            'html' => '<html><body>page is ready</body></html>',
                            'challenge' => false,
                        ], JSON_THROW_ON_ERROR),
                        'stderr' => '',
                    ], JSON_THROW_ON_ERROR));
                }
            };

            $handler = new PlaywrightFamilyFetchHandler(
                new FetchRuntimeConfig(playwrightScript: $script),
                new BrowserServiceProcessRunner('https://browser.example.test', $client),
            );
            $profile = new SiteProfileDto(
                slug: 'example',
                displayName: 'Example',
                baseUrl: 'https://example.test',
                fetchProfile: FetchProfile::BrowserLikely,
                fetchChain: FetchMethod::browserChain(),
                http: new HttpProfileDto(),
            );

            $result = $handler->fetch('https://example.test/page', $profile, FetchMethod::Playwright);

            self::assertTrue($result->ok);
            self::assertInstanceOf(\stdClass::class, $client->extraHttpHeaders);
            self::assertSame([], get_object_vars($client->extraHttpHeaders));
        } finally {
            unlink($script);
        }
    }
}
