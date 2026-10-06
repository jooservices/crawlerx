<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Guard;

use Faker\Factory;
use JOOservices\CrawlerX\Fetch\Guard\HostCircuit;
use JOOservices\CrawlerX\Tests\Support\ArrayCache;
use PHPUnit\Framework\TestCase;

final class HostCircuitTest extends TestCase
{
    public function test_opens_for_five_minutes_after_ten_consecutive_failures(): void
    {
        $now = 100.0;
        $host = Factory::create()->unique()->domainName();
        $circuit = $this->circuit(new ArrayCache(), 'node-a', $now);

        for ($failure = 1; $failure < 10; $failure++) {
            $circuit->recordFailure($host);
            self::assertNull($circuit->beforeFetch($host));
        }

        $circuit->recordFailure($host);

        self::assertSame(300, $circuit->beforeFetch($host));
        $now += 75;
        self::assertSame(225, $circuit->beforeFetch($host));
    }

    public function test_only_one_half_open_probe_is_allowed_and_success_closes_the_circuit(): void
    {
        $now = 100.0;
        $host = Factory::create()->unique()->domainName();
        $circuit = $this->circuit(new ArrayCache(), 'node-a', $now);
        $this->open($circuit, $host);
        $now += 300;

        self::assertNull($circuit->beforeFetch($host));
        self::assertSame(30, $circuit->beforeFetch($host));

        $circuit->recordSuccess($host);

        self::assertNull($circuit->beforeFetch($host));
    }

    public function test_a_failed_half_open_probe_reopens_the_circuit(): void
    {
        $now = 100.0;
        $host = Factory::create()->unique()->domainName();
        $circuit = $this->circuit(new ArrayCache(), 'node-a', $now);
        $this->open($circuit, $host);
        $now += 300;
        self::assertNull($circuit->beforeFetch($host));

        $circuit->recordFailure($host);

        self::assertSame(300, $circuit->beforeFetch($host));
    }

    public function test_success_resets_consecutive_failures_while_closed(): void
    {
        $now = 100.0;
        $host = Factory::create()->unique()->domainName();
        $circuit = $this->circuit(new ArrayCache(), 'node-a', $now);

        for ($failure = 0; $failure < 9; $failure++) {
            $circuit->recordFailure($host);
        }
        $circuit->recordSuccess($host);
        $circuit->recordFailure($host);

        self::assertNull($circuit->beforeFetch($host));
    }

    public function test_circuit_state_is_isolated_by_host_and_node(): void
    {
        $now = 100.0;
        $host = Factory::create()->unique()->domainName();
        $cache = new ArrayCache();
        $nodeA = $this->circuit($cache, 'node-a', $now);
        $anotherWorkerOnNodeA = $this->circuit($cache, 'node-a', $now);
        $nodeB = $this->circuit($cache, 'node-b', $now);
        $this->open($nodeA, $host);

        self::assertSame(300, $nodeA->beforeFetch($host));
        self::assertSame(300, $anotherWorkerOnNodeA->beforeFetch($host));
        self::assertNull($nodeB->beforeFetch($host));
        self::assertNull($nodeA->beforeFetch(Factory::create()->unique()->domainName()));
    }

    private function circuit(ArrayCache $cache, string $node, float &$now): HostCircuit
    {
        return new HostCircuit(
            cache: $cache,
            nodeId: $node,
            clock: static function () use (&$now): float {
                return $now;
            },
        );
    }

    private function open(HostCircuit $circuit, string $host): void
    {
        for ($failure = 0; $failure < 10; $failure++) {
            $circuit->recordFailure($host);
        }
    }
}
