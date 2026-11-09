<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Request;

use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\Request\AuthWallDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthWallDetectorTest extends TestCase
{
    #[DataProvider('walls')]
    public function test_detects_login_walls(int $status, string $body, ?string $finalUrl, bool $expected): void
    {
        $result = new FetchResultDto(true, $body, $status, FetchMethod::Http, 0, false, $finalUrl);

        self::assertSame($expected, AuthWallDetector::isAuthRequired($result));
    }

    /** @return array<string, array{int, string, ?string, bool}> */
    public static function walls(): array
    {
        return [
            '401' => [401, '', null, true],
            'visible marker' => [200, '<p>Please   LOG IN to continue</p>', null, true],
            'marker inside script is ignored' => [200, '<script>var a = "login required";</script><p>ok</p>', null, false],
            'login path' => [200, '<p>form</p>', 'https://a.test/signin/', true],
            'plain page' => [200, '<p>hello</p>', 'https://a.test/video/1', false],
        ];
    }
}
