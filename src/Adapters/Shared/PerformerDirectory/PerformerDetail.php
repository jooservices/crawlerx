<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\PerformerDirectory;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use Symfony\Component\DomCrawler\Crawler;

final class PerformerDetail extends AbstractType implements TypeInterface
{
    use NormalizesUrls;
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly PerformerDirectoryDefinition $definition,
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), $this->definition->label, 'performer_detail');
        $crawler = new Crawler($html, $request->url);
        $fields = $this->fields($crawler);
        $name = $this->name($crawler) ?? $this->definition->externalId($request->url);

        $item = PerformerDto::item(
            url: $request->url,
            externalId: $this->definition->externalId($request->url),
            title: $name,
            data: [
                'profile_image_url' => $this->image($crawler, $request->url),
                'birth_date_raw' => $this->field($fields, ['生年月日', 'Birthday', 'Birth date']),
                'height_raw' => $this->field($fields, ['身長', 'Height']),
                'size_raw' => $this->field($fields, ['スリーサイズ', 'Size']),
                'raw_profile' => $fields,
                'metadata' => $fields,
            ],
        );

        $this->assertUsablePerformerDetail($item, $this->definition->label);

        return $item;
    }

    private function name(Crawler $crawler): ?string
    {
        foreach ($this->definition->titleSelectors as $selector) {
            $name = $this->firstText($crawler, $selector);
            if ($name !== null) {
                return trim(explode('｜', $name)[0]);
            }
        }

        return $this->firstAttribute($crawler, 'meta[property="og:title"]', 'content');
    }

    private function image(Crawler $crawler, string $baseUrl): ?string
    {
        foreach ($this->definition->imageSelectors as $selector) {
            $src = $this->firstAttribute($crawler, $selector, 'src');
            if ($src !== null) {
                return $this->absolute($baseUrl, $src);
            }
        }

        return null;
    }

    /** @return array<string, string> */
    private function fields(Crawler $crawler): array
    {
        $fields = [];
        $crawler->filter('dt')->each(function (Crawler $label) use (&$fields): void {
            $key = $this->normalizeText($label->text(''));
            $value = $label->nextAll()->first();
            $text = $value->count() > 0 ? $this->normalizeText($value->text('')) : null;
            if ($key !== null && $text !== null) {
                $fields[rtrim($key, ':')] = $text;
            }
        });

        return $fields;
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $keys
     */
    private function field(array $fields, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($fields[$key])) {
                return $fields[$key];
            }
        }

        return null;
    }
}
