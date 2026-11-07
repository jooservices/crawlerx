<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavMenu\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\JavMenu\Cards;
use JOOservices\CrawlerX\Adapters\JavMenu\Selectors;
use JOOservices\CrawlerX\Adapters\JavMenu\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;

/**
 * The performer page: the site shows only the name and the grid of her works (paged), so those are the data.
 */
final class PerformerDetail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return Site::NAME;
    }

    protected function contextLabel(): string
    {
        return 'performer_detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $name = $this->firstText($crawler, Selectors::ARCHIVE_TITLE);
        $externalId = Site::performerId($request->url);
        if ($name === null || $externalId === null) {
            throw new CrawlParseException(Site::NAME . ' performer detail did not contain the performer name.');
        }

        $cards = new Cards();
        $pagination = $cards->pagination($crawler, $request->url, $request->page);

        return PerformerDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $name,
            data: [
                'raw_profile' => ['heading' => $this->firstText($crawler, Selectors::ARCHIVE_HEADING)],
                'metadata' => MovieMeta::filter([
                    'works' => array_values($cards->works($crawler, $request->url)),
                    'works_page' => $pagination->currentPage,
                    'works_last_page' => $pagination->lastPage,
                    'works_next_url' => $pagination->nextUrl,
                ]),
            ],
        );
    }
}
