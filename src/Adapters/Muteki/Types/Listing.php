<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Muteki\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Muteki\Site;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractType implements TypeInterface
{
    use NormalizesUrls;
    use ParsesHtml;

    public function __construct(private readonly CrawlHttpClient $client)
    {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $response = $this->client->get($request->url);
        $dom = new Crawler($this->htmlFromResponse($response->toPsrResponse(), 'MUTEKI', 'listing'));
        $page = $this->pageFromUrl($request->url);
        $items = [];

        foreach ($dom->filter('.area-works-list-content .lst-index > li') as $element) {
            $card = new Crawler($element);
            $href = $this->firstAttribute($card, 'a.btn-works[href]', 'href');
            $title = $this->firstText($card, 'a.btn-works p');
            if ($href === null || $title === null) {
                continue;
            }

            $url = $this->absolute(Site::BASE_URL, $href);
            $id = strtoupper(trim(basename((string) parse_url($url, PHP_URL_PATH)), '/'));
            $performers = [];
            foreach ($card->filter('.bx-actress-link a[href]') as $element) {
                $link = new Crawler($element);
                $name = $this->normalizeText($link->text(''));
                if ($name === null) {
                    continue;
                }

                $profileUrl = $this->absolute(Site::BASE_URL, (string) $link->attr('href'));

                $performers[] = [
                    'name' => $name,
                    'name_japanese' => $name,
                    'url' => $profileUrl,
                    'external_id' => trim(basename((string) parse_url($profileUrl, PHP_URL_PATH)), '/'),
                ];
            }

            $items[] = MovieDto::item($url, $id, $title, [
                'code' => $id,
                'date' => $this->date($this->firstText($card, 'a.btn-works span')),
                'cover_url' => $this->image($card, 'img'),
                'performers' => $performers,
                'metadata' => ['maker' => Site::MAKER],
            ]);
        }

        if ($items === []) {
            throw new CrawlParseException('MUTEKI listing did not contain expected work cards.');
        }

        $pages = [];
        foreach ($dom->filter('.bx-paging a[href], a[rel="next"][href], .pagination a.next[href], .pager a.next[href]') as $element) {
            $link = new Crawler($element);
            $href = (string) $link->attr('href');
            $linkedPage = $this->pageFromUrl($this->absolute(Site::BASE_URL, $href));
            if ($linkedPage > $page) {
                $pages[$linkedPage] = $this->absolute(Site::BASE_URL, $href);
            }
        }
        ksort($pages);
        $nextPage = array_key_first($pages);
        $nextUrl = $nextPage === null ? null : $pages[$nextPage];

        return new CrawlListResultDto(
            url: $request->url,
            page: $page,
            entityType: 'movie',
            items: $items,
            pagination: new CrawlPaginationDto(
                currentPage: $page,
                lastPage: $pages === [] ? $page : max(array_keys($pages)),
                nextPage: $nextPage,
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    private function pageFromUrl(string $url): int
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        preg_match('#^/works/list/(?:release|all|year/\d{4}|genre/\d+)(?:/([1-9]\d*))?/?$#', $path, $match);

        return isset($match[1]) ? (int) $match[1] : 1;
    }

    private function date(?string $value): ?string
    {
        if ($value === null || preg_match('/(\d{4})\/(\d{1,2})\/(\d{1,2})/u', $value, $match) !== 1) {
            return null;
        }

        return checkdate((int) $match[2], (int) $match[3], (int) $match[1])
            ? sprintf('%04d-%02d-%02d', (int) $match[1], (int) $match[2], (int) $match[3])
            : null;
    }

    private function image(Crawler $node, string $selector): ?string
    {
        $url = $this->firstAttribute($node, $selector, 'src');

        return $url === null ? null : $this->absolute(Site::BASE_URL, $url);
    }
}
