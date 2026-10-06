<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature\FetchLab;

use Faker\Factory;
use JOOservices\CrawlerX\Contracts\LoginCookieProvider;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\PlaywrightProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Exceptions\CrawlFetchException;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Handlers\FlaresolverrFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Fetch\Session\SessionStore;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class SessionStoreLabTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('CRAWLERX_FETCH_LAB') !== '1') {
            self::markTestSkipped('Set CRAWLERX_FETCH_LAB=1 to run the Compose fetch lab.');
        }
    }

    public function test_tc_se_01_flare_solution_is_replayed_by_five_http_fetches(): void
    {
        $flareCalls = 0;
        $chain = $this->chain(new SessionStore(), new class ($flareCalls) implements ClientInterface {
            public function __construct(private int &$calls)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                ++$this->calls;
                $payload = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
                $targetUrl = is_array($payload) && is_string($payload['url'] ?? null) ? $payload['url'] : 'https://fixture.test/';

                return new Response(200, [], json_encode([
                    'status' => 'ok',
                    'solution' => [
                        'status' => 200,
                        'url' => $targetUrl,
                        'response' => '<html><body>flare solved fixture response</body></html>',
                        'cookies' => [['name' => 'cf_clearance', 'value' => 'fixture-clearance']],
                        'userAgent' => 'CrawlerX-Fake-Flare/1.0',
                    ],
                ], JSON_THROW_ON_ERROR));
            }
        });

        self::assertTrue($chain->fetch($this->fixtureUrl('/cf-bound/TC-SE-01'), $this->profile(), [FetchMethod::Http, FetchMethod::Flaresolverr])->ok);
        for ($index = 0; $index < 5; ++$index) {
            self::assertTrue($chain->fetch($this->fixtureUrl('/cf-bound/TC-SE-01-' . $index), $this->profile(), [FetchMethod::Http, FetchMethod::Flaresolverr])->ok);
        }

        self::assertSame(1, $flareCalls);
    }

    public function test_tc_se_02_wrong_session_is_forgotten_before_a_new_flare_solve(): void
    {
        $sessions = new SessionStore();
        $sessions->put('fetch-lab', ['cf_clearance' => 'wrong'], 'Wrong-UA', 'flaresolverr');
        $flareCalls = 0;
        $chain = $this->chain($sessions, new class ($flareCalls) implements ClientInterface {
            public function __construct(private int &$calls)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                ++$this->calls;

                return new Response(200, [], json_encode([
                    'status' => 'ok',
                    'solution' => [
                        'status' => 200,
                        'url' => (string) $request->getUri(),
                        'response' => '<html><body>flare solved fixture response</body></html>',
                        'cookies' => [['name' => 'cf_clearance', 'value' => 'fixture-clearance']],
                        'userAgent' => 'CrawlerX-Fake-Flare/1.0',
                    ],
                ], JSON_THROW_ON_ERROR));
            }
        });

        self::assertTrue($chain->fetch($this->fixtureUrl('/cf-bound/TC-SE-02'), $this->profile(), [FetchMethod::Http, FetchMethod::Flaresolverr])->ok);
        self::assertSame(1, $flareCalls);
        self::assertSame('fixture-clearance', $sessions->get('fetch-lab')['cookies']['cf_clearance']);
    }

    public function test_tc_se_06_provider_cookie_authenticates_the_fixture(): void
    {
        $token = Factory::create()->sha256();
        $provider = new class ($token) implements LoginCookieProvider {
            public function __construct(private readonly string $token)
            {
            }

            public function cookiesFor(string $site): array
            {
                return ['remember_token' => $this->token];
            }
        };
        $chain = $this->chain(new SessionStore(), $this->nullClient(), $provider);

        self::assertTrue($chain->fetch($this->fixtureUrl('/login-wall/TC-SE-06'), $this->profile(), [FetchMethod::Http])->ok);
    }

    public function test_tc_se_07_missing_provider_cookie_is_auth_required(): void
    {
        $chain = $this->chain(new SessionStore(), $this->nullClient());

        try {
            $chain->fetch($this->fixtureUrl('/login-wall/TC-SE-07'), $this->profile(), [FetchMethod::Http]);
            self::fail('Expected auth_required.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::AuthRequired, $exception->errorCode);
            self::assertFalse($exception->retryable);
        }
    }

    private function chain(SessionStore $sessions, ClientInterface $flareClient, ?LoginCookieProvider $provider = null): FetchFallbackChain
    {
        $runtime = new FetchRuntimeConfig(
            flaresolverrUrl: $this->fixtureUrl('/__flare/v1'),
            userAgent: 'CrawlerX-Test-UA',
            userAgentPool: ['CrawlerX-Test-UA'],
        );
        $cookies = new CookieHandoffStore();

        return new FetchFallbackChain([
            FetchMethod::Http->value => new HttpFetchHandler(new ClientFactory(), $cookies, $sessions, $provider),
            FetchMethod::Flaresolverr->value => new FlaresolverrFetchHandler($runtime, $flareClient, $sessions, $provider),
        ], $cookies, $sessions, $provider, $runtime);
    }

    private function nullClient(): ClientInterface
    {
        return new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response(500);
            }
        };
    }

    private function fixtureUrl(string $path): string
    {
        $default = getenv('CI') === 'true' ? 'http://fixture-site:8080' : 'http://127.0.0.1:8080';

        return rtrim((string) (getenv('CRAWLERX_FIXTURE_SITE_URL') ?: $default), '/') . $path;
    }

    private function profile(): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'fetch-lab',
            displayName: 'Fetch Lab',
            baseUrl: $this->fixtureUrl('/'),
            fetchProfile: FetchProfile::Adaptive,
            fetchChain: [FetchMethod::Http, FetchMethod::Flaresolverr],
            http: new HttpProfileDto(headers: ['User-Agent' => 'CrawlerX-Test-UA']),
            playwright: new PlaywrightProfileDto(),
        );
    }
}
