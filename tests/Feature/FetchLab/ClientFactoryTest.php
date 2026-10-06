<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature\FetchLab;

use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class ClientFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('CRAWLERX_FETCH_LAB') !== '1') {
            self::markTestSkipped('Set CRAWLERX_FETCH_LAB=1 to run the Compose fetch lab.');
        }
    }

    public function test_tc_ht_01_reuses_one_connection_for_twenty_fetches_to_one_host(): void
    {
        $this->fixtureJson('/__reset', 'POST');
        $handler = new HttpFetchHandler(new ClientFactory());
        $profile = $this->profile();

        for ($index = 0; $index < 20; $index++) {
            $result = $handler->fetch(
                $this->fixtureUrl(sprintf('/static/movie/TC-HT-01-%d', $index)),
                $profile,
                FetchMethod::Http,
            );

            self::assertTrue($result->ok, (string) $result->error);
        }

        $metrics = $this->fixtureJson('/__hits?connections=1');

        self::assertSame(20, $metrics['hits']['/static/movie/:id']);
        self::assertCount(1, $metrics['connections']['/static/movie/:id']);
    }

    public function test_tc_ht_02_decodes_gzip_fixture_response(): void
    {
        $result = (new HttpFetchHandler(new ClientFactory()))->fetch(
            $this->fixtureUrl('/gzip/movie/TC-HT-02'),
            $this->profile(),
            FetchMethod::Http,
        );

        self::assertTrue($result->ok, (string) $result->error);
        self::assertSame(200, $result->status);
        self::assertStringContainsString('gzip movie TC-HT-02', $result->body);
    }

    public function test_request_headers_do_not_leak_between_reused_site_clients(): void
    {
        $handler = new HttpFetchHandler(new ClientFactory());
        $authenticated = $handler->fetch(
            $this->fixtureUrl('/age-gate/TC-HT-COOKIE'),
            $this->profile(['Cookie' => 'legal_age=1']),
            FetchMethod::Http,
        );
        $anonymous = $handler->fetch(
            $this->fixtureUrl('/age-gate/TC-HT-ANONYMOUS'),
            $this->profile(),
            FetchMethod::Http,
        );

        self::assertTrue($authenticated->ok, (string) $authenticated->error);
        self::assertFalse($anonymous->ok);
        self::assertSame(403, $anonymous->status);
    }

    /** @param array<string, string> $headers */
    private function profile(array $headers = []): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'fetch-lab',
            displayName: 'Fetch Lab',
            baseUrl: $this->fixtureUrl('/'),
            fetchProfile: FetchProfile::HttpOnly,
            fetchChain: [FetchMethod::Http],
            http: new HttpProfileDto(headers: $headers),
        );
    }

    /** @return array<string, mixed> */
    private function fixtureJson(string $path, string $method = 'GET'): array
    {
        $context = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true]]);
        $body = file_get_contents($this->fixtureUrl($path), false, $context);
        self::assertIsString($body);

        $data = json_decode($body, true);
        self::assertIsArray($data);

        return $data;
    }

    private function fixtureUrl(string $path): string
    {
        $default = getenv('CI') === 'true' ? 'http://fixture-site:8080' : 'http://127.0.0.1:8080';

        return rtrim((string) (getenv('CRAWLERX_FIXTURE_SITE_URL') ?: $default), '/') . $path;
    }

}
