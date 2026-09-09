<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavDatabase\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\JavDatabase\Selectors;
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
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'javdatabase', 'performer_detail');
        $crawler = new Crawler($html, $request->url);

        $nameNode = $crawler->filter(Selectors::PERFORMER_NAME);
        if ($nameNode->count() === 0) {
            throw new \RuntimeException('Idol name element not found.');
        }

        $h1Text = $nameNode->first()->text('');
        $name = trim(preg_replace('/\s*-\s*JAV Profile/i', '', $h1Text) ?? $h1Text);

        $container = $nameNode->ancestors()->first();
        $containerHtml = $container->html();
        $rawProfile = $this->cleanText($container->text(''));

        $externalId = $this->externalId($request->url);

        $age = $this->nullableUnknown($this->extractField($containerHtml, 'Age:'));
        $birthDateRaw = $this->nullableUnknown($this->extractField($containerHtml, 'DOB:'));
        $debutRaw = $this->nullableUnknown($this->extractField($containerHtml, 'Debut:'));
        $birthplace = $this->nullableUnknown($this->extractField($containerHtml, 'Birthplace:'));
        $sign = $this->nullableUnknown($this->extractField($containerHtml, 'Sign:'));
        $blood = $this->nullableUnknown($this->extractField($containerHtml, 'Blood:'));
        $measurementsRaw = $this->nullableUnknown($this->extractField($containerHtml, 'Measurements:'));
        $cup = $this->nullableUnknown($this->extractField($containerHtml, 'Cup:'));
        $heightRaw = $this->nullableUnknown($this->extractField($containerHtml, 'Height:'));
        $shoeRaw = $this->nullableUnknown($this->extractField($containerHtml, 'Shoe Size:'));
        $hairLength = $this->nullableUnknown($this->extractField($containerHtml, 'Hair Length(s):'));
        $hairColor = $this->nullableUnknown($this->extractField($containerHtml, 'Hair Color(s):'));
        $jpName = $this->nullableUnknown($this->extractField($containerHtml, 'JP:'));

        $favoriteCountRaw = null;
        $favoriteBtn = $crawler->filter(Selectors::FAVORITE_BUTTON);
        if ($favoriteBtn->count() > 0) {
            $favAttr = $favoriteBtn->first()->attr('data-favoritecount');
            if (is_numeric($favAttr)) {
                $favoriteCountRaw = $favAttr;
            }
        }

        $tags = [];
        $parts = explode('<b>Tags:</b>', $containerHtml);
        if (count($parts) > 1) {
            $tagsHtml = explode('<b>JP:</b>', $parts[1])[0];
            if (preg_match_all('/<a[^>]*>(.*?)<\/a>/', $tagsHtml, $matches) > 0) {
                foreach ($matches[1] as $tagText) {
                    $tagTextCleaned = trim(strip_tags($tagText));
                    if ($tagTextCleaned !== '' && ! str_contains(strtolower($tagTextCleaned), 'suggest')) {
                        $tags[] = $tagTextCleaned;
                    }
                }
            }
        }

        $imgNode = $crawler->filter(Selectors::PERFORMER_IMAGE);
        $imageUrl = $imgNode->count() > 0 ? $imgNode->first()->attr('src') : null;
        $profileImageUrl = is_string($imageUrl) && trim($imageUrl) !== ''
            ? UriResolver::resolve($imageUrl, $request->url)
            : null;

        $sizeRaw = null;
        if ($measurementsRaw !== null && preg_match('/(\d{2,3})\s*[-\/]\s*(\d{2,3})\s*[-\/]\s*(\d{2,3})/', $measurementsRaw, $m) === 1) {
            $bust = $m[1];
            $waist = $m[2];
            $hip = $m[3];
            if ($cup !== null) {
                $sizeRaw = "B{$bust}({$cup}) W{$waist} H{$hip}";
            } else {
                $sizeRaw = "B{$bust} W{$waist} H{$hip}";
            }
        } elseif ($cup !== null) {
            $sizeRaw = "B00({$cup})";
        }

        $aliases = [];
        if ($jpName !== null) {
            $aliases[] = $jpName;
        }

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
            'favorite_count_raw' => $favoriteCountRaw,
            'raw_profile' => $rawProfile,
            'aliases' => $aliases !== [] ? $aliases : null,
            'tags' => $tags !== [] ? $tags : null,
            'age' => $age,
            'debut_date_raw' => $debutRaw,
            'zodiac_sign' => $sign,
            'shoe_size_raw' => $shoeRaw,
            'hair_length' => $hairLength,
            'hair_color' => $hairColor,
            'name_japanese' => $jpName,
        ], fn(mixed $value): bool => $value !== null && $value !== '');

        $item = PerformerDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $name,
            data: $data,
        );

        $this->assertUsablePerformerDetail($item, 'javdatabase');

        return $item;
    }

    private function extractField(string $html, string $label): ?string
    {
        $quotedLabel = preg_quote($label, '/');
        $pattern = '/<b>' . $quotedLabel . '<\/b>\s*(?:<[^>]+>)*\s*(.*?)\s*(?:<\/a>)?\s*(?:\s+-\s+|<br|<p|\n|$)/i';
        if (preg_match($pattern, $html, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    private function nullableUnknown(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $val = trim($value);
        if ($val === '' || $val === '?' || $val === '-' || strtolower($val) === 'n/a') {
            return null;
        }

        return $val;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/idols/([^/]+)/?#i', $path, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    private function cleanText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5)));
    }
}
