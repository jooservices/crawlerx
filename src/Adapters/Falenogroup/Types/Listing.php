<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Falenogroup\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Falenogroup\Fields;
use JOOservices\CrawlerX\Adapters\Falenogroup\Selectors;
use JOOservices\CrawlerX\Adapters\Falenogroup\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
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
        $fields = new Fields();
        $maker = Site::isMakerPage($request->url) ? $this->makerContext($crawler, $request->url) : null;
        $items = [];

        $crawler->filter(Selectors::CARD)->each(function (Crawler $card) use (&$items, $request, $fields, $maker): void {
            $href = $this->firstAttribute($card, Selectors::CARD_LINK, 'href');
            $url = $href === null ? null : $this->absolute($request->url, $href);
            $externalId = $url === null ? null : Site::externalId($url);
            if ($url === null || $externalId === null || isset($items[$url])) {
                return;
            }

            $note = $fields->releaseNote($card, Selectors::CARD_RELEASE_NOTE);
            $cover = $this->firstAttribute($card, Selectors::CARD_COVER, 'src');
            $badge = $this->firstAttribute($card, Selectors::CARD_MAKER_LOGO, 'src');
            $sample = $this->firstAttribute($card, Selectors::SAMPLE_VIDEO, 'href');

            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $externalId,
                title: $this->firstText($card, Selectors::CARD_TITLE),
                data: [
                    'code' => Site::code($externalId),
                    'cover_url' => $cover === null ? null : $this->absolute($request->url, $cover),
                    'date' => MovieMeta::isoDate($note),
                    'metadata' => MovieMeta::filter([
                        'maker' => $maker['name'] ?? null,
                        'maker_logo_url' => $badge ?? ($maker['logo_url'] ?? null),
                        MovieMeta::SAMPLE_VIDEO_URL => $sample === null ? null : $this->absolute($request->url, $sample),
                        'release_note' => $note,
                        'purchase_links' => $fields->purchaseLinks($card, Selectors::CARD_PURCHASE_LINKS),
                    ]),
                ],
            );
        });

        if ($items === []) {
            throw new CrawlParseException(Site::NAME . ' listing did not contain expected work cards.');
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'movie',
            items: array_values($items),
            pagination: $this->pagination($crawler, $request),
        );
    }

    /**
     * The maker that owns a /makers/<slug>/ page: every card on it belongs to this maker.
     *
     * @return array{name: ?string, logo_url: ?string}
     */
    private function makerContext(Crawler $crawler, string $url): array
    {
        $logo = $this->firstAttribute($crawler, Selectors::MAKER_LOGO, 'src');

        return [
            'name' => $this->firstText($crawler, Selectors::MAKER_NAME),
            'logo_url' => $logo === null ? null : $this->absolute($url, $logo),
        ];
    }

    private function pagination(Crawler $crawler, CrawlRequestDto $request): CrawlPaginationDto
    {
        $current = (int) $this->firstText($crawler, Selectors::PAGINATION_CURRENT);
        $current = $current > 0 ? $current : max(1, $request->page);
        $next = $this->firstAttribute($crawler, Selectors::PAGINATION_NEXT, 'href');
        $nextUrl = $next === null ? null : $this->absolute($request->url, $next);
        $hrefs = $crawler->filter(Selectors::PAGINATION_LAST . ', ' . Selectors::PAGINATION_PAGES)
            ->each(static fn(Crawler $node): string => (string) $node->attr('href'));
        $pages = array_map(fn(string $href): int => $this->pageNumber($href) ?? 0, $hrefs);

        return new CrawlPaginationDto(
            currentPage: $current,
            lastPage: max([$current, ...$pages]),
            nextPage: $nextUrl === null ? null : ($this->pageNumber($nextUrl) ?? $current + 1),
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }

    private function pageNumber(string $href): ?int
    {
        return preg_match('#/page/(\d+)/?#', $href, $match) === 1 ? (int) $match[1] : null;
    }
}
