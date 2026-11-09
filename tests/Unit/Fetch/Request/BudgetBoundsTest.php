<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Request;

use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\HttpOptionsDto;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\PlaywrightProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\Budget\FetchBudget;
use JOOservices\CrawlerX\Fetch\Request\BudgetBounds;
use PHPUnit\Framework\TestCase;

final class BudgetBoundsTest extends TestCase
{
    public function test_profile_is_returned_as_is_for_non_browser_methods_or_without_playwright(): void
    {
        $budget = new FetchBudget(10);
        $withBrowser = $this->profile(new PlaywrightProfileDto());
        $without = $this->profile(null);

        self::assertSame($withBrowser, BudgetBounds::profile($withBrowser, FetchMethod::Http, $budget));
        self::assertSame($without, BudgetBounds::profile($without, FetchMethod::Playwright, $budget));
    }

    public function test_browser_timeouts_are_capped_by_the_method_timeout(): void
    {
        $budget = new FetchBudget(10);
        $seconds = $budget->timeoutSeconds(FetchMethod::Playwright);

        $bounded = BudgetBounds::profile($this->profile(new PlaywrightProfileDto()), FetchMethod::Playwright, $budget);

        self::assertSame(min(90000, $seconds * 1000), $bounded->playwright?->navigationTimeoutMs);
        self::assertSame(min(8000, max(0, ($seconds - 1) * 1000)), $bounded->playwright?->postWaitMs);
        self::assertSame(min(45000, max(1, ($seconds - 1) * 1000)), $bounded->playwright?->readyTimeoutMs);
    }

    public function test_http_options_get_the_smaller_timeout_and_keep_headers(): void
    {
        $budget = new FetchBudget(100);
        $options = new CrawlOptionsDto(http: new HttpOptionsDto(timeout: 3, verifySsl: false, headers: ['A' => 'b']));

        $bounded = BudgetBounds::options($options, FetchMethod::Http, $budget);

        self::assertSame(min(3, $budget->timeoutSeconds(FetchMethod::Http)), $bounded->http?->timeout);
        self::assertFalse($bounded->http?->verifySsl);
        self::assertSame(['A' => 'b'], $bounded->http?->headers);
        self::assertSame($budget->timeoutSeconds(FetchMethod::Http), $bounded->methodTimeoutSeconds);
    }

    public function test_storage_state_and_ready_markers_resolve_from_options_then_session(): void
    {
        $budget = new FetchBudget(100);

        $fromSession = BudgetBounds::options(null, FetchMethod::Playwright, $budget, ['m'], ['cookies' => []]);
        $fromOptions = BudgetBounds::options(
            new CrawlOptionsDto(storageState: ['own' => true], readyMarkers: ['opt']),
            FetchMethod::Playwright,
            $budget,
            [],
            ['cookies' => []],
        );

        self::assertSame(['cookies' => []], $fromSession->storageState);
        self::assertSame(['m'], $fromSession->readyMarkers);
        self::assertSame(['own' => true], $fromOptions->storageState);
        self::assertSame(['opt'], $fromOptions->readyMarkers);
    }

    private function profile(?PlaywrightProfileDto $playwright): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'sample',
            displayName: 'Sample',
            baseUrl: 'https://example.test',
            fetchProfile: FetchProfile::Adaptive,
            fetchChain: [],
            http: new HttpProfileDto(),
            playwright: $playwright,
        );
    }
}
