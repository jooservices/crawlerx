<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavMenu\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\JavMenu\Cards;
use JOOservices\CrawlerX\Adapters\JavMenu\Selectors;
use JOOservices\CrawlerX\Adapters\JavMenu\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The paged performer index of a section (`/censored/actor`).
 */
final class PerformerListing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return Site::NAME;
    }

    protected function contextLabel(): string
    {
        return 'performer_listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $cards = new Cards();
        $items = [];

        $crawler->filter(Selectors::CARD)->each(function (Crawler $card) use (&$items, $request, $cards): void {
            $href = $this->firstAttribute($card, Selectors::CARD_LINK, 'href');
            $url = $href === null ? null : $this->absolute($request->url, $href);
            $externalId = $url === null ? null : Site::performerId($url);
            $name = $this->firstText($card, Selectors::PERFORMER_NAME);
            if ($url === null || $externalId === null || $name === null || isset($items[$url])) {
                return;
            }

            $items[$url] = PerformerDto::item(
                url: $url,
                externalId: $externalId,
                title: $name,
                data: ['profile_image_url' => $cards->cover($card, $request->url)],
            );
        });

        if ($items === []) {
            throw new CrawlParseException(Site::NAME . ' performer listing did not contain performer cards.');
        }

        $pagination = $cards->pagination($crawler, $request->url, $request->page);

        return new CrawlListResultDto(
            url: $request->url,
            page: $pagination->currentPage ?? 1,
            entityType: 'performer',
            items: array_values($items),
            pagination: $pagination,
        );
    }
}
