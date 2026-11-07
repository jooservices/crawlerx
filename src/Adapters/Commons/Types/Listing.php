<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Commons\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Commons\CommonsApi;
use JOOservices\CrawlerX\Adapters\Commons\Galleries;
use JOOservices\CrawlerX\Adapters\Commons\Performers;
use JOOservices\CrawlerX\Adapters\Commons\Site;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;

/**
 * Members of a category: sub-categories become person items, files become one-photo gallery items. Two API
 * requests per page: the ordered member list, then the full data of those members.
 */
final class Listing extends AbstractType implements TypeInterface
{
    private const TYPES = 'subcat|file';

    public function __construct(private readonly CrawlHttpClient $client)
    {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $target = Site::target($request->url);
        if ($target === null || ! Site::isCategory($target['title'])) {
            throw new CrawlParseException('Commons listing needs a Category: URL.');
        }

        $page = max(1, $request->page);
        $payload = CommonsApi::query(
            $this->client,
            Site::membersUrl($target['title'], self::TYPES, $target['continue'], $target['limit']),
            'listing',
        );
        $members = CommonsApi::members($payload);
        $pages = $members === []
            ? []
            : CommonsApi::pages(CommonsApi::query($this->client, Site::pagesUrl(CommonsApi::pageIds($members)), 'listing members'));

        $items = [];
        foreach ($members as $member) {
            $item = $this->item($pages[$member['pageid']] ?? null, $member);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        $token = CommonsApi::continuation($payload);

        return new CrawlListResultDto(
            url: $request->url,
            page: $page,
            entityType: 'category_member',
            items: $items,
            pagination: new CrawlPaginationDto(
                currentPage: $page,
                lastPage: null,
                nextPage: $token !== null ? $page + 1 : null,
                nextUrl: $token !== null ? Site::membersUrl($target['title'], self::TYPES, $token, $target['limit']) : null,
                hasNextPage: $token !== null,
            ),
        );
    }

    /**
     * @param  array<string, mixed>|null  $page
     * @param  array{pageid: int, title: string, type: mixed, timestamp: mixed, sortkeyprefix: mixed}  $member
     */
    private function item(?array $page, array $member): ?CrawlItemResultDto
    {
        if ($page === null) {
            return null;
        }

        if (is_string($page['title'] ?? null) && Site::isCategory($page['title'])) {
            $performer = Performers::fromPage($page, $member);

            return $performer?->toItem((string) $performer->url);
        }

        return Galleries::fromFile($page);
    }
}
