<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use InvalidArgumentException;
use JOOservices\CrawlerX\Services\Import\ImportUrlNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImportUrlNormalizationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array{url: string, current_page: int}}>
     */
    public static function normalizableUrls(): iterable
    {
        yield 'query page' => [
            'https://crawlerx.test/list/?query=live&page=3',
            ['url' => 'https://crawlerx.test/list/?query=live', 'current_page' => 3],
        ];
        yield 'path page' => [
            'https://crawlerx.test/list/page/4/?query=live',
            ['url' => 'https://crawlerx.test/list?query=live', 'current_page' => 4],
        ];
        yield 'credentials and port' => [
            'https://reader:example@crawlerx.test:8443/list/?page=0',
            ['url' => 'https://reader:example@crawlerx.test:8443/list/', 'current_page' => 1],
        ];
    }

    #[DataProvider('normalizableUrls')]
    public function test_normalizes_import_url_and_preserves_non_pagination_parts(string $url, array $expected): void
    {
        self::assertSame($expected, (new ImportUrlNormalizer())->normalize($url));
    }

    public function test_rejects_empty_and_relative_import_urls(): void
    {
        $normalizer = new ImportUrlNormalizer();

        try {
            $normalizer->normalize('');
            self::fail('Expected empty import URL to be rejected.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        $normalizer->normalize('/list/');
    }
}
