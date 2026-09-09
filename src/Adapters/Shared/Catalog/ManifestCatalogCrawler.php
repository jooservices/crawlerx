<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\Catalog;

use RuntimeException;

final class ManifestCatalogCrawler extends AbstractCatalogCrawler
{
    protected function definition(): CatalogDefinition
    {
        return match ($this->manifest()->slug) {
            '10musume' => new CatalogDefinition(
                slug: '10musume',
                label: '10musume',
                baseUrl: 'https://www.10musume.com',
                hosts: ['10musume.com', 'www.10musume.com'],
                detailPathPattern: '#^/movies/([^/]+)/?$#',
                listingUrl: 'https://www.10musume.com/',
                listingItemSelector: 'a[href^="/movies/"]',
                listingLinkSelector: 'a[href^="/movies/"]',
                screenshotSelectors: ['.movie-gallery img[data-vue-img-src]'],
                detailTitleSelectors: ['h1'],
                coverSelectors: ['.movie-main img' => 'src', '.movie-detail img' => 'src'],
            ),
            'pacopacomama' => new CatalogDefinition(
                slug: 'pacopacomama',
                label: 'Pacopacomama',
                baseUrl: 'https://www.pacopacomama.com',
                hosts: ['pacopacomama.com', 'www.pacopacomama.com'],
                detailPathPattern: '#^/movies/([^/]+)/?$#',
                listingUrl: 'https://www.pacopacomama.com/',
                listingItemSelector: 'a[href^="/movies/"]',
                listingLinkSelector: 'a[href^="/movies/"]',
                screenshotSelectors: ['.movie-gallery img[data-vue-img-src]'],
                detailTitleSelectors: ['h1'],
                coverSelectors: ['.movie-main img' => 'src', '.movie-detail img' => 'src'],
            ),
            'muramura' => new CatalogDefinition(
                slug: 'muramura',
                label: 'Muramura',
                baseUrl: 'https://www.muramura.tv',
                hosts: ['muramura.tv', 'www.muramura.tv'],
                detailPathPattern: '#^/movies/([^/]+)/?$#',
                listingUrl: 'https://www.muramura.tv/',
                listingItemSelector: 'a[href^="/movies/"]',
                listingLinkSelector: 'a[href^="/movies/"]',
                detailTitleSelectors: ['h1'],
                coverSelectors: ['.movie-main img' => 'src', '.movie-detail img' => 'src'],
            ),
            'kin8tengoku' => new CatalogDefinition(
                slug: 'kin8tengoku',
                label: 'Kin8tengoku',
                baseUrl: 'https://www.kin8tengoku.com',
                hosts: ['kin8tengoku.com', 'www.kin8tengoku.com'],
                detailPathPattern: '#^/movie/(\d+)/?$#',
                listingUrl: 'https://www.kin8tengoku.com/',
                listingItemSelector: 'a[href^="/movie/"]',
                listingLinkSelector: 'a[href^="/movie/"]',
                detailTitleSelectors: ['h1'],
            ),
            'moodyz' => $this->studioDefinition('moodyz', 'MOODYZ', 'https://moodyz.com'),
            'ideapocket' => $this->studioDefinition('ideapocket', 'IDEAPOCKET', 'https://ideapocket.com'),
            's1' => $this->studioDefinition('s1', 'S1 NO.1 STYLE', 'https://s1s1s1.com'),
            'madonna' => $this->studioDefinition('madonna', 'Madonna', 'https://madonna-av.com'),
            default => throw new RuntimeException('Unsupported manifest catalog adapter: ' . $this->manifest()->slug),
        };
    }

    private function studioDefinition(string $slug, string $label, string $baseUrl): CatalogDefinition
    {
        $host = (string) parse_url($baseUrl, PHP_URL_HOST);

        return new CatalogDefinition(
            slug: $slug,
            label: $label,
            baseUrl: $baseUrl,
            hosts: [$host],
            detailPathPattern: '#^/works/detail/([^/]+)/?$#',
            listingUrl: $baseUrl . '/top',
            listingItemSelector: 'a[href*="/works/detail/"]',
            listingLinkSelector: 'a[href*="/works/detail/"]',
            screenshotSelectors: ['.p-workPage__visual a[href]'],
            detailTitleSelectors: ['.p-workPage__title'],
        );
    }
}
