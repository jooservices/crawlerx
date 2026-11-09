<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponse;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\Support\LocalHttpServer;
use PHPUnit\Framework\TestCase;

final class HttpFetchHandlerProxySecurityTest extends TestCase
{
    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_client_factory_keeps_the_callers_guarded_redirect_policy_when_a_proxy_is_configured(): void
    {
        self::assertSame(['track_redirects' => true], $this->redirectPolicy([
            'proxy' => 'http://proxy.example:8080',
            'allow_redirects' => ['track_redirects' => true],
        ]));
    }

    public function test_direct_http_requests_keep_redirect_tracking_enabled(): void
    {
        $originalProxy = getenv('CRAWLERX_HTTP_PROXY');
        putenv('CRAWLERX_HTTP_PROXY');
        try {
            self::assertSame(
                ['track_redirects' => true],
                $this->redirectPolicy(['allow_redirects' => ['track_redirects' => true]]),
            );
        } finally {
            if (is_string($originalProxy)) {
                putenv('CRAWLERX_HTTP_PROXY=' . $originalProxy);
            } else {
                putenv('CRAWLERX_HTTP_PROXY');
            }
        }
    }

    public function test_transport_rejects_cross_origin_redirects_for_proxy_clients_without_a_handler_callback(): void
    {
        $requestLog = sys_get_temp_dir() . '/crawlerx-proxy-client-redirect-' . bin2hex(random_bytes(8));
        $proxy = LocalHttpServer::start(sprintf(
            <<<'PHP'
            <?php
            $target = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            file_put_contents(%s, $target . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($target === '/start') {
                header('Location: http://public.example:8080/private', true, 302);
                exit;
            }
            header('Content-Type: text/html; charset=utf-8');
            echo '<html><body>private response</body></html>';
            PHP,
            var_export($requestLog, true),
        ));

        try {
            $exception = null;
            try {
                (new ClientFactory())->factory([
                    'proxy' => $proxy->url('/'),
                    'allow_redirects' => ['track_redirects' => true],
                    'max_response_bytes' => 1024,
                ])->get('http://public.example/start');
            } catch (\Throwable $caught) {
                $exception = $caught;
            }

            self::assertNotNull($exception);
            self::assertSame(['/start'], file($requestLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        } finally {
            $proxy->stop();
            if (is_file($requestLog)) {
                unlink($requestLog);
            }
        }
    }

    public function test_authorization_headers_do_not_follow_redirects_automatically(): void
    {
        $result = $this->fetchResult(
            headers: ['Accept' => 'text/html', 'Authorization' => 'Bearer secret'],
            status: 302,
        );

        self::assertFalse($result->ok);
        self::assertSame('redirect_not_followed', $result->error);
    }

    public function test_a_proxied_redirect_body_is_not_returned_as_a_successful_page(): void
    {
        $result = $this->fetchResult(
            proxy: 'http://proxy.example:8080',
            status: 302,
        );

        self::assertFalse($result->ok);
        self::assertSame(302, $result->status);
        self::assertSame('redirect_not_followed', $result->error);
    }

    public function test_proxy_credentials_are_not_exposed_in_fetch_errors(): void
    {
        $url = 'https://example.test/proxy-error';
        $result = $this->fetchResult(
            url: $url,
            proxy: 'http://proxy-user:proxy-secret@proxy.example:8080',
            response: new \RuntimeException('Unable to connect through http://proxy-user:proxy-secret@proxy.example:8080'),
        );

        self::assertSame('fetch_error', $result->error);
        self::assertStringNotContainsString('proxy-secret', $result->error ?? '');
    }

    private function redirectPolicy(array $options): mixed
    {
        $method = new \ReflectionMethod(ClientFactory::class, 'allowRedirects');

        return $method->invoke(new ClientFactory(), $options);
    }

    /** @param array<string, string> $headers */
    private function fetchResult(
        string $url = 'https://example.test/redirect',
        ?string $proxy = null,
        array $headers = ['Accept' => 'text/html'],
        int $status = 200,
        mixed $response = null,
    ): FetchResultDto {
        ClientBuilder::fake();
        $response ??= TestResponse::make(
            $status,
            $status >= 300 && $status < 400 ? ['Location' => 'https://redirect.example.test/page'] : [],
            '<html><body>usable response for redirect test</body></html>',
        );
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())->push($response));
        $handler = new HttpFetchHandler(new ClientFactory(), new CookieHandoffStore());

        return $handler->fetch($url, $this->profile($proxy, $headers), FetchMethod::Http);
    }

    /** @param array<string, string> $headers */
    private function profile(?string $proxy, array $headers = ['Accept' => 'text/html']): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'test',
            displayName: 'Test',
            baseUrl: 'https://example.test',
            fetchProfile: FetchProfile::HttpOnly,
            fetchChain: [FetchMethod::Http],
            http: new HttpProfileDto(headers: $headers, proxy: $proxy),
        );
    }
}
