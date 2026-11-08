<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Gravurech\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Gravurech\ContentUrl;
use JOOservices\CrawlerX\Adapters\Gravurech\Selectors;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Dto\Entity\PhotoDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Only the cover is public: the photo pages of a work are member-only and are never requested.
 */
final class Gallery extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    private const PAGE_COUNT_LABEL = 'ページ数';

    protected function siteLabel(): string
    {
        return 'gravurech';
    }

    protected function contextLabel(): string
    {
        return 'gallery';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $title = $this->firstText($crawler, Selectors::DETAIL_TITLE);
        $code = ContentUrl::code($request->url);

        if ($title === null || $code === null) {
            throw new CrawlParseException('Gravurech gallery page did not contain expected gallery fields.');
        }

        $photos = $this->photos($crawler, $request->url);
        $performers = $this->links($crawler, Selectors::DETAIL_PERFORMERS, $request->url);
        $tags = $this->links($crawler, Selectors::DETAIL_TAGS, $request->url);
        $fields = $this->sourceFields($crawler);
        $description = $this->firstText($crawler, Selectors::DETAIL_DESCRIPTION);
        $issue = $this->issue($description);
        $category = $this->firstText($crawler, Selectors::DETAIL_CATEGORY);

        return GalleryDto::item(
            url: $request->url,
            externalId: $code,
            title: $title,
            data: [
                'photo_count' => $this->pageCount($fields),
                'date' => $issue['date'],
                'performers' => array_column($performers, 'name'),
                'categories' => $category === null ? [] : [$category],
                'tags' => array_column($tags, 'name'),
                'photos' => $photos,
                'metadata' => [
                    'content_id' => $this->meta($crawler, 'articleid') ?? ContentUrl::numericId($photos[0]->imageUrl ?? null),
                    'description' => $description,
                    'magazine' => $issue['magazine'],
                    'performer_urls' => array_column($performers, 'url'),
                    'performer_ids' => array_column($performers, 'id'),
                    'tag_urls' => array_column($tags, 'url'),
                    'tag_ids' => array_column($tags, 'id'),
                    'category_label' => $this->meta($crawler, 'ftb-category'),
                    'cast_label' => $this->meta($crawler, 'ftb-cast'),
                    'costume' => $this->meta($crawler, 'ftb-coutume'),
                    'job' => $this->meta($crawler, 'ftb-job'),
                    'content_class' => $this->meta($crawler, 'ftb-content-class'),
                    'source_fields' => $fields,
                ],
            ],
        );
    }

    /** @return list<PhotoDto> every public image of the work (the cover), absolute and de-duplicated */
    private function photos(Crawler $crawler, string $baseUrl): array
    {
        $urls = $crawler->filter(Selectors::DETAIL_COVER)->each(
            fn(Crawler $image): ?string => $image->attr('src') === null ? null : $this->absolute($baseUrl, $image->attr('src')),
        );

        $photos = [];
        foreach (array_values(array_unique(array_filter($urls, static fn(?string $url): bool => $url !== null))) as $index => $url) {
            $photos[] = new PhotoDto(
                id: ContentUrl::numericId($url),
                url: $url,
                imageUrl: $url,
                thumbnailUrl: $url,
                position: $index + 1,
            );
        }

        return $photos;
    }

    /** @return list<array{name: string, url: string, id: ?string}> */
    private function links(Crawler $crawler, string $selector, string $baseUrl): array
    {
        $links = [];
        $crawler->filter($selector)->each(function (Crawler $node) use (&$links, $baseUrl): void {
            $name = $this->normalizeText($node->text(''));
            if ($name === null) {
                return;
            }

            $url = $this->absolute($baseUrl, (string) $node->attr('href'));
            $links[$url] = [
                'name' => $name,
                'url' => $url,
                'id' => preg_match('#/(\d+)/?$#', $url, $match) === 1 ? $match[1] : null,
            ];
        });

        return array_values($links);
    }

    /** @return array<string, string> the labelled rows of the description table */
    private function sourceFields(Crawler $crawler): array
    {
        $fields = [];
        $crawler->filter(Selectors::DETAIL_FIELD_ROWS)->each(function (Crawler $row) use (&$fields): void {
            $label = $this->firstText($row, 'dt');
            $value = $this->firstText($row, 'dd');
            if ($label !== null && $value !== null) {
                $fields[$label] = $value;
            }
        });

        return $fields;
    }

    /** @param array<string, string> $fields */
    private function pageCount(array $fields): ?int
    {
        $value = $fields[self::PAGE_COUNT_LABEL] ?? null;

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    /**
     * "漫画アクション2025年7月15日号掲載" is the magazine and the issue date of the original publication.
     *
     * @return array{magazine: ?string, date: ?string}
     */
    private function issue(?string $description): array
    {
        $none = ['magazine' => null, 'date' => null];
        if ($description === null || preg_match('/^(.+?)\s*(\d{4})年(\d{1,2})月(\d{1,2})日号/u', $description, $match) !== 1) {
            return $none;
        }

        [, $magazine, $year, $month, $day] = $match;
        if (! checkdate((int) $month, (int) $day, (int) $year)) {
            return $none;
        }

        return ['magazine' => $magazine, 'date' => sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day)];
    }

    private function meta(Crawler $crawler, string $name): ?string
    {
        $value = $this->firstAttribute($crawler, sprintf(Selectors::META_PREFIX, $name), 'content');

        return $value === null ? null : $this->normalizeText($value);
    }
}
