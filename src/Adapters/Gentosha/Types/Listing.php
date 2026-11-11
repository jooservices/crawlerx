<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Gentosha\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Gentosha\Selectors;
use JOOservices\CrawlerX\Adapters\Gentosha\Site;
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
        return 'Gentosha';
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
            $card = new Crawler($element);
            $link = $card->filter(Selectors::LISTING_LINK);
            $href = $link->count() > 0 ? $link->first()->attr('href') : null;
            if (! is_string($href) || trim($href) === '') {
                continue;
            }

            $url = $this->absolute(Site::BASE_URL, $href);
            $isbn = $this->isbn($url) ?? $this->attributeValue($link->first(), 'data-itemid');
            $title = $this->text($card, Selectors::LISTING_TITLE);
            $authors = $this->authors($card);
            $types = $this->texts($card, Selectors::LISTING_TYPES);
            $price = $this->text($card, Selectors::LISTING_PRICE);
            $published = $this->attribute($card, Selectors::LISTING_DATE, 'datetime');
            $publishedText = $this->text($card, Selectors::LISTING_DATE_TEXT);
            $cover = $this->attribute($card, Selectors::LISTING_COVER, 'src');

            $items[] = MovieDto::item($url, $isbn, $title, [
                'code' => $isbn,
                'date' => MovieMeta::isoDate($published),
                'cover_url' => $cover === null ? null : $this->absolute(Site::BASE_URL, $cover),
                'tags' => $types,
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => Site::MAKER,
                    'authors' => $authors,
                    'formats' => $types,
                    MovieMeta::PRICE => $this->price($price),
                    'source_fields' => $this->sourceFields(implode(' ', $types), $title, $authors, $price, $publishedText ?? $published),
                ]),
            ]);
        }

        if ($items === []) {
            throw new CrawlParseException('Gentosha listing page did not contain expected book cards.');
        }

        $current = $crawler->filter(Selectors::PAGER_CURRENT);
        $currentText = $current->count() > 0 ? $this->normalizeText($current->first()->text('')) : null;
        $page = $currentText !== null && ctype_digit($currentText) && (int) $currentText > 0
            ? (int) $currentText
            : $this->pageFromUrl($request->url);
        $pages = [];
        foreach ($crawler->filter(Selectors::PAGER_LINKS) as $element) {
            $link = new Crawler($element);
            $href = $link->attr('href');
            if (is_string($href)) {
                $linkedPage = $this->pageFromUrl($this->absolute(Site::BASE_URL, $href));
                if ($linkedPage > $page) {
                    $pages[$linkedPage] = $this->absolute(Site::BASE_URL, $href);
                }
            }
        }

        $next = $crawler->filter(Selectors::PAGER_NEXT);
        $nextHref = $next->count() > 0 ? $next->first()->attr('href') : null;
        $nextUrl = is_string($nextHref) ? $this->absolute(Site::BASE_URL, $nextHref) : null;
        $last = $crawler->filter(Selectors::PAGER_LAST);
        $lastHref = $last->count() > 0 ? $last->first()->attr('href') : null;
        $lastPage = is_string($lastHref) ? $this->pageFromUrl($this->absolute(Site::BASE_URL, $lastHref)) : $page;
        if (! is_string($lastHref) && $pages !== []) {
            $lastPage = max(array_keys($pages));
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: $page,
            entityType: 'movie',
            items: $items,
            pagination: new CrawlPaginationDto(
                currentPage: $page,
                lastPage: $lastPage,
                nextPage: $nextUrl === null ? null : $this->pageFromUrl($nextUrl),
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    private function pageFromUrl(string $url): int
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $page = $query['p'] ?? null;

        return is_string($page) && ctype_digit($page) && (int) $page > 0 ? (int) $page : 1;
    }

    private function isbn(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return preg_match('#^/book/detail/(\d{13})/?$#', $path, $match) === 1 ? $match[1] : null;
    }

    /** @return list<array{name: string, url: ?string}> */
    private function authors(Crawler $card): array
    {
        $authors = [];
        foreach ($card->filter(Selectors::LISTING_AUTHOR . ' a') as $element) {
            $link = new Crawler($element);
            $name = $this->normalizeText($link->text(''));
            if ($name === null) {
                continue;
            }

            $href = $link->attr('href');
            $authors[] = [
                'name' => $name,
                'url' => is_string($href) ? $this->absolute(Site::BASE_URL, $href) : null,
            ];
        }

        if ($authors === []) {
            $name = $this->text($card, Selectors::LISTING_AUTHOR);
            if ($name !== null) {
                $authors[] = ['name' => $name, 'url' => null];
            }
        }

        return $authors;
    }

    /**
     * @param list<array{name: string, url: ?string}> $authors
     * @return array<string, string>
     */
    private function sourceFields(?string $type, ?string $title, array $authors, ?string $price, ?string $date): array
    {
        $authorNames = [];
        foreach ($authors as $author) {
            $authorNames[] = $author['name'];
        }

        $fields = [
            '発行形態' => $type === null ? null : '発行形態 ' . $type,
            'タイトル' => $title === null ? null : 'タイトル ' . $title,
            '著者' => $authorNames === [] ? null : '著者 ' . implode(', ', $authorNames),
            '価格' => $price === null ? null : '価格 ' . $price,
            '発売日' => $date === null ? null : '発売日 ' . $date,
        ];
        $sourceFields = [];
        foreach ($fields as $label => $value) {
            if ($value !== null) {
                $sourceFields[$label] = $value;
            }
        }

        return $sourceFields;
    }

    /** @return array{amount: ?float, currency: ?string, label: ?string}|null */
    private function price(?string $label): ?array
    {
        if ($label === null || preg_match('/([\d,]+)\s*円/u', $label, $match) !== 1) {
            return null;
        }

        return MovieMeta::price((float) str_replace(',', '', $match[1]), 'JPY', $label);
    }

    private function text(Crawler $node, string $selector): ?string
    {
        $field = $node->filter($selector);

        return $field->count() > 0 ? $this->normalizeText($field->first()->text('')) : null;
    }

    /** @return list<string> */
    private function texts(Crawler $node, string $selector): array
    {
        $values = [];
        foreach ($node->filter($selector) as $element) {
            $value = $this->normalizeText($element->textContent);
            if ($value !== null) {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    private function attribute(Crawler $node, string $selector, string $name = 'src'): ?string
    {
        $field = $node->filter($selector);

        return $field->count() > 0 ? $this->attributeValue($field->first(), $name) : null;
    }

    private function attributeValue(Crawler $node, string $name): ?string
    {
        $value = $node->attr($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
