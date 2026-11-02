<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Shared;

use Faker\Factory;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\FamilyCrawler;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\SiteConfig;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

class OnePondoFamilyTest extends TestCase
{
    public function test_another_site_uses_its_own_urls_and_metadata(): void
    {
        $faker = Factory::create();
        $base = 'https://' . $faker->domainName();
        $maker = $faker->company();
        $config = new SiteConfig(
            baseUrl: $base,
            assetBaseUrl: $base . '/assets/',
            moviePath: '/videos/{id}/',
            movieUrlPattern: '#^' . preg_quote($base, '#') . '/videos/(\d{6}_\d{3})/$#',
            galleryPath: '/gallery/{id}.json',
            galleryImagePath: '/pictures/',
            maker: $maker,
            label: $maker,
            gallerySource: $faker->slug(),
        );
        $image = 'movie/sample/' . $faker->uuid() . '.jpg';
        $client = $this->clientWithMappedResponses([
            $base . '/dyn/phpauto/movie_details/movie_id/060426_001.json' => ['body' => $this->loadFixture('onepondo/detail-060426_001.json')],
            $base . '/gallery/060426_001.json' => ['body' => json_encode(['Rows' => [['Img' => $image]]], JSON_THROW_ON_ERROR)],
            $base . '/dyn/phpauto/movie_lists/list_newest_0.json' => ['body' => $this->loadFixture('onepondo/list-newest-0.json')],
        ]);
        $normalizer = new UrlNormalizer($config);
        $crawler = new class ($client, $config) extends FamilyCrawler {
            public function __construct(CrawlHttpClient $client, private readonly SiteConfig $config)
            {
                parent::__construct(new ClientFactory());
                $this->client = $client;
            }

            public function name(): string
            {
                return $this->config->label;
            }

            protected function siteConfig(): SiteConfig
            {
                return $this->config;
            }
        };
        $url = $base . '/videos/060426_001/';
        $detail = $crawler->detail(new CrawlRequestDto(url: $url));
        self::assertSame($url, $detail->url);
        self::assertSame($maker, $detail->meta['movie']['metadata']['maker']);
        self::assertSame($base . '/assets/pictures/' . $image, $detail->meta['movie']['screenshots'][0]['url']);
        $listing = $crawler->listing(new CrawlRequestDto(url: $base . '/list/'));
        self::assertStringStartsWith($base . '/videos/', $listing->items[0]->url);
        self::assertSame($base . '/assets/file.jpg', $normalizer->assetUrl('/file.jpg'));
        self::assertSame($base . '/file.jpg', $normalizer->absolute($base, '//' . parse_url($base, PHP_URL_HOST) . '/file.jpg'));
    }
}
