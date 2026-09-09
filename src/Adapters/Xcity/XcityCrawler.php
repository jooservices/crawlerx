<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Xcity;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\XcityUrlDetectRules;
use JOOservices\CrawlerX\Adapters\Xcity\Types\Detail;
use JOOservices\CrawlerX\Adapters\Xcity\Types\Listing;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerIndex;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerKana;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerListing;
use JOOservices\CrawlerX\Contracts\PerformerDetailCapable;
use JOOservices\CrawlerX\Contracts\PerformerListingCapable;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use RuntimeException;

final class XcityCrawler extends AbstractBaseCrawler implements PerformerDetailCapable, PerformerListingCapable, UrlDetectCapable
{
    use DetectsUrls;
    use XcityUrlDetectRules;

    public function name(): string
    {
        return 'xcity';
    }

    /**
     * @return list<array{match: string|callable(CrawlRequestDto): bool, type: class-string<TypeInterface>}>
     */
    protected function listingRoutes(): array
    {
        return [
            ['match' => '/idol/detail/', 'type' => Listing::class],
            ['match' => '?ini=', 'type' => PerformerListing::class],
            ['match' => '?kana=', 'type' => PerformerKana::class],
            ['match' => fn(CrawlRequestDto $request): bool => $this->isIdolIndexUrl($request->url), 'type' => PerformerIndex::class],
        ];
    }

    /**
     * @return list<array{match: string, type: class-string<TypeInterface>}>
     */
    protected function detailRoutes(): array
    {
        return [
            ['match' => '/idol/detail/', 'type' => PerformerDetail::class],
        ];
    }

    protected function defaultListingType(): string
    {
        return Listing::class;
    }

    protected function defaultDetailType(): string
    {
        return Detail::class;
    }

    public function listing(CrawlRequestDto $request): CrawlListResultDto
    {
        /** @var CrawlListResultDto $result */
        $result = $this->makeType($this->resolveListingType($request))->execute($request);

        return $result;
    }

    public function detail(CrawlRequestDto $request): CrawlItemResultDto
    {
        /** @var CrawlItemResultDto $result */
        $result = $this->makeType($this->resolveDetailType($request))->execute($request);

        return $result;
    }

    public function performerListing(CrawlRequestDto $request): CrawlListResultDto
    {
        return $this->listing($request);
    }

    public function performerDetail(CrawlRequestDto $request): CrawlItemResultDto
    {
        if (! str_contains($request->url, '/idol/detail/') && ! $this->hasLegacyPerformerId($request->url)) {
            throw new RuntimeException('xcity performer detail requires an /idol/detail/ URL.');
        }

        /** @var CrawlItemResultDto $result */
        $result = $this->makeType(PerformerDetail::class)->execute($request);

        return $result;
    }

    private function isIdolIndexUrl(string $url): bool
    {
        if (str_contains($url, '/idol/detail/')) {
            return false;
        }

        if (str_contains($url, '?ini=') || str_contains($url, '?kana=')) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#/idol/?$#', $path) === 1;
    }

    private function hasLegacyPerformerId(string $url): bool
    {
        if (! str_contains($url, '/idol/')) {
            return false;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return false;
        }

        parse_str($query, $params);
        $id = $params['id'] ?? null;

        return is_string($id) && trim($id) !== '';
    }
}
