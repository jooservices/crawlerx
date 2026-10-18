<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\MinnanoAv\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\MinnanoAv\Selectors;
use JOOservices\CrawlerX\Adapters\MinnanoAv\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Support\CodeNormalizer;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    private readonly UrlNormalizer $normalizer;

    public function __construct(CrawlHttpClient $client)
    {
        parent::__construct($client);
        $this->normalizer = new UrlNormalizer();
    }

    /** @return array<string, \JOOservices\CrawlerX\Dto\CrawlItemResultDto> keyed by movie url */
    private function tableItems(Crawler $crawler, string $baseUrl): array
    {
        $items = [];
        $crawler->filter(Selectors::FILMOGRAPHY_ROWS)->each(function (Crawler $row) use (&$items, $baseUrl): void {
            $link = $row->filter('h3.ttl a, td.jacket a')->first();
            $href = $link->count() > 0 ? $link->attr('href') : null;
            if (! is_string($href) || preg_match('#av\d+\.html$#i', $href) !== 1) {
                return;
            }

            $url = $this->normalizer->absolute($baseUrl, $href);
            $title = $this->normalizeText($row->filter('h3.ttl')->count() > 0 ? $row->filter('h3.ttl')->text('') : $link->attr('title') ?? '')
                ?? $this->normalizeText($row->filter('td.jacket img')->count() > 0 ? (string) $row->filter('td.jacket img')->attr('alt') : '')
                ?? '';
            if ($title === '') {
                return;
            }

            $externalId = $this->externalIdFromAvUrl($url);
            $image = $row->filter('td.jacket img');
            $src = $image->count() > 0 ? ($image->attr('data-src') ?? $image->attr('src')) : null;
            $maker = null;
            $label = null;
            $performers = [];
            $row->filter('p.cast')->each(function (Crawler $cast) use (&$maker, &$label, &$performers, $baseUrl): void {
                $links = $cast->filter('a[href*="actress"]');
                if ($links->count() > 0) {
                    $links->each(function (Crawler $a) use (&$performers, $baseUrl): void {
                        $href = $a->attr('href');
                        $name = $this->normalizeText($a->text(''));
                        if (is_string($href) && $name !== null && preg_match('#actress(\d+)\.html#i', $href, $m) === 1) {
                            $performers[$m[1]] = [
                                'external_id' => $m[1],
                                'name' => $name,
                                'url' => $this->normalizer->canonicalActressUrl($this->normalizer->absolute($baseUrl, $href)),
                            ];
                        }
                    });

                    return;
                }

                $parts = array_map('trim', explode(' / ', $this->normalizeText($cast->text('')) ?? ''));
                $maker = $parts[0] !== '' ? $parts[0] : null;
                $label = $parts[1] ?? null;
            });
            $date = null;
            $cells = $row->filter('td');
            if ($cells->count() >= 3 && preg_match('#(\d{4})/(\d{1,2})/(\d{1,2})#', $cells->eq(2)->text(''), $d) === 1) {
                $date = sprintf('%04d-%02d-%02d', (int) $d[1], (int) $d[2], (int) $d[3]);
            }

            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $externalId,
                title: $title,
                data: array_filter([
                    'code' => CodeNormalizer::canonical($title, $externalId, $url),
                    'cover_url' => is_string($src) && $src !== '' && ! str_ends_with($src, 'blank.gif') && ! str_starts_with($src, 'data:')
                        ? $this->normalizer->absolute($baseUrl, $src)
                        : null,
                    'date' => $date,
                    'performers' => array_values($performers),
                    'metadata' => array_filter(['maker' => $maker, 'label' => $label], static fn(mixed $v): bool => $v !== null && $v !== ''),
                ], static fn(mixed $value): bool => $value !== null && $value !== '' && $value !== []),
            );
        });

        return $items;
    }

    protected function siteLabel(): string
    {
        return 'minnanoav';
    }

    protected function contextLabel(): string
    {
        return 'filmography_listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $items = $this->tableItems($crawler, $request->url);

        // Fallback for pages without the filmography table: any link to a movie page.
        if ($items === []) {
            $crawler->filter(Selectors::FILMOGRAPHY_MOVIE_LINKS)->each(function (Crawler $node) use (&$items, $request): void {
                $href = $node->attr('href');
                if (! is_string($href) || trim($href) === '') {
                    return;
                }

                if (preg_match('#av\d+\.html$#i', $href) !== 1) {
                    return;
                }

                $url = $this->normalizer->absolute($request->url, $href);
                $title = $this->normalizeText($node->text('')) ?? '';
                if ($title === '') {
                    return;
                }

                $externalId = $this->externalIdFromAvUrl($url);
                $code = CodeNormalizer::canonical($title, $externalId, $url);

                $items[$url] = MovieDto::item(
                    url: $url,
                    externalId: $externalId,
                    title: $title,
                    data: array_filter(['code' => $code], fn(mixed $value): bool => $value !== null && $value !== ''),
                );
            });
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'movie',
            items: array_values($items),
            pagination: $this->pagination($crawler, $request->url, $request->page),
        );
    }

    private function pagination(Crawler $crawler, string $url, int $currentPage): CrawlPaginationDto
    {
        $nextUrl = null;
        $crawler->filter(Selectors::PAGINATION_NEXT)->each(function (Crawler $node) use (&$nextUrl, $url): void {
            if ($nextUrl !== null) {
                return;
            }

            $href = $node->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $nextUrl = $this->normalizer->absolute($url, $href);
        });

        if ($nextUrl === null) {
            $crawler->filter('.pagination a[href*="page="]')->each(function (Crawler $node) use (&$nextUrl, $url, $currentPage): void {
                $href = $node->attr('href');
                if (! is_string($href) || preg_match('/page=(\d+)/', $href, $match) !== 1) {
                    return;
                }

                if ((int) $match[1] === $currentPage + 1) {
                    $nextUrl = $this->normalizer->absolute($url, $href);
                }
            });
        }

        $nextPage = $nextUrl !== null ? $this->pageFromUrl($nextUrl) : null;

        return new CrawlPaginationDto(
            currentPage: $currentPage,
            lastPage: null,
            nextPage: $nextPage,
            nextUrl: $nextUrl,
            hasNextPage: $nextPage !== null,
        );
    }

    private function externalIdFromAvUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/(av\d+)\.html$#i', $path, $match) === 1) {
            return strtolower($match[1]);
        }

        return null;
    }

    private function pageFromUrl(string $url): ?int
    {
        if (preg_match('/[?&]page=(\d+)/', $url, $match) === 1) {
            return (int) $match[1];
        }

        return null;
    }
}
