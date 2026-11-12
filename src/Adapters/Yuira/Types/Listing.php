<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Yuira\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Yuira\ListingPagination;
use JOOservices\CrawlerX\Adapters\Yuira\Selectors;
use JOOservices\CrawlerX\Adapters\Yuira\Site;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    public function __construct(
        CrawlHttpClient $client,
        private readonly ListingPagination $pagination = new ListingPagination(),
    ) {
        parent::__construct($client);
    }

    protected function siteLabel(): string
    {
        return Site::NAME;
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $page = $this->fetchCrawler($request);
        $items = $this->cards($page);
        if ($items === []) {
            throw new CrawlParseException('YUIRA listing did not contain product cards.');
        }

        $pagination = $this->pagination->fromPage($page, $request);

        return new CrawlListResultDto(
            url: $request->url,
            page: $pagination->currentPage ?? 1,
            entityType: 'movie',
            items: $items,
            pagination: $pagination,
        );
    }

    /** @return list<CrawlItemResultDto> */
    private function cards(Crawler $page): array
    {
        $items = [];
        foreach ($page->filter(Selectors::LISTING_CARDS) as $element) {
            $card = new Crawler($element);
            $href = $this->firstAttribute($card, Selectors::CARD_LINK, 'href');
            $title = $this->firstText($card, Selectors::CARD_LINK);
            if ($href === null || $title === null) {
                continue;
            }

            $url = $this->absolute(Site::BASE_URL, $href);
            $cover = $this->firstAttribute($card, Selectors::CARD_IMAGE, 'data-src');
            $items[] = MovieDto::item($url, Site::productId($url), $title, [
                'cover_url' => $cover === null ? null : $this->absolute(Site::BASE_URL, $cover),
            ]);
        }

        return $items;
    }
}
