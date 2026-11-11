<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Gravidia\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Gravidia\Selectors;
use JOOservices\CrawlerX\Adapters\Gravidia\Site;
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
        $items = [];

        $crawler->filter(Selectors::LISTING_CARDS)->each(function (Crawler $card) use (&$items, $request): void {
            $url = $this->firstAttribute($card, Selectors::CARD_LINK, 'href');
            $id = $url === null ? null : Site::productId($url);
            if ($url === null || $id === null || isset($items[$id])) {
                return;
            }

            $url = $this->absolute($request->url, $url);
            $photographer = $this->firstText($card, Selectors::CARD_PHOTOGRAPHER);
            $photographer = $photographer === null ? null : trim(preg_replace('/^撮影[：:]\s*/u', '', $photographer) ?? $photographer);
            $model = $this->firstText($card, Selectors::CARD_MODEL);
            $splitModels = $model === null ? [] : preg_split('/\s*,\s*/u', $model);
            $models = $model === null
                ? []
                : array_values(array_filter(array_map('trim', is_array($splitModels) ? $splitModels : []), static fn(string $name): bool => $name !== ''));
            $label = $this->texts($card, Selectors::CARD_LABEL);

            $items[$id] = MovieDto::item($url, $id, $this->firstText($card, Selectors::CARD_TITLE), [
                'code' => $id,
                'cover_url' => $this->image($card, Selectors::CARD_IMAGE, $request->url),
                'performers' => $models,
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => Site::NAME,
                    'model' => $model,
                    'photographer' => $photographer,
                    'label' => $label[0] ?? null,
                    'labels' => $label,
                ]),
            ]);
        });

        if ($items === []) {
            throw new CrawlParseException('Gravidia listing page did not contain product cards.');
        }

        $pagination = $this->pagination($crawler, $request);

        return new CrawlListResultDto(
            url: $request->url,
            page: $pagination->currentPage ?? 1,
            entityType: 'movie',
            items: array_values($items),
            pagination: $pagination,
        );
    }

    private function pagination(Crawler $crawler, CrawlRequestDto $request): CrawlPaginationDto
    {
        $active = $crawler->filter(Selectors::PAGINATION_ACTIVE)->first();
        $activeText = $active->count() > 0 ? trim($active->text('')) : '';
        $activePage = ctype_digit($activeText) ? (int) $activeText : max(1, $request->page);
        $pages = [];
        $nextUrl = null;

        $crawler->filter(Selectors::PAGINATION_LINKS)->each(function (Crawler $link) use (&$pages, &$nextUrl, $activePage, $request): void {
            $text = trim($link->text(''));
            if (ctype_digit($text)) {
                $page = (int) $text;
                $pages[] = $page;
                if ($page === $activePage + 1) {
                    $href = $link->attr('href');
                    $nextUrl = $href === null ? null : $this->absolute($request->url, $href);
                }
            }
        });

        $current = $activePage > 0 ? $activePage : 1;
        $last = max([$current, ...$pages]);

        return new CrawlPaginationDto(
            currentPage: $current,
            lastPage: $last,
            nextPage: $nextUrl === null ? null : $current + 1,
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }

    private function image(Crawler $node, string $selector, string $baseUrl): ?string
    {
        $src = $this->firstAttribute($node, $selector, 'src');

        return $src === null ? null : $this->absolute($baseUrl, $src);
    }
}
