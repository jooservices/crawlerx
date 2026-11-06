<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Indies\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Indies\Fields;
use JOOservices\CrawlerX\Adapters\Indies\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/** The actress cards of /actpage/ ("今月の女優たち"): name, profile url and image. The index links are navigation. */
final class PerformerListing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return Site::LABEL;
    }

    protected function contextLabel(): string
    {
        return 'performer_listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $items = [];

        $crawler->filter('li.package a[href*="/actress/"]')->each(function (Crawler $card) use (&$items, $request): void {
            $url = $this->absolute($request->url, (string) $card->attr('href'));
            $externalId = Fields::slug($url, 'actress');
            $name = $this->firstText($card, 'p.h6') ?? $this->firstAttribute($card, 'img', 'alt');
            if ($externalId === null || $name === null) {
                return;
            }

            $image = $this->firstAttribute($card, 'img', 'src');
            $items[$url] ??= PerformerDto::item($url, $externalId, $name, MovieMeta::filter([
                'profile_image_url' => $image === null ? null : $this->absolute($request->url, $image),
            ]));
        });

        if ($items === []) {
            throw new CrawlParseException('Indies performer listing did not contain actress cards.');
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'performer',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $request->page,
                lastPage: null,
                nextPage: null,
                nextUrl: null,
                hasNextPage: false,
            ),
        );
    }
}
