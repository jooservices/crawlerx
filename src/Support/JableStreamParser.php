<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Support;

use DateTimeImmutable;

final class JableStreamParser
{
    /**
     * @return array{manifest_url: string, video_id: ?string, expires_at: ?string, poster_url: ?string}|null
     */
    public static function parse(string $html): ?array
    {
        if (preg_match("/var hlsUrl = '([^']+)'/", $html, $matches) !== 1) {
            return null;
        }

        $manifestUrl = trim($matches[1]);
        if ($manifestUrl === '') {
            return null;
        }

        $videoId = null;
        if (preg_match("/videoId: '(\d+)'/", $html, $videoMatches) === 1) {
            $videoId = $videoMatches[1];
        }

        $posterUrl = null;
        if (preg_match('/poster="([^"]+)"/', $html, $posterMatches) === 1) {
            $posterUrl = $posterMatches[1];
        }

        $expiresAt = self::expiresAtFromManifestUrl($manifestUrl);

        return [
            'manifest_url' => $manifestUrl,
            'video_id' => $videoId,
            'expires_at' => $expiresAt?->format(DATE_ATOM),
            'poster_url' => $posterUrl,
        ];
    }

    public static function expiresAtFromManifestUrl(string $manifestUrl): ?DateTimeImmutable
    {
        $path = parse_url($manifestUrl, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            static fn(string $segment): bool => $segment !== '',
        ));
        foreach ($segments as $segment) {
            if (preg_match('/^\d{10}$/', $segment) === 1) {
                $timestamp = (int) $segment;

                return (new DateTimeImmutable())->setTimestamp($timestamp);
            }
        }

        return null;
    }
}
