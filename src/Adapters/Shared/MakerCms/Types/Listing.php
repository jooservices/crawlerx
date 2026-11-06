<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Fields;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractType implements TypeInterface
{
    public function __construct(private readonly CrawlHttpClient $client, private readonly SiteConfig $config)
    {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $page = max(1, $request->page);
        $fields = new Fields($this->config);
        $dom = $this->fetch($request->url);
        $groups = $fields->links($dom, '.p-accordion .genre a[href*="' . $this->config->listingPath . '"]');
        $group = null;
        $nextUrl = null;
        if ($groups !== []) {
            $group = $groups[$page - 1] ?? null;
            if ($group === null) {
                throw new CrawlParseException('Maker CMS date page is outside the available range.');
            }
            $group['date'] = $fields->date($group['external_id']);
            $group['count'] = $this->countFromText($group['name']);
            usleep(max(0, $this->config->dateGapSeconds) * 1_000_000);
            $dom = $this->fetch($group['url']);
            $nextUrl = isset($groups[$page]) ? strtok($request->url, '?') . '?page=' . ($page + 1) : null;
        }
        $date = $fields->date($group['url'] ?? $request->url);
        $items = $this->items($dom, $fields, $date, $group);
        if ($items === []) {
            throw new CrawlParseException('Maker CMS listing did not contain expected movie cards.');
        }
        $next = $dom->filter('main a[rel="next"][href]');
        if ($next->count() > 0) {
            $nextUrl = $this->config->absolute($next->first()->attr('href') ?? '');
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: $page,
            entityType: 'movie',
            items: $items,
            pagination: new CrawlPaginationDto(
                currentPage: $page,
                lastPage: $groups !== [] ? count($groups) : ($nextUrl === null ? $page : null),
                nextPage: $nextUrl !== null ? $page + 1 : null,
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    private function fetch(string $url): Crawler
    {
        $response = $this->client->get($url);

        return new Crawler($this->htmlFromResponse($response->toPsrResponse(), $this->config->maker, 'listing'));
    }

    /** @param array<string, mixed>|null $group
     * @return list<CrawlItemResultDto>
     */
    private function items(Crawler $dom, Fields $fields, ?string $date, ?array $group): array
    {
        $items = [];
        foreach ($dom->filter('.p-search .c-card') as $element) {
            $card = new Crawler($element);
            $links = $fields->links($card, 'a.img[href*="' . $this->config->detailPath . '"]');
            if ($links === []) {
                continue;
            }
            $id = $this->config->entityId($links[0]['url'], $this->config->detailPath);
            $url = $this->config->baseUrl . $this->config->detailPath . $id;
            $itemGroup = $group ?? $this->releaseGroup($card, $fields);
            $items[$id] = MovieDto::item($url, $id, $fields->text($card, '.hover__child .text'), [
                'code' => $id,
                'date' => $itemGroup['date'] ?? $date,
                'cover_url' => $fields->image($card, 'a.img img'),
                'performers' => $fields->performers($card, '.name'),
                'metadata' => [
                    'maker' => $this->config->maker,
                    'performer_count' => $this->countFromText($fields->text($card, '.ninzu')),
                    'badge' => $fields->text($card, '.c-card__tag'),
                    'release_group' => $itemGroup,
                    'search_key' => $itemGroup !== null && $group === null ? $itemGroup['name'] : $fields->text($dom, '.p-search__key'),
                    'listing_range' => $fields->text($dom, '.p-search .swiper-pagination-02'),
                ],
            ]);
        }

        return array_values($items);
    }

    /** @return array{name: string, url: string, external_id: string, date: string, count: int}|null */
    private function releaseGroup(Crawler $card, Fields $fields): ?array
    {
        $heading = $card->ancestors()->filter('.l-wrap')->first()->previousAll()->filter('.p-search__key.-flexBet')->first();
        $date = $fields->date($fields->text($heading, 'p'));
        if ($date === null) {
            return null;
        }

        return [
            'name' => $heading->text(),
            'url' => $this->config->baseUrl . $this->config->listingPath . $date,
            'external_id' => $date,
            'date' => $date,
            'count' => (int) $fields->text($heading, 'p:last-child'),
        ];
    }

    private function countFromText(?string $text): ?int
    {
        return $text !== null && preg_match('/(?:作品数[：:]|全)\s*(\d+)/u', $text, $match) === 1 ? (int) $match[1] : null;
    }
}
