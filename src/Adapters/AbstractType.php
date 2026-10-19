<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters;

use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Fetch\ChallengeDetector;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractType
{
    protected function htmlFromResponse(ResponseInterface $response, string $site, string $context): string
    {
        $html = (string) $response->getBody();

        if ($response->getStatusCode() >= 400 || $this->isChallengeResponse($response, $html)) {
            throw new CrawlBlockedException("{$site} {$context} page is blocked or unavailable.");
        }

        if (trim($html) === '') {
            throw new CrawlParseException("{$site} {$context} page is empty.");
        }

        return $html;
    }

    protected function assertUsableMovieDetail(CrawlItemResultDto $item, string $site): void
    {
        /** @var array<string, mixed> $movie */
        $movie = is_array($item->meta['movie'] ?? null) ? $item->meta['movie'] : [];
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($movie['metadata'] ?? null) ? $movie['metadata'] : [];
        $hasTitle = is_string($movie['title'] ?? null) && trim($movie['title']) !== '';
        $hasUsableField = $this->filled($movie, 'cover_url') !== null
            || $this->filled($movie, 'description') !== null
            || $this->filled($metadata, 'download_url') !== null
            || $this->filled($movie, 'date') !== null
            || $this->hasList($movie, 'performers')
            || $this->hasList($movie, 'tags');

        if (! $hasTitle || ! $hasUsableField) {
            throw new CrawlParseException("{$site} detail page did not contain expected movie fields.");
        }
    }

    /**
     * @deprecated Use assertUsableMovieDetail() instead.
     */
    protected function assertUsableDetail(CrawlItemResultDto $item, string $site): void
    {
        $this->assertUsableMovieDetail($item, $site);
    }

    protected function assertUsablePerformerDetail(CrawlItemResultDto $item, string $site): void
    {
        /** @var array<string, mixed> $performer */
        $performer = is_array($item->meta['performer'] ?? null) ? $item->meta['performer'] : [];
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($performer['metadata'] ?? null) ? $performer['metadata'] : [];
        $name = is_string($performer['name'] ?? null) ? trim($performer['name']) : '';
        $externalId = is_string($performer['external_id'] ?? null) ? trim($performer['external_id']) : '';

        if ($name === '') {
            throw new CrawlParseException("{$site} performer detail is missing a valid name.");
        }

        if ($externalId === '') {
            throw new CrawlParseException("{$site} performer detail is missing a valid external ID.");
        }

        $hasBio = $this->filled($performer, 'profile_image_url') !== null
            || $this->filled($performer, 'birth_date_raw') !== null
            || $this->filled($performer, 'size_raw') !== null
            || $this->filled($performer, 'height_raw') !== null
            || $this->hasList($performer, 'tags')
            || (is_array($performer['raw_profile'] ?? null) && $performer['raw_profile'] !== [])
            || $this->filled($metadata, 'blood_type') !== null
            || $this->filled($metadata, 'birthplace') !== null;

        if (! $hasBio) {
            throw new CrawlParseException("{$site} performer detail has insufficient bio data.");
        }
    }

    private function isChallengeResponse(ResponseInterface $response, string $html): bool
    {
        // One definition of a challenge for the whole library: a real interstitial, not any page that merely
        // mentions Cloudflare (a Turnstile script on a normal page must not make the page "blocked").
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[(string) $name] = array_values($values);
        }

        return ChallengeDetector::isChallenge($html, $response->getStatusCode(), $headers);
    }

    /** @param array<string, mixed> $data */
    private function filled(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @param array<string, mixed> $data */
    private function hasList(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;

        return is_array($value) && $value !== [];
    }
}
