<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Handlers;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Contracts\ProcessRunner;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\ChallengeDetector;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;

final class CurlImpersonateFetchHandler implements FetchMethodHandler
{
    public function __construct(
        private readonly FetchRuntimeConfig $runtime,
        private readonly ProcessRunner $runner,
        private readonly CookieHandoffStore $cookies = new CookieHandoffStore(),
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

        $command = [
            $binary,
            '--silent',
            '--show-error',
            '--location',
            '--max-time',
            (string) (($options !== null && $options->http !== null && $options->http->timeout !== null) ? $options->http->timeout : $profile->http->timeout),
            '--user-agent',
            $profile->http->headers['User-Agent'] ?? 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        ];

        $cookie = $this->cookies->cookieHeader($url);
        if ($cookie !== null) {
            $command[] = '--cookie';
            $command[] = $cookie;
        }

        $command[] = $url;

        $timeout = ($options !== null && $options->http !== null && $options->http->timeout !== null)
            ? $options->http->timeout
            : $profile->http->timeout;
        $result = $this->runner->run($command, $timeout + 15);
        $body = $result->stdout;
        $status = $result->exitCode === 0 ? 200 : 0;
        $challenge = ChallengeDetector::isChallenge($body, $status);
        $ok = $result->exitCode === 0 && ! $challenge && ChallengeDetector::isUsableBody($body, 200);

        return new FetchResultDto(
            ok: $ok,
            body: $body,
            status: $ok ? 200 : ($challenge ? 403 : $status),
            methodUsed: $method,
            elapsedMs: (int) round(microtime(true) * 1000) - $started,
            challengeDetected: $challenge,
            finalUrl: $url,
            error: $ok ? null : ($result->stderr !== '' ? $result->stderr : 'curl-impersonate failed'),
        );
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
