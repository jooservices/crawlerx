<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Commons\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Commons\CommonsApi;
use JOOservices\CrawlerX\Adapters\Commons\Performers;
use JOOservices\CrawlerX\Adapters\Commons\Site;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;

/**
 * The person entity: an idol's category page (one API request).
 */
final class PerformerDetail extends AbstractType implements TypeInterface
{
    public function __construct(private readonly CrawlHttpClient $client)
    {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $target = Site::target($request->url);
        if ($target === null || ! Site::isCategory($target['title'])) {
            throw new CrawlParseException('Commons performer detail needs a Category: URL.');
        }

        $page = array_values(CommonsApi::pages(
            CommonsApi::query($this->client, Site::categoryUrl($target['title']), 'performer'),
        ))[0] ?? null;
        $performer = $page === null ? null : Performers::fromPage($page);
        if ($performer === null) {
            throw new CrawlParseException('Commons performer detail did not contain the expected fields.');
        }

        return $performer->toItem((string) $performer->url);
    }
}
