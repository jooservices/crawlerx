<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Guard;

use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\Guard\HostCircuit;
use JOOservices\CrawlerX\Fetch\Guard\HostCircuitReporter;
use JOOservices\CrawlerX\Tests\Support\ArrayCache;
use PHPUnit\Framework\TestCase;

final class HostCircuitReporterTest extends TestCase
{
    public function test_is_silent_without_circuit_or_host(): void
    {
        (new HostCircuitReporter())->failure('a.test');
        (new HostCircuitReporter($this->circuit()))->failure(null);

        $this->expectNotToPerformAssertions();
    }

    public function test_failures_open_the_circuit_and_success_resets_the_count(): void
    {
        $circuit = $this->circuit();
        $reporter = new HostCircuitReporter($circuit);

        for ($failure = 0; $failure < 9; $failure++) {
            $reporter->failure('a.test');
        }
        $reporter->success('a.test');
        $reporter->failure('a.test');

        self::assertNull($circuit->beforeFetch('a.test'));

        for ($failure = 0; $failure < 9; $failure++) {
            $reporter->failure('a.test');
        }

        self::assertSame(300, $circuit->beforeFetch('a.test'));
    }

    public function test_terminal_counts_as_success_except_a_throttle_answer_after_failures(): void
    {
        $circuit = $this->circuit();
        $reporter = new HostCircuitReporter($circuit);
        $plain = new FetchResultDto(false, '', 404, FetchMethod::Http, 1, false);
        $throttled = new FetchResultDto(false, '', 429, FetchMethod::Http, 1, false, headers: ['X-CrawlerX-Guard' => ['throttle']]);

        for ($failure = 0; $failure < 9; $failure++) {
            $reporter->failure('a.test');
        }
        $reporter->terminal($throttled, false, 'a.test');
        $reporter->terminal($throttled, true, 'a.test');

        self::assertSame(300, $circuit->beforeFetch('a.test'));

        $reporter->terminal($plain, true, 'a.test');
        $reporter->failure('b.test');

        self::assertNull($circuit->beforeFetch('b.test'));
    }

    private function circuit(): HostCircuit
    {
        return new HostCircuit(cache: new ArrayCache(), clock: static fn(): float => 100.0);
    }
}
