<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Avjoho\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Avjoho\Selectors;
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
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'avjoho', 'performer_detail');
        $crawler = new Crawler($html, $request->url);

        $nameNode = $crawler->filter(Selectors::PERFORMER_NAME);
        if ($nameNode->count() === 0) {
            throw new CrawlParseException('Avjoho performer detail is missing a valid name.');
        }

        $name = $this->normalizeText($nameNode->first()->text('')) ?? '';
        $externalId = $this->externalId($request->url);
        if ($name === '') {
            throw new CrawlParseException('Avjoho performer detail is missing a valid name.');
        }

        $profile = $this->profileTable($crawler);
        $birthplace = $this->tableValue($crawler, Selectors::BIRTHPLACE_TABLE);
        $bloodType = $this->tableValue($crawler, Selectors::BLOOD_TYPE_TABLE);
        $hobby = $this->tableValue($crawler, Selectors::HOBBY_TABLE);
        $alias = $this->tableValue($crawler, Selectors::ALIAS_TABLE);
        $maker = $this->tableValue($crawler, Selectors::MAKER_TABLE);
        $sns = $this->tableValue($crawler, Selectors::SNS_TABLE);

        $data = array_filter([
            'external_id' => $externalId,
            'external_url' => $request->url,
            'name' => $name,
            'name_japanese' => $name,
            'debut_date_raw' => $profile['デビュー'] ?? null,
            'birth_date_raw' => $profile['生年月日'] ?? null,
            'height_raw' => $profile['身長'] ?? null,
            'size_raw' => $profile['スリーサイズ'] ?? null,
            'cup_size' => $profile['カップ'] ?? null,
            'birthplace' => $birthplace,
            'blood_type' => $bloodType,
            'hobby' => $hobby,
            'aliases' => array_filter([(string) $alias], static fn(string $value): bool => $value !== '–' && $value !== '-'),
            'maker' => $maker,
            'sns' => $sns,
            'raw_profile' => $this->normalizeText($crawler->filter(Selectors::BIO_TEXT)->text('')) ?? null,
            'profile_image_url' => $this->coverUrl($crawler, $request->url),
        ], fn(mixed $value): bool => $value !== null && $value !== '');

        $item = PerformerDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $name,
            data: $data,
        );

        $this->assertUsablePerformerDetail($item, 'avjoho');

        return $item;
    }

    /**
     * @return array<string, string>
     */
    private function profileTable(Crawler $crawler): array
    {
        $node = $crawler->filter(Selectors::PROFILE_TABLE);
        if ($node->count() === 0) {
            return [];
        }

        $fields = [];
        $node->first()->filter('tr')->each(function (Crawler $row) use (&$fields): void {
            $label = $this->firstText($row, 'th');
            $value = $this->firstText($row, 'td');
            if ($label !== null && $value !== null) {
                $fields[$label] = $value;
            }
        });

        return $fields;
    }

    private function tableValue(Crawler $crawler, string $selector): ?string
    {
        $node = $crawler->filter($selector);
        if ($node->count() === 0) {
            return null;
        }

        return $this->firstText($node->first(), 'td');
    }

    private function coverUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $node = $crawler->filter(Selectors::COVER_IMAGE);
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
        if (is_string($path) && preg_match('#^/([^/]+)/?$#', $path, $match) === 1) {
            $slug = $match[1];

            return $slug !== 'sitemap' ? $slug : null;
        }

        return null;
    }
}
