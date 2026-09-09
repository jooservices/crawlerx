<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\PerformerDirectory;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\AliasesPerformerCapabilities;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Contracts\PerformerDetailCapable;
use JOOservices\CrawlerX\Contracts\PerformerListingCapable;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;
use RuntimeException;

final class ManifestPerformerDirectoryCrawler extends AbstractBaseCrawler implements PerformerDetailCapable, PerformerListingCapable, UrlDetectCapable
{
    use AliasesPerformerCapabilities;
    use DetectsUrls;

    public function name(): string
    {
        return $this->definition()->slug;
    }

    public function listing(CrawlRequestDto $request): CrawlListResultDto
    {
        return (new PerformerListing($this->client, $this->definition()))->execute($request);
    }

    public function detail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new PerformerDetail($this->client, $this->definition()))->execute($request);
    }

    /** @return list<string> */
    protected function urlDetectHostPatterns(): array
    {
        return $this->definition()->hosts;
    }

    /** @return list<ImportMatchRule> */
    protected function urlDetectRules(): array
    {
        return [
            new ImportMatchRule(
                entity: ImportEntity::Performer,
                urlType: 'detail',
                crawlType: CrawlType::PerformerDetail,
                priority: 100,
                matcher: fn(string $url, string $path, string $query): bool => $this->definition()->externalId($url) !== null,
            ),
            new ImportMatchRule(
                entity: ImportEntity::Performer,
                urlType: 'listing',
                crawlType: CrawlType::PerformerListing,
                priority: 10,
                matcher: static fn(string $url, string $path, string $query): bool => true,
            ),
        ];
    }

    private function definition(): PerformerDirectoryDefinition
    {
        return match ($this->manifest()->slug) {
            'tpowers' => new PerformerDirectoryDefinition(
                slug: 'tpowers',
                label: 'T-Powers',
                listingUrl: 'https://www.t-powers.co.jp/talent/',
                hosts: ['t-powers.co.jp', 'www.t-powers.co.jp'],
                listingLinkSelector: 'a[href*="/talent/"]',
                detailUrlPattern: '~^https?://(?:www\\.)?t-powers\\.co\\.jp/talent/([^/?#]+)/?$~',
                titleSelectors: ['meta[property="og:title"]'],
                imageSelectors: [],
            ),
            'mines' => new PerformerDirectoryDefinition(
                slug: 'mines',
                label: 'Mine\'s',
                listingUrl: 'https://mines-pro.jp/model/',
                hosts: ['mines-pro.jp', 'www.mines-pro.jp'],
                listingLinkSelector: 'a[href^="/model/"]',
                detailUrlPattern: '#^https?://(?:www\\.)?mines-pro\\.jp/model/(\\d+)/?$#',
                titleSelectors: ['h1'],
                imageSelectors: ['.modelProfileLayout img'],
            ),
            'bstar' => new PerformerDirectoryDefinition(
                slug: 'bstar',
                label: 'Bstar',
                listingUrl: 'https://bstar-pro.com/models.html',
                hosts: ['bstar-pro.com', 'www.bstar-pro.com'],
                listingLinkSelector: 'a[href*="model.html?mid="]',
                detailUrlPattern: '#^https?://(?:www\\.)?bstar-pro\\.com/model\\.html\\?mid=(\\d+)(?:&|$)#',
                titleSelectors: ['.modelinfo_title', 'h2'],
                imageSelectors: ['img.modelpropic'],
            ),
            'sod' => new PerformerDirectoryDefinition(
                slug: 'sod',
                label: 'SOFT ON DEMAND',
                listingUrl: 'https://www.sod.co.jp/actress/',
                hosts: ['sod.co.jp', 'www.sod.co.jp'],
                listingLinkSelector: 'a[href^="/actress/"]',
                detailUrlPattern: '#^https?://(?:www\\.)?sod\\.co\\.jp/actress/([a-z0-9-]+)/?$#i',
                titleSelectors: ['h1'],
                imageSelectors: ['img.thumb'],
            ),
            default => throw new RuntimeException('Unsupported manifest performer directory: ' . $this->manifest()->slug),
        };
    }
}
