<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\AvfanProfiles\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\AvfanProfiles\Selectors;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

final class PerformerDetail extends AbstractType implements TypeInterface
{
    use NormalizesUrls;
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'avfan_profiles', 'performer_detail');
        $crawler = new Crawler($html, $request->url);

        $nameNode = $crawler->filter(Selectors::PERFORMER_NAME);
        if ($nameNode->count() === 0) {
            throw new CrawlParseException('AvfanProfiles performer detail is missing a valid name.');
        }

        $name = $this->normalizeText($nameNode->first()->text('')) ?? '';
        $externalId = $this->externalId($request->url);
        if ($name === '') {
            throw new CrawlParseException('AvfanProfiles performer detail is missing a valid name.');
        }

        $bio = $this->bioRows($crawler);
        $sns = $this->snsLinks($crawler, $request->url);

        $data = array_filter([
            'external_id' => $externalId,
            'external_url' => $request->url,
            'name' => $name,
            'name_japanese' => $name,
            'name_kana' => $this->firstText($crawler, Selectors::PERFORMER_NAME_KANA),
            'birth_date_raw' => $bio['誕生日'] ?? null,
            'size_raw' => $bio['スリーサイズ'] ?? null,
            'cup_size' => $this->cupFromSize($bio['スリーサイズ'] ?? null),
            'birthplace' => $bio['出身地'] ?? null,
            'blood_type' => $bio['血液型'] ?? null,
            'hobby' => $bio['趣味・特技'] ?? null,
            'debut_date_raw' => $bio['デビュー日'] ?? null,
            'agency' => $bio['事務所'] ?? null,
            'official_url' => $bio['公式サイト・ブログ'] ?? null,
            'sns' => $sns !== [] ? $sns : null,
            'profile_image_url' => $this->profileImage($crawler, $request->url),
        ], fn(mixed $value): bool => $value !== null && $value !== '');

        $item = PerformerDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $name,
            data: $data,
        );

        $this->assertUsablePerformerDetail($item, 'avfan_profiles');

        return $item;
    }

    /**
     * @return array<string, string>
     */
    private function bioRows(Crawler $crawler): array
    {
        $rows = [];
        $crawler->filter(Selectors::BIO_ROWS)->each(function (Crawler $row) use (&$rows): void {
            $label = $this->firstText($row, Selectors::BIO_LABEL);
            $value = $this->firstText($row, Selectors::BIO_VALUE);
            if ($label !== null && $value !== null) {
                $rows[$label] = $value;
            }
        });

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function snsLinks(Crawler $crawler, string $baseUrl): array
    {
        $links = [];
        $crawler->filter(Selectors::SNS_LINKS)->each(function (Crawler $node) use (&$links, $baseUrl): void {
            $href = $node->attr('href');
            if (! is_string($href) || $href === '') {
                return;
            }

            $links[$this->absolute($baseUrl, $href)] = true;
        });

        return array_keys($links);
    }

    private function cupFromSize(?string $size): ?string
    {
        if ($size === null) {
            return null;
        }

        if (preg_match('/[BWK](\d+)\s*\(([A-Z])\)/', $size, $match) === 1) {
            return $match[2];
        }

        return null;
    }

    private function profileImage(Crawler $crawler, string $baseUrl): ?string
    {
        $node = $crawler->filter(Selectors::PROFILE_IMAGE);
        if ($node->count() === 0) {
            return null;
        }

        $src = $node->first()->attr('src');
        if (! is_string($src) || trim($src) === '') {
            return null;
        }

        return $this->absolute($baseUrl, $src);
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#^/actress/([^/]+)\.html$#i', $path, $match) === 1) {
            $slug = $match[1];

            return $slug;
        }

        return null;
    }
}
