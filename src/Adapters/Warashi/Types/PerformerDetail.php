<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Warashi\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Warashi\Selectors;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

final class PerformerDetail extends AbstractType implements TypeInterface
{
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
    ) {
    }

    protected function htmlFromResponse(ResponseInterface $response, string $site, string $context): string
    {
        $html = (string) $response->getBody();

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException("{$site} {$context} page is blocked or unavailable.");
        }

        $isChallenge = str_contains($html, 'cf-browser-verification')
            || str_contains($html, 'cf-mitigated')
            || str_contains($html, 'Just a moment...')
            || str_contains($html, 'Attention Required!');

        if ($isChallenge) {
            throw new \RuntimeException("{$site} {$context} page is blocked or unavailable.");
        }

        if (trim($html) === '') {
            throw new \RuntimeException("{$site} {$context} page is empty.");
        }

        return $html;
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'warashi', 'performer_detail');
        $crawler = new Crawler($html, $request->url);

        $nameNode = $crawler->filter(Selectors::PERFORMER_NAME);
        if ($nameNode->count() === 0) {
            throw new \RuntimeException('Performer name element not found.');
        }

        $name = $this->normalizeText($nameNode->first()->text('')) ?? '';
        $jpName = $this->firstText($crawler, Selectors::PERFORMER_NAME_JAPANESE);
        $externalId = $this->externalId($request->url);

        $profileNode = $crawler->filter(Selectors::PROFILE_INFO);
        $profileCrawler = $profileNode->count() > 0 ? $profileNode->first() : $crawler;
        $rawProfile = $this->normalizeText($profileCrawler->text('')) ?? '';

        $birthDateRaw = $this->birthDateRaw($crawler);
        $birthplace = $this->firstText($crawler, Selectors::BIRTHPLACE);
        $zodiacSign = $this->labeledValue($profileCrawler, 'astrological sign');
        $measurementsRaw = $this->labeledValue($profileCrawler, 'measurements');
        $cup = $this->labeledValue($profileCrawler, 'cup size');
        $heightRaw = $this->heightRaw($crawler);
        $blood = $this->labeledValue($profileCrawler, 'blood type');
        $age = $this->labeledValue($profileCrawler, 'current age');
        $debutRaw = $this->labeledValue($profileCrawler, 'porn/AV activity');

        if ($age !== null && preg_match('/(\d+)/', $age, $match) === 1) {
            $age = $match[1];
        }

        $aliases = $this->aliases($crawler, $jpName);
        $tags = $this->tags($crawler);

        $imgNode = $crawler->filter(Selectors::PERFORMER_IMAGE);
        $imageUrl = $imgNode->count() > 0 ? $imgNode->first()->attr('src') : null;
        $profileImageUrl = is_string($imageUrl) && trim($imageUrl) !== ''
            ? UriResolver::resolve($imageUrl, $request->url)
            : null;

        $sizeRaw = $this->sizeRaw($measurementsRaw, $cup);

        $data = array_filter([
            'external_id' => $externalId,
            'external_url' => $request->url,
            'name' => $name,
            'profile_image_url' => $profileImageUrl,
            'birth_date_raw' => $birthDateRaw,
            'blood_type' => $blood,
            'birthplace' => $birthplace,
            'height_raw' => $heightRaw,
            'size_raw' => $sizeRaw,
            'raw_profile' => $rawProfile,
            'aliases' => $aliases !== [] ? $aliases : null,
            'tags' => $tags !== [] ? $tags : null,
            'age' => $age,
            'debut_date_raw' => $debutRaw,
            'zodiac_sign' => $zodiacSign,
            'name_japanese' => $jpName,
            'name_romaji' => $name,
        ], fn(mixed $value): bool => $value !== null && $value !== '');

        $item = PerformerDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $name,
            data: $data,
        );

        $this->assertUsablePerformerDetail($item, 'warashi');

        return $item;
    }

    private function birthDateRaw(Crawler $crawler): ?string
    {
        $node = $crawler->filter(Selectors::BIRTH_DATE);
        if ($node->count() === 0) {
            return null;
        }

        $content = $node->first()->attr('content');
        if (is_string($content) && trim($content) !== '') {
            return trim($content);
        }

        return $this->nullableUnknown($node->first()->text(''));
    }

    private function heightRaw(Crawler $crawler): ?string
    {
        $node = $crawler->filter(Selectors::HEIGHT_VALUE);
        if ($node->count() === 0) {
            return null;
        }

        $value = trim($node->first()->text(''));
        if ($value !== '' && is_numeric($value)) {
            return $value . ' cm';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function aliases(Crawler $crawler, ?string $jpName): array
    {
        $aliases = [];

        if ($jpName !== null && $jpName !== '') {
            $aliases[] = $jpName;
        }

        $crawler->filter(Selectors::ALIAS_LIST)->each(function (Crawler $node) use (&$aliases): void {
            $western = $node->filter('span[itemprop="additionalName"]')->first();
            if ($western->count() === 0) {
                return;
            }

            $name = $this->normalizeText($western->text(''));
            if ($name !== null && $name !== '') {
                $aliases[] = $name;
            }
        });

        return array_values(array_unique($aliases));
    }

    /**
     * @return list<string>
     */
    private function tags(Crawler $crawler): array
    {
        $tags = [];

        $crawler->filter(Selectors::TAGS)->each(function (Crawler $node) use (&$tags): void {
            $text = $this->normalizeText($node->text(''));
            if ($text !== null && $text !== '') {
                $tags[] = $text;
            }
        });

        return $tags;
    }

    private function labeledValue(Crawler $profile, string $label): ?string
    {
        $value = null;
        $prefix = strtolower($label) . ':';

        $profile->filter('p')->each(function (Crawler $node) use ($prefix, &$value): void {
            if ($value !== null) {
                return;
            }

            $text = $this->normalizeText($node->text('')) ?? '';
            if (! str_starts_with(strtolower($text), $prefix)) {
                return;
            }

            $value = trim(substr($text, strlen($prefix)));
        });

        return $this->nullableUnknown($value);
    }

    private function sizeRaw(?string $measurementsRaw, ?string $cup): ?string
    {
        if ($measurementsRaw === null) {
            return $cup !== null ? "B00({$cup})" : null;
        }

        if (preg_match('/JP\s*(\d{2,3})\s*[-–]\s*(\d{2,3})\s*[-–]\s*(\d{2,3})/i', $measurementsRaw, $match) === 1) {
            $bust = $match[1];
            $waist = $match[2];
            $hip = $match[3];
            if ($cup !== null) {
                return "B{$bust}({$cup}) W{$waist} H{$hip}";
            }

            return "B{$bust} W{$waist} H{$hip}";
        }

        return $measurementsRaw;
    }

    private function nullableUnknown(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $val = trim($value);
        if ($val === '' || $val === '?' || $val === '-' || strtolower($val) === 'n/a' || strtolower($val) === 'unknown') {
            return null;
        }

        return $val;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/asian-female-pornstar/(\d+)/?$#i', $path, $match) === 1) {
            return $match[1];
        }

        return null;
    }
}
