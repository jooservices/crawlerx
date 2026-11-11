<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Hiyoko\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Hiyoko\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
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
        $dom = $this->fetchCrawler($request);
        $items = [];
        foreach ($dom->filter('#second-block-in03 > ul.list-07 > li') as $element) {
            $card = new Crawler($element);
            $link = $card->filter('a[href]')->first();
            if ($link->count() === 0) {
                continue;
            }

            $href = $link->attr('href');
            $code = $link->attr('title');
            $title = $this->firstText($card, 'a > div');
            $image = $this->firstAttribute($card, 'a img[src]', 'src');
            if ($href === null || $code === null || $title === null || $image === null) {
                continue;
            }

            $url = $this->absolute(Site::BASE_URL, $href);
            $items[] = MovieDto::item($url, strtoupper($code), $title, [
                'code' => strtoupper($code),
                'cover_url' => $this->absolute(Site::BASE_URL, $image),
                'metadata' => ['maker' => Site::NAME],
            ]);
        }
        if ($items === []) {
            throw new CrawlParseException('Hiyoko listing did not contain expected work cards.');
        }

        $currentPage = $this->currentPage($dom, $request);
        $nextHref = $this->firstAttribute($dom, '.wp-pagenavi a[rel="next"][href]', 'href');
        $nextUrl = $nextHref === null ? null : $this->absolute(Site::BASE_URL, $nextHref);
        $lastPage = $this->lastPage($dom, $currentPage);

        return new CrawlListResultDto(
            url: $request->url,
            page: $currentPage,
            entityType: 'movie',
            items: $items,
            pagination: new CrawlPaginationDto(
                currentPage: $currentPage,
                lastPage: $lastPage,
                nextPage: $nextUrl === null ? null : $currentPage + 1,
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    private function currentPage(Crawler $dom, CrawlRequestDto $request): int
    {
        $current = $this->firstText($dom, '.wp-pagenavi .current');

        return $current !== null && ctype_digit($current) ? (int) $current : max(1, $request->page);
    }

    private function lastPage(Crawler $dom, int $currentPage): int
    {
        $last = $this->firstAttribute($dom, '.wp-pagenavi a.last[href]', 'href');
        if ($last !== null && preg_match('#/page/(\d+)/?$#', (string) parse_url($last, PHP_URL_PATH), $match) === 1) {
            return max($currentPage, (int) $match[1]);
        }

        $pages = $dom->filter('.wp-pagenavi .pages')->each(
            static fn(Crawler $node): string => $node->text(''),
        );
        foreach ($pages as $text) {
            if (preg_match('#/\s*(\d+)#', $text, $match) === 1) {
                return max($currentPage, (int) $match[1]);
            }
        }

        return $currentPage;
    }
}
