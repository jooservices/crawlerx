<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Next11\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Next11\Fields;
use JOOservices\CrawlerX\Adapters\Next11\Selectors;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractHtmlType implements TypeInterface
{
    public function __construct(
        CrawlHttpClient $client,
        private readonly Fields $fields = new Fields(),
    ) {
        parent::__construct($client);
    }

    protected function siteLabel(): string
    {
        return 'next11';
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $items = [];
        foreach ($crawler->filter(Selectors::LISTING_CARDS) as $element) {
            $item = $this->card(new Crawler($element));
            if ($item !== null) {
                $items[] = $item;
            }
        }

        if ($items === []) {
            throw new CrawlParseException('Next11 listing page did not contain expected movie fields.');
        }

        $page = $this->requestedPage($request);

        return new CrawlListResultDto(
            url: $request->url,
            page: $page,
            entityType: 'movie',
            items: $items,
            pagination: $this->pagination($crawler, $page),
        );
    }

    /**
     * The page a listing URL points at: the site's own `pageno` query parameter when present, else the requested page.
     */
    private function requestedPage(CrawlRequestDto $request): int
    {
        parse_str((string) parse_url($request->url, PHP_URL_QUERY), $query);
        $pageno = $query['pageno'] ?? null;

        return is_numeric($pageno) && (int) $pageno > 0 ? (int) $pageno : max($request->page, 1);
    }

    private function card(Crawler $card): ?CrawlItemResultDto
    {
        $link = $card->filter(Selectors::LISTING_LINK);
        $url = $link->count() > 0 ? $this->fields->resolve($link->first()->attr('href')) : null;
        if ($url === null) {
            return null;
        }

        $rows = $card->filter(Selectors::LISTING_ROWS)->count() > 0
            ? $this->fields->rows($card->filter(Selectors::LISTING_ROWS)->first())
            : [];
        $image = $card->filter(Selectors::LISTING_IMAGE);
        $note = $card->filter(Selectors::LISTING_NOTE);
        $title = $card->filter(Selectors::LISTING_TITLE);

        return MovieDto::item(
            url: $url,
            externalId: $this->fields->productId($url),
            title: $title->count() > 0 ? $this->fields->text($title->first()) : null,
            data: MovieMeta::filter([
                'code' => isset($rows['商品']) ? $this->fields->text($rows['商品']) : null,
                'cover_url' => $image->count() > 0 ? $this->fields->resolve($image->first()->attr('src')) : null,
                'performers' => isset($rows['女優']) ? $this->fields->links($rows['女優']) : [],
                'metadata' => MovieMeta::filter([
                    'release_note' => $note->count() > 0 ? $this->fields->text($note->first()) : null,
                    'pre_release' => $note->count() > 0 ? true : null,
                    MovieMeta::FIELDS => $this->fields->sourceFields($rows),
                ]),
            ]),
        );
    }

    private function pagination(Crawler $crawler, int $requestedPage): CrawlPaginationDto
    {
        $current = $crawler->filter(Selectors::PAGER_CURRENT);
        $currentPage = $current->count() > 0 && is_numeric($this->fields->text($current->first()))
            ? (int) $this->fields->text($current->first())
            : max($requestedPage, 1);

        $pages = [$currentPage];
        foreach ($crawler->filter(Selectors::PAGER_LINKS) as $element) {
            parse_str((string) parse_url((string) (new Crawler($element))->attr('href'), PHP_URL_QUERY), $query);
            if (isset($query['pageno']) && is_numeric($query['pageno'])) {
                $pages[] = (int) $query['pageno'];
            }
        }

        $next = $crawler->filter(Selectors::PAGER_NEXT);
        $nextUrl = $next->count() > 0 ? $this->fields->resolve($next->first()->attr('href')) : null;

        return new CrawlPaginationDto(
            currentPage: $currentPage,
            lastPage: max($pages),
            nextPage: $nextUrl === null ? null : $currentPage + 1,
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }
}
