<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Naturalhigh\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Naturalhigh\Fields;
use JOOservices\CrawlerX\Adapters\Naturalhigh\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractHtmlType implements TypeInterface
{
    protected function siteLabel(): string
    {
        return Site::SLUG;
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $dom = $this->fetchCrawler($request);
        $fields = new Fields();
        $items = [];
        foreach ($dom->filter('.archive_style > li > a.p-style-list__item-img[href]') as $element) {
            $link = new Crawler($element);
            $url = $fields->resolve($link->attr('href'));
            if ($url === null || isset($items[$url])) {
                continue;
            }
            $card = $link->ancestors()->filter('li')->first();
            $code = $fields->text($card->filter('.nhx-arc__code')->first()) ?? strtoupper(basename((string) parse_url($url, PHP_URL_PATH)));
            $title = $fields->text($card->filter('.nhx-arc__name')->first()) ?? $link->attr('title');
            $image = $link->filter('img')->first();
            $cover = $image->count() > 0 ? $this->largestImage($image, $fields) : null;
            $items[$url] = MovieDto::item($url, $code, $title, [
                'code' => $code,
                'cover_url' => $cover,
                'performers' => [],
            ]);
        }
        if ($items === []) {
            throw new CrawlParseException('Naturalhigh listing page did not contain expected work cards.');
        }

        $current = $this->currentPage($dom, $request->url, $request->page);
        $last = $this->lastPage($dom, $current);
        $nextUrl = $this->nextUrl($dom, $fields);

        return new CrawlListResultDto(
            url: $request->url,
            page: $current,
            entityType: 'movie',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $current,
                lastPage: $last,
                nextPage: $nextUrl === null ? null : $current + 1,
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    private function currentPage(Crawler $dom, string $url, int $requested): int
    {
        $current = $dom->filter('.pagination .current, .page-numbers.current, [aria-current="page"]')->first();
        if ($current->count() > 0 && preg_match('/\d+/', $current->text(''), $match) === 1) {
            return (int) $match[0];
        }
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str(is_string($query) ? $query : '', $params);
        $page = $params['sf_paged'] ?? null;

        return is_string($page) && ctype_digit($page)
            ? max(1, (int) $page)
            : max(1, $requested);
    }

    private function lastPage(Crawler $dom, int $current): int
    {
        $pages = [$current];
        foreach ($dom->filter('.wp-pagenavi a[href]') as $element) {
            $href = (new Crawler($element))->attr('href') ?? '';
            $query = parse_url($href, PHP_URL_QUERY);
            parse_str(is_string($query) ? $query : '', $params);
            $page = $params['sf_paged'] ?? null;
            if (is_string($page) && ctype_digit($page)) {
                $pages[] = (int) $page;
            }
        }

        return max($pages);
    }

    private function nextUrl(Crawler $dom, Fields $fields): ?string
    {
        $next = $dom->filter('.wp-pagenavi a.nextpostslink[href], a[rel="next"][href]')->first();

        return $next->count() > 0 ? $fields->resolve($next->attr('href')) : null;
    }

    private function largestImage(Crawler $image, Fields $fields): ?string
    {
        $srcset = $image->attr('srcset') ?? '';
        preg_match_all('/(https?:\/\/[^\s,]+|\/[^\s,]+)\s+(\d+)w/', $srcset, $matches, PREG_SET_ORDER);
        if ($matches !== []) {
            usort($matches, static fn(array $left, array $right): int => (int) $right[2] <=> (int) $left[2]);

            return $fields->resolve($matches[0][1]);
        }

        foreach (['data-lazy-src', 'data-src', 'data-original', 'src'] as $attribute) {
            $source = $image->attr($attribute);
            if ($source !== null && trim($source) !== '') {
                return $fields->resolve($source);
            }
        }

        return null;
    }
}
