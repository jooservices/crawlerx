<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use Throwable;

final class FetchFallbackChain
{
    /**
     * @param  array<string, FetchMethodHandler>  $handlers
     */
    public function __construct(
        private readonly array $handlers,
        private readonly CookieHandoffStore $cookies = new CookieHandoffStore(),
    ) {
    }

    /**
     * @param  list<FetchMethod>  $plan
     */
    public function fetch(
        string $url,
        SiteProfileDto $profile,
        array $plan,
        ?CrawlOptionsDto $options = null,
    ): FetchResultDto {
        $attempts = [];
        $last = null;

        foreach ($plan as $method) {
            $handler = $this->handlers[$method->value] ?? null;
            if (! $handler instanceof FetchMethodHandler || ! $handler->supports($method)) {
                $attempts[] = $this->attempt($method, 0, 0, false, false, 'handler not registered');
                continue;
            }

            $started = (int) round(microtime(true) * 1000);

            try {
                $result = $handler->fetch($url, $profile, $method, $options);
            } catch (Throwable $exception) {
                $elapsed = (int) round(microtime(true) * 1000) - $started;
                $attempts[] = $this->attempt($method, $elapsed, 0, false, false, $exception->getMessage());
                continue;
            }

            $attempts[] = $this->attempt(
                $method,
                $result->elapsedMs,
                $result->status,
                $result->challengeDetected,
                $result->ok,
                $result->error,
            );
            $last = $result->withAttempts($attempts);

            if ($result->ok) {
                if ($profile->cookieHandoffAfterBrowser && $result->cookies !== []) {
                    $host = parse_url($result->finalUrl ?? $url, PHP_URL_HOST);
                    if (is_string($host) && $host !== '') {
                        $this->cookies->put($host, $result->cookies);
                    }
                }

                return $last;
            }
        }

        throw new CrawlBlockedException(
            message: 'All fetch methods exhausted for URL [' . $url . '].',
            fetch: $last?->toMeta() ?? new \JOOservices\CrawlerX\Dto\FetchMetaDto(
                methodUsed: ($plan[0] ?? FetchMethod::Http)->value,
                elapsedMs: 0,
                challengeDetected: true,
                attempts: $attempts,
            ),
        );
    }

    public function cookies(): CookieHandoffStore
    {
        return $this->cookies;
    }

    /**
     * @return array{method: string, elapsed_ms: int, status: int, challenge: bool, ok: bool, error?: string|null}
     */
    private function attempt(
        FetchMethod $method,
        int $elapsedMs,
        int $status,
        bool $challenge,
        bool $ok,
        ?string $error,
    ): array {
        $row = [
            'method' => $method->value,
            'elapsed_ms' => $elapsedMs,
            'status' => $status,
            'challenge' => $challenge,
            'ok' => $ok,
        ];

        if ($error !== null && $error !== '') {
            $row['error'] = $error;
        }

        return $row;
    }
}
