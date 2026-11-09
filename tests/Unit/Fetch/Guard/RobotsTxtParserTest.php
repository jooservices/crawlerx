<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Guard;

use JOOservices\CrawlerX\Fetch\Guard\RobotsTxtGroup;
use JOOservices\CrawlerX\Fetch\Guard\RobotsTxtParser;
use JOOservices\CrawlerX\Fetch\Guard\RobotsTxtRule;
use PHPUnit\Framework\TestCase;

final class RobotsTxtParserTest extends TestCase
{
    public function test_parses_user_agent_groups_rules_and_crawl_delay(): void
    {
        $robots = RobotsTxtParser::parse(
            "User-agent: Googlebot\n"
            . "Disallow: /nogoogle\n"
            . "Crawl-delay: 3\n\n"
            . "User-agent: *\n"
            . "Disallow: /private\n"
            . "Allow: /private/public\n",
        );

        self::assertCount(2, $robots->groups);

        $google = $robots->groups[0];
        self::assertInstanceOf(RobotsTxtGroup::class, $google);
        self::assertSame(['Googlebot'], $google->userAgents);
        self::assertSame(3.0, $google->crawlDelay);
        self::assertCount(1, $google->rules);
        self::assertRule(false, '/nogoogle', $google->rules[0]);

        $wildcard = $robots->groups[1];
        self::assertSame(['*'], $wildcard->userAgents);
        self::assertNull($wildcard->crawlDelay);
        self::assertCount(2, $wildcard->rules);
        self::assertRule(false, '/private', $wildcard->rules[0]);
        self::assertRule(true, '/private/public', $wildcard->rules[1]);
    }

    public function test_multiple_user_agent_lines_share_one_group(): void
    {
        $robots = RobotsTxtParser::parse(
            "User-agent: Googlebot\n"
            . "User-agent: Bingbot\n"
            . "Disallow: /shared\n",
        );

        self::assertCount(1, $robots->groups);
        self::assertSame(['Googlebot', 'Bingbot'], $robots->groups[0]->userAgents);
        self::assertCount(1, $robots->groups[0]->rules);
    }

    public function test_comments_are_stripped_and_directives_are_case_insensitive(): void
    {
        $robots = RobotsTxtParser::parse(
            "# leading comment\n"
            . "user-agent: *\n"
            . "DISALLOW: /secret # inline comment\n",
        );

        self::assertCount(1, $robots->groups);
        self::assertSame(['*'], $robots->groups[0]->userAgents);
        self::assertCount(1, $robots->groups[0]->rules);
        self::assertRule(false, '/secret', $robots->groups[0]->rules[0]);
    }

    public function test_empty_disallow_and_rules_before_a_user_agent_are_ignored(): void
    {
        $robots = RobotsTxtParser::parse(
            "Disallow: /orphan\n"
            . "User-agent: *\n"
            . "Disallow:\n"
            . "Allow:\n",
        );

        self::assertCount(1, $robots->groups);
        self::assertSame([], $robots->groups[0]->rules);
    }

    public function test_malformed_lines_and_unknown_directives_are_ignored(): void
    {
        $robots = RobotsTxtParser::parse(
            "User-agent: *\n"
            . "Sitemap: https://example.test/sitemap.xml\n"
            . "no-colon-here\n"
            . "Crawl-delay: soon\n"
            . "Disallow: /kept\n",
        );

        self::assertCount(1, $robots->groups);
        self::assertNull($robots->groups[0]->crawlDelay);
        self::assertCount(1, $robots->groups[0]->rules);
        self::assertRule(false, '/kept', $robots->groups[0]->rules[0]);
    }

    public function test_empty_content_yields_no_groups(): void
    {
        self::assertSame([], RobotsTxtParser::parse('')->groups);
    }

    private static function assertRule(bool $allow, string $pattern, RobotsTxtRule $rule): void
    {
        self::assertSame($allow, $rule->allow);
        self::assertSame($pattern, $rule->pattern);
    }
}
