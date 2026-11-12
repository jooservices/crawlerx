<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Tma\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Tma\ProductFields;
use JOOservices\CrawlerX\Adapters\Tma\ProductImages;
use JOOservices\CrawlerX\Adapters\Tma\ProductUrl;
use JOOservices\CrawlerX\Adapters\Tma\Site;
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
        return Site::NAME;
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $dom = $this->fetchCrawler($request);
        $slug = ProductUrl::slug($request->url);
        $title = $this->firstText($dom, 'h1.product-title');
        $images = (new ProductImages())->read($dom);
        if ($slug === null || $title === null || $images === []) {
            throw new CrawlParseException('TMA detail did not contain expected work fields and images.');
        }

        $fields = new ProductFields($dom);
        $item = MovieDto::item(ProductUrl::canonical($slug), ProductUrl::externalId($slug), $title, [
            'code' => $fields->code() ?? ProductUrl::code($slug),
            'description' => $this->firstText($dom, '.description p'),
            'date' => $fields->date(),
            'duration' => $fields->duration(),
            'cover_url' => $images[0]['url'],
            'performers' => $this->texts($dom, '.morelinks .in-page-tags a.tag'),
            'tags' => $fields->type() === null ? [] : [$fields->type()],
            'screenshots' => $images,
            'metadata' => $this->metadata($dom, $fields),
        ]);
        $this->assertUsableMovieDetail($item, Site::NAME);

        return $item;
    }

    /** @return array<string, mixed> */
    private function metadata(Crawler $dom, ProductFields $fields): array
    {
        $price = $fields->priceIncludingTax();

        return [
            MovieMeta::MAKER => $fields->maker() ?? Site::NAME,
            MovieMeta::PRICE => MovieMeta::price(
                $price === null ? null : (float) $price,
                'JPY',
                $fields->rows()['価格'] ?? null,
            ),
            'price_excluding_tax' => $fields->priceExcludingTax(),
            'jan_code' => $fields->janCode(),
            MovieMeta::SAMPLE_VIDEO_URL => $this->firstAttribute($dom, '.description video[src]', 'src'),
            'purchase_links' => $this->purchaseLinks($dom),
            'source_fields' => $fields->rows(),
        ];
    }

    /** @return list<array{name: string, url: string}> */
    private function purchaseLinks(Crawler $dom): array
    {
        $links = [];
        foreach ($dom->filter('.description a[href]') as $element) {
            $link = new Crawler($element);
            $name = trim($link->text(''));
            $href = trim((string) $link->attr('href'));
            if ($name !== '' && $href !== '') {
                $links[] = ['name' => $name, 'url' => $href];
            }
        }

        return $links;
    }
}
