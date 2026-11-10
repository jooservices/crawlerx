<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Request;

use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;

/**
 * Everything about one fetch that stays fixed while the chain walks its methods.
 */
final readonly class FetchRequest
{
    /**
     * @param  list<string>  $readyMarkers
     * @param  array{cookies: array<string, string>, userAgent: ?string, source: string, expiresAt: int, storageState: array<string, mixed>|null, challengeCount: int}|null  $session
     * @param  array<string, string>  $loginCookies
     */
    public function __construct(
        public string $url,
        public SiteProfileDto $profile,
        public ?CrawlOptionsDto $options,
        public array $readyMarkers,
        public ?array $session,
        public array $loginCookies,
        public ?string $userAgent,
    ) {
    }

    /** Same request after a challenge invalidated the session: no session cookies, a fresh User-Agent. */
    public function withoutSession(?string $userAgent): self
    {
        return new self($this->url, $this->profile, $this->options, $this->readyMarkers, null, $this->loginCookies, $userAgent);
    }
}
