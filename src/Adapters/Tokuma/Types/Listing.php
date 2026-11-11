<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Tokuma\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Tokuma\TokumaSite;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractType implements TypeInterface
{
    public function __construct(private readonly CrawlHttpClient $client)
    {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $response = $this->client->get($request->url)->toPsrResponse();
        $dom = new Crawler($this->htmlFromResponse($response, 'Tokuma Shoten', 'new books listing'));
        $items = [];
        foreach ($dom->filter('.resultWrap .bookWrap > ul > li') as $element) {
            $card = new Crawler($element);
            $link = $card->filter('.image a[href*="/book/b"], .ttl a[href*="/book/b"]')->first();
            if ($link->count() === 0) {
                continue;
            }
            $url = TokumaSite::absolute($request->url, $link->attr('href') ?? '');
            $id = TokumaSite::externalId($url);
            if ($id === null) {
                continue;
            }
            $image = $card->filter('.image img[src]')->first();
            $cover = $image->count() > 0 ? TokumaSite::absolute($request->url, $image->attr('src') ?? '') : null;
            if (TokumaSite::isPlaceholderImage($cover)) {
                $cover = null;
            } elseif ($cover !== null && str_ends_with($cover, '_mid.jpg')) {
                $cover = substr($cover, 0, -8) . '.jpg';
            }
            $title = self::text($card, '.ttl');
            $authorLinks = self::links($card, '.author a[href]', $request->url);
            $items[] = MovieDto::item($url, $id, $title, [
                'code' => $id,
                'cover_url' => $cover,
                'performers' => [],
                'metadata' => MovieMeta::filter([
                    'maker' => 'Tokuma Shoten',
                    'price' => TokumaSite::price(self::text($card, '.price')),
                    'authors' => $authorLinks,
                ]),
            ]);
        }
        if ($items === []) {
            throw new CrawlParseException('Tokuma Shoten listing did not contain expected book cards.');
        }

        [$page, $lastPage, $nextUrl] = $this->pagination($dom, $request->url);

        return new CrawlListResultDto(
            url: $request->url,
            page: $page,
            entityType: 'movie',
            items: $items,
            pagination: new CrawlPaginationDto(
                currentPage: $page,
                lastPage: $lastPage,
                nextPage: $nextUrl !== null ? $page + 1 : null,
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    /** @return array{int, int|null, string|null} */
    private function pagination(Crawler $dom, string $url): array
    {
        $pager = $dom->filter('.resultWrap .pagingWrap .right')->first();
        $page = 1;
        $lastPage = null;
        if ($pager->count() > 0) {
            $numbers = [];
            foreach ($pager->filter('a, strong') as $element) {
                $node = new Crawler($element);
                $text = self::normalize($node->text(''));
                if (ctype_digit($text)) {
                    $numbers[] = (int) $text;
                    if ($element->nodeName === 'strong') {
                        $page = (int) $text;
                    }
                }
            }
            $lastPage = $numbers === [] ? null : max($numbers);
        }
        $range = $dom->filter('.resultWrap .pagingWrap .left')->first();
        $rangeText = $range->count() > 0 ? self::normalize($range->text('')) : '';
        $pageSizeNode = $dom->filter('.resultWrap .number option[selected]')->first();
        $pageSizeText = $pageSizeNode->count() > 0 ? $pageSizeNode->attr('value') : null;
        $configuredPageSize = is_string($pageSizeText) && ctype_digit($pageSizeText) ? (int) $pageSizeText : null;
        $pageSize = $configuredPageSize;
        $rangeEnd = null;
        if ($pageSize === null && preg_match('/(\d+)[〜～](\d+)件/u', $rangeText, $match) === 1) {
            $rangeEnd = (int) $match[2];
            $pageSize = $rangeEnd - (int) $match[1] + 1;
        }
        if ($pageSize !== null && $pageSize > 0 && preg_match('/全([\d,]+)件/u', $rangeText, $match) === 1) {
            $total = (int) str_replace(',', '', $match[1]);
            if ($configuredPageSize !== null || ($rangeEnd !== null && $rangeEnd < $total)) {
                $lastPage = (int) ceil($total / $pageSize);
            }
        }
        $next = $dom->filter('.resultWrap .pagingWrap a.next[href]')->first();
        $nextUrl = $next->count() > 0 ? TokumaSite::absolute($url, $next->attr('href') ?? '') : null;

        return [$page, $lastPage, $nextUrl];
    }

    private static function text(Crawler $dom, string $selector): ?string
    {
        $node = $dom->filter($selector)->first();
        if ($node->count() === 0) {
            return null;
        }
        $value = self::normalize($node->text(''));

        return $value === '' ? null : $value;
    }

    private static function normalize(string $value): string
    {
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $value) ?? '');
    }

    /** @return list<array{name: string, url: string, role: string}> */
    private static function links(Crawler $dom, string $selector, string $baseUrl): array
    {
        $links = [];
        foreach ($dom->filter($selector) as $element) {
            $node = new Crawler($element);
            $name = trim($node->text(''));
            $href = $node->attr('href') ?? '';
            $role = trim($node->getNode(0)->nextSibling->textContent ?? '');
            if ($name !== '') {
                $links[] = ['name' => $name, 'url' => TokumaSite::absolute($baseUrl, $href), 'role' => $role];
            }
        }

        return $links;
    }
}
