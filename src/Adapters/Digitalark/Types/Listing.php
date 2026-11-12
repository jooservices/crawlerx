<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Digitalark\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Digitalark\Pagination;
use JOOservices\CrawlerX\Adapters\Digitalark\Selectors;
use JOOservices\CrawlerX\Adapters\Digitalark\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

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
        $crawler = $this->fetchCrawler($request);
        $items = [];

        $crawler->filter(Selectors::LISTING_CARDS)->each(function (Crawler $card) use (&$items, $request): void {
            $href = $this->firstAttribute($card, Selectors::CARD_LINK, 'href');
            $id = $href === null ? null : Site::itemId($href);
            if ($id === null || isset($items[$id])) {
                return;
            }

            $items[$id] = $this->card($card, $id, $request->url);
        });

        if ($items === []) {
            throw new CrawlParseException('DIGITAL ARK listing page did not contain product cards.');
        }

        $pagination = (new Pagination())->read($crawler, $request->url, $request->page);

        return new CrawlListResultDto(
            url: $request->url,
            page: $pagination->currentPage ?? 1,
            entityType: 'movie',
            items: array_values($items),
            pagination: $pagination,
        );
    }

    private function card(Crawler $card, string $id, string $baseUrl): CrawlItemResultDto
    {
        $src = $this->firstAttribute($card, Selectors::CARD_IMAGE, 'src');

        return MovieDto::item($this->absolute($baseUrl, '/view/item/' . $id), $id, $this->firstText($card, Selectors::CARD_LINK), [
            'code' => $id,
            'cover_url' => $src === null ? null : $this->absolute($baseUrl, $src),
            'metadata' => MovieMeta::filter([MovieMeta::MAKER => Site::NAME]),
        ]);
    }
}
