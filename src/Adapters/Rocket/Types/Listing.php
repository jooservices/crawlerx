<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Rocket\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Rocket\Fields;
use JOOservices\CrawlerX\Adapters\Rocket\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * ROCKET work lists: `/works/` groups cards under "<date> 発売作品" headings, taxonomy archives (actress, genre,
 * director, series) list the cards of one term. The card title only exists inside an HTML comment. The year/month
 * archive sidebar is navigation, not data.
 */
final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return 'ROCKET';
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $dom = $this->fetchCrawler($request);
        $fields = new Fields();
        $taxonomy = $this->taxonomy($request->url, $dom, $fields);
        $items = [];
        foreach ($dom->filter('.works-item') as $element) {
            $card = new Crawler($element);
            $link = $card->filter('a[href*="' . Site::DETAIL_PATH . '"]');
            if ($link->count() === 0) {
                continue;
            }

            $id = Site::entityId($link->first()->attr('href') ?? '');
            $group = $this->releaseGroup($card, $fields);
            $items[$id] = MovieDto::item(Site::detailUrl($id), $id, $this->commentedTitle($card), MovieMeta::filter([
                'date' => $group['date'] ?? null,
                'cover_url' => $fields->image($card, 'img'),
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => Site::MAKER,
                    'release_group' => $group,
                    'taxonomy' => $taxonomy,
                ]),
            ]));
        }

        if ($items === []) {
            throw new CrawlParseException('ROCKET listing did not contain expected work cards.');
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: max(1, $request->page),
            entityType: 'movie',
            items: array_values($items),
            pagination: $this->pagination($dom, $request),
        );
    }

    private function commentedTitle(Crawler $card): ?string
    {
        return preg_match('/<!--\s*<h3[^>]*>(.*?)<\/h3>\s*-->/s', $card->outerHtml(), $match) === 1
            ? $this->normalizeText(strip_tags($match[1]))
            : null;
    }

    /** @return array{name: string, date: ?string}|null */
    private function releaseGroup(Crawler $card, Fields $fields): ?array
    {
        $group = $card->ancestors()->filter('.date-wrapper');
        $name = $group->count() > 0 ? $fields->text($group->first(), 'h2') : null;

        return $name !== null ? ['name' => $name, 'date' => MovieMeta::isoDate($name)] : null;
    }

    /** @return array{type: string, slug: string, heading: ?string, name: ?string}|null */
    private function taxonomy(string $url, Crawler $dom, Fields $fields): ?array
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (preg_match('#^/works_(actress|genre|director|series)/([^/]+)#', $path, $match) !== 1) {
            return null;
        }

        $subtitle = $fields->text($dom, 'main > div:first-of-type span');

        return [
            'type' => $match[1],
            'slug' => urldecode($match[2]),
            'heading' => $fields->text($dom, 'main > div:first-of-type h2'),
            'name' => $subtitle !== null ? trim((string) preg_replace('/^Works by\s*/', '', $subtitle)) : null,
        ];
    }

    private function pagination(Crawler $dom, CrawlRequestDto $request): CrawlPaginationDto
    {
        $nav = $dom->filter('nav.pagination');
        $current = $nav->filter('.page-numbers.current');
        $page = $current->count() > 0 ? (int) $current->first()->text('') : max(1, $request->page);
        $pages = array_map(
            static fn(string $text): int => (int) $text,
            array_filter($nav->filter('.page-numbers:not(.next):not(.prev):not(.dots)')->each(static fn(Crawler $node): string => trim($node->text(''))), 'ctype_digit'),
        );
        $next = $nav->filter('a.next[href]');
        $nextUrl = $next->count() > 0 ? Site::absolute($next->first()->attr('href') ?? '') : null;

        return new CrawlPaginationDto(
            currentPage: $page,
            lastPage: $pages !== [] ? max($pages) : $page,
            nextPage: $nextUrl !== null ? $page + 1 : null,
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }
}
