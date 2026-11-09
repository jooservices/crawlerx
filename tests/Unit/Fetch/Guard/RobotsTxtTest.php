<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Guard;

use JOOservices\CrawlerX\Fetch\Guard\RobotsTxt;
use JOOservices\CrawlerX\Fetch\Guard\RobotsTxtParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RobotsTxtTest extends TestCase
{
    public function test_specific_user_agent_group_wins_over_the_wildcard_group(): void
    {
        $robots = $this->robots('user-agents.txt');

        self::assertFalse($robots->allows('/nogoogle', 'Mozilla/5.0 Googlebot/2.1'));
        self::assertTrue($robots->allows('/private', 'Mozilla/5.0 Googlebot/2.1'));
        self::assertTrue($robots->allows('/nogoogle', 'Bingbot/2.0'));
        self::assertFalse($robots->allows('/private', 'Bingbot/2.0'));
    }

    public function test_allow_overrides_disallow_when_more_specific(): void
    {
        $robots = $this->robots('basic.txt');

        self::assertFalse($robots->allows('/admin', '*'));
        self::assertFalse($robots->allows('/administer', '*'));
        self::assertTrue($robots->allows('/admin/public', '*'));
        self::assertTrue($robots->allows('/admin/public/anything', '*'));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function wildcardPaths(): iterable
    {
        yield 'pdf is blocked' => ['/report.pdf', false];
        yield 'nested pdf is blocked' => ['/a/b/report.pdf', false];
        yield 'pdf with a trailing slash is allowed' => ['/report.pdf/', true];
        yield 'tmp subtree is blocked' => ['/tmp/whatever', false];
        yield 'tmp root itself is allowed' => ['/tmp', true];
        yield 'allowed tmp file overrides the subtree' => ['/tmp/index.html', true];
        yield 'anchored admin is blocked' => ['/admin', false];
        yield 'longer admin prefix is allowed' => ['/administrator', true];
    }

    #[DataProvider('wildcardPaths')]
    public function test_wildcards_and_end_of_string_anchors(string $path, bool $expected): void
    {
        self::assertSame($expected, $this->robots('wildcards.txt')->allows($path, '*'));
    }

    public function test_longest_match_wins_and_allow_beats_disallow_on_a_tie(): void
    {
        $robots = $this->robots('longest-match.txt');

        self::assertFalse($robots->allows('/photos', '*'));
        self::assertFalse($robots->allows('/photos/other', '*'));
        self::assertTrue($robots->allows('/photos/public/x', '*'));
        self::assertFalse($robots->allows('/photos/public/secret', '*'));
        self::assertFalse($robots->allows('/photos/public/secret/extra', '*'));
    }

    public function test_crawl_delay_is_resolved_per_matching_user_agent(): void
    {
        $robots = $this->robots('crawl-delay.txt');

        self::assertSame(7.5, $robots->crawlDelay('CrawlerX/1.7'));
        self::assertSame(2.0, $robots->crawlDelay('SomeOtherBot'));
        self::assertFalse($robots->allows('/crawl', 'CrawlerX/1.7'));
        self::assertTrue($robots->allows('/everyone', 'CrawlerX/1.7'));
    }

    public function test_blocking_rule_exposes_the_matched_pattern(): void
    {
        $rule = $this->robots('basic.txt')->blockingRule('/admin', '*');

        self::assertNotNull($rule);
        self::assertFalse($rule->allow);
        self::assertSame('/admin', $rule->pattern);
    }

    private function robots(string $fixture): RobotsTxt
    {
        $content = file_get_contents(__DIR__ . '/../../../Fixtures/robots/' . $fixture);

        self::assertNotFalse($content);

        return RobotsTxtParser::parse($content);
    }
}
