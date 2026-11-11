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
        $page = max(1, $request->page);
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
            $performers = $card->filter('.bx-actress-link a[href]')->each(function (Crawler $link): array {
                $profileUrl = $this->absolute(Site::BASE_URL, (string) $link->attr('href'));

                return [
                    'name' => $this->normalizeText($link->text('')) ?? '',
                    'name_japanese' => $this->normalizeText($link->text('')) ?? '',
                    'url' => $profileUrl,
                    'external_id' => trim(basename((string) parse_url($profileUrl, PHP_URL_PATH)), '/'),
                ];
            });

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

        $next = $dom->filter('a[rel="next"][href], .pagination a.next[href], .pager a.next[href]');
        $nextUrl = $next->count() > 0 ? $this->absolute(Site::BASE_URL, (string) $next->first()->attr('href')) : null;

        return new CrawlListResultDto(
            url: $request->url,
            page: $page,
            entityType: 'movie',
            items: $items,
            pagination: new CrawlPaginationDto(
                currentPage: $page,
                lastPage: $nextUrl === null ? $page : null,
                nextPage: $nextUrl === null ? null : $page + 1,
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
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
