<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Handlers;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Contracts\LoginCookieProvider;
use JOOservices\CrawlerX\Contracts\ProcessRunner;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\ChallengeDetector;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Guard\RobotsGuard;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Fetch\Session\SessionStore;
use Symfony\Component\DomCrawler\UriResolver;
use Throwable;

final class CurlImpersonateFetchHandler implements FetchMethodHandler
{
    public function __construct(
        private readonly FetchRuntimeConfig $runtime,
        private readonly ProcessRunner $runner,
        private readonly CookieHandoffStore $cookies = new CookieHandoffStore(),
        private readonly ?SessionStore $sessions = null,
        private readonly ?LoginCookieProvider $logins = null,
        private readonly ?RobotsGuard $robotsGuard = null,
    ) {
    }

    public function supports(FetchMethod $method): bool
    {
        return $method === FetchMethod::CurlImpersonate;
    }

    public function fetch(
        string $url,
        SiteProfileDto $profile,
        FetchMethod $method,
        ?CrawlOptionsDto $options = null,
    ): FetchResultDto {
        $started = (int) round(microtime(true) * 1000);
        $binary = $this->runtime->curlImpersonateBinary;
        if ($binary === null || $binary === '') {
            return $this->fail($method, $started, $url, 'curl-impersonate binary not found');
        }

        $userAgent = $this->sessions?->userAgent($profile->slug)
            ?? $profile->http->headers['User-Agent']
            ?? $this->runtime->userAgent
            ?? 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
        $cookie = $this->cookies->cookieHeader($url);
        if ($cookie === null && isset($profile->http->headers['Cookie'])) {
            $cookie = $profile->http->headers['Cookie'];
        }
        $sessionCookie = $this->sessions?->cookieHeader(
            $profile->slug,
            $this->logins?->cookiesFor($profile->slug) ?? [],
        );
        if ($sessionCookie !== null) {
            $cookie = $sessionCookie;
        }
        $timeout = ($options !== null && $options->http !== null && $options->http->timeout !== null)
            ? $options->http->timeout
            : $profile->http->timeout;
        $requestTimeout = min($timeout, $options->methodTimeoutSeconds ?? $timeout);
        $respectRobots = $profile->respectRobots ?? $this->runtime->respectRobots;
        $headerFile = tempnam(sys_get_temp_dir(), 'crawlerx-curl-headers-');
        $cookieJar = tempnam(sys_get_temp_dir(), 'crawlerx-curl-cookies-');
        if ($headerFile === false || $cookieJar === false) {
            if (is_string($headerFile) && is_file($headerFile)) {
                unlink($headerFile);
            }
            if (is_string($cookieJar) && is_file($cookieJar)) {
                unlink($cookieJar);
            }

            return $this->fail($method, $started, $url, 'unable to create temporary curl files');
        }

        $currentUrl = $url;
        $result = null;
        $status = 0;
        try {
            for ($redirects = 0; $redirects <= 10; $redirects++) {
                if ($respectRobots) {
                    $this->robotsGuard?->assertAllowed($currentUrl, $userAgent);
                }
                $remainingTimeout = $requestTimeout - (int) floor((microtime(true) * 1000 - $started) / 1000);
                if ($remainingTimeout < 1) {
                    return $this->fail($method, $started, $url, 'curl-impersonate timed out');
                }

                $command = [
                    $binary,
                    '--silent',
                    '--show-error',
                    '--compressed',
                    '--noproxy',
                    '*',
                    '--max-time',
                    (string) $remainingTimeout,
                    '--user-agent',
                    $userAgent,
                    '--dump-header',
                    $headerFile,
                    '--cookie-jar',
                    $cookieJar,
                ];
                if (is_file($cookieJar) && filesize($cookieJar) > 0) {
                    $command[] = '--cookie';
                    $command[] = $cookieJar;
                }
                if ($cookie !== null) {
                    $command[] = '--cookie';
                    $command[] = $cookie;
                }
                $command[] = $currentUrl;

                $result = $this->runner->run($command, $remainingTimeout);
                if (str_contains($result->stderr, 'process output exceeded the configured size limit.')) {
                    return $this->fail($method, $started, $url, 'response_too_large');
                }
                if (strlen($result->stdout) > $this->runtime->maxResponseBytes) {
                    return $this->fail($method, $started, $url, 'response_too_large');
                }
                if ($result->exitCode !== 0) {
                    return $this->fail($method, $started, $url, $result->stderr !== '' ? $result->stderr : 'curl-impersonate failed');
                }

                $headers = $this->responseHeaders($headerFile);
                $status = $headers['status'];
                $location = $headers['location'];
                if ($status < 300 || $status >= 400 || $location === null) {
                    break;
                }
                if ($redirects === 10) {
                    return $this->fail($method, $started, $url, 'too many redirects');
                }

                try {
                    $nextUrl = UriResolver::resolve($location, $currentUrl);
                } catch (Throwable) {
                    return $this->fail($method, $started, $url, 'invalid redirect URL');
                }
                if (! $this->sameOrigin($currentUrl, $nextUrl)) {
                    return $this->fail($method, $started, $url, 'redirect changed origin');
                }

                $currentUrl = $nextUrl;
            }
        } finally {
            unlink($headerFile);
            unlink($cookieJar);
        }

        if ($result === null) {
            return $this->fail($method, $started, $url, 'curl-impersonate failed');
        }

        $body = $result->stdout;
        $challenge = ChallengeDetector::isChallenge($body, $status);
        $ok = ! $challenge && ChallengeDetector::isUsableBody($body, $status);

        return new FetchResultDto(
            ok: $ok,
            body: $body,
            status: $ok ? 200 : ($challenge ? 403 : $status),
            methodUsed: $method,
            elapsedMs: (int) round(microtime(true) * 1000) - $started,
            challengeDetected: $challenge,
            finalUrl: $currentUrl,
            error: $ok ? null : ($challenge ? 'challenge page' : 'unusable HTTP body'),
        );
    }

