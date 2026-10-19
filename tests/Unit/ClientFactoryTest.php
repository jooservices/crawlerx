<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Client\HttpClient;
use JOOservices\Client\Dto\ClientConfig;
use JOOservices\Client\Exceptions\TimeoutException;
use JOOservices\Client\Testing\TestResponse;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\Http\ClientCrawlHttpClient;
use JOOservices\CrawlerX\Http\PrefetchedCrawlHttpClient;
use JOOservices\CrawlerX\Services\ClientFactory;
use PHPUnit\Framework\TestCase;
use Throwable;

final class ClientFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_applies_headers_to_built_client(): void
    {
        $this->respond('https://example.test/items');

        (new ClientFactory())->factory([
            'headers' => ['X-Crawler' => 'crawler-test'],
        ])->get('https://example.test/items');

        $recorded = ClientBuilder::lastRequest();
        self::assertNotNull($recorded);
        self::assertSame('crawler-test', $recorded->request->getHeaderLine('X-Crawler'));
    }

    public function test_applies_timeout_to_built_client(): void
    {
        $config = $this->clientConfig(['timeout' => 0.25]);

        self::assertSame(0.25, $config->timeout);
    }

    public function test_configures_connect_timeout_and_compression_by_default(): void
    {
        $config = $this->clientConfig([]);

        self::assertSame(5.0, $config->connectTimeout);
        self::assertTrue($config->compression);
    }

    public function test_keeps_request_headers_out_of_client_configuration(): void
    {
        $config = $this->clientConfig([
            'headers' => ['Cookie' => 'session=private'],
        ]);

        self::assertSame([], $config->headers);
    }

    public function test_reuses_clients_for_matching_site_and_base_uri_options(): void
    {
        $factory = new ClientFactory();
        $baseOptions = ['base_uri' => 'https://example.test/api/'];
        $first = $this->httpClient($factory->factory($baseOptions, 'example-site'));
        $sameOptions = $this->httpClient($factory->factory($baseOptions, 'example-site'));
        $differentBaseUri = $this->httpClient($factory->factory(
            ['base_uri' => 'https://example.test/v2/'],
            'example-site',
        ));

        self::assertSame($first, $sameOptions);
        self::assertNotSame($first, $differentBaseUri);
    }

    public function test_enforces_timeout_against_a_slow_local_endpoint(): void
    {
        $directory = sys_get_temp_dir() . '/crawlerx-client-timeout-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($directory));
        $router = $directory . '/router.php';
        file_put_contents($router, "<?php\nusleep(2_000_000);\necho 'slow';\n");

        $port = $this->freePort();
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", $router],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $directory,
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        try {
            $this->waitForEndpoint($port);
            $client = (new ClientFactory())->factory([
                'base_uri' => "http://127.0.0.1:{$port}/",
                'timeout' => 1,
            ]);
            $started = microtime(true);
            $exception = null;

            try {
                $client->get('/slow');
            } catch (Throwable $caught) {
                $exception = $caught;
            }

            self::assertInstanceOf(TimeoutException::class, $exception);
            self::assertLessThan(1.75, microtime(true) - $started);
        } finally {
            proc_terminate($process);
            foreach ([$pipes[1], $pipes[2]] as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($process);
            unlink($router);
            rmdir($directory);
        }
    }

    public function test_resolves_relative_request_uri_against_base_uri(): void
    {
        $this->respond('https://example.test/api/items');

        (new ClientFactory())->factory([
            'base_uri' => 'https://example.test/api/',
        ])->get('items');

        $recorded = ClientBuilder::lastRequest();
        self::assertNotNull($recorded);
        self::assertSame('https://example.test/api/items', (string) $recorded->request->getUri());
    }

    public function test_applies_verify_ssl_false_to_built_client(): void
    {
        self::assertFalse($this->clientConfig(['verify_ssl' => false])->verifySsl);
    }

    public function test_skips_non_scalar_headers_and_keeps_scalar_headers(): void
    {
        $this->respond('https://example.test/items');

        (new ClientFactory())->factory([
            'headers' => [
                'X-Keep' => 'keep',
                'X-Skip' => ['nested'],
            ],
        ])->get('https://example.test/items');

        $recorded = ClientBuilder::lastRequest();
        self::assertNotNull($recorded);
        self::assertSame('keep', $recorded->request->getHeaderLine('X-Keep'));
        self::assertFalse($recorded->request->hasHeader('X-Skip'));
    }

    public function test_returns_prefetched_client_with_prefetched_html(): void
    {
        $client = (new ClientFactory())->factory([
            'prefetched_html' => '<html><body>prefetched</body></html>',
            'prefetched_status' => 201,
            'prefetched_final_url' => 'https://example.test/final',
        ]);

        self::assertInstanceOf(PrefetchedCrawlHttpClient::class, $client);

        $response = $client->get('https://example.test/items')->toPsrResponse();
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('<html><body>prefetched</body></html>', (string) $response->getBody());
        self::assertSame('https://example.test/final', $client->finalUrl());
    }

    private function respond(string $url): void
    {
        ClientBuilder::fake();
        ClientBuilder::respond(
            'GET',
            $url,
            (new TestResponseSequence())->push(TestResponse::make(200, [], '<html><body>usable</body></html>')),
        );
    }

    /** @param array<string, mixed> $options */
    private function clientConfig(array $options): ClientConfig
    {
        $client = (new ClientFactory())->factory($options);
        self::assertInstanceOf(ClientCrawlHttpClient::class, $client);

        $httpClient = $this->httpClient($client);

        $config = (new \ReflectionProperty(HttpClient::class, 'config'))->getValue($httpClient);
        self::assertInstanceOf(ClientConfig::class, $config);

        return $config;
    }

    private function httpClient(\JOOservices\CrawlerX\Contracts\CrawlHttpClient $client): HttpClient
    {
        self::assertInstanceOf(ClientCrawlHttpClient::class, $client);

        $httpClient = (new \ReflectionProperty(ClientCrawlHttpClient::class, 'httpClient'))->getValue($client);
        self::assertInstanceOf(HttpClient::class, $httpClient);

        return $httpClient;
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        self::assertIsResource($socket, $errorMessage);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        self::assertIsString($address);

        return (int) substr($address, (int) strrpos($address, ':') + 1);
    }

    private function waitForEndpoint(int $port): void
    {
        $deadline = microtime(true) + 3.0;
        do {
            $socket = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 0.1);
            if (is_resource($socket)) {
                fclose($socket);

                return;
            }

            usleep(10_000);
        } while (microtime(true) < $deadline);

        self::fail(sprintf('Local slow endpoint did not start: %s (%d)', $errorMessage, $errorCode));
    }
}
