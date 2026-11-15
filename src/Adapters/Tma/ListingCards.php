<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Tma;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Reads the product cards of the TMA collection page (code, title, cover and detail URL per card).
 */
final class ListingCards
{
    use NormalizesUrls;
    use ParsesHtml;

    /** @return list<CrawlItemResultDto> */
    public function items(Crawler $dom): array
    {
        $items = [];
        foreach ($dom->filter('.blocklayout .block.product') as $element) {
            $item = $this->card(new Crawler($element));
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function card(Crawler $card): ?CrawlItemResultDto
    {
        $href = $this->firstAttribute($card, '.main a.img-link[href]', 'href');
        $slug = $href === null ? null : ProductUrl::slug($href);
        $title = $this->firstText($card, '.sub a.product-block-title');
        $cover = $this->firstAttribute($card, '.main noscript img[src]', 'src');
        if ($slug === null || $title === null || $cover === null) {
            return null;
        }

        return MovieDto::item(ProductUrl::canonical($slug), ProductUrl::externalId($slug), $title, [
            'code' => ProductUrl::code($slug),
            'cover_url' => $this->absolute(Site::BASE_URL, $cover),
            'metadata' => [MovieMeta::MAKER => Site::NAME],
        ]);
    }
}
