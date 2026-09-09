<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Handlers;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Contracts\ProcessRunner;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\PlaywrightProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\ChallengeDetector;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;

final class PuppeteerStealthFetchHandler implements FetchMethodHandler
{
    public function __construct(
        private readonly FetchRuntimeConfig $runtime,
        private readonly ProcessRunner $runner,
    ) {
    }

    public function supports(FetchMethod $method): bool
    {
        return $method === FetchMethod::PuppeteerStealth;
    }

    public function fetch(
        string $url,
        SiteProfileDto $profile,
        FetchMethod $method,
        ?CrawlOptionsDto $options = null,
    ): FetchResultDto {
        $started = (int) round(microtime(true) * 1000);
        $script = $this->runtime->puppeteerScript;
        if ($script === '' || ! is_file($script)) {
            return $this->fail($method, $started, $url, 'Puppeteer script not found');
        }

        $playwright = $profile->playwright ?? new PlaywrightProfileDto();
        $configPath = tempnam(sys_get_temp_dir(), 'crawlerx-pptr-');
        if ($configPath === false) {
            return $this->fail($method, $started, $url, 'Unable to create Puppeteer config tempfile');
        }

        file_put_contents($configPath, json_encode([
            'url' => $url,
            'waitMs' => $playwright->postWaitMs,
            'headless' => $playwright->headless,
            'navigationTimeoutMs' => $playwright->navigationTimeoutMs,
            'userAgent' => $playwright->userAgent,
        ], JSON_THROW_ON_ERROR));

        try {
            $result = $this->runner->run(
                [$this->runtime->nodeBinary, $script, '--config=' . $configPath],
                (int) ceil($playwright->navigationTimeoutMs / 1000) + 30,
            );
        } finally {
            if (is_file($configPath)) {
                unlink($configPath);
            }
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($result->stdout, true);
        if (! is_array($decoded)) {
            return $this->fail($method, $started, $url, $result->stderr !== '' ? $result->stderr : 'invalid puppeteer JSON');
        }

        $body = is_string($decoded['html'] ?? null) ? $decoded['html'] : '';
        $status = is_numeric($decoded['status'] ?? null) ? (int) $decoded['status'] : 0;
        $challenge = (bool) ($decoded['challenge'] ?? false) || ChallengeDetector::isChallenge($body, $status);
        $ok = $result->exitCode === 0 && ! $challenge && ChallengeDetector::isUsableBody($body, $status > 0 ? $status : 200);
        $cookies = $this->cookieMap(is_array($decoded['cookies'] ?? null) ? $decoded['cookies'] : []);

        return new FetchResultDto(
            ok: $ok,
            body: $body,
            status: $status > 0 ? $status : ($ok ? 200 : 0),
            methodUsed: $method,
            elapsedMs: (int) round(microtime(true) * 1000) - $started,
            challengeDetected: $challenge,
            finalUrl: is_string($decoded['finalUrl'] ?? null) ? $decoded['finalUrl'] : $url,
            cookies: $cookies,
            error: $ok ? null : (is_string($decoded['error'] ?? null) ? $decoded['error'] : 'puppeteer fetch failed'),
        );
    }

    /**
     * @param  array<mixed>  $cookies
     * @return array<string, string>
     */
    private function cookieMap(array $cookies): array
    {
        $map = [];
        foreach ($cookies as $cookie) {
            if (! is_array($cookie)) {
                continue;
            }

            $name = $cookie['name'] ?? null;
            $value = $cookie['value'] ?? null;
            if (is_string($name) && $name !== '' && is_string($value)) {
                $map[$name] = $value;
            }
        }

        return $map;
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
