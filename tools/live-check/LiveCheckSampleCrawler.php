<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\LiveCheck;

use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\CrawlOutcomeDto;
use JOOservices\CrawlerX\Dto\FetchOptionsDto;
use JOOservices\CrawlerX\Dto\HttpOptionsDto;
use JOOservices\CrawlerX\Enums\FetchProfile;
use Throwable;

final class LiveCheckSampleCrawler
{
    public function __construct(
        private readonly LiveCheckRequestLimiter $requestLimiter,
        private readonly ?string $proxy = null,
    ) {
    }

    /** Crawls one selected sample with the live-check request limits applied.
     *
     * @param array{slug: string, type: \JOOservices\CrawlerX\Enums\CrawlType, url: string} $sample
     */
    public function crawl(array $sample): CrawlOutcomeDto
    {
        try {
            $outcome = $this->requestLimiter->forAdapter(
                $sample['slug'],
                fn(): CrawlOutcomeDto => CrawlerX::url($sample['url'])
                    ->site($sample['slug'])
                    ->type($sample['type'])
                    ->options(new CrawlOptionsDto(
                        http: new HttpOptionsDto(proxy: $this->proxy),
                        fetch: new FetchOptionsDto(profile: FetchProfile::HttpOnly),
                    ))
                    ->tryCrawl(),
            );
        } catch (Throwable $exception) {
            $this->throwIfRequestWasNotChecked($sample['slug'], $exception);

            throw $exception;
        }

        $this->throwIfRedirectWasNotChecked($sample['slug']);
        if ($this->requestLimiter->wasBudgetExceeded($sample['slug']) && ! $outcome->ok) {
            throw new LiveCheckRequestLimitException();
        }

        return $outcome;
    }

    /** @throws LiveCheckRequestLimitException */
    private function throwIfRequestWasNotChecked(string $slug, Throwable $previous): void
    {
        $this->throwIfRedirectWasNotChecked($slug, $previous);
        if ($this->requestLimiter->wasBudgetExceeded($slug)) {
            throw new LiveCheckRequestLimitException(previous: $previous);
        }
    }

    /** @throws LiveCheckRequestLimitException */
    private function throwIfRedirectWasNotChecked(string $slug, ?Throwable $previous = null): void
    {
        if ($this->requestLimiter->wasRedirectUnsupported($slug)) {
            throw new LiveCheckRequestLimitException(reason: 'redirect_unsupported', previous: $previous);
        }
    }
}
