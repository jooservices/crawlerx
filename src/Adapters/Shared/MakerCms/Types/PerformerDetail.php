<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Fields;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use Symfony\Component\DomCrawler\Crawler;

final class PerformerDetail extends AbstractType implements TypeInterface
{
    public function __construct(private readonly CrawlHttpClient $client, private readonly SiteConfig $config)
    {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $id = $this->config->entityId($request->url, $this->config->actressPath);
        $response = $this->client->get($request->url);
        $dom = new Crawler($this->htmlFromResponse($response->toPsrResponse(), $this->config->maker, 'actress'));
        $fields = new Fields($this->config);
        $profile = [];
        foreach ($fields->rows($dom, '.p-profile__info .table > .item') as $label => $row) {
            $profile[$label] = $fields->text($row, '.td');
        }
        $japanese = $fields->text($dom, '.p-profile__title .top');
        $latin = $fields->text($dom, '.p-profile__title .bottom');
        $images = $fields->images($dom, '.p-profile__imgArea .swiper-slide');
        $works = $fields->links($dom, '.p-profile a[href*="' . $this->config->detailPath . '"]');
        $cards = $dom->filter('.p-profile a[href*="' . $this->config->detailPath . '"]');
        foreach (array_keys($works) as $index) {
            $works[$index]['url'] = explode('?', $works[$index]['url'], 2)[0];
            $works[$index]['cover_url'] = $fields->image($cards->eq($index), 'img');
        }
        $item = PerformerDto::item($request->url, $id, $latin ?? $japanese, [
            'name_japanese' => $japanese,
            'aliases' => array_values(array_filter([$japanese, $latin], static fn(?string $name): bool => $name !== null)),
            'profile_image_url' => $images[0] ?? null,
            'birth_date_raw' => $fields->date($profile['誕生日'] ?? null),
            'height_raw' => $profile['身長'] ?? null,
            'size_raw' => $profile['3サイズ'] ?? null,
            'raw_profile' => $profile,
            'metadata' => [
                'images' => $images,
                'profile_links' => $fields->links($dom, '.p-profile__info .td a[href]'),
                'social_links' => $fields->links($dom, '.p-profile__info .sns a[href]'),
                'works' => $works,
                'work_range' => $fields->text($dom, '.p-profile .swiper-pagination-02'),
            ],
        ]);
        $this->assertUsablePerformerDetail($item, $this->config->maker);

        return $item;
    }
}
