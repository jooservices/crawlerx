<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Asagei\Types;

use JOOservices\CrawlerX\Adapters\Asagei\Selectors;
use JOOservices\CrawlerX\Adapters\Asagei\Urls;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Dto\Entity\PhotoDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Gallery extends AsageiHtmlType implements TypeInterface
{
    protected function contextLabel(): string
    {
        return 'gallery';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $url = Urls::article($request->url);
        $crawler = $this->fetchCrawler(new CrawlRequestDto(url: $url, type: $request->type));
        $title = $this->firstText($crawler, Selectors::ARTICLE_TITLE);
        $externalId = Urls::externalId($url);
        $photos = $this->photos($crawler, $url);

        if ($title === null || $externalId === null || $photos === []) {
            throw new CrawlParseException('Asagei gallery page did not contain expected gallery fields.');
        }

        $posted = $this->postedAt($crawler, Selectors::ARTICLE_DATE, Selectors::ARTICLE_DATETIME);
        $taxonomy = $crawler->filter('.entry-utility-cats-tags');
        $taxonomyRoot = $taxonomy->count() > 0 ? $taxonomy->first() : $crawler;
        $categories = $this->labelledLinks($taxonomyRoot, 'カテゴリー', $url);
        $tags = $this->labelledLinks($taxonomyRoot, 'タグ', $url);
        $categoryHead = $this->firstText($crawler, Selectors::ARTICLE_CATEGORY_HEAD);
        $categoryNames = $this->linkNames($categories);
        if ($categoryHead !== null && ! in_array($categoryHead, $categoryNames, true)) {
            array_unshift($categoryNames, $categoryHead);
        }

        $ogImage = $this->firstAttribute($crawler, Selectors::OG_IMAGE, 'content');
        $cover = $this->coverFromUrl($ogImage, $url) ?? [
            'url' => $photos[0]->imageUrl,
            'mime_type' => Urls::mimeType((string) $photos[0]->imageUrl),
        ];

        $sourceFields = MovieMeta::filter([
            'Posted on' => $posted['raw'],
            'カテゴリー' => $categoryNames === [] ? null : implode(', ', $categoryNames),
            'タグ' => $tags === [] ? null : implode(', ', $this->linkNames($tags)),
        ]);

        return GalleryDto::item(
            url: $url,
            externalId: $externalId,
            title: $title,
            data: [
                'photo_count' => count($photos),
                'date' => $posted['date'],
                'categories' => $categoryNames,
                'tags' => $this->linkNames($tags),
                'photos' => $photos,
                'metadata' => MovieMeta::filter([
                    'published_at' => $posted['published_at'],
                    'description' => $this->firstAttribute($crawler, Selectors::OG_DESCRIPTION, 'content'),
                    'body' => $this->bodyParagraphs($crawler),
                    'category_urls' => $this->linkUrls($categories),
                    'tag_urls' => $this->linkUrls($tags),
                    'cover' => $cover,
                    'prev_url' => $this->navUrl($crawler, Selectors::ARTICLE_PREV, $url),
                    'next_url' => $this->navUrl($crawler, Selectors::ARTICLE_NEXT, $url),
                    'source_fields' => $sourceFields,
                ]),
            ],
        );
    }

    /** @return list<PhotoDto> */
    private function photos(Crawler $crawler, string $baseUrl): array
    {
        $photos = [];
        $seen = [];

        $crawler->filter(Selectors::ARTICLE_IMAGES)->each(function (Crawler $img) use (&$photos, &$seen, $baseUrl): void {
            $src = $this->imageSource($img);
            if ($src === null) {
                return;
            }

            $absolute = $this->absolute($baseUrl, $src);
            if ($this->isSiteChromeImage($absolute)) {
                return;
            }

            $full = Urls::original($absolute);
            if (isset($seen[$full])) {
                return;
            }
            $seen[$full] = true;

            $photos[] = new PhotoDto(
                url: $full,
                imageUrl: $full,
                thumbnailUrl: $full === $absolute ? null : $absolute,
                position: count($photos) + 1,
                metadata: MovieMeta::filter([
                    'alt' => $this->normalizeText((string) $img->attr('alt')),
                    'width' => $this->dimension($img->attr('width')),
                    'height' => $this->dimension($img->attr('height')),
                    'source_page_url' => $baseUrl,
                    'mime_type' => Urls::mimeType($full),
                ]),
            );
        });

        return $photos;
    }

    /** @return list<string> */
    private function bodyParagraphs(Crawler $crawler): array
    {
        return array_values(array_filter(
            $crawler->filter(Selectors::ARTICLE_BODY . ' > p')->each(
                function (Crawler $paragraph): string {
                    if ($paragraph->filter('img')->count() > 0 && $this->normalizeText($paragraph->text('')) === null) {
                        return '';
                    }

                    return $this->normalizeText($paragraph->text('')) ?? '';
                },
            ),
            static fn(string $text): bool => $text !== '',
        ));
    }

    private function navUrl(Crawler $crawler, string $selector, string $baseUrl): ?string
    {
        $href = $this->firstAttribute($crawler, $selector, 'href');

        return $href === null ? null : Urls::article($this->absolute($baseUrl, $href));
    }

    private function dimension(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
