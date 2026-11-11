<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Gentosha\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Gentosha\Selectors;
use JOOservices\CrawlerX\Adapters\Gentosha\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return 'Gentosha';
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $schema = $this->bookSchema($crawler);
        $workExample = $schema['workExample'] ?? null;
        $edition = $workExample instanceof \stdClass ? get_object_vars($workExample) : [];
        $isbn = $this->isbn($request->url) ?? $this->string($edition['isbn'] ?? null);
        $title = $this->text($crawler, Selectors::DETAIL_TITLE) ?? $this->string($schema['name'] ?? null);
        if ($title === null) {
            throw new CrawlParseException('Gentosha detail page did not contain expected book fields.');
        }

        $authors = $this->authors($crawler, $schema);
        $category = $this->categories($crawler, $schema);
        $publishedText = $this->text($crawler, Selectors::DETAIL_RELEASE_TEXT);
        $published = $this->attribute($crawler, Selectors::DETAIL_RELEASE_DATE, 'datetime')
            ?? $this->string($edition['datePublished'] ?? null);
        $priceLabel = $this->text($crawler, Selectors::DETAIL_PRICE);
        $priceNote = $this->text($crawler, Selectors::DETAIL_PRICE_NOTE);
        $coverThumbnail = $this->attribute($crawler, Selectors::DETAIL_COVER_THUMBNAIL);
        $cover = $this->attribute($crawler, Selectors::DETAIL_COVER_FULL, 'data-src')
            ?? $this->attribute($crawler, Selectors::DETAIL_COVER)
            ?? $this->string($edition['image'] ?? null);
        $description = $this->text($crawler, Selectors::DETAIL_DESCRIPTION)
            ?? $this->string($schema['description'] ?? null);
        $rows = $this->sourceRows($crawler, $title, $authors, $category, $priceLabel, $publishedText ?? $published, $isbn);
        $screenshots = $this->screenshots($crawler, $cover, $coverThumbnail);

        $item = MovieDto::item($request->url, $isbn, $title, [
            'code' => $isbn,
            'description' => $description,
            'date' => MovieMeta::isoDate($published),
            'cover_url' => $cover,
            'tags' => array_column($category, 'name'),
            'screenshots' => $screenshots,
            'metadata' => MovieMeta::filter([
                MovieMeta::MAKER => Site::MAKER,
                MovieMeta::PRICE => $this->price($priceLabel),
                'authors' => $authors,
                'category' => array_column($category, 'name'),
                'publisher' => $this->publisher($edition['publisher'] ?? null),
                'book_format' => $this->string($edition['bookFormat'] ?? null),
                'cover_thumbnail_url' => $coverThumbnail,
                'price_note' => $priceNote,
                'purchase_links' => $this->purchaseLinks($crawler),
                'source_fields' => $rows,
            ]),
        ]);
        $this->assertUsableMovieDetail($item, Site::MAKER);

        return $item;
    }

    /** @return array<string, mixed> */
    private function bookSchema(Crawler $crawler): array
    {
        foreach ($crawler->filter(Selectors::JSON_LD) as $element) {
            $decoded = json_decode($element->textContent);
            if ($decoded instanceof \stdClass) {
                if (($decoded->{'@type'} ?? null) === 'Book') {
                    return [
                        '@type' => $decoded->{'@type'},
                        'workExample' => $decoded->workExample ?? null,
                        'name' => $decoded->name ?? null,
                        'author' => $decoded->author ?? null,
                        'description' => $decoded->description ?? null,
                        'genre' => $decoded->genre ?? null,
                    ];
                }
            }
        }

        return [];
    }

    /** @param array<string, mixed> $schema
     * @return list<array{name: string, url: ?string, external_id: ?string}>
     */
    private function authors(Crawler $crawler, array $schema): array
    {
        $authors = [];
        foreach ($crawler->filter(Selectors::DETAIL_AUTHORS . ' a') as $element) {
            $link = new Crawler($element);
            $name = $this->normalizeText($link->text(''));
            if ($name === null) {
                continue;
            }

            $href = $link->attr('href');
            $url = is_string($href) ? $this->absolute(Site::BASE_URL, $href) : null;
            $authors[] = ['name' => $name, 'url' => $url, 'external_id' => $url === null ? null : $this->lastPathSegment($url)];
        }

        if ($authors === []) {
            $schemaAuthors = is_array($schema['author'] ?? null) ? $schema['author'] : [];
            if (isset($schema['author']) && ! is_array($schema['author'])) {
                $schemaAuthors = [$schema['author']];
            }
            foreach ($schemaAuthors as $author) {
                if ($author instanceof \stdClass) {
                    $author = get_object_vars($author);
                }
                if (is_array($author)) {
                    $name = $this->string($author['name'] ?? null);
                    if ($name !== null) {
                        $authors[] = ['name' => $name, 'url' => $this->string($author['url'] ?? null), 'external_id' => null];
                    }
                }
            }
        }

        return $authors;
    }

    /** @param array<string, mixed> $schema
     * @return list<array{name: string}>
     */
    private function categories(Crawler $crawler, array $schema): array
    {
        $categories = [];
        foreach ($crawler->filter(Selectors::DETAIL_CATEGORY) as $element) {
            $name = $this->normalizeText($element->textContent);
            if ($name !== null) {
                $categories[$name] = ['name' => $name];
            }
        }

        if ($categories === []) {
            $genres = $schema['genre'] ?? [];
            foreach (is_array($genres) ? $genres : [$genres] as $genre) {
                $name = $this->string($genre);
                if ($name !== null) {
                    $categories[$name] = ['name' => $name];
                }
            }
        }

        return array_values($categories);
    }

    /** @return list<array{url: string, thumbnail_url: ?string, position: int}> */
    private function screenshots(Crawler $crawler, ?string $cover, ?string $coverThumbnail): array
    {
        $images = [];
        if ($cover !== null) {
            $images[$cover] = [
                'url' => $cover,
                'thumbnail_url' => $coverThumbnail,
                'position' => 0,
            ];
        }

        foreach ($crawler->filter(Selectors::DETAIL_WORK_IMAGES) as $element) {
            $image = new Crawler($element);
            $src = $this->attributeValue($image, 'data-src') ?? $this->attributeValue($image, 'src');
            if ($src === null) {
                continue;
            }

            $url = $this->absolute(Site::BASE_URL, $src);
            if (! isset($images[$url])) {
                $images[$url] = [
                    'url' => $url,
                    'thumbnail_url' => null,
                    'position' => count($images),
                ];
            }
        }

        return array_values($images);
    }

    /** @param list<array{name: string, url: ?string, external_id: ?string}> $authors
     * @param list<array{name: string}> $categories
     * @return array<string, string>
     */
    private function sourceRows(
        Crawler $crawler,
        string $title,
        array $authors,
        array $categories,
        ?string $price,
        ?string $published,
        ?string $isbn,
    ): array {
        $rows = [];
        foreach ($crawler->filter(Selectors::DETAIL_INFO_ROWS) as $element) {
            $row = new Crawler($element);
            $label = $this->text($row, '.p-book-infoData__label');
            $value = $this->text($row, '.p-book-infoData__text');
            if ($label !== null && $value !== null) {
                $cleanLabel = trim(str_replace(['：', ':'], '', $label));
                $rows[$cleanLabel] = $cleanLabel . ' ' . $value;
            }
        }

        $authorNames = [];
        foreach ($authors as $author) {
            $authorNames[] = $author['name'];
        }
        $categoryNames = implode(', ', array_column($categories, 'name'));

        $additionalRows = [
            'タイトル' => 'タイトル ' . $title,
            '著者' => $authorNames === [] ? null : '著者 ' . implode(', ', $authorNames),
            '分類' => $categoryNames === '' ? null : '分類 ' . $categoryNames,
            '価格' => $price === null ? null : '価格 ' . $price,
            '発売日' => $published === null ? null : '発売日 ' . $published,
        ];
        foreach ($additionalRows as $label => $value) {
            if ($value !== null) {
                $rows[$label] = $value;
            }
        }

        return $rows;
    }

    /** @return list<array{name: string, url: string, format: ?string}> */
    private function purchaseLinks(Crawler $crawler): array
    {
        $links = [];
        foreach ($crawler->filter(Selectors::DETAIL_PURCHASE_LINKS) as $element) {
            $link = new Crawler($element);
            $href = $link->attr('href');
            $name = $this->attributeValue($link, 'data-store');
            if (is_string($href) && $name !== null) {
                $links[] = [
                    'name' => $name,
                    'url' => $href,
                    'format' => $this->attributeValue($link, 'data-variant'),
                ];
            }
        }

        return $links;
    }

    private function lastPathSegment(string $url): string
    {
        return trim(basename((string) parse_url($url, PHP_URL_PATH)), '/');
    }

    /** @return array{amount: ?float, currency: ?string, label: ?string}|null */
    private function price(?string $label): ?array
    {
        if ($label === null || preg_match('/([\d,]+)\s*円/u', $label, $match) !== 1) {
            return null;
        }

        return MovieMeta::price((float) str_replace(',', '', $match[1]), 'JPY', $label);
    }

    private function isbn(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return preg_match('#^/book/detail/(\d{13})/?$#', $path, $match) === 1 ? $match[1] : null;
    }

    private function text(Crawler $node, string $selector): ?string
    {
        $field = $node->filter($selector);

        return $field->count() > 0 ? $this->normalizeText($field->first()->text('')) : null;
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

    private function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function publisher(mixed $value): string
    {
        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        }

        return is_array($value) ? ($this->string($value['name'] ?? null) ?? Site::MAKER) : Site::MAKER;
    }
}
