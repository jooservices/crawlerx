<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Yuira\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Yuira\GalleryImages;
use JOOservices\CrawlerX\Adapters\Yuira\ProductFields;
use JOOservices\CrawlerX\Adapters\Yuira\Selectors;
use JOOservices\CrawlerX\Adapters\Yuira\Site;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
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

    private const PRICE_LABEL = '参考価格';

    public function __construct(
        CrawlHttpClient $client,
        private readonly GalleryImages $gallery = new GalleryImages(),
    ) {
        parent::__construct($client);
    }

    protected function siteLabel(): string
    {
        return Site::NAME;
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $page = $this->fetchCrawler($request);
        $title = $this->firstText($page, Selectors::DETAIL_TITLE);
        $images = $this->gallery->read($page);
        if ($title === null || $images === []) {
            throw new CrawlParseException('YUIRA detail did not contain expected product fields and images.');
        }

        $fields = new ProductFields($page);
        $item = MovieDto::item($request->url, Site::productId($request->url), $title, [
            'code' => $fields->value('品番'),
            'description' => $fields->description(),
            'date' => MovieMeta::isoDate($fields->value('発売日')),
            'cover_url' => $images[0]['url'],
            'screenshots' => $this->screenshots($images),
            'tags' => $fields->categories(),
            'metadata' => $this->metadata($page, $fields),
        ]);
        $this->assertUsableMovieDetail($item, Site::NAME);

        return $item;
    }

    /**
     * @param  list<array{url: string, thumbnail_url: ?string}>  $images
     * @return list<array{url: string, thumbnail_url: ?string, position: int}>
     */
    private function screenshots(array $images): array
    {
        return array_map(
            static fn(array $image, int $position): array => ['url' => $image['url'], 'thumbnail_url' => $image['thumbnail_url'], 'position' => $position],
            $images,
            array_keys($images),
        );
    }

    /** @return array<string, mixed> */
    private function metadata(Crawler $page, ProductFields $fields): array
    {
        return MovieMeta::filter([
            MovieMeta::MAKER => $fields->value('メーカー'),
            'jan' => $fields->value('JAN'),
            MovieMeta::PRICE => $this->price($page),
            'purchase_links' => $this->purchaseLinks($page),
            'source_fields' => $fields->sourceFields(),
        ]);
    }

    /** @return ?array{amount: ?float, currency: ?string, label: ?string} */
    private function price(Crawler $page): ?array
    {
        $digits = preg_replace('/\D+/', '', (string) $this->firstText($page, Selectors::DETAIL_PRICE));

        return $digits === '' || $digits === null ? null : MovieMeta::price((float) $digits, 'JPY', self::PRICE_LABEL);
    }

    /** @return list<array{name: string, url: string}> */
    private function purchaseLinks(Crawler $page): array
    {
        $links = [];
        foreach ($page->filter(Selectors::PURCHASE_LINK) as $element) {
            $link = new Crawler($element);
            $name = $this->normalizeText($link->text(''));
            $href = trim((string) $link->attr('href'));
            if ($name !== null && $href !== '') {
                $links[] = ['name' => $name, 'url' => $href];
            }
        }

        return $links;
    }
}
