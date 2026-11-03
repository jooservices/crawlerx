<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\CaribbeanCom;

use JOOservices\CrawlerX\Adapters\Shared\Catalog\CatalogDefinition;

/**
 * Per-site configuration of the Caribbeancom family (caribbeancom, caribbeancompr). The listing and detail parsers
 * are shared; a profile only says which selectors, spec labels and urls a site uses.
 */
final class Profile
{
    /**
     * @param list<string>               $hosts
     * @param list<string>               $titleSuffixes
     * @param array<string, list<string>> $specLabels    spec key (performers, date, duration, tags, maker) => labels
     * @param list<string>               $posterFiles    poster file names the player script may build
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $label,
        public readonly string $baseUrl,
        public readonly array $hosts,
        public readonly string $detailPathPattern,
        public readonly string $listingUrl,
        public readonly string $specSelector,
        public readonly string $titleSelector,
        public readonly string $downloadLinkSelector,
        public readonly array $specLabels,
        public readonly array $posterFiles,
        public readonly string $performerIdPattern = '',
        public readonly ?string $descriptionSelector = null,
        public readonly ?string $purchaseSelector = null,
        public readonly bool $playerFlags = false,
        public readonly array $titleSuffixes = [],
    ) {
    }

    public static function caribbeancom(): self
    {
        return new self(
            slug: 'caribbeancom',
            label: 'Caribbeancom',
            baseUrl: 'https://en.caribbeancom.com',
            hosts: ['en.caribbeancom.com', 'caribbeancom.com'],
            detailPathPattern: '#^/eng/moviepages/([^/]+)/index\.html$#i',
            listingUrl: 'https://en.caribbeancom.com/eng/index2.htm',
            specSelector: '.movie-detail__spec',
            titleSelector: 'h1[itemprop="name"], .movie-info h1',
            downloadLinkSelector: 'a.invisible-cta, a#dl-btn',
            specLabels: [
                'performers' => ['Starring'],
                'date' => ['Release Date'],
                'duration' => ['Duration'],
                'tags' => ['Tags'],
            ],
            posterFiles: ['poster_en.jpg'],
            performerIdPattern: '~/search_act/(\d+)/~',
            titleSuffixes: [' - Caribbeancom.com'],
        );
    }

    /** Caribbeancom Premium: English pages under /eng/ on en.caribbeancompr.com, Japanese pages on www.caribbeancompr.com. */
    public static function caribbeancompr(): self
    {
        return new self(
            slug: 'caribbeancompr',
            label: 'Caribbeancom Premium',
            baseUrl: 'https://en.caribbeancompr.com',
            hosts: ['en.caribbeancompr.com', 'www.caribbeancompr.com', 'caribbeancompr.com'],
            detailPathPattern: '#^(?:/eng)?/moviepages/([^/]+)/index\.html$#i',
            listingUrl: 'https://en.caribbeancompr.com/eng/listpages/1.html',
            specSelector: '.movie-spec',
            titleSelector: '.movie-info h1',
            downloadLinkSelector: '.download-cta a[href]',
            specLabels: [
                'performers' => ['Starring', '出演'],
                'date' => ['Release Date', '販売日'],
                'duration' => ['Duration', '再生時間'],
                'tags' => ['Tags', 'タグ'],
                'maker' => ['Studio', 'スタジオ'],
            ],
            posterFiles: ['poster_en.jpg', 'l_l.jpg'],
            descriptionSelector: '.movie-info .section > p',
            purchaseSelector: '.movie-purchase',
            playerFlags: true,
            titleSuffixes: [' - Caribbeancom Premium'],
        );
    }

    public function definition(): CatalogDefinition
    {
        return new CatalogDefinition(
            slug: $this->slug,
            label: $this->label,
            baseUrl: $this->baseUrl,
            hosts: $this->hosts,
            detailPathPattern: $this->detailPathPattern,
            listingUrl: $this->listingUrl,
            listingItemSelector: '.entry, .swiper-slide',
            listingLinkSelector: 'a[href*="/moviepages/"]',
            titleSuffixes: $this->titleSuffixes,
        );
    }

    public function specKey(string $title): ?string
    {
        foreach ($this->specLabels as $key => $labels) {
            if (in_array($title, $labels, true)) {
                return $key;
            }
        }

        return null;
    }
}