    /** @return array{status: int, location: string|null} */
    private function responseHeaders(string $path): array
    {
        clearstatcache(true, $path);
        if (! is_file($path) || filesize($path) > 65_536) {
            return ['status' => 0, 'location' => null];
        }

        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            return ['status' => 0, 'location' => null];
        }

        $status = 0;
        $location = null;
        $lines = preg_split('/\r?\n/', $contents);
        if (! is_array($lines)) {
            return ['status' => 0, 'location' => null];
        }
        foreach ($lines as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', $line, $matches) === 1) {
                $status = (int) $matches[1];
                $location = null;
            } elseif (preg_match('/^Location:\s*(.+?)\s*$/i', $line, $matches) === 1) {
                $location = $matches[1];
            }
        }

        return ['status' => $status, 'location' => $location];
    }

    private function sameOrigin(string $left, string $right): bool
    {
        $leftParts = parse_url($left);
        $rightParts = parse_url($right);
        if (! is_array($leftParts) || ! is_array($rightParts)) {
            return false;
        }

        $leftScheme = is_string($leftParts['scheme'] ?? null) ? strtolower($leftParts['scheme']) : '';
        $rightScheme = is_string($rightParts['scheme'] ?? null) ? strtolower($rightParts['scheme']) : '';
        $leftHost = is_string($leftParts['host'] ?? null) ? strtolower($leftParts['host']) : '';
        $rightHost = is_string($rightParts['host'] ?? null) ? strtolower($rightParts['host']) : '';
        $leftPort = $leftParts['port'] ?? ($leftScheme === 'https' ? 443 : 80);
        $rightPort = $rightParts['port'] ?? ($rightScheme === 'https' ? 443 : 80);

        return in_array($leftScheme, ['http', 'https'], true)
            && $leftScheme === $rightScheme
            && $leftHost !== ''
            && $leftHost === $rightHost
            && $leftPort === $rightPort
            && ! isset($rightParts['user'])
            && ! isset($rightParts['pass']);
    }

    private function fail(FetchMethod $method, int $started, string $url, string $error): FetchResultDto
    {
        return new FetchResultDto(
            ok: false,
            body: '',
            status: 0,
            methodUsed: $method,
            elapsedMs: (int) round(microtime(true) * 1000) - $started,
            challengeDetected: false,
            finalUrl: $url,
            error: $error,
        );
    }
}
